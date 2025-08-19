<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        $this->title = 'Grade Trend';
    }

    // Dashboard only
    public function applicable_formats() {
        return ['my' => true];
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE;

        if ($this->content !== null) return $this->content;
        $this->content = new stdClass();

        require_once($CFG->dirroot . '/course/lib.php');
        $PAGE->requires->css(new moodle_url('/blocks/gradeheatmap/styles.css'));

        // Who am I? Can I see everyone’s grades?
        $systemcanviewall = is_siteadmin($USER) ||
            has_capability('moodle/grade:viewall', context_system::instance());

        // Courses where current user has grade:viewall (teacher role)
        $teachergradecourses = [];
        $enrolled = enrol_get_users_courses($USER->id, true, 'id,shortname');
        foreach ($enrolled as $c) {
            $ctx = context_course::instance($c->id);
            if (has_capability('moodle/grade:viewall', $ctx)) {
                $teachergradecourses[$c->id] = $c->shortname;
            }
        }

        // Teacher/Admin vs Student mode
        $mode = (!empty($teachergradecourses) || $systemcanviewall) ? 'teacher' : 'student';

        // Build list of courses that actually have numeric grade items
        $courseoptions = [];
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
        } elseif (!empty($teachergradecourses)) {
            list($inSql,$inParams) = $DB->get_in_or_equal(array_keys($teachergradecourses), SQL_PARAMS_NAMED, 'cid');
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

        // Which course is selected (teacher/admin)?
        $selectedcourseid = 0;
        if ($mode === 'teacher') {
            $selectedcourseid = optional_param('ghm_courseid', 0, PARAM_INT);
            if (!$selectedcourseid && !empty($courseoptions)) {
                $selectedcourseid = (int)array_key_first($courseoptions);
            }
        }

        // Canvas + top bar (always show dropdown in teacher/admin)
        $canvasid = html_writer::random_id('ghm_');
        $canvas   = html_writer::tag('canvas', '', ['id'=>$canvasid]);

        $topbar = '';
        if ($mode === 'teacher') {
            $opts = '';
            foreach ($courseoptions as $cid=>$short) {
                $sel = ($cid==$selectedcourseid) ? ' selected' : '';
                $opts .= "<option value=\"$cid\"$sel>".s($short)."</option>";
            }
            if ($opts === '') { $opts = '<option value="" disabled>(No courses with grade data)</option>'; }

            $topbar = '
              <div class="ghm-topbar dark">
                <label class="ghm-label">Course:</label>
                <select class="ghm-select" id="ghm-course-select-'.$canvasid.'">'.$opts.'</select>
              </div>';
        }

        $wrap = html_writer::tag('div', $topbar.$canvas, [
            'class'=> ($mode==='teacher' ? 'heatmap-wrap dark' : 'heatmap-wrap')
        ]);
        $this->content->text = html_writer::div($wrap, 'block_gradeheatmap');

        // ----- Build data payload -----
        if ($mode === 'teacher' && $selectedcourseid) {
            // TEACHER/ADMIN: average % per item (include course total but label it safely)
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
               WHERE gi.courseid=:cid
                 AND gi.itemtype IN ('mod','manual','course')
                 AND gi.gradetype = 1
                 AND gg.finalgrade IS NOT NULL
            GROUP BY gi.id, gi.itemname, gi.sortorder
            ORDER BY gi.sortorder, gi.id
            ", ['cid'=>$selectedcourseid]);

            $labels=[]; $actual=[];
            if ($rows) {
                foreach ($rows as $r) {
                    $iname = trim($r->itemname ?? '');
                    if ($iname === '') $iname = 'Item #'.$r->id;
                    $labels[] = $iname;
                    $actual[] = $r->percent === null ? null : (float)$r->percent;
                }
            }
            // Expected line - simple constant target (60%)
            $expected = array_fill(0, max(1,count($labels)), 60.0);

            if (empty($labels)) { // safe demo fallback
                $labels   = ['Demo: Quiz 1','Demo: Assign 1','Demo: Final'];
                $actual   = [48.0, 73.0, 66.0];
                $expected = [60.0, 60.0, 60.0];
            }

            $payload = [
                'mode'     => 'teacher',
                'chart'    => 'curves',
                'canvasid' => $canvasid,
                'labels'   => $labels,
                'actual'   => $actual,
                'expected' => $expected
            ];

        } else {
            // STUDENT: their own grades across all enrolled courses (smooth line)
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
                    $iname = trim($r->itemname ?? '');
                    if ($iname === '') $iname = 'Item #'.$r->id;
                    $labels[] = trim($c.': '.$iname);
                    $series[] = ($r->grademax>0 && $r->finalgrade!==null)
                        ? (float)round(($r->finalgrade/$r->grademax)*100,1) : null;
                }
            }
            if (empty($labels)) { $labels=['Demo: Quiz 1','Demo: Assign 1','Demo: Final']; $series=[62.0,84.0,null]; }

            $payload = [
                'mode'     => 'student',
                'canvasid' => $canvasid,
                'labels'   => $labels,
                'series'   => $series
            ];
        }

        $json = json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        // ----- JS (no-AMD loader + high-contrast curves) -----
        $init = <<<JS
(function(){
  var p = $json;
  var cv = document.getElementById(p.canvasid);
  if (!cv) return;
  var ctx = cv.getContext('2d');

  // Course dropdown reload
  var sel = document.getElementById('ghm-course-select-'+p.canvasid);
  if (sel) sel.addEventListener('change', function(){
    var url = new URL(window.location.href);
    url.searchParams.set('ghm_courseid', this.value);
    window.location.href = url.toString();
  });

  // Load Chart.js without AMD conflicts
  function noAMD(src, cb){
    var od=window.define, om=window.module, oe=window.exports;
    try{ window.define=undefined; window.module=undefined; window.exports=undefined; }catch(e){}
    var s=document.createElement('script'); s.src=src; s.async=true;
    s.onload=function(){ window.define=od; window.module=om; window.exports=oe; cb(); };
    s.onerror=function(){ window.define=od; window.module=om; window.exports=oe; cb(new Error('loadfail '+src)); };
    document.head.appendChild(s);
  }

  // -------- Teacher/Admin (dual curves, glow, high contrast) --------
  function drawTeacherCurves(){
    if (typeof Chart==='undefined'){
      cv.insertAdjacentHTML('beforebegin','<div style="color:#f55">Chart.js missing</div>');
      return;
    }

    var count = (p.labels||[]).length;
    cv.width  = Math.max(900, count*70);
    cv.height = 340;

    // Detect bg for contrast choice
    function parseRGB(str){ var m=/rgba?\\((\\d+),\\s*(\\d+),\\s*(\\d+)/.exec(str||''); return m?{r:+m[1],g:+m[2],b:+m[3]}:{r:255,g:255,b:255}; }
    function luma(rgb){ return 0.2126*rgb.r + 0.7152*rgb.g + 0.0722*rgb.b; }
    var bg = getComputedStyle(cv.parentElement).backgroundColor;
    var isLight = luma(parseRGB(bg)) > 200;

    var axisTick  = isLight ? '#1e293b' : '#CFE3FF';
    var gridColor = isLight ? '#e5e7eb' : 'rgba(255,255,255,0.08)';
    var actualCol = isLight ? '#0284C7' : '#399AFF';
    var fillTop   = isLight ? 'rgba(2,132,199,0.18)' : 'rgba(57,154,255,0.18)';
    var expectedCol = isLight ? '#d97706' : '#FFD44A';

    var hasActual = Array.isArray(p.actual) && p.actual.some(v => v !== null && !isNaN(v));
    if (!hasActual && (!p.expected || !p.expected.length)) {
      cv.insertAdjacentHTML('afterend','<div style="margin-top:6px;color:'+axisTick+'">No grade data yet for this course.</div>');
    }

    const hoverBand = {
      id: 'hoverBand',
      afterDatasetsDraw(chart){
        const {ctx, tooltip, chartArea:{top,bottom}} = chart;
        if (!tooltip || !tooltip.getActiveElements().length) return;
        const x = tooltip.caretX;
        ctx.save();
        ctx.fillStyle = isLight ? 'rgba(2,132,199,0.06)' : 'rgba(180,200,255,0.07)';
        ctx.fillRect(x-28, top, 56, bottom-top);
        ctx.restore();
      }
    };

    const glow = {
      id: 'glow',
      beforeDatasetsDraw(chart, args){
        const m = chart.getDatasetMeta(0);
        if (!m || !m.dataset) return;
        const {ctx} = chart;
        ctx.save();
        ctx.shadowColor = isLight ? 'rgba(2,132,199,0.35)' : 'rgba(57,154,255,0.6)';
        ctx.shadowBlur = 12;
        m.dataset.draw(ctx, args, {});
        ctx.restore();
      }
    };

    var g = ctx.createLinearGradient(0,0,0,cv.height);
    g.addColorStop(0, fillTop);
    g.addColorStop(1, 'rgba(0,0,0,0)');

    new Chart(ctx,{
      type:'line',
      plugins:[hoverBand, glow],
      data:{
        labels: p.labels,
        datasets:[
          {
            label:'Actual',
            data:p.actual || [],
            tension:.42,
            cubicInterpolationMode:'monotone',
            borderColor: actualCol,
            backgroundColor: g,
            borderWidth: 3,
            pointRadius: 4,
            pointBackgroundColor: actualCol,
            spanGaps: true,
            fill:true
          },
          {
            label:'Expected',
            data:p.expected || [],
            tension:.42,
            borderColor: expectedCol,
            borderDash:[6,6],
            borderWidth:2,
            pointRadius:0,
            fill:false
          }
        ]
      },
      options:{
        responsive:true, maintainAspectRatio:false, animation:{duration:500},
        layout:{padding:{left:8,right:8,top:0,bottom:0}},
        scales:{
          y:{min:0,max:100,grid:{color:gridColor},ticks:{color:axisTick,callback:v=>v+'%'}},
          x:{grid:{display:false},ticks:{color:axisTick,autoSkip:false,maxRotation:0}}
        },
        plugins:{
          legend:{labels:{color:axisTick}},
          tooltip:{
            padding:10,
            backgroundColor: isLight ? 'rgba(15,23,42,0.92)' : 'rgba(8,12,20,0.92)',
            titleColor:'#E6F0FF', bodyColor:'#CFE3FF',
            borderColor: isLight ? 'rgba(255,255,255,0.15)' : 'rgba(255,255,255,0.12)',
            borderWidth:1,
            callbacks:{ label:c => c.dataset.label+': '+(c.parsed.y==null?'—':c.parsed.y+'%') }
          }
        }
      }
    });
  }

  // -------- Student (single green smooth line) --------
  function drawStudent(){
    if (typeof Chart==='undefined'){
      cv.insertAdjacentHTML('beforebegin','<div style="color:#a00">Chart.js missing</div>');
      return;
    }
    cv.width  = Math.max(900,(p.labels||[]).length*60);
    cv.height = 320;

    var g = ctx.createLinearGradient(0,0,0,cv.height);
    g.addColorStop(0,'rgba(46,204,113,0.28)');
    g.addColorStop(1,'rgba(46,204,113,0.00)');

    new Chart(ctx,{
      type:'line',
      data:{ labels:p.labels, datasets:[{
        label:'Grade (%)',
        data:p.series,
        spanGaps:true,
        tension:.35,
        cubicInterpolationMode:'monotone',
        borderColor:'#2ECC71',
        backgroundColor:g,
        borderWidth:3,
        pointRadius:4,
        fill:true
      }]},
      options:{
        responsive:true, maintainAspectRatio:false, animation:{duration:500},
        scales:{
          y:{min:0,max:100,grid:{color:'rgba(0,0,0,.06)'},ticks:{callback:v=>v+'%'}},
          x:{grid:{display:false},ticks:{autoSkip:false,maxRotation:60,minRotation:0}}
        },
        plugins:{ legend:{display:false},
          tooltip:{callbacks:{ label:c => (c.parsed.y==null ? 'No grade' : c.parsed.y+'%') }}
        }
      }
    });
  }

  function start(){
    if (typeof Chart!=='undefined') return (p.mode==='teacher' ? drawTeacherCurves() : drawStudent());
    noAMD('/blocks/gradeheatmap/js/chart.umd.min.js', function(err){
      if (err || typeof Chart==='undefined'){
        noAMD('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js',
          function(){ (p.mode==='teacher' ? drawTeacherCurves() : drawStudent()); });
      } else { (p.mode==='teacher' ? drawTeacherCurves() : drawStudent()); }
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

