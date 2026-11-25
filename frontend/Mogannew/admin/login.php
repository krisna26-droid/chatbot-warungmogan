<?php
// Contoh logika penanganan error atau sukses sederhana
$error_message = "";
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'invalid') {
        $error_message = "Username atau Kata Sandi salah! Silakan coba lagi.";
    } elseif ($_GET['error'] == 'empty') {
        $error_message = "Kolom username dan kata sandi wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Warung Mogan Vegetarian</title>
    <!-- Google Fonts & FontAwesome untuk Ikon -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #2e3d30;
        }

        .login-container {
            background: #ffffff;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(46, 61, 48, 0.1);
            border-top: 5px solid #2e7d32;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-header i {
            font-size: 40px;
            color: #2e7d32;
            margin-bottom: 10px;
        }

        .brand-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1b5e20;
        }

        .brand-header p {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        /* Notifikasi Error */
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 38px;
            color: #888;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px 12px 42px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            outline: none;
        }

        .form-control:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            margin-bottom: 25px;
        }

        .form-options a {
            color: #2e7d32;
            text-decoration: none;
            font-weight: 500;
        }

        .form-options a:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background-color: #2e7d32;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .btn-login:hover {
            background-color: #1b5e20;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 13px;
            color: #666;
        }

        .register-link a {
            color: #2e7d32;
            font-weight: 600;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Header Logo & Judul -->
        <div class="brand-header">
            <!-- Ganti path "assets/img/logo-mogan.png" sesuai lokasi folder gambar Anda -->
            <img src="../assets/images/logo_mogan.jpeg" alt="Logo Warung Mogan" height="36" class="rounded" onerror="this.src='https://placehold.co/120x36?text=Mogan'">
        </div>

        <!-- Bagian Penanganan Gagal Login (Error Alert) -->
        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form action="process_login.php" method="POST">
            <div class="form-group">
                <label for="username">Username atau Email</label>
                <i class="fa-solid fa-user input-icon"></i>
                <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username admin" required>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <i class="fa-solid fa-lock input-icon"></i>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required>
            </div>

            <div class="form-options">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="remember"> Ingat saya
                </label>
                <a href="forgot_password.php">Lupa kata sandi?</a>
            </div>

            <button type="submit" class="btn-login">Masuk</button>
        </form>

        <!-- Tautan Registrasi Admin Baru -->
        <div class="register-link">
            Belum punya akun admin? <a href="register.php">Daftar di sini</a>
        </div>
    </div>

</body>
</html>