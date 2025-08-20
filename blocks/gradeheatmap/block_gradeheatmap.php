
<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        try { 
            $this->title = get_string('pluginname', 'block_gradeheatmap'); 
        } catch (\Throwable $e) { 
            $this->title = 'Grade Trends'; 
        }
    }

    // Show on Dashboard and course pages.
    public function applicable_formats() {
        return ['my' => true, 'course-view' => true, 'site' => false];
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE, $COURSE;

        if ($this->content !== null) return $this->content;
        $this->content = new stdClass();

        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/lib/enrollib.php');

        // Styles
        $PAGE->requires->css(new moodle_url('/blocks/gradeheatmap/styles.css'));

        // Load ECharts + our AMD renderer (it listens for a custom event).
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js'), true);
        $PAGE->requires->js_call_amd('block_gradeheatmap/echart', 'init', [[
            'el' => 'gh-echart',
            'minHeight' => 340,
            'on' => true
        ]]);

        // Chart container.
        $chartdiv = html_writer::div('', 'gradeheatmap-echart', ['id' => 'gh-echart']);

        $isDash   = ($this->page->pagetype === 'my-index');
        $isCourse = (!empty($COURSE) && $COURSE->id > 1);

        // -------- data helpers --------
        $build_series = function(int $userid, ?int $courseid=null) use($DB) {
            $labels = []; $series = [];

            if ($courseid) {
                $courseids = [$courseid];
                $prefixmap = [];
            } else {
                $courses = enrol_get_users_courses($userid, true, 'id,shortname');
                if (!$courses) return [$labels, $series];
                $courseids = array_keys($courses);
                $prefixmap = array_column((array)$courses, 'shortname', 'id'); // id => shortname
            }

            list($inSql, $inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $params = array_merge($inParams, ['userid' => $userid]);

            // Corrected query with named placeholders
            $sql = "SELECT gi.id, gi.courseid,
                       COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule,' #',gi.id)) AS itemname,
                       gi.sortorder, gi.grademax, gg.finalgrade
                  FROM {grade_items} gi
                  JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.courseid $inSql
                   AND gi.itemtype IN ('mod','manual','course')
                   AND gi.gradetype = 1
              ORDER BY gi.courseid, gi.sortorder, gi.id";
            $rows = $DB->get_records_sql($sql, $params);

            foreach ($rows as $r) {
                $prefix = $courseid ? '' : (($prefixmap[$r->courseid] ?? 'C'.$r->courseid).': ');
                $labels[] = $prefix . trim($r->itemname);
                $series[] = ($r->grademax > 0 && $r->finalgrade !== null)
                          ? round(($r->finalgrade / $r->grademax) * 100, 1)
                          : null;
            }
            return [$labels, $series];
        };

        $build_avg_series = function(int $courseid) use($DB) {
            $labels = []; $series = [];
            $sql = "SELECT gi.id,
                       COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule,' #',gi.id)) AS itemname,
                       gi.sortorder,
                       AVG(CASE WHEN gi.grademax>0 AND gg.finalgrade IS NOT NULL
                                THEN (gg.finalgrade/gi.grademax)*100 END) AS pct
                      FROM {grade_items} gi
                      JOIN {grade_grades} gg ON gg.itemid = gi.id
                     WHERE gi.courseid = :cid
                       AND gi.itemtype IN ('mod','manual','course')
                       AND gi.gradetype = 1
                  GROUP BY gi.id, gi.itemname, gi.sortorder
                  ORDER BY gi.sortorder, gi.id";
            $rows = $DB->get_records_sql($sql, ['cid'=>$courseid]);
            foreach ($rows as $r) {
                $labels[] = trim($r->itemname);
                $series[] = $r->pct !== null ? round((float)$r->pct, 1) : null;
            }
            return [$labels, $series];
        };

        // -------- decide view + build toolbars --------
        $labels = []; $series = [];
        $toolbar = '';

        if ($isDash) {
            // Teacher/Admin dashboard: show course+student pickers if they have grade:viewall somewhere.
            $teachercourses = get_user_capability_course('moodle/grade:viewall', $USER->id, true, 'c.id, c.shortname', 'c.shortname ASC');
            if ($teachercourses) {
                $courses = array_values($teachercourses);

                $cid = optional_param('gh_cid', 0, PARAM_INT);
                $valid = array_map(function($c){return (int)$c->id;}, $courses);
                if (!$cid || !in_array((int)$cid, $valid, true)) {
                    $cid = (int)$courses[0]->id;
                }

                // Course select
                $copts = '';
                foreach ($courses as $c) {
                    $attrs = ['value'=>$c->id];
                    if ((int)$c->id === (int)$cid) $attrs['selected'] = 'selected';
                    $copts .= html_writer::tag('option', $c->shortname, $attrs);
                }
                $courseSel = html_writer::tag('select', $copts, ['id'=>'gh-select-course','class'=>'ghm-select']);

                // Students for selected course
                $ctx = context_course::instance($cid);
                $students = get_enrolled_users($ctx, 'mod/assign:submit', 0, 'u.id, u.firstname, u.lastname', 'u.lastname, u.firstname', 0, 5000);

                $seluid = optional_param('gh_uid', 'avg', PARAM_RAW);
                $uopts = html_writer::tag('option', get_string('allstudentsavg', 'block_gradeheatmap'), ['value'=>'avg','selected'=>($seluid==='avg')]);
                foreach ($students as $u) {
                    $attrs = ['value'=>$u->id];
                    if ((string)$u->id === (string)$seluid) $attrs['selected'] = 'selected';
                    $uopts .= html_writer::tag('option', fullname($u), $attrs);
                }
                $userSel = html_writer::tag('select', $uopts, ['id'=>'gh-select-user','class'=>'ghm-select']);

                $toolbar = html_writer::div(
                    html_writer::span(get_string('course')).' '.$courseSel.' '.
                    html_writer::span(get_string('user')).' '.$userSel,
                    'ghm-topbar'
                );

                if ($seluid === 'avg') {
                    [$labels, $series] = $build_avg_series($cid);
                } else {
                    [$labels, $series] = $build_series((int)$seluid, $cid);
                }

                // Change handlers (Dashboard)
                $PAGE->requires->js_init_code(<<<JS
(function(){
  function go(){
    var url=new URL(window.location.href);
    url.searchParams.set('gh_cid', document.getElementById('gh-select-course').value);
    url.searchParams.set('gh_uid', document.getElementById('gh-select-user').value);
    window.location.assign(url.toString());
  }
  var c=document.getElementById('gh-select-course');
  var u=document.getElementById('gh-select-user');
  if(c) c.addEventListener('change', go);
  if(u) u.addEventListener('change', go);
})();
JS);

            } else {
                // Regular student dashboard → show own across enrolled courses
                [$labels, $series] = $build_series($USER->id, null);
            }

        } elseif ($isCourse && (has_capability('moodle/grade:viewall', $this->page->context) ||
                                has_capability('moodle/course:update', $this->page->context))) {
            // Course page teacher/admin: student dropdown
            $selected = optional_param('gh_uid', 'avg', PARAM_RAW);
            $students = get_enrolled_users($this->page->context, 'mod/assign:submit', 0, 'u.id, u.firstname, u.lastname', 'u.lastname, u.firstname', 0, 5000);

            $opts = html_writer::tag('option', get_string('allstudentsavg', 'block_gradeheatmap'), ['value'=>'avg','selected'=>($selected==='avg')]);
            foreach ($students as $u) {
                $attrs = ['value'=>$u->id];
                if ((string)$u->id === (string)$selected) $attrs['selected'] = 'selected';
                $opts .= html_writer::tag('option', fullname($u), $attrs);
            }
            $sel = html_writer::tag('select', $opts, ['id'=>'gh-select-user','class'=>'ghm-select']);
            $toolbar = html_writer::div(html_writer::span(get_string('user')).' '.$sel, 'ghm-topbar');

            if ($selected === 'avg') {
                [$labels, $series] = $build_avg_series($COURSE->id);
            } else {
                [$labels, $series] = $build_series((int)$selected, $COURSE->id);
            }

            $PAGE->requires->js_init_code(<<<JS
(function(){
  var s=document.getElementById('gh-select-user'); if(!s) return;
  s.addEventListener('change', function(){
    var url=new URL(window.location.href);
    url.searchParams.set('gh_uid', this.value);
    window.location.assign(url.toString());
  });
})();
JS);
        } else {
            // Fallback: viewer's own
            [$labels, $series] = $build_series($USER->id, null);
        }

        // Empty-state note
        $note = '';
        if (empty($labels)) {
            $labels = ['Demo: Quiz 1','Demo: Assignment 1','Demo: Final'];
            $series = [62, 84, null];
            $note = html_writer::div(get_string('nogrades', 'block_gradeheatmap'),
                                     'small text-muted', ['style'=>'margin:6px 12px;']);
        }

        // Layout
        $this->content->text = html_writer::div($toolbar . $note . $chartdiv, 'block_gradeheatmap');

        // ECharts option & dispatch update (our AMD listens for this)
        $option = [
            'xAxis'   => ['type'=>'category','data'=>array_values($labels)],
            'yAxis'   => ['type'=>'value','min'=>0,'max'=>100],
            'tooltip' => ['trigger'=>'axis'],
            'grid'    => ['left'=>'3%','right'=>'3%','bottom'=>'3%','containLabel'=>true],
            'series'  => [[
                'name'=>'Grade (%)','type'=>'line','smooth'=>true,
                'data'=>array_values($series),'connectNulls'=>false
            ]]
        ];
        $PAGE->requires->js_init_code("(function(){var ev=new CustomEvent('gh:echart:update',{detail:{option:".
            json_encode($option, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP).
        "}});document.dispatchEvent(ev);}());");

        $this->content->footer = '';
        return $this->content;
    }
}
