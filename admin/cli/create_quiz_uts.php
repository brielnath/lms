<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

mtrace("==================================================");
mtrace("🚀 MEMBUAT KUIS ONLINE UTS (AUTO-GRADING) PADA SIF105");
mtrace("==================================================");

$course = $DB->get_record('course', ['shortname' => 'SIF105']);

if (!$course) {
    mtrace("❌ Course SIF105 tidak ditemukan!");
    exit(1);
}

$mod_quiz = $DB->get_record('modules', ['name' => 'quiz']);

if (!$mod_quiz) {
    mtrace("❌ Modul 'quiz' tidak ditemukan!");
    exit(1);
}

// 1. Buat Aktivitas Kuis di mdl_quiz
$quiz = new stdClass();
$quiz->course = $course->id;
$quiz->name = "📝 Kuis Evaluasi UTS: Computational Thinking (Auto-Grading)";
$quiz->intro = "<div style='padding:15px; background:#eef5ff; border-left:5px solid #0056b3; border-radius:6px; margin-bottom:15px;'>
<h3 style='color:#0056b3; margin-top:0;'>📝 Ujian Evaluasi UTS Online</h3>
<p>Kuis ini menguji pemahaman Anda terhadap 4 pilar Computational Thinking. Sistem akan melakukan <strong>Penilaian Otomatis (Auto-Grading)</strong> dan menampilkan skor Anda secara instan begitu ujian diselesaikan!</p>
<ul>
    <li><strong>Durasi Ujian:</strong> 90 Menit</li>
    <li><strong>Kesempatan Mengerjakan:</strong> 1 Kali</li>
    <li><strong>Acak Pilihan Jawaban:</strong> Aktif (Anti-Curang)</li>
</ul>
</div>";
$quiz->introformat = FORMAT_HTML;
$quiz->timeopen = 0;
$quiz->timeclose = 0;
$quiz->timelimit = 5400; // 90 Menit
$quiz->overduehandling = 'autosubmit';
$quiz->graceperiod = 0;
$quiz->preferredbehaviour = 'deferredfeedback';
$quiz->canredoquestions = 0;
$quiz->attempts = 1;
$quiz->attemptonlast = 0;
$quiz->grademethod = QUIZ_GRADEHIGHEST;
$quiz->decimalpoints = 2;
$quiz->questiondecimalpoints = -1;
$quiz->reviewattempt = 0x10010;
$quiz->reviewcorrectness = 0x10010;
$quiz->reviewmarks = 0x10010;
$quiz->reviewspecificfeedback = 0x10010;
$quiz->reviewgeneralfeedback = 0x10010;
$quiz->reviewrightanswer = 0x10010;
$quiz->reviewoverallfeedback = 0x10010;
$quiz->questionsperpage = 1;
$quiz->navmethod = QUIZ_NAVMETHOD_FREE;
$quiz->shuffleanswers = 1;
$quiz->sumgrades = 5;
$quiz->grade = 100;
$quiz->timecreated = time();
$quiz->timemodified = time();

$quiz_id = $DB->insert_record('quiz', $quiz);
mtrace("     ✅ Aktivitas Kuis dibuat di Database Moodle (ID Quiz: {$quiz_id})");

// 2. Buat Course Module
$cm = new stdClass();
$cm->course = $course->id;
$cm->module = $mod_quiz->id;
$cm->instance = $quiz_id;
$cm->section = 8; // Pertemuan 8 (UTS)
$cm->added = time();
$cm->score = 0;
$cm->indent = 0;
$cm->visible = 1;
$cm->visibleold = 1;
$cm->groupmode = 0;
$cm->groupingid = 0;
$cm->completion = 2; // Automatic completion
$cm->completionview = 1; // Completed on view or submission

$cm_id = $DB->insert_record('course_modules', $cm);
rebuild_course_cache($course->id, true);

mtrace("     ✅ Modul Kuis dipasang pada Pertemuan 8 (UTS) SIF105");

// 3. Tambahkan 5 Soal Pilihan Ganda ke Question Bank & Quiz
$context = context_course::instance($course->id);

// Cari atau buat kategori soal default untuk course ini
$cat = $DB->get_record('question_categories', ['contextid' => $context->id]);
if (!$cat) {
    $cat = new stdClass();
    $cat->name = 'Default for SIF105';
    $cat->contextid = $context->id;
    $cat->info = 'Kategori Soal Kuis SIF105';
    $cat->infoformat = FORMAT_HTML;
    $cat->stamp = make_unique_id_code();
    $cat->parent = 0;
    $cat->sortorder = 999;
    $cat->id = $DB->insert_record('question_categories', $cat);
}

$questions_data = [
    [
        'name' => 'Soal 1: Pilar Dekomposisi',
        'questiontext' => '<p>Manakah dari pilar utama Computational Thinking berikut yang bertugas memecahkan masalah besar dan kompleks menjadi komponen-komponen kecil yang lebih mudah dikelola?</p>',
        'answers' => [
            ['text' => 'Abstraksi (Abstraction)', 'fraction' => 0.0],
            ['text' => 'Dekomposisi (Decomposition)', 'fraction' => 1.0], // BENAR
            ['text' => 'Pengenalan Pola (Pattern Recognition)', 'fraction' => 0.0],
            ['text' => 'Desain Algoritma (Algorithm Design)', 'fraction' => 0.0]
        ]
    ],
    [
        'name' => 'Soal 2: Konsep Abstraksi',
        'questiontext' => '<p>Proses membuang detail yang tidak penting dan memfokuskan perhatian hanya pada esensi karakteristik utama informasi dinamakan...</p>',
        'answers' => [
            ['text' => 'Abstraksi (Abstraction)', 'fraction' => 1.0], // BENAR
            ['text' => 'Dekomposisi (Decomposition)', 'fraction' => 0.0],
            ['text' => 'Flowcharting', 'fraction' => 0.0],
            ['text' => 'Pseudocoding', 'fraction' => 0.0]
        ]
    ],
    [
        'name' => 'Soal 3: Simbol Flowchart Jajaran Genjang',
        'questiontext' => '<p>Berdasarkan standar internasional ANSI/ISO Flowchart, simbol berbentuk <strong>Jajaran Genjang</strong> digunakan untuk menggambarkan...</p>',
        'answers' => [
            ['text' => 'Pengujian Kondisi Logika (Decision)', 'fraction' => 0.0],
            ['text' => 'Proses Input atau Output Data', 'fraction' => 1.0], // BENAR
            ['text' => 'Titik Awal / Akhir Program (Terminal)', 'fraction' => 0.0],
            ['text' => 'Eksekusi Perhitungan Matematika (Process)', 'fraction' => 0.0]
        ]
    ],
    [
        'name' => 'Soal 4: Prinsip Struktur Data Queue',
        'questiontext' => '<p>Struktur data Antrean (Queue) dalam pengelolaan data komputer bekerja berdasarkan prinsip...</p>',
        'answers' => [
            ['text' => 'Last-In, First-Out (LIFO)', 'fraction' => 0.0],
            ['text' => 'First-In, First-Out (FIFO)', 'fraction' => 1.0], // BENAR
            ['text' => 'Random Access Memory (RAM)', 'fraction' => 0.0],
            ['text' => 'Binary Tree Search', 'fraction' => 0.0]
        ]
    ],
    [
        'name' => 'Soal 5: Perbandingan Algoritma Pencarian',
        'questiontext' => '<p>Algoritma pencarian yang membelah himpunan data terurut menjadi 2 bagian secara berulang dengan efisiensi notasi $O(\log N)$ dinamakan...</p>',
        'answers' => [
            ['text' => 'Linear Search', 'fraction' => 0.0],
            ['text' => 'Binary Search', 'fraction' => 1.0], // BENAR
            ['text' => 'Bubble Sort', 'fraction' => 0.0],
            ['text' => 'Sequential Search', 'fraction' => 0.0]
        ]
    ]
];

foreach ($questions_data as $qindex => $qdata) {
    // 1. Buat record di mdl_question
    $q = new stdClass();
    $q->category = $cat->id;
    $q->parent = 0;
    $q->name = $qdata['name'];
    $q->questiontext = $qdata['questiontext'];
    $q->questiontextformat = FORMAT_HTML;
    $q->generalfeedback = '';
    $q->generalfeedbackformat = FORMAT_HTML;
    $q->defaultmark = 1.0;
    $q->penalty = 0.3333333;
    $q->qtype = 'multichoice';
    $q->length = 1;
    $q->stamp = make_unique_id_code();
    $q->version = make_unique_id_code();
    $q->timecreated = time();
    $q->timemodified = time();
    $q->createdby = 2; // Admin
    $q->modifiedby = 2;

    $q_id = $DB->insert_record('question', $q);

    // 2. Tambah opsi jawaban di mdl_question_answers
    foreach ($qdata['answers'] as $ans) {
        $answer = new stdClass();
        $answer->question = $q_id;
        $answer->answer = $ans['text'];
        $answer->answerformat = FORMAT_HTML;
        $answer->fraction = $ans['fraction'];
        $answer->feedback = ($ans['fraction'] > 0) ? 'Jawaban Anda Benar!' : 'Jawaban Kurang Tepat.';
        $answer->feedbackformat = FORMAT_HTML;
        $DB->insert_record('question_answers', $answer);
    }

    // 3. Tambahkan opsi multichoice ke mdl_qtype_multichoice_options
    $mc_opt = new stdClass();
    $mc_opt->questionid = $q_id;
    $mc_opt->layout = 0;
    $mc_opt->single = 1;
    $mc_opt->shuffleanswers = 1;
    $mc_opt->correctfeedback = 'Jawaban Anda Benar!';
    $mc_opt->correctfeedbackformat = FORMAT_HTML;
    $mc_opt->partiallycorrectfeedback = 'Jawaban Anda Sebagian Benar.';
    $mc_opt->partiallycorrectfeedbackformat = FORMAT_HTML;
    $mc_opt->incorrectfeedback = 'Jawaban Anda Salah.';
    $mc_opt->incorrectfeedbackformat = FORMAT_HTML;
    $mc_opt->answernumbering = 'abc';
    $mc_opt->shownumcorrect = 1;
    $DB->insert_record('qtype_multichoice_options', $mc_opt);

    // Moodle 4.x Question Bank entries & versions support
    if ($DB->get_manager()->table_exists('question_bank_entries')) {
        $qbe = new stdClass();
        $qbe->questioncategoryid = $cat->id;
        $qbe->idnumber = 'Q' . ($qindex + 1);
        $qbe_id = $DB->insert_record('question_bank_entries', $qbe);

        $qv = new stdClass();
        $qv->questionbankentryid = $qbe_id;
        $qv->version = 1;
        $qv->questionid = $q_id;
        $qv->status = 'ready';
        $DB->insert_record('question_versions', $qv);

        // Link question to Quiz Slots in Moodle 4.x
        if ($DB->get_manager()->table_exists('quiz_slots')) {
            $slot = new stdClass();
            $slot->quizid = $quiz_id;
            $slot->slot = $qindex + 1;
            $slot->page = $qindex + 1;
            $slot->maxmark = 1.0;
            $slot_id = $DB->insert_record('quiz_slots', $slot);

            if ($DB->get_manager()->table_exists('quiz_slot_tags')) {
                // Compatible with slot definitions
            }
            if ($DB->get_manager()->table_exists('question_references')) {
                $qr = new stdClass();
                $qr->usingcontextid = $context->id;
                $qr->component = 'mod_quiz';
                $qr->questionarea = 'slot';
                $qr->itemid = $slot_id;
                $qr->questionbankentryid = $qbe_id;
                $DB->insert_record('question_references', $qr);
            }
        }
    }

    mtrace("     ✅ [SOAL " . ($qindex + 1) . "] '{$qdata['name']}' berhasil ditambahkan ke Kuis!");
}

rebuild_course_cache($course->id, true);

mtrace("==================================================");
mtrace("🎉 KUIS ONLINE EVALUASI UTS (AUTO-GRADING) 100% SIAP DIGUNAKAN!");
mtrace("==================================================");
