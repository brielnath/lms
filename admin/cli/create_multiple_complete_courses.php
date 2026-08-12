<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

mtrace("==================================================");
mtrace("🚀 MEMBUAT BEBERAPA MATA KULIAH LENGKAP DI MOODLE USH");
mtrace("==================================================");

$courses_master = [
    [
        'shortname' => 'MNJ401',
        'fullname' => 'MNJ401 - Manajemen Strategis',
        'category' => 1,
        'summary' => 'Mata kuliah Manajemen Strategis membekali mahasiswa dengan keahlian dalam merumuskan, mengimplementasikan, dan mengevaluasi keputusan lintas fungsi yang memungkinkan organisasi mencapai tujuannya.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus & RPS Manajemen Strategis</h3><p>Dosen Pengampu: Budi Santoso, M.M.</p>'],
            1 => ['name' => 'Pertemuan 1: Pengantar Visi, Misi & Tujuan Perusahaan', 'summary' => '<h4>1. Visi & Misi Perusahaan</h4><p>Pentingnya penetapan arah strategis dan pernyataan misi yang terukur.</p>'],
            2 => ['name' => 'Pertemuan 2: Analisis Lingkungan Eksternal (PESTEL & Porter 5 Forces)', 'summary' => '<h4>2. Analisis PESTEL & Industri</h4><p>Memahami kekuatan persaingan industri dan pengaruh faktor makro politik/ekonomi.</p>'],
            3 => ['name' => 'Pertemuan 3: Analisis Lingkungan Internal & Matriks SWOT', 'summary' => '<h4>3. Analisis SWOT & Sumber Daya</h4><p>Peta kekuatan (Strengths), kelemahan (Weaknesses), peluang (Opportunities), dan ancaman (Threats).</p>'],
            4 => ['name' => 'Pertemuan 4: Strategi Tingkat Korporat & Bisnis Unit', 'summary' => '<h4>4. Strategi Kepemimpinan Biaya & Diferensiasi</h4><p>Bagaimana perusahaan membangun keunggulan bersaing berkelanjutan.</p>'],
            5 => ['name' => 'Pertemuan 5: Formulasi & Eksekusi Strategi', 'summary' => '<h4>5. Implementasi Strategi Organisasi</h4><p>Menyelaraskan struktur organisasi, budaya, dan kepemimpinan.</p>'],
            6 => ['name' => 'Pertemuan 6: Pengukuran Kinerja (Balanced Scorecard)', 'summary' => '<h4>6. Balanced Scorecard Framework</h4><p>Pengukuran dari 4 perspektif: Keuangan, Pelanggan, Proses Internal, dan Pembelajaran.</p>'],
            7 => ['name' => 'Pertemuan 7: Manajemen Risiko & Audit Strategis', 'summary' => '<h4>7. Audit Strategi & Kontinjensi</h4><p>Mengidentifikasi risiko dan menyiapkan rencana darurat kontinjensi.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. Evaluasi UTS Online Manajemen Strategis</h4><p>Ujian evaluasi teori dan analisis studi kasus bisnis.</p>']
        ]
    ],
    [
        'shortname' => 'INF302',
        'fullname' => 'INF302 - Pemrograman Web Lanjut',
        'category' => 1,
        'summary' => 'Mata kuliah Pemrograman Web Lanjut berfokus pada pembangunan aplikasi web skala industri dengan arsitektur REST API, framework backend modern, autentikasi JWT, dan integrasi frontend SPA.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Pemrograman Web Lanjut</h3><p>Dosen Pengampu: Rina Rahmawati, M.T.</p>'],
            1 => ['name' => 'Pertemuan 1: Arsitektur RESTful API & Standar JSON', 'summary' => '<h4>1. Arsitektur Client-Server & REST API</h4><p>Memahami HTTP Methods (GET, POST, PUT, DELETE) dan format data JSON.</p>'],
            2 => ['name' => 'Pertemuan 2: Backend Framework & Routing (Node.js / Express)', 'summary' => '<h4>2. Express.js Server Setup</h4><p>Pengenalan middleware, routing, dan penanganan async/await di Node.js.</p>'],
            3 => ['name' => 'Pertemuan 3: Database ORM & Migrasi Schema', 'summary' => '<h4>3. Object-Relational Mapping (ORM)</h4><p>Pengelolaan database relational menggunakan ORM (Sequelize / Prisma / Eloquent).</p>'],
            4 => ['name' => 'Pertemuan 4: Autentikasi Token (JWT & Bearer Auth)', 'summary' => '<h4>4. Securing API with JSON Web Token (JWT)</h4><p>Mekanisme hashing password, pembuatan token access, dan proteksi endpoint.</p>'],
            5 => ['name' => 'Pertemuan 5: Integrasi Frontend Single Page Application (SPA)', 'summary' => '<h4>5. Connecting React/Vue dengan REST API</h4><p>Konsumsi API dari frontend SPA dengan Axios / Fetch API.</p>'],
            6 => ['name' => 'Pertemuan 6: Penanganan File Upload & Cloud Storage', 'summary' => '<h4>6. Upload Handling & Amazon S3</h4><p>Teknik streaming file upload dan penyimpanan media di cloud.</p>'],
            7 => ['name' => 'Pertemuan 7: Testing API & Deployment Cloud Hosting', 'summary' => '<h4>7. Postman Automated Test & Docker/Cloud Deploy</h4><p>Automated API testing dan deployment ke Docker / AWS / Vercel.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. Ujian Praktek Live Coding REST API</h4><p>Pembuatan proyek backend API terproteksi JWT dalam waktu 90 menit.</p>']
        ]
    ],
    [
        'shortname' => 'BDG201',
        'fullname' => 'BDG201 - Bisnis Digital & E-Commerce',
        'category' => 1,
        'summary' => 'Mata kuliah ini membahas arsitektur ekosistem bisnis digital, perancangan platform e-commerce, strategi digital marketing, dan analitik optimasi konversi penjualan.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Bisnis Digital & E-Commerce</h3><p>Dosen Pengampu: Dian Puspita, M.M.</p>'],
            1 => ['name' => 'Pertemuan 1: Model Bisnis Digital & Ekosistem Startup', 'summary' => '<h4>1. Tren Model Bisnis B2B, B2C, C2C, dan Marketplace</h4><p>Memahami perbedaan lanskap e-commerce tradisional dan era marketplace.</p>'],
            2 => ['name' => 'Pertemuan 2: Digital Marketing & Optimasi SEO/SEM', 'summary' => '<h4>2. Search Engine Optimization & Paid Ads</h4><p>Strategi organik SEO, kata kunci, Google Ads, dan Meta Social Ads.</p>'],
            3 => ['name' => 'Pertemuan 3: UI/UX Design & Customer Journey Mapping', 'summary' => '<h4>3. Pengalaman Pengguna (UX) di E-Commerce</h4><p>Desain alur keranjang belanja dan kemudahan transaksi checkout.</p>'],
            4 => ['name' => 'Pertemuan 4: Payment Gateway & Sistem Integrasi Logistik', 'summary' => '<h4>4. FinTech & API Ekspedisi</h4><p>Integrasi Midtrans/Xendit dan kalkulasi ongkos kirim real-time.</p>'],
            5 => ['name' => 'Pertemuan 5: Data Analytics & Conversion Rate Optimization (CRO)', 'summary' => '<h4>5. Google Analytics & Funnel Sales</h4><p>Menganalisis rasio konversi pengunjung toko menjadi pembeli aktif.</p>'],
            6 => ['name' => 'Pertemuan 6: Customer Relationship Management (CRM Digital)', 'summary' => '<h4>6. Retensi & Loyalty Program</h4><p>Strategi email marketing, broadcast WhatsApp, dan program kupon promo.</p>'],
            7 => ['name' => 'Pertemuan 7: Hukum & Keamanan Transaksi Elektronik (UU ITE)', 'summary' => '<h4>7. Perlindungan Data Pribadi & Hak Cipta</h4><p>Aspek hukum e-commerce dan perlindungan transaksi keuangan.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. UTS Pitching Rancangan Proposal Startup Digital</h4><p>Presentasi rencana bisnis startup digital e-commerce.</p>']
        ]
    ],
    [
        'shortname' => 'GZI102',
        'fullname' => 'GZI102 - Gizi Kuliner & Keamanan Pangan',
        'category' => 1,
        'summary' => 'Mata kuliah Gizi Kuliner mempelajari aspek pengolahan bahan makanan dengan tetap mempertahankan kandungan gizi, standar sanitasi higiene, dan analisis sistem keamanan pangan HACCP.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Gizi Kuliner & Keamanan Pangan</h3><p>Dosen Pengampu: dr. Maya Saraswati, M.Gizi</p>'],
            1 => ['name' => 'Pertemuan 1: Prinsip Dasar Gizi Kuliner & Nilai Nutrisi', 'summary' => '<h4>1. Pengantar Gizi Kuliner</h4><p>Hubungan antara teknik memasak dengan kerusakan atau retensi vitamin/mineral.</p>'],
            2 => ['name' => 'Pertemuan 2: Analisis Kualitas Bahan Makanan Segar', 'summary' => '<h4>2. Pemilihan Bahan Makanan</h4><p>Standar kualitas protein hewani, nabati, sayur, dan buah segar.</p>'],
            3 => ['name' => 'Pertemuan 3: Teknik Pengolahan Pangan Sehat', 'summary' => '<h4>3. Steaming, Poaching, & Roasting</h4><p>Pengolahan makanan rendah kalori dan menjaga antioksidan.</p>'],
            4 => ['name' => 'Pertemuan 4: Sanitasi & Higiene Pengolahan Pangan', 'summary' => '<h4>4. Kebersihan Penjamah Makanan</h4><p>Pencegahan kontaminasi silang dan sanitasi alat dapur.</p>'],
            5 => ['name' => 'Pertemuan 5: Keamanan Pangan & Sistem HACCP', 'summary' => '<h4>5. Hazard Analysis Critical Control Point</h4><p>Analisis bahaya biologis, kimia, dan fisik pada rantai makanan.</p>'],
            6 => ['name' => 'Pertemuan 6: Formulasi Resep & Perhitungan Kalori Makanan', 'summary' => '<h4>6. Perhitungan DKBM / TKPI</h4><p>Menghitung kandungan gizi total dari resep masakan kuliner.</p>'],
            7 => ['name' => 'Pertemuan 7: Manajemen Dapur Industri & Catering', 'summary' => '<h4>7. Layanan Makanan Masal</h4><p>Manajemen porsi, distribusi makanan, dan standar suhu saji.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. Evaluasi UTS Teori Gizi & HACCP</h4><p>Ujian online evaluasi gizi kuliner dan analisis dokumen HACCP.</p>']
        ]
    ]
];

foreach ($courses_master as $cdata) {
    mtrace("\n--------------------------------------------------");
    mtrace("📚 Memproses Course: {$cdata['fullname']}");

    $course = $DB->get_record('course', ['shortname' => $cdata['shortname']]);

    if (!$course) {
        $newcourse = new stdClass();
        $newcourse->fullname = $cdata['fullname'];
        $newcourse->shortname = $cdata['shortname'];
        $newcourse->idnumber = $cdata['shortname'];
        $newcourse->category = $cdata['category'];
        $newcourse->summary = $cdata['summary'];
        $newcourse->summaryformat = FORMAT_HTML;
        $newcourse->format = 'topics';
        $newcourse->numsections = 8;
        $newcourse->coursedisplay = 0; // Show all on one page
        $newcourse->startdate = time();
        $newcourse->visible = 1;
        $newcourse->enablecompletion = 1;

        $course = create_course($newcourse);
        mtrace("     ✨ [AUTO-CREATE] Course baru berhasil dibuat: {$cdata['fullname']}");
    } else {
        $course->fullname = $cdata['fullname'];
        $course->summary = $cdata['summary'];
        $course->summaryformat = FORMAT_HTML;
        $course->coursedisplay = 0;
        $course->numsections = 8;
        $course->enablecompletion = 1;
        $DB->update_record('course', $course);
        mtrace("     ℹ️ Course sudah ada, pembaruan data selesai.");
    }

    // Set Sections & Summaries
    foreach ($cdata['sections'] as $sec_num => $sinfo) {
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sec_num]);
        if (!$section) {
            $section = new stdClass();
            $section->course = $course->id;
            $section->section = $sec_num;
            $section->id = $DB->insert_record('course_sections', $section);
        }
        $section->name = $sinfo['name'];
        $section->summary = $sinfo['summary'];
        $section->summaryformat = FORMAT_HTML;
        $DB->update_record('course_sections', $section);
        mtrace("     ✅ [SECTION {$sec_num}] {$sinfo['name']}");
    }

    rebuild_course_cache($course->id, true);
}

mtrace("==================================================");
mtrace("🎉 SELURUH MATA KULIAH BARU BERHASIL DIBUAT DENGAN LENGKAP!");
mtrace("==================================================");
