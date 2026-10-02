<?php
namespace local_ush_pimpinan\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Query LMS. Akun universitas mengambil seluruh periode aktif.
 * Akun fakultas hanya kelas yang prodinya berada di id_fakultas miliknya.
 */
class repository {

    /**
     * @return array<int, array<string, mixed>>
     */
    public function courses(scope $scope): array {
        global $DB;

        $period = catalog::active_period();
        if (!$period) {
            return [];
        }

        $params = [
            'path' => $period->path,
            'pathlike' => $period->path . '/%',
            'demo' => $DB->sql_like_escape('DEMO') . '%',
            'democat' => 'CAT_DEMO_PRESENTASI',
        ];
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE c.id > 1
                AND c.visible = 1
                AND (cc.path = :path OR cc.path LIKE :pathlike)
                AND cc.idnumber <> :democat
                AND " . $DB->sql_like('c.shortname', ':demo', false, true, true),
            $params
        );

        $rows = [];
        foreach ($courses as $course) {
            $prodi = catalog::prodi_from_shortname($course->shortname);
            $faculty = catalog::faculty_of_prodi($prodi);
            if ($scope->facultycode !== null && $faculty !== $scope->facultycode) {
                continue;
            }
            if ($scope->is_university() === false && $faculty === null) {
                continue;
            }
            $rows[(int) $course->id] = [
                'id' => (int) $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'prodi' => $prodi,
                'faculty' => $faculty,
                'students' => [],
                'teachers' => [],
                'opened' => 0,
                'held' => 0,
                'taken' => 0,
                'present' => 0,
                'logs' => 0,
                'hasmaterial' => false,
                'hasgrade' => false,
            ];
        }
        if (!$rows) {
            return [];
        }

        $this->attach_people($rows);
        $this->attach_sessions($rows);
        $this->attach_attendance($rows);
        $this->attach_materials($rows);
        $this->attach_grades($rows);
        $this->attach_opened_sections($rows);
        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attach_people(array &$rows): void {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
        $params['clevel'] = CONTEXT_COURSE;

        $students = $DB->get_recordset_sql(
            "SELECT c.id AS courseid, ue.userid
               FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0 AND u.suspended = 0
               JOIN {context} ctx ON ctx.contextlevel = :clevel AND ctx.instanceid = c.id
               JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
               JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
              WHERE c.id $insql",
            $params
        );
        foreach ($students as $row) {
            $rows[(int) $row->courseid]['students'][(int) $row->userid] = true;
        }
        $students->close();

        $teachers = $DB->get_recordset_sql(
            "SELECT c.id AS courseid, u.id AS userid, u.firstname, u.lastname
               FROM {course} c
               JOIN {context} ctx ON ctx.contextlevel = :clevel AND ctx.instanceid = c.id
               JOIN {role_assignments} ra ON ra.contextid = ctx.id
               JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher')
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
              WHERE c.id $insql",
            $params
        );
        foreach ($teachers as $row) {
            $rows[(int) $row->courseid]['teachers'][(int) $row->userid] = trim($row->firstname . ' ' . $row->lastname);
        }
        $teachers->close();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attach_sessions(array &$rows): void {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
        $now = time();
        $params['nowheld'] = $now;
        $params['nowtaken'] = $now;
        $records = $DB->get_records_sql(
            "SELECT a.course AS courseid,
                    SUM(CASE WHEN s.sessdate > 0 AND s.sessdate <= :nowheld THEN 1 ELSE 0 END) AS held,
                    SUM(CASE WHEN s.sessdate > 0 AND s.sessdate <= :nowtaken AND s.lasttaken > 0 THEN 1 ELSE 0 END) AS taken
               FROM {attendance} a
               JOIN {attendance_sessions} s ON s.attendanceid = a.id
              WHERE a.course $insql
           GROUP BY a.course",
            $params
        );
        foreach ($records as $record) {
            $id = (int) $record->courseid;
            $rows[$id]['held'] = (int) $record->held;
            $rows[$id]['taken'] = (int) $record->taken;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attach_attendance(array &$rows): void {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
        $params['now'] = time();
        $records = $DB->get_records_sql(
            "SELECT a.course AS courseid,
                    COUNT(l.id) AS logs,
                    SUM(CASE WHEN st.grade > 0 THEN 1 ELSE 0 END) AS present
               FROM {attendance} a
               JOIN {attendance_sessions} s ON s.attendanceid = a.id
                    AND s.sessdate > 0 AND s.sessdate <= :now
               JOIN {attendance_log} l ON l.sessionid = s.id
               JOIN {attendance_statuses} st ON st.id = l.statusid
              WHERE a.course $insql
           GROUP BY a.course",
            $params
        );
        foreach ($records as $record) {
            $id = (int) $record->courseid;
            $rows[$id]['logs'] = (int) $record->logs;
            $rows[$id]['present'] = (int) $record->present;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attach_materials(array &$rows): void {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
        list($modsql, $modparams) = $DB->get_in_or_equal(['resource', 'folder', 'page', 'url', 'book'], SQL_PARAMS_NAMED, 'mod');
        $records = $DB->get_records_sql(
            "SELECT cm.course AS courseid, COUNT(cm.id) AS n
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course $insql
                AND cm.deletioninprogress = 0
                AND m.name $modsql
           GROUP BY cm.course",
            $params + $modparams
        );
        foreach ($records as $record) {
            if ((int) $record->n > 0) {
                $rows[(int) $record->courseid]['hasmaterial'] = true;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attach_grades(array &$rows): void {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql(
            "SELECT gi.courseid, COUNT(gg.id) AS n
               FROM {grade_items} gi
               JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
              WHERE gi.itemtype = 'mod'
                AND gi.courseid $insql
           GROUP BY gi.courseid",
            $params
        );
        foreach ($records as $record) {
            if ((int) $record->n > 0) {
                $rows[(int) $record->courseid]['hasgrade'] = true;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function attach_opened_sections(array &$rows): void {
        global $DB;

        list($insql, $params) = $DB->get_in_or_equal(array_keys($rows), SQL_PARAMS_NAMED);
        $now = time();
        $sections = $DB->get_recordset_sql(
            "SELECT id, course, availability
               FROM {course_sections}
              WHERE course $insql
                AND section >= 1
                AND section <= :maxsection",
            $params + ['maxsection' => catalog::TARGET_MEETINGS]
        );
        foreach ($sections as $section) {
            if ($this->section_is_open($section->availability, $now)) {
                $rows[(int) $section->course]['opened']++;
            }
        }
        $sections->close();
    }

    private function section_is_open(?string $availability, int $now): bool {
        if ($availability === null || $availability === '') {
            return false;
        }
        $data = json_decode($availability, true);
        if (!is_array($data) || empty($data['c']) || !is_array($data['c'])) {
            return false;
        }
        $found = false;
        foreach ($data['c'] as $condition) {
            if (($condition['type'] ?? '') !== 'date') {
                continue;
            }
            $found = true;
            $time = (int) ($condition['t'] ?? 0);
            $direction = (string) ($condition['d'] ?? '>=');
            if ($direction === '>=' && $now < $time) {
                return false;
            }
            if ($direction === '<' && $now >= $time) {
                return false;
            }
        }
        return $found;
    }
}
