<?php
// Pastikan tidak ada spasi/enter kosong sebelum tag <?php di atas
error_reporting(0); // Mencegah notice/warning PHP merusak JSON output

define('RASA_API_URL', 'http://127.0.0.1:5005/webhooks/rest/webhook');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_chat') {
    header('Content-Type: application/json; charset=utf-8');
    
    $userMessage = trim($_POST['message'] ?? '');
    $senderId    = trim($_POST['sender_id'] ?? 'user_tamu');

    if (empty($userMessage)) {
        echo json_encode(['status' => 'error', 'reply' => 'Pesan tidak boleh kosong.']);
        exit;
    }

    $payload = json_encode([
        'sender'  => $senderId,
        'message' => $userMessage
    ]);

    // Request ke REST webhook server Rasa
    $ch = curl_init(RASA_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); // Kunci: paksa IPv4 agar tidak bentrok di Windows

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $botReplies = [];

    // Jika server Rasa aktif dan merespons
    if ($httpCode === 200 && $response) {
        $rasaData = json_decode($response, true);
        if (is_array($rasaData) && count($rasaData) > 0) {
            foreach ($rasaData as $msg) {
                if (isset($msg['text']) && !empty($msg['text'])) {
                    $botReplies[] = $msg['text'];
                }
            }
        }
    }

    // Jika Rasa tidak membalas teks (misal intent belum ada teksnya atau server offline)
    if (empty($botReplies)) {
        $msgLower = strtolower($userMessage);
        if (str_contains($msgLower, 'halo') || str_contains($msgLower, 'hai') || str_contains($msgLower, 'pagi') || str_contains($msgLower, 'siang') || str_contains($msgLower, 'sore')) {
            $botReplies[] = "Om Swastyastu! Selamat datang di Warung Mogan Balinese Plant-based Restaurant. Ada yang bisa kami bantu?";
        } elseif (str_contains($msgLower, 'menu') || str_contains($msgLower, 'daftar')) {
            $botReplies[] = "Menu kami meliputi Nasi Campur Mogan Vegetarian, Nasi Sela, Tipat Cantok, Genyol Vegan bumbu Bali, Nasi Bakar, serta aneka fresh juice alami!";
        } elseif (str_contains($msgLower, 'rekomendasi') || str_contains($msgLower, 'favorit') || str_contains($msgLower, 'best seller')) {
            $botReplies[] = "Menu andalan kami adalah Nasi Campur Vegetarian spesial dan Genyol Vegan dengan cita rasa bumbu otentik khas Bali.";
        } elseif (str_contains($msgLower, 'pedas') || str_contains($msgLower, 'pedes')) {
            $botReplies[] = "Untuk hidangan pedas, Anda bisa mencoba Nasi Bakar Pedas Gurih atau olahan Genyol Vegan sambal matah khas Mogan!";
        } elseif (str_contains($msgLower, 'minum') || str_contains($msgLower, 'seger')) {
            $botReplies[] = "Pilihan minuman segar kami ada Es Kelapa Muda Jeruk Nipis, Es Teler Spesial Vegan, dan Jus Alpukat murni.";
        } elseif (str_contains($msgLower, 'jam') || str_contains($msgLower, 'buka') || str_contains($msgLower, 'tutup')) {
            $botReplies[] = "Warung Mogan buka setiap hari mulai pukul 09.00 - 21.00 WITA.";
        } elseif (str_contains($msgLower, 'lokasi') || str_contains($msgLower, 'alamat') || str_contains($msgLower, 'dimana')) {
            $botReplies[] = "Kami berlokasi di Mudutaki, Dalung, Kuta Utara, Badung, Bali.";
        } elseif (str_contains($msgLower, 'bayar') || str_contains($msgLower, 'qris') || str_contains($msgLower, 'cash')) {
            $botReplies[] = "Kami melayani pembayaran melalui Scan QRIS, transfer bank, maupun uang tunai (cash) di kasir.";
        } else {
            $botReplies[] = "Mogan AI siap membantu! Anda dapat bertanya mengenai menu nabati, rekomendasi hidangan favorit, harga, jam buka, atau lokasi warung.";
        }
    }

    echo json_encode(['status' => 'success', 'replies' => $botReplies]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warung Mogan - Balinese Plant-based Restaurant</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Palet diselaraskan dengan logo Mogan (Teal, Green, & Ochre Gold)
                        mogan: {
                            teal: '#0a6363',      // Warna utama tulisan Mogan
                            tealDark: '#074848',  // Warna hover/aksen pekat
                            tealLight: '#e6f3f3', // Background lembut
                            leaf: '#1ca053',      // Warna daun hijau muda
                            leafLight: '#eaf8f0',
                            gold: '#b47b2b',      // Warna tulisan Balinese Plant-based Restaurant
                            goldLight: '#fbf4ea'
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-[#fafaf7] text-slate-800 font-sans antialiased">

<!-- NAVBAR -->
<header class="bg-white/95 backdrop-blur-md sticky top-0 z-50 border-b border-[#0a6363]/15 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="#" class="flex items-center">
                <img src="logo_mogan.jpeg" 
                     alt="Logo Warung Mogan" 
                     class="h-10 md:h-12 w-auto object-contain">
            </a>
            <nav class="hidden md:flex items-center space-x-7 text-sm font-semibold text-slate-600">
                <a href="#cerita" class="hover:text-mogan-teal transition">Tentang Kami</a>
                <a href="#galeri" class="hover:text-mogan-teal transition">Galeri Suasana</a>
                <a href="#chatbot" class="hover:text-mogan-teal transition">Tanya AI Mogan</a>
                <a href="#kontak" class="hover:text-mogan-teal transition">Lokasi & Kontak</a>
            </nav>
            <a href="#chatbot" class="bg-mogan-teal hover:bg-mogan-tealDark text-white text-xs md:text-sm font-semibold px-4 py-2 rounded-full transition shadow-sm shadow-mogan-teal/30 flex items-center gap-2">
                <i class="fa-solid fa-leaf text-mogan-leaf"></i>
                <span>Tanya Menu AI</span>
            </a>
        </div>
    </header>

    <!-- SECTION 1: CERITA SINGKAT & HERO -->
    <section id="cerita" class="py-12 md:py-16 bg-gradient-to-b from-mogan-tealLight/60 via-white to-[#fafaf7]">
        <div class="max-w-6xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-mogan-leafLight text-mogan-leaf text-xs font-bold rounded-full mb-3 border border-mogan-leaf/20">
                        <i class="fa-solid fa-seedling"></i> 100% Balinese Plant-based
                    </span>
                    <h2 class="text-3xl md:text-4xl font-extrabold text-mogan-tealDark leading-tight mb-4">
                        Cita Rasa Khas Bali dalam Hidangan Nabati.
                    </h2>
                    <p class="text-slate-600 text-sm md:text-base leading-relaxed mb-4 text-justify">
                        Berlokasi di kawasan asri <strong>Mudutaki, Badung</strong>, <strong>Warung Mogan</strong> menyajikan pengalaman kuliner tradisional Bali berbahan dasar nabati (<em>plant-based</em>). Kami memadukan racikan bumbu rempah otentik Bali dengan bahan segar pilihan untuk menghasilkan hidangan yang kaya rasa, serta ramah dikantong.
                    </p>
                    <p class="text-slate-600 text-sm md:text-base leading-relaxed mb-6 text-justify">
                    Seiring tingginya antusiasme pelanggan dan pemesanan dalam jumlah besar, Warung Mogan kini menghadirkan inovasi digital berupa <strong>sistem pemesanan mandiri terintegrasi asisten chatbot AI</strong>. Melalui teknologi ini, Anda dapat menanyakan rekomendasi menu, memeriksa sajian pedas khas Bali, serta memesan secara mandiri dengan cepat, praktis, dan akurat tanpa perlu menunggu antrean panjang pelayan.                    </p>
                    <div class="flex flex-wrap items-center gap-4 text-xs md:text-sm text-slate-700 font-semibold">
                        <div class="flex items-center space-x-2 bg-white px-3 py-2 rounded-lg border border-slate-200 shadow-sm">
                            <i class="fa-regular fa-clock text-mogan-gold text-base"></i>
                            <span>Buka 07:00 - 22:00 WITA</span>
                        </div>
                        <div class="flex items-center space-x-2 bg-white px-3 py-2 rounded-lg border border-slate-200 shadow-sm">
                            <i class="fa-solid fa-map-pin text-mogan-teal text-base"></i>
                            <span>Mudutaki, Dalung - Bali</span>
                        </div>
                    </div>
                </div>

                <!-- FOTO UTAMA WARUNG -->
                <div class="relative">
                    <div class="absolute -inset-2 bg-gradient-to-r from-mogan-teal/20 via-mogan-leaf/20 to-mogan-gold/20 rounded-3xl blur-lg"></div>
                    <div class="relative bg-white p-3 rounded-2xl shadow-xl border border-mogan-teal/20">
                        <img src="view mogan.PNG" 
                             alt="Suasana Warung Mogan Gazebo Sawah" 
                             class="w-full h-72 md:h-80 object-cover rounded-xl shadow-inner">
                        <div class="mt-3 flex justify-between items-center px-2">
                            <p class="text-xs font-medium text-slate-500">Suasana Joglo Asri di Tepi Sawah</p>
                        </div>
                    </div>
                </div>
    </section>

    <!-- SECTION 2: CHATBOT PELANGGAN DI TENGAH -->
    <section id="chatbot" class="py-14 bg-gradient-to-b from-[#fafaf7] to-mogan-tealLight/40">
        <div class="max-w-4xl mx-auto px-4">
            <!-- Header Chatbot -->
            <div class="text-center mb-8">
                <span class="bg-mogan-teal text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                    Layanan Mandiri Pelanggan
                </span>
                <h3 class="text-2xl md:text-3xl font-extrabold text-mogan-tealDark mt-2">Asisten Chatbot Warung Mogan</h3>
                <p class="text-sm text-slate-600 mt-1">Dapatkan informasi menu rekomendasi, harga menu, jam buka, atau rute lokasi secara instan.</p>
            </div>

            <!-- CHATBOX CONTAINER -->
            <div class="bg-white rounded-3xl shadow-xl border border-mogan-teal/20 overflow-hidden flex flex-col h-[560px]">
                
                <!-- 1. Header Chatbot -->
                <div class="bg-gradient-to-r from-mogan-teal to-mogan-tealDark text-white p-4 flex items-center justify-between shadow-md">
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-full bg-white p-1 flex items-center justify-center shadow-sm overflow-hidden">
                                <img src="logo_mogan.jpeg" alt="Logo Mogan" class="w-full h-full object-contain">
                            </div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 border-2 border-white rounded-full"></span>
                        </div>
                        <div>
                            <h4 class="font-bold text-base leading-tight">Mogan Virtual Assistant</h4>
                        </div>
                    </div>
                    <span class="text-xs bg-white/15 border border-white/20 px-3 py-1 rounded-full text-white font-medium">Aktif</span>
                </div>

                <!-- 2. Pesan Chat (Scroll Area) -->
                <div id="chatMessages" class="flex-1 p-4 md:p-6 overflow-y-auto space-y-4 bg-slate-50/70 chat-scroll">
                    <!-- Pesan Pembuka Default -->
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 rounded-full bg-white p-0.5 border border-mogan-teal/20 flex items-center justify-center flex-shrink-0 mt-1 shadow-sm overflow-hidden">
                            <img src="logo_mogan.jpeg" alt="Logo Mogan" class="w-full h-full object-contain">
                        </div>
                        <div class="bg-white text-slate-800 p-4 rounded-2xl rounded-tl-none border border-slate-200 shadow-sm max-w-[80%] text-sm leading-relaxed">
                            Om Swastyastu! Selamat datang di <strong>Warung Mogan</strong> 🌿<br>
                            Silakan tanyakan menu rekomendasi nabati, info kepedasan, harga, atau metode pembayaran apa yang bisa digunakan.
                        </div>
                    </div>
                </div> <!-- HANYA SATU </div> UNTUK MENUTUP #chatMessages DI SINI -->

                <!-- 3. Tombol Rekomendasi Cepat -->
                <div class="px-4 py-2.5 bg-white border-t border-slate-100 flex items-center space-x-2 overflow-x-auto whitespace-nowrap text-xs">
                    <span class="text-slate-400 font-medium">Pilihan Cepat:</span>
                    <button onclick="sendQuickPrompt('Menu favorit apa?')" class="bg-mogan-tealLight hover:bg-mogan-teal/15 text-mogan-teal px-3 py-1.5 rounded-full border border-mogan-teal/20 font-medium transition">
                        ⭐ Rekomendasi Menu
                    </button>
                    <button onclick="sendQuickPrompt('Menu pedas apa aja?')" class="bg-mogan-goldLight hover:bg-amber-100 text-mogan-gold px-3 py-1.5 rounded-full border border-mogan-gold/30 font-medium transition">
                        🌶️ Menu Pedas
                    </button>
                    <button onclick="sendQuickPrompt('Mau yang seger')" class="bg-mogan-leafLight hover:bg-emerald-100 text-mogan-leaf px-3 py-1.5 rounded-full border border-mogan-leaf/30 font-medium transition">
                        🍹 Minuman Segar
                    </button>
                    <button onclick="sendQuickPrompt('Buka jam berapa?')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-full transition">
                        🕒 Jam Buka
                    </button>
                    <button onclick="sendQuickPrompt('Warung Mogan Terletak dimana?')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded-full transition">
                        📍 Lokasi Warung
                    </button>
                </div>

                <!-- 4. Form Input Chat -->
                <form id="chatForm" onsubmit="handleChatSubmit(event)" class="p-3 md:p-4 bg-white border-t border-slate-200 flex items-center space-x-2">
                    <input type="text" id="userInput" placeholder="Ketik pertanyaan Anda di sini..." 
                           class="flex-1 bg-slate-50 border border-slate-300 focus:border-mogan-teal focus:bg-white text-slate-800 text-sm px-4 py-3 rounded-full outline-none transition" 
                           autocomplete="off">
                    <button type="submit" id="sendBtn" 
                            class="bg-mogan-teal hover:bg-mogan-tealDark text-white w-11 h-11 rounded-full flex items-center justify-center transition shadow-md shadow-mogan-teal/30">
                        <i class="fa-solid fa-paper-plane text-sm"></i>
                    </button>
                </form>

            </div> <!-- PENUTUP KOTAK UTAMA CHATBOT DI SINI -->
    </section>

    <!-- SECTION 3: GALERI SUASANA & PILIHAN MENU -->
    <section id="galeri" class="py-12 bg-white">
        <div class="max-w-6xl mx-auto px-4">
            <div class="text-center mb-10">
                <h3 class="text-2xl font-bold text-mogan-tealDark">Suasana Bersantap & Pilihan Hidangan</h3>
                <p class="text-slate-500 text-sm mt-1">Menu plant-based dengan rempah Bali pilihan dan tempat yang nyaman</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <!-- Card 1 -->
                <div class="bg-[#fafaf7] rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition">
                    <img src="GENYOL VEGAN.jpg" alt="Nasi Genyol Vegan" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <span class="text-[10px] font-bold text-mogan-gold uppercase tracking-wider">Favorit Pelanggan</span>
                        <h4 class="font-bold text-mogan-teal text-base mt-0.5">Nasi Genyol Vegan</h4>
                        <p class="text-xs text-slate-500 mt-1">Paduan sate dari sari kedelai, lawar, genyol dari gluten dan sambal Bali yang otentik.</p>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="bg-[#fafaf7] rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition">
                    <img src="GAZEBO.jpg" alt="Meja Warung Mogan" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <span class="text-[10px] font-bold text-mogan-teal uppercase tracking-wider">Area Meja</span>
                        <h4 class="font-bold text-mogan-teal text-base mt-0.5">Fasilitas Pemesanan Meja</h4>
                        <p class="text-xs text-slate-500 mt-1">Dilengkapi QR Code di tiap meja untuk pemesanan mandiri cepat dan akurat.</p>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="bg-[#fafaf7] rounded-2xl overflow-hidden border border-slate-200 shadow-sm hover:shadow-md transition">
                    <img src="KELAPA JERUK.jpg" alt="Minuman Segar" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <span class="text-[10px] font-bold text-mogan-leaf uppercase tracking-wider">Segar & Sehat</span>
                        <h4 class="font-bold text-mogan-teal text-base mt-0.5">Es Kelapa Jeruk</h4>
                        <p class="text-xs text-slate-500 mt-1">Minuman penyegar dahaga dari buah asli tanpa perisa sintetis.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="kontak" class="bg-mogan-tealDark text-white py-10 border-t border-mogan-teal">
        <div class="max-w-6xl mx-auto px-4 grid grid-cols-1 md:grid-cols-3 gap-8 text-sm">
            <div>
                <h5 class="text-lg font-bold text-white mb-2">Mogan</h5>
                <p class="text-xs font-semibold text-mogan-gold mb-3 uppercase tracking-wider">Balinese Plant-based Restaurant</p>
                <p class="text-slate-300 leading-relaxed text-xs">
                    Sistem pemesanan mandiri berbasis web terintegrasi Chatbot AI untuk kenyamanan pengalaman bersantap Anda.
                </p>
            </div>
            <div>
                <h5 class="font-bold text-white mb-3">Lokasi & Jam Kerja</h5>
                <p class="text-slate-300 text-xs mb-1.5 flex items-center">
                    <i class="fa-solid fa-location-dot mr-2 text-mogan-gold"></i> Mudutaki, Dalung, Kuta Utara, Badung - Bali
                </p>
                <p class="text-slate-300 text-xs mb-1.5 flex items-center">
                    <i class="fa-solid fa-clock mr-2 text-mogan-gold"></i> Buka Setiap Hari: 07.00 - 22.00 WITA
                </p>
            </div>
            <div>
                <h5 class="font-bold text-white mb-3">Kemudahan Pembayaran</h5>
                <p class="text-slate-300 text-xs leading-relaxed">
                    Pembayaran dapat dilakukan dengan tunai, QRIS, kartu debit, kartu kredit, serta transfer.
                </p>
            </div>
        </div>
        <div class="max-w-6xl mx-auto px-4 mt-8 pt-4 border-t border-white/10 text-center text-xs text-slate-400">
            &copy; <?= date('Y') ?> Warung Mogan Balinese Plant-based Restaurant.
        </div>
    </footer>

    <!-- JAVASCRIPT LOGIC CHATBOT -->
    <script>
        const chatMessages = document.getElementById('chatMessages');
        const userInput    = document.getElementById('userInput');
        const sendBtn      = document.getElementById('sendBtn');
        const senderId     = 'pelanggan_' + Math.random().toString(36).substring(2, 9);

        function appendMessage(text, isUser = false) {
            const wrapper = document.createElement('div');
            wrapper.className = isUser ? 'flex items-start justify-end space-x-2' : 'flex items-start space-x-3';

            if (isUser) {
                wrapper.innerHTML = `
                    <div class="bg-mogan-teal text-white p-3.5 rounded-2xl rounded-tr-none shadow-sm max-w-[80%] text-sm leading-relaxed">
                        ${escapeHtml(text)}
                    </div>
                    <div class="w-8 h-8 rounded-full bg-slate-300 text-slate-700 flex items-center justify-center text-xs font-bold flex-shrink-0 mt-1">
                        Anda
                    </div>
                `;
            } else {
                wrapper.innerHTML = `
                    <div class="w-8 h-8 rounded-full bg-mogan-teal text-white flex items-center justify-center text-xs font-bold flex-shrink-0 mt-1 shadow-sm">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div class="bg-white text-slate-800 p-3.5 rounded-2xl rounded-tl-none border border-slate-200 shadow-sm max-w-[80%] text-sm leading-relaxed">
                        ${text.replace(/\n/g, '<br>')}
                    </div>
                `;
            }

            chatMessages.appendChild(wrapper);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function showTypingIndicator() {
            const typing = document.createElement('div');
            typing.id = 'typingIndicator';
            typing.className = 'flex items-start space-x-3';
            typing.innerHTML = `
                <div class="w-8 h-8 rounded-full bg-mogan-teal text-white flex items-center justify-center text-xs font-bold flex-shrink-0 mt-1">
                    <i class="fa-solid fa-robot"></i>
                </div>
                <div class="bg-white text-slate-400 px-4 py-3 rounded-2xl rounded-tl-none border border-slate-200 shadow-sm text-xs italic">
                    <i class="fa-solid fa-circle-notch fa-spin mr-1 text-mogan-leaf"></i> Mogan Bot sedang mengetik...
                </div>
            `;
            chatMessages.appendChild(typing);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function removeTypingIndicator() {
            const typing = document.getElementById('typingIndicator');
            if (typing) typing.remove();
        }

        function escapeHtml(string) {
            return String(string).replace(/[&<>"'`=\/]/g, function (s) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                    '/': '&#x2F;'
                }[s];
            });
        }

        async function sendMessage(text) {
            if (!text || text.trim() === '') return;

            appendMessage(text, true);
            userInput.value = '';
            showTypingIndicator();

            try {
                const formData = new FormData();
                formData.append('action', 'send_chat');
                formData.append('message', text);
                formData.append('sender_id', senderId);

                const res = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                removeTypingIndicator();

                if (data.status === 'success' && data.replies && data.replies.length > 0) {
                    data.replies.forEach(replyText => {
                        appendMessage(replyText, false);
                    });
                } else {
                    appendMessage("Maaf, tidak ada tanggapan saat ini. Silakan coba kembali nanti.", false);
                }
            } catch (err) {
                removeTypingIndicator();
                appendMessage("Terjadi kendala koneksi dengan chatbot. Silakan coba sesaat lagi.", false);
            }
        }

        function handleChatSubmit(e) {
            e.preventDefault();
            const message = userInput.value;
            sendMessage(message);
        }

        function sendQuickPrompt(promptText) {
            sendMessage(promptText);
        }
    </script>
</body>
</html>