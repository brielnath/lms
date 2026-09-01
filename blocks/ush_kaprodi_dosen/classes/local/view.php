<?php
namespace block_ush_kaprodi_dosen\local;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderer_base;

/**
 * Kaprodi dashboard: lecturers teaching in the managed study program.
 */
class view {

    public function export(?renderer_base $output = null): array {
        global $USER, $OUTPUT;

        $output = $output ?? $OUTPUT;
        $categories = $this->kaprodi_categories((int) $USER->id);
        if (!$categories) {
            return ['iskaprodi' => false];
        }

        $labels = [];
        $catids = [];
        $prefixes = [];
        foreach ($categories as $category) {
            $catids[] = (int) $category->id;
            $labels[] = format_string($category->name);
            foreach ($this->prefixes_for_category($category->name) as $prefix) {
                $prefixes[$prefix] = $prefix;
            }
        }

        $lecturers = $this->lecturers($catids, array_values($prefixes), $output);

        return [
            'iskaprodi' => true,
            'title' => get_string('title', 'block_ush_kaprodi_dosen'),
            'description' => get_string('description', 'block_ush_kaprodi_dosen'),
            'prodilabel' => implode(', ', array_unique($labels)),
            'count' => count($lecturers),
            'hasdosen' => !empty($lecturers),
            'nodosen' => get_string('nodosen', 'block_ush_kaprodi_dosen'),
            'dosen' => $lecturers,
        ];
    }

    /**
     * Course categories where the user has the manager role (Kaprodi).
     *
     * @return \stdClass[]
     */
    public function kaprodi_categories(int $userid): array {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        if (!$roleid) {
            return [];
        }

        return $DB->get_records_sql(
            "SELECT cc.*
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxlevel
               JOIN {course_categories} cc ON cc.id = ctx.instanceid
              WHERE ra.userid = :userid AND ra.roleid = :roleid
           ORDER BY cc.sortorder",
            [
                'ctxlevel' => CONTEXT_COURSECAT,
                'userid' => $userid,
                'roleid' => $roleid,
            ]
        );
    }

    /**
     * @param int[] $categoryids
     * @param string[] $prefixes
     * @return array
     */
    private function lecturers(array $categoryids, array $prefixes, renderer_base $output): array {
        global $DB;

        $allcatids = $this->category_tree_ids($categoryids);
        $wheres = [];
        $params = [
            'clevel' => CONTEXT_COURSE,
            'siteid' => SITEID,
        ];

        if ($allcatids) {
            list($insql, $inparams) = $DB->get_in_or_equal($allcatids, SQL_PARAMS_NAMED, 'cat');
            $wheres[] = "c.category {$insql}";
            $params = array_merge($params, $inparams);
        }

        $prefixors = [];
        foreach (array_values($prefixes) as $i => $prefix) {
            $key = 'pfx' . $i;
            $prefixors[] = $DB->sql_like('c.shortname', ':' . $key, false);
            $params[$key] = $DB->sql_like_escape($prefix) . '%';
        }
        if ($prefixors) {
            $wheres[] = '(' . implode(' OR ', $prefixors) . ')';
        }

        if (!$wheres) {
            return [];
        }

        $where = implode(' OR ', $wheres);
        $userfields = implode(', ', [
            'u.id', 'u.firstname', 'u.lastname', 'u.email', 'u.picture', 'u.imagealt',
            'u.firstnamephonetic', 'u.lastnamephonetic', 'u.middlename', 'u.alternatename',
        ]);

        $records = $DB->get_records_sql(
            "SELECT {$userfields}, COUNT(DISTINCT c.id) AS coursecount
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
               JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :clevel
               JOIN {course} c ON c.id = ctx.instanceid AND c.visible = 1 AND c.id <> :siteid
               JOIN {user} u ON u.id = ra.userid AND u.deleted = 0 AND u.suspended = 0
              WHERE r.shortname IN ('editingteacher', 'teacher')
                AND ({$where})
           GROUP BY {$userfields}
           ORDER BY u.lastname, u.firstname",
            $params
        );

        $items = [];
        foreach ($records as $user) {
            $items[] = [
                'fullname' => fullname($user),
                'email' => $user->email,
                'coursecount' => get_string('coursecount', 'block_ush_kaprodi_dosen', (int) $user->coursecount),
                'profileurl' => (new moodle_url('/user/profile.php', ['id' => $user->id]))->out(false),
                'picture' => $output->user_picture($user, ['size' => 48, 'link' => false]),
            ];
        }
        return $items;
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

    /**
     * @return string[]
     */
    private function prefixes_for_category(string $categoryname): array {
        if (class_exists('\\block_ush_calendar\\local\\prodi')) {
            foreach (\block_ush_calendar\local\prodi::list() as $info) {
                foreach ($info['needles'] as $needle) {
                    if ($needle !== '' && stripos($categoryname, $needle) !== false) {
                        return $info['prefixes'];
                    }
                }
            }
        }

        if (preg_match('/\(([A-Za-z]{2,4})\)/', $categoryname, $matches)) {
            return [strtoupper($matches[1])];
        }
        return [];
    }
}
