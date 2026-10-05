<?php
namespace block_ush_matkul_dosen\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_ush_matkul_dosen', [
            'userid' => 'privacy:metadata:block_ush_matkul_dosen:userid',
            'lecname' => 'privacy:metadata:block_ush_matkul_dosen:lecname',
            'code' => 'privacy:metadata:block_ush_matkul_dosen:code',
            'lessonname' => 'privacy:metadata:block_ush_matkul_dosen:lessonname',
        ], 'privacy:metadata:block_ush_matkul_dosen');
        $collection->add_external_location_link('siakad', [], 'privacy:metadata:siakad');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('block_ush_matkul_dosen', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context() instanceof context_system) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {block_ush_matkul_dosen} WHERE userid > 0', []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }
            $rows = $DB->get_records('block_ush_matkul_dosen', ['userid' => $userid], '', 'id, code, lessonname, tahun, periode');
            if ($rows) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_ush_matkul_dosen')],
                    (object) ['courses' => array_values($rows)]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context instanceof context_system) {
            $DB->delete_records('block_ush_matkul_dosen');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                $DB->delete_records('block_ush_matkul_dosen', ['userid' => $contextlist->get_user()->id]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$userlist->get_context() instanceof context_system || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids());
        $DB->delete_records_select('block_ush_matkul_dosen', "userid $insql", $params);
    }
}
