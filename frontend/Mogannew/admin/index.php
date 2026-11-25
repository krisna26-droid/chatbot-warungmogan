<?php
session_start();

// --- Pengecekan Keamanan Session Login ---
if (!isset($_SESSION['status_login']) || $_SESSION['status_login'] !== true) {
    header("Location: login.php?error=unauthorized");
    exit();
}
// ----------------------------------------

require_once __DIR__ . '/../config.php';

// --- TAMBAHKAN KODINGAN INI DI SINI ---
// Ambil tanggal mulai dan selesai dari URL (GET), jika kosong gunakan default hari ini
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-d');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');
// -------------------------------------
// ... Sisa kode fungsionalitas admin dan modal Anda berikutnya ...

// 1. Eksekusi Cepat Edit Menu Langsung dari Modal
if (isset($_POST['aksi_edit_menu'])) {
    $idMenu    = (int)$_POST['id_menu'];
    $namaMenu  = trim($conn->real_escape_string($_POST['nama_menu']));
    $harga     = (float)$_POST['harga'];
    $kategori  = $conn->real_escape_string($_POST['kategori']);
    $deskripsi = trim($conn->real_escape_string($_POST['deskripsi']));

    if (!empty($_FILES['gambar']['name'])) {
        $namaFile = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['gambar']['name']);
        $tujuan   = "../assets/images/" . $namaFile;
        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $tujuan)) {
            $conn->query("UPDATE menu SET nama_menu='$namaMenu', harga='$harga', kategori='$kategori', deskripsi='$deskripsi', gambar='$namaFile' WHERE id_menu=$idMenu");
        }
    } else {
        $conn->query("UPDATE menu SET nama_menu='$namaMenu', harga='$harga', kategori='$kategori', deskripsi='$deskripsi' WHERE id_menu=$idMenu");
    }

    // Simpan pesan ke sesi lalu arahkan kembali ke tabel menu
    $_SESSION['notif_sukses'] = "Menu <strong>" . htmlspecialchars($namaMenu) . "</strong> berhasil diperbarui!";
    header("Location: index.php#section-menu");
    exit;
}

if (isset($_GET['hapus_menu'])) {
    $idHapus = (int)$_GET['hapus_menu'];
    $cekGbr  = $conn->query("SELECT gambar, nama_menu FROM menu WHERE id_menu = $idHapus")->fetch_assoc();
    if (!empty($cekGbr['gambar']) && file_exists("../assets/images/" . $cekGbr['gambar'])) {
        unlink("../assets/images/" . $cekGbr['gambar']);
    }
    $conn->query("DELETE FROM menu WHERE id_menu = $idHapus");
    $_SESSION['notif_sukses'] = "Menu berhasil dihapus!";
    header("Location: index.php#section-menu");
    exit;
}
// 2. Eksekusi Hapus Menu
if (isset($_GET['hapus_menu'])) {
    $idHapus = (int)$_GET['hapus_menu'];
    $cekGbr  = $conn->query("SELECT gambar FROM menu WHERE id_menu = $idHapus")->fetch_assoc();
    if (!empty($cekGbr['gambar']) && file_exists("../assets/images/" . $cekGbr['gambar'])) {
        unlink("../assets/images/" . $cekGbr['gambar']);
    }
    $conn->query("DELETE FROM menu WHERE id_menu = $idHapus");
    header("Location: index.php");
    exit;
}

// 3. Ringkasan Kartu Atas
$totalPesananMenunggu = $conn->query("SELECT COUNT(*) as t FROM keranjang WHERE status = 'menunggu'")->fetch_assoc()['t'] ?? 0;
$totalMenu            = $conn->query("SELECT COUNT(*) as t FROM menu")->fetch_assoc()['t'] ?? 0;
$totalOmzetSemua      = $conn->query("SELECT SUM(total_harga) as t FROM keranjang WHERE status = 'selesai'")->fetch_assoc()['t'] ?? 0;

// 4. Logika Filter Kalender Laporan Detail (Default: Hari Ini)
$tglMulai   = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-d');
$tglSelesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// Ringkasan omzet dan transaksi dalam rentang tanggal yang dipilih
$qRingkasanLaporan = $conn->query("
    SELECT 
        COUNT(id_keranjang) AS total_transaksi,
        COALESCE(SUM(total_harga), 0) AS total_omzet,
        COALESCE(AVG(total_harga), 0) AS rata_rata
    FROM keranjang 
    WHERE status = 'selesai' 
      AND DATE(created_at) BETWEEN '$tglMulai' AND '$tglSelesai'
")->fetch_assoc();

// Total porsi terjual pada rentang tanggal tersebut
$totalPorsiTerjual = $conn->query("
    SELECT COALESCE(SUM(dk.jumlah), 0) AS total_item
    FROM detail_keranjang dk
    JOIN keranjang k ON dk.id_keranjang = k.id_keranjang
    WHERE k.status = 'selesai' 
      AND DATE(k.created_at) BETWEEN '$tglMulai' AND '$tglSelesai'
")->fetch_assoc()['total_item'] ?? 0;

// Rekap item menu yang laku pada rentang tanggal tersebut
$qMenuTerlaris = $conn->query("
    SELECT 
        dk.nama_pesanan,
        SUM(dk.jumlah) AS total_qty,
        SUM(dk.jumlah * dk.harga_satuan) AS subtotal
    FROM detail_keranjang dk
    JOIN keranjang k ON dk.id_keranjang = k.id_keranjang
    WHERE k.status = 'selesai' 
      AND DATE(k.created_at) BETWEEN '$tglMulai' AND '$tglSelesai'
    GROUP BY dk.nama_pesanan
    ORDER BY total_qty DESC
");

// Rincian transaksi struk per meja pada rentang tanggal tersebut
$qDaftarTransaksi = $conn->query("
    SELECT * 
    FROM keranjang 
    WHERE status = 'selesai' 
      AND DATE(created_at) BETWEEN '$tglMulai' AND '$tglSelesai'
    ORDER BY created_at DESC
");

// 5. Data Daftar Semua Menu
$semuaMenu = $conn->query("SELECT * FROM menu ORDER BY id_menu DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Warung Mogan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --mogan-teal: #0b6056;
            --mogan-teal-dark: #07463f;
            --mogan-teal-light: #e6f4f2;
            --bg-body: #f4f7fb;
            --sidebar-width: 250px;
        }

        body {
            background-color: var(--bg-body);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        /* Sidebar Kiri Tanpa Profil */
        .sidebar {
            width: var(--sidebar-width);
            background: #ffffff;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0.75rem;
            margin: 0;
            flex-grow: 1;
        }

        .sidebar-menu .nav-item {
            margin-bottom: 0.35rem;
        }

        .sidebar-menu .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            color: #475569;
            font-size: 0.92rem;
            font-weight: 500;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .sidebar-menu .nav-link:hover {
            background-color: #f8fafc;
            color: var(--mogan-teal);
        }

        .sidebar-menu .nav-link.active {
            background-color: var(--mogan-teal);
            color: #ffffff;
            font-weight: 600;
        }

        .sidebar-menu .nav-link.active i {
            color: #ffffff !important;
        }

        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
        }

        

        /* Top Navbar */
        .top-navbar {
            background: #ffffff;
            padding: 0.85rem 2rem;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .content-area {
            padding: 2rem;
        }

        /* Kartu Metrik */
        .card-metric {
            background: #ffffff;
            border: none;
            border-radius: 18px;
            padding: 1.4rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
        }

        .metric-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .card-custom {
            background: #ffffff;
            border: none;
            border-radius: 18px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
        }

        .table-thumb {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: cover;
        }

        /* Responsif Smartphone */
        @media (max-width: 991.98px) {
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
            .sidebar.show {
                margin-left: 0;
            }
            .main-wrapper {
                margin-left: 0;
            }
            .content-area {
                padding: 1.25rem;
            }
        }
        
        @media print {
    /* Sembunyikan elemen navigasi, menu samping, dan tombol */
    .sidebar, 
    nav, 
    .no-print, 
    #section-menu, 
    .btn, 
    form, 
    .header-nav {
        display: none !important;
    }

    body {
        background-color: #fff !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .card, .card-custom {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
    </style>
</head>
<body>

<!-- SIDEBAR KIRI (BERSIH TANPA PROFIL) -->
<aside class="sidebar" id="sidebarMenu">
    <div class="sidebar-header">
        <a href="index.php" class="d-flex align-items-center justify-content-center gap-2 text-decoration-none">
            <img src="../assets/images/logo_mogan.jpeg" alt="Logo Warung Mogan" height="36" class="rounded" onerror="this.src='https://placehold.co/120x36?text=Mogan'">
            <span class="fw-bold fs-6 text-dark">Admin Mogan</span>
        </a>
    </div>

    <ul class="sidebar-menu">
        <li class="nav-item">
            <a href="index.php" class="nav-link active">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-house-door-fill"></i>
                    <span>Dashboard</span>
                </span>
            </a>
        </li>
        <li class="nav-item">
            <a href="pesanan.php" class="nav-link">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt-cutoff text-secondary"></i>
                    <span>Pesanan Masuk</span>
                </span>
                <?php if ($totalPesananMenunggu > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $totalPesananMenunggu ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a href="#section-laporan" class="nav-link">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-check text-secondary"></i>
                    <span>Laporan Penjualan</span>
                </span>
            </a>
        </li>
        <li class="nav-item">
            <a href="#section-menu" class="nav-link">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-secondary"></i>
                    <span>Kelola & Edit Menu</span>
                </span>
            </a>
        </li>
        <li class="nav-item">
            <a href="tambah.php" class="nav-link">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle text-secondary"></i>
                    <span>Tambah Menu</span>
                </span>
            </a>
        </li>
        <li class="nav-item mt-3 pt-3 border-top">
            <a href="../menu.php" class="nav-link" target="_blank">
                <span class="d-flex align-items-center gap-2">
                    <i class="bi bi-box-arrow-up-right text-muted"></i>
                    <span>Katalog Tamu</span>
                </span>
            </a>
        </li>

<!-- TOMBOL LOGOUT DI POJOK KIRI BAWAH -->
<div style="margin-top: auto; padding: 270px 15px 35px 15px; border-top: 1px solid #eee; background-color: #fff;">
        <div style="font-size: 11px; color: #666; margin-bottom: 6px; text-align: center;">
            Masuk sebagai: <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong>
        </div>
        <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="btn btn-danger btn-sm w-100 rounded-pill text-white" style="display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; background-color: #dc3545; border-color: #dc3545;">
            <i class="bi bi-box-arrow-right"></i> Keluar (Logout)
        </a>
    </div>    </ul>
</aside>

<!-- AREA KONTEN UTAMA -->
<div class="main-wrapper">
    <!-- TOP NAVBAR BERSIH -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-light d-lg-none border-0" id="btnToggleSidebar">
                <i class="bi bi-list fs-4"></i>
            </button>
            <h5 class="fw-bold mb-0 text-dark">Warung Mogan Vegetarian</h5>
        </div>
        <span class="badge rounded-pill px-3 py-2 fw-semibold" style="background-color: var(--mogan-teal-light); color: var(--mogan-teal);">
            Panel Kasir & Laporan
        </span>
    </header>

    <main class="content-area">
        <!-- Notifikasi -->
        <?php if (!empty($notif)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $notif ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Judul Halaman -->
        <div class="mb-4">
            <h3 class="fw-bold text-dark mb-1">Dashboard</h3>
            <span class="text-muted small">Kelola operasional dan pantau transaksi penjualan Warung Mogan secara berkala.</span>
        </div>

        <!-- 3 KARTU METRIK UTAMA -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card-metric">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Pesanan Menunggu</span>
                        <h2 class="fw-bold mb-1 text-dark"><?= $totalPesananMenunggu ?></h2>
                        <small class="text-muted">Antrean meja aktif</small>
                    </div>
                    <div class="metric-icon-box" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card-metric">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Daftar Menu</span>
                        <h2 class="fw-bold mb-1 text-dark"><?= $totalMenu ?></h2>
                        <small class="text-muted">Item hidangan terdaftar</small>
                    </div>
                    <div class="metric-icon-box" style="background-color: var(--mogan-teal-light); color: var(--mogan-teal);">
                        <i class="bi bi-card-checklist"></i>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="card-metric">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Total Penjualan</span>
                        <h4 class="fw-bold mb-1" style="color: var(--mogan-teal);">
                            Rp <?= number_format($totalOmzetSemua, 0, ',', '.') ?>
                        </h4>
                        <small class="text-muted">Seluruh transaksi lunas</small>
                    </div>
                    <div class="metric-icon-box" style="background-color: #ecfdf5; color: #059669;">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION: LAPORAN PENJUALAN DETAIL DENGAN KALENDER -->
        <div class="card card-custom p-4 mb-4" id="section-laporan">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-calendar-event me-2 text-success"></i>Laporan Penjualan Lengkap
                    </h5>
                    <small class="text-muted">Pilih tanggal untuk melihat rekapitulasi penjualan, porsi terjual, dan rincian transaksi.</small>
                </div>
                <button type="button" 
        onclick="window.print()" 
        class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm no-print">
    <i class="bi bi-printer me-1"></i> Cetak Laporan
</button>
            </div>

            <!-- Form Pemilih Tanggal Kalender -->
            <div class="bg-light p-3 rounded-4 mb-4 border">
                <form method="GET" action="index.php#section-laporan" class="row g-2 align-items-end">
                    <div class="col-12 col-sm-4 col-md-3">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            <i class="bi bi-calendar-range me-1"></i> Dari Tanggal
                        </label>
                        <input type="date" name="tgl_mulai" class="form-control form-control-sm rounded-3 shadow-none" value="<?= htmlspecialchars($tglMulai) ?>" required>
                    </div>

                    <div class="col-12 col-sm-4 col-md-3">
                        <label class="form-label small fw-bold text-secondary mb-1">
                            <i class="bi bi-calendar-check me-1"></i> Sampai Tanggal
                        </label>
                        <input type="date" name="tgl_selesai" class="form-control form-control-sm rounded-3 shadow-none" value="<?= htmlspecialchars($tglSelesai) ?>" required>
                    </div>

                    <div class="col-12 col-sm-4 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-3 w-100 fw-semibold" style="background-color: var(--mogan-teal);">
                            <i class="bi bi-funnel me-1"></i> Tampilkan
                        </button>
                        <a href="index.php#section-laporan" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Hari Ini">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Ringkasan Nilai Laporan Terfilter -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="p-3 rounded-4 border h-100" style="background-color: #f0fdf4;">
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Pendapatan</span>
                        <h4 class="fw-bold mb-0" style="color: var(--mogan-teal);">
                            Rp <?= number_format($qRingkasanLaporan['total_omzet'], 0, ',', '.') ?>
                        </h4>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="p-3 rounded-4 border h-100 bg-white">
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Transaksi Lunas</span>
                        <h4 class="fw-bold mb-0 text-dark">
                            <?= number_format($qRingkasanLaporan['total_transaksi'], 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Struk</span>
                        </h4>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="p-3 rounded-4 border h-100 bg-white">
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Rata-rata / Meja</span>
                        <h5 class="fw-bold mb-0 text-dark">
                            Rp <?= number_format($qRingkasanLaporan['rata_rata'], 0, ',', '.') ?>
                        </h5>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="p-3 rounded-4 border h-100 bg-white">
                        <span class="text-muted small fw-semibold text-uppercase d-block mb-1">Porsi Terjual</span>
                        <h4 class="fw-bold mb-0 text-success">
                            <?= number_format($totalPorsiTerjual, 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Item</span>
                        </h4>
                    </div>
                </div>
            </div>

            <!-- Tabel Menu Terjual -->
            <div class="mb-4">
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-pie-chart-fill me-1 text-success"></i> Rekap Item Menu yang Laku
                </h6>
                <div class="table-responsive border rounded-4">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th>Nama Hidangan</th>
                                <th class="text-center">Kuantitas</th>
                                <th class="text-end">Total Penjualan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($qMenuTerlaris->num_rows === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted small">Tidak ada transaksi menu pada periode ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($rowK = $qMenuTerlaris->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($rowK['nama_pesanan']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill px-3"><?= $rowK['total_qty'] ?> Porsi</span>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            Rp <?= number_format($rowK['subtotal'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Transaksi Struk Meja -->
            <div>
                <h6 class="fw-bold text-dark mb-2">
                    <i class="bi bi-receipt-cutoff me-1 text-success"></i> Rincian Struk Pelunasan
                </h6>
                <div class="table-responsive border rounded-4">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-muted text-uppercase">
                                <th>Waktu Bayar</th>
                                <th>Meja</th>
                                <th>Menu & Catatan</th>
                                <th class="text-end">Total Bayar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($qDaftarTransaksi->num_rows === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted small">Belum ada riwayat pesanan selesai pada periode ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($trx = $qDaftarTransaksi->fetch_assoc()): 
                                    $idKeranjang = $trx['id_keranjang'];
                                    $qItems = $conn->query("SELECT * FROM detail_keranjang WHERE id_keranjang = $idKeranjang");
                                ?>
                                    <tr>
                                        <td class="small text-muted" style="white-space: nowrap;">
                                            <?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill fw-bold" style="background-color: var(--mogan-teal-light); color: var(--mogan-teal);">
                                                Meja <?= htmlspecialchars($trx['nomor_meja']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <ul class="list-unstyled mb-0 small">
                                                <?php while ($it = $qItems->fetch_assoc()): ?>
                                                    <li class="text-secondary">
                                                        <strong><?= htmlspecialchars($it['nama_pesanan']) ?></strong> (x<?= $it['jumlah'] ?>)
                                                        <?php if (!empty($it['catatan'])): ?>
                                                            <span class="text-danger fst-italic">- "<?= htmlspecialchars($it['catatan']) ?>"</span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endwhile; ?>
                                            </ul>
                                        </td>
                                        <td class="text-end fw-bold text-success" style="white-space: nowrap;">
                                            Rp <?= number_format($trx['total_harga'], 0, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION: KELOLA & EDIT MENU -->
        <div class="card card-custom p-4" id="section-menu">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Kelola Menu Hidangan</h5>
                    <small class="text-muted">Edit nama, harga, deskripsi, atau hapus item menu restoran</small>
                </div>
                <a href="tambah.php" class="btn btn-success btn-sm rounded-pill px-3 fw-semibold" style="background-color: var(--mogan-teal);">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Menu
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-muted text-uppercase">
                            <th>Foto</th>
                            <th>Nama Menu</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($semuaMenu->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">Belum ada menu yang didaftarkan.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($m = $semuaMenu->fetch_assoc()): 
                                $gbr = !empty($m['gambar']) ? '../assets/images/' . $m['gambar'] : 'https://placehold.co/100x100?text=Menu';
                            ?>
                                <tr>
                                    <td>
                                        <img src="<?= htmlspecialchars($gbr) ?>" class="table-thumb" alt="Menu" onerror="this.src='https://placehold.co/100x100?text=No+Img'">
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($m['nama_menu']) ?></div>
                                        <small class="text-muted text-truncate d-block" style="max-width: 250px;">
                                            <?= htmlspecialchars($m['deskripsi'] ?? '-') ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border rounded-pill">
                                            <?= ucfirst($m['kategori'] ?? 'makanan') ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success">
                                        Rp <?= number_format($m['harga'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Tombol Pemicu Modal Edit -->
                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-circle p-2 me-1" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditMenu<?= $m['id_menu'] ?>" 
                                                title="Edit Menu">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>

<!-- Tombol Pemicu Modal Hapus -->
<button type="button" 
        class="btn btn-outline-danger btn-sm rounded-circle p-2" 
        data-bs-toggle="modal" 
        data-bs-target="#modalHapusMenu<?= $m['id_menu'] ?>" 
        title="Hapus Menu">
    <i class="bi bi-trash-fill"></i>
</button>
<div class="modal fade" id="modalHapusMenu<?= $m['id_menu'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered text-start">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-danger-subtle py-3">
                <h6 class="modal-title fw-bold text-danger mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3 text-danger">
                    <i class="bi bi-trash3-fill fs-1"></i>
                </div>
                <h6 class="fw-bold text-dark mb-2">Hapus Menu Ini?</h6>
                <p class="text-muted small mb-0">
                    Apakah Anda yakin ingin menghapus menu <strong>"<?= htmlspecialchars($m['nama_menu']) ?>"</strong>?
                </p>
            </div>
            <div class="modal-footer border-0 bg-light p-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">
                    Batal
                </button>
                <a href="index.php?hapus_menu=<?= $m['id_menu'] ?>" class="btn btn-danger btn-sm rounded-pill px-4 fw-semibold">
                    Yakin Hapus
                </a>
            </div>
        </div>
    </div>
</div>                    
              
                                <!-- POP-UP MODAL EDIT MENU -->
                                        <div class="modal fade" id="modalEditMenu<?= $m['id_menu'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered text-start">
                                                <div class="modal-content border-0 shadow rounded-4">
                                                    <div class="modal-header bg-light border-0">
                                                        <h6 class="modal-title fw-bold text-dark">
                                                            <i class="bi bi-pencil-square me-2"></i>Edit Menu
                                                        </h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" enctype="multipart/form-data">
                                                        <div class="modal-body p-4">
                                                            <input type="hidden" name="id_menu" value="<?= $m['id_menu'] ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Nama Menu</label>
                                                                <input type="text" name="nama_menu" class="form-control rounded-3" value="<?= htmlspecialchars($m['nama_menu']) ?>" required>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Harga (Rp)</label>
                                                                <input type="number" name="harga" class="form-control rounded-3" value="<?= $m['harga'] ?>" required>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Kategori</label>
                                                                <select name="kategori" class="form-select rounded-3">
                                                                    <option value="makanan" <?= ($m['kategori'] ?? '') === 'makanan' ? 'selected' : '' ?>>Makanan</option>
                                                                    <option value="minuman" <?= ($m['kategori'] ?? '') === 'minuman' ? 'selected' : '' ?>>Minuman</option>
                                                                </select>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-bold">Deskripsi</label>
                                                                <textarea name="deskripsi" class="form-control rounded-3" rows="3"><?= htmlspecialchars($m['deskripsi'] ?? '') ?></textarea>
                                                            </div>

                                                            <div class="mb-2">
                                                                <label class="form-label small fw-bold">Ganti Foto (Opsional)</label>
                                                                <input type="file" name="gambar" class="form-control rounded-3" accept="image/*">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" name="aksi_edit_menu" class="btn btn-success rounded-pill px-4" style="background-color: var(--mogan-teal);">Simpan Perubahan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div> <!-- Penutup Tag Sidebar Utama --><script>
    const btnToggle = document.getElementById('btnToggleSidebar');
    const sidebar = document.getElementById('sidebarMenu');
    if (btnToggle) {
        btnToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
</script>
</body>
</html>
