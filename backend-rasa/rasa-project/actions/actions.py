import logging
import re
from typing import Any, Dict, List, Optional, Text

import mysql.connector
from rasa_sdk import Action, Tracker
from rasa_sdk.events import SlotSet
from rasa_sdk.executor import CollectingDispatcher

logger = logging.getLogger(__name__)

# ====== SESUAIKAN JIKA PERLU ======
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "db_warung_mogan",
}
# ==================================

KATEGORI_VALID = ["makanan", "minuman", "cemilan", "dessert"]
KATA_KUNCI_JENIS_MENU = [
    "milk shake",
    "es krim",
    "nasi goreng",
    "tipat",
    "bakmie",
    "juice",
    "kopi",
    "coffee",
    "rujak",
    "bubur",
    "sate",
    "bakso",
    "nasi",
    "mie",
    "teh",
    "es",
]
KATA_KUNCI_PEDAS = [
    "pedas",
    "pedes",
    "geprek",
    "balado",
    "plecing",
    "cabe",
    "sambal",
    "sere tabia",
    "penyet",
]
KATA_KUNCI_SEGAR = [
    "es ",
    "juice",
    "kelapa",
    "jeruk",
    "lemon",
    "soda",
    "milk shake",
    "shake",
    "cendol",
    "daluman",
    "teler",
    "campur",
    "lidah buaya",
]
KATA_KUNCI_KOPI = ["kopi", "coffee", "latte", "cappuccino", "americano"]
KATA_KUNCI_JUS = ["juice", "jus"]
KATA_KUNCI_SHAKE = ["shake", "milk shake", "milkshake"]
KATA_KUNCI_HANGAT = ["hangat", "hot ", "kopi bali", "kopi nescafe", "white coffee", "kopi luwak"]

# Daftar kata kunci menu andalan (tetap divalidasi langsung ke database agar harga & ketersediaan nyata)
ANDALAN_MAKANAN = [
    "nasi campur bali",
    "nasi bakar",
    "nasi genyol vegan",
    "nasi kuning",
    "sate jamur",
    "tipat kables",
    "bakmie bakso",
    "jamur geprek",
]
ANDALAN_MINUMAN = [
    "es mogan",
    "es daluman",
    "es teler",
    "es kelapa muda",
    "es jeruk",
    "goki coffee brown sugar",
    "juice alpokat",
]
ANDALAN_CEMILAN = [
    "singkong salju",
    "roti bakar mogan",
    "tahu crispy",
    "jamur crispy",
    "pisang keju",
    "godoh crispy",
    "kentang goreng",
]
ANDALAN_DESSERT = [
    "salad buah",
    "es krim goreng",
    "banana split",
    "sandae",
    "cone soft milk",
    "bubur injin",
]

CATATAN_PAJAK = "Harga belum termasuk pajak dan service 12%."
PESAN_ERROR = "Maaf, terjadi gangguan saat mengambil data. Silakan coba lagi."

_KOLOM_MENU_CACHE: Optional[set] = None
_HAS_CATATAN_DETAIL_CACHE: Optional[bool] = None


def jalankan_query(sql: str, params=None):
    koneksi = mysql.connector.connect(**DB_CONFIG)
    try:
        cur = koneksi.cursor(dictionary=True)
        try:
            if params:
                cur.execute(sql, params)
            else:
                cur.execute(sql)
            return cur.fetchall()
        finally:
            cur.close()
    finally:
        koneksi.close()


def ambil_kolom_menu() -> set:
    global _KOLOM_MENU_CACHE
    if _KOLOM_MENU_CACHE is not None:
        return _KOLOM_MENU_CACHE
    rows = jalankan_query("SHOW COLUMNS FROM menu")
    _KOLOM_MENU_CACHE = {str(r.get("Field", "")).lower() for r in rows}
    return _KOLOM_MENU_CACHE


def cek_kolom_catatan_detail() -> bool:
    global _HAS_CATATAN_DETAIL_CACHE
    if _HAS_CATATAN_DETAIL_CACHE is not None:
        return _HAS_CATATAN_DETAIL_CACHE
    rows = jalankan_query("SHOW COLUMNS FROM detail_keranjang LIKE 'catatan'")
    _HAS_CATATAN_DETAIL_CACHE = len(rows) > 0
    return _HAS_CATATAN_DETAIL_CACHE


def kolom_kategori_sql() -> str:
    cols = ambil_kolom_menu()
    if "kategori" in cols:
        return "COALESCE(kategori, '')"
    if "jenis_menu" in cols:
        return "COALESCE(jenis_menu, '')"
    return "''"


def kolom_deskripsi_sql() -> str:
    cols = ambil_kolom_menu()
    if "deskripsi" in cols:
        return "COALESCE(deskripsi, '')"
    if "keterangan" in cols:
        return "COALESCE(keterangan, '')"
    return "''"


def rupiah(angka) -> str:
    return "Rp" + f"{int(round(float(angka))):,}".replace(",", ".")


def daftar_menu(baris, batas: int = 25) -> str:
    items = baris[:batas]
    return "\n".join(f"- {b['nama_menu']} ({rupiah(b['harga'])})" for b in items)


def ambil_angka_jumlah(nilai_jumlah, default: int = 1) -> int:
    if nilai_jumlah is None:
        return default
    match = re.search(r"\d+", str(nilai_jumlah))
    if not match:
        return default
    jumlah_int = int(match.group())
    return jumlah_int if jumlah_int > 0 else default


def deteksi_bungkus(teks: str) -> bool:
    if not teks:
        return False
    teks_lower = teks.lower()
    return bool(
        re.search(r"\b(bungkus|bungkusin|bks|dibungkus|take\s*away|pulang)\b", teks_lower)
    )


def ekstrak_catatan_pesanan(
    teks: str, nama_menu: str = "", nama_asli: str = ""
) -> Optional[str]:
    """Mengambil keterangan/catatan khusus pelanggan dari kalimat pesanan."""
    if not teks:
        return None
    sisa = teks.lower()

    # Hapus nama menu (baik dari entitas maupun nama asli di database)
    for nama in sorted({nama_menu, nama_asli}, key=len, reverse=True):
        if nama:
            sisa = re.sub(rf"\b{re.escape(nama.lower())}\b", " ", sisa)

    # Hapus info nomor meja
    sisa = re.sub(r"\b(untuk\s+|di\s+|ke\s+)?meja\s*(nomor\s+|no\.?\s*)?\d+\b", " ", sisa)

    # Hapus info mode saji (sudah ditangani terpisah oleh deteksi_bungkus)
    sisa = re.sub(
        r"\b(bungkusin|dibungkus|bungkus|bks|take\s*away|bawa\s+pulang|pulang|makan\s+di\s+tempat|makan\s+sini|dine\s*in)\b",
        " ",
        sisa,
    )

    # Hapus angka jumlah dan satuannya (misal: "1", "2 porsi", "1 gelas")
    sisa = re.sub(
        r"\b\d+([.,]\d+)?\s*(porsi|gelas|cup|botol|buah|pcs|biji|piring|mangkok|mangkuk)?\b",
        " ",
        sisa,
    )

    # Hapus kata kerja pemesanan dan kata pengisi di awal/akhir kalimat
    sisa = re.sub(
        r"\b(tolong|pesankan|pesan|pesen|order|beli|saya|aku|mau|ingin|tambah|tambahan|sebanyak|dong|yaa|ya|kak|min|deh|aja|nih|disini|di\s+sini|catatan|note|keterangan)\b",
        " ",
        sisa,
    )

    # Rapikan tanda baca dan spasi berlebih
    sisa = re.sub(r"[,.;:!?()]+", " ", sisa)
    sisa = re.sub(r"\s+", " ", sisa).strip()

    # Hapus kata sambung yang tersisa di paling awal (misal: "yang tanpa nanas" -> "tanpa nanas")
    sisa = re.sub(r"^(yang|tapi|dengan|buat|untuk)\s+", "", sisa).strip()

    return sisa if len(sisa) >= 3 else None


def deteksi_kategori(teks: str) -> Optional[str]:
    if not teks:
        return None
    teks_lower = teks.lower()
    if "dessert" in teks_lower or "penutup" in teks_lower:
        return "dessert"
    if "cemilan" in teks_lower or "snack" in teks_lower or "gorengan" in teks_lower or "ngemil" in teks_lower:
        return "cemilan"
    if "minuman" in teks_lower or "minum" in teks_lower or "drink" in teks_lower:
        return "minuman"
    if "makanan" in teks_lower or "makan" in teks_lower or "food" in teks_lower:
        return "makanan"
    for kat in KATEGORI_VALID:
        if re.search(rf"\b{re.escape(kat)}\b", teks_lower):
            return kat
    return None


def deteksi_kata_kunci_jenis(teks: str) -> Optional[str]:
    if not teks:
        return None
    teks_lower = teks.lower()
    for kata in KATA_KUNCI_JENIS_MENU:
        if re.search(rf"\b{re.escape(kata.strip())}\b", teks_lower):
            return kata.strip()
    return None


def deteksi_mode_ekstrem(teks: str) -> Optional[str]:
    if not teks:
        return None
    teks_lower = teks.lower()
    if any(
        k in teks_lower
        for k in ["termahal", "paling mahal", "harga tertinggi", "paling tinggi", "mahal"]
    ):
        return "termahal"
    if any(
        k in teks_lower
        for k in ["termurah", "paling murah", "harga terendah", "paling rendah", "murah"]
    ):
        return "termurah"
    return None


def bersihkan_nama_menu(teks_menu: str) -> str:
    bersih = re.sub(
        r"\b(pesan|pesen|order|beli|bungkus|bungkusin|dibungkus|bks|porsi|gelas|harga|berapa|biaya|cek|menu|nya|ya|dong|kah|saya|mau|batalkan|batal|batalin|cancel|gajadi|aja|deh|tadi|cocok|dengan|buat|untuk|minuman|makanan|cemilan|penutup|dessert)\b",
        " ",
        str(teks_menu).lower(),
    )
    bersih = re.sub(r"\s+", " ", bersih).strip()
    return bersih or str(teks_menu).strip()


def cari_menu_cocok(kata_kunci: str):
    bersih = bersihkan_nama_menu(kata_kunci).lower()
    if not bersih:
        return []

    exact = jalankan_query(
        "SELECT id_menu, nama_menu, harga FROM menu WHERE LOWER(nama_menu) = %s ORDER BY id_menu ASC",
        (bersih,),
    )
    if exact:
        return exact

    return jalankan_query(
        "SELECT id_menu, nama_menu, harga FROM menu WHERE LOWER(nama_menu) LIKE %s ORDER BY id_menu ASC",
        (f"%{bersih}%",),
    )


def ambil_menu_berdasarkan_kategori(kategori: str, limit: int = 100):
    kat_sql = kolom_kategori_sql()
    kat_lower = kategori.lower()
    batas = int(limit)

    rows = jalankan_query(
        f"SELECT nama_menu, harga FROM menu WHERE LOWER({kat_sql}) LIKE %s ORDER BY id_menu ASC LIMIT %s",
        (f"%{kat_lower}%", batas),
    )
    if rows:
        return rows

    # Fallback berdasarkan nama menu jika kolom kategori di DB kosong/tidak seragam
    if kat_lower == "dessert":
        return jalankan_query(
            "SELECT nama_menu, harga FROM menu "
            "WHERE LOWER(nama_menu) LIKE '%es krim%' OR LOWER(nama_menu) LIKE '%sandae%' "
            "OR LOWER(nama_menu) LIKE '%cone%' OR LOWER(nama_menu) LIKE '%banana split%' "
            "OR LOWER(nama_menu) LIKE '%bubur%' OR LOWER(nama_menu) LIKE '%salad%' "
            "OR LOWER(nama_menu) LIKE '%buah potong%' "
            f"ORDER BY id_menu ASC LIMIT {batas}"
        )
    if kat_lower == "cemilan":
        return jalankan_query(
            "SELECT nama_menu, harga FROM menu "
            "WHERE LOWER(nama_menu) LIKE '%crispy%' OR LOWER(nama_menu) LIKE '%pisang%' "
            "OR LOWER(nama_menu) LIKE '%roti bakar%' OR LOWER(nama_menu) LIKE '%rujak%' "
            "OR LOWER(nama_menu) LIKE '%singkong%' OR LOWER(nama_menu) LIKE '%donut%' "
            "OR LOWER(nama_menu) LIKE '%kentang%' OR LOWER(nama_menu) LIKE '%risoles%' "
            "OR LOWER(nama_menu) LIKE '%burger%' OR LOWER(nama_menu) LIKE '%jajan%' "
            "OR LOWER(nama_menu) LIKE '%laklak%' OR LOWER(nama_menu) LIKE '%tape%' "
            f"ORDER BY id_menu ASC LIMIT {batas}"
        )
    if kat_lower == "minuman":
        return jalankan_query(
            "SELECT nama_menu, harga FROM menu "
            "WHERE LOWER(nama_menu) LIKE '%es %' OR LOWER(nama_menu) LIKE '%juice%' "
            "OR LOWER(nama_menu) LIKE '%kopi%' OR LOWER(nama_menu) LIKE '%coffee%' "
            "OR LOWER(nama_menu) LIKE '%teh%' OR LOWER(nama_menu) LIKE '%shake%' "
            "OR LOWER(nama_menu) LIKE '%susu%' OR LOWER(nama_menu) LIKE '%kelapa%' "
            "OR LOWER(nama_menu) LIKE '%hangat%' OR LOWER(nama_menu) LIKE '%hot %' "
            "OR LOWER(nama_menu) LIKE '%soda%' OR LOWER(nama_menu) LIKE '%milk%' "
            f"ORDER BY id_menu ASC LIMIT {batas}"
        )
    if kat_lower == "makanan":
        return jalankan_query(
            "SELECT nama_menu, harga FROM menu "
            "WHERE LOWER(nama_menu) LIKE '%nasi%' OR LOWER(nama_menu) LIKE '%tipat%' "
            "OR LOWER(nama_menu) LIKE '%bakmie%' OR LOWER(nama_menu) LIKE '%mie%' "
            "OR LOWER(nama_menu) LIKE '%sate%' OR LOWER(nama_menu) LIKE '%bakso%' "
            "OR LOWER(nama_menu) LIKE '%soto%' OR LOWER(nama_menu) LIKE '%capcai%' "
            "OR LOWER(nama_menu) LIKE '%gado%' OR LOWER(nama_menu) LIKE '%geprek%' "
            "OR LOWER(nama_menu) LIKE '%penyet%' OR LOWER(nama_menu) LIKE '%karedok%' "
            f"ORDER BY id_menu ASC LIMIT {batas}"
        )
    return []


def pilih_menu_prioritas(daftar_baris: list, daftar_kunci: list, batas: int = 5) -> list:
    """Memilih menu nyata dari hasil query DB berdasarkan urutan prioritas kata kunci."""
    hasil: List[Dict[str, Any]] = []
    sudah_ada = set()

    for kunci in daftar_kunci:
        for item in daftar_baris:
            nama_lower = item["nama_menu"].lower()
            if kunci in nama_lower and nama_lower not in sudah_ada:
                hasil.append(item)
                sudah_ada.add(nama_lower)
                break
        if len(hasil) >= batas:
            break

    if len(hasil) < batas:
        for item in daftar_baris:
            nama_lower = item["nama_menu"].lower()
            if nama_lower not in sudah_ada:
                hasil.append(item)
                sudah_ada.add(nama_lower)
            if len(hasil) >= batas:
                break

    return hasil[:batas]


def rekomendasi_pairing_menu(teks_acuan: str):
    """Memberikan saran minuman, dessert, atau makanan/cemilan yang cocok dari DB."""
    teks = (teks_acuan or "").lower()
    is_pedas = any(k in teks for k in KATA_KUNCI_PEDAS)
    is_gurih_berat = any(
        k in teks
        for k in [
            "goreng",
            "bakar",
            "sate",
            "tipat",
            "nasi",
            "bakso",
            "mie",
            "bakmie",
            "rujak",
            "padang",
            "soto",
        ]
    )
    is_kopi_atau_hangat = any(
        k in teks for k in ["kopi", "coffee", "latte", "cappuccino", "americano", "teh hangat", "jahe", "hot milk", "shake"]
    )

    semua_minuman = ambil_menu_berdasarkan_kategori("minuman", limit=100)
    if is_pedas:
        kunci_minum = ["es daluman", "es kelapa muda", "es teler", "es jeruk", "milk shake", "juice alpokat"]
        minuman_saran = pilih_menu_prioritas(semua_minuman, kunci_minum, batas=5)
    elif is_gurih_berat:
        kunci_minum = ["es jeruk", "es tea", "es lemon tea", "es kelapa muda", "es daluman", "es mogan", "juice"]
        minuman_saran = pilih_menu_prioritas(semua_minuman, kunci_minum, batas=5)
    else:
        minuman_saran = pilih_menu_prioritas(semua_minuman, ANDALAN_MINUMAN, batas=5)

    semua_dessert = ambil_menu_berdasarkan_kategori("dessert", limit=50)
    dessert_saran = pilih_menu_prioritas(semua_dessert, ANDALAN_DESSERT, batas=4)

    if is_kopi_atau_hangat:
        semua_cemilan = ambil_menu_berdasarkan_kategori("cemilan", limit=50)
        makanan_saran = pilih_menu_prioritas(semua_cemilan, ANDALAN_CEMILAN, batas=5)
    else:
        semua_makanan = ambil_menu_berdasarkan_kategori("makanan", limit=80)
        makanan_saran = pilih_menu_prioritas(semua_makanan, ANDALAN_MAKANAN, batas=5)

    return minuman_saran, dessert_saran, makanan_saran


def hitung_ulang_subtotal_keranjang(cur, id_keranjang: int) -> float:
    cur.execute(
        "SELECT COALESCE(SUM(jumlah * harga_satuan), 0) AS subtotal "
        "FROM detail_keranjang WHERE id_keranjang = %s",
        (id_keranjang,),
    )
    row = cur.fetchone()
    return float(row["subtotal"] if row and row["subtotal"] is not None else 0.0)


def ambil_pesanan_aktif_terakhir(nomor_meja: str):
    pesanan = jalankan_query(
        "SELECT id_keranjang, total_harga FROM keranjang "
        "WHERE nomor_meja = %s AND status = 'menunggu' "
        "ORDER BY id_keranjang DESC LIMIT 1",
        (nomor_meja,),
    )
    if not pesanan:
        return None, []
    id_keranjang = pesanan[0]["id_keranjang"]

    if cek_kolom_catatan_detail():
        detail = jalankan_query(
            "SELECT nama_pesanan, SUM(jumlah) AS jumlah, harga_satuan, COALESCE(catatan, '') AS catatan "
            "FROM detail_keranjang WHERE id_keranjang = %s "
            "GROUP BY nama_pesanan, harga_satuan, COALESCE(catatan, '') "
            "ORDER BY MIN(id_detail_keranjang) ASC",
            (id_keranjang,),
        )
    else:
        detail = jalankan_query(
            "SELECT nama_pesanan, SUM(jumlah) AS jumlah, harga_satuan, '' AS catatan "
            "FROM detail_keranjang WHERE id_keranjang = %s "
            "GROUP BY nama_pesanan, harga_satuan ORDER BY MIN(id_detail_keranjang) ASC",
            (id_keranjang,),
        )
    return pesanan[0], detail


class ActionTampilkanMenu(Action):
    def name(self) -> Text:
        return "action_tampilkan_menu"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        teks_user = tracker.latest_message.get("text") or ""
        teks_lower = teks_user.lower()
        kategori_slot = tracker.get_slot("kategori") or next(
            tracker.get_latest_entity_values("kategori"), None
        )
        kategori = deteksi_kategori(f"{kategori_slot or ''} {teks_user}")
        kata_jenis = deteksi_kata_kunci_jenis(teks_user)

        try:
            if any(k in teks_lower for k in ["kategori apa", "jenis menu apa", "bagian menu"]):
                if not kategori and not kata_jenis:
                    dispatcher.utter_message(
                        text=(
                            "Kategori menu di Warung Mogan:\n"
                            "- Makanan (nasi, tipat, bakmie, sate, bakso, dll.)\n"
                            "- Minuman (kopi, jus, es segar, milkshake, minuman hangat)\n"
                            "- Cemilan (crispy, roti bakar, pisang keju, rujak, dll.)\n"
                            "- Dessert (es krim, sandae, salad buah, bubur, dll.)\n"
                            "Silakan sebutkan kategori atau varian yang ingin dilihat."
                        )
                    )
                    return [SlotSet("kategori", None)]

            if kata_jenis:
                baris = jalankan_query(
                    "SELECT nama_menu, harga FROM menu WHERE LOWER(nama_menu) LIKE %s ORDER BY id_menu ASC",
                    (f"%{kata_jenis.lower()}%",),
                )
                if baris:
                    dispatcher.utter_message(
                        text=(
                            f"Varian '{kata_jenis}' di Warung Mogan:\n"
                            f"{daftar_menu(baris, batas=30)}\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                    return [SlotSet("kategori", None)]

            if kategori:
                baris = ambil_menu_berdasarkan_kategori(kategori, limit=30)
                if not baris:
                    dispatcher.utter_message(
                        text=f"Maaf, belum ada menu {kategori} yang tersedia saat ini."
                    )
                    return [SlotSet("kategori", None)]
                dispatcher.utter_message(
                    text=(
                        f"Daftar {kategori} di Warung Mogan:\n"
                        f"{daftar_menu(baris, batas=30)}\n"
                        f"{CATATAN_PAJAK}"
                    )
                )
                return [SlotSet("kategori", None)]

            baris = jalankan_query(
                "SELECT nama_menu, harga FROM menu ORDER BY id_menu ASC LIMIT 30"
            )
            if not baris:
                dispatcher.utter_message(text="Maaf, daftar menu saat ini kosong.")
                return [SlotSet("kategori", None)]

            dispatcher.utter_message(
                text=(
                    f"Berikut beberapa menu di Warung Mogan:\n"
                    f"{daftar_menu(baris, batas=30)}\n"
                    f"{CATATAN_PAJAK}"
                )
            )
        except Exception as e:
            logger.error("Gagal tampilkan menu: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)

        return [SlotSet("kategori", None)]


class ActionCekHarga(Action):
    def name(self) -> Text:
        return "action_cek_harga"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        teks_user = tracker.latest_message.get("text") or ""
        nama_menu = tracker.get_slot("nama_menu") or next(
            tracker.get_latest_entity_values("nama_menu"), None
        )
        kategori_slot = tracker.get_slot("kategori") or next(
            tracker.get_latest_entity_values("kategori"), None
        )
        kategori = deteksi_kategori(f"{kategori_slot or ''} {teks_user}")
        mode_ekstrem = deteksi_mode_ekstrem(teks_user)
        kat_sql = kolom_kategori_sql()

        try:
            if mode_ekstrem:
                arah = "DESC" if mode_ekstrem == "termahal" else "ASC"
                if kategori:
                    baris = jalankan_query(
                        f"SELECT nama_menu, harga FROM menu "
                        f"WHERE LOWER({kat_sql}) LIKE %s "
                        f"ORDER BY harga {arah}, id_menu ASC LIMIT 1",
                        (f"%{kategori.lower()}%",),
                    )
                    if not baris:
                        baris = ambil_menu_berdasarkan_kategori(kategori, limit=100)
                        if baris:
                            baris = sorted(
                                baris,
                                key=lambda x: float(x["harga"]),
                                reverse=(mode_ekstrem == "termahal"),
                            )[:1]
                else:
                    baris = jalankan_query(
                        f"SELECT nama_menu, harga FROM menu ORDER BY harga {arah}, id_menu ASC LIMIT 1"
                    )

                if not baris:
                    label_kat = f" kategori {kategori}" if kategori else ""
                    dispatcher.utter_message(
                        text=f"Maaf, belum ada data menu{label_kat} yang tersedia."
                    )
                    return [SlotSet("nama_menu", None), SlotSet("kategori", None)]

                item = baris[0]
                label_sifat = "termahal" if mode_ekstrem == "termahal" else "termurah"
                label_kat = f" untuk kategori {kategori}" if kategori else ""
                dispatcher.utter_message(
                    text=(
                        f"Menu {label_sifat}{label_kat} di Warung Mogan adalah "
                        f"{item['nama_menu']} dengan harga {rupiah(item['harga'])}.\n"
                        f"{CATATAN_PAJAK}"
                    )
                )
                return [SlotSet("nama_menu", None), SlotSet("kategori", None)]

            if nama_menu:
                bersih = bersihkan_nama_menu(str(nama_menu))
                baris = cari_menu_cocok(str(nama_menu))
                if not baris:
                    dispatcher.utter_message(
                        text=f"Maaf, menu '{nama_menu}' tidak ditemukan di daftar menu Warung Mogan."
                    )
                    return [SlotSet("nama_menu", None), SlotSet("kategori", None)]

                if len(baris) == 1:
                    item = baris[0]
                    dispatcher.utter_message(
                        text=(
                            f"Harga {item['nama_menu']} adalah {rupiah(item['harga'])}.\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                else:
                    dispatcher.utter_message(
                        text=(
                            f"Berikut daftar harga menu yang cocok dengan '{bersih}':\n"
                            f"{daftar_menu(baris)}\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                return [SlotSet("nama_menu", None), SlotSet("kategori", None)]

            if kategori:
                baris = ambil_menu_berdasarkan_kategori(kategori, limit=25)
            else:
                baris = jalankan_query(
                    "SELECT nama_menu, harga FROM menu ORDER BY id_menu ASC LIMIT 25"
                )

            if not baris:
                dispatcher.utter_message(text="Maaf, daftar menu saat ini kosong.")
                return [SlotSet("nama_menu", None), SlotSet("kategori", None)]

            dispatcher.utter_message(
                text=f"Daftar harga menu Warung Mogan:\n{daftar_menu(baris)}\n{CATATAN_PAJAK}"
            )
        except Exception as e:
            logger.error("Gagal cek harga menu: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)

        return [SlotSet("nama_menu", None), SlotSet("kategori", None)]


class ActionRekomendasiMenu(Action):
    def name(self) -> Text:
        return "action_rekomendasi_menu"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        teks_user = tracker.latest_message.get("text") or ""
        teks_lower = teks_user.lower()
        nomor_meja = (
            tracker.get_slot("no_meja")
            or next(tracker.get_latest_entity_values("no_meja"), None)
            or "1"
        )

        try:
            # 1. Pertanyaan pairing (minuman cocok dengan makanan, atau makanan/cemilan cocok dengan minuman)
            kata_pairing = [
                "cocok",
                "pas buat",
                "pas dengan",
                "pas untuk",
                "teman minum",
                "teman makan",
                "nemenin",
                "bareng",
                "habis makan",
                "setelah makan",
                "enaknya minum",
                "enaknya makan",
                "bagusnya minum",
            ]
            if any(k in teks_lower for k in kata_pairing):
                _, detail_rows = ambil_pesanan_aktif_terakhir(nomor_meja)
                acuan_teks = teks_user
                if detail_rows:
                    acuan_teks += " " + " ".join(r["nama_pesanan"] for r in detail_rows)

                minuman_saran, dessert_saran, makanan_saran = rekomendasi_pairing_menu(acuan_teks)
                bagian: List[str] = []

                tanya_makanan = any(
                    k in teks_lower
                    for k in ["makanan yang cocok", "cemilan yang", "teman makan", "enaknya makan", "dimakan bareng", "dimakan sama", "saran makanan"]
                )
                tanya_dessert = any(k in teks_lower for k in ["dessert", "penutup"])

                if tanya_makanan:
                    if makanan_saran:
                        bagian.append("Makanan / cemilan yang cocok:\n" + daftar_menu(makanan_saran))
                elif tanya_dessert:
                    if dessert_saran:
                        bagian.append("Dessert / penutup yang cocok:\n" + daftar_menu(dessert_saran))
                else:
                    if minuman_saran:
                        bagian.append("Minuman yang cocok:\n" + daftar_menu(minuman_saran))

                if bagian:
                    dispatcher.utter_message(
                        text="\n\n".join(bagian) + f"\n{CATATAN_PAJAK}"
                    )
                    return []

            # 2. Rekomendasi khusus dessert
            if any(k in teks_lower for k in ["dessert", "penutup"]):
                semua_dessert = ambil_menu_berdasarkan_kategori("dessert", limit=50)
                baris_dessert = pilih_menu_prioritas(semua_dessert, ANDALAN_DESSERT, batas=5)
                if baris_dessert:
                    dispatcher.utter_message(
                        text=(
                            f"Rekomendasi dessert favorit di Warung Mogan:\n"
                            f"{daftar_menu(baris_dessert)}\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                    return []

            # 3. Rekomendasi khusus cemilan
            if any(k in teks_lower for k in ["cemilan", "snack", "ngemil"]):
                semua_cemilan = ambil_menu_berdasarkan_kategori("cemilan", limit=50)
                baris_cemilan = pilih_menu_prioritas(semua_cemilan, ANDALAN_CEMILAN, batas=5)
                if baris_cemilan:
                    dispatcher.utter_message(
                        text=(
                            f"Rekomendasi cemilan favorit di Warung Mogan:\n"
                            f"{daftar_menu(baris_cemilan)}\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                    return []

            # 4. Rekomendasi khusus minuman
            if "minuman" in teks_lower or "minum" in teks_lower:
                semua_minuman = ambil_menu_berdasarkan_kategori("minuman", limit=100)
                if any(k in teks_lower for k in ["seger", "segar", "dingin"]):
                    kandidat = [
                        m for m in semua_minuman
                        if any(w in m["nama_menu"].lower() for w in KATA_KUNCI_SEGAR)
                    ]
                    baris_minum = pilih_menu_prioritas(kandidat or semua_minuman, ANDALAN_MINUMAN, batas=6)
                else:
                    baris_minum = pilih_menu_prioritas(semua_minuman, ANDALAN_MINUMAN, batas=6)

                if baris_minum:
                    dispatcher.utter_message(
                        text=(
                            f"Rekomendasi minuman favorit di Warung Mogan:\n"
                            f"{daftar_menu(baris_minum)}\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                    return []

            # 5. Rekomendasi makanan / menu umum andalan
            semua_makanan = ambil_menu_berdasarkan_kategori("makanan", limit=100)
            baris = pilih_menu_prioritas(semua_makanan, ANDALAN_MAKANAN, batas=6)
            if not baris:
                baris = jalankan_query(
                    "SELECT nama_menu, harga FROM menu ORDER BY id_menu ASC LIMIT 6"
                )
            if not baris:
                dispatcher.utter_message(
                    text="Maaf, belum ada rekomendasi menu yang tersedia saat ini."
                )
                return []

            dispatcher.utter_message(
                text=(
                    f"Rekomendasi menu favorit di Warung Mogan:\n"
                    f"{daftar_menu(baris)}\n"
                    f"{CATATAN_PAJAK}"
                )
            )
        except Exception as e:
            logger.error("Gagal ambil rekomendasi menu: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)

        return []


class ActionMenuPedas(Action):
    def name(self) -> Text:
        return "action_menu_pedas"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        teks_user = (tracker.latest_message.get("text") or "").lower()
        tidak_pedas = any(
            k in teks_user for k in ["tidak pedas", "gak pedas", "ga pedas", "gak pds"]
        )
        desk_sql = kolom_deskripsi_sql()

        try:
            pola_list = [f"%{k}%" for k in KATA_KUNCI_PEDAS]
            kondisi_or = " OR ".join(
                [f"LOWER(nama_menu) LIKE %s OR LOWER({desk_sql}) LIKE %s" for _ in pola_list]
            )
            params_or: List[Any] = []
            for p in pola_list:
                params_or.extend([p, p])

            if tidak_pedas:
                kondisi_and = " AND ".join(
                    [f"LOWER(nama_menu) NOT LIKE %s AND LOWER({desk_sql}) NOT LIKE %s" for _ in pola_list]
                )
                baris = jalankan_query(
                    f"SELECT nama_menu, harga FROM menu WHERE {kondisi_and} ORDER BY id_menu ASC LIMIT 12",
                    tuple(params_or),
                )
                label = "tidak pedas"
            else:
                baris = jalankan_query(
                    f"SELECT nama_menu, harga FROM menu WHERE {kondisi_or} ORDER BY id_menu ASC LIMIT 12",
                    tuple(params_or),
                )
                label = "pedas"

            if not baris:
                fallback = jalankan_query(
                    "SELECT nama_menu, harga FROM menu ORDER BY id_menu ASC LIMIT 8"
                )
                if fallback:
                    dispatcher.utter_message(
                        text=(
                            f"Belum ada penanda khusus menu {label} di database. "
                            f"Berikut beberapa menu yang bisa Anda pilih:\n"
                            f"{daftar_menu(fallback)}\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                else:
                    dispatcher.utter_message(
                        text=f"Maaf, daftar menu {label} belum tersedia di database."
                    )
                return []

            dispatcher.utter_message(
                text=(
                    f"Pilihan menu {label} di Warung Mogan:\n"
                    f"{daftar_menu(baris)}\n"
                    f"{CATATAN_PAJAK}"
                )
            )
        except Exception as e:
            logger.error("Gagal ambil menu pedas: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)

        return []


class ActionDaftarMinuman(Action):
    def name(self) -> Text:
        return "action_daftar_minuman"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        teks_user = tracker.latest_message.get("text") or ""
        teks_lower = teks_user.lower()
        nama_menu = tracker.get_slot("nama_menu") or next(
            tracker.get_latest_entity_values("nama_menu"), None
        )
        nomor_meja = (
            tracker.get_slot("no_meja")
            or next(tracker.get_latest_entity_values("no_meja"), None)
            or "1"
        )

        try:
            # 1. Jika menanyakan minuman yang cocok dengan makanan/pesanan
            if any(k in teks_lower for k in ["cocok", "pas buat", "pas dengan", "pas untuk", "teman"]):
                acuan = f"{teks_user} {nama_menu or ''}"
                if not nama_menu:
                    _, detail_rows = ambil_pesanan_aktif_terakhir(nomor_meja)
                    if detail_rows:
                        acuan += " " + " ".join(r["nama_pesanan"] for r in detail_rows)

                minuman_saran, _, _ = rekomendasi_pairing_menu(acuan)
                if minuman_saran:
                    dispatcher.utter_message(
                        text=(
                            f"Minuman yang cocok untuk Anda:\n"
                            f"{daftar_menu(minuman_saran)}\n"
                            f"{CATATAN_PAJAK}"
                        )
                    )
                    return [SlotSet("nama_menu", None)]

            semua_minuman = ambil_menu_berdasarkan_kategori("minuman", limit=100)
            if not semua_minuman:
                dispatcher.utter_message(
                    text="Maaf, daftar minuman belum tersedia saat ini."
                )
                return [SlotSet("nama_menu", None)]

            # 2. Sub-kategori minuman: Kopi
            if any(k in teks_lower for k in ["kopi", "coffee", "latte", "cappuccino", "americano"]):
                pilihan = [
                    m for m in semua_minuman
                    if any(w in m["nama_menu"].lower() for w in KATA_KUNCI_KOPI)
                ]
                if pilihan:
                    dispatcher.utter_message(
                        text=f"Pilihan menu kopi di Warung Mogan:\n{daftar_menu(pilihan, batas=20)}\n{CATATAN_PAJAK}"
                    )
                    return [SlotSet("nama_menu", None)]

            # 3. Sub-kategori minuman: Jus buah
            if any(k in teks_lower for k in ["jus", "juice"]):
                pilihan = [
                    m for m in semua_minuman
                    if any(w in m["nama_menu"].lower() for w in KATA_KUNCI_JUS)
                ]
                if pilihan:
                    dispatcher.utter_message(
                        text=f"Pilihan jus buah di Warung Mogan:\n{daftar_menu(pilihan, batas=20)}\n{CATATAN_PAJAK}"
                    )
                    return [SlotSet("nama_menu", None)]

            # 4. Sub-kategori minuman: Milkshake / Shake
            if any(k in teks_lower for k in ["milkshake", "milk shake", "shake"]):
                pilihan = [
                    m for m in semua_minuman
                    if any(w in m["nama_menu"].lower() for w in KATA_KUNCI_SHAKE)
                ]
                if pilihan:
                    dispatcher.utter_message(
                        text=f"Pilihan milkshake di Warung Mogan:\n{daftar_menu(pilihan, batas=20)}\n{CATATAN_PAJAK}"
                    )
                    return [SlotSet("nama_menu", None)]

            # 5. Sub-kategori minuman: Hangat / Tanpa es
            if any(k in teks_lower for k in ["hangat", "panas", "jahe", "tanpa es", "gak pakai es", "ga pakai es"]):
                pilihan = [
                    m for m in semua_minuman
                    if any(w in m["nama_menu"].lower() for w in KATA_KUNCI_HANGAT)
                ]
                if pilihan:
                    dispatcher.utter_message(
                        text=f"Pilihan minuman hangat / tanpa es di Warung Mogan:\n{daftar_menu(pilihan, batas=20)}\n{CATATAN_PAJAK}"
                    )
                    return [SlotSet("nama_menu", None)]

            # 6. Sub-kategori minuman: Segar / Dingin / Es
            if any(k in teks_lower for k in ["seger", "segar", "dingin", "haus", "adem", "es apa"]):
                minuman_segar = [
                    m for m in semua_minuman
                    if any(w in m["nama_menu"].lower() for w in KATA_KUNCI_SEGAR)
                ]
                pilihan = minuman_segar if minuman_segar else semua_minuman[:15]
                dispatcher.utter_message(
                    text=(
                        f"Pilihan minuman segar di Warung Mogan:\n"
                        f"{daftar_menu(pilihan, batas=20)}\n"
                        f"{CATATAN_PAJAK}"
                    )
                )
                return [SlotSet("nama_menu", None)]

            dispatcher.utter_message(
                text=(
                    f"Daftar minuman di Warung Mogan:\n"
                    f"{daftar_menu(semua_minuman, batas=30)}\n"
                    f"{CATATAN_PAJAK}"
                )
            )
        except Exception as e:
            logger.error("Gagal ambil daftar minuman: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)

        return [SlotSet("nama_menu", None)]


class ActionProsesPesanan(Action):
    def name(self) -> Text:
        return "action_proses_pesanan"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        teks_user = tracker.latest_message.get("text") or ""
        nama_menu = tracker.get_slot("nama_menu") or next(
            tracker.get_latest_entity_values("nama_menu"), None
        )
        jumlah = ambil_angka_jumlah(
            tracker.get_slot("jumlah")
            or next(tracker.get_latest_entity_values("jumlah"), None),
            default=1,
        )
        nomor_meja = (
            tracker.get_slot("no_meja")
            or next(tracker.get_latest_entity_values("no_meja"), None)
            or "1"
        )
        is_bungkus = deteksi_bungkus(teks_user) or (
            nama_menu is not None and deteksi_bungkus(str(nama_menu))
        )

        if not nama_menu:
            dispatcher.utter_message(
                text="Menu apa yang ingin dipesan? Contoh: pesan Nasi Bakar 1 porsi atau bungkus Nasi Kuning 1 tanpa pedas."
            )
            return [SlotSet("jumlah", None)]

        bersih_menu = bersihkan_nama_menu(str(nama_menu))

        try:
            kandidat = cari_menu_cocok(str(nama_menu))

            if not kandidat:
                dispatcher.utter_message(
                    text=f"Maaf, menu '{nama_menu}' tidak ditemukan di daftar menu Warung Mogan."
                )
                return [SlotSet("nama_menu", None), SlotSet("jumlah", None)]

            if len(kandidat) > 1 and kandidat[0]["nama_menu"].lower() != bersih_menu.lower():
                dispatcher.utter_message(
                    text=(
                        f"Ada beberapa varian untuk '{bersih_menu}':\n"
                        f"{daftar_menu(kandidat)}\n"
                        f"Silakan sebutkan nama varian lengkapnya ya."
                    )
                )
                return [SlotSet("nama_menu", None), SlotSet("jumlah", None)]

            menu_data = kandidat[0]
            id_menu = menu_data["id_menu"]
            nama_asli = menu_data["nama_menu"]
            harga = float(menu_data["harga"])

            # Ekstrak catatan khusus (misal: "tanpa buah nanas", "pedas sedang", "sedikit es")
            catatan_khusus = ekstrak_catatan_pesanan(
                teks_user, str(nama_menu), nama_asli
            )
            daftar_catatan: List[str] = []
            if is_bungkus:
                daftar_catatan.append("Bungkus (Take Away)")
            if catatan_khusus:
                daftar_catatan.append(catatan_khusus)
            catatan_item = ", ".join(daftar_catatan) if daftar_catatan else None

            koneksi = None
            cur = None
            try:
                koneksi = mysql.connector.connect(**DB_CONFIG)
                cur = koneksi.cursor(dictionary=True)

                cur.execute(
                    "SELECT id_keranjang FROM keranjang "
                    "WHERE nomor_meja = %s AND status = 'menunggu' "
                    "ORDER BY id_keranjang DESC LIMIT 1",
                    (nomor_meja,),
                )
                keranjang_aktif = cur.fetchone()

                if keranjang_aktif:
                    id_keranjang = keranjang_aktif["id_keranjang"]
                else:
                    cur.execute(
                        "INSERT INTO keranjang (nomor_meja, total_harga, status, tanggal_dibuat) "
                        "VALUES (%s, 0, 'menunggu', NOW())",
                        (nomor_meja,),
                    )
                    id_keranjang = cur.lastrowid

                if cek_kolom_catatan_detail():
                    cur.execute(
                        "INSERT INTO detail_keranjang (id_keranjang, id_menu, nama_pesanan, jumlah, harga_satuan, catatan) "
                        "VALUES (%s, %s, %s, %s, %s, %s)",
                        (id_keranjang, id_menu, nama_asli, jumlah, harga, catatan_item),
                    )
                else:
                    cur.execute(
                        "INSERT INTO detail_keranjang (id_keranjang, id_menu, nama_pesanan, jumlah, harga_satuan) "
                        "VALUES (%s, %s, %s, %s, %s)",
                        (id_keranjang, id_menu, nama_asli, jumlah, harga),
                    )

                subtotal_baru = hitung_ulang_subtotal_keranjang(cur, id_keranjang)
                cur.execute(
                    "UPDATE keranjang SET total_harga = %s WHERE id_keranjang = %s",
                    (subtotal_baru, id_keranjang),
                )
                koneksi.commit()

                mode_saji = "bungkus" if is_bungkus else "makan di tempat"
                info_catatan = f", catatan: {catatan_khusus}" if catatan_khusus else ""
                dispatcher.utter_message(
                    text=(
                        f"Pesanan {jumlah} porsi {nama_asli} ({mode_saji}, meja {nomor_meja}{info_catatan}) "
                        f"berhasil ditambahkan ke sistem kasir! Mohon tunggu sebentar ya."
                    )
                )
            except Exception as e:
                if koneksi is not None and koneksi.is_connected():
                    koneksi.rollback()
                logger.error("Gagal proses pesanan bot: %s", e)
                dispatcher.utter_message(text=PESAN_ERROR)
            finally:
                if cur is not None:
                    cur.close()
                if koneksi is not None and koneksi.is_connected():
                    koneksi.close()

        except Exception as e:
            logger.error("Error saat mencari menu pesanan: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)

        return [SlotSet("nama_menu", None), SlotSet("jumlah", None)]


class ActionBatalPesanan(Action):
    def name(self) -> Text:
        return "action_batal_pesanan"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        nomor_meja = (
            tracker.get_slot("no_meja")
            or next(tracker.get_latest_entity_values("no_meja"), None)
            or "1"
        )
        nama_menu = tracker.get_slot("nama_menu") or next(
            tracker.get_latest_entity_values("nama_menu"), None
        )
        bersih_menu = bersihkan_nama_menu(str(nama_menu)) if nama_menu else ""

        koneksi = None
        cur = None
        try:
            koneksi = mysql.connector.connect(**DB_CONFIG)
            cur = koneksi.cursor(dictionary=True)

            cur.execute(
                "SELECT id_keranjang FROM keranjang "
                "WHERE nomor_meja = %s AND status = 'menunggu' "
                "ORDER BY id_keranjang DESC LIMIT 1",
                (nomor_meja,),
            )
            pesanan = cur.fetchone()

            if not pesanan:
                dispatcher.utter_message(
                    text=f"Tidak ada pesanan aktif berstatus menunggu untuk meja {nomor_meja}."
                )
                return [SlotSet("nama_menu", None)]

            id_keranjang = pesanan["id_keranjang"]

            if bersih_menu:
                cur.execute(
                    "SELECT id_detail_keranjang, nama_pesanan FROM detail_keranjang "
                    "WHERE id_keranjang = %s AND LOWER(nama_pesanan) LIKE %s",
                    (id_keranjang, f"%{bersih_menu.lower()}%"),
                )
                item_cocok = cur.fetchall()
                if item_cocok:
                    nama_item = item_cocok[0]["nama_pesanan"]
                    cur.execute(
                        "DELETE FROM detail_keranjang "
                        "WHERE id_keranjang = %s AND LOWER(nama_pesanan) LIKE %s",
                        (id_keranjang, f"%{bersih_menu.lower()}%"),
                    )
                    sisa_subtotal = hitung_ulang_subtotal_keranjang(cur, id_keranjang)
                    if sisa_subtotal <= 0:
                        cur.execute(
                            "UPDATE keranjang SET total_harga = 0, status = 'dibatalkan' WHERE id_keranjang = %s",
                            (id_keranjang,),
                        )
                    else:
                        cur.execute(
                            "UPDATE keranjang SET total_harga = %s WHERE id_keranjang = %s",
                            (sisa_subtotal, id_keranjang),
                        )
                    koneksi.commit()
                    dispatcher.utter_message(
                        text=f"Item {nama_item} pada pesanan meja {nomor_meja} berhasil dibatalkan."
                    )
                    return [SlotSet("nama_menu", None)]

            cur.execute(
                "UPDATE keranjang SET status = 'dibatalkan' WHERE id_keranjang = %s",
                (id_keranjang,),
            )
            koneksi.commit()

            dispatcher.utter_message(
                text=f"Pesanan aktif untuk meja {nomor_meja} berhasil dibatalkan."
            )
        except Exception as e:
            if koneksi is not None and koneksi.is_connected():
                koneksi.rollback()
            logger.error("Gagal membatalkan pesanan bot: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)
        finally:
            if cur is not None:
                cur.close()
            if koneksi is not None and koneksi.is_connected():
                koneksi.close()

        return [SlotSet("nama_menu", None)]


class ActionCekKeranjang(Action):
    def name(self) -> Text:
        return "action_cek_keranjang"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        nomor_meja = (
            tracker.get_slot("no_meja")
            or next(tracker.get_latest_entity_values("no_meja"), None)
            or "1"
        )

        try:
            pesanan_aktif, detail_rows = ambil_pesanan_aktif_terakhir(nomor_meja)

            if not pesanan_aktif:
                dispatcher.utter_message(
                    text=f"Belum ada pesanan aktif yang menunggu di kasir untuk meja {nomor_meja}."
                )
                return []

            id_keranjang = pesanan_aktif["id_keranjang"]
            if not detail_rows:
                dispatcher.utter_message(
                    text=f"Pesanan aktif meja {nomor_meja} (#{id_keranjang}) belum memiliki rincian item."
                )
                return []

            baris_ringkasan = []
            for r in detail_rows:
                catatan_teks = f" [{r['catatan']}]" if r.get("catatan") else ""
                baris_ringkasan.append(
                    f"- {r['nama_pesanan']} x{int(r['jumlah'])}{catatan_teks} "
                    f"({rupiah(float(r['harga_satuan']) * int(r['jumlah']))})"
                )
            ringkasan = "\n".join(baris_ringkasan)
            subtotal = sum(
                float(r["harga_satuan"]) * int(r["jumlah"]) for r in detail_rows
            )

            dispatcher.utter_message(
                text=(
                    f"Pesanan aktif meja {nomor_meja} (Antrean #{id_keranjang}):\n"
                    f"{ringkasan}\n"
                    f"Subtotal: {rupiah(subtotal)}\n"
                    f"{CATATAN_PAJAK}"
                )
            )
        except Exception as e:
            logger.error("Gagal cek keranjang bot: %s", e)
            dispatcher.utter_message(text=PESAN_ERROR)

        return []


class ActionInfoUmum(Action):
    def name(self) -> Text:
        return "action_info_umum"

    def run(
        self,
        dispatcher: CollectingDispatcher,
        tracker: Tracker,
        domain: Dict[Text, Any],
    ) -> List[Dict[Text, Any]]:
        teks_lower = (tracker.latest_message.get("text") or "").lower()

        if any(k in teks_lower for k in ["bayar", "qris", "debit", "kredit", "transfer", "tunai", "cash"]):
            dispatcher.utter_message(
                text="Kami menerima pembayaran tunai/cash, QRIS, kartu debit/kredit, maupun transfer."
            )
        elif any(k in teks_lower for k in ["jam", "buka", "tutup", "galungan", "hari raya"]):
            dispatcher.utter_message(
                text="Warung Mogan buka setiap hari pukul 07.00-22.00 WITA."
            )
        elif any(k in teks_lower for k in ["lokasi", "alamat", "jalan", "di mana", "dimana", "dalung"]):
            dispatcher.utter_message(
                text="Warung Mogan Vegetarian beralamat di Jalan Mudutaki No. 99x, Dalung."
            )
        else:
            dispatcher.utter_message(
                text=(
                    "Halo! Saya asisten Warung Mogan. Anda bisa menanyakan menu, harga termurah/termahal, "
                    "menu pedas, minuman segar, kopi, jus, dessert, cara pembayaran, atau memesan menu langsung."
                )
            )

        return []