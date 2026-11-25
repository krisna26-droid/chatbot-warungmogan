<?php
// Setel cookie sesi agar otomatis hangus saat browser ditutup
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Opsi hapus sesi jika pelanggan menekan tombol 'Selesai / Keluar'
if (isset($_GET['aksi']) && $_GET['aksi'] === 'selesai_keluar') {
    unset($_SESSION['id_pesanan_aktif']);
    unset($_SESSION['keranjang']);
    unset($_SESSION['no_meja']);
    session_destroy();
    header("Location: index.php");
    exit;
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_warung_mogan";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Simpan nomor meja dari URL jika ada
if (isset($_GET['meja']) && $_GET['meja'] !== '') {
    $_SESSION['no_meja'] = htmlspecialchars(trim($_GET['meja']), ENT_QUOTES, 'UTF-8');
}
$noMeja = isset($_SESSION['no_meja']) ? $_SESSION['no_meja'] : '-';

// Proses saat ada menu baru di keranjang (dari sesi web)
if (!empty($_SESSION['keranjang'])) {
    $totalTambahan = 0;
    foreach ($_SESSION['keranjang'] as $item) {
        $totalTambahan += ((float)$item['harga'] * (int)$item['qty']);
    }

    $idPesanan = isset($_SESSION['id_pesanan_aktif']) ? (int)$_SESSION['id_pesanan_aktif'] : null;

    if ($idPesanan) {
        $conn->query("UPDATE keranjang SET total_harga = total_harga + $totalTambahan WHERE id_keranjang = $idPesanan");
    } else {
        $mejaInsert = $conn->real_escape_string($noMeja !== '-' ? $noMeja : '1');
        $conn->query("INSERT INTO keranjang (nomor_meja, total_harga, status, tanggal_dibuat) VALUES ('$mejaInsert', '$totalTambahan', 'menunggu', NOW())");
        $idPesanan = (int)$conn->insert_id;
        $_SESSION['id_pesanan_aktif'] = $idPesanan;
    }

    foreach ($_SESSION['keranjang'] as $id_menu => $item) {
        $idMenuInt    = (int)$id_menu;
        $nama_pesanan = $conn->real_escape_string($item['nama']);
        $harga_satuan = (float)$item['harga'];
        $catatan      = $conn->real_escape_string($item['catatan'] ?? '');
        $qty          = (int)$item['qty'];

        $conn->query("INSERT INTO detail_keranjang (id_keranjang, id_menu, nama_pesanan, jumlah, catatan, harga_satuan) 
                      VALUES ('$idPesanan', '$idMenuInt', '$nama_pesanan', '$qty', '$catatan', '$harga_satuan')");
    }

    unset($_SESSION['keranjang']);
}

// Pastikan ada pesanan aktif (dari SESSION atau dari database berstatus 'menunggu', termasuk pesanan dari chatbot)
$idPesanan = isset($_SESSION['id_pesanan_aktif']) ? (int)$_SESSION['id_pesanan_aktif'] : null;

if (!$idPesanan) {
    if (isset($_GET['meja']) && $_GET['meja'] !== '') {
        $mejaAman = $conn->real_escape_string(trim($_GET['meja']));
        $cekAktif = $conn->query("SELECT id_keranjang FROM keranjang WHERE nomor_meja = '$mejaAman' AND status = 'menunggu' ORDER BY id_keranjang DESC LIMIT 1");
    } else {
        $cekAktif = $conn->query("SELECT id_keranjang FROM keranjang WHERE status = 'menunggu' ORDER BY id_keranjang DESC LIMIT 1");
    }

    if ($cekAktif && $rowAktif = $cekAktif->fetch_assoc()) {
        $idPesanan = (int)$rowAktif['id_keranjang'];
        $_SESSION['id_pesanan_aktif'] = $idPesanan;
    }
}

if (!$idPesanan) {
    header("Location: menu.php");
    exit;
}

// Ambil info pesanan
$queryPesanan = $conn->query("SELECT * FROM keranjang WHERE id_keranjang = $idPesanan");
$dataPesanan  = $queryPesanan ? $queryPesanan->fetch_assoc() : null;

if (!$dataPesanan || $dataPesanan['status'] !== 'menunggu') {
    unset($_SESSION['id_pesanan_aktif']);
    unset($_SESSION['keranjang']);
    header("Location: index.php");
    exit;
}

// Ambil semua daftar rincian menu
$queryDetail = $conn->query("SELECT id_menu, nama_pesanan, SUM(jumlah) AS jumlah, catatan, harga_satuan 
                             FROM detail_keranjang 
                             WHERE id_keranjang = $idPesanan 
                             GROUP BY id_menu, nama_pesanan, catatan, harga_satuan 
                             ORDER BY MIN(id_detail_keranjang) ASC");
$semuaMenu = [];
$totalItem = 0;
while ($row = $queryDetail->fetch_assoc()) {
    $semuaMenu[] = $row;
    $totalItem += (int)$row['jumlah'];
}

// Inisialisasi total keseluruhan sebelum loop
$totalSubtotal = 0;
foreach ($semuaMenu as $item) {
    $totalSubtotal += ((float)$item['harga_satuan'] * (int)$item['jumlah']);
}

// Hitung Pajak (10%) dan Service (2%)
$pajak      = $totalSubtotal * 0.10;
$service    = $totalSubtotal * 0.02;
$grandTotal = $totalSubtotal + $pajak + $service;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Anda - Warung Mogan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --mogan-teal: #0b6056;
            --mogan-teal-dark: #07463f;
            --mogan-bg: #f8fafc;
        }

        body {
            background-color: var(--mogan-bg);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        .navbar-mogan {
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .logo-navbar {
            max-height: 48px;
            width: auto;
            object-fit: contain;
        }

        .badge-meja {
            background-color: #e6f4f2;
            color: var(--mogan-teal);
            border: 1.5px solid var(--mogan-teal);
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.4rem 1rem;
            border-radius: 50px;
        }

        .card-pesanan {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(11, 96, 86, 0.08);
            background: #ffffff;
        }

        .header-pesanan {
            background: linear-gradient(135deg, var(--mogan-teal) 0%, var(--mogan-teal-dark) 100%);
            border-radius: 20px 20px 0 0;
            color: #ffffff;
            padding: 1.5rem;
        }

        .status-badge {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 50px;
            padding: 0.4rem 1.2rem;
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .btn-tambah {
            background-color: #ffffff;
            color: var(--mogan-teal);
            border: 2px solid var(--mogan-teal);
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-tambah:hover {
            background-color: var(--mogan-teal);
            color: #ffffff;
        }

        .btn-kasir {
            background-color: var(--mogan-teal);
            color: #ffffff;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-kasir:hover {
            background-color: var(--mogan-teal-dark);
            color: #ffffff;
        }

        .notice-kasir {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mogan sticky-top py-2">
    <div class="container d-flex justify-content-between align-items-center">
        <img src="assets/images/logo_mogan.jpeg" alt="Logo Warung Mogan" class="logo-navbar" onerror="this.src='https://placehold.co/150x50?text=Warung+Mogan'">

        <div class="badge-meja d-flex align-items-center gap-2 shadow-sm">
            <span>Meja: <span class="text-dark"><?= htmlspecialchars($dataPesanan['nomor_meja']) ?></span></span>
        </div>

        <a href="menu.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Menu
        </a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card card-pesanan">
                <div class="header-pesanan text-center">
                    <div class="d-inline-flex p-3 rounded-circle bg-white bg-opacity-10 mb-2">
                        <i class="bi bi-hourglass-split fs-2 text-white"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Pesanan Anda</h4>
                    <p class="small text-white-50 mb-0">Warung Mogan Vegetarian • Antrean #<?= (int)$dataPesanan['id_keranjang'] ?></p>
                </div>

                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="status-badge shadow-sm">
                            <span role="status"></span>
                            Pesanan Sedang Diproses
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-secondary mb-0">Daftar Menu Dipesan (Termasuk Tambahan)</h6>
                        <span class="badge bg-secondary rounded-pill"><?= $totalItem ?> Item</span>
                    </div>

                    <!-- Daftar Menu yang Dipesan -->
                    <div class="list-group list-group-flush mb-4">
                        <?php foreach ($semuaMenu as $item):
                            $subtotal = (float)$item['harga_satuan'] * (int)$item['jumlah'];
                        ?>
                        <div class="list-group-item px-0 py-2 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($item['nama_pesanan']) ?></span>
                                    <span class="text-muted small ms-1">x<?= (int)$item['jumlah'] ?></span>
                                    <?php if (!empty($item['catatan'])): ?>
                                        <div class="small text-muted fst-italic">
                                            <i class="bi bi-chat-left-dots me-1"></i>Catatan: <?= htmlspecialchars($item['catatan']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="fw-bold text-dark">
                                    Rp <?= number_format($subtotal, 0, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Rincian Total Harga, Pajak, & Service -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span>Subtotal Menu</span>
                            <span>Rp <?= number_format($totalSubtotal, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span>Pajak (10%)</span>
                            <span>Rp <?= number_format($pajak, 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 text-muted small border-bottom pb-2">
                            <span>Service (2%)</span>
                            <span>Rp <?= number_format($service, 0, ',', '.') ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">Total Keseluruhan</span>
                            <h4 class="fw-bold mb-0" style="color: var(--mogan-teal);">
                                Rp <?= number_format($grandTotal, 0, ',', '.') ?>
                            </h4>
                        </div>
                    </div>

                    <div class="notice-kasir d-flex align-items-start gap-3 mb-4">
                        <i class="bi bi-shop fs-3 text-warning"></i>
                        <div class="small text-dark">
                            <strong>Pesanan Anda tercatat di kasir!</strong><br>
                            Setiap penambahan menu otomatis diperbarui di meja Anda (<strong>Meja <?= htmlspecialchars($dataPesanan['nomor_meja']) ?></strong>). Pembayaran dilakukan di kasir saat selesai bersantap.
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="d-grid gap-2">
                        <a href="menu.php" class="btn btn-tambah py-2 text-center text-decoration-none">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Menu Lain
                        </a>
                        <a href="keranjang.php?aksi=selesai_keluar" class="btn btn-outline-danger rounded-pill py-2 text-center text-decoration-none fw-semibold" onclick="return confirm('Apakah Anda yakin sudah selesai dan ingin keluar?')">
                            <i class="bi bi-box-arrow-right me-1"></i> Selesai Bersantap / Keluar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>