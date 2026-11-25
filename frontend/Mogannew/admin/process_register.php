<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_warung_mogan";

// Buat koneksi ke database
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validasi jika kolom kosong
    if (empty($username) || empty($email) || empty($password)) {
        header("Location: register.php?status=empty");
        exit();
    }

    // Cek apakah username atau email sudah pernah terdaftar
    $check = $conn->prepare("SELECT * FROM admin WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Jika sudah ada, kembalikan dengan status exists
        header("Location: register.php?status=exists");
        exit();
    }

    // Enkripsi password agar aman
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Simpan akun baru ke tabel admin
    $stmt = $conn->prepare("INSERT INTO admin (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hashed_password);

    if ($stmt->execute()) {
        // Jika berhasil, arahkan kembali ke halaman login dengan status sukses
        header("Location: login.php?status=success");
        exit();
    } else {
        header("Location: register.php?status=error");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}
?>