<?php
/**
 * Halaman Mahasiswa Bimbingan DPA
 * URL: /local/siakad_sync/dpa_mahasiswa.php
 * Hanya dapat diakses oleh Dosen/Teacher
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tablelib.php');

require_login();
$context = context_system::instance();

// Hanya Dosen / Admin yang boleh akses
if (!has_capability('moodle/course:viewhiddencourses', $context) &&
    !has_capability('moodle/site:viewparticipants', $context)) {
    redirect($CFG->wwwroot, get_string('nopermissions', 'error'));
}

$PAGE->set_url(new moodle_url('/local/siakad_sync/dpa_mahasiswa.php'));
$PAGE->set_context($context);
$PAGE->set_title('Mahasiswa Bimbingan DPA');
$PAGE->set_heading('Mahasiswa Bimbingan DPA');
$PAGE->set_pagelayout('base');

// Nama lengkap dosen yang sedang login
$dosenNama = fullname($USER);

// Cari mahasiswa yang DPA-nya = nama dosen yang login
// Data tersimpan via set_user_preference('siakad_dpa', $nama_dpa, $userid)
$sql = "
    SELECT u.id, u.username, u.firstname, u.lastname, u.email,
           u.lastaccess, u.suspended,
           p_nim.value    AS nim,
           p_prodi.value  AS prodi,
           p_angk.value   AS angkatan,
           p_dpa.value    AS dpa_name
    FROM {user} u
    JOIN {user_preferences} p_dpa  ON p_dpa.userid  = u.id AND p_dpa.name  = 'siakad_dpa'
    LEFT JOIN {user_preferences} p_nim   ON p_nim.userid   = u.id AND p_nim.name   = 'siakad_nim'
    LEFT JOIN {user_preferences} p_prodi ON p_prodi.userid = u.id AND p_prodi.name = 'siakad_prodi'
    LEFT JOIN {user_preferences} p_angk  ON p_angk.userid  = u.id AND p_angk.name  = 'siakad_angkatan'
    WHERE u.deleted = 0
      AND u.suspended = 0
      AND p_dpa.value = :dpaname
    ORDER BY p_prodi.value ASC, u.lastname ASC, u.firstname ASC
";

$mahasiswaList = $DB->get_records_sql($sql, ['dpaname' => $dosenNama]);

// Filter pencarian
$search = optional_param('search', '', PARAM_TEXT);
if (!empty($search)) {
    $mahasiswaList = array_filter($mahasiswaList, function($m) use ($search) {
        $fullname = strtolower($m->firstname . ' ' . $m->lastname);
        return strpos($fullname, strtolower($search)) !== false ||
               strpos(strtolower($m->nim ?? ''), strtolower($search)) !== false;
    });
}

echo $OUTPUT->header();
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
body, .dpa-wrap { font-family: 'Poppins', sans-serif; }

.dpa-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 10px 20px 40px;
}
.dpa-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0284c7 100%);
    border-radius: 16px;
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 28px;
    border-left: 6px solid #38bdf8;
}
.dpa-badge {
    background: #0284c7;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 14px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: inline-block;
    margin-bottom: 12px;
}
.dpa-header h1 { font-size: 22px; font-weight: 800; margin: 0 0 6px 0; }
.dpa-header p  { font-size: 13px; color: #cbd5e1; margin: 0; }

.stats-row {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.stat-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 24px;
    flex: 1;
    min-width: 140px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.stat-num  { font-size: 30px; font-weight: 800; color: #0284c7; }
.stat-lbl  { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600; }

.search-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
}
.search-bar input {
    flex: 1;
    padding: 10px 16px;
    border: 1px solid #cbd5e1;
    border-radius: 50px;
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    outline: none;
}
.search-bar button {
    padding: 10px 24px;
    background: linear-gradient(135deg, #0284c7, #38bdf8);
    color: #fff;
    border: none;
    border-radius: 50px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
}

.mhs-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
}
.mhs-table thead tr {
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: #fff;
}
.mhs-table th {
    padding: 13px 16px;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    letter-spacing: .5px;
    text-transform: uppercase;
}
.mhs-table td {
    padding: 12px 16px;
    font-size: 13px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.mhs-table tr:hover td { background: #f8fafc; }
.mhs-table tr:last-child td { border-bottom: none; }

.badge-prodi {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
.badge-active {
    background: #dcfce7;
    color: #16a34a;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
.avatar-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0284c7, #38bdf8);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    margin-right: 10px;
    vertical-align: middle;
}
.btn-lihat {
    padding: 5px 14px;
    background: #0284c7;
    color: #fff;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    display: inline-block;
    transition: background .2s;
}
.btn-lihat:hover { background: #0369a1; color: #fff; }
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}
.empty-state .icon { font-size: 56px; margin-bottom: 16px; }
.empty-state h3 { font-size: 18px; color: #475569; margin-bottom: 8px; }
</style>

<div class="dpa-wrap">
    <!-- Header -->
    <div class="dpa-header">
        <div class="dpa-badge">👨‍🏫 PORTAL DPA — MAHASISWA BIMBINGAN</div>
        <h1>Daftar Mahasiswa Bimbingan Akademik</h1>
        <p>Dosen Pembimbing Akademik (DPA): <strong><?php echo htmlspecialchars($dosenNama); ?></strong></p>
    </div>

    <!-- Stats -->
    <?php
    $totalMhs    = count($mahasiswaList);
    $prodiList   = array_unique(array_column((array)$mahasiswaList, 'prodi'));
    $angkatanList = array_unique(array_column((array)$mahasiswaList, 'angkatan'));
    ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num"><?php echo $totalMhs; ?></div>
            <div class="stat-lbl">Total Mahasiswa Bimbingan</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?php echo count($prodiList); ?></div>
            <div class="stat-lbl">Program Studi</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?php echo count($angkatanList); ?></div>
            <div class="stat-lbl">Angkatan</div>
        </div>
    </div>

    <!-- Search -->
    <form method="get" action="">
        <div class="search-bar">
            <input type="text" name="search" placeholder="🔍 Cari nama atau NIM mahasiswa..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Cari</button>
            <?php if (!empty($search)): ?>
                <a href="?" style="padding: 10px 20px; background: #e2e8f0; border-radius: 50px; font-size: 13px; color: #475569; text-decoration: none; font-weight: 600;">✕ Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <?php if (empty($mahasiswaList)): ?>
        <div class="empty-state">
            <div class="icon">👨‍🎓</div>
            <h3>Belum Ada Mahasiswa Bimbingan</h3>
            <p>Data akan muncul setelah Admin menjalankan sinkronisasi SIAKAD terbaru.<br>
               Pastikan nama DPA di SIAKAD sesuai dengan nama akun Anda di LMS.</p>
            <p style="margin-top:16px; font-size:13px; color:#94a3b8;">
                Nama akun Anda di LMS: <strong><?php echo htmlspecialchars($dosenNama); ?></strong>
            </p>
        </div>
    <?php else: ?>
        <table class="mhs-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Mahasiswa</th>
                    <th>NIM</th>
                    <th>Prodi</th>
                    <th>Angkatan</th>
                    <th>Email</th>
                    <th>Terakhir Aktif</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($mahasiswaList as $mhs): ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td>
                        <span class="avatar-circle"><?php echo strtoupper(substr($mhs->firstname, 0, 1) . substr($mhs->lastname, 0, 1)); ?></span>
                        <strong><?php echo htmlspecialchars(fullname($mhs)); ?></strong>
                    </td>
                    <td><code><?php echo htmlspecialchars($mhs->nim ?? $mhs->username); ?></code></td>
                    <td><span class="badge-prodi"><?php echo htmlspecialchars($mhs->prodi ?? '-'); ?></span></td>
                    <td><?php echo htmlspecialchars($mhs->angkatan ?? '-'); ?></td>
                    <td style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($mhs->email); ?></td>
                    <td style="font-size:11px; color:#64748b;">
                        <?php echo $mhs->lastaccess ? date('d/m/Y', $mhs->lastaccess) : '<span style="color:#ef4444;">Belum pernah login</span>'; ?>
                    </td>
                    <td>
                        <a class="btn-lihat" href="<?php echo $CFG->wwwroot; ?>/user/view.php?id=<?php echo $mhs->id; ?>&course=1" target="_blank">
                            👤 Lihat Profil
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="text-align:right; color:#94a3b8; font-size:12px; margin-top:12px;">
            Menampilkan <?php echo count($mahasiswaList); ?> dari <?php echo $totalMhs; ?> mahasiswa bimbingan.
        </p>
    <?php endif; ?>

    <div style="margin-top:24px;">
        <a href="<?php echo $CFG->wwwroot; ?>/my/" style="color:#0284c7; font-size:13px; font-weight:600; text-decoration:none;">← Kembali ke Dashboard</a>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>
