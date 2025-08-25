<?php
namespace theme_acme\output;

defined('MOODLE_INTERNAL') || die();

class core_renderer extends \theme_boost\output\core_renderer {
    public function standard_head_html() {
        global $CFG, $USER;

        // Cohort functions live here.
        require_once($CFG->dirroot . '/cohort/lib.php');

        // TODO: Use your real cohort id.
        $acmecohortid = 02;

        // Note the leading backslashes – these are global functions.
        if (\isloggedin() && !\isguestuser() && \cohort_is_member($acmecohortid, $USER->id)) {
            $this->page->add_body_class('org-acme');
        }

        return parent::standard_head_html();
    }
}
