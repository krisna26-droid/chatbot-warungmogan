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

// Cek apakah kolom metode_pembayaran sudah tersedia di tabel keranjang
$hasMetodeCol = false;
$cekKolom = $conn->query("SHOW COLUMNS FROM keranjang LIKE 'metode_pembayaran'");
if ($cekKolom && $cekKolom->num_rows > 0) {
    $hasMetodeCol = true;
}

// 1. Logika Update Status Pesanan (Selesai / Batal)
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $idOrder = (int)$_GET['id'];
    $aksi    = $_GET['aksi'];

    if ($aksi === 'selesai') {
        $metodeRaw = strtolower(trim($_GET['metode_pembayaran'] ?? 'cash'));
        $metodeValid = ['cash', 'qris', 'kartu'];
        $metodeSimpan = in_array($metodeRaw, $metodeValid, true) ? $metodeRaw : 'cash';

        if ($hasMetodeCol) {
            $metodeEsc = $conn->real_escape_string($metodeSimpan);
            $conn->query("UPDATE keranjang SET status = 'selesai', metode_pembayaran = '$metodeEsc', total_harga = ROUND(total_harga * 1.12) WHERE id_keranjang = $idOrder");
        } else {
            $conn->query("UPDATE keranjang SET status = 'selesai', total_harga = ROUND(total_harga * 1.12) WHERE id_keranjang = $idOrder");
        }
    } elseif ($aksi === 'batal') {
        $conn->query("UPDATE keranjang SET status = 'dibatalkan' WHERE id_keranjang = $idOrder");
    }

    header("Location: pesanan.php");
    exit;
}

// 2. Filter Tab Status (Default: menunggu)
$tabAktif = (isset($_GET['tab']) && $_GET['tab'] === 'riwayat') ? 'riwayat' : 'menunggu';

if ($tabAktif === 'riwayat') {
    $queryPesanan = $conn->query("SELECT * FROM keranjang WHERE status IN ('selesai', 'dibatalkan') ORDER BY id_keranjang DESC LIMIT 30");
} else {
    $queryPesanan = $conn->query("SELECT * FROM keranjang WHERE status = 'menunggu' ORDER BY id_keranjang DESC");
}

// Hitung jumlah antrean aktif saat ini
$qHitung = $conn->query("SELECT COUNT(*) AS total FROM keranjang WHERE status = 'menunggu'");
$totalAntrean = $qHitung ? (int)($qHitung->fetch_assoc()['total'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir - Antrean Pesanan Warung Mogan</title>
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
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            padding-bottom: 85px;
        }

        .card-pesanan {
            border: none;
            border-radius: 18px;
            box-shadow: 0 4px 15px rgba(11, 96, 86, 0.08);
            background: #ffffff;
            overflow: hidden;
        }

        .badge-meja {
            background-color: #e6f4f2;
            color: var(--mogan-teal);
            border: 1.5px solid var(--mogan-teal);
            font-weight: 700;
            border-radius: 50px;
            padding: 0.35rem 0.85rem;
        }

        .catatan-tag {
            background-color: #fffbeb;
            color: #92400e;
            border-left: 3px solid #f59e0b;
            font-size: 0.82rem;
            padding: 0.35rem 0.6rem;
            border-radius: 4px;
        }

        .nav-pills-mogan .nav-link {
            color: #64748b;
            border-radius: 50px;
            font-weight: 600;
            padding: 0.4rem 1.2rem;
            font-size: 0.9rem;
        }

        .nav-pills-mogan .nav-link.active {
            background-color: var(--mogan-teal);
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm my-3 mx-3 rounded-3 px-3">
        <div class="container-fluid">
            <img src="../assets/images/logo_mogan.jpeg" alt="Logo" class="rounded-1 me-2" height="32" onerror="this.style.display='none'">
            <span class="navbar-brand fw-bold text-success">Kasir Mogan</span>
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

    <div class="container py-4">
        <!-- Header dan Tombol Tab -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-0 text-dark">Daftar Antrean Meja</h4>
                <small class="text-muted">Pantau pesanan yang dikirim dari meja maupun chatbot</small>
            </div>

            <ul class="nav nav-pills nav-pills-mogan bg-white p-1 rounded-pill shadow-sm">
                <li class="nav-item">
                    <a class="nav-link <?= ($tabAktif === 'menunggu') ? 'active' : '' ?>" href="pesanan.php?tab=menunggu">
                        Aktif (<?= $totalAntrean ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($tabAktif === 'riwayat') ? 'active' : '' ?>" href="pesanan.php?tab=riwayat">
                        Riwayat
                    </a>
                </li>
            </ul>
        </div>

        <!-- Tampilan Jika Daftar Kosong -->
        <?php if (!$queryPesanan || $queryPesanan->num_rows === 0): ?>
            <div class="card card-pesanan text-center p-5">
                <div class="d-inline-flex p-3 rounded-circle bg-light mx-auto mb-3">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Tidak Ada Pesanan</h5>
                <p class="text-muted small mb-0">
                    <?= ($tabAktif === 'menunggu') ? 'Belum ada antrean baru yang masuk dari meja.' : 'Belum ada riwayat pesanan yang selesai atau dibatalkan.' ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Grid Kartu Pesanan Masuk -->
            <div class="row g-3">
                <?php while ($pesanan = $queryPesanan->fetch_assoc()):
                    $idOrder = (int)$pesanan['id_keranjang'];
                    $queryDetail = $conn->query("SELECT * FROM detail_keranjang WHERE id_keranjang = $idOrder ORDER BY id_detail_keranjang ASC");
                    $statusOrder = $pesanan['status'];
                    $waktuRaw = $pesanan['tanggal_dibuat'] ?? ($pesanan['created_at'] ?? null);
                    $waktuTampil = $waktuRaw ? date('H:i', strtotime($waktuRaw)) . ' WITA' : '-';

                    $subtotal = (float)$pesanan['total_harga'];
                    $pajak = $subtotal * 0.10;
                    $service = $subtotal * 0.02;
                    $total_keseluruhan = $subtotal + $pajak + $service;
                    $totalBulat = (int)round($total_keseluruhan);

                    $labelMetodeRiwayat = '';
                    if (!empty($pesanan['metode_pembayaran'])) {
                        $mapLabel = [
                            'cash'  => 'Cash',
                            'qris'  => 'QRIS',
                            'kartu' => 'Debit/Kredit',
                        ];
                        $keyMet = strtolower($pesanan['metode_pembayaran']);
                        $labelMetodeRiwayat = $mapLabel[$keyMet] ?? strtoupper($pesanan['metode_pembayaran']);
                    }
                ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card card-pesanan h-100 d-flex flex-column">
                            <!-- Header Kartu -->
                            <div class="card-header bg-white border-bottom pt-3 pb-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="badge-meja">
                                    <i class="bi bi-geo-alt-fill me-1"></i> Meja <?= htmlspecialchars($pesanan['nomor_meja'] ?: '-') ?>
                                </span>
                                <div class="text-end">
                                    <span class="badge <?= ($statusOrder === 'menunggu') ? 'bg-warning text-dark' : (($statusOrder === 'selesai') ? 'bg-success' : 'bg-secondary') ?> rounded-pill small">
                                        <?= ucfirst($statusOrder) ?>
                                    </span>
                                    <?php if ($statusOrder === 'selesai' && $labelMetodeRiwayat !== ''): ?>
                                        <span class="badge bg-info-subtle text-dark border rounded-pill small ms-1">
                                            <?= htmlspecialchars($labelMetodeRiwayat) ?>
                                        </span>
                                    <?php endif; ?>
                                    <div class="text-muted small mt-1" style="font-size: 0.75rem;">
                                        <i class="bi bi-clock me-1"></i><?= $waktuTampil ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Rincian Item yang Dipesan -->
                            <div class="card-body p-3 flex-grow-1">
                                <div class="list-group list-group-flush mb-3">
                                    <?php while ($item = $queryDetail->fetch_assoc()):
                                        $subtotalItem = (float)$item['harga_satuan'] * (int)$item['jumlah'];
                                    ?>
                                        <div class="list-group-item px-0 py-2 border-bottom">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($item['nama_pesanan']) ?></span>
                                                    <span class="text-muted small ms-1">x<?= (int)$item['jumlah'] ?></span>
                                                </div>
                                                <span class="fw-bold text-dark small">
                                                    Rp <?= number_format($subtotalItem, 0, ',', '.') ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($item['catatan'])): ?>
                                                <div class="catatan-tag mt-1">
                                                    <i class="bi bi-chat-left-dots me-1"></i><?= htmlspecialchars($item['catatan']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>

                                <!-- Total Pembayaran -->
                                <div class="pt-2">
                                    <div class="d-flex justify-content-between text-muted small mb-1">
                                        <span>Subtotal:</span>
                                        <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted small mb-1">
                                        <span>Pajak (10%):</span>
                                        <span>Rp <?= number_format($pajak, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between text-muted small mb-1">
                                        <span>Service (2%):</span>
                                        <span>Rp <?= number_format($service, 0, ',', '.') ?></span>
                                    </div>

                                    <hr class="my-2">

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted small fw-bold">TOTAL TAGIHAN:</span>
                                        <h5 class="fw-bold mb-0" style="color: var(--mogan-teal);">
                                            Rp <?= number_format($total_keseluruhan, 0, ',', '.') ?>
                                        </h5>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Aksi Kasir -->
                            <?php if ($statusOrder === 'menunggu'): ?>
                                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                                    <button type="button"
                                            class="btn btn-success btn-sm flex-grow-1 rounded-pill py-2 fw-semibold"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalLunas<?= $idOrder ?>">
                                        <i class="bi bi-cash-coin me-1"></i> Selesai & Lunas
                                    </button>

                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm rounded-pill px-3 py-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalBatal<?= $idOrder ?>"
                                            title="Batalkan Pesanan">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>

                                <!-- Modal Pembayaran Kasir -->
                                <div class="modal fade" id="modalLunas<?= $idOrder ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                                            <div class="modal-header border-0 bg-success-subtle py-3">
                                                <h6 class="modal-title fw-bold text-success mb-0">
                                                    <i class="bi bi-calculator me-2"></i>Kasir Pembayaran - Meja <?= htmlspecialchars($pesanan['nomor_meja'] ?: '-') ?>
                                                </h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>

                                            <form action="pesanan.php" method="GET">
                                                <input type="hidden" name="aksi" value="selesai">
                                                <input type="hidden" name="id" value="<?= $idOrder ?>">
                                                <input type="hidden" name="metode_pembayaran" id="inputMetode_<?= $idOrder ?>" value="cash">

                                                <div class="modal-body p-4">
                                                    <div class="bg-light p-3 rounded-3 mb-3 border">
                                                        <div class="text-center mb-2">
                                                            <span class="text-muted small fw-bold">RINCIAN PEMBAYARAN</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between text-muted small mb-1">
                                                            <span>Subtotal:</span>
                                                            <span>Rp <?= number_format($subtotal, 0, ',', '.') ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between text-muted small mb-1">
                                                            <span>Pajak (10%):</span>
                                                            <span>Rp <?= number_format($pajak, 0, ',', '.') ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between text-muted small mb-1">
                                                            <span>Service (2%):</span>
                                                            <span>Rp <?= number_format($service, 0, ',', '.') ?></span>
                                                        </div>
                                                        <hr class="my-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span class="fw-bold text-dark">TOTAL TAGIHAN:</span>
                                                            <h4 class="fw-bold text-success mb-0">
                                                                Rp <?= number_format($total_keseluruhan, 0, ',', '.') ?>
                                                            </h4>
                                                        </div>
                                                    </div>

                                                    <!-- Pilihan Metode Pembayaran -->
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold text-dark">Metode Pembayaran</label>
                                                        <div class="btn-group w-100" role="group" aria-label="Metode Pembayaran">
                                                            <input type="radio"
                                                                   class="btn-check"
                                                                   name="pilihMetode_<?= $idOrder ?>"
                                                                   id="metodeCash_<?= $idOrder ?>"
                                                                   value="cash"
                                                                   checked
                                                                   onchange="pilihMetodePembayaran(<?= $idOrder ?>, 'cash', <?= $totalBulat ?>)">
                                                            <label class="btn btn-outline-success btn-sm py-2 fw-semibold" for="metodeCash_<?= $idOrder ?>">
                                                                <i class="bi bi-cash-stack me-1"></i> Cash
                                                            </label>

                                                            <input type="radio"
                                                                   class="btn-check"
                                                                   name="pilihMetode_<?= $idOrder ?>"
                                                                   id="metodeQris_<?= $idOrder ?>"
                                                                   value="qris"
                                                                   onchange="pilihMetodePembayaran(<?= $idOrder ?>, 'qris', <?= $totalBulat ?>)">
                                                            <label class="btn btn-outline-success btn-sm py-2 fw-semibold" for="metodeQris_<?= $idOrder ?>">
                                                                <i class="bi bi-qr-code-scan me-1"></i> QRIS
                                                            </label>

                                                            <input type="radio"
                                                                   class="btn-check"
                                                                   name="pilihMetode_<?= $idOrder ?>"
                                                                   id="metodeKartu_<?= $idOrder ?>"
                                                                   value="kartu"
                                                                   onchange="pilihMetodePembayaran(<?= $idOrder ?>, 'kartu', <?= $totalBulat ?>)">
                                                            <label class="btn btn-outline-success btn-sm py-2 fw-semibold" for="metodeKartu_<?= $idOrder ?>">
                                                                <i class="bi bi-credit-card me-1"></i> Debit/Kredit
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- Khusus Pembayaran Cash -->
                                                    <div id="boxCash_<?= $idOrder ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold text-dark">Uang Diterima (Rp)</label>
                                                            <input type="number"
                                                                   id="inputBayar_<?= $idOrder ?>"
                                                                   class="form-control form-control-lg rounded-3 fw-bold text-dark"
                                                                   placeholder="Contoh: 50000"
                                                                   min="<?= $totalBulat ?>"
                                                                   oninput="hitungKembalian(<?= $idOrder ?>, <?= $totalBulat ?>)"
                                                                   required>
                                                        </div>

                                                        <div class="d-flex justify-content-between align-items-center p-3 rounded-3 bg-light border">
                                                            <span class="small fw-semibold text-secondary">Kembalian:</span>
                                                            <span class="fw-bold fs-5 text-danger" id="teksKembalian_<?= $idOrder ?>">Rp 0</span>
                                                        </div>
                                                    </div>

                                                    <!-- Info Pembayaran Non-Tunai (QRIS / Debit / Kredit) -->
                                                    <div id="boxNonCash_<?= $idOrder ?>" class="alert alert-success-subtle border border-success-subtle rounded-3 small mb-0 d-none">
                                                        <div class="fw-semibold text-success mb-1">
                                                            <i class="bi bi-check-circle me-1"></i> Pembayaran Non-Tunai (<span id="labelNonCash_<?= $idOrder ?>">QRIS</span>)
                                                        </div>
                                                        <div class="text-muted">
                                                            Pastikan transaksi sebesar <strong>Rp <?= number_format($total_keseluruhan, 0, ',', '.') ?></strong> sudah berhasil diproses di mesin EDC / aplikasi QRIS sebelum klik <strong>Konfirmasi Lunas</strong>.
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="modal-footer border-0 bg-light p-3 d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit"
                                                            id="btnSubmit_<?= $idOrder ?>"
                                                            class="btn btn-success btn-sm rounded-pill px-4 fw-semibold"
                                                            style="background-color: #0b6056;"
                                                            disabled>
                                                        Konfirmasi Lunas
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Konfirmasi Batal Pesanan -->
                                <div class="modal fade" id="modalBatal<?= $idOrder ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                                            <div class="modal-header border-0 bg-danger-subtle py-3">
                                                <h6 class="modal-title fw-bold text-danger mb-0">
                                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Batalkan Pesanan
                                                </h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4 text-center">
                                                <div class="mb-3 text-danger">
                                                    <i class="bi bi-x-circle-fill fs-1"></i>
                                                </div>
                                                <h5 class="fw-bold text-dark mb-1">Batalkan Pesanan Ini?</h5>
                                                <p class="text-muted small mb-0">
                                                    Apakah Anda yakin ingin membatalkan antrean pesanan untuk <strong>Meja <?= htmlspecialchars($pesanan['nomor_meja'] ?: '-') ?></strong>?
                                                </p>
                                            </div>
                                            <div class="modal-footer border-0 bg-light p-3 d-flex justify-content-end gap-2">
                                                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Kembali</button>
                                                <a href="pesanan.php?aksi=batal&id=<?= $idOrder ?>" class="btn btn-danger btn-sm rounded-pill px-4 fw-semibold">
                                                    Ya, Batalkan
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function hitungKembalian(id, total) {
        const inputUang = document.getElementById('inputBayar_' + id);
        const teksKembali = document.getElementById('teksKembalian_' + id);
        const btnSubmit = document.getElementById('btnSubmit_' + id);

        if (!inputUang || !teksKembali || !btnSubmit) return;

        const bayar = parseFloat(inputUang.value) || 0;
        const kembali = bayar - total;

        if (inputUang.value !== '' && bayar >= total) {
            teksKembali.className = "fw-bold fs-5 text-success";
            teksKembali.innerText = "Rp " + Math.round(kembali).toLocaleString('id-ID');
            btnSubmit.disabled = false;
        } else {
            teksKembali.className = "fw-bold fs-5 text-danger";
            teksKembali.innerText = inputUang.value === '' ? "Rp 0" : "Uang Kurang!";
            btnSubmit.disabled = true;
        }
    }

    function pilihMetodePembayaran(id, metode, total) {
        const inputMetode = document.getElementById('inputMetode_' + id);
        const boxCash = document.getElementById('boxCash_' + id);
        const boxNonCash = document.getElementById('boxNonCash_' + id);
        const labelNonCash = document.getElementById('labelNonCash_' + id);
        const inputUang = document.getElementById('inputBayar_' + id);
        const btnSubmit = document.getElementById('btnSubmit_' + id);

        if (inputMetode) {
            inputMetode.value = metode;
        }

        if (metode === 'cash') {
            if (boxCash) boxCash.classList.remove('d-none');
            if (boxNonCash) boxNonCash.classList.add('d-none');
            if (inputUang) {
                inputUang.required = true;
                hitungKembalian(id, total);
            }
        } else {
            if (boxCash) boxCash.classList.add('d-none');
            if (boxNonCash) boxNonCash.classList.remove('d-none');
            if (labelNonCash) {
                labelNonCash.innerText = metode === 'qris' ? 'QRIS' : 'Kartu Debit/Kredit';
            }
            if (inputUang) {
                inputUang.required = false;
            }
            if (btnSubmit) {
                btnSubmit.disabled = false;
            }
        }
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>