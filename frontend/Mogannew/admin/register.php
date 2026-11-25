<?php
// Logika penanganan pesan error atau sukses pendaftaran
$message = "";
$status = "";

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
        $message = "Registrasi berhasil! Silakan masuk dengan akun baru Anda.";
        $status = "success";
    } elseif ($_GET['status'] == 'exists') {
        $message = "Username atau email sudah terdaftar. Silakan gunakan yang lain.";
        $status = "error";
    } elseif ($_GET['status'] == 'empty') {
        $message = "Semua kolom wajib diisi!";
        $status = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Admin - Warung Mogan Vegetarian</title>
    <!-- Google Fonts & FontAwesome -->
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
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #2e3d30;
            padding: 20px 0;
        }

        .register-container {
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
            margin-bottom: 25px;
        }

        .brand-header i {
            font-size: 35px;
            color: #2e7d32;
            margin-bottom: 8px;
        }

        .brand-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: #1b5e20;
        }

        .brand-header p {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }

        /* Notifikasi Alert */
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }

        .form-group {
            margin-bottom: 18px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 6px;
            color: #333;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 36px;
            color: #888;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 11px 15px 11px 42px;
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

        .btn-register {
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
            margin-top: 5px;
        }

        .btn-register:hover {
            background-color: #1b5e20;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }

        .login-link a {
            color: #2e7d32;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="register-container">
        <!-- Header Logo & Judul -->
        <div class="brand-header">
            <i class="fa-solid fa-user-plus"></i>
            <h2>Pendaftaran Admin</h2>
            <p>Warung Mogan Vegetarian</p>
        </div>

        <!-- Notifikasi Status -->
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo ($status == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <i class="fa-solid <?php echo ($status == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Registrasi -->
        <form action="process_register.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <i class="fa-solid fa-user input-icon"></i>
                <input type="text" id="username" name="username" class="form-control" placeholder="Buat username admin" required>
            </div>

            <div class="form-group">
                <label for="email">Alamat Email</label>
                <i class="fa-solid fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" class="form-control" placeholder="admin@warungmogan.com" required>
            </div>

            <div class="form-group">
                <label for="password">Kata Sandi</label>
                <i class="fa-solid fa-lock input-icon"></i>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required>
            </div>

            <button type="submit" class="btn-register">Daftar Sekarang</button>
        </form>

        <!-- Tautan Kembali ke Login -->
        <div class="login-link">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </div>

</body>
</html>