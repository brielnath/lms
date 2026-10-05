<?php
defined('MOODLE_INTERNAL') || die();

class block_ush_matkul_dosen extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_ush_matkul_dosen');
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return true;
    }

    public function applicable_formats() {
        return ['my' => true];
    }

    public function get_content() {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $data = (new \block_ush_matkul_dosen\local\view())->export((int) $USER->id);
        if (!empty($data['show'])) {
            $this->title = $data['title'];
            $this->content->text = $this->page->get_renderer('core')->render_from_template(
                'block_ush_matkul_dosen/main',
                $data
            );
        }
        return $this->content;
    }
}
