<?php
session_start();

// Koneksi langsung ke database MySQL (sesuaikan nama database Anda jika berbeda)
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_warung_mogan"; // Ganti dengan nama database Warung Mogan Anda

$conn = new mysqli($host, $user, $pass, $db);

// Periksa koneksi
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $conn->connect_error]));
}
// Pastikan request menggunakan metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data yang dikirim oleh Chatbot Rasa
    $no_meja = $_POST['no_meja'] ?? '1'; // Default meja jika tidak ada
    $id_menu = (int)($_POST['id_menu'] ?? 0);
    $jumlah  = (int)($_POST['jumlah'] ?? 1);
    $catatan = trim($_POST['catatan'] ?? '');

    // Ambil detail menu dari database untuk mendapatkan nama dan harga asli
    $queryMenu = $conn->query("SELECT * FROM menu WHERE id_menu = $id_menu");
    
    if ($queryMenu && $queryMenu->num_rows > 0) {
        $menu = $queryMenu->fetch_assoc();
        $nama   = $menu['nama_menu'];
        $harga  = $menu['harga'];

        // Masukkan ke SESSION keranjang (mengikuti struktur keranjang web Anda)
        if (isset($_SESSION['keranjang'][$id_menu])) {
            $_SESSION['keranjang'][$id_menu]['qty'] += $jumlah;
            if (!empty($catatan)) {
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

        // Simpan juga secara otomatis ke database (tabel detail_keranjang & keranjang)
        // Mengecek apakah sudah ada sesi pesanan aktif untuk meja ini
        $idPesanan = $_SESSION['id_pesanan_aktif'] ?? null;
        $totalTambahan = $harga * $jumlah;

        if ($idPesanan) {
            $conn->query("UPDATE keranjang SET total_harga = total_harga + $totalTambahan WHERE id_keranjang = $idPesanan");
        } else {
            $conn->query("INSERT INTO keranjang (nomor_meja, total_harga, status) VALUES ('$no_meja', '$totalTambahan', 'menunggu')");
            $idPesanan = $conn->insert_id;
            $_SESSION['id_pesanan_aktif'] = $idPesanan;
        }

        // Masukkan ke detail keranjang
        $conn->query("INSERT INTO detail_keranjang (id_keranjang, id_menu, nama_pesanan, jumlah, catatan, harga_satuan) 
                      VALUES ('$idPesanan', '$id_menu', '$nama', '$jumlah', '$catatan', '$harga')");

        // Kirim respon sukses dalam format JSON ke Chatbot
        echo json_encode([
            "status" => "success",
            "message" => "Menu $nama x$jumlah berhasil ditambahkan ke keranjang!",
            "id_pesanan" => $idPesanan
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Menu tidak ditemukan di database."
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Metode request tidak diizinkan."
    ]);
}
?>