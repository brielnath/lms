<?php
namespace local_ush_pimpinan\local;

defined('MOODLE_INTERNAL') || die();

class dashboard {

    public function build(scope $scope): array {
        $period = catalog::active_period();
        $calendar = catalog::calendar_today($period);
        $courses = (new repository())->courses($scope);
        $groups = $this->group_courses($courses, $scope);

        $students = [];
        $teachers = [];
        $opened = 0;
        $target = 0;
        $present = 0;
        $logs = 0;
        foreach ($courses as $course) {
            foreach ($course['students'] as $userid => $unused) {
                $students[$userid] = true;
            }
            foreach ($course['teachers'] as $userid => $unused) {
                $teachers[$userid] = true;
            }
            $done = $this->meetings_done($course);
            $opened += $done;
            $target += catalog::TARGET_MEETINGS;
            $present += $course['present'];
            $logs += $course['logs'];
        }

        $faculty = $scope->facultycode ? catalog::faculty($scope->facultycode) : null;
        $realisasi = $this->percent($opened, $target);
        $hadir = $logs > 0 ? $this->percent($present, $logs) : null;

        $data = [
            'isuniv' => $scope->is_university(),
            'isfaculty' => !$scope->is_university(),
            'drilled' => $scope->drilled,
            'backurl' => (new \moodle_url('/local/ush_pimpinan/index.php'))->out(false),
            'title' => $scope->is_university()
                ? 'Universitas Sugeng Hartono'
                : ($faculty['name'] ?? 'Fakultas'),
            'scope' => $scope->is_university() ? 'Seluruh universitas' : 'Fakultas ini saja',
            'period' => $period ? format_string($period->name) : 'Periode belum ada',
            'calendarlabel' => $calendar['label'],
            'calendardetail' => $calendar['detail'],
            'calendartone' => $calendar['tone'],
            'students' => $this->number(count($students)),
            'teachers' => $this->number(count($teachers)),
            'classes' => $this->number(count($courses)),
            'headlinepercent' => $scope->is_university()
                ? $this->show_percent($realisasi)
                : $this->show_percent($hadir),
            'headlinelabel' => $scope->is_university()
                ? 'Rata-rata realisasi perkuliahan'
                : 'Kehadiran pada absensi yang sudah diisi',
            'groups' => $this->present_groups($groups, $scope),
            'hasgroups' => !empty($groups),
            'chartattendance' => $this->chart($groups, 'hadir', 'Kehadiran'),
            'chartprogress' => $this->chart($groups, 'realisasi', 'Realisasi pertemuan'),
            'chartstudents' => $this->chart_count($groups, 'students', 'Mahasiswa'),
            'chartclasses' => $this->chart_count($groups, 'classes', 'Kelas'),
            'chartmateri' => $this->chart($groups, 'materi', 'RPS & materi'),
            'classesrows' => $scope->is_university() ? [] : $this->class_rows($courses),
            'hasclasses' => !$scope->is_university() && !empty($courses),
            'target' => catalog::TARGET_MEETINGS,
            'note' => 'Angka diambil dari kelas LMS pada periode aktif. Fakultas dan prodi mengikuti id_fakultas di SIAKAD. Realisasi dihitung dari pertemuan yang tanggalnya sudah lewat, target ' . catalog::TARGET_MEETINGS . ' sesi. Kehadiran dihitung dari absensi yang sudah diisi.',
        ];
        return $data;
    }

    /**
     * @param array<int, array<string, mixed>> $courses
     * @return array<string, array<string, mixed>>
     */
    private function group_courses(array $courses, scope $scope): array {
        $groups = [];
        if ($scope->is_university()) {
            foreach (catalog::faculties() as $code => $faculty) {
                $groups[$code] = $this->empty_group($code, $faculty['name'], true);
            }
            $groups['LAIN'] = $this->empty_group('LAIN', 'Mata kuliah umum', false);
        } else {
            $faculty = catalog::faculty((string) $scope->facultycode);
            foreach ($faculty['prodi'] as $prodi) {
                $groups[$prodi] = $this->empty_group($prodi, catalog::prodi_label($prodi), true);
            }
            $groups[$scope->facultycode] = $this->empty_group(
                (string) $scope->facultycode,
                'Kelas tingkat fakultas',
                false
            );
        }

        foreach ($courses as $course) {
            $key = $scope->is_university()
                ? ($course['faculty'] ?? 'LAIN')
                : $course['prodi'];
            if (!isset($groups[$key])) {
                $groups[$key] = $this->empty_group($key, catalog::prodi_label($key), false);
            }
            $group = &$groups[$key];
            $group['classes']++;
            foreach ($course['students'] as $userid => $unused) {
                $group['studentids'][$userid] = true;
            }
            foreach ($course['teachers'] as $userid => $unused) {
                $group['teacherids'][$userid] = true;
            }
            $group['done'] += $this->meetings_done($course);
            $group['target'] += catalog::TARGET_MEETINGS;
            $group['taken'] += $course['taken'];
            $group['opened'] += $course['opened'];
            $group['present'] += $course['present'];
            $group['logs'] += $course['logs'];
            if ($course['hasmaterial']) {
                $group['withmaterial']++;
            }
            if ($course['hasgrade']) {
                $group['withgrade']++;
            }
            unset($group);
        }

        foreach ($groups as $key => $group) {
            if ($group['classes'] === 0 && empty($group['keepempty'])) {
                unset($groups[$key]);
            }
        }
        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    private function empty_group(string $code, string $name, bool $keepempty): array {
        return [
            'code' => $code,
            'name' => $name,
            'keepempty' => $keepempty,
            'classes' => 0,
            'studentids' => [],
            'teacherids' => [],
            'done' => 0,
            'target' => 0,
            'taken' => 0,
            'opened' => 0,
            'present' => 0,
            'logs' => 0,
            'withmaterial' => 0,
            'withgrade' => 0,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     * @return array<int, array<string, mixed>>
     */
    private function present_groups(array $groups, scope $scope): array {
        $rows = [];
        foreach ($groups as $group) {
            $students = count($group['studentids']);
            $teachers = count($group['teacherids']);
            $realisasi = $this->percent($group['done'], $group['target']);
            $jurnal = $group['opened'] > 0 ? $this->percent($group['taken'], $group['opened']) : null;
            $hadir = $group['logs'] > 0 ? $this->percent($group['present'], $group['logs']) : null;
            $materi = $group['classes'] > 0 ? $this->percent($group['withmaterial'], $group['classes']) : null;
            $nilai = $group['classes'] > 0 ? $this->percent($group['withgrade'], $group['classes']) : null;
            $badge = $this->badge($jurnal, $jurnal !== null);
            $url = '';
            if ($scope->is_university() && $group['code'] !== 'LAIN' && catalog::faculty($group['code'])) {
                $url = (new \moodle_url('/local/ush_pimpinan/index.php', ['fakultas' => $group['code']]))->out(false);
            }
            $rows[] = [
                'name' => $group['name'],
                'code' => $group['code'],
                'classes' => $this->number($group['classes']),
                'students' => $this->number($students),
                'teachers' => $this->number($teachers),
                'meetings' => $group['target'] > 0 ? $this->show_percent($realisasi) : '—',
                'jurnal' => $this->show_percent($jurnal),
                'hadir' => $this->show_percent($hadir),
                'materi' => $this->show_percent($materi),
                'nilai' => $this->show_percent($nilai),
                'realisasi' => $realisasi ?? 0,
                'hadirvalue' => $hadir ?? 0,
                'materivalue' => $materi ?? 0,
                'studentsvalue' => $students,
                'classesvalue' => $group['classes'],
                'statusclass' => $badge['class'],
                'statuslabel' => $badge['label'],
                'url' => $url,
                'hasurl' => $url !== '',
            ];
        }
        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function chart(array $groups, string $field, string $series): array {
        $bars = [];
        foreach ($this->present_groups($groups, new scope('univ', null, false, false)) as $row) {
            $value = match ($field) {
                'hadir' => $row['hadirvalue'],
                'materi' => $row['materivalue'],
                default => $row['realisasi'],
            };
            $bars[] = [
                'name' => $row['name'],
                'value' => (int) round($value),
                'label' => (int) round($value) . '%',
                'series' => $series,
            ];
        }
        return $bars;
    }

    /**
     * @param array<string, array<string, mixed>> $groups
     * @return array<int, array<string, mixed>>
     */
    private function chart_count(array $groups, string $field, string $series): array {
        $presented = $this->present_groups($groups, new scope('univ', null, false, false));
        $max = 1;
        foreach ($presented as $row) {
            $max = max($max, $field === 'students' ? $row['studentsvalue'] : $row['classesvalue']);
        }
        $bars = [];
        foreach ($presented as $row) {
            $value = $field === 'students' ? $row['studentsvalue'] : $row['classesvalue'];
            $bars[] = [
                'name' => $row['name'],
                'value' => (int) round($value / $max * 100),
                'label' => $this->number($value),
                'series' => $series,
            ];
        }
        return $bars;
    }

    /**
     * @param array<int, array<string, mixed>> $courses
     * @return array<int, array<string, mixed>>
     */
    private function class_rows(array $courses): array {
        $rows = [];
        foreach ($courses as $course) {
            $done = $this->meetings_done($course);
            $hadir = $course['logs'] > 0 ? $this->percent($course['present'], $course['logs']) : null;
            $jurnal = $course['opened'] > 0 ? $this->percent($course['taken'], $course['opened']) : null;
            $badge = $this->badge($hadir ?? $jurnal, $hadir !== null || $jurnal !== null);
            $names = array_values($course['teachers']);
            if (count($names) > 2) {
                $teacherlabel = $names[0] . ', ' . $names[1] . ' +' . (count($names) - 2);
            } else {
                $teacherlabel = $names ? implode(', ', $names) : 'Belum ada dosen';
            }
            $rows[] = [
                'prodi' => catalog::prodi_label($course['prodi']),
                'fullname' => $course['fullname'],
                'teachers' => $teacherlabel,
                'meetings' => $done . '/' . catalog::TARGET_MEETINGS,
                'hadir' => $this->show_percent($hadir),
                'jurnal' => $this->show_percent($jurnal),
                'statusclass' => $badge['class'],
                'statuslabel' => $badge['label'],
                'sort' => $hadir ?? ($jurnal ?? 101),
            ];
        }
        usort($rows, static function (array $a, array $b): int {
            return $a['sort'] <=> $b['sort'] ?: strcmp($a['fullname'], $b['fullname']);
        });
        foreach ($rows as &$row) {
            unset($row['sort']);
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $course
     */
    private function meetings_done(array $course): int {
        return min(catalog::TARGET_MEETINGS, max((int) $course['opened'], (int) $course['held']));
    }

    private function percent(int $part, int $whole): ?float {
        if ($whole <= 0) {
            return null;
        }
        return round($part / $whole * 100, 1);
    }

    private function show_percent(?float $value): string {
        if ($value === null) {
            return '—';
        }
        $rounded = round($value, 1);
        if ((float) (int) $rounded === (float) $rounded) {
            return (int) $rounded . '%';
        }
        return number_format($rounded, 1, ',', '.') . '%';
    }

    private function number(int $value): string {
        return number_format($value, 0, ',', '.');
    }

    /**
     * @return array{class:string, label:string}
     */
    private function badge(?float $percent, bool $hasdata): array {
        if (!$hasdata || $percent === null) {
            return ['class' => 'is-empty', 'label' => 'Belum ada data'];
        }
        if ($percent >= 75) {
            return ['class' => 'is-good', 'label' => 'Baik'];
        }
        if ($percent >= 50) {
            return ['class' => 'is-warn', 'label' => 'Perlu perhatian'];
        }
        return ['class' => 'is-bad', 'label' => 'Tertinggal'];
    }
}
