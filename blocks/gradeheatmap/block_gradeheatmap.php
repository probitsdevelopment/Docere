<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        $this->title = 'Grade Trend and Login Activity';
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

        // Chart container IDs
        $chartcontainerid = html_writer::random_id('ghm_');
        $loginchartcontainerid = html_writer::random_id('ghm_login_');
        $chartcontainer = html_writer::tag('div', '', ['id' => $chartcontainerid, 'style' => 'width:100%;height:350px;']);
        $loginchartcontainer = html_writer::tag('div', '', ['id' => $loginchartcontainerid, 'style' => 'width:100%;height:350px; margin-top: 20px;']);

        $topbar = '';
        if ($mode === 'teacher') {
            // Course select
            $opts = '';
            foreach ($courseoptions as $cid => $short) {
                $sel = ($cid == $selectedcourseid) ? ' selected' : '';
                $opts .= "<option value=\"$cid\"$sel>".s($short)."</option>";
            }
            if ($opts === '') $opts = '<option value="" disabled>(No courses with grade data)</option>';

            // Student select (0 = average)
            $uopts = '<option value="0"'.($selecteduserid == 0 ? ' selected' : '').'>All students — Average</option>';
            foreach ($studentoptions as $uid => $name) {
                $sel = ($uid == $selecteduserid) ? ' selected' : '';
                $uopts .= "<option value=\"$uid\"$sel>".s($name)."</option>";
            }

            $topbar = '
               <div class="ghm-topbar dark">
                 <label class="ghm-label">Course:</label>
                 <select class="ghm-select" id="ghm-course-select-'.$chartcontainerid.'">'.$opts.'</select>

                 <label class="ghm-label" style="margin-left:12px">Student:</label>
                 <select class="ghm-select" id="ghm-user-select-'.$chartcontainerid.'">'.$uopts.'</select>
                 
                 <div class="ghm-toggle-wrap">
                     <label class="switch">
                         <input type="checkbox" id="darkModeToggle">
                         <span class="slider round"></span>
                     </label>
                     <label for="darkModeToggle" class="ghm-label">Dark Mode</label>
                 </div>
               </div>';
        } else {
            // Dark Mode Toggle for student view
            $topbar = '
               <div class="ghm-topbar">
                 <div class="ghm-toggle-wrap">
                     <label class="switch">
                         <input type="checkbox" id="darkModeToggle">
                         <span class="slider round"></span>
                     </label>
                     <label for="darkModeToggle" class="ghm-label">Dark Mode</label>
                 </div>
               </div>';
        }

        $wrap = html_writer::tag('div', $topbar . $chartcontainer . $loginchartcontainer, [
            'class' => ($mode === 'teacher' ? 'heatmap-wrap dark' : 'heatmap-wrap')
        ]);
        $this->content->text = html_writer::div($wrap, 'block_gradeheatmap');

        // ----------------- DATA PAYLOAD -----------------
        $selected_user_id = ($mode === 'teacher' && $selecteduserid > 0) ? $selecteduserid : $USER->id;

        // Fetch login activity for the last 7 days, grouped by day of the week
        $logins = $DB->get_records_sql("
            SELECT
                DAYOFWEEK(FROM_UNIXTIME(timecreated)) AS dayofweek,
                COUNT(id) AS login_count
            FROM
                {logstore_standard_log}
            WHERE
                userid = :userid AND
                eventname = '\\core\\event\\user_loggedin' AND
                timecreated > UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY)
            GROUP BY
                dayofweek
            ORDER BY
                dayofweek ASC
        ", ['userid' => $selected_user_id]);

        $login_labels = [];
        $login_series = [];
        if ($logins) {
            foreach ($logins as $login) {
                $login_labels[] = (int)$login->dayofweek;
                $login_series[] = (int)$login->login_count;
            }
        }

        // Generate dummy data if no real data exists
        if (empty($login_labels)) {
            $login_labels = [1, 2, 3, 4, 5, 6, 7];
            $login_series = [rand(0, 5), rand(0, 5), rand(0, 5), rand(0, 5), rand(0, 5), rand(0, 5), rand(0, 5)];
        }

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
                $labels = ['Demo: Quiz 1','Demo: Assign 1','Demo: Final'];
                $actual = [48.0,73.0,66.0];
            }

            $payload = [
                'mode'             => 'teacher',
                'chartcontainerid' => $chartcontainerid,
                'loginchartcontainerid' => $loginchartcontainerid,
                'labels'           => $labels,
                'actual'           => $actual,
                'actualLabel'      => $actualLabel,
                'loginlabels'      => $login_labels,
                'loginseries'      => $login_series
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
                'mode'             => 'student',
                'chartcontainerid' => $chartcontainerid,
                'loginchartcontainerid' => $loginchartcontainerid,
                'labels'           => $labels,
                'series'           => $series,
                'loginlabels'      => $login_labels,
                'loginseries'      => $login_series
            ];
        }

        $json = json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        // ----------------- JS -----------------
        $init = <<<JS
(function(){
  var p = {$json};
  var chartDom = document.getElementById(p.chartcontainerid);
  var loginChartDom = document.getElementById(p.loginchartcontainerid);
  var myChart = null;
  var myLoginChart = null;
  var darkModeToggle = document.getElementById('darkModeToggle');

  // Change handlers: keep both params
  var csel = document.getElementById('ghm-course-select-'+p.chartcontainerid);
  if (csel) csel.addEventListener('change', function(){
    var url = new URL(window.location.href);
    url.searchParams.set('ghm_courseid', this.value);
    var usel = document.getElementById('ghm-user-select-'+p.chartcontainerid);
    if (usel) url.searchParams.set('ghm_userid', usel.value || 0);
    window.location.href = url.toString();
  });
  var usel = document.getElementById('ghm-user-select-'+p.chartcontainerid);
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

  function renderCharts() {
    var theme = darkModeToggle.checked ? 'dark' : 'light';
    var body = document.body;
    if (darkModeToggle.checked) {
        body.classList.add('dark-mode');
    } else {
        body.classList.remove('dark-mode');
    }
    
    // Dispose and re-initialize charts with the new theme
    if (myChart) {
      myChart.dispose();
      myChart = null;
    }
    if (myLoginChart) {
      myLoginChart.dispose();
      myLoginChart = null;
    }
    
    if (p.mode === 'teacher') {
      drawTeacher(theme);
    } else {
      drawStudent(theme);
    }
    drawLoginChart(theme);
  }

  if (darkModeToggle) {
      darkModeToggle.addEventListener('change', function() {
          renderCharts();
      });
  }

  function drawTeacher(theme){
    if (!chartDom) return;
    var successColor = theme === 'dark' ? '#4ade80' : '#10B981';
    var averageColor = theme === 'dark' ? '#FBBF24' : '#F59E0B';
    var failColor = theme === 'dark' ? '#F87171' : '#EF4444';
    var axisTick = theme === 'dark' ? '#CFE3FF' : '#1e293b';

    var option = {
      tooltip: {
        trigger: 'axis',
        formatter: function (params) {
          var value = params[0].value;
          return params[0].name + '<br/>Grade (%): ' + (value == null ? '—' : value + '%');
        },
        backgroundColor: theme === 'dark' ? 'rgba(8,12,20,0.92)' : 'rgba(15,23,42,0.92)',
        textStyle: {
          color: theme === 'dark' ? '#E6F0FF' : '#E6F0FF'
        },
        borderColor: theme === 'dark' ? 'rgba(255,255,255,0.12)' : 'rgba(255,255,255,0.15)',
        borderWidth: 1,
        padding: 10
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
            color: theme === 'dark' ? 'rgba(255,255,255,0.08)' : '#e5e7eb'
          }
        }
      },
      visualMap: {
        show: false,
        type: 'piecewise',
        seriesIndex: 0,
        pieces: [
          { gt: 80, color: successColor },
          { gt: 50, lt: 80, color: averageColor },
          { lt: 50, color: failColor }
        ],
        outOfRange: {
          color: failColor
        }
      },
      series: [
        {
          name: p.actualLabel || 'Actual',
          type: 'line',
          smooth: true,
          areaStyle: {
            opacity: 0.8
          },
          lineStyle: {
            width: 0 
          },
          data: p.actual
        }
      ]
    };
    myChart = echarts.init(chartDom, theme === 'dark' ? 'dark' : null);
    myChart.setOption(option);
  }

  function drawStudent(theme){
    if (!chartDom) return;
    var successColor = theme === 'dark' ? '#4ade80' : '#10B981';
    var averageColor = theme === 'dark' ? '#FBBF24' : '#F59E0B';
    var failColor = theme === 'dark' ? '#F87171' : '#EF4444';
    var axisTick = theme === 'dark' ? '#CFE3FF' : '#1e293b';

    var option = {
      tooltip: {
        trigger: 'axis',
        formatter: function (params) {
          var value = params[0].value;
          return params[0].name + '<br/>Grade (%): ' + (value == null ? 'No grade' : value + '%');
        },
        backgroundColor: theme === 'dark' ? 'rgba(8,12,20,0.92)' : 'rgba(15,23,42,0.92)',
        textStyle: {
          color: theme === 'dark' ? '#E6F0FF' : '#E6F0FF'
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
          interval: 0,
          color: axisTick
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
            color: theme === 'dark' ? 'rgba(255,255,255,0.08)' : '#e5e7eb'
          }
        }
      },
      visualMap: {
        show: false,
        type: 'piecewise',
        seriesIndex: 0,
        pieces: [
          { gt: 80, color: successColor },
          { gt: 50, lt: 80, color: averageColor },
          { lt: 50, color: failColor }
        ],
        outOfRange: {
          color: failColor
        }
      },
      series: [
        {
          name: 'Grade (%)',
          type: 'line',
          smooth: true,
          areaStyle: {
            opacity: 0.8
          },
          lineStyle: {
              width: 0 
          },
          data: p.series
        }
      ]
    };
    myChart = echarts.init(chartDom, theme === 'dark' ? 'dark' : null);
    myChart.setOption(option);
  }

  function drawLoginChart(theme){
    if (!loginChartDom) return;
    var barColor = theme === 'dark' ? '#399AFF' : '#0284C7';
    var axisTick = theme === 'dark' ? '#CFE3FF' : '#1e293b';

    // Map day of week number to name (1=Sun, 2=Mon...)
    var daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var loginLabels = p.loginlabels.map(function(dayIndex) {
      return daysOfWeek[dayIndex - 1];
    });

    var loginOption = {
      title: {
        text: 'Login Activity (Last 7 Days)',
        textStyle: {
          color: axisTick
        }
      },
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow'
        },
        formatter: function(params) {
          var value = params[0].value;
          return params[0].name + '<br/>Logins: ' + value;
        },
        backgroundColor: 'rgba(0,0,0,0.7)',
        textStyle: {
          color: '#fff'
        }
      },
      xAxis: {
        type: 'category',
        data: loginLabels,
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
        name: 'Login Count',
        nameTextStyle: {
            color: axisTick
        },
        axisLabel: {
            color: axisTick
        },
        splitLine: {
            lineStyle: {
                color: theme === 'dark' ? 'rgba(255,255,255,0.08)' : '#e5e7eb'
            }
        }
      },
      series: [
        {
          name: 'Logins',
          type: 'bar',
          data: p.loginseries,
          itemStyle: {
            color: barColor
          }
        }
      ]
    };
    myLoginChart = echarts.init(loginChartDom, theme === 'dark' ? 'dark' : null);
    myLoginChart.setOption(loginOption);
  }

  function start(){
    if (typeof echarts !== 'undefined') {
      var isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (darkModeToggle) {
          darkModeToggle.checked = isDarkMode;
      }
      renderCharts();
      return;
    }
    noAMD('/blocks/gradeheatmap/js/echarts.min.js', function(err){
      if (err || typeof echarts === 'undefined'){
        noAMD('https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js',
          function(){
            // Load ECharts dark theme
            noAMD('https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/dark.js',
              function(){
                var isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (darkModeToggle) {
                    darkModeToggle.checked = isDarkMode;
                }
                renderCharts();
              });
          });
      } else {
        noAMD('https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/dark.js',
          function(){
            var isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (darkModeToggle) {
                darkModeToggle.checked = isDarkMode;
            }
            renderCharts();
          });
      }
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