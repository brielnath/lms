<?php
/**
 * Contoh materi pertemuan untuk kelas dummy Etika Profesi (DEMO002). Hanya LMS lokal.
 * Dibuat atas nama dosen.demo. Aktivitas yang namanya sudah ada di pertemuan itu dilewati.
 *
 *   php admin/cli/ush_demo_materi_etika_local.php
 *   php admin/cli/ush_demo_materi_etika_local.php --confirm
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/filelib.php');

if (strpos($CFG->wwwroot, 'localhost') === false && strpos($CFG->wwwroot, '127.0.0.1') === false) {
    mtrace('Dibatalkan: bukan LMS lokal.');
    exit(1);
}

$confirm = in_array('--confirm', array_slice($argv, 1), true);
$course = $DB->get_record('course', ['shortname' => 'DEMO002_20262027Ganjil'], '*', MUST_EXIST);
$dosen = $DB->get_record('user', ['username' => 'dosen.demo@sugenghartono.ac.id', 'deleted' => 0], '*', MUST_EXIST);

$meetings = [
    1 => [
        'topic' => 'Pengantar Etika dan Etika Profesi di Bidang TI',
        'desc' => 'Pertemuan ini mengenalkan arti etika, moral, dan profesi, lalu mengapa seorang profesional TI terikat pada etika profesi.',
        'goals' => [
            'Menjelaskan perbedaan etika, moral, dan hukum.',
            'Menjelaskan ciri-ciri sebuah profesi dan arti profesionalisme.',
            'Memberi contoh pelanggaran etika di bidang teknologi informasi.',
        ],
        'video' => 'https://www.youtube.com/watch?v=o2KBYLs69cE',
        'videotitle' => 'Etika dan Etika Profesi dalam Bidang TI',
        'watch' => [
            'Definisi etika dan etika profesi.',
            'Hubungan etika dengan pekerjaan di bidang TI.',
            'Contoh perilaku profesional dan tidak profesional.',
        ],
        'summary' => [
            'Etika adalah cabang filsafat yang membahas baik dan buruknya perbuatan manusia.',
            'Profesi adalah pekerjaan yang menuntut keahlian khusus, pendidikan formal, dan tanggung jawab kepada masyarakat.',
            'Etika profesi adalah norma tertulis yang mengatur perilaku anggota suatu profesi, biasanya dalam bentuk kode etik.',
            'Prinsip umum etika profesi: integritas, objektivitas, kompetensi, akuntabilitas, keadilan, dan tanggung jawab.',
        ],
        'reading' => 'https://id.jobstreet.com/id/career-advice/article/etika-profesi-arti-prinsip-tujuan-manfaat-contoh',
        'readingtitle' => 'Etika Profesi: arti, prinsip, tujuan, dan contohnya (Jobstreet)',
        'question' => 'Ceritakan satu contoh perilaku tidak etis yang pernah kamu lihat di dunia digital (media sosial, aplikasi, atau tempat kerja). Prinsip etika profesi mana yang dilanggar?',
    ],
    2 => [
        'topic' => 'Kode Etik Profesi TI: ACM, IEEE, dan IPKIN',
        'desc' => 'Pertemuan ini membahas kode etik profesi komputer yang berlaku internasional dan di Indonesia, serta cara memakainya saat menghadapi dilema.',
        'goals' => [
            'Menyebutkan prinsip utama ACM Code of Ethics.',
            'Membandingkan kode etik ACM, IEEE, dan IPKIN.',
            'Menerapkan kode etik untuk menilai satu kasus dilema pengembang perangkat lunak.',
        ],
        'video' => 'https://www.youtube.com/watch?v=OgZq59CVjTA',
        'videotitle' => 'Software Engineering Ethics: IEEE CS/ACM Code of Ethics (bahasa Inggris)',
        'watch' => [
            'Delapan prinsip kode etik rekayasa perangkat lunak.',
            'Mengapa kepentingan publik ditempatkan paling atas.',
            'Contoh kasus dan cara menganalisisnya.',
        ],
        'summary' => [
            'ACM Code of Ethics (2018) menekankan: berkontribusi pada masyarakat, menghindari bahaya, jujur, adil, menghormati karya orang lain, dan menjaga privasi.',
            'Kode etik IEEE CS/ACM untuk rekayasa perangkat lunak berisi delapan prinsip, dimulai dari kepentingan publik.',
            'Di Indonesia, IPKIN (Ikatan Profesi Komputer dan Informatika Indonesia) menetapkan kode etik yang disesuaikan dengan kondisi lokal.',
            'Kode etik dipakai sebagai pedoman saat aturan hukum belum mengatur atau saat ada konflik kepentingan.',
        ],
        'reading' => 'https://www.acm.org/code-of-ethics',
        'readingtitle' => 'ACM Code of Ethics and Professional Conduct (situs resmi ACM)',
        'question' => 'Atasanmu meminta aplikasi segera dirilis padahal kamu tahu masih ada celah keamanan yang bisa membocorkan data pengguna. Apa yang kamu lakukan, dan prinsip kode etik mana yang kamu pakai?',
    ],
    3 => [
        'topic' => 'Privasi dan Pelindungan Data Pribadi (UU No. 27 Tahun 2022)',
        'desc' => 'Pertemuan ini membahas hak atas privasi dan kewajiban pengelola data menurut Undang-Undang Pelindungan Data Pribadi.',
        'goals' => [
            'Menjelaskan arti data pribadi dan data pribadi yang bersifat spesifik.',
            'Membedakan pengendali, prosesor, dan subjek data pribadi.',
            'Menjelaskan kewajiban pengembang aplikasi saat mengolah data pengguna.',
        ],
        'video' => 'https://www.youtube.com/watch?v=7BX5Kgcn21o',
        'videotitle' => 'UU Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi',
        'watch' => [
            'Tiga pihak yang diatur: pengendali, prosesor, dan subjek data.',
            'Hak pemilik data pribadi.',
            'Sanksi bila terjadi pelanggaran.',
        ],
        'summary' => [
            'UU PDP ditetapkan 17 Oktober 2022 dan masa transisinya berakhir 17 Oktober 2024.',
            'Pengendali data menentukan tujuan pemrosesan; prosesor mengolah data atas nama pengendali; subjek data adalah pemilik data.',
            'Subjek data berhak mendapat informasi, memperbaiki, menghapus, dan menarik persetujuan atas datanya.',
            'Pengembang wajib menjaga keamanan data, membatasi pengumpulan sesuai tujuan, dan melaporkan kebocoran.',
        ],
        'reading' => 'https://pasal.id/peraturan/uu/uu-no-27-tahun-2022',
        'readingtitle' => 'Teks UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi',
        'question' => 'Sebutkan satu aplikasi yang kamu pakai setiap hari. Data pribadi apa saja yang dikumpulkannya, dan apakah menurutmu semua data itu memang diperlukan?',
    ],
    4 => [
        'topic' => 'Hak Kekayaan Intelektual di Dunia Digital',
        'desc' => 'Pertemuan ini membahas hak cipta, paten, dan merek, serta cara menghargai karya orang lain di dunia digital, termasuk kode program.',
        'goals' => [
            'Menjelaskan jenis-jenis hak kekayaan intelektual.',
            'Menjelaskan perlindungan hak cipta untuk perangkat lunak dan konten digital.',
            'Membedakan pemakaian yang sah dan pelanggaran hak cipta.',
        ],
        'video' => 'https://www.youtube.com/watch?v=1tFjGfsYN6Q',
        'videotitle' => 'Hak Atas Kekayaan Intelektual (HAKI) dalam Literasi Digital',
        'watch' => [
            'Pembagian hak cipta dan hak kekayaan industri.',
            'Hak cipta, paten, dan merek dagang dalam konteks digital.',
            'Mengapa kita perlu menghargai hak cipta.',
        ],
        'summary' => [
            'Hak kekayaan intelektual terbagi menjadi hak cipta dan hak kekayaan industri.',
            'Hak cipta melindungi karya tulis, musik, foto, video, desain, dan kode program komputer.',
            'Hak kekayaan industri mencakup paten, merek, desain industri, rahasia dagang, dan indikasi geografis.',
            'Memakai, menyalin, atau menyebarkan karya orang lain tanpa izin adalah pelanggaran, kecuali lisensinya mengizinkan.',
        ],
        'reading' => 'https://ekii.dgip.go.id',
        'readingtitle' => 'Edukasi Kekayaan Intelektual Indonesia (DJKI)',
        'question' => 'Bolehkah menyalin potongan kode dari internet ke dalam tugas atau proyekmu? Kapan hal itu sah dan kapan termasuk pelanggaran?',
    ],
];

mtrace('=== Materi dummy Etika Profesi ===');
mtrace('Mode: ' . ($confirm ? 'LIVE' : 'DRY-RUN') . '  kelas id ' . $course->id);
foreach ($meetings as $n => $m) {
    mtrace("  Pertemuan $n: " . $m['topic']);
}
if (!$confirm) {
    mtrace('DRY-RUN. Ulangi dengan --confirm.');
    exit(0);
}

\core\session\manager::set_user($dosen);
$modids = $DB->get_records_menu('modules', null, '', 'name, id');
$resourceconfig = get_config('resource');

function ush_list(array $items): string {
    return '<ul>' . implode('', array_map(static fn($i) => '<li>' . s($i) . '</li>', $items)) . '</ul>';
}

function ush_exists(int $courseid, int $sectionnum, string $modname, string $name): bool {
    global $DB;
    return $DB->record_exists_sql(
        "SELECT 1
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module AND m.name = :modname
           JOIN {course_sections} cs ON cs.id = cm.section AND cs.section = :sectionnum
           JOIN {{$modname}} x ON x.id = cm.instance
          WHERE cm.course = :courseid AND cm.deletioninprogress = 0 AND x.name = :name",
        ['modname' => $modname, 'sectionnum' => $sectionnum, 'courseid' => $courseid, 'name' => $name]
    );
}

function ush_before(int $courseid, int $sectionnum): ?int {
    global $DB;
    $first = $DB->get_records_sql(
        "SELECT cm.id
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module AND m.name IN ('assign', 'quiz')
           JOIN {course_sections} cs ON cs.id = cm.section AND cs.section = :sectionnum
          WHERE cm.course = :courseid AND cm.deletioninprogress = 0
       ORDER BY cm.id",
        ['sectionnum' => $sectionnum, 'courseid' => $courseid],
        0,
        1
    );
    return $first ? (int) reset($first)->id : null;
}

function ush_pdf(int $n, array $m): string {
    $pdf = new pdf();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(20, 20, 20);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 11);
    $html = '<h2>Etika Profesi &mdash; Pertemuan ' . $n . '</h2>'
        . '<h3>' . s($m['topic']) . '</h3>'
        . '<p>' . s($m['desc']) . '</p>'
        . '<h4>Tujuan pembelajaran</h4>' . ush_list($m['goals'])
        . '<h4>Ringkasan materi</h4>' . ush_list($m['summary'])
        . '<h4>Sumber</h4><p>Video: ' . s($m['video']) . '<br>Bacaan: ' . s($m['reading']) . '</p>'
        . '<p style="color:#666;font-size:9px">Materi contoh untuk kelas latihan LMS Universitas Sugeng Hartono.</p>';
    $pdf->writeHTML($html);
    return $pdf->Output('', 'S');
}

$created = 0;
foreach ($meetings as $n => $m) {
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $n], '*', MUST_EXIST);
    $section->summary = '<p>' . s($m['desc']) . '</p>'
        . '<p><strong>Tujuan pembelajaran</strong></p>' . ush_list($m['goals'])
        . '<p><strong>Urutan belajar:</strong> tonton video, baca ringkasan PDF, buka bacaan tambahan, lalu ikut diskusi.</p>';
    $section->summaryformat = FORMAT_HTML;
    $DB->update_record('course_sections', $section);

    $before = ush_before((int) $course->id, $n);
    $base = [
        'course' => $course->id,
        'section' => $n,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'introformat' => FORMAT_HTML,
        'beforemod' => $before,
    ];

    $name = 'Video Materi: ' . $m['topic'];
    $content = '<p>' . s($m['desc']) . '</p>'
        . '<p><a href="' . s($m['video']) . '">' . s($m['videotitle']) . '</a></p>'
        . '<p><strong>Perhatikan bagian ini saat menonton:</strong></p>' . ush_list($m['watch']);
    $existingpage = $DB->get_record_sql(
        "SELECT p.id, p.content
           FROM {page} p
           JOIN {course_modules} cm ON cm.instance = p.id AND cm.module = :mod AND cm.deletioninprogress = 0
          WHERE p.course = :course AND p.name = :name",
        ['mod' => $modids['page'], 'course' => $course->id, 'name' => $name],
        IGNORE_MULTIPLE
    );
    if ($existingpage && trim((string) $existingpage->content) === '') {
        $DB->update_record('page', (object) [
            'id' => $existingpage->id,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'revision' => 2,
            'timemodified' => time(),
        ]);
        mtrace("  P$n ~ Isi halaman video diperbaiki");
    } else if (!$existingpage) {
        add_moduleinfo((object) ($base + [
            'module' => $modids['page'],
            'modulename' => 'page',
            'name' => $name,
            'intro' => '',
            'page' => ['text' => $content, 'format' => FORMAT_HTML, 'itemid' => 0],
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'display' => RESOURCELIB_DISPLAY_OPEN,
            'printintro' => 0,
            'printlastmodified' => 1,
        ]), $course);
        $created++;
        mtrace("  P$n + Halaman video");
    }

    $name = 'Ringkasan Materi Pertemuan ' . $n . ' (PDF)';
    if (!ush_exists((int) $course->id, $n, 'resource', $name)) {
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => context_user::instance($dosen->id)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => 'Etika_Profesi_Pertemuan_' . $n . '.pdf',
        ], ush_pdf($n, $m));
        add_moduleinfo((object) ($base + [
            'module' => $modids['resource'],
            'modulename' => 'resource',
            'name' => $name,
            'intro' => '<p>Ringkasan poin penting pertemuan ' . $n . '. Baca setelah menonton video.</p>',
            'files' => $draftid,
            'display' => $resourceconfig->display ?? RESOURCELIB_DISPLAY_AUTO,
            'popupwidth' => $resourceconfig->popupwidth ?? 620,
            'popupheight' => $resourceconfig->popupheight ?? 450,
            'printintro' => 1,
            'showsize' => 1,
            'showtype' => 1,
            'showdate' => 0,
            'filterfiles' => $resourceconfig->filterfiles ?? 0,
        ]), $course);
        $created++;
        mtrace("  P$n + Berkas ringkasan PDF");
    }

    $name = 'Bacaan Tambahan: ' . $m['readingtitle'];
    if (!ush_exists((int) $course->id, $n, 'url', $name)) {
        add_moduleinfo((object) ($base + [
            'module' => $modids['url'],
            'modulename' => 'url',
            'name' => $name,
            'intro' => '<p>Bacaan pendukung untuk memperdalam materi pertemuan ' . $n . '.</p>',
            'externalurl' => $m['reading'],
            'display' => RESOURCELIB_DISPLAY_POPUP,
            'popupwidth' => 1024,
            'popupheight' => 768,
            'printintro' => 1,
        ]), $course);
        $created++;
        mtrace("  P$n + URL bacaan");
    }

    $name = 'Diskusi Pertemuan ' . $n;
    if (!ush_exists((int) $course->id, $n, 'forum', $name)) {
        add_moduleinfo((object) ($base + [
            'module' => $modids['forum'],
            'modulename' => 'forum',
            'name' => $name,
            'intro' => '<p><strong>Pertanyaan pemantik:</strong> ' . s($m['question']) . '</p>'
                . '<p>Jawab dengan bahasamu sendiri, lalu tanggapi minimal satu jawaban teman.</p>',
            'type' => 'single',
            'forcesubscribe' => 0,
            'trackingtype' => 1,
            'assessed' => 0,
            'grade_forum' => 0,
        ]), $course);
        $created++;
        mtrace("  P$n + Forum diskusi");
    }
}

rebuild_course_cache($course->id, true);
mtrace('Selesai. Aktivitas baru: ' . $created);
