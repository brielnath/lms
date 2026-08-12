<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/page/lib.php');

mtrace("==================================================");
mtrace("🚀 MENAMBAHKAN MODUL PEMBELAJARAN SAMPLE DENGAN COMPLETION TRACKING");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);

if (!$course) {
    mtrace("❌ Course SIF105 tidak ditemukan!");
    exit(1);
}

// Pastikan module 'page' tersedia
$module = $DB->get_record('modules', ['name' => 'page']);

if (!$module) {
    mtrace("❌ Modul 'page' tidak ditemukan!");
    exit(1);
}

$sample_topics = [
    [
        'name' => 'Modul 1: Pengantar Computational Thinking & Algoritma',
        'intro' => 'Silakan pelajari modul pengantar ini sebelum memulai kuis.',
        'content' => '<h2>Computational Thinking</h2><p>Computational thinking adalah metode pemecahan masalah dengan menguraikan masalah menjadi bagian-bagian kecil yang lebih mudah diselesaikan.</p>'
    ],
    [
        'name' => 'Modul 2: Abstraksi & Pengenalan Pola (Pattern Recognition)',
        'intro' => 'Materi bab 2 mengenai teknik menemukan pola dalam data.',
        'content' => '<h2>Pattern Recognition</h2><p>Pengenalan pola membantu menemukan kesamaan di antara masalah-masalah yang berbeda.</p>'
    ],
    [
        'name' => 'Modul 3: Desain Algoritma & Flowchart',
        'intro' => 'Materi bab 3 mengenai penyusunan langkah-langkah sistematis.',
        'content' => '<h2>Desain Algoritma</h2><p>Algoritma adalah urutan langkah logis untuk menyelesaikan masalah.</p>'
    ]
];

foreach ($sample_topics as $index => $topic) {
    $page = new stdClass();
    $page->course = $course->id;
    $page->name = $topic['name'];
    $page->intro = $topic['intro'];
    $page->introformat = FORMAT_HTML;
    $page->content = $topic['content'];
    $page->contentformat = FORMAT_HTML;
    $page->timemodified = time();

    // Simpan ke mdl_page
    $page_id = $DB->insert_record('page', $page);

    // Buat course_module
    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $module->id;
    $cm->instance = $page_id;
    $cm->section = 1 + $index;
    $cm->idnumber = 'MOD' . ($index + 1);
    $cm->added = time();
    $cm->score = 0;
    $cm->indent = 0;
    $cm->visible = 1;
    $cm->visibleold = 1;
    $cm->groupmode = 0;
    $cm->groupingid = 0;
    $cm->completion = 1; // Manual completion tracking enabled!

    $cm_id = $DB->insert_record('course_modules', $cm);
    rebuild_course_cache($course->id, true);

    mtrace("     ✅ Modul ditambahkan: '{$topic['name']}' (Completion: AKTIF)");
}

mtrace("==================================================");
mtrace("🎉 MODUL SAMPLE TERMASUK INDIKATOR COMPLETION BERHASIL DIBUAT!");
mtrace("==================================================");
