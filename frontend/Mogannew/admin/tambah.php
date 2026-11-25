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

$pesanError   = "";
$pesanSukses  = "";

if (isset($_POST['simpan_menu'])) {
    $nama      = trim($conn->real_escape_string($_POST['nama_menu']));
    $harga     = (float)$_POST['harga'];
    $deskripsi = trim($conn->real_escape_string($_POST['deskripsi']));
    $kategori  = $conn->real_escape_string($_POST['kategori'] ?? 'makanan');
    $namaFileBaru = "";

    // Validasi dan Proses Unggah Gambar
    if (!empty($_FILES['gambar']['name'])) {
        $namaFileAsli = $_FILES['gambar']['name'];
        $tmpName      = $_FILES['gambar']['tmp_name'];
        $ekstensi     = strtolower(pathinfo($namaFileAsli, PATHINFO_EXTENSION));
        $formatValid  = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ekstensi, $formatValid)) {
            // Beri nama file unik agar tidak bertabrakan
            $namaFileBaru = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $namaFileAsli);
            $folderTujuan = "../assets/images/" . $namaFileBaru;

            if (!move_uploaded_file($tmpName, $folderTujuan)) {
                $pesanError = "Gagal mengunggah foto ke folder assets/images.";
            }
        } else {
            $pesanError = "Format foto harus JPG, JPEG, PNG, atau WEBP.";
        }
    }

    if (empty($pesanError)) {
        $sql = "INSERT INTO menu (nama_menu, harga, deskripsi, gambar, kategori) 
                VALUES ('$nama', '$harga', '$deskripsi', '$namaFileBaru', '$kategori')";

        if ($conn->query($sql)) {
            header("Location: menu.php");
            exit;
        } else {
            $pesanError = "Gagal menyimpan data ke database: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Menu - Warung Mogan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --mogan-teal: #0b6056;
            --mogan-teal-dark: #07463f;
            --mogan-leaf: #34d399;
            --mogan-bg: #f4f7f6;
        }

        body {
            background-color: var(--mogan-bg);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            padding-bottom: 85px;
        }

        .navbar-kasir {
            background: linear-gradient(135deg, var(--mogan-teal) 0%, var(--mogan-teal-dark) 100%);
        }

        .card-form {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(11, 96, 86, 0.08);
            background: #ffffff;
        }

        .btn-mogan {
            background: linear-gradient(135deg, var(--mogan-teal) 0%, var(--mogan-teal-dark) 100%);
            color: #ffffff;
            border: none;
            transition: all 0.2s ease-in-out;
        }

        .btn-mogan:hover {
            background: #07463f;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .preview-img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 14px;
            display: none;
            border: 2px dashed #0b6056;
        }

        .bottom-nav {
            background: #ffffff;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.06);
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

<!-- Navbar Atas -->
<!-- Navbar Gabungan (Logo dan Menu Menjadi Satu Baris) -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm my-3 mx-3 rounded-3 px-4 py-3">
    <div class="container-fluid px-0">
        <!-- Logo / Nama Brand di Sebelah Kiri -->
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-success fs-4" href="pesanan.php">
            <img src="../assets/images/logo_mogan.jpeg" alt="Logo" class="rounded-1" height="32" onerror="this.style.display='none'">
            <span>Kasir Mogan</span>
        </a>

        <!-- Tombol Toggle untuk Tampilan HP (Hamburger Menu) -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Daftar Menu Navigasi di Sebelah Kanan -->
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center gap-2">
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active bg-success text-white fw-semibold' : 'text-dark' ?>" href="index.php">
                        <i class="bi bi-house-door me-1"></i> Dasbor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'pesanan.php' ? 'active bg-success text-white fw-semibold' : 'text-dark' ?>" href="pesanan.php">
                        <i class="bi bi-receipt-cutoff me-1"></i> Pesanan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active bg-success text-white fw-semibold' : 'text-dark' ?>" href="menu.php">
                        <i class="bi bi-grid me-1"></i> Kelola Menu
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3 py-2 rounded-pill <?= basename($_SERVER['PHP_SELF']) == 'tambah.php' ? 'active bg-success text-white fw-semibold' : 'text-dark' ?>" href="tambah.php">
                        <i class="bi bi-plus-circle me-1 text-success"></i> Tambah
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="mb-3">
                <h4 class="fw-bold mb-1 text-dark">Form Menu Baru</h4>
                <p class="text-muted small mb-0">Isi detail hidangan nabati khas Warung Mogan yang ingin ditambahkan ke katalog pelanggan.</p>
            </div>

            <?php if (!empty($pesanError)): ?>
                <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $pesanError ?></span>
                </div>
            <?php endif; ?>

            <div class="card card-form p-4">
                <form method="POST" enctype="multipart/form-data">
                    <!-- Nama Menu -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Nama Menu <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-cup-hot"></i></span>
                            <input type="text" name="nama_menu" class="form-control rounded-end-3" placeholder="Contoh: Nasi Rendang Jamur" required>
                        </div>
                    </div>

                    <!-- Harga -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Harga (Rp) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">Rp</span>
                            <input type="number" name="harga" class="form-control rounded-end-3" placeholder="30000" min="0" required>
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Kategori Menu <span class="text-danger">*</span></label>
                        <select name="kategori" class="form-select rounded-3">
                            <option value="makanan">Makanan</option>
                            <option value="minuman">Minuman</option>
                            <option value="snack">Camilan / Side Dish</option>
                        </select>
                    </div>

                    <!-- Deskripsi -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-dark">Deskripsi Menu</label>
                        <textarea name="deskripsi" class="form-control rounded-3" rows="3" placeholder="Contoh: Olahan jamur dengan racikan bumbu rempah rendang otentik khas Mogan."></textarea>
                    </div>

                    <!-- Upload Foto & Preview -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-dark">Foto Menu</label>
                        <input type="file" name="gambar" id="inputFoto" class="form-control rounded-3 mb-2" accept="image/*" onchange="previewGambar(this)">
                        
                        <!-- Box Preview Gambar Sebelum Disimpan -->
                        <img id="tampilanPreview" class="preview-img mt-2" alt="Pratinjau Foto Menu">
                    </div>

                    <!-- Tombol Simpan -->
                    <div class="d-grid gap-2">
                        <button type="submit" name="simpan_menu" class="btn btn-mogan py-2 rounded-pill fw-semibold shadow-sm">
                            <i class="bi bi-cloud-arrow-up-fill me-1"></i> Simpan ke Katalog Menu
                        </button>
                        <a href="menu.php" class="btn btn-outline-secondary py-2 rounded-pill small">
                            Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Fungsi pratinjau langsung foto menu yang diunggah
    function previewGambar(input) {
        var preview = document.getElementById('tampilanPreview');
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.style.display = 'none';
        }
    }
</script>
</body>
</html>