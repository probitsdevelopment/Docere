<?php
defined('MOODLE_INTERNAL') || die();

class block_grade_heatmap extends block_base {
    public function init() {
        try { $this->title = get_string('pluginname', 'block_grade_heatmap'); }
        catch (\Throwable $e) { $this->title = 'Grade Heatmap'; }
    }

    public function applicable_formats() {
        // Dashboard only; set 'course-view' => true if you also want it on course pages.
        return ['my' => true];
    }

    public function get_content() {
        global $CFG, $USER, $PAGE;

        if ($this->content !== null) { return $this->content; }
        $this->content = new stdClass();
        $this->content->footer = '';

        // CSS
        $PAGE->requires->css(new moodle_url('/blocks/grade_heatmap/styles.css'));

        // Load Chart.js + Matrix plugin (as globals used by AMD code).
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js'), true);
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.umd.min.js'), true);

        // Build course dropdown options: courses where user can view grades.
        require_once($CFG->dirroot . '/course/lib.php');
        $courses = enrol_get_users_courses($USER->id, true, 'id,fullname,shortname');
        $options = [];
        foreach ($courses as $c) {
            $ctx = context_course::instance($c->id);
            if (has_capability('moodle/grade:view', $ctx)) {
                $options[$c->id] = format_string($c->fullname ?: $c->shortname ?: ('Course '.$c->id));
            }
        }
        if (empty($options)) {
            $this->content->text = html_writer::div(get_string('nocourses', 'moodle'), 'muted');
            return $this->content;
        }

        $selectcourseid  = 'ghm-course-'.$this->instance->id;
        $selectstudentid = 'ghm-student-'.$this->instance->id;
        $canvasid        = 'ghm-canvas-'.$this->instance->id;

        // Top bar
        $topbar  = html_writer::start_tag('div', ['class'=>'ghm-topbar', 'role'=>'group', 'aria-label'=>'Grade heatmap controls']);
        $topbar .= html_writer::tag('span', get_string('course').':', ['class'=>'ghm-label']);
        $topbar .= html_writer::start_tag('select', ['id'=>$selectcourseid, 'class'=>'ghm-select']);
        foreach ($options as $cid => $cname) {
            $topbar .= html_writer::tag('option', $cname, ['value'=>$cid]);
        }
        $topbar .= html_writer::end_tag('select');
        $topbar .= html_writer::tag('span', '', ['class'=>'ghm-spacer']);
        $topbar .= html_writer::tag('span', get_string('user').':', ['class'=>'ghm-label']);
        $topbar .= html_writer::tag('select', '', ['id'=>$selectstudentid, 'class'=>'ghm-select']);
        $topbar .= html_writer::end_tag('div');

        // Canvas
        $canvas = html_writer::tag('canvas', '', [
            'id'=>$canvasid, 'aria-label'=>'Grade heatmap', 'role'=>'img',
            'style'=>'width:100%;height:520px'
        ]);

        $this->content->text = html_writer::div(
            html_writer::div($topbar.$canvas, 'heatmap-wrap'),
            'block_gradeheatmap'
        );

        // Endpoints passed to AMD init
        $dataurl     = (new moodle_url('/local/gradeheatmap/data.php'))->out(false);
        $studentsurl = (new moodle_url('/local/gradeheatmap/students.php'))->out(false);

        $PAGE->requires->js_call_amd('block_grade_heatmap/heatmap', 'init', [[
            'selectcourseid'  => $selectcourseid,
            'selectstudentid' => $selectstudentid,
            'canvasid'        => $canvasid,
            'dataurl'         => $dataurl,
            'studentsurl'     => $studentsurl
        ]]);

        return $this->content;
    }
}
