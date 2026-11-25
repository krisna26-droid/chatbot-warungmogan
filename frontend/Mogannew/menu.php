<?php
session_start();
require_once __DIR__ . '/config.php';

// 1. Simpan nomor meja dari URL QR code ke sesi jika tersedia
if (isset($_GET['meja']) && $_GET['meja'] !== '') {
    $_SESSION['no_meja'] = htmlspecialchars(trim($_GET['meja']), ENT_QUOTES, 'UTF-8');
}
$noMeja = isset($_SESSION['no_meja']) ? $_SESSION['no_meja'] : '-';

// 3. Cek Status Pesanan Aktif Pelanggan di Meja Ini
if (isset($_SESSION['id_pesanan_aktif'])) {
    $idPesananAktif = (int)$_SESSION['id_pesanan_aktif'];
    $cekPesanan = $conn->query("SELECT status FROM keranjang WHERE id_keranjang = $idPesananAktif");

    if ($cekPesanan && $cekPesanan->num_rows > 0) {
        $rowPesanan = $cekPesanan->fetch_assoc();
        if ($rowPesanan['status'] === 'selesai' || $rowPesanan['status'] === 'dibatalkan') {
            unset($_SESSION['id_pesanan_aktif']);
            $_SESSION['keranjang'] = [];
        }
    } else {
        unset($_SESSION['id_pesanan_aktif']);
        $_SESSION['keranjang'] = [];
    }
}

// 4. Pastikan array keranjang selalu terinisialisasi
if (!isset($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

// Logika Tambah Item ke Keranjang dari Modal Pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['konfirmasi_pesan'])) {
    $id_menu  = (int)($_POST['id_menu'] ?? 0);
    $nama     = trim($_POST['nama_menu'] ?? '');
    $harga    = (float)($_POST['harga'] ?? 0);
    $catatan  = trim($_POST['catatan_tambahan'] ?? '');
    $jumlah   = max(1, (int)($_POST['jumlah'] ?? 1));

    if ($id_menu > 0 && $nama !== '') {
        if (isset($_SESSION['keranjang'][$id_menu])) {
            $_SESSION['keranjang'][$id_menu]['qty'] += $jumlah;
            if ($catatan !== '') {
                $_SESSION['keranjang'][$id_menu]['catatan'] = $catatan;
            }
        } else {
            $_SESSION['keranjang'][$id_menu] = [
                'nama'    => $nama,
                'harga'   => $harga,
                'qty'     => $jumlah,
                'catatan' => $catatan
            ];
        }
    }

    header("Location: menu.php#daftar-menu");
    exit;
}

// Logika Update, Edit Catatan, Kurang, & Hapus Item dari Keranjang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_cart'])) {
    $id_menu = (int)($_POST['id_menu'] ?? 0);
    $aksi    = $_POST['action_cart'];

    if (isset($_SESSION['keranjang'][$id_menu])) {
        if ($aksi === 'tambah') {
            $_SESSION['keranjang'][$id_menu]['qty'] += 1;
        } elseif ($aksi === 'kurang') {
            $_SESSION['keranjang'][$id_menu]['qty'] -= 1;
            if ($_SESSION['keranjang'][$id_menu]['qty'] <= 0) {
                unset($_SESSION['keranjang'][$id_menu]);
            }
        } elseif ($aksi === 'hapus') {
            unset($_SESSION['keranjang'][$id_menu]);
        } elseif ($aksi === 'update_catatan') {
            $_SESSION['keranjang'][$id_menu]['catatan'] = trim($_POST['catatan_baru'] ?? '');
        }
    }
    header("Location: menu.php?open_cart=1");
    exit;
}

// Ambil pesanan aktif berstatus 'menunggu' dari database (termasuk yang dibuat lewat chatbot)
$idKeranjangAktifDB = null;
if (!empty($_SESSION['id_pesanan_aktif'])) {
    $idCek = (int)$_SESSION['id_pesanan_aktif'];
    $qCek  = $conn->query("SELECT id_keranjang FROM keranjang WHERE id_keranjang = $idCek AND status = 'menunggu' LIMIT 1");
    if ($qCek && $rCek = $qCek->fetch_assoc()) {
        $idKeranjangAktifDB = (int)$rCek['id_keranjang'];
    }
}

if (!$idKeranjangAktifDB && $noMeja !== '-') {
    $mejaEsc = $conn->real_escape_string($noMeja);
    $qCek    = $conn->query("SELECT id_keranjang FROM keranjang WHERE nomor_meja = '$mejaEsc' AND status = 'menunggu' ORDER BY id_keranjang DESC LIMIT 1");
    if ($qCek && $rCek = $qCek->fetch_assoc()) {
        $idKeranjangAktifDB = (int)$rCek['id_keranjang'];
        $_SESSION['id_pesanan_aktif'] = $idKeranjangAktifDB;
    }
}

$itemPesananAktifDB  = [];
$qtyPesananAktifDB   = 0;
$totalPesananAktifDB = 0;

if ($idKeranjangAktifDB) {
    $qDetailDB = $conn->query("SELECT id_menu, nama_pesanan, SUM(jumlah) AS jumlah, catatan, harga_satuan 
                               FROM detail_keranjang 
                               WHERE id_keranjang = $idKeranjangAktifDB 
                               GROUP BY id_menu, nama_pesanan, catatan, harga_satuan 
                               ORDER BY MIN(id_detail_keranjang) ASC");
    if ($qDetailDB) {
        while ($rowDB = $qDetailDB->fetch_assoc()) {
            $qtyDB = (int)$rowDB['jumlah'];
            $subDB = (float)$rowDB['harga_satuan'] * $qtyDB;
            $itemPesananAktifDB[] = $rowDB;
            $qtyPesananAktifDB  += $qtyDB;
            $totalPesananAktifDB += $subDB;
        }
    }
}

$adaPesananAktifDB = ($idKeranjangAktifDB !== null && $qtyPesananAktifDB > 0);

$query  = "SELECT * FROM menu ORDER BY id_menu ASC";
$result = $conn->query($query);

$totalQty   = 0;
$totalBayar = 0;
foreach ($_SESSION['keranjang'] as $item) {
    $totalQty   += (int)$item['qty'];
    $totalBayar += ((float)$item['harga'] * (int)$item['qty']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu - Warung Mogan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .card-menu {
            border: none;
            border-radius: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            overflow: hidden;
        }
        .card-menu:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        .menu-img {
            height: 180px;
            object-fit: cover;
            width: 100%;
            background-color: #f1f5f9;
        }
        .modal-menu-img {
            height: 220px;
            object-fit: cover;
            width: 100%;
            border-radius: 12px;
            background-color: #f1f5f9;
        }
        .btn-mogan {
            background-color: #0b6056;
            color: white;
            border-radius: 20px;
        }
        .btn-mogan:hover {
            background-color: #084841;
            color: white;
        }
        .logo-navbar {
            max-height: 48px;
            width: auto;
            object-fit: contain;
        }
        input, textarea, select {
            font-size: 16px !important;
        }
        .kolom-menu-catatan {
            min-width: 165px;
        }
        .input-catatan-keranjang {
            font-size: 13px !important;
            padding: 4px 8px;
            min-width: 115px;
        }
        @media (max-width: 576px) {
            #modalKeranjang .modal-body {
                padding: 12px !important;
            }
            #modalKeranjang table th,
            #modalKeranjang table td {
                padding: 8px 6px !important;
                font-size: 12px !important;
            }
            .kolom-menu-catatan {
                min-width: 145px;
                white-space: normal;
            }
            .input-catatan-keranjang {
                font-size: 12px !important;
                min-width: 95px;
            }
        }
    </style>
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top py-2">
    <div class="container">
        <img src="assets/images/logo_mogan.jpeg" alt="Logo Warung Mogan" class="logo-navbar" onerror="this.src='https://placehold.co/150x50?text=Warung+Mogan'">

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Kembali ke Beranda</a>

            <?php if ($adaPesananAktifDB): ?>
                <a href="keranjang.php" class="btn btn-outline-success btn-sm rounded-pill px-3">
                    <i class="bi bi-receipt me-1"></i> Lihat Pesanan Aktif
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-danger rounded-pill px-3 py-1 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalKeranjang">
                <i class="bi bi-cart3"></i>
                <span class="fw-semibold"><?= $totalQty + $qtyPesananAktifDB ?> Item</span>
                <?php if ($qtyPesananAktifDB > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.7rem;">Aktif</span>
                <?php endif; ?>
            </button>
        </div>
    </div>
</nav>

<!-- Kolom Pencarian Menu Responsif untuk HP -->
<form onsubmit="return false;" class="mt-3">
    <div class="container mb-4">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8 col-12 px-3">
                <div class="input-group shadow-sm rounded-pill overflow-hidden border bg-white">
                    <span class="input-group-text bg-white border-0 ps-3">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control border-0 py-2 shadow-none" placeholder="Cari nama menu...">
                </div>
            </div>
        </div>
    </div>
</form>

<div class="container py-3" id="daftar-menu">
    <div class="text-center mb-4">
        <h2 class="fw-bold text-dark">Pilihan Menu Makanan & Minuman</h2>
    </div>

    <!-- Grid Menu -->
    <div class="row g-4">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()):
                $gambarMenu    = !empty($row['gambar']) ? 'assets/images/' . $row['gambar'] : 'https://placehold.co/400x300?text=Menu+Mogan';
                $deskripsiMenu = !empty($row['deskripsi']) ? $row['deskripsi'] : 'Hidangan olahan nabati khas Bali dengan racikan bumbu rempah otentik Warung Mogan.';
            ?>
                <div class="col-12 col-md-4 col-lg-3 menu-card-item">
                    <div class="card card-menu h-100 shadow-sm">
                        <img src="<?= htmlspecialchars($gambarMenu) ?>"
                             class="menu-img"
                             alt="<?= htmlspecialchars($row['nama_menu']) ?>"
                             onerror="this.src='https://placehold.co/400x300?text=Menu+Mogan'">

                        <div class="card-body d-flex flex-column">
                            <h6 class="fw-bold text-dark mb-1 menu-title"><?= htmlspecialchars($row['nama_menu']) ?></h6>

                            <p class="text-muted small flex-grow-1">
                                <?= htmlspecialchars($deskripsiMenu) ?>
                            </p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-2">
                                <span class="fw-bold text-success fs-6">
                                    Rp <?= number_format((float)$row['harga'], 0, ',', '.') ?>
                                </span>

                                <button type="button" class="btn btn-mogan btn-sm px-3"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalPesanMenu"
                                        data-id="<?= (int)$row['id_menu'] ?>"
                                        data-nama="<?= htmlspecialchars($row['nama_menu'], ENT_QUOTES) ?>"
                                        data-harga="<?= (float)$row['harga'] ?>"
                                        data-hargafmt="Rp <?= number_format((float)$row['harga'], 0, ',', '.') ?>"
                                        data-gambar="<?= htmlspecialchars($gambarMenu, ENT_QUOTES) ?>"
                                        data-deskripsi="<?= htmlspecialchars($deskripsiMenu, ENT_QUOTES) ?>"
                                        onclick="bukaModalPesan(this)">
                                    + Pesan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <p class="text-muted">Belum ada menu yang terdaftar di database.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Pop-up Pemesanan & Catatan Menu -->
<div class="modal fade" id="modalPesanMenu" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="popupNamaMenu">Nama Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="menu.php#daftar-menu" method="POST">
                <div class="modal-body">
                    <img id="popupGambarMenu" src="" class="modal-menu-img mb-3" alt="Foto Menu">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-5 fw-bold text-success" id="popupHargaMenu">Rp 0</span>
                    </div>

                    <p class="text-muted small mb-3" id="popupDeskripsiMenu"></p>

                    <input type="hidden" name="id_menu" id="popupIdMenu">
                    <input type="hidden" name="nama_menu" id="popupNamaMenuInput">
                    <input type="hidden" name="harga" id="popupHargaInput">

                    <div class="mb-3 d-flex align-items-center justify-content-between bg-light p-3 rounded-3">
                        <span class="small fw-bold text-dark">Jumlah Porsi</span>
                        <div class="input-group input-group-sm" style="width: 130px;">
                            <button type="button" class="btn btn-outline-secondary px-2" onclick="kurangQty()">
                                <i class="bi bi-dash"></i>
                            </button>
                            <input type="number" name="jumlah" id="qtyItem" class="form-control text-center fw-bold" value="1" min="1" readonly>
                            <button type="button" class="btn btn-outline-secondary px-2" onclick="tambahQty()">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold small text-dark">
                            <i class="bi bi-pencil-square text-secondary me-1"></i>Catatan Khusus (Opsional):
                        </label>
                        <textarea name="catatan_tambahan" id="popupCatatan" class="form-control form-control-sm" rows="3" placeholder="Contoh: jangan pedas, sedikit es, kuah banyakin..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="konfirmasi_pesan" class="btn btn-success rounded-pill px-4">
                        <i class="bi bi-cart-plus me-1"></i> Masukkan ke Keranjang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail & Edit Keranjang -->
<div class="modal fade" id="modalKeranjang" tabindex="-1" aria-labelledby="modalKeranjangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="modalKeranjangLabel"><i class="bi bi-cart-check me-2"></i>Keranjang Pesanan Anda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <?php if (!empty($itemPesananAktifDB)): ?>
                    <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3">
                        <i class="bi bi-receipt me-1"></i>
                        <strong>Pesanan Aktif di Kasir (Antrean #<?= (int)$idKeranjangAktifDB ?>):</strong> item di bawah sudah tercatat di sistem.
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th>Menu Tercatat</th>
                                    <th class="text-center">Jumlah</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itemPesananAktifDB as $dbItem):
                                    $subDB = (float)$dbItem['harga_satuan'] * (int)$dbItem['jumlah'];
                                ?>
                                    <tr>
                                        <td class="kolom-menu-catatan">
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($dbItem['nama_pesanan']) ?></div>
                                            <?php if (!empty($dbItem['catatan'])): ?>
                                                <div class="small fw-semibold text-success fst-italic mt-1">
                                                    <i class="bi bi-chat-left-text me-1"></i>Catatan: <?= htmlspecialchars($dbItem['catatan']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-semibold">x<?= (int)$dbItem['jumlah'] ?></td>
                                        <td class="text-end fw-semibold">Rp <?= number_format($subDB, 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="2" class="text-end">Subtotal Pesanan Aktif:</th>
                                    <th class="text-end text-success">Rp <?= number_format($totalPesananAktifDB, 0, ',', '.') ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (empty($_SESSION['keranjang'])): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-cart-x fs-1 text-muted"></i>
                        <p class="text-muted mt-2">Keranjang baru Anda masih kosong. Silakan pilih menu terlebih dahulu!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="kolom-menu-catatan">Menu & Catatan</th>
                                    <th>Harga</th>
                                    <th class="text-center" style="width: 120px;">Jumlah</th>
                                    <th>Subtotal</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['keranjang'] as $id => $item):
                                    $subtotal = (float)$item['harga'] * (int)$item['qty'];
                                    $catatanItem = trim($item['catatan'] ?? '');
                                ?>
                                    <tr>
                                        <td class="kolom-menu-catatan">
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($item['nama']) ?></div>
                                            <?php if ($catatanItem !== ''): ?>
                                                <div class="small fw-semibold text-success mt-1">
                                                    <i class="bi bi-chat-left-text me-1"></i>Catatan: <?= htmlspecialchars($catatanItem) ?>
                                                </div>
                                            <?php endif; ?>
                                            <form method="POST" class="d-flex align-items-center gap-1 mt-1">
                                                <input type="hidden" name="id_menu" value="<?= (int)$id ?>">
                                                <input type="hidden" name="action_cart" value="update_catatan">
                                                <input type="text" name="catatan_baru" value="<?= htmlspecialchars($catatanItem) ?>" class="form-control form-control-sm text-dark input-catatan-keranjang flex-grow-1" placeholder="Tulis catatan...">
                                                <button type="submit" class="btn btn-outline-success btn-sm px-2 py-1 flex-shrink-0" title="Simpan Catatan"><i class="bi bi-check2"></i></button>
                                            </form>
                                        </td>
                                        <td>Rp <?= number_format((float)$item['harga'], 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center align-items-center gap-1">
                                                <form method="POST" class="d-inline m-0">
                                                    <input type="hidden" name="id_menu" value="<?= (int)$id ?>">
                                                    <input type="hidden" name="action_cart" value="kurang">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm px-2 py-0">-</button>
                                                </form>
                                                <span class="px-1 fw-bold"><?= (int)$item['qty'] ?></span>
                                                <form method="POST" class="d-inline m-0">
                                                    <input type="hidden" name="id_menu" value="<?= (int)$id ?>">
                                                    <input type="hidden" name="action_cart" value="tambah">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm px-2 py-0">+</button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-success">Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                                        <td class="text-center">
                                            <form method="POST" class="d-inline m-0">
                                                <input type="hidden" name="id_menu" value="<?= (int)$id ?>">
                                                <input type="hidden" name="action_cart" value="hapus">
                                                <button type="submit" class="btn btn-outline-danger btn-sm border-0" title="Hapus Item">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th colspan="3" class="text-end fs-6">Total Pembayaran:</th>
                                    <th colspan="2" class="fs-5 text-success fw-bold">Rp <?= number_format($totalBayar, 0, ',', '.') ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                <?php if (!empty($_SESSION['keranjang']) || $qtyPesananAktifDB > 0): ?>
                    <a href="keranjang.php" class="btn btn-success rounded-pill px-4">
                        <i class="bi bi-bookmark-check me-1"></i> Proses Pesanan
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function bukaModalPesan(btn) {
        document.getElementById('popupIdMenu').value = btn.getAttribute('data-id');
        document.getElementById('popupNamaMenuInput').value = btn.getAttribute('data-nama');
        document.getElementById('popupHargaInput').value = btn.getAttribute('data-harga');

        document.getElementById('popupNamaMenu').innerText = btn.getAttribute('data-nama');
        document.getElementById('popupHargaMenu').innerText = btn.getAttribute('data-hargafmt');
        document.getElementById('popupDeskripsiMenu').innerText = btn.getAttribute('data-deskripsi');
        document.getElementById('qtyItem').value = 1;
        document.getElementById('popupCatatan').value = '';

        const gambar = btn.getAttribute('data-gambar');
        const imgEl = document.getElementById('popupGambarMenu');
        imgEl.src = gambar;
        imgEl.onerror = () => { imgEl.src = 'https://placehold.co/400x300?text=Menu+Mogan'; };
    }

    function tambahQty() {
        let inputQty = document.getElementById('qtyItem');
        let nilai = parseInt(inputQty.value, 10) || 1;
        inputQty.value = nilai + 1;
    }

    function kurangQty() {
        let inputQty = document.getElementById('qtyItem');
        let nilai = parseInt(inputQty.value, 10) || 1;
        if (nilai > 1) {
            inputQty.value = nilai - 1;
        }
    }

    document.getElementById('searchInput').addEventListener('input', function() {
        let keyword = this.value.toLowerCase().trim();
        document.querySelectorAll('.menu-card-item').forEach(card => {
            let title = card.querySelector('.menu-title').textContent.toLowerCase();
            card.style.display = title.includes(keyword) ? '' : 'none';
        });
    });
</script>

<?php if (isset($_GET['open_cart'])): ?>
<script>
    const cartModal = new bootstrap.Modal(document.getElementById('modalKeranjang'));
    cartModal.show();
</script>
<?php endif; ?>
</body>
</html>
