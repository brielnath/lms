<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Block monitoring pimpinan — menampilkan ringkasan monitoring
 * fakultas/universitas langsung di dashboard (my-index).
 *
 * Akun ushpimpinanfak  → menampilkan data fakultasnya sendiri.
 * Akun ushpimpinanuniv → menampilkan ringkasan seluruh universitas.
 * Akun lain            → block kosong (tidak terlihat).
 */
class block_ush_pimpinan extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_ush_pimpinan');
    }

    public function hide_header() {
        return true;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function applicable_formats() {
        return ['my' => true];
    }

    public function get_content() {
        global $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->footer = '';
        $this->content->text   = '';

        // Pastikan local_ush_pimpinan tersedia.
        if (!file_exists($CFG->dirroot . '/local/ush_pimpinan/classes/local/access.php')) {
            return $this->content;
        }

        require_once($CFG->dirroot . '/local/ush_pimpinan/classes/local/access.php');
        require_once($CFG->dirroot . '/local/ush_pimpinan/classes/local/scope.php');
        require_once($CFG->dirroot . '/local/ush_pimpinan/classes/local/catalog.php');
        require_once($CFG->dirroot . '/local/ush_pimpinan/classes/local/repository.php');
        require_once($CFG->dirroot . '/local/ush_pimpinan/classes/local/dashboard.php');

        $scope = \local_ush_pimpinan\local\access::resolve('');
        if ($scope === null) {
            // Bukan pimpinan — sembunyikan block.
            return $this->content;
        }

        $data             = (new \local_ush_pimpinan\local\dashboard())->build($scope);
        $data['inblock']  = true;   // flag untuk template: tampilan ringkas
        $data['fullurl']  = (new moodle_url('/local/ush_pimpinan/index.php'))->out(false);

        $this->content->text = $this->page->get_renderer('core')->render_from_template(
            'block_ush_pimpinan/dashboard',
            $data
        );

        return $this->content;
    }
}
