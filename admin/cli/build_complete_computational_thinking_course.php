<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');

mtrace("==================================================");
mtrace("🚀 MEMBANGUN MATA KULIAH LENGKAP: COMPUTATIONAL THINKING (SIF105)");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);

if (!$course) {
    mtrace("❌ Course SIF105 tidak ditemukan!");
    exit(1);
}

// 1. Reset Modul & Aktivitas Lama pada Course SIF105
$cms = $DB->get_records('course_modules', ['course' => $course->id]);
foreach ($cms as $cm) {
    $DB->delete_records('course_modules', ['id' => $cm->id]);
}
$DB->delete_records('page', ['course' => $course->id]);
$DB->delete_records('forum', ['course' => $course->id]);
$DB->delete_records('assign', ['course' => $course->id]);

// Update informasi utama Course
$course->fullname = "SIF105 - Computational Thinking";
$course->summary = "<div style='background:#f4f6f9; padding:15px; border-left:5px solid #0056b3; border-radius:5px;'>
<h3>Mata Kuliah Computational Thinking (SIF105)</h3>
<p>Mata kuliah ini membekali mahasiswa dengan fondasi berpikir komputasional, teknik dekomposisi masalah, pengenalan pola, abstraksi, serta penyusunan algoritma efisien untuk memecahkan masalah kompleks dalam era digital.</p>
<p><strong>Dosen Pengampu:</strong> Dwi Utari Iswavigra, S.T., M.Kom.</p>
</div>";
$course->summaryformat = FORMAT_HTML;
$course->format = 'topics';
$course->numsections = 8;
$course->enablecompletion = 1;
$DB->update_record('course', $course);

rebuild_course_cache($course->id, true);

$mod_page = $DB->get_record('modules', ['name' => 'page']);
$mod_forum = $DB->get_record('modules', ['name' => 'forum']);
$mod_assign = $DB->get_record('modules', ['name' => 'assign']);

// Helper untuk menambah modul
function add_custom_module($course_id, $mod_id, $instance_id, $section_num, $cm_name) {
    global $DB;
    $cm = new stdClass();
    $cm->course = $course_id;
    $cm->module = $mod_id;
    $cm->instance = $instance_id;
    $cm->section = $section_num;
    $cm->added = time();
    $cm->score = 0;
    $cm->indent = 0;
    $cm->visible = 1;
    $cm->visibleold = 1;
    $cm->groupmode = 0;
    $cm->groupingid = 0;
    $cm->completion = 2; // Automatic completion
    $cm->completionview = 1; // Done on view

    $cm_id = $DB->insert_record('course_modules', $cm);
    mtrace("     ✅ [MODUL] Added: {$cm_name} (Auto-Completion)");
    return $cm_id;
}

// --------------------------------------------------
// SECTION 0: PENGANTAR & SILABUS
// --------------------------------------------------
mtrace("\n📌 [SECTION 0] Pengantar & Silabus Perkuliahan");

$page0 = new stdClass();
$page0->course = $course->id;
$page0->name = "📖 Silabus & Rencana Pembelajaran Semester (RPS)";
$page0->intro = "Petunjuk Umum Perkuliahan Computational Thinking";
$page0->introformat = FORMAT_HTML;
$page0->content = "<div style='font-family:sans-serif;'>
<h2>Rencana Pembelajaran Semester (RPS)</h2>
<p>Selamat datang di mata kuliah <strong>Computational Thinking</strong>. Mata kuliah ini terdiri dari 8 Pertemuan Utama:</p>
<ul>
    <li><strong>Pertemuan 1:</strong> Pengantar Computational Thinking & Dekomposisi</li>
    <li><strong>Pertemuan 2:</strong> Pengenalan Pola (Pattern Recognition)</li>
    <li><strong>Pertemuan 3:</strong> Abstraksi & Pemodelan Masalah</li>
    <li><strong>Pertemuan 4:</strong> Desain Algoritma & Flowchart</li>
    <li><strong>Pertemuan 5:</strong> Logika & Pemrograman Dasar</li>
    <li><strong>Pertemuan 6:</strong> Penugasan Kasus Kompleks</li>
    <li><strong>Pertemuan 7:</strong> Diskusi Kelompok & Evaluasi Algoritma</li>
    <li><strong>Pertemuan 8:</strong> Ujian Tengah Semester (UTS)</li>
</ul>
</div>";
$page0->contentformat = FORMAT_HTML;
$page0->timemodified = time();
$page0_id = $DB->insert_record('page', $page0);
add_custom_module($course->id, $mod_page->id, $page0_id, 0, "Silabus & RPS");

// Forum Pengumuman
$forum0 = new stdClass();
$forum0->course = $course->id;
$forum0->name = "📢 Forum Pengumuman & Diskusi Kelas";
$forum0->intro = "Wadah pengumuman resmi dari dosen dan diskusi perkuliahan.";
$forum0->introformat = FORMAT_HTML;
$forum0->type = "general";
$forum0->timemodified = time();
$forum0_id = $DB->insert_record('forum', $forum0);
add_custom_module($course->id, $mod_forum->id, $forum0_id, 0, "Forum Pengumuman");

// --------------------------------0------------------
// SECTION 1 - 8: PERTEMUAN KULIAH LENGKAP
// --------------------------------------------------

$pertemuan_data = [
    1 => [
        'title' => 'Pertemuan 1: Pengantar Computational Thinking & Dekomposisi',
        'materi_title' => '📄 Materi 1: Konsep Dekomposisi Masalah Kompleks',
        'content' => "<div style='padding:15px; border:1px solid #ddd; border-radius:8px;'>
            <h3>1. Apa itu Computational Thinking?</h3>
            <p>Computational thinking (CT) adalah proses berpikir dalam merumuskan masalah dan solusinya, sehingga solusi tersebut dapat secara efektif dijalankan oleh agen pemroses informasi (manusia atau komputer).</p>
            <h3>2. Pilar Utama CT: Dekomposisi</h3>
            <p>Dekomposisi adalah memecah masalah besar dan rumit menjadi bagian-bagian kecil yang lebih mudah dikelola dan diselesaikan.</p>
        </div>"
    ],
    2 => [
        'title' => 'Pertemuan 2: Pengenalan Pola (Pattern Recognition)',
        'materi_title' => '📄 Materi 2: Menganalisis Pola dan Tren Data',
        'content' => "<div style='padding:15px; border:1px solid #ddd; border-radius:8px;'>
            <h3>Pengenalan Pola (Pattern Recognition)</h3>
            <p>Melihat kesamaan atau pola pada masalah yang berbeda. Dengan mengenali pola, kita dapat menerapkan solusi yang pernah berhasil pada masalah serupa.</p>
        </div>"
    ],
    3 => [
        'title' => 'Pertemuan 3: Abstraksi & Pemodelan Masalah',
        'materi_title' => '📄 Materi 3: Teknik Abstraksi & Menyaring Informasi',
        'content' => "<div style='padding:15px; border:1px solid #ddd; border-radius:8px;'>
            <h3>Prinsip Abstraksi</h3>
            <p>Fokus pada informasi yang penting saja dan mengabaikan detail yang tidak relevan agar kompleksitas masalah berkurang.</p>
        </div>"
    ],
    4 => [
        'title' => 'Pertemuan 4: Desain Algoritma & Flowchart',
        'materi_title' => '📄 Materi 4: Menyusun Algoritma Sistematis & Simbol Flowchart',
        'content' => "<div style='padding:15px; border:1px solid #ddd; border-radius:8px;'>
            <h3>Desain Algoritma</h3>
            <p>Menyusun langkah-langkah terurut (step-by-step) untuk menyelesaikan masalah secara konsisten.</p>
        </div>"
    ],
    5 => [
        'title' => 'Pertemuan 5: Logika & Struktur Data Dasar',
        'materi_title' => '📄 Materi 5: Penerapan Logika Boolean & Array',
        'content' => "<div style='padding:15px; border:1px solid #ddd; border-radius:8px;'>
            <h3>Logika Pemrograman</h3>
            <p>Penggunaan operator logika AND, OR, NOT serta pengorganisasian data menggunakan variabel dan array.</p>
        </div>"
    ],
    6 => [
        'title' => 'Pertemuan 6: Studi Kasus & Penugasan Mandiri',
        'materi_title' => '📄 Studi Kasus: Studi Kasus Dekomposisi Sistem Parkir Otomatis',
        'content' => "<div style='padding:15px; border:1px solid #ddd; border-radius:8px;'>
            <h3>Studi Kasus Sistem Parkir</h3>
            <p>Pelajari studi kasus penerapan Computational Thinking dalam merancang algoritma tiket parkir otomatis.</p>
        </div>"
    ],
    7 => [
        'title' => 'Pertemuan 7: Evaluasi & Optimasi Algoritma',
        'materi_title' => '📄 Materi 7: Efisiensi Kompleksitas Waktu & Ruang',
        'content' => "<div style='padding:15px; border:1px solid #ddd; border-radius:8px;'>
            <h3>Optimasi Algoritma</h3>
            <p>Bagaimana mengukur efisiensi algoritma agar dapat dieksekusi dengan cepat dan hemat memori.</p>
        </div>"
    ],
    8 => [
        'title' => 'Pertemuan 8: Ujian Tengah Semester (UTS)',
        'materi_title' => '📄 Petunjuk Pelaksanaan UTS Computational Thinking',
        'content' => "<div style='padding:15px; border:1px solid #d9534f; border-radius:8px; background:#fff5f5;'>
            <h3 style='color:#d9534f;'>Ujian Tengah Semester (UTS)</h3>
            <p>Selamat mengerjakan UTS. Pastikan Anda sudah mempelajari seluruh materi Pertemuan 1 sampai 7.</p>
        </div>"
    ]
];

foreach ($pertemuan_data as $sec_num => $pdata) {
    mtrace("\n📌 [SECTION {$sec_num}] {$pdata['title']}");

    // Set nama section di Moodle
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sec_num]);
    if (!$section) {
        $section = new stdClass();
        $section->course = $course->id;
        $section->section = $sec_num;
        $section->summaryformat = FORMAT_HTML;
        $section->id = $DB->insert_record('course_sections', $section);
    }
    $section->name = $pdata['title'];
    $DB->update_record('course_sections', $section);

    // Tambah Materi Halaman
    $p = new stdClass();
    $p->course = $course->id;
    $p->name = $pdata['materi_title'];
    $p->intro = "Materi Pokok Pembelajaran Pertemuan {$sec_num}";
    $p->introformat = FORMAT_HTML;
    $p->content = $pdata['content'];
    $p->contentformat = FORMAT_HTML;
    $p->timemodified = time();
    $p_id = $DB->insert_record('page', $p);
    add_custom_module($course->id, $mod_page->id, $p_id, $sec_num, $pdata['materi_title']);

    // Pada Pertemuan 6, tambahkan Tugas (Assignment)
    if ($sec_num === 6) {
        $assign = new stdClass();
        $assign->course = $course->id;
        $assign->name = "📝 Tugas Mandiri 1: Perancangan Flowchart Sistem Kampus";
        $assign->intro = "<p>Buatlah flowchart dan dekomposisi masalah untuk sistem pendaftaran KRS mahasiswa. Kumpulkan file PDF di sini.</p>";
        $assign->introformat = FORMAT_HTML;
        $assign->alwaysshowdescription = 1;
        $assign->nosubmissions = 0;
        $assign->submissiondrafts = 0;
        $assign->sendnotifications = 0;
        $assign->sendlatenotifications = 0;
        $assign->duedate = time() + (7 * 24 * 3600); // 7 hari dari sekarang
        $assign->allowsubmissionsfromdate = time();
        $assign->grade = 100;
        $assign->timemodified = time();
        $assign_id = $DB->insert_record('assign', $assign);
        add_custom_module($course->id, $mod_assign->id, $assign_id, $sec_num, "Tugas Mandiri 1");
    }
}

rebuild_course_cache($course->id, true);

mtrace("==================================================");
mtrace("🎉 MATA KULIAH SIF105 - COMPUTATIONAL THINKING LENGKAP 8 PERTEMUAN BERHASIL DIBANGUN!");
mtrace("==================================================");
