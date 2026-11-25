<?php
/**
 * Shared application configuration for database and Rasa.
 * Update these values when deploying the application to a hosted environment.
 */
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_warung_mogan';

if (!defined('RASA_API_URL')) {
    define('RASA_API_URL', 'http://127.0.0.1:5005/webhooks/rest/webhook');
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    $errorMessage = 'Koneksi database gagal: ' . $conn->connect_error;
    if (!empty($config_database_error_json)) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['status' => 'error', 'message' => $errorMessage]));
    }
    die($errorMessage);
}
$conn->set_charset('utf8mb4');
