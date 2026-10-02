<?php
namespace local_ush_pimpinan\local;

defined('MOODLE_INTERNAL') || die();

class scope {
    public function __construct(
        public string $level,
        public ?string $facultycode,
        public bool $candrill,
        public bool $drilled,
    ) {
    }

    public function is_university(): bool {
        return $this->level === 'univ';
    }
}
