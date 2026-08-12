<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

mtrace("==================================================");
mtrace("🚀 MERAPIKAN TAMPILAN COURSE & MEMASUKKAN MATERI LANGSUNG KE SECTION");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);

if (!$course) {
    mtrace("❌ Course SIF105 tidak ditemukan!");
    exit(1);
}

// 1. Set Course Display agar Menampilkan Seluruh Materi di Halaman Utama (TIDAK TERSEMBUNYI)
$course->coursedisplay = 0; // Show all sections on one page
$course->numsections = 8;
$DB->update_record('course', $course);

// 2. Hapus Section Kosong Sisa ('New section') yang melebihi Section 8
$DB->delete_records_select('course_sections', 'course = ? AND section > 8', [$course->id]);

// 3. Masukkan Konten Pembelajaran Lengkap Langsung ke dalam Summary Section (Agar Langsung Tampil Tanpa Diklik Lagi)
$deep_summaries = [
    0 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="background: #eef5ff; border-left: 5px solid #0056b3; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
            <h2 style="color: #0056b3; margin-top: 0;">📖 Silabus & Rencana Pembelajaran Semester (RPS)</h2>
            <p><strong>Mata Kuliah:</strong> SIF105 - Computational Thinking (3 SKS)</p>
            <p><strong>Dosen Pengampu:</strong> Dwi Utari Iswavigra, S.T., M.Kom.</p>
            <p><strong>Deskripsi Singkat:</strong> Membekali mahasiswa dengan fondasi pemikiran komputasional untuk memecahkan masalah kompleks secara matematis, sistematis, dan logis melalui 4 pilar utama: Dekomposisi, Pengenalan Pola, Abstraksi, dan Desain Algoritma.</p>
        </div>
    </div>',

    1 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #eaf2f8; border-left: 5px solid #2980B9; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #2980B9; margin: 0 0 5px 0;">MODULE 01: Pengantar Computational Thinking & Dekomposisi Masalah</h3>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Memahami definisi Berpikir Komputasional dan menerapkan teknik Dekomposisi untuk memecah masalah sistemik yang kompleks.</p>
        </div>

        <h4 style="color: #2c3e50;">1. Apakah Computational Thinking Itu?</h4>
        <p>Istilah <em>Computational Thinking</em> (CT) dipopulerkan oleh <strong>Jeannette Wing (2006)</strong>. CT adalah metode pemecahan masalah yang merumuskan masalah dan solusinya secara terstruktur sehingga dapat dieksekusi secara efektif oleh manusia maupun komputer.</p>

        <div style="background: #FFF9E6; border: 1px solid #FFE0B2; padding: 12px; border-radius: 6px; margin: 15px 0;">
            <h5 style="color: #F39C12; margin-top: 0;">💡 4 Pilar Utama CT:</h5>
            <ul>
                <li><strong>Dekomposisi:</strong> Memecah masalah besar menjadi sub-masalah kecil yang mandiri.</li>
                <li><strong>Pengenalan Pola:</strong> Mencari kesamaan atau pola pengulangan dari data.</li>
                <li><strong>Abstraksi:</strong> Membuang informasi tidak relevan dan fokus pada esensi masalah.</li>
                <li><strong>Algoritma:</strong> Menyusun langkah-langkah sistematis yang terurut.</li>
            </ul>
        </div>

        <h4 style="color: #2c3e50;">2. Studi Kasus Dekomposisi: Sistem Toko Online (E-Commerce)</h4>
        <p>Ketika membangun sistem E-Commerce, kita memecahnya menjadi modul-modul independen:</p>
        <ol>
            <li><strong>Modul Katalog Produk:</strong> Menyimpan nama, harga, dan stok barang.</li>
            <li><strong>Modul Keranjang Belanja:</strong> Menampung barang yang dipilih pembeli.</li>
            <li><strong>Modul Payment Gateway:</strong> Memproses pembayaran bank/e-wallet.</li>
            <li><strong>Modul Pengiriman (Logistik):</strong> Menghitung ongkos kirim.</li>
        </ol>
    </div>',

    2 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #e8f8f5; border-left: 5px solid #1abc9c; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #16a085; margin: 0 0 5px 0;">MODULE 02: Pengenalan Pola (Pattern Recognition) & Data Analysis</h3>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Menemukan keteraturan, kecenderungan, dan pola berulang dari kumpulan data.</p>
        </div>
        <p>Ketika kita menemukan pola yang pernah terjadi sebelumnya, kita tidak perlu membuat solusi dari nol; kita cukup menggunakan solusi lama yang terbukti berhasil!</p>
        <ul>
            <li><strong>Sistem Rekomendasi (Netflix / Spotify):</strong> Menganalisis pola riwayat tontonan/lagu pengguna.</li>
            <li><strong>Deteksi Penipuan Kartu Kredit:</strong> Bank mengenali pola transaksi harian dan mendeteksi anomali.</li>
        </ul>
    </div>',

    3 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #fef9e7; border-left: 5px solid #f1c40f; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #f39c12; margin: 0 0 5px 0;">MODULE 03: Abstraksi & Pemodelan Masalah (Abstraction)</h3>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Menyaring detail yang tidak perlu dan fokus pada karakteristik utama.</p>
        </div>
        <p><strong>Contoh Kasus Peta KRL/MRT:</strong> Detail gedung dan jalan ditiadakan, yang dipertahankan hanya nama dan urutan stasiun agar mudah dibaca penumpang.</p>
    </div>',

    4 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #ebdef0; border-left: 5px solid #8e44ad; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #8e44ad; margin: 0 0 5px 0;">MODULE 04: Desain Algoritma & Standar Flowchart ANSI/ISO</h3>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Merancang urutan instruksi logis dan menyajikannya secara visual dengan Flowchart.</p>
        </div>
        <pre style="background: #272c34; color: #abb2bf; padding: 12px; border-radius: 6px;">
START
    INPUT nilai_mahasiswa
    IF nilai_mahasiswa >= 75 THEN
        OUTPUT "LULUS"
    ELSE
        OUTPUT "TIDAK LULUS"
    ENDIF
END
        </pre>
    </div>',

    5 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #eafaf1; border-left: 5px solid #27ae60; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #27ae60; margin: 0 0 5px 0;">MODULE 05: Logika Boolean & Struktur Data Dasar</h3>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Operator Logika (AND, OR, NOT) dan Struktur Antrean Queue (FIFO).</p>
        </div>
    </div>',

    6 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #fdfefe; border-left: 5px solid #34495e; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #2c3e50; margin: 0 0 5px 0;">MODULE 06: Studi Kasus - Sistem Parkir Otomatis</h3>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Merancang algoritma fisik-digital berbasis sensor jarak dan OCR kamera plat nomor.</p>
        </div>
    </div>',

    7 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #fef5e7; border-left: 5px solid #e67e22; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #d35400; margin: 0 0 5px 0;">MODULE 07: Evaluasi & Optimasi Algoritma (Big-O Notation)</h3>
            <p style="margin: 0;"><strong>Fokus Pembelajaran:</strong> Mengukur kompleksitas waktu O(1), O(N), O(log N) dan perbandingan Linear vs Binary Search.</p>
        </div>
    </div>',

    8 => '
    <div style="font-family: Arial, sans-serif; line-height: 1.7; color: #2C3E50; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e8ed; margin-bottom: 15px;">
        <div style="background: #fadbd8; border-left: 5px solid #cb4335; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
            <h3 style="color: #b03a2e; margin: 0 0 5px 0;">MODULE 08: Ujian Tengah Semester (UTS)</h3>
            <p style="margin: 0;"><strong>Evaluasi:</strong> Ujian Online 90 Menit mencakup materi Pertemuan 1 s/d 7.</p>
        </div>
    </div>'
];

foreach ($deep_summaries as $sec_num => $summary_html) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sec_num]);
    if ($section) {
        $section->summary = $summary_html;
        $section->summaryformat = FORMAT_HTML;
        $DB->update_record('course_sections', $section);
        mtrace("     ✅ [SECTION {$sec_num}] Summary materi berhasil disisipkan!");
    }
}

rebuild_course_cache($course->id, true);

mtrace("==================================================");
mtrace("🎉 TAMPILAN COURSE SIF105 BERHASIL DIRAPIKAN!");
mtrace("==================================================");
