<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 OVERRIDE STRINGS & TOMBOL ASSIGNMENT (DOSEN VS MAHASISWA)");
mtrace("==================================================");

// Create custom lang override directory
$custom_lang_dir = $CFG->dataroot . '/lang/id_local';
if (!is_dir($custom_lang_dir)) {
    mkdir($custom_lang_dir, 0777, true);
}

// Write custom assign.php strings
$custom_assign_strings = <<<PHP
<?php
\$string['viewgrades'] = '📥 Lihat & Nilai Semua Tugas Mahasiswa (View/Grade Submissions)';
\$string['viewsubmission'] = '📥 Lihat & Nilai Tugas Mahasiswa';
\$string['addsubmission'] = '📤 Tambahkan Pengajuan Tugas (Add Submission)';
\$string['editsubmission'] = '✏️ Ubah Pengajuan Tugas (Edit Submission)';
\$string['grademodule'] = '📊 Nilai Tugas Mahasiswa';
PHP;

file_put_contents($custom_lang_dir . '/assign.php', $custom_assign_strings);

// En lang override directory
$custom_en_dir = $CFG->dataroot . '/lang/en_local';
if (!is_dir($custom_en_dir)) {
    mkdir($custom_en_dir, 0777, true);
}

$custom_en_strings = <<<PHP
<?php
\$string['viewgrades'] = '📥 View & Grade All Student Submissions';
\$string['viewsubmission'] = '📥 View & Grade Submissions';
\$string['addsubmission'] = '📤 Add Submission';
\$string['editsubmission'] = '✏️ Edit Submission';
\$string['grademodule'] = '📊 Grade Student Submissions';
PHP;

file_put_contents($custom_en_dir . '/assign.php', $custom_en_strings);

// Add custom CSS to make Assignment buttons stand out
$assignment_btn_css_js = <<<HTML
<!-- ASSIGNMENT BUTTON ENHANCEMENT (DOSEN VS MAHASISWA) -->
<style>
  /* Button Dosen: Lihat & Nilai Tugas */
  .path-mod-assign a.btn-primary[href*="action=grading"],
  .path-mod-assign a.btn[href*="action=grading"],
  .path-mod-assign .gradelayout a.btn-primary {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    padding: 12px 24px !important;
    border-radius: 30px !important;
    box-shadow: 0 4px 15px rgba(2, 132, 199, 0.3) !important;
    font-size: 14px !important;
    transition: all 0.2s ease !important;
  }

  .path-mod-assign a.btn-primary[href*="action=grading"]:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.45) !important;
  }

  /* Button Mahasiswa: Tambahkan Pengajuan Tugas */
  .path-mod-assign form.submitform button[type="submit"],
  .path-mod-assign a.btn-primary[href*="action=editsubmission"] {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    padding: 12px 24px !important;
    border-radius: 30px !important;
    box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3) !important;
    font-size: 14px !important;
    transition: all 0.2s ease !important;
  }

  .path-mod-assign form.submitform button[type="submit"]:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(22, 163, 74, 0.45) !important;
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('/mod/assign/view.php')) {
      const isTeacher = document.body.classList.contains('role-teacher') || 
                        document.body.classList.contains('role-editingteacher') ||
                        document.querySelector('.userbutton .username')?.textContent.includes('Dosen') ||
                        document.querySelector('.userbutton .username')?.textContent.includes('S.Kom') ||
                        document.querySelector('.userbutton .username')?.textContent.includes('S.E');
      
      const gradeBtn = document.querySelector('a[href*="action=grading"]');
      if (gradeBtn) {
        gradeBtn.innerHTML = '📥 <strong>Lihat & Nilai Semua Tugas Mahasiswa (View/Grade Submissions)</strong>';
      }

      const submitBtn = document.querySelector('a[href*="action=editsubmission"], form.submitform button');
      if (submitBtn && !isTeacher) {
        submitBtn.innerHTML = '📤 <strong>Tambahkan Pengajuan Tugas (Add Submission)</strong>';
      }
    }
  });
</script>
<!-- END ASSIGNMENT BUTTON ENHANCEMENT -->
HTML;

$current_html = get_config('core', 'additionalhtmltopofbody');
if (strpos($current_html, 'ASSIGNMENT BUTTON ENHANCEMENT') === false) {
    set_config('additionalhtmltopofbody', $current_html . "\n" . $assignment_btn_css_js);
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("✅ Tombol & String Assignment (Dosen & Mahasiswa) Berhasil Diperbarui!");
mtrace("==================================================");
