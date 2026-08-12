<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG WIDGET DOSEN PENGAMPU DI KELAS MOODLE");
mtrace("==================================================");

// Fetch current additionalhtmltopofbody
$current_html = get_config('core', 'additionalhtmltopofbody');

// Add lecturer profile auto-detector script for course view pages
$lecturer_script = <<<HTML
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Inject Instructor Badge on course view pages
    const isCourseView = window.location.pathname.includes('/course/view.php');
    if (isCourseView) {
      const courseId = new URLSearchParams(window.location.search).get('id');
      if (courseId && !document.getElementById('ushInstructorBadge')) {
        // Fetch course teacher via Moodle DOM or API
        const teachers = Array.from(document.querySelectorAll('.teachers li, .teacher, .instructor-name'))
                              .map(el => el.textContent.trim())
                              .filter(Boolean);
        
        const targetHeader = document.querySelector('.page-header-headings') || document.querySelector('#page-header') || document.querySelector('.course-content');
        
        if (targetHeader && teachers.length > 0) {
          const teacherNames = teachers.join(', ');
          const badgeHTML = `
            <div id="ushInstructorBadge" style="background: rgba(0, 43, 127, 0.06); border-left: 4px solid #002B7F; padding: 10px 16px; border-radius: 0 10px 10px 0; margin: 12px 0; display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: #002B7F; font-family: sans-serif;">
              <span style="font-size: 18px;">👨‍🏫</span>
              <div>
                <strong style="font-weight: 700;">Dosen Pengampu Resmi:</strong> ${teacherNames}
                <span style="display: block; font-size: 11.5px; color: #475569; margin-top: 2px;">Terverifikasi dari Sistem Informasi Akademik (SIAKAD) Universitas Sugeng Hartono</span>
              </div>
            </div>
          `;
          targetHeader.insertAdjacentHTML('afterend', badgeHTML);
        }
      }
    }
  });
</script>
HTML;

if (strpos($current_html, 'ushInstructorBadge') === false) {
    set_config('additionalhtmltopofbody', $current_html . "\n" . $lecturer_script);
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("✅ Widget Dosen Pengampu berhasil terpasang!");
mtrace("==================================================");
