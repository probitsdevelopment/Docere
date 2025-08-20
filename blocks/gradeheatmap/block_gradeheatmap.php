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

        // Load Apache ECharts library
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js'));

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
        $canvas   = html_writer::tag('div', '', ['id'=>$canvasid, 'style'=>'height: 400px;']);

        $wrap = html_writer::tag('div', $canvas, [
            'class'=> ($mode==='teacher' ? 'heatmap-wrap dark' : 'heatmap-wrap')
        ]);
        $this->content->text = html_writer::div($wrap, 'block_gradeheatmap');

        // ----------------- DATA PAYLOAD -----------------
        if ($mode === 'teacher' && $selectedcourseid) {
            if ($selecteduserid > 0) {
                // Per-student
                $rows = $DB->get_records_sql("
                    SELECT
                      gi.id,
                      COALESCE(
                        NULLIF(gi.itemname,''), 
                        IFNULL(CONCAT(gi.itemmodule,' #',gi.id), CONCAT('Course total #', gi.id))
                      ) AS itemname,
                      ROUND(gg.finalgrade/NULLIF(gi.grademax,0)*100,1) AS percent,
                      gi.sortorder
                    FROM {grade_items} gi
                    JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :uid
                   WHERE gi.courseid = :cid
                     AND gi.itemtype IN ('mod','manual','course')
                     AND gi.gradetype = 1
                ORDER BY gi.sortorder, gi.id
                ", ['cid'=>$selectedcourseid, 'uid'=>$selecteduserid]);
                $actualLabel = 'Actual — '.($studentoptions[$selecteduserid] ?? 'Student');
            } else {
                // Course average
                $rows = $DB->get_records_sql("
                    SELECT
                      gi.id,
                      COALESCE(
                        NULLIF(gi.itemname,''), 
                        IFNULL(CONCAT(gi.itemmodule,' #',gi.id), CONCAT('Course total #', gi.id))
                      ) AS itemname,
                      ROUND(AVG(gg.finalgrade/NULLIF(gi.grademax,0))*100,1) AS percent,
                      gi.sortorder
                    FROM {grade_items} gi
                    JOIN {grade_grades} gg ON gg.itemid = gi.id
                   WHERE gi.courseid = :cid
                     AND gi.itemtype IN ('mod','manual','course')
                     AND gi.gradetype = 1
                     AND gg.finalgrade IS NOT NULL
                GROUP BY gi.id, gi.itemname, gi.sortorder
                ORDER BY gi.sortorder, gi.id
                ", ['cid'=>$selectedcourseid]);
                $actualLabel = 'Actual — Course average';
            }

            $labels=[]; $actual=[];
            if ($rows) {
                foreach ($rows as $r) {
                    $name = trim($r->itemname ?? '');
                    if ($name==='') $name = 'Item #'.$r->id;
                    $labels[] = $name;
                    $actual[] = $r->percent===null ? null : (float)$r->percent;
                }
            }
            if (empty($labels)) {
                $labels   = ['Demo: Quiz 1','Demo: Assign 1','Demo: Final'];
                $actual   = [48.0,73.0,66.0];
            }
            $expected = array_fill(0, max(1,count($labels)), 60.0);

            $payload = [
                'mode'        => 'teacher',
                'canvasid'    => $canvasid,
                'labels'      => $labels,
                'actual'      => $actual,
                'expected'    => $expected,
                'actualLabel' => $actualLabel
            ];

        } else {
            // Student: their own % across all enrolled courses
            $labels=[]; $series=[];
            if (!empty($enrolled)) {
                $courseids = array_keys($enrolled);
                list($inSql,$inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
                $rows = $DB->get_records_sql("
                    SELECT gi.id AS id,
                           gi.courseid,
                           COALESCE(
                             NULLIF(gi.itemname,''), 
                             IFNULL(CONCAT(gi.itemmodule,' #',gi.id), CONCAT('Course total #', gi.id))
                           ) AS itemname,
                           gi.sortorder, gi.grademax, gg.finalgrade
                      FROM {grade_items} gi
                      JOIN {grade_grades} gg ON gg.itemid=gi.id AND gg.userid=:uid
                     WHERE gi.courseid $inSql
                       AND gi.itemtype IN ('mod','manual','course')
                       AND gi.gradetype = 1
                  ORDER BY gi.courseid, gi.sortorder, gi.id
                ", array_merge($inParams,['uid'=>$USER->id]));
                foreach ($rows as $r) {
                    $c = $enrolled[$r->courseid]->shortname ?? ('C'.$r->courseid);
                    $name = trim($r->itemname ?? '');
                    if ($name==='') $name = 'Item #'.$r->id;
                    $labels[] = $c.': '.$name;
                    $series[] = ($r->grademax>0 && $r->finalgrade!==null)
                        ? (float)round(($r->finalgrade/$r->grademax)*100,1) : null;
                }
            }
            if (empty($labels)) {
                $labels=['Demo: Quiz 1','Demo: Assign 1','Demo: Final'];
                $series=[62.0,84.0,null];
            }
            $payload = [
                'mode'     => 'student',
                'canvasid' => $canvasid,
                'labels'   => $labels,
                'series'   => $series
            ];
        }

        $json = json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        // ----------------- JS -----------------
        $init = <<<JS
(function(){
  var p = $json;
  var cv = document.getElementById(p.canvasid);
  if (!cv) return;
  var myChart = echarts.init(cv);

  var option = {
      title: {
          text: p.actualLabel || 'Grades Overview'
      },
      tooltip: {
          trigger: 'axis'
      },
      xAxis: {
          type: 'category',
          data: p.labels
      },
      yAxis: {
          type: 'value'
      },
      series: [{
          data: p.actual || [],
          type: 'line',  // This ensures the chart is a line chart
          smooth: true,
          color: '#0284C7',
          name: 'Actual'
      }, {
          data: p.expected || [],
          type: 'line',  // Expected is also a line
          smooth: true,
          color: '#FFD44A',
          name: 'Expected',
          lineStyle: { type: 'dashed' }
      }]
  };

  myChart.setOption(option);
  window.addEventListener('resize', function() {
    myChart.resize();
  });
})();
JS;

        $PAGE->requires->js_init_code($init);

        $this->content->footer = '';
        return $this->content;
    }
}
