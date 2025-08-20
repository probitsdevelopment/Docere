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
                  JOIN {user} u        ON u.id = gg.userid
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
        $canvas   = html_writer::tag('div', '', ['id'=>$canvasid, 'style' => 'width:100%;height:350px;']);

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

            $topbar = '
               <div class="ghm-topbar dark">
                 <label class="ghm-label">Course:</label>
                 <select class="ghm-select" id="ghm-course-select-'.$canvasid.'">'.$opts.'</select>

                 <label class="ghm-label" style="margin-left:12px">Student:</label>
                 <select class="ghm-select" id="ghm-user-select-'.$canvasid.'">'.$uopts.'</select>
               </div>';
        }

        $wrap = html_writer::tag('div', $topbar.$canvas, [
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
  var p = {$json};
  var chartDom = document.getElementById(p.canvasid);
  if (!chartDom) return;
  var myChart = null;

  // Change handlers: keep both params
  var csel = document.getElementById('ghm-course-select-'+p.canvasid);
  if (csel) csel.addEventListener('change', function(){
    var url = new URL(window.location.href);
    url.searchParams.set('ghm_courseid', this.value);
    var usel = document.getElementById('ghm-user-select-'+p.canvasid);
    if (usel) url.searchParams.set('ghm_userid', usel.value || 0);
    window.location.href = url.toString();
  });
  var usel = document.getElementById('ghm-user-select-'+p.canvasid);
  if (usel) usel.addEventListener('change', function(){
    var url = new URL(window.location.href);
    url.searchParams.set('ghm_userid', this.value);
    if (csel) url.searchParams.set('ghm_courseid', csel.value || 0);
    window.location.href = url.toString();
  });

  function noAMD(src, cb){
    var od=window.define, om=window.module, oe=window.exports;
    try{ window.define=undefined; window.module=undefined; window.exports=undefined; }catch(e){}
    var s=document.createElement('script'); s.src=src; s.async=true;
    s.onload=function(){ window.define=od; window.module=om; window.exports=oe; cb(); };
    s.onerror=function(){ window.define=od; window.module=om; window.exports=oe; cb(new Error('loadfail '+src)); };
    document.head.appendChild(s);
  }

  function drawTeacher(){
    var isLight = getComputedStyle(chartDom.parentElement).backgroundColor.startsWith('rgb(245, 245, 245)') || getComputedStyle(chartDom.parentElement).backgroundColor.startsWith('rgb(255, 255, 255)');
    var actualCol = isLight ? '#0284C7' : '#399AFF';
    var expectedCol = isLight ? '#d97706' : '#FFD44A';
    var axisTick = isLight ? '#1e293b' : '#CFE3FF';

    var option = {
      tooltip: {
        trigger: 'axis',
        formatter: function (params) {
          var res = params[0].name + '<br/>';
          for (var i = 0; i < params.length; i++) {
            res += params[i].marker + ' ' + params[i].seriesName + ': ' + (params[i].value == null ? '—' : params[i].value + '%') + '<br/>';
          }
          return res;
        },
        backgroundColor: isLight ? 'rgba(15,23,42,0.92)' : 'rgba(8,12,20,0.92)',
        textStyle: {
          color: '#E6F0FF'
        },
        borderColor: isLight ? 'rgba(255,255,255,0.15)' : 'rgba(255,255,255,0.12)',
        borderWidth: 1,
        padding: 10
      },
      legend: {
        data: [p.actualLabel || 'Actual', 'Expected'],
        textStyle: {
          color: axisTick
        }
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: p.labels,
        axisLabel: {
            color: axisTick,
            rotate: 45,
            interval: 0
        },
        axisLine: {
            lineStyle: {
                color: axisTick
            }
        }
      },
      yAxis: {
        type: 'value',
        min: 0,
        max: 100,
        axisLabel: {
            formatter: '{value} %',
            color: axisTick
        },
        splitLine: {
          lineStyle: {
            color: isLight ? '#e5e7eb' : 'rgba(255,255,255,0.08)'
          }
        }
      },
      series: [
        {
          name: p.actualLabel || 'Actual',
          type: 'line',
          smooth: true,
          lineStyle: {
            color: actualCol,
            width: 3
          },
          itemStyle: {
              color: actualCol
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
              offset: 0,
              color: isLight ? 'rgba(2,132,199,0.18)' : 'rgba(57,154,255,0.18)'
            }, {
              offset: 1,
              color: 'rgba(0,0,0,0)'
            }])
          },
          data: p.actual
        },
        {
          name: 'Expected',
          type: 'line',
          smooth: true,
          lineStyle: {
            color: expectedCol,
            type: 'dashed',
            width: 2
          },
          itemStyle: {
              color: expectedCol
          },
          data: p.expected
        }
      ]
    };
    if (myChart) {
      myChart.setOption(option, true);
    } else {
      myChart = echarts.init(chartDom);
      myChart.setOption(option);
    }
  }

  function drawStudent(){
    var option = {
      tooltip: {
        trigger: 'axis',
        formatter: function (params) {
          var value = params[0].value;
          return params[0].name + '<br/>Grade (%): ' + (value == null ? 'No grade' : value + '%');
        },
        backgroundColor: 'rgba(0,0,0,0.7)',
        textStyle: {
          color: '#fff'
        }
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: p.labels,
        axisLabel: {
          rotate: 60,
          interval: 0
        }
      },
      yAxis: {
        type: 'value',
        min: 0,
        max: 100,
        axisLabel: {
          formatter: '{value} %'
        },
        splitLine: {
          lineStyle: {
            color: 'rgba(0,0,0,.06)'
          }
        }
      },
      series: [
        {
          name: 'Grade (%)',
          type: 'line',
          smooth: true,
          lineStyle: {
            color: '#2ECC71',
            width: 3
          },
          itemStyle: {
              color: '#2ECC71'
          },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{
              offset: 0,
              color: 'rgba(46,204,113,0.28)'
            }, {
              offset: 1,
              color: 'rgba(46,204,113,0.00)'
            }])
          },
          data: p.series
        }
      ]
    };
    if (myChart) {
      myChart.setOption(option, true);
    } else {
      myChart = echarts.init(chartDom);
      myChart.setOption(option);
    }
  }

  function start(){
    if (typeof echarts !== 'undefined') {
      return (p.mode === 'teacher' ? drawTeacher() : drawStudent());
    }
    noAMD('/blocks/gradeheatmap/js/echarts.min.js', function(err){
      if (err || typeof echarts === 'undefined'){
        noAMD('https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js',
          function(){ (p.mode === 'teacher' ? drawTeacher() : drawStudent()); });
      } else { (p.mode === 'teacher' ? drawTeacher() : drawStudent()); }
    });
  }
  start();
})();
JS;

        $PAGE->requires->js_init_code($init);

        $this->content->footer = '';
        return $this->content;
    }
}