<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MENGAKTIFKAN FITUR COMPLETION TRACKING (PROGRES BELAJAR)");
mtrace("==================================================");

// 1. Enable Site-wide Completion Tracking
set_config('enablecompletion', 1);
mtrace("✅ [SITE CONFIG] Completion Tracking tingkat situs berhasil DIAKTIFKAN!");

// 2. Enable Completion Tracking for all existing courses
$courses = $DB->get_records_select('course', 'id > 1');
$updated_courses = 0;

foreach ($courses as $course) {
    if ($course->enablecompletion != 1) {
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
        $updated_courses++;
    }
}
mtrace("✅ [COURSES] Completion Tracking diaktifkan pada {$updated_courses} mata kuliah!");

// 3. Enable Completion Tracking on course activities
$DB->execute("UPDATE {course_modules} SET completion = 1 WHERE completion = 0");
mtrace("✅ [ACTIVITIES] Indikator penyelesaian otomatis diaktifkan pada modul/aktivitas perkuliahan!");

mtrace("==================================================");
mtrace("🎉 COMPLETION TRACKING PROGRES BELAJAR SELESAI DIAKTIFKAN!");
mtrace("==================================================");
