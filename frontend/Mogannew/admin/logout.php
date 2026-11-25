<?php
session_start(); // Mulai session

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan session-nya
session_destroy();

// Arahkan kembali ke halaman login
header("Location: login.php");
exit();
?>