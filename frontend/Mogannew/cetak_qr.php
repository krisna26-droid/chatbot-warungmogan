<?php
$base_url = "https://warungmogan.store/?meja=";
$jumlah_meja = 60;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak QR Code 60 Meja - Warung Mogan</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            /* Warna biru sesuai contoh (ganti ke #0a6363 jika ingin warna hijau Warung Mogan) */
            --frame-color: #3c82b5;
            --frame-shadow: #8ab4d4;
            --title-color: #162447;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            text-align: center;
            background: #ffffff;
            margin: 24px;
            color: var(--title-color);
        }

        .no-print {
            margin-bottom: 30px;
        }

        .no-print h2 {
            margin: 0 0 8px 0;
            font-size: 24px;
        }

        .no-print p {
            margin: 0 0 16px 0;
            color: #4a5568;
            font-size: 14px;
        }

        .btn-print {
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            background: var(--frame-color);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-print:hover {
            opacity: 0.9;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 28px 20px;
            max-width: 1050px;
            margin: auto;
        }

        .qr-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            page-break-inside: avoid;
            break-inside: avoid;
            padding: 10px 6px;
        }

        .meja-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--title-color);
            margin: 0 0 2px 0;
        }

        .meja-subtitle {
            font-size: 11px;
            color: #4a5568;
            margin: 0 0 10px 0;
        }

        /* Bingkai atas QR */
        .qr-frame {
            background: var(--frame-color);
            padding: 8px;
            border-radius: 12px 12px 0 0;
            width: 170px;
        }

        .qr-inner {
            background: #ffffff;
            padding: 10px;
            border-radius: 7px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Garis sambungan tipis di bawah bingkai */
        .qr-connector {
            width: 170px;
            height: 6px;
            background: var(--frame-shadow);
        }

        /* Banner "Scan Me" di bawah */
        .scan-banner {
            background: var(--frame-color);
            color: #ffffff;
            width: 196px;
            padding: 9px 12px;
            border-radius: 9px;
            font-size: 20px;
            font-weight: 500;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }

        @media print {
            body {
                margin: 10px;
            }
            .no-print {
                display: none;
            }
            .grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 24px 16px;
            }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <h2>QR Code 60 Meja — Warung Mogan</h2>
        <p>Scan salah satu QR Code dari HP untuk menguji, atau klik tombol di bawah untuk mencetak / simpan PDF.</p>
        <button class="btn-print" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
    </div>

    <div class="grid">
        <?php for ($i = 1; $i <= $jumlah_meja; $i++): ?>
            <div class="qr-card">
                <div class="meja-title">MEJA <?= $i ?></div>
                <div class="meja-subtitle">Warung Mogan</div>
                <div class="qr-frame">
                    <div class="qr-inner" id="qr-<?= $i ?>"></div>
                </div>
                <div class="qr-connector"></div>
                <div class="scan-banner">Scan Me</div>
            </div>
        <?php endfor; ?>
    </div>

    <script>
        const baseUrl = <?= json_encode($base_url) ?>;
        const totalMeja = <?= (int)$jumlah_meja ?>;

        for (let i = 1; i <= totalMeja; i++) {
            new QRCode(document.getElementById("qr-" + i), {
                text: baseUrl + i,
                width: 134,
                height: 134,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        }
    </script>

</body>
</html>