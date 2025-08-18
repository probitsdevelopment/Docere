<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_gradeheatmap');
    }

    public function applicable_formats() {
        // Allow on Dashboard and Course pages.
        return ['my' => true, 'course-view' => true];
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE, $OUTPUT, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();

        // If we are on a course page AND the viewer can see all grades, we can keep the course view.
        $oncoursepage = !empty($COURSE) && !empty($COURSE->id) && $PAGE->pagelayout !== 'mydashboard';

        // Include libs to list a user's courses.
        require_once($CFG->dirroot . '/course/lib.php');

        // -------- MODE 1: DASHBOARD (My home) --------
        // Show THE LOGGED-IN USER's grades across all their enrolled courses.
        $showdashboardview = ($PAGE->pagelayout === 'mydashboard' || $PAGE->context->contextlevel == CONTEXT_USER);

        // If on course page but you still want per-user dashboard-like view, set this true.
        // $showdashboardview = true;

        if ($showdashboardview) {
            // 1) Get courses the user is enrolled in (visible only).
            $mycourses = enrol_get_users_courses($USER->id, true, 'id,fullname,shortname');
            if (empty($mycourses)) {
                $this->content->text = get_string('nogrades', 'block_gradeheatmap');
                return $this->content;
            }
            $courseids = array_keys($mycourses);

            // Build dynamic placeholders for IN (...)
            list($inSql, $inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');

            // 2) Pull ONLY this user's numeric grades from those courses.
            $params = array_merge($inParams, ['userid' => $USER->id]);

            $sql = "SELECT
                        gi.id AS itemid,
                        gi.courseid,
                        COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule, ' #', gi.id)) AS itemname,
                        gi.sortorder,
                        gi.grademax,
                        gg.finalgrade
                    FROM {grade_items} gi
                    JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                   WHERE gi.courseid $inSql
                     AND gi.itemtype = 'mod'
                     AND gi.gradetype = 1
                ORDER BY gi.courseid, gi.sortorder, gi.id";

            $rows = $DB->get_records_sql($sql, $params);

            if (!$rows) {
                $this->content->text = get_string('nogrades', 'block_gradeheatmap');
                return $this->content;
            }

            // Labels: X = "CourseShortName: Item", Y = just one row (the user).
            $xlabels = [];
            $cells   = [];

            // Build ordered unique activity labels.
            foreach ($rows as $r) {
                $cshort = isset($mycourses[$r->courseid]) ? $mycourses[$r->courseid]->shortname : ('C' . $r->courseid);
                $label  = trim($cshort . ': ' . $r->itemname);
                $xlabels[] = $label;
            }
            $xlabels = array_values(array_unique($xlabels)); // keep order

            // One row with the user's name.
            $ylabels = [fullname($USER)];

            // Quick lookup to fill matrix (single row).
            $lookup = [];
            foreach ($rows as $r) {
                $cshort = isset($mycourses[$r->courseid]) ? $mycourses[$r->courseid]->shortname : ('C' . $r->courseid);
                $iname  = trim($cshort . ': ' . $r->itemname);
                $pct = (isset($r->finalgrade) && $r->grademax > 0) ? round(($r->finalgrade / $r->grademax) * 100, 1) : null;
                $lookup[$iname] = $pct;
            }

            for ($x = 0; $x < count($xlabels); $x++) {
                $iname = $xlabels[$x];
                $v = $lookup[$iname] ?? null;
                $cells[] = ['x' => $x, 'y' => 0, 'v' => $v]; // y=0 (single row)
            }

            // Render template + JS.
            $canvasid = html_writer::random_id('gradeheatmap_');
            $this->content->text = $OUTPUT->render_from_template('block_gradeheatmap/heatmap', [
                'canvasid' => $canvasid
            ]);

            $payload = [
                'canvasid' => $canvasid,
                'xlabels'  => $xlabels,
                'ylabels'  => $ylabels,
                'cells'    => $cells
            ];

            // Load Chart.js + matrix plugin (CDN) and our AMD init.
            $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js'));
            $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.umd.min.js'));
            $PAGE->requires->js_call_amd('block_gradeheatmap/heatmap', 'init', [$payload]);

            $this->content->footer = '';
            return $this->content;
        }

        // -------- MODE 2: COURSE PAGE (original multi-student grid) --------
        // (Kept for flexibility; remove this whole block if you don’t want it on courses.)
        $context = context_course::instance($COURSE->id);
        $canviewall = has_capability('moodle/grade:viewall', $context);
        $params = ['courseid' => $COURSE->id];
        $userfilter = '';

        if (!$canviewall) {
            $params['userid'] = $USER->id;
            $userfilter = ' AND gg.userid = :userid';
        }

        $sql = "SELECT
                    u.id AS userid,
                    u.firstname,
                    u.lastname,
                    gi.id AS itemid,
                    COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule, ' #', gi.id)) AS itemname,
                    gi.sortorder,
                    gi.grademax,
                    gg.finalgrade
                FROM {grade_items} gi
                JOIN {grade_grades} gg ON gg.itemid = gi.id
                JOIN {user} u ON u.id = gg.userid
               WHERE gi.courseid = :courseid
                 AND gi.itemtype = 'mod'
                 AND gi.gradetype = 1
                 {$userfilter}
            ORDER BY u.lastname, u.firstname, gi.sortorder";

        $rows = $DB->get_records_sql($sql, $params);

        if (!$rows) {
            $this->content->text = get_string('nogrades', 'block_gradeheatmap');
            return $this->content;
        }

        $xlabels = array_values(array_unique(array_map(function($r){ return trim($r->itemname); }, $rows)));
        $ylabels = array_values(array_unique(array_map(function($r){ return trim(($r->firstname ?? '').' '.($r->lastname ?? '')); }, $rows)));

        $lookup = [];
        foreach ($rows as $r) {
            $sname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
            $iname = trim($r->itemname);
            $pct = (isset($r->finalgrade) && $r->grademax > 0) ? round(($r->finalgrade / $r->grademax) * 100, 1) : null;
            $lookup[$sname][$iname] = $pct;
        }

        $cells = [];
        for ($y = 0; $y < count($ylabels); $y++) {
            $sname = $ylabels[$y];
            for ($x = 0; $x < count($xlabels); $x++) {
                $iname = $xlabels[$x];
                $v = $lookup[$sname][$iname] ?? null;
                $cells[] = ['x' => $x, 'y' => $y, 'v' => $v];
            }
        }

        $canvasid = html_writer::random_id('gradeheatmap_');
        $this->content->text = $OUTPUT->render_from_template('block_gradeheatmap/heatmap', [
            'canvasid' => $canvasid
        ]);

        $payload = [
            'canvasid' => $canvasid,
            'xlabels'  => $xlabels,
            'ylabels'  => $ylabels,
            'cells'    => $cells
        ];

        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js'));
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.umd.min.js'));
        $PAGE->requires->js_call_amd('block_gradeheatmap/heatmap', 'init', [$payload]);

        $this->content->footer = '';
        return $this->content;
    }
}

