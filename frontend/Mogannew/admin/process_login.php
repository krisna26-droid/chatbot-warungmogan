<?php
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_input = trim($_POST['username']);
    $password_input = trim($_POST['password']);

    if (empty($username_input) || empty($password_input)) {
        header("Location: login.php?error=empty");
        exit();
    }

    // Cari user berdasarkan username atau email
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username_input, $username_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Cocokkan password yang di-input dengan password terenkripsi di database
        if (password_verify($password_input, $row['password'])) {
            // Jika BERHASIL
            $_SESSION['status_login'] = true;
            $_SESSION['username'] = $row['username'];

            header("Location: index.php");
            exit();
        }
    }

    // Jika username tidak ditemukan atau password salah
    header("Location: login.php?error=invalid");
    exit();
}
?>
