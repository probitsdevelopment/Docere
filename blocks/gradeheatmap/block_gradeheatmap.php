<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        $this->title = 'Grade Trend';
    }

    // Show on user Dashboard only
    public function applicable_formats() {
        return ['my' => true];
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        require_once($CFG->dirroot . '/course/lib.php');

        // Styles (dark card for teacher/admin)
        $PAGE->requires->css(new moodle_url('/blocks/gradeheatmap/styles.css'));

        // Are we teacher/admin somewhere?
        $systemcanviewall = is_siteadmin($USER) ||
            has_capability('moodle/grade:viewall', context_system::instance());

        // All courses the user is enrolled in (used for student mode and to find teacher courses)
        $enrolled = enrol_get_users_courses($USER->id, true, 'id,shortname');

        // Courses where user has grade:viewall (teacher)
        $teachergradecourses = [];
        foreach ($enrolled as $c) {
            $ctx = context_course::instance($c->id);
            if (has_capability('moodle/grade:viewall', $ctx)) {
                $teachergradecourses[$c->id] = $c->shortname;
            }
        }

        // Mode
        $mode = (!empty($teachergradecourses) || $systemcanviewall) ? 'teacher' : 'student';

        // Courses that actually have numeric grade data (options for teacher/admin)
        $courseoptions = [];
        if ($mode === 'teacher') {
            if ($systemcanviewall) {
                $recs = $DB->get_records_sql("
                    SELECT c.id, c.shortname
                      FROM {course} c
                      JOIN {grade_items} gi
                        ON gi.courseid=c.id
                       AND gi.itemtype IN ('mod','manual','course')
                       AND gi.gradetype=1
                      JOIN {grade_grades} gg ON gg.itemid=gi.id
                     WHERE c.id<>1
                  GROUP BY c.id, c.shortname
                  ORDER BY c.shortname
                ");
                foreach ($recs as $r) $courseoptions[$r->id] = $r->shortname;
            } else if (!empty($teachergradecourses)) {
                list($inSql, $inParams) = $DB->get_in_or_equal(array_keys($teachergradecourses), SQL_PARAMS_NAMED, 'cid');
                $recs = $DB->get_records_sql("
                    SELECT c.id, c.shortname
                      FROM {course} c
                      JOIN {grade_items} gi
                        ON gi.courseid=c.id
                       AND gi.itemtype IN ('mod','manual','course')
                       AND gi.gradetype=1
                      JOIN {grade_grades} gg ON gg.itemid=gi.id
                     WHERE c.id $inSql
                  GROUP BY c.id, c.shortname
                  ORDER BY c.shortname
                ", $inParams);
                foreach ($recs as $r) $courseoptions[$r->id] = $r->shortname;
            }
        }

        // Current selections (teacher/admin)
        $selectedcourseid = ($mode==='teacher') ? optional_param('ghm_courseid', 0, PARAM_INT) : 0;
        if ($mode==='teacher' && !$selectedcourseid && !empty($courseoptions)) {
            $selectedcourseid = (int)array_key_first($courseoptions);
        }
        $selecteduserid = ($mode==='teacher') ? optional_param('ghm_userid', 0, PARAM_INT) : 0; // 0 = average

        // Build student list for the selected course
        $studentoptions = [];
        if ($mode==='teacher' && $selectedcourseid) {
            $studs = $DB->get_records_sql("
                SELECT DISTINCT u.id, u.firstname, u.lastname
                  FROM {grade_grades} gg
                  JOIN {user} u         ON u.id = gg.userid
                  JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.courseid=:cid
                 WHERE gi.gradetype = 1
                   AND gg.finalgrade IS NOT NULL
              ORDER BY u.lastname, u.firstname
            ", ['cid'=>$selectedcourseid]);
            foreach ($studs as $u) {
                $studentoptions[$u->id] = fullname($u);
            }
        }

        // Canvas + top bar (always show both dropdowns for teacher/admin)
        $canvasid = html_writer::random_id('ghm_');
        $canvas   = html_writer::tag('div', '', ['id'=>$canvasid]);

        $topbar = '';
        if ($mode === 'teacher') {
            // Course select
            $opts = '';
            foreach ($courseoptions as $cid=>$short) {
                $sel = ($cid==$selectedcourseid) ? ' selected' : '';
                $opts .= "<option value=\"$cid\"$sel>".s($short)."</option>";
            }
            if ($opts === '') $opts = '<option value="" disabled>(No courses with grade data)</option>';

            // Student select (0 = average)
            $uopts = '<option value="0"'.($selecteduserid==0?' selected':'').'>All students — Average</option>';
            foreach ($studentoptions as $uid=>$name) {
                $sel = ($uid==$selecteduserid) ? ' selected' : '';
                $uopts .= "<option value=\"$uid\"$sel>".s($name)."</option>";
            }

            // Render dropdowns in topbar
            $topbar = '<div class="ghm-topbar">'
                . '<label for="ghm_courseid">Course: </label>'
                . '<select id="ghm_courseid" name="ghm_courseid">' . $opts . '</select>'
                . '&nbsp;&nbsp;'
                . '<label for="ghm_userid">Student: </label>'
                . '<select id="ghm_userid" name="ghm_userid">' . $uopts . '</select>'
                . '</div>';
        }

        $init = <<<JS
require(['block_gradeheatmap/heatmap'], function(heatmap) {
    var p = $json;
    heatmap.init(p);
});
JS;

        $PAGE->requires->js_init_code($init);

        // Render topbar and canvas in block content
        $this->content->text = $topbar . $canvas;
        $this->content->footer = '';
        return $this->content;
    }
}
