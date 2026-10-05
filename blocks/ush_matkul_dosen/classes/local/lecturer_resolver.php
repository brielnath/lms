<?php
namespace block_ush_matkul_dosen\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Matches SIAKAD lecturer names to LMS accounts.
 *
 * SIAKAD and LMS spell names with different titles and casing
 * ("Dr. Budi Purnomo, M.Hum." vs "Dr. | Budi Purnomo, M.Hum."), and LMS sometimes
 * abbreviates the last word ("NICKY GILANG W"), so names are compared without titles
 * and a unique abbreviated match is accepted. Falls back to the dosen_<id_lecture> username.
 */
class lecturer_resolver {
    private const PREFIX_TITLES = ['PROF', 'DR', 'DRS', 'DRA', 'IR', 'H', 'HJ', 'APT', 'NS'];

    /** @var array normalised name => userid[] */
    private $byname = [];
    /** @var array username => userid */
    private $byusername = [];
    /** @var array userid => username */
    private $usernames = [];
    /** @var array userid => true for users holding a teacher role somewhere */
    private $teachers = [];

    public function __construct() {
        global $DB, $CFG;

        // NIM usernames (06YYPP...) are students; everything else may be a lecturer.
        $users = $DB->get_recordset_select(
            'user',
            "deleted = 0 AND mnethostid = :mnet AND username <> 'guest' AND username " .
                $DB->sql_regex(false) . ' :nimre',
            ['mnet' => $CFG->mnet_localhost_id, 'nimre' => '^06[0-9]{6,}$'],
            '',
            'id, username, firstname, lastname'
        );
        foreach ($users as $u) {
            $this->byusername[$u->username] = (int) $u->id;
            $this->usernames[(int) $u->id] = $u->username;
            $norm = self::normalise($u->firstname . ' ' . $u->lastname);
            if ($norm !== '') {
                $this->byname[$norm][(int) $u->id] = (int) $u->id;
            }
        }
        $users->close();

        $teacherids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE r.shortname IN ('editingteacher', 'teacher')"
        );
        $this->teachers = array_fill_keys(array_map('intval', $teacherids), true);
    }

    public function resolve(int $lecid, string $name): int {
        $norm = self::normalise($name);
        if ($norm !== '' && isset($this->byname[$norm]) && ($id = $this->pick($this->byname[$norm]))) {
            return $id;
        }
        if ($norm !== '') {
            $hits = [];
            foreach ($this->byname as $candidate => $ids) {
                if (self::abbreviated_match($norm, $candidate) && ($id = $this->pick($ids))) {
                    $hits[$id] = true;
                }
            }
            if (count($hits) === 1) {
                return (int) array_key_first($hits);
            }

            $first = explode(' ', $norm)[0];
            $hits = [];
            foreach ($this->byname as $candidate => $ids) {
                if (self::is_placeholder_of($candidate, $first) && ($id = $this->pick($ids))) {
                    $hits[$id] = true;
                }
            }
            if (count($hits) === 1) {
                return (int) array_key_first($hits);
            }
        }
        return $this->byusername['dosen_' . $lecid] ?? 0;
    }

    /**
     * Choose one account among same-named users: Kaprodi also have a separate kaprodi.* login,
     * so prefer the account that actually teaches, then the personal (non-kaprodi) account.
     *
     * @param int[] $ids
     */
    private function pick(array $ids): int {
        if (count($ids) === 1) {
            return (int) reset($ids);
        }
        $teaching = array_filter($ids, fn($id) => isset($this->teachers[$id]));
        if (count($teaching) === 1) {
            return (int) reset($teaching);
        }
        $pool = $teaching ?: $ids;
        $personal = array_filter($pool, fn($id) => !str_starts_with($this->usernames[$id] ?? '', 'kaprodi.'));
        return count($personal) === 1 ? (int) reset($personal) : 0;
    }

    /**
     * Uppercase name without academic titles or punctuation.
     */
    public static function normalise(string $name): string {
        $name = html_entity_decode(strip_tags($name), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = explode(',', $name)[0];
        $name = \core_text::strtoupper($name);
        $name = preg_replace("/[^\\p{L}\\s']/u", ' ', $name) ?? $name;
        $name = str_replace("'", '', $name);
        $tokens = preg_split('/\s+/', trim($name)) ?: [];
        while ($tokens && in_array($tokens[0], self::PREFIX_TITLES, true) && count($tokens) > 2) {
            array_shift($tokens);
        }
        return implode(' ', $tokens);
    }

    /**
     * Shared lecturer accounts named like "YOSEPHINE DOSEN USH" or "MANDARIN USH LECTURER".
     */
    private static function is_placeholder_of(string $candidate, string $firstword): bool {
        $tokens = explode(' ', $candidate);
        if (array_shift($tokens) !== $firstword || !$tokens) {
            return false;
        }
        return !array_diff($tokens, ['DOSEN', 'USH', 'LECTURER']);
    }

    /**
     * Same number of words and each word of one is a prefix of the other's ("GILANG W" ~ "GILANG WICAKSONO").
     */
    private static function abbreviated_match(string $a, string $b): bool {
        $ta = explode(' ', $a);
        $tb = explode(' ', $b);
        if (count($ta) !== count($tb) || count($ta) < 2) {
            return false;
        }
        foreach ($ta as $i => $wa) {
            $wb = $tb[$i];
            if (!str_starts_with($wa, $wb) && !str_starts_with($wb, $wa)) {
                return false;
            }
        }
        return true;
    }
}
