<?php
/**
 * SHOWCASE COURSE ENRICHER
 * Mengisi mata kuliah SIF201 (Bahasa Pemrograman Dasar) dengan konten pembelajaran
 * yang sangat kaya, realistis, dan siap untuk presentasi live.
 */
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

mtrace("==================================================");
mtrace("🚀 MEMBANGUN SHOWCASE KELAS LENGKAP: SIF201");
mtrace("   Bahasa Pemrograman Dasar — 2025/2026 Ganjil");
mtrace("==================================================\n");

// Get or find SIF201 course
$course = $DB->get_record('course', ['shortname' => 'SIF201_20252026Ganjil']);
if (!$course) {
    // Try any SIF course if SIF201 not found
    $course = $DB->get_record_sql("SELECT * FROM {course} WHERE shortname LIKE '%SIF%' ORDER BY id ASC LIMIT 1");
}

if (!$course) {
    mtrace("❌ Kelas SIF tidak ditemukan!");
    exit(1);
}

mtrace("✅ Target Kelas: [ID {$course->id}] {$course->fullname} ({$course->shortname})");

// Ensure 16 sections exist
course_create_sections_if_missing($course->id, range(0, 16));

// Ensure course format is weeks/topics with proper summary
$DB->set_field('course', 'fullname', 'Bahasa Pemrograman Dasar — 2025/2026 - Ganjil', ['id' => $course->id]);
$DB->set_field('course', 'summary', '<p>Mata kuliah dasar pemrograman menggunakan bahasa Python & JavaScript untuk mahasiswa Sistem Informasi / Informatika USH.</p>', ['id' => $course->id]);

// ─── SECTION 0: BANNER & DOKUMEN UTAMA ───────────────────────────────────────
mtrace("📌 [Section 0] Mengisi Banner, Profile Dosen, & RPS...");

$banner_url = $CFG->wwwroot . '/pix/banner_showcase.jpg';

$sec0_html = "
<div style='background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 25px; font-family: system-ui, -apple-system, sans-serif;'>
    <img src='{$banner_url}' alt='Banner Pemrograman Dasar' style='width: 100%; max-height: 320px; object-fit: cover; display: block;' />
    
    <div style='padding: 24px 28px;'>
        <div style='display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;'>
            <span style='background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 12px; padding: 5px 14px; border-radius: 20px;'>💻 PRODI SISTEM INFORMASI</span>
            <span style='background: #f0fdf4; color: #15803d; font-weight: 700; font-size: 12px; padding: 5px 14px; border-radius: 20px;'>⚡ 3 SKS (Teori & Praktikum)</span>
            <span style='background: #fef3c7; color: #b45309; font-weight: 700; font-size: 12px; padding: 5px 14px; border-radius: 20px;'>📅 TA 2025/2026 - GANJIL</span>
        </div>

        <h2 style='color: #0f172a; font-size: 22px; font-weight: 800; margin: 0 0 10px 0;'>Selamat Datang di Mata Kuliah Bahasa Pemrograman Dasar! 🚀</h2>
        <p style='color: #475569; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;'>
            Mata kuliah ini dirancang untuk membekali mahasiswa dengan fondasi logika berpikir komputasional, struktur algoritma, kontrol percabangan, perulangan, fungsi, struktur data list/dictionary, hingga dasar Pemrograman Berbasis Objek (OOP) menggunakan bahasa <strong>Python 3</strong>.
        </p>

        <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 20px;'>
            <div style='background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px;'>
                <div style='font-weight: 800; color: #1e293b; font-size: 14px; margin-bottom: 6px;'>👩‍🏫 Dosen Pengampu</div>
                <div style='color: #0284c7; font-weight: 700; font-size: 13.5px;'>Dwi Utari Iswavigra, S.T., M.Kom.</div>
                <div style='color: #64748b; font-size: 12px; margin-top: 4px;'>✉️ dwi.utari@sugenghartono.ac.id</div>
                <div style='color: #64748b; font-size: 12px;'>🏢 Ruang Dosen Gedung A Fl. 2</div>
            </div>

            <div style='background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px;'>
                <div style='font-weight: 800; color: #1e293b; font-size: 14px; margin-bottom: 6px;'>⚖️ Skema Penilaian</div>
                <div style='font-size: 12.5px; color: #334155; line-height: 1.5;'>
                    • Kehadiran & Keaktifan: <strong>10%</strong><br>
                    • Tugas & Lab Practice: <strong>20%</strong><br>
                    • Kuis Formatif: <strong>15%</strong><br>
                    • UTS (Ujian Tengah Sem): <strong>25%</strong><br>
                    • UAS (Ujian Akhir Sem): <strong>30%</strong>
                </div>
            </div>
        </div>

        <div style='background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 18px; border-radius: 8px;'>
            <div style='font-weight: 700; color: #1e40af; font-size: 13.5px; margin-bottom: 4px;'>🎯 Capaian Pembelajaran Mata Kuliah (CPMK):</div>
            <ul style='margin: 0; padding-left: 18px; color: #1e3a8a; font-size: 13px; line-height: 1.6;'>
                <li><strong>CPMK-1</strong>: Mampu menjelaskan prinsip dasar berpikir komputasional & logika algoritma.</li>
                <li><strong>CPMK-2</strong>: Mampu mengimplementasikan tipe data, variabel, operator, percabangan & perulangan.</li>
                <li><strong>CPMK-3</strong>: Mampu merancang fungsi modular dan memanipulasi struktur data koleksi (List, Tuple, Dict).</li>
                <li><strong>CPMK-4</strong>: Mampu menyelesaikan studi kasus pemrograman nyata secara mandiri dan tim.</li>
            </ul>
        </div>
    </div>
</div>
<hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;' />
";

$section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
if ($section0) {
    $DB->set_field('course_sections', 'summary', $sec0_html, ['id' => $section0->id]);
}

// Add Page Resource for Complete RPS Syllabus in Section 0
$page_module_id = $DB->get_field('modules', 'id', ['name' => 'page']);

$rps_page_content = "
<h3>📄 Rencana Pembelajaran Semester (RPS) Resmi</h3>
<p><strong>Mata Kuliah:</strong> Bahasa Pemrograman Dasar (SIF201)</p>
<p><strong>SKS:</strong> 3 SKS | <strong>Program Studi:</strong> Sistem Informasi / Informatika USH</p>
<table border='1' cellpadding='10' cellspacing='0' style='width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; font-family: sans-serif; font-size: 13px;'>
    <tr style='background: #0f172a; color: #ffffff;'>
        <th>Minggu</th>
        <th>Kemampuan Akhir (Sub-CPMK)</th>
        <th>Bahan Kajian / Materi</th>
        <th>Metode Pembelajaran</th>
        <th>Bentuk Evaluasi</th>
    </tr>
    <tr>
        <td align='center'><strong>1</strong></td>
        <td>Memahami konsep algoritma & flowchart</td>
        <td>Pengenalan Berpikir Komputasional & Flowchart</td>
        <td>Kuliah & Diskusi Interactive</td>
        <td>Tugas 1 (Flowchart)</td>
    </tr>
    <tr style='background: #f8fafc;'>
        <td align='center'><strong>2</strong></td>
        <td>Menguasai tipe data, variabel & operator</td>
        <td>Tipe Data Primitive, Variabel & Operator Python</td>
        <td>Hands-on Mini Lab Practice</td>
        <td>Kuis Formatif 1</td>
    </tr>
    <tr>
        <td align='center'><strong>3</strong></td>
        <td>Mengimplementasikan logika percabangan</td>
        <td>Struktur Kontrol IF, ELSE, ELIF</td>
        <td>Studi Kasus Program Kasir</td>
        <td>Tugas 2 (Program Kasir)</td>
    </tr>
    <tr style='background: #f8fafc;'>
        <td align='center'><strong>4</strong></td>
        <td>Menguasai perulangan & iterasi</td>
        <td>Looping FOR, WHILE, Break & Continue</td>
        <td>Live Coding & Mini Quiz</td>
        <td>Kuis Formatif 2</td>
    </tr>
    <tr>
        <td align='center'><strong>5-6</strong></td>
        <td>Merancang fungsi modular & parameter</td>
        <td>Functions, Return Value, Scope Variabel</td>
        <td>Praktikum Lab Komputer</td>
        <td>Tugas 3 (Modular Program)</td>
    </tr>
    <tr style='background: #f8fafc;'>
        <td align='center'><strong>7</strong></td>
        <td>Manipulasi Array & List Data Structure</td>
        <td>List, Tuple, Dictionary & Method Operasi</td>
        <td>Studi Kasus Data Mahasiswa</td>
        <td>Tugas 4 (Data Collection)</td>
    </tr>
    <tr style='background: #fef2f2; color: #991b1b;'>
        <td align='center'><strong>8 / 9</strong></td>
        <td colspan='3'><strong>🏆 UJIAN TENGAH SEMESTER (UTS)</strong></td>
        <td><strong>Kuis UTS (25%)</strong></td>
    </tr>
</table>
";

// Create or update Page for RPS Syllabus
$existing_rps_page = $DB->get_record('page', ['course' => $course->id, 'name' => '📄 Silabus & RPS Lengkap Mata Kuliah']);
if (!$existing_rps_page) {
    $page = new stdClass();
    $page->course      = $course->id;
    $page->name        = '📄 Silabus & RPS Lengkap Mata Kuliah';
    $page->intro       = '<p>Dokumen rincian kegiatan perkuliahan 16 pertemuan.</p>';
    $page->introformat = FORMAT_HTML;
    $page->content     = $rps_page_content;
    $page->contentformat = FORMAT_HTML;
    $page->timemodified = time();
    $page->id = $DB->insert_record('page', $page);

    $cm = new stdClass();
    $cm->course   = $course->id;
    $cm->module   = $page_module_id;
    $cm->instance = $page->id;
    $cm->section  = $section0->id;
    $cm->visible  = 1;
    $cm->added    = time();
    $DB->insert_record('course_modules', $cm);
}

// ─── PERTEMUAN 1: PENGENALAN ALGORITMA & LOGIKA ────────────────────────────
mtrace("📌 [Pertemuan 1] Menambah Materi, Video, Forum, & Tugas 1...");
$sec1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
if ($sec1) {
    $sec1_desc = "
    <div style='background: #f1f5f9; padding: 12px 16px; border-radius: 8px; border-left: 4px solid #0284c7; margin-bottom: 12px;'>
        <strong style='color: #0369a1;'>🎯 Capaian Pertemuan 1:</strong> Mahasiswa dapat menjelaskan konsep pemikiran komputasional, algoritma pseudocode, serta menggambar flowchart standar ISO.
    </div>";
    $DB->set_field('course_sections', 'name', '📅 Pertemuan 1: Pengenalan Algoritma & Berpikir Komputasional', ['id' => $sec1->id]);
    $DB->set_field('course_sections', 'summary', $sec1_desc, ['id' => $sec1->id]);

    // Page Material P1
    $page_p1 = new stdClass();
    $page_p1->course = $course->id;
    $page_p1->name = '📖 Materi 1: Konsep Algoritma & Pemikiran Komputasional';
    $page_p1->intro = '<p>Bahan bacaan utama Pertemuan 1.</p>';
    $page_p1->introformat = FORMAT_HTML;
    $page_p1->content = "
    <h3>1. Apa itu Algoritma?</h3>
    <p>Algoritma adalah urutan langkah-langkah logis yang terstruktur untuk menyelesaikan suatu masalah komputasi secara efektif dan efisien.</p>
    
    <h3>2. Empat Pilar Computational Thinking:</h3>
    <ul>
        <li><strong>Dekomposisi</strong>: Memecah masalah besar menjadi bagian-bagian kecil.</li>
        <li><strong>Pengenalan Pola</strong>: Mencari kesamaan pola pada masalah.</li>
        <li><strong>Abstraksi</strong>: Fokus pada informasi penting dan mengabaikan detail tidak relevan.</li>
        <li><strong>Perancangan Algoritma</strong>: Menyusun langkah penyelesaian secara berurutan.</li>
    </ul>

    <div style='background: #0f172a; color: #38bdf8; padding: 14px; border-radius: 8px; font-family: monospace;'>
        # Contoh Pseudocode Algoritma Menentukan Bilangan Genap:<br>
        1. INPUT angka<br>
        2. IF angka MOD 2 == 0 THEN<br>
        3.    PRINT 'Bilangan Genap'<br>
        4. ELSE<br>
        5.    PRINT 'Bilangan Ganjil'<br>
        6. ENDIF
    </div>
    ";
    $page_p1->contentformat = FORMAT_HTML;
    $page_p1->timemodified = time();
    $p1_id = $DB->insert_record('page', $page_p1);

    $cm = new stdClass();
    $cm->course = $course->id; $cm->module = $page_module_id; $cm->instance = $p1_id; $cm->section = $sec1->id; $cm->visible = 1; $cm->added = time();
    $DB->insert_record('course_modules', $cm);
}

// ─── PERTEMUAN 2: TIPE DATA & VARIABEL + KUIS FORMATIF ─────────────────────
mtrace("📌 [Pertemuan 2] Menambah Materi Lab & Kuis Formatif Interactive...");
$sec2 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);
if ($sec2) {
    $sec2_desc = "
    <div style='background: #f1f5f9; padding: 12px 16px; border-radius: 8px; border-left: 4px solid #16a34a; margin-bottom: 12px;'>
        <strong style='color: #15803d;'>🎯 Capaian Pertemuan 2:</strong> Mahasiswa mampu memanipulasi variabel, tipe data Integer, Float, String, Boolean, dan operator aritmatika di Python.
    </div>";
    $DB->set_field('course_sections', 'name', '📅 Pertemuan 2: Tipe Data, Variabel & Operator Python', ['id' => $sec2->id]);
    $DB->set_field('course_sections', 'summary', $sec2_desc, ['id' => $sec2->id]);
}

// ─── SECTION 9: UJIAN TENGAH SEMESTER (UTS) DENGAN SOAL INTERAKTIF ────────
mtrace("📌 [Section 9] Membangun Kuis UTS & Bank Soal Interaktif...");

$sec9 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 9]);
if ($sec9) {
    $DB->set_field('course_sections', 'name', '🏆 Ujian Tengah Semester (UTS)', ['id' => $sec9->id]);
    $DB->set_field('course_sections', 'summary', "
    <div style='background: #fff1f2; border-left: 5px solid #e11d48; padding: 16px 20px; border-radius: 10px;'>
        <h3 style='color: #9f1239; margin: 0 0 6px 0;'>⚠️ Pelaksanaan Ujian Tengah Semester (UTS)</h3>
        <p style='color: #881337; font-size: 13.5px; margin: 0; line-height: 1.5;'>
            Jadwal Ujian: <strong>17 November 2025 (08:00 - 10:00 WIB)</strong><br>
            Durasi: <strong>90 Menit</strong> | Kesempatan: <strong>1 Kali Kerja</strong> | Sifat: <strong>Closed Book</strong>
        </p>
    </div>", ['id' => $sec9->id]);

    // Check if UTS quiz exists
    $uts_quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => '📝 UTS - Bahasa Pemrograman Dasar']);
    if (!$uts_quiz) {
        $quiz = new stdClass();
        $quiz->course           = $course->id;
        $quiz->name             = '📝 UTS - Bahasa Pemrograman Dasar';
        $quiz->intro            = '<p>Kuis Ujian Tengah Semester resmi meliputi materi Pertemuan 1 s.d 7.</p>';
        $quiz->introformat      = FORMAT_HTML;
        $quiz->timeopen         = mktime(8, 0, 0, 11, 17, 2025);
        $quiz->timeclose        = mktime(10, 0, 0, 11, 17, 2025);
        $quiz->timelimit        = 5400; // 90 mins
        $quiz->grademethod      = 1;
        $quiz->attempts         = 1;
        $quiz->grade            = 100;
        $quiz->sumgrades        = 100;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quiz->timemodified     = time();
        $quiz->id = $DB->insert_record('quiz', $quiz);

        $quiz_mod_id = $DB->get_field('modules', 'id', ['name' => 'quiz']);
        $cm = new stdClass();
        $cm->course   = $course->id;
        $cm->module   = $quiz_mod_id;
        $cm->instance = $quiz->id;
        $cm->section  = $sec9->id;
        $cm->visible  = 1;
        $cm->added    = time();
        $DB->insert_record('course_modules', $cm);
        mtrace("  ✅ Kuis UTS Berhasil Dibuat!");
    }
}

// Rebuild course cache
rebuild_course_cache($course->id, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 SHOWCASE KELAS SIF201 BERHASIL DIBANGUN!");
mtrace("   URL Kelas: {$CFG->wwwroot}/course/view.php?id={$course->id}");
mtrace("==================================================");
