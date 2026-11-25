<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_warung_mogan";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Ambil pesan notifikasi jika ada
$pesanSukses = "";
if (isset($_SESSION['pesan_sukses'])) {
    $pesanSukses = $_SESSION['pesan_sukses'];
    unset($_SESSION['pesan_sukses']); // Hapus setelah dibaca sekali
}

// Ambil semua menu dengan urutan menu terbaru di paling atas
$queryMenu = $conn->query("SELECT * FROM menu ORDER BY id_menu DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Warung Mogan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --mogan-teal: #0b6056;
            --mogan-teal-dark: #07463f;
            --mogan-bg: #f4f7f6;
        }
        body {
            background-color: var(--mogan-bg);
            font-family: system-ui, -apple-system, sans-serif;
            padding-bottom: 85px;
        }
        .navbar-kasir {
            background: linear-gradient(135deg, var(--mogan-teal) 0%, var(--mogan-teal-dark) 100%);
        }
        .menu-thumb {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 12px;
        }
        .bottom-nav {
            background: #ffffff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.06);
        }
        .bottom-nav a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.8rem;
        }
        .bottom-nav a.active {
            color: var(--mogan-teal);
            font-weight: 700;
        }
    </style>
</head>
<body>

<!-- Header Navbar -->

<!-- 2. NAVBAR ATAS (Tempel kode menu di sini agar berada di bawah Kasir Mogan) -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm my-3 mx-3 rounded-3 px-3">

        <div class="container-fluid">
        <img src="../assets/images/logo_mogan.jpeg" alt="Logo" class="rounded-1" height="32" onerror="this.style.display='none'">
            <span class="navbar-brand fw-bold text-success">Kelola Menu</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav gap-2">
                    <li class="nav-item">
                        <a class="nav-link px-3 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active bg-success text-white' : 'text-dark' ?>" href="index.php">
                            <i class="bi bi-house-door me-1"></i> Dasbor
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'pesanan.php' ? 'active bg-success text-white' : 'text-dark' ?>" href="pesanan.php">
                            <i class="bi bi-receipt-cutoff me-1"></i> Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active bg-success text-white' : 'text-dark' ?>" href="menu.php">
                            <i class="bi bi-grid me-1"></i> Kelola Menu
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link px-3 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'tambah.php' ? 'active bg-success text-white' : 'text-dark' ?>" href="tambah.php">
                            <i class="bi bi-plus-circle me-1 text-success"></i> Tambah
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</nav>

<div class="container py-4">
    <!-- Notifikasi Menu Berhasil Ditambahkan -->
    <?php if (!empty($pesanSukses)): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm py-3 px-3 d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <div><?= $pesanSukses ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-dark">Daftar Menu</h5>
        <span class="badge bg-secondary rounded-pill"><?= $queryMenu->num_rows ?> Menu</span>
    </div>

    <!-- Daftar List Menu -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="list-group list-group-flush">
            <?php if ($queryMenu->num_rows === 0): ?>
                <div class="text-center p-5 text-muted">
                    <i class="bi bi-egg-fried fs-1 mb-2 d-block"></i>
                    Belum ada menu yang terdaftar. Klik <strong>Menu Baru</strong> untuk menambahkan.
                </div>
            <?php else: ?>
                <?php while ($row = $queryMenu->fetch_assoc()): 
                    $gambar = !empty($row['gambar']) ? '../assets/images/' . $row['gambar'] : 'https://placehold.co/100x100?text=Menu';
                ?>
                    <div class="list-group-item p-3 d-flex align-items-center justify-content-between gap-3">
                        <img src="<?= htmlspecialchars($gambar) ?>" class="menu-thumb shadow-sm" alt="<?= htmlspecialchars($row['nama_menu']) ?>" onerror="this.src='https://placehold.co/100x100?text=No+Img'">
                        
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($row['nama_menu']) ?></h6>
                            <span class="fw-bold text-success small">Rp <?= number_format($row['harga'], 0, ',', '.') ?></span>
                            <span class="badge bg-light text-secondary border ms-1 rounded-pill small"><?= ucfirst($row['kategori'] ?? 'makanan') ?></span>
                            <p class="text-muted small mb-0 text-truncate mt-1" style="max-width: 200px;"><?= htmlspecialchars($row['deskripsi'] ?? '-') ?></p>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="edit.php?id=<?= $row['id_menu'] ?>" class="btn btn-outline-primary btn-sm rounded-circle p-2">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <a href="hapus.php?id=<?= $row['id_menu'] ?>" class="btn btn-outline-danger btn-sm rounded-circle p-2" onclick="return confirm('Apakah Anda yakin ingin menghapus menu <?= htmlspecialchars($row['nama_menu']) ?>?')">
                                <i class="bi bi-trash-fill"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>