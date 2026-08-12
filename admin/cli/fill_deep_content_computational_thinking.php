<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

mtrace("==================================================");
mtrace("🚀 MEMASUKKAN MATERI PEMBELAJARAN LENGKAP & MENDALAM KE SIF105");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);

if (!$course) {
    mtrace("❌ Course SIF105 tidak ditemukan!");
    exit(1);
}

// 1. Update Silabus (Section 0)
$page_silabus = $DB->get_record('page', ['course' => $course->id, 'name' => '📖 Silabus & Rencana Pembelajaran Semester (RPS)']);

if ($page_silabus) {
    $page_silabus->content = '
    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="background: #eef5ff; border-left: 5px solid #0056b3; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
            <h2 style="color: #0056b3; margin-top: 0;">📖 Rencana Pembelajaran Semester (RPS)</h2>
            <p><strong>Mata Kuliah:</strong> SIF105 - Computational Thinking (3 SKS)</p>
            <p><strong>Dosen Pengampu:</strong> Dwi Utari Iswavigra, S.T., M.Kom.</p>
            <p><strong>Deskripsi Singkat:</strong> Mata kuliah ini membekali mahasiswa dengan fondasi pemikiran komputasional untuk memecahkan masalah kompleks secara matematis, sistematis, dan logis melalui 4 pilar utama: Dekomposisi, Pengenalan Pola, Abstraksi, dan Desain Algoritma.</p>
        </div>

        <h3 style="color: #222; border-bottom: 2px solid #eee; padding-bottom: 8px;">🎯 Capaian Pembelajaran Mata Kuliah (CPMK)</h3>
        <ol>
            <li>Mahasiswa mampu menguraikan (dekomposisi) masalah sistemik menjadi komponen-komponen kecil.</li>
            <li>Mahasiswa mampu mendeteksi pola (pattern recognition) pada himpunan data atau fenomena numerik.</li>
            <li>Mahasiswa mampu melakukan abstraksi untuk membuang elemen relevansi rendah dalam pemodelan data.</li>
            <li>Mahasiswa mampu merancang algoritma dan flowchart terstruktur yang efisien secara logika.</li>
        </ol>

        <h3 style="color: #222; border-bottom: 2px solid #eee; padding-bottom: 8px;">📅 Agenda 8 Pertemuan Utama</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <thead>
                <tr style="background: #0056b3; color: white;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Pekan</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Topik Pembelajaran</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Bentuk Aktivitas</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">1</td><td style="padding: 10px; border: 1px solid #ddd;">Pengantar Computational Thinking & Dekomposisi Masalah</td><td style="padding: 10px; border: 1px solid #ddd;">Membaca Modul & Studi Kasus E-Commerce</td></tr>
                <tr style="background: #f9f9f9;"><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">2</td><td style="padding: 10px; border: 1px solid #ddd;">Pengenalan Pola (Pattern Recognition) & Analisis Data</td><td style="padding: 10px; border: 1px solid #ddd;">Materi & Latihan Deteksi Pola Trend</td></tr>
                <tr><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">3</td><td style="padding: 10px; border: 1px solid #ddd;">Abstraksi & Pemodelan Masalah (Abstraction)</td><td style="padding: 10px; border: 1px solid #ddd;">Materi & Pemodelan Peta Transportasi</td></tr>
                <tr style="background: #f9f9f9;"><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">4</td><td style="padding: 10px; border: 1px solid #ddd;">Desain Algoritma & Standar Flowchart ANSI/ISO</td><td style="padding: 10px; border: 1px solid #ddd;">Modul Flowchart & Latihan Penentuan Grade</td></tr>
                <tr><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">5</td><td style="padding: 10px; border: 1px solid #ddd;">Logika Boolean & Struktur Data Dasar (Array/Queue)</td><td style="padding: 10px; border: 1px solid #ddd;">Modul Logika & Antrean Rumah Sakit</td></tr>
                <tr style="background: #f9f9f9;"><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">6</td><td style="padding: 10px; border: 1px solid #ddd;">Studi Kasus Kompleks & Penugasan Mandiri</td><td style="padding: 10px; border: 1px solid #ddd;">Pengumpulan Tugas Flowchart Sistem Kampus</td></tr>
                <tr><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">7</td><td style="padding: 10px; border: 1px solid #ddd;">Evaluasi & Optimasi Algoritma (Big-O Notation)</td><td style="padding: 10px; border: 1px solid #ddd;">Materi Kompleksitas & Comparison Code</td></tr>
                <tr style="background: #f9f9f9;"><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">8</td><td style="padding: 10px; border: 1px solid #ddd;">Ujian Tengah Semester (UTS)</td><td style="padding: 10px; border: 1px solid #ddd;">Evaluasi Ujian Online UTS</td></tr>
            </tbody>
        </table>
    </div>';
    $DB->update_record('page', $page_silabus);
    mtrace("✅ Silabus diperbarui dengan konten lengkap!");
}

// 2. Definisi Konten Pembelajaran Mendalam per Pertemuan
$deep_contents = [
    1 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #eaf2f8; border-left: 5px solid #2980B9; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #2980B9; margin: 0 0 10px 0;">MODULE 01: Pengantar Computational Thinking & Dekomposisi Masalah</h2>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Memahami definisi Berpikir Komputasional dan menerapkan teknik Dekomposisi untuk memecah masalah sistemik yang kompleks.</p>
        </div>

        <h3>1. Pendahuluan: Apakah Computational Thinking Itu?</h3>
        <p>Istilah <em>Computational Thinking</em> (CT) dipopulerkan oleh <strong>Jeannette Wing (2006)</strong>. CT bukanlah proses berpikir seperti komputer atau pemrograman belaka, melainkan sebuah <strong>metode pemecahan masalah manusia</strong> yang merumuskan masalah dan solusinya secara terstruktur sehingga solusi tersebut dapat dengan mudah dieksekusi oleh manusia maupun sistem komputer.</p>

        <div style="background: #FFF9E6; border: 1px solid #FFE0B2; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <h4 style="color: #F39C12; margin-top: 0;">💡 4 Pilar Utama Computational Thinking:</h4>
            <ul>
                <li><strong>Dekomposisi:</strong> Memecah masalah besar menjadi sub-masalah kecil yang mandiri.</li>
                <li><strong>Pengenalan Pola (Pattern Recognition):</strong> Mencari kesamaan atau pola pengulangan dari data.</li>
                <li><strong>Abstraksi:</strong> Membuang informasi yang tidak relevan dan fokus pada esensi masalah.</li>
                <li><strong>Algoritma:</strong> Menyusun langkah-langkah sistematis yang terurut untuk menyelesaikan masalah.</li>
            </ul>
        </div>

        <h3>2. Pendalaman Pilar 1: Dekomposisi Masalah (Decomposition)</h3>
        <p>Tanpa dekomposisi, sebuah masalah sistemik akan terlihat sangat menakutkan dan sulit diselesaikan. Melalui dekomposisi, kita membedah komponen-komponennya.</p>

        <div style="background: #F4F6F7; padding: 15px; border-radius: 6px; border: 1px solid #E5E7E9;">
            <h4>🏬 Studi Kasus: Dekomposisi Sistem E-Commerce (Toko Online)</h4>
            <p>Bayangkan Anda diminta membangun sistem Toko Online dari nol. Jika Anda memikirkannya sekaligus, itu sangat rumit. Dengan <strong>Dekomposisi</strong>, kita membaginya menjadi modul-modul independen:</p>
            <ol>
                <li><strong>Modul Katalog Produk:</strong> Menyimpan nama, harga, gambar, dan stok barang.</li>
                <li><strong>Modul Keranjang Belanja:</strong> Menampung barang yang dipilih pembeli sebelum checkout.</li>
                <li><strong>Modul Payment Gateway:</strong> Memproses pembayaran via Transfer Bank, E-Wallet, atau Kartu Kredit.</li>
                <li><strong>Modul Pengiriman (Logistik):</strong> Menghitung ongkos kirim berdasarkan berat dan lokasi tujuan.</li>
            </ol>
        </div>

        <h3 style="margin-top: 25px;">📝 Pertanyaan Refleksi & Diskusi</h3>
        <p>Coba pikirkan sistem di kampus Anda (misal: <em>Sistem Pendaftaran Wisuda</em>). Komponen-komponen kecil apa saja yang terbentuk jika Anda melakukan dekomposisi pada sistem tersebut?</p>
    </div>',

    2 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #e8f8f5; border-left: 5px solid #1abc9c; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #16a085; margin: 0 0 10px 0;">MODULE 02: Pengenalan Pola (Pattern Recognition) & Data Analysis</h2>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Menemukan keteraturan, kecenderungan, dan pola berulang dari kumpulan data untuk memprediksi solusi.</p>
        </div>

        <h3>1. Mengapa Pengenalan Pola Penting?</h3>
        <p>Setelah masalah dipecah-pecah melalui dekomposisi, langkah berikutnya adalah mencari <strong>pola kesamaan</strong>. Ketika kita menemukan pola yang pernah terjadi sebelumnya, kita tidak perlu membuat solusi dari nol; kita cukup menggunakan solusi lama yang terbukti berhasil!</p>

        <h3>2. Penerapan Pola pada Industri Modern</h3>
        <ul>
            <li><strong>Sistem Rekomendasi (Netflix / Spotify):</strong> Menganalisis pola riwayat tontonan/lagu Anda. Jika Pola Pengguna A mirip dengan Pola Pengguna B, sistem akan merekomendasikan film favorit B kepada A.</li>
            <li><strong>Deteksi Penipuan Kartu Kredit (Fraud Detection):</strong> Bank mengenali pola transaksi harian Anda. Jika tiba-tiba ada transaksi di luar negeri pada jam 3 pagi, sistem mengenali ini sebagai anomali pola dan memblokir sementara kartu tersebut.</li>
        </ul>

        <div style="background: #F4F6F7; padding: 15px; border-radius: 6px; border: 1px solid #E5E7E9;">
            <h4>📊 Contoh Latihan Analisis Pola Numerik</h4>
            <p>Perhatikan deret angka berikut: <code>2, 4, 8, 16, 32, ...</code></p>
            <p><strong>Pola yang ditemukan:</strong> Setiap suku berikutnya merupakan hasil perkalian 2 dari suku sebelumnya (f(n) = 2^n). Dengan mengetahui pola ini, kita bisa langsung memprediksi suku ke-10 tanpa harus menghitung satu per satu.</p>
        </div>
    </div>',

    3 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #fef9e7; border-left: 5px solid #f1c40f; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #f39c12; margin: 0 0 10px 0;">MODULE 03: Abstraksi & Pemodelan Masalah (Abstraction & Modeling)</h2>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Menyaring detail yang tidak perlu (filtering noise) dan memfokuskan perhatian hanya pada karakteristik utama yang relevan.</p>
        </div>

        <h3>1. Konsep Dasar Abstraksi</h3>
        <p>Abstraksi adalah seni memilih <strong>apa yang penting</strong> dan <strong>mengabaikan apa yang tidak penting</strong>. Dunia nyata sangat rumit dan penuh detail. Jika kita memasukkan semua detail ke dalam model komputer, program akan menjadi sangat lambat dan kompleks.</p>

        <div style="background: #EAEDED; padding: 15px; border-radius: 6px; margin: 20px 0;">
            <h4>🚌 Contoh Kasus Klasik: Peta Transportasi Publik (MRT / KRL)</h4>
            <p>Peta rute MRT/KRL yang Anda lihat di stasiun adalah bentuk <strong>Abstraksi Sempurna</strong>:</p>
            <ul>
                <li><strong>Detail yang DIBUANG (diabaikan):</strong> Pemandangan jalan raya, kelok-kelok jalan asli, pohon, dan gedung di sekitar lintasan rel.</li>
                <li><strong>Detail yang DIPERTAHANKAN (penting):</strong> Nama stasiun, urutan stasiun, dan warna jalur/line.</li>
            </ul>
            <p><em>Jika peta MRT menampilkan gambar gedung dan pohon secara riil, penumpang justru akan bingung membaca rute keretanya!</em></p>
        </div>

        <h3>2. Abstraksi dalam Pemrograman (Pemodelan Objek Mahasiswa)</h3>
        <p>Misalkan kita ingin membuat Sistem Informasi Akademik (SIAKAD) Moodle:</p>
        <ul>
            <li><strong>Detail Relevan (Disimpan):</strong> NIM, Nama, Email, Semester, IPK, Mata Kuliah.</li>
            <li><strong>Detail Tidak Relevan (Dibuang):</strong> Ukuran sepatu mahasiswa, warna favorit, nama kucing peliharaan.</li>
        </ul>
    </div>',

    4 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #ebdef0; border-left: 5px solid #8e44ad; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #8e44ad; margin: 0 0 10px 0;">MODULE 04: Desain Algoritma & Standar Flowchart ANSI/ISO</h2>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Merancang urutan instruksi logis yang tidak ambigu (deterministic) dan menyajikannya secara visual menggunakan diagram alir (Flowchart).</p>
        </div>

        <h3>1. Pengertian Algoritma</h3>
        <p>Algoritma adalah kumpulan instruksi terstruktur dan terbatas (finite) yang disusun langkah demi langkah untuk menyelesaikan suatu tugas tertentu.</p>

        <h3>2. Simbol Standardisasi Flowchart (ANSI / ISO)</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr style="background: #8e44ad; color: white;">
                    <th style="padding: 10px; border: 1px solid #ddd;">Bentuk Simbol</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Nama Simbol</th>
                    <th style="padding: 10px; border: 1px solid #ddd;">Fungsi Utama</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">Oval / Capsul</td><td style="padding: 10px; border: 1px solid #ddd;">Terminal (Start / End)</td><td style="padding: 10px; border: 1px solid #ddd;">Menandai awal atau akhir alur algoritma.</td></tr>
                <tr style="background: #f9f9f9;"><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">Jajaran Genjang</td><td style="padding: 10px; border: 1px solid #ddd;">Input / Output</td><td style="padding: 10px; border: 1px solid #ddd;">Proses menerima input atau menampilkan hasil output.</td></tr>
                <tr><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">Persegi Panjang</td><td style="padding: 10px; border: 1px solid #ddd;">Process</td><td style="padding: 10px; border: 1px solid #ddd;">Eksekusi perhitungan matematika atau pemrosesan data.</td></tr>
                <tr style="background: #f9f9f9;"><td style="padding: 10px; border: 1px solid #ddd; text-align: center;">Belah Ketupat (Diamond)</td><td style="padding: 10px; border: 1px solid #ddd;">Decision (Keputusan)</td><td style="padding: 10px; border: 1px solid #ddd;">Pengujian kondisi logika (TRUE / FALSE).</td></tr>
            </tbody>
        </table>

        <div style="background: #F4F6F7; padding: 15px; border-radius: 6px; margin-top: 20px;">
            <h4>💻 Contoh Pseudocode: Penentuan Kelulusan Nilai</h4>
            <pre style="background: #272c34; color: #abb2bf; padding: 15px; border-radius: 6px;">
START
    INPUT nilai_mahasiswa
    IF nilai_mahasiswa >= 75 THEN
        OUTPUT "LULUS"
    ELSE
        OUTPUT "TIDAK LULUS"
    ENDIF
END
            </pre>
        </div>
    </div>',

    5 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #eafaf1; border-left: 5px solid #27ae60; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #27ae60; margin: 0 0 10px 0;">MODULE 05: Logika Boolean & Struktur Data Dasar (Array & Queue)</h2>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Memahami pengujian variabel kondisi logika serta pengorganisasian data menggunakan struktur Array dan Queue (Antrean).</p>
        </div>

        <h3>1. Operator Logika Boolean (AND, OR, NOT)</h3>
        <ul>
            <li><strong>AND (&&):</strong> Bernilai TRUE hanya jika semua kondisi bernilai TRUE.  
                <em>Contoh: (IPK >= 3.0) AND (Bebas SPP == TRUE).</em></li>
            <li><strong>OR (||):</strong> Bernilai TRUE jika salah satu kondisi bernilai TRUE.  
                <em>Contoh: (Punya Kartu Beasiswa) OR (Lulus Seleksi KIP).</em></li>
            <li><strong>NOT (!):</strong> Membalikkan nilai logika.  
                <em>Contoh: !(Sudah Lulus) -> Berarti Belum Lulus.</em></li>
        </ul>

        <h3>2. Struktur Data Antrean (Queue - FIFO)</h3>
        <p>Struktur data <strong>Queue (Antrean)</strong> menganut prinsip <strong>First-In, First-Out (FIFO)</strong>: Data yang pertama kali masuk adalah data yang pertama kali dilayani dan keluar.</p>

        <div style="background: #F4F6F7; padding: 15px; border-radius: 6px;">
            <h4>🏥 Aplikasi Nyata Queue pada Antrean Rumah Sakit</h4>
            <ol>
                <li><code>Enqueue("Pasien A")</code> -> Pasien A masuk antrean no. 1.</li>
                <li><code>Enqueue("Pasien B")</code> -> Pasien B masuk antrean no. 2.</li>
                <li><code>Dequeue()</code> -> Dokter memanggil & melayani <strong>Pasien A</strong> terlebih dahulu.</li>
            </ol>
        </div>
    </div>',

    6 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #fdfefe; border-left: 5px solid #34495e; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #2c3e50; margin: 0 0 10px 0;">MODULE 06: Studi Kasus Kompleks - Sistem Parkir Otomatis</h2>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Menggabungkan 4 pilar Computational Thinking untuk merancang solusi lengkap sistem fisik-digital.</p>
        </div>

        <h3>🚗 Kasus: Merancang Sistem Tiket & Palang Parkir Otomatis</h3>
        <p>Anda diminta merancang sistem otomatis untuk palang pintu masuk Mall yang menggunakan kamera pendeteksi plat nomor kendaraan dan sensor jarak.</p>

        <div style="background: #f4f6f7; padding: 15px; border-radius: 6px;">
            <h4>Langkah Solusi Komputasional:</h4>
            <ol>
                <li><strong>Dekomposisi:</strong> Bagi menjadi sub-sistem (Sensor Jarak, Kamera OCR Plat, Printer Tiket, Motor Servo Palang Pintu).</li>
                <li><strong>Pengenalan Pola:</strong> Kamera mengenali pola karakter huruf & angka pada plat nomor Indonesia.</li>
                <li><strong>Abstraksi:</strong> Abaikan warna mobil, merk mobil, atau tingkat kebersihan mobil; ambil hanya string Plat Nomor dan Jam Masuk.</li>
                <li><strong>Algoritma:</strong>
                    <pre style="background: #272c34; color: #abb2bf; padding: 12px; border-radius: 6px;">
WHEN Mobil_Mendekat (Sensor_Jarak < 1 Meter)
    Foto_Plat = Kamera.Capture()
    Plat_Nomor = OCR_Extract(Foto_Plat)
    Waktu_Masuk = Get_Current_Time()
    Print_Tiket(Plat_Nomor, Waktu_Masuk)
    Buka_Palang_Pintu()
    WAIT UNTIL Sensor_Lewat == TRUE
    Tutup_Palang_Pintu()
                    </pre>
                </li>
            </ol>
        </div>
    </div>',

    7 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #fef5e7; border-left: 5px solid #e67e22; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #d35400; margin: 0 0 10px 0;">MODULE 07: Evaluasi & Optimasi Algoritma (Big-O Notation)</h2>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Mengukur efisiensi algoritma dalam skala data besar dan membandingkan algoritma Pencarian (Search Algorithms).</p>
        </div>

        <h3>1. Kompleksitas Algoritma (Notasi Big-O)</h3>
        <p>Tidak semua algoritma yang menghasilkan jawaban benar itu efisien. Notasi <strong>Big-O</strong> digunakan untuk mengukur bagaimana performa algoritma melambat saat ukuran data (N) bertambah.</p>

        <ul>
            <li><strong>O(1) - Constant Time:</strong> Kecepatan konstan tanpa peduli seberapa besar data. <em>(Sangat Cepat)</em></li>
            <li><strong>O(log N) - Logarithmic Time:</strong> Jumlah langkah bertambah sangat sedikit saat data berlipat ganda (contoh: Binary Search).</li>
            <li><strong>O(N) - Linear Time:</strong> Jumlah langkah berbanding lurus dengan jumlah data (contoh: Linear Search).</li>
            <li><strong>O(N^2) - Quadratic Time:</strong> Performa melambat drastis karena nested loop berulang. <em>(Sangat Lambat)</em></li>
        </ul>

        <h3>2. Perbandingan: Linear Search vs Binary Search</h3>
        <p>Jika mencari 1 nama di antara <strong>1.000.000 data mahasiswa</strong>:</p>
        <ul>
            <li><strong>Linear Search (O(N)):</strong> Mencari dari orang ke-1, ke-2, ke-3... butuh maksimal <strong>1.000.000 kali pencocokan</strong>.</li>
            <li><strong>Binary Search (O(log N)):</strong> Membelah data terurut menjadi 2 bagian terus-menerus... butuh maksimal <strong>hanya 20 kali pencocokan</strong>!</li>
        </ul>
    </div>',

    8 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50;">
        <div style="background: #fadbd8; border-left: 5px solid #cb4335; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h2 style="color: #b03a2e; margin: 0 0 10px 0;">MODULE 08: Ujian Tengah Semester (UTS) - Computational Thinking</h2>
            <p style="margin: 0;"><strong>Fokus Evaluasi:</strong> Menguji pemahaman komprehensif mahasiswa terhadap 4 pilar CT, pembuatan flowchart, dan analisis algoritma.</p>
        </div>

        <div style="background: #fff; border: 1px solid #ecc19c; padding: 18px; border-radius: 6px; margin-bottom: 20px;">
            <h3 style="color: #c0392b; margin-top: 0;">📋 Informasi & Aturan Ujian UTS</h3>
            <ul>
                <li><strong>Sifat Ujian:</strong> Online via Moodle LMS.</li>
                <li><strong>Durasi Waktu:</strong> 90 Menit.</li>
                <li><strong>Cakupan Materi:</strong> Pertemuan 1 sampai Pertemuan 7.</li>
                <li><strong>Bentuk Soal:</strong> 20 Pilihan Ganda (Konsep) & 2 Soal Essay Studi Kasus Flowchart.</li>
            </ul>
        </div>

        <h3 style="color: #27ae60;">💡 Petunjuk Persiapan UTS</h3>
        <ol>
            <li>Pelajari ulang contoh-contoh Dekomposisi Sistem pada Modul 1 dan Modul 6.</li>
            <li>Pastikan Anda hafal dan paham simbol-simbol standar Flowchart (ANSI/ISO) di Modul 4.</li>
            <li>Pahami perbedaan performa algoritma Linear Search vs Binary Search di Modul 7.</li>
        </ol>
    </div>'
];

// Update isi dari halaman-halaman pertemuan 1 - 8
foreach ($deep_contents as $sec_num => $html_content) {
    $pages = $DB->get_records_sql("SELECT p.* FROM {page} p JOIN {course_modules} cm ON cm.instance = p.id WHERE p.course = ? AND cm.section = ?", [$course->id, $sec_num]);
    foreach ($pages as $p) {
        $p->content = $html_content;
        $DB->update_record('page', $p);
        mtrace("✅ [CONTENT FILLED] Pertemuan {$sec_num}: '{$p->name}' berhasil diisi materi mendalam!");
    }
}

rebuild_course_cache($course->id, true);

mtrace("==================================================");
mtrace("🎉 SELURUH MATERI PEMBELAJARAN LENGKAP BUKU TEKS TELAH DISISIPKAN KE MOODLE!");
mtrace("==================================================");
