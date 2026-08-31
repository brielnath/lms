<?php
namespace block_ush_calendar\local;

defined('MOODLE_INTERNAL') || die();

use moodle_url;

/**
 * Builds template data for the academic calendar dashboard block.
 */
class view {
    /** @var string[] Indonesian month names, 1-indexed. */
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function export(): array {

        $now = time();
        $year = optional_param('ushcalyear', (int) date('Y'), PARAM_INT);
        $month = optional_param('ushcalmonth', (int) date('n'), PARAM_INT);
        $day = optional_param('ushcalday', 0, PARAM_INT);
        $prodicode = optional_param('ushcalprodi', '', PARAM_ALPHANUMEXT);
        if (!array_key_exists($prodicode, prodi::list())) {
            $prodicode = '';
        }

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $monthstart = make_timestamp($year, $month, 1, 0, 0, 0);
        $monthend = make_timestamp($year, $month, (int) date('t', $monthstart), 23, 59, 59);

        $prev = strtotime('-1 month', $monthstart);
        $next = strtotime('+1 month', $monthstart);

        $baseurl = new moodle_url('/my/index.php');
        $params = function (array $extra) use ($year, $month, $prodicode): array {
            return array_merge([
                'ushcalyear' => $year,
                'ushcalmonth' => $month,
                'ushcalprodi' => $prodicode,
            ], $extra);
        };

        $categories = prodi::filter_options($prodicode);

        list($items, $eventdays) = $this->get_items($prodicode, $monthstart, $monthend, $day, $now);

        return [
            'title' => get_string('pagetitle', 'block_ush_calendar'),
            'description' => get_string('pagedesc', 'block_ush_calendar'),
            'choosecategory' => get_string('choosecategory', 'block_ush_calendar'),
            'formurl' => $baseurl->out(false),
            'year' => $year,
            'month' => $month,
            'prodicode' => $prodicode,
            'monthlabel' => self::MONTHS[$month] . ' ' . $year,
            'categories' => $categories,
            'hascategories' => !empty($categories),
            'hasitems' => !empty($items),
            'noitems' => get_string('noitems', 'block_ush_calendar'),
            'items' => $items,
            'prevurl' => (new moodle_url($baseurl, $params([
                'ushcalyear' => (int) date('Y', $prev),
                'ushcalmonth' => (int) date('n', $prev),
                'ushcalday' => 0,
            ])))->out(false),
            'nexturl' => (new moodle_url($baseurl, $params([
                'ushcalyear' => (int) date('Y', $next),
                'ushcalmonth' => (int) date('n', $next),
                'ushcalday' => 0,
            ])))->out(false),
            'weekdays' => [
                ['label' => 'SEN'], ['label' => 'SEL'], ['label' => 'RAB'],
                ['label' => 'KAM'], ['label' => 'JUM'], ['label' => 'SAB'], ['label' => 'MIN'],
            ],
            'weeks' => $this->build_weeks($year, $month, $day, $eventdays, $baseurl, $params),
        ];
    }

    /**
     * @return array{0: array, 1: array<int, bool>}
     */
    private function get_items(string $prodicode, int $monthstart, int $monthend, int $day, int $now): array {
        global $DB;

        $params = ['siteid' => SITEID];
        $where = 'c.visible = 1 AND c.id <> :siteid';
        if ($prodicode !== '') {
            $prodi = prodi::list()[$prodicode];
            $ors = [];
            foreach ($prodi['prefixes'] as $i => $prefix) {
                $key = 'pfx' . $i;
                $ors[] = $DB->sql_like('c.shortname', ':' . $key, false);
                $params[$key] = $DB->sql_like_escape($prefix) . '%';
            }
            foreach ($prodi['needles'] as $i => $needle) {
                $key = 'ndl' . $i;
                $ors[] = $DB->sql_like('cc.name', ':' . $key, false);
                $params[$key] = '%' . $DB->sql_like_escape($needle) . '%';
            }
            if ($ors) {
                $where .= ' AND (' . implode(' OR ', $ors) . ')';
            }
        }

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate, c.format, cc.name AS categoryname
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE {$where}
           ORDER BY c.startdate DESC, c.fullname ASC",
            $params,
            0,
            150
        );

        $enrolrows = [];
        if ($courses) {
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
            $enrols = $DB->get_records_sql(
                "SELECT courseid, MIN(NULLIF(enrolstartdate, 0)) AS enrolstart, MAX(enrolenddate) AS enrolend
                   FROM {enrol}
                  WHERE status = 0 AND courseid {$insql}
               GROUP BY courseid",
                $inparams
            );
            foreach ($enrols as $enrol) {
                $enrolrows[(int) $enrol->courseid] = $enrol;
            }
        }

        $items = [];
        $eventdays = [];
        foreach ($courses as $course) {
            $startdate = (int) $course->startdate;
            $enddate = (int) $course->enddate;
            $enrolstart = isset($enrolrows[$course->id]) ? (int) $enrolrows[$course->id]->enrolstart : 0;
            $enrolend = isset($enrolrows[$course->id]) ? (int) $enrolrows[$course->id]->enrolend : 0;

            $this->mark_day($eventdays, $startdate, $monthstart, $monthend);
            $this->mark_day($eventdays, $enddate, $monthstart, $monthend);
            $this->mark_day($eventdays, $enrolstart, $monthstart, $monthend);
            $this->mark_day($eventdays, $enrolend, $monthstart, $monthend);

            if ($day > 0) {
                $daystart = make_timestamp((int) date('Y', $monthstart), (int) date('n', $monthstart), $day, 0, 0, 0);
                $dayend = $daystart + DAYSECS - 1;
                $onthisday = $this->overlaps($startdate, $enddate ?: $startdate, $daystart, $dayend)
                    || $this->overlaps($enrolstart, $enrolend ?: $enrolstart, $daystart, $dayend);
                if (!$onthisday) {
                    continue;
                }
            }

            $prodicodeforcourse = prodi::detect($course->shortname, $course->categoryname);
            if ($prodicode !== '' && $prodicodeforcourse !== $prodicode) {
                continue;
            }
            $prodilabel = $prodicodeforcourse !== ''
                ? prodi::label($prodicodeforcourse)
                : format_string($course->categoryname);

            $tags = [
                ['label' => get_string('tagcourse', 'block_ush_calendar'), 'class' => 'is-type'],
            ];
            if ($enddate && $now > $enddate) {
                $tags[] = ['label' => get_string('statusdone', 'block_ush_calendar'), 'class' => 'is-done'];
            } else if ($startdate && $now >= $startdate && (!$enddate || $now <= $enddate)) {
                $tags[] = ['label' => get_string('statusongoing', 'block_ush_calendar'), 'class' => 'is-open'];
            } else if ($startdate && $now < $startdate) {
                $tags[] = ['label' => get_string('statusupcoming', 'block_ush_calendar'), 'class' => 'is-soon'];
            }
            if ($enrolend && $now > $enrolend) {
                $tags[] = ['label' => get_string('statusclosed', 'block_ush_calendar'), 'class' => 'is-closed'];
            }

            $items[] = [
                'title' => format_string($course->fullname),
                'provider' => $prodilabel,
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'formatlabel' => get_string('tagcourse', 'block_ush_calendar') . ' | LMS USH',
                'tags' => $tags,
                'registration' => $this->format_range($enrolstart ?: $startdate, $enrolend),
                'implementation' => $this->format_range($startdate, $enddate),
                'registrationlabel' => get_string('registration', 'block_ush_calendar'),
                'implementationlabel' => get_string('implementation', 'block_ush_calendar'),
            ];
        }

        return [$items, $eventdays];
    }

    private function mark_day(array &$eventdays, int $timestamp, int $monthstart, int $monthend): void {
        if ($timestamp >= $monthstart && $timestamp <= $monthend) {
            $eventdays[(int) date('j', $timestamp)] = true;
        }
    }

    private function overlaps(int $start, int $end, int $rangestart, int $rangeend): bool {
        if (!$start && !$end) {
            return false;
        }
        if (!$start) {
            $start = $end;
        }
        if (!$end) {
            $end = $start;
        }
        return $start <= $rangeend && $end >= $rangestart;
    }

    private function format_range(int $start, int $end): string {
        if (!$start && !$end) {
            return get_string('nodates', 'block_ush_calendar');
        }
        $fmt = 'd M Y';
        if ($start && $end && $end !== $start) {
            return userdate($start, $fmt) . ' - ' . userdate($end, $fmt);
        }
        return userdate($start ?: $end, $fmt);
    }

    private function build_weeks(
        int $year,
        int $month,
        int $selectedday,
        array $eventdays,
        moodle_url $baseurl,
        callable $params
    ): array {
        $firstwday = (int) date('N', make_timestamp($year, $month, 1)); // 1 = Mon.
        $daysinmonth = (int) date('t', make_timestamp($year, $month, 1));
        $today = (int) date('Y') === $year && (int) date('n') === $month ? (int) date('j') : 0;

        $weeks = [];
        $week = [];
        for ($i = 1; $i < $firstwday; $i++) {
            $week[] = ['empty' => true];
        }
        for ($d = 1; $d <= $daysinmonth; $d++) {
            $week[] = [
                'empty' => false,
                'day' => $d,
                'hastasks' => !empty($eventdays[$d]),
                'istoday' => ($d === $today),
                'isselected' => ($d === $selectedday),
                'url' => (new moodle_url($baseurl, $params(['ushcalday' => $d])))->out(false),
            ];
            if (count($week) === 7) {
                $weeks[] = ['days' => $week];
                $week = [];
            }
        }
        if ($week) {
            while (count($week) < 7) {
                $week[] = ['empty' => true];
            }
            $weeks[] = ['days' => $week];
        }
        return $weeks;
    }
}
