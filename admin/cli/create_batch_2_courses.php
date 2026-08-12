<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');

mtrace("==================================================");
mtrace("🚀 MEMBUAT BATCH 2: 5 MATA KULIAH BARU LENGKAP DI MOODLE USH");
mtrace("==================================================");

$courses_batch2 = [
    [
        'shortname' => 'HKM101',
        'fullname' => 'HKM101 - Hukum Bisnis & Etika Profesi',
        'category' => 1,
        'summary' => 'Mata kuliah ini membahas landasan hukum dalam kegiatan bisnis, regulasi perseroan, perlindungan konsumen, kontrak bisnis, Hak Kekayaan Intelektual (HAKI), serta penerapan etika dalam dunia profesional.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Hukum Bisnis & Etika Profesi</h3><p>Dosen Pengampu: Prof. Dr. Hendra Gunawan, S.H., M.Hum.</p>'],
            1 => ['name' => 'Pertemuan 1: Pengantar Hukum Bisnis & Sistem Hukum Indonesia', 'summary' => '<h4>1. Konsep Hukum & Kegiatan Ekonomi</h4><p>Pengertian hukum bisnis, hierarki peraturan perundang-undangan, dan peran hukum dalam kepastian transaksi.</p>'],
            2 => ['name' => 'Pertemuan 2: Subjek & Objek Hukum Bisnis', 'summary' => '<h4>2. Subjek Hukum Mandiri & Badan Hukum</h4><p>Perbedaan perorangan (Natuurlijk Persoon) dan badan hukum (Rechtspersoon) seperti PT, CV, dan Firma.</p>'],
            3 => ['name' => 'Pertemuan 3: Hukum Perjanjian & Penyusunan Kontrak Bisnis', 'summary' => '<h4>3. Syarat Sah Perjanjian (Pasal 1320 KUHPdt)</h4><p>Klausul kontrak bisnis, wanprestasi, dan penyelesaian sengketa arbitrase.</p>'],
            4 => ['name' => 'Pertemuan 4: Hukum Perseroan Terbatas (UU No 40/2007)', 'summary' => '<h4>4. Struktur PT: RUPS, Direksi, & Dewan Komisaris</h4><p>Tanggung jawab pemegang saham dan prinsip Good Corporate Governance (GCG).</p>'],
            5 => ['name' => 'Pertemuan 5: Hak Kekayaan Intelektual (HAKI)', 'summary' => '<h4>5. Perlindungan Merek, Hak Cipta, & Paten</h4><p>Prosedur pendaftaran Merek Dagang, Hak Cipta produk digital, dan Lisensi.</p>'],
            6 => ['name' => 'Pertemuan 6: Hukum Perlindungan Konsumen & Persaingan Usaha', 'summary' => '<h4>6. UU No 8/1999 & Larangan Praktik Monopoli</h4><p>Hak-hak konsumen dan batasan persaingan sehat antar pelaku usaha.</p>'],
            7 => ['name' => 'Pertemuan 7: Etika Bisnis & Tanggung Jawab Sosial (CSR)', 'summary' => '<h4>7. Dilema Etika & Corporate Social Responsibility</h4><p>Penerapan etika profesional dan kontribusi sosial perusahaan.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. Evaluasi UTS Online Hukum Bisnis</h4><p>Ujian analisis studi kasus sengketa kontrak dan pelanggaran etika bisnis.</p>']
        ]
    ],
    [
        'shortname' => 'FAR202',
        'fullname' => 'FAR202 - Farmakologi Dasar & Farmasi Klinik',
        'category' => 1,
        'summary' => 'Mata kuliah ini membahas mekanisme kerja obat pada tubuh, proses farmakokinetika dan farmakodinamika, serta penerapan farmasi klinik dalam pelayanan resep obat medis.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Farmakologi Dasar & Farmasi Klinik</h3><p>Dosen Pengampu: apt. Dr. Ratna Juwita, M.Sc.</p>'],
            1 => ['name' => 'Pertemuan 1: Pengantar Farmakologi & Nasib Obat dalam Tubuh', 'summary' => '<h4>1. Konsep Dasar Obat & Dosis</h4><p>Definisi obat, rute pemberian obat (oral, iv, im), dan rincian fase farmasetik.</p>'],
            2 => ['name' => 'Pertemuan 2: Prinsip Farmakokinetika (ADME)', 'summary' => '<h4>2. Absorpsi, Distribusi, Metabolisme, & Ekskresi</h4><p>Proses penyerapan obat, bioavailabilitas, metabolisme di hati, dan eliminasi ginjal.</p>'],
            3 => ['name' => 'Pertemuan 3: Prinsip Farmakodinamika & Interaksi Reseptor', 'summary' => '<h4>3. Mekanisme Kerja Obat pada Reseptor</h4><p>Konsep Agonis, Antagonis, afinitas reseptor, dan kurva respon dosis.</p>'],
            4 => ['name' => 'Pertemuan 4: Farmakologi Obat Sistem Saraf Otonom', 'summary' => '<h4>4. Obat Simpatomimetik & Parasimpatomimetik</h4><p>Pengaruh obat pada saraf simpatik dan parasimpatik tubuh.</p>'],
            5 => ['name' => 'Pertemuan 5: Farmakologi Antibiotik & Antimikroba', 'summary' => '<h4>5. Klasifikasi Antibakteri & Resistensi Obat</h4><p>Golongan Penisilin, Sefalosporin, Kuinolon, dan pencegahan resistensi bakteri.</p>'],
            6 => ['name' => 'Pertemuan 6: Interaksi Obat & Efek Samping (Adverse Drug Event)', 'summary' => '<h4>6. Interaksi Farmakokinetik vs Farmakodinamik</h4><p>Mendeteksi toksisitas obat dan kombinasi kontrainplikasi obat.</p>'],
            7 => ['name' => 'Pertemuan 7: Pelayanan Farmasi Klinik & Skrining Resep', 'summary' => '<h4>7. Penyiapan Obat & Komunikasi Edukasi Pasien</h4><p>Analisis legalitas resep, compounding, dan konseling apoteker.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. UTS Teori Farmakologi & Kasus Dosis Obat</h4><p>Ujian evaluasi mekanisme kerja obat dan kalkulasi dosis pasien.</p>']
        ]
    ],
    [
        'shortname' => 'K3M301',
        'fullname' => 'K3M301 - Keselamatan & Kesehatan Kerja (K3) Industri',
        'category' => 1,
        'summary' => 'Mata kuliah K3 Industri mempelajari identifikasi potensi bahaya di tempat kerja, analisis risiko keselamatan, sistem manajemen K3 ISO 45001, serta budaya pencegahan kecelakaan kerja.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Keselamatan & Kesehatan Kerja (K3)</h3><p>Dosen Pengampu: Ir. Bambang Supriyadi, M.KKK</p>'],
            1 => ['name' => 'Pertemuan 1: Dasar Hukum & Regulasi K3 (UU No 1/1970)', 'summary' => '<h4>1. Peraturan Perundangan K3 Indonesia</h4><p>Hak dan kewajiban tenaga kerja serta kewajiban pengusaha dalam menjamin lingkungan kerja aman.</p>'],
            2 => ['name' => 'Pertemuan 2: Identifikasi Bahaya & Penilaian Risiko (HIRADC)', 'summary' => '<h4>2. Hazard Identification & Risk Assessment</h4><p>Kategori bahaya Fisik, Kimia, Biologi, Ergonomi, dan Psikososial di tempat kerja.</p>'],
            3 => ['name' => 'Pertemuan 3: Sistem Manajemen K3 (SMK3 & ISO 45001)', 'summary' => '<h4>3. Implementasi Kerangka SMK3</h4><p>Prinsip Plan-Do-Check-Act (PDCA) dalam kebijakan keselamatan industri.</p>'],
            4 => ['name' => 'Pertemuan 4: Alat Pelindung Diri (APD) & Prinsip Ergonomi', 'summary' => '<h4>4. Hierarki Pengendalian Risiko & APD</h4><p>Eliminasi, Substitusi, Rekayasa Teknik, Kontrol Administratif, dan pemilihan APD standar.</p>'],
            5 => ['name' => 'Pertemuan 5: Investigasi & Pelaporan Kecelakaan Kerja', 'summary' => '<h4>5. Root Cause Analysis Kecelakaan</h4><p>Teknik wawancara saksi, metode 5-Why, dan penyusunan berita acara kecelakaan.</p>'],
            6 => ['name' => 'Pertemuan 6: Tanggap Darurat & Proteksi Kebakaran (Fire Safety)', 'summary' => '<h4>6. Sistem Proteksi Aktif & Pasif Kebakaran</h4><p>Penggunaan APAR, Hydrant, Sprikler, dan rute evakuasi darurat (Assembly Point).</p>'],
            7 => ['name' => 'Pertemuan 7: Higiene Industri & Pengukuran Paparan Lingkungan', 'summary' => '<h4>7. Nilai Ambang Batas (NAB) Kebisingan & Pencahayaan</h4><p>Pengukuran tingkat kebisingan (Sound Level Meter) dan kualitas udara kerja.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. UTS Evaluasi Audit Dokumen HIRADC</h4><p>Ujian analisis dokumen HIRADC dan simulasi penanganan darurat K3.</p>']
        ]
    ],
    [
        'shortname' => 'AKT102',
        'fullname' => 'AKT102 - Akuntansi Keuangan & Pelaporan Keuangan',
        'category' => 1,
        'summary' => 'Mata kuliah ini membahas siklus lengkap akuntansi, penyusunan laporan keuangan standar IFRS/SAK, pencatatan persediaan, aset tetap, serta pelaporan neraca keuangan perusahaan.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Akuntansi Keuangan</h3><p>Dosen Pengampu: Sri Wahyuni, S.E., M.Si., Ak., CA</p>'],
            1 => ['name' => 'Pertemuan 1: Persamaan Dasar Akuntansi & Siklus Transaksi', 'summary' => '<h4>1. Aset = Liabilitas + Ekuitas</h4><p>Pengenalan akun debet/kredit dan pengaruh transaksi keuangan pada persamaan dasar.</p>'],
            2 => ['name' => 'Pertemuan 2: Jurnal Umum, Buku Besar, & Neraca Saldo', 'summary' => '<h4>2. Pencatatan Transaksi Harian</h4><p>Proses posting jurnal umum ke buku besar (General Ledger) dan penyusunan Trial Balance.</p>'],
            3 => ['name' => 'Pertemuan 3: Jurnal Penyesuaian (Adjusting Entries)', 'summary' => '<h4>3. Penyesuaian Beban Dibayar Dimuka & Pendapatan Diterima Dimuka</h4><p>Prinsip akrual vs kas dalam pengakuan pendapatan dan beban.</p>'],
            4 => ['name' => 'Pertemuan 4: Penyusunan Laporan Keuangan Lengkap', 'summary' => '<h4>4. Laporan Laba Rugi, Perubahan Ekuitas, & Neraca</h4><p>Menyusun laporan Laba/Rugi (Income Statement) dan Laporan Posisi Keuangan (Balance Sheet).</p>'],
            5 => ['name' => 'Pertemuan 5: Akuntansi Persediaan (FIFO, LIFO, Average)', 'summary' => '<h4>5. Metode Penilaian Persediaan Barang</h4><p>Perhitungan Harga Pokok Penjualan (HPP) dengan metode FIFO dan Rata-rata Tertimbang.</p>'],
            6 => ['name' => 'Pertemuan 6: Akuntansi Aset Tetap & Depresiasi (Penyusutan)', 'summary' => '<h4>6. Metode Garis Lurus & Saldo Menurun</h4><p>Perhitungan depresiasi aset bangunan, kendaraan, dan peralatan kantor.</p>'],
            7 => ['name' => 'Pertemuan 7: Jurnal Penutup & Neraca Saldo Setelah Penutupan', 'summary' => '<h4>7. Closing Entries & Akun Nominal</h4><p>Menutup akun temporer laba/rugi ke akun Modal di akhir periode akuntansi.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. UTS Praktek Penyusunan Laporan Keuangan</h4><p>Ujian praktek siklus akuntansi lengkap dari transaksi hingga neraca.</p>']
        ]
    ],
    [
        'shortname' => 'DES105',
        'fullname' => 'DES105 - Desain Komunikasi Visual & Branding',
        'category' => 1,
        'summary' => 'Mata kuliah DKV & Branding mengajarkan teori komposisi visual, hirarki tipografi, psikologi warna, prinsip Gestalt, serta perancangan identitas merek (Brand Identity) profesional.',
        'sections' => [
            0 => ['name' => 'General', 'summary' => '<h3>📖 Silabus Desain Komunikasi Visual & Branding</h3><p>Dosen Pengampu: Yudi Febrianto, S.Sn., M.Ds.</p>'],
            1 => ['name' => 'Pertemuan 1: Dasar Komposisi Visual & Elemen DKV', 'summary' => '<h4>1. Garis, Bentuk, Ruang, & Tekstur Visual</h4><p>Prinsip keterbacaan, titik fokus (Focal Point), dan Keseimbangan (Balance).</p>'],
            2 => ['name' => 'Pertemuan 2: Psikologi Warna & Color Harmony', 'summary' => '<h4>2. Teori Warna RGB vs CMYK</h4><p>Psikologi persepsi warna dalam membangun emosi dan identitas produk.</p>'],
            3 => ['name' => 'Pertemuan 3: Hirarki Tipografi & Grid System', 'summary' => '<h4>3. Anatomi Huruf & Tata Letak Layout</h4><p>Pemilihan font (Serif, Sans-serif, Script) dan penerapan Grid System pada media cetak/digital.</p>'],
            4 => ['name' => 'Pertemuan 4: Prinsip Persepsi Gestalt dalam Visual', 'summary' => '<h4>4. Proximity, Similarity, Continuity, & Closure</h4><p>Bagaimana otak manusia mengelompokkan elemen grafis menjadi satu kesatuan makna.</p>'],
            5 => ['name' => 'Pertemuan 5: Perancangan Logo & Brand Identity Guide', 'summary' => '<h4>5. Filosofi Logo & Graphic Standard Manual (GSM)</h4><p>Merancang logo yang scalable, memorable, dan menyusun panduan penggunaan logo.</p>'],
            6 => ['name' => 'Pertemuan 6: UI Design System & Aplikasi Mobile/Web', 'summary' => '<h4>6. Visual Component UI dalam DKV</h4><p>Penerapan iconografi, tombol, dan konsistensi komponen visual pada aplikasi digital.</p>'],
            7 => ['name' => 'Pertemuan 7: Storytelling Visual & Desain Kampanye', 'summary' => '<h4>7. Narasi Visual & Poster Promosi</h4><p>Menyusun pesan persuasif melalui komunikasi media publikasi visual.</p>'],
            8 => ['name' => 'Pertemuan 8: Ujian Tengah Semester (UTS)', 'summary' => '<h4>8. UTS Portfolio Presentation Brand Guidelines</h4><p>Presentasi portofolio perancangan logo dan identitas merek usaha.</p>']
        ]
    ]
];

foreach ($courses_batch2 as $cdata) {
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
        $newcourse->coursedisplay = 0;
        $newcourse->startdate = time();
        $newcourse->visible = 1;
        $newcourse->enablecompletion = 1;

        $course = create_course($newcourse);
        mtrace("     ✨ [AUTO-CREATE BATCH 2] Course baru dibuat: {$cdata['fullname']}");
    } else {
        $course->fullname = $cdata['fullname'];
        $course->summary = $cdata['summary'];
        $course->summaryformat = FORMAT_HTML;
        $course->coursedisplay = 0;
        $course->numsections = 8;
        $course->enablecompletion = 1;
        $DB->update_record('course', $course);
        mtrace("     ℹ️ Course sudah ada, data diperbarui.");
    }

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
    }

    rebuild_course_cache($course->id, true);
}

// Enrol mahasiswa ke course batch 2
$enrolments = [
    '062201024' => ['HKM101', 'DES105'],
    '062201025' => ['HKM101', 'AKT102', 'DES105'],
    '062201026' => ['K3M301', 'FAR202', 'HKM101']
];

$plugin = enrol_get_plugin('manual');
$studentrole = $DB->get_record('role', ['shortname' => 'student']);

foreach ($enrolments as $username => $shortnames) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) continue;

    foreach ($shortnames as $shortname) {
        $course = $DB->get_record('course', ['shortname' => $shortname]);
        if (!$course) continue;

        $instances = enrol_get_instances($course->id, true);
        $manualinstance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }
        if (!$manualinstance) {
            $instance_id = $plugin->add_instance($course);
            $manualinstance = $DB->get_record('enrol', ['id' => $instance_id]);
        }

        if (!$DB->record_exists('user_enrolments', ['enrolid' => $manualinstance->id, 'userid' => $user->id])) {
            $plugin->enrol_user($manualinstance, $user->id, $studentrole->id);
            mtrace("     🎓 Enrolled {$user->username} into {$course->shortname}");
        }
    }
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 BATCH 2: 5 MATA KULIAH BARU BERHASIL DIBUAT DENGAN LENGKAP!");
mtrace("==================================================");
