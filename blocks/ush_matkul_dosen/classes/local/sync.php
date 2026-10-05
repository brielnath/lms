<?php
namespace block_ush_matkul_dosen\local;

defined('MOODLE_INTERNAL') || die();

use moodle_exception;

/**
 * Pulls lecturer teaching assignments for the upcoming semester from SIAKAD
 * and hides inactive-semester courses from lecturers' "My courses".
 */
class sync {
    /** Final-project style activities that never get an LMS class. */
    private const SKIP_RE = '/(kkn|skripsi|kerja praktik|praktik kerja|kuliah kerja|seminar proposal)/i';

    /** User preference myoverview reads to drop a course from the "All" view. */
    private const MYOVERVIEW_HIDDEN = 'block_myoverview_hidden_course_';

    /** Marker so a course is auto-hidden only once and a lecturer can unhide it again. */
    private const AUTOHIDDEN = 'block_ush_matkul_dosen_autohidden_';

    /** @var callable */
    private $logger;

    public function __construct(?callable $logger = null) {
        $this->logger = $logger ?? static function (string $msg): void {
            mtrace($msg);
        };
    }

    private function log(string $msg): void {
        ($this->logger)($msg);
    }

    /**
     * Refresh the upcoming-semester table from SIAKAD.
     *
     * @return array{target: string, source: string, rows: int, matched: int}
     */
    public function sync_upcoming(): array {
        global $DB;

        $active = semester::active();
        $target = semester::next($active);
        $fallback = semester::previous_year($target);
        $this->log('Semester aktif: ' . $active['label'] . ', semester berikutnya: ' . $target['label']
            . ' (cadangan: ' . $fallback['label'] . ')');

        $client = new siakad_client();
        $client->login();

        $rows = [$target['tahun'] => [], $fallback['tahun'] => []];
        $page = 1;
        $lastpage = null;
        while (true) {
            [$http, $res] = $client->get('/grades-per-course?per_page=100&page=' . $page);
            if ($http !== 200 || !is_array($res)) {
                throw new moodle_exception('error_fetch', 'block_ush_matkul_dosen', '', "page $page HTTP $http");
            }
            $items = $res['data'] ?? [];
            foreach ($items as $student) {
                foreach ($student['grade'] ?? [] as $g) {
                    $this->collect($g, $target['periode'], $rows);
                }
            }
            $lastpage = (int) ($res['pagination']['last_page'] ?? $res['meta']['last_page'] ?? $res['last_page'] ?? 0);
            if ($page === 1 || $page % 25 === 0) {
                $this->log("  halaman $page" . ($lastpage ? " / $lastpage" : ''));
            }
            if (!$items || ($lastpage && $page >= $lastpage) || $page >= 500) {
                break;
            }
            $page++;
            usleep(250000);
        }

        $sourcetahun = !empty($rows[$target['tahun']]) ? $target['tahun'] : $fallback['tahun'];
        $assignments = $rows[$sourcetahun];
        if (!$assignments) {
            $this->log('Tidak ada data pengampu ' . $target['periode'] . ' di SIAKAD; tabel tidak diubah.');
            return ['target' => $target['label'], 'source' => '', 'rows' => 0, 'matched' => 0];
        }

        $resolver = new lecturer_resolver();
        $now = time();
        $records = [];
        $matched = [];
        foreach ($assignments as $a) {
            $userid = $resolver->resolve($a['lecid'], $a['lecname']);
            if ($userid) {
                $matched[$a['lecid']] = true;
            }
            $records[] = (object) [
                'userid' => $userid,
                'lecid' => $a['lecid'],
                'lecname' => \core_text::substr($a['lecname'], 0, 255),
                'code' => \core_text::substr($a['code'], 0, 50),
                'lessonname' => \core_text::substr($a['lessonname'], 0, 255),
                'tahun' => $sourcetahun,
                'periode' => $target['periode'],
                'timemodified' => $now,
            ];
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('block_ush_matkul_dosen');
        $DB->insert_records('block_ush_matkul_dosen', $records);
        $transaction->allow_commit();

        set_config('target', $target['suffix'], 'block_ush_matkul_dosen');
        set_config('sourcetahun', $sourcetahun, 'block_ush_matkul_dosen');
        set_config('lastsync', $now, 'block_ush_matkul_dosen');

        $lecturers = count(array_unique(array_column($assignments, 'lecid')));
        $this->log('Data ' . $target['periode'] . ' ' . $sourcetahun . ': ' . count($records) . ' pasangan MK-dosen, '
            . $lecturers . ' dosen, ' . count($matched) . ' cocok dengan akun LMS.');
        $unmatched = [];
        foreach ($assignments as $a) {
            if (empty($matched[$a['lecid']])) {
                $unmatched[$a['lecid']] = $a['lecname'];
            }
        }
        if ($unmatched) {
            $this->log('  Tanpa akun LMS: ' . implode('; ', array_slice($unmatched, 0, 15)));
        }

        $this->link_courses();

        return ['target' => $target['label'], 'source' => $sourcetahun, 'rows' => count($records), 'matched' => count($matched)];
    }

    /**
     * Point each assignment at its LMS course and make sure the lecturer can open it.
     *
     * Only courses outside the active semester are used (the original course with the bare code,
     * else the newest code_<semester> copy), so a past lecturer never gets editing rights on a class
     * someone else is teaching now. Enrolments are only added, never removed.
     *
     * @return int number of new enrolments
     */
    public function link_courses(): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $active = semester::active()['suffix'];
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $manual = enrol_get_plugin('manual');

        $bycode = [];
        $enrolled = 0;
        $linked = 0;
        foreach ($DB->get_records_select('block_ush_matkul_dosen', 'userid > 0') as $row) {
            if (!array_key_exists($row->code, $bycode)) {
                $bycode[$row->code] = $this->find_course($row->code, $active);
            }
            $courseid = $bycode[$row->code];
            if ((int) $row->courseid !== $courseid) {
                $DB->set_field('block_ush_matkul_dosen', 'courseid', $courseid, ['id' => $row->id]);
            }
            if (!$courseid) {
                continue;
            }
            $linked++;
            $context = \context_course::instance($courseid);
            if (is_enrolled($context, $row->userid) && user_has_role_assignment($row->userid, $roleid, $context->id)) {
                continue;
            }
            $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
            if (!$instance && $manual) {
                $instance = $DB->get_record('enrol', ['id' => $manual->add_instance(get_course($courseid))]);
            }
            if (!$instance) {
                continue;
            }
            // Silent enrolment: the welcome-email hook reads customint1 from the instance passed in.
            $silent = clone $instance;
            $silent->customint1 = ENROL_DO_NOT_SEND_EMAIL;
            $manual->enrol_user($silent, $row->userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
            $enrolled++;
        }

        $this->log("Kartu tertaut ke kursus LMS: $linked, dosen baru didaftarkan sebagai pengajar: $enrolled");
        return $enrolled;
    }

    private function find_course(string $code, string $activesuffix): int {
        global $DB;

        $exact = $DB->get_field('course', 'id', ['shortname' => $code]);
        if ($exact) {
            return (int) $exact;
        }
        $candidates = $DB->get_records_select(
            'course',
            $DB->sql_like('shortname', ':pattern'),
            ['pattern' => $DB->sql_like_escape($code) . '\_%'],
            'shortname DESC',
            'id, shortname'
        );
        foreach ($candidates as $c) {
            if (preg_match('/^' . preg_quote($code, '/') . '_(20\d{6}(Ganjil|Genap))$/i', $c->shortname, $m)
                    && strcasecmp($m[1], $activesuffix) !== 0) {
                return (int) $c->id;
            }
        }
        return 0;
    }

    /**
     * @param array $g one grade row from grades-per-course
     * @param string $periode Ganjil|Genap
     * @param array $rows tahun => "lecid|code" => assignment
     */
    private function collect(array $g, string $periode, array &$rows): void {
        if (strcasecmp(trim((string) ($g['periode'] ?? '')), $periode) !== 0) {
            return;
        }
        $tahun = preg_replace('/[^0-9]/', '', (string) ($g['tahun_akademik'] ?? '')) ?? '';
        $key = null;
        foreach (array_keys($rows) as $want) {
            if ($tahun !== '' && $tahun === preg_replace('/[^0-9]/', '', $want)) {
                $key = $want;
            }
        }
        if ($key === null) {
            return;
        }
        $lecid = (int) ($g['id_lecture'] ?? 0);
        $lecname = trim(strip_tags((string) ($g['lecture_name'] ?? '')));
        $code = strtoupper(str_replace(['*', ' '], '', trim((string) ($g['lesson_code'] ?? ''))));
        $lessonname = trim((string) ($g['lesson_name'] ?? ''));
        if ($lecid <= 0 || $code === '' || $lecname === '' || strcasecmp($lecname, 'Unknown') === 0) {
            return;
        }
        if (preg_match(self::SKIP_RE, $lessonname . ' ' . $code)) {
            return;
        }
        $rows[$key][$lecid . '|' . $code] = [
            'lecid' => $lecid,
            'lecname' => $lecname,
            'code' => $code,
            'lessonname' => $lessonname !== '' ? $lessonname : $code,
        ];
    }

    /**
     * Hide courses outside the active semester from each lecturer's "My courses" (once per course),
     * and bring back courses this task hid earlier once their semester becomes active.
     *
     * Only hidden courses and courses carrying another semester's suffix are touched,
     * so non-semester courses such as guides or trainings stay visible.
     *
     * @return int number of courses hidden
     */
    public function hide_inactive_courses(): int {
        global $DB;

        $active = semester::active();
        $rows = $DB->get_recordset_sql(
            "SELECT DISTINCT ra.userid, c.id AS courseid, c.shortname, c.visible
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('editingteacher', 'teacher')
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
               JOIN {course} c ON c.id = ctx.instanceid AND c.id <> :siteid
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0",
            ['ctxlevel' => CONTEXT_COURSE, 'siteid' => SITEID]
        );

        $hidden = 0;
        $restored = 0;
        foreach ($rows as $row) {
            $marker = self::AUTOHIDDEN . $row->courseid;
            $marked = $DB->record_exists('user_preferences', ['userid' => $row->userid, 'name' => $marker]);
            if (self::is_inactive_course($row->shortname, (int) $row->visible, $active['suffix'])) {
                if (!$marked) {
                    set_user_preference(self::MYOVERVIEW_HIDDEN . $row->courseid, 1, $row->userid);
                    set_user_preference($marker, 1, $row->userid);
                    $hidden++;
                }
            } else if ($marked) {
                unset_user_preference(self::MYOVERVIEW_HIDDEN . $row->courseid, $row->userid);
                unset_user_preference($marker, $row->userid);
                $restored++;
            }
        }
        $rows->close();

        $this->log("Kursus non-aktif disembunyikan dari 'Kursus saya': $hidden, dimunculkan kembali: $restored");
        return $hidden;
    }

    public static function is_inactive_course(string $shortname, int $visible, string $activesuffix): bool {
        if (preg_match('/_?(20\d{6}(Ganjil|Genap))$/i', $shortname, $m)) {
            return strcasecmp($m[1], $activesuffix) !== 0;
        }
        return $visible === 0;
    }
}
