<?php
defined('MOODLE_INTERNAL') || die();

class block_ush_kaprodi_dosen extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_ush_kaprodi_dosen');
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
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $view = new \block_ush_kaprodi_dosen\local\view();
        $data = $view->export($OUTPUT);
        if (empty($data['iskaprodi'])) {
            $this->content->text = '';
            return $this->content;
        }

        $this->content->text = $this->page->get_renderer('core')->render_from_template(
            'block_ush_kaprodi_dosen/main',
            $data
        );
        return $this->content;
    }
}
