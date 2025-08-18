<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        // Title from language pack; falls back if string missing.
        try {
            $this->title = get_string('pluginname', 'block_gradeheatmap');
        } catch (Exception $e) {
            $this->title = 'Grade Heatmap';
        }
    }

    // Allow on Dashboard ("my") and Course pages.
    public function applicable_formats() {
        return ['my' => true, 'course-view' => true];
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE, $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();

        // Detect Dashboard (My home) context.
        $isdashboard = ($PAGE->pagelayout === 'mydashboard') || ($PAGE->context->contextlevel == CONTEXT_USER);

        // We need course API for enrolled courses.
        require_once($CFG->dirroot . '/course/lib.php');

        // Prepare data containers.
        $xlabels = []; // columns (activities)
        $ylabels = []; // rows (students or single user)
        $cells   = []; // {x, y, v} matrix cells

        if ($isdashboard) {
            // DASHBOARD VIEW: show THIS user's grades across ALL enrolled courses.
            $mycourses = enrol_get_users_courses($USER->id, true, 'id,shortname');
            if (empty($mycourses)) {
                $this->content->text = get_string('nogrades', 'block_gradeheatmap');
                return $this->content;
            }
            $courseids = array_keys($mycourses);
            list($inSql, $inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
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

            // Build column labels as "COURSE: Item".
            foreach ($rows as $r) {
                $cshort = isset($mycourses[$r->courseid]) ? $mycourses[$r->courseid]->shortname : ('C'.$r->courseid);
                $xlabels[] = trim($cshort . ': ' . $r->itemname);
            }
            $xlabels = array_values(array_unique($xlabels)); // keep first-seen order
            $ylabels = [fullname($USER)];                    // single row (this user)

            // Quick lookup: label => percent
            $lookup = [];
            foreach ($rows as $r) {
                $cshort = isset($mycourses[$r->courseid]) ? $mycourses[$r->courseid]->shortname : ('C'.$r->courseid);
                $lab = trim($cshort . ': ' . $r->itemname);
                $pct = ($r->grademax > 0 && isset($r->finalgrade)) ? round(($r->finalgrade / $r->grademax) * 100, 1) : null;
                $lookup[$lab] = $pct;
            }

            for ($x = 0; $x < count($xlabels); $x++) {
                $iname = $xlabels[$x];
                $v = $lookup[$iname] ?? null;
                $cells[] = ['x' => $x, 'y' => 0, 'v' => $v]; // single row => y=0
            }

        } else {
            // COURSE PAGE VIEW: show grid (all students if permitted, else current user only).
            if (empty($COURSE) || empty($COURSE->id)) {
                $this->content->text = get_string('nogrades', 'block_gradeheatmap');
                return $this->content;
            }

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
                     $userfilter
                ORDER BY u.lastname, u.firstname, gi.sortorder";

            $rows = $DB->get_records_sql($sql, $params);
            if (!$rows) {
                $this->content->text = get_string('nogrades', 'block_gradeheatmap');
                return $this->content;
            }

            // Columns = activity names; Rows = student full names (or just current user).
            $xlabels = array_values(array_unique(array_map(function($r){ return trim($r->itemname); }, $rows)));
            $ylabels = array_values(array_unique(array_map(function($r){ return trim(($r->firstname ?? '').' '.($r->lastname ?? '')); }, $rows)));

            // Fill matrix lookup.
            $lookup = [];
            foreach ($rows as $r) {
                $sname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
                $iname = trim($r->itemname);
                $pct = ($r->grademax > 0 && isset($r->finalgrade)) ? round(($r->finalgrade / $r->grademax) * 100, 1) : null;
                $lookup[$sname][$iname] = $pct;
            }

            for ($y = 0; $y < count($ylabels); $y++) {
                $sname = $ylabels[$y];
                for ($x = 0; $x < count($xlabels); $x++) {
                    $iname = $xlabels[$x];
                    $v = $lookup[$sname][$iname] ?? null;
                    $cells[] = ['x' => $x, 'y' => $y, 'v' => $v];
                }
            }
        }

        // Render a simple canvas (no Mustache).
        $canvasid = html_writer::random_id('gradeheatmap_');
        $canvas = html_writer::tag('canvas', '', ['id' => $canvasid]);
        $wrapper = html_writer::tag('div', $canvas, [
            'class' => 'heatmap-scroll',
            'style' => 'overflow:auto; max-height:480px;'
        ]);
        $this->content->text = html_writer::div($wrapper, 'block_gradeheatmap');

        // Prepare payload for JS.
        $payload = [
            'canvasid' => $canvasid,
            'xlabels'  => array_values($xlabels),
            'ylabels'  => array_values($ylabels),
            'cells'    => array_values($cells),
        ];

        // Load Chart.js + matrix plugin from CDN.
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js'));
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.umd.min.js'));

        // Inline init JS (no AMD build needed).
        $payloadjson = json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
        $init = <<<JS
(function(){
  function valueToColor(v){
    if (v===null || isNaN(v)) return 'rgba(220,220,220,0.35)'; // no grade
    var t = Math.max(0, Math.min(1, v/100)); // 0..1
    var r = Math.round(255*(1-t));
    var g = Math.round(255*t);
    return 'rgba('+r+','+g+',0,0.85)'; // red->green
  }
  var payload = $payloadjson;
  var cv = document.getElementById(payload.canvasid);
  if (!cv || typeof Chart === 'undefined') return;
  // Size canvas based on data volume.
  cv.width  = Math.max(600, payload.xlabels.length * 40);
  cv.height = Math.max(300, payload.ylabels.length * 28);
  var ctx = cv.getContext('2d');
  // Convert index-based cells to labelled matrix data.
  var data = payload.cells.map(function(c){
    return { x: payload.xlabels[c.x], y: payload.ylabels[c.y], v: c.v };
  });
  new Chart(ctx, {
    type: 'matrix',
    data: {
      datasets: [{
        label: 'Grades (%)',
        data: data,
        backgroundColor: function(ctx){ return valueToColor(ctx.raw.v); },
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.08)',
        width: function(ctx){ var a = ctx.chart.chartArea; return (a.right-a.left)/payload.xlabels.length - 2; },
        height:function(ctx){ var a = ctx.chart.chartArea; return (a.bottom-a.top)/payload.ylabels.length - 2; }
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: function(items){ var it = items[0]; return it.raw.y + ' — ' + it.raw.x; },
            label: function(item){ var v = item.raw.v; return (v==null||isNaN(v)) ? 'No grade' : (v + '%'); }
          }
        }
      },
      scales: {
        x: { type: 'category', labels: payload.xlabels, position: 'top', ticks: { autoSkip:false, maxRotation:60, minRotation:0 } },
        y: { type: 'category', labels: payload.ylabels, reverse: true, ticks: { autoSkip:false } }
      }
    }
  });
})();
JS;
        $PAGE->requires->js_init_code($init);

        $this->content->footer = '';
        return $this->content;
    }
}
