<?php
namespace block_ush_kaprodi_dosen\local;

defined('MOODLE_INTERNAL') || die();

use context_course;
use moodle_url;
use renderer_base;

/**
 * Kaprodi: pantau progres mahasiswa per aktivitas (sudah / belum mengerjakan).
 */
class monitor {

    /**
     * Dasbor: daftar MK ringkas + yang banyak belum mengerjakan.
     */
    public function export_dashboard(?renderer_base $output = null): array {
        global $USER, $OUTPUT;

        $output = $output ?? $OUTPUT;
        $view = new view();
        $categories = $view->kaprodi_categories((int) $USER->id);
        if (!$categories) {
            return ['iskaprodi' => false];
        }

        $courses = $this->course_summaries($categories);
        $dosen = $view->export($output)['dosen'] ?? [];
        $labels = [];
        foreach ($categories as $category) {
            $labels[] = format_string($category->name);
        }

        $attention = array_values(array_filter($courses, static function ($c) {
            return !empty($c['haspending']);
        }));

        return [
            'iskaprodi' => true,
            'title' => get_string('monitortitle', 'block_ush_kaprodi_dosen'),
            'description' => get_string('monitordesc', 'block_ush_kaprodi_dosen'),
            'prodilabel' => implode(', ', array_unique($labels)),
            'summary' => [
                'total' => count($courses),
                'pendingcourses' => count($attention),
                'totallabel' => get_string('summarytotal', 'block_ush_kaprodi_dosen', count($courses)),
                'pendinglabel' => get_string('summarypending', 'block_ush_kaprodi_dosen', count($attention)),
            ],
            'hascourses' => !empty($attention),
            'courses' => array_slice($attention, 0, 10),
            'allok' => empty($attention) && !empty($courses),
            'alloklabel' => get_string('allok', 'block_ush_kaprodi_dosen'),
            'monitorurl' => (new moodle_url('/blocks/ush_kaprodi_dosen/monitor.php'))->out(false),
            'monitorlabel' => get_string('viewallmonitor', 'block_ush_kaprodi_dosen'),
            'nocourses' => get_string('nocourses', 'block_ush_kaprodi_dosen'),
            'dosen' => $dosen,
            'hasdosen' => !empty($dosen),
            'dosentitle' => get_string('title', 'block_ush_kaprodi_dosen'),
            'dosendesc' => get_string('description', 'block_ush_kaprodi_dosen'),
            'count' => count($dosen),
        ];
    }

    /**
     * Halaman daftar semua MK, atau detail satu MK (?courseid=).
     */
    public function export_page(?int $courseid = null): array {
        global $USER;

        $view = new view();
        $categories = $view->kaprodi_categories((int) $USER->id);
        if (!$categories) {
            return ['iskaprodi' => false];
        }

        $labels = [];
        foreach ($categories as $category) {
            $labels[] = format_string($category->name);
        }

        if ($courseid) {
            if (!$this->course_in_categories($courseid, $categories)) {
                return [
                    'iskaprodi' => true,
                    'forbidden' => true,
                    'message' => get_string('courseforbidden', 'block_ush_kaprodi_dosen'),
                    'backurl' => (new moodle_url('/blocks/ush_kaprodi_dosen/monitor.php'))->out(false),
                    'backlabel' => get_string('backtolist', 'block_ush_kaprodi_dosen'),
                ];
            }
            $detail = $this->course_detail($courseid);
            return [
                'iskaprodi' => true,
                'isdetail' => true,
                'title' => get_string('coursemonitortitle', 'block_ush_kaprodi_dosen'),
                'description' => get_string('coursemonitordesc', 'block_ush_kaprodi_dosen'),
                'prodilabel' => implode(', ', array_unique($labels)),
                'course' => $detail,
                'backurl' => (new moodle_url('/blocks/ush_kaprodi_dosen/monitor.php'))->out(false),
                'backlabel' => get_string('backtolist', 'block_ush_kaprodi_dosen'),
            ];
        }

        $courses = $this->course_summaries($categories);
        return [
            'iskaprodi' => true,
            'isdetail' => false,
            'title' => get_string('monitortitle', 'block_ush_kaprodi_dosen'),
            'description' => get_string('monitordesc', 'block_ush_kaprodi_dosen'),
            'prodilabel' => implode(', ', array_unique($labels)),
            'hascourses' => !empty($courses),
            'courses' => $courses,
            'nocourses' => get_string('nocourses', 'block_ush_kaprodi_dosen'),
            'backurl' => (new moodle_url('/my/'))->out(false),
            'backlabel' => get_string('backtodashboard', 'block_ush_kaprodi_dosen'),
        ];
    }

    /**
     * @param \stdClass[] $categories
     * @return array[]
     */
    private function course_summaries(array $categories): array {
        global $DB;

        $catids = $this->category_tree_ids(array_map(static function ($c) {
            return (int) $c->id;
        }, $categories));
        if (!$catids) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cat');
        $params['siteid'] = SITEID;
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.shortname, c.fullname
               FROM {course} c
              WHERE c.id <> :siteid AND c.visible = 1 AND c.category {$insql}
           ORDER BY c.fullname",
            $params
        );

        $rows = [];
        foreach ($courses as $course) {
            $detail = $this->course_detail((int) $course->id);
            $pendingacts = 0;
            $totalpending = 0;
            $lines = [];
            foreach ($detail['activities'] as $act) {
                if ($act['pending'] > 0) {
                    $pendingacts++;
                    $totalpending += $act['pending'];
                    $lines[] = $act['name'] . ': ' . $act['done'] . ' sudah / ' . $act['pending'] . ' belum';
                }
            }
            $rows[] = [
                'courseid' => (int) $course->id,
                'shortname' => $course->shortname,
                'fullname' => format_string($course->fullname),
                'teachers' => $detail['teachers'],
                'students' => $detail['students'],
                'studentslabel' => get_string('nstudents', 'block_ush_kaprodi_dosen', $detail['students']),
                'haspending' => $pendingacts > 0,
                'pendingacts' => $pendingacts,
                'totalpending' => $totalpending,
                'summaryline' => $lines ? implode(' · ', array_slice($lines, 0, 3)) : get_string('nopending', 'block_ush_kaprodi_dosen'),
                'detailurl' => (new moodle_url('/blocks/ush_kaprodi_dosen/monitor.php', ['courseid' => $course->id]))->out(false),
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'detaillabel' => get_string('viewdetail', 'block_ush_kaprodi_dosen'),
            ];
        }

        usort($rows, static function ($a, $b) {
            if ($a['haspending'] !== $b['haspending']) {
                return $a['haspending'] ? -1 : 1;
            }
            return $b['totalpending'] <=> $a['totalpending'];
        });

        return $rows;
    }

    /**
     * Detail satu kelas: tiap aktivitas + sudah/belum.
     */
    private function course_detail(int $courseid): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $ctx = context_course::instance($courseid);
        $studentids = $this->student_ids($ctx->id);
        $students = count($studentids);

        $teachers = $this->course_teachers($courseid);
        $teachernames = [];
        foreach ($teachers as $t) {
            $teachernames[] = fullname($t);
        }

        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.instance, m.name AS modname
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :course AND cm.deletioninprogress = 0
                AND m.name IN ('assign', 'quiz', 'attendance')
           ORDER BY cm.idnumber, cm.id",
            ['course' => $courseid]
        );

        $activities = [];
        foreach ($cms as $cm) {
            $name = (string) $DB->get_field($cm->modname, 'name', ['id' => $cm->instance]);
            if ($cm->modname === 'assign') {
                $stats = $this->assign_stats((int) $cm->instance, $studentids);
                $type = get_string('typeassign', 'block_ush_kaprodi_dosen');
            } else if ($cm->modname === 'quiz') {
                $stats = $this->quiz_stats((int) $cm->instance, $studentids);
                $type = get_string('typequiz', 'block_ush_kaprodi_dosen');
            } else {
                $stats = $this->attendance_stats($courseid, $studentids);
                $type = get_string('typeattendance', 'block_ush_kaprodi_dosen');
            }

            $done = $stats['done'];
            $pending = max(0, $students - $done);
            $pct = $students ? (int) round(($done / $students) * 100) : 0;
            $activities[] = [
                'cmid' => (int) $cm->id,
                'name' => format_string($name),
                'type' => $type,
                'modname' => $cm->modname,
                'typekey' => $cm->modname, // assign|quiz|attendance — untuk filter chip.
                'done' => $done,
                'pending' => $pending,
                'students' => $students,
                'donelabel' => get_string('ndone', 'block_ush_kaprodi_dosen', $done),
                'pendinglabel' => get_string('npending', 'block_ush_kaprodi_dosen', $pending),
                'pct' => $pct,
                'pctlabel' => $pct . '%',
                'haspending' => $pending > 0,
                'pendingflag' => $pending > 0 ? '1' : '0',
                'activityurl' => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
                'pendingusers' => $stats['pendingusers'],
                'haspendingusers' => !empty($stats['pendingusers']),
            ];
        }

        return [
            'id' => $courseid,
            'shortname' => $course->shortname,
            'fullname' => format_string($course->fullname),
            'teachers' => $teachernames ? implode(', ', $teachernames) : get_string('noteacher', 'block_ush_kaprodi_dosen'),
            'students' => $students,
            'studentslabel' => get_string('nstudents', 'block_ush_kaprodi_dosen', $students),
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
            'participantsurl' => (new moodle_url('/user/index.php', ['id' => $courseid]))->out(false),
            'activities' => $activities,
            'hasactivities' => !empty($activities),
            'noactivities' => get_string('noactivities', 'block_ush_kaprodi_dosen'),
        ];
    }

    /**
     * @param int[] $studentids
     * @return array{done:int, pendingusers:array}
     */
    private function assign_stats(int $assignid, array $studentids): array {
        global $DB;

        if (!$studentids) {
            return ['done' => 0, 'pendingusers' => []];
        }

        list($insql, $params) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'u');
        $params['assignid'] = $assignid;
        $doneids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid
               FROM {assign_submission}
              WHERE assignment = :assignid
                AND latest = 1
                AND status = 'submitted'
                AND userid {$insql}",
            $params
        );
        $doneids = array_map('intval', $doneids);
        $pending = array_values(array_diff($studentids, $doneids));

        return [
            'done' => count($doneids),
            'pendingusers' => $this->user_labels($pending, 8),
        ];
    }

    /**
     * @param int[] $studentids
     * @return array{done:int, pendingusers:array}
     */
    private function quiz_stats(int $quizid, array $studentids): array {
        global $DB;

        if (!$studentids) {
            return ['done' => 0, 'pendingusers' => []];
        }

        list($insql, $params) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'u');
        $params['quizid'] = $quizid;
        // Finished attempt counts as done.
        $doneids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid
               FROM {quiz_attempts}
              WHERE quiz = :quizid
                AND state = 'finished'
                AND userid {$insql}",
            $params
        );
        $doneids = array_map('intval', $doneids);
        $pending = array_values(array_diff($studentids, $doneids));

        return [
            'done' => count($doneids),
            'pendingusers' => $this->user_labels($pending, 8),
        ];
    }

    /**
     * Absensi: mahasiswa yang hadir di ≥1 sesi yang sudah diambil.
     *
     * @param int[] $studentids
     * @return array{done:int, pendingusers:array}
     */
    private function attendance_stats(int $courseid, array $studentids): array {
        global $DB;

        if (!$studentids || !$DB->get_manager()->table_exists('attendance_log')) {
            return ['done' => 0, 'pendingusers' => $this->user_labels($studentids, 8)];
        }

        $taken = (int) $DB->count_records_sql(
            "SELECT COUNT(s.id)
               FROM {attendance_sessions} s
               JOIN {attendance} a ON a.id = s.attendanceid
              WHERE a.course = :course AND s.lasttaken IS NOT NULL AND s.lasttaken > 0",
            ['course' => $courseid]
        );
        if ($taken < 1) {
            // Belum ada sesi diambil: semua dianggap belum.
            return ['done' => 0, 'pendingusers' => $this->user_labels($studentids, 8)];
        }

        list($insql, $params) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'u');
        $params['course'] = $courseid;
        // Present-ish = status grade > 0 on at least half of taken sessions, or any present?
        // For "sudah absen": marked in the latest taken session with any status.
        $doneids = $DB->get_fieldset_sql(
            "SELECT DISTINCT l.studentid
               FROM {attendance_log} l
               JOIN {attendance_sessions} s ON s.id = l.sessionid
               JOIN {attendance} a ON a.id = s.attendanceid
              WHERE a.course = :course
                AND s.lasttaken IS NOT NULL AND s.lasttaken > 0
                AND l.studentid {$insql}",
            $params
        );
        $doneids = array_map('intval', $doneids);
        $pending = array_values(array_diff($studentids, $doneids));

        return [
            'done' => count($doneids),
            'pendingusers' => $this->user_labels($pending, 8),
        ];
    }

    /**
     * @param int[] $userids
     * @return array[]
     */
    private function user_labels(array $userids, int $limit = 8): array {
        global $DB;

        if (!$userids) {
            return [];
        }
        $slice = array_slice($userids, 0, $limit);
        list($insql, $params) = $DB->get_in_or_equal($slice, SQL_PARAMS_NAMED, 'u');
        $users = $DB->get_records_sql(
            "SELECT id, firstname, lastname, email,
                    firstnamephonetic, lastnamephonetic, middlename, alternatename
               FROM {user}
              WHERE id {$insql}
           ORDER BY lastname, firstname",
            $params
        );
        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'fullname' => fullname($u),
                'profileurl' => (new moodle_url('/user/profile.php', ['id' => $u->id]))->out(false),
            ];
        }
        $more = count($userids) - count($out);
        if ($more > 0) {
            $out[] = [
                'fullname' => get_string('andmore', 'block_ush_kaprodi_dosen', $more),
                'profileurl' => '',
            ];
        }
        $n = count($out);
        foreach ($out as $i => &$row) {
            $row['last'] = ($i === $n - 1);
        }
        unset($row);
        return $out;
    }

    /**
     * @return int[]
     */
    private function student_ids(int $contextid): array {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        if (!$roleid) {
            return [];
        }
        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE ra.contextid = :ctx AND ra.roleid = :roleid",
            ['ctx' => $contextid, 'roleid' => $roleid]
        );
        return array_map('intval', $ids);
    }

    /**
     * @return \stdClass[]
     */
    private function course_teachers(int $courseid): array {
        global $DB;

        $ctx = context_course::instance($courseid);
        return $DB->get_records_sql(
            "SELECT DISTINCT u.id, u.firstname, u.lastname, u.email,
                    u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE ra.contextid = :ctxid
                AND r.shortname IN ('editingteacher', 'teacher')
           ORDER BY u.lastname, u.firstname",
            ['ctxid' => $ctx->id]
        );
    }

    /**
     * @param \stdClass[] $categories
     */
    private function course_in_categories(int $courseid, array $categories): bool {
        global $DB;

        $catids = $this->category_tree_ids(array_map(static function ($c) {
            return (int) $c->id;
        }, $categories));
        if (!$catids) {
            return false;
        }
        $course = $DB->get_record('course', ['id' => $courseid], 'id, category');
        return $course && in_array((int) $course->category, $catids, true);
    }

    /**
     * @param int[] $categoryids
     * @return int[]
     */
    private function category_tree_ids(array $categoryids): array {
        global $DB;

        $ids = [];
        foreach ($categoryids as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $ids[$id] = $id;
            $children = $DB->get_fieldset_sql(
                "SELECT id FROM {course_categories} WHERE path LIKE :pathpat OR path LIKE :pathend",
                [
                    'pathpat' => '%/' . $id . '/%',
                    'pathend' => '%/' . $id,
                ]
            );
            foreach ($children as $childid) {
                $ids[(int) $childid] = (int) $childid;
            }
        }
        return array_values($ids);
    }
}
