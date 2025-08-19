<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        $this->title = 'Grade Trend';
    }

    public function applicable_formats() {
        return ['my' => true]; // Dashboard only
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE;

        if ($this->content !== null) return $this->content;
        $this->content = new stdClass();

        require_once($CFG->dirroot . '/course/lib.php');
        $PAGE->requires->css(new moodle_url('/blocks/gradeheatmap/styles.css'));

        // Detect privileges
        $systemcanviewall = is_siteadmin($USER) ||
            has_capability('moodle/grade:viewall', context_system::instance());

        // Courses where this user can view all grades (teacher)
        $teachergradecourses = [];
        $enrolled = enrol_get_users_courses($USER->id, true, 'id,shortname');
        foreach ($enrolled as $c) {
            $ctx = context_course::instance($c->id);
            if (has_capability('moodle/grade:viewall', $ctx)) {
                $teachergradecourses[$c->id] = $c->shortname;
            }
        }

        // Decide mode
        $mode = (!empty($teachergradecourses) || $systemcanviewall) ? 'teacher' : 'student';

        // Build course list with real grade data
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

        // Selected course for teacher/admin
        $selectedcourseid = 0;
        if ($mode === 'teacher') {
            $selectedcourseid = optional_param('ghm_courseid', 0, PARAM_INT);
            if (!$selectedcourseid && !empty($courseoptions)) {
                $selectedcourseid = (int)array_key_first($courseoptions);
            }
        }

        // Container
        $canvasid = html_writer::random_id('ghm_');
        $canvas   = html_writer::tag('canvas', '', ['id'=>$canvasid]);

        $topbar = '';
        if ($mode === 'teacher' && count($courseoptions) > 1) {
            $opts = '';
            foreach ($courseoptions as $cid=>$short) {
                $sel = ($cid==$selectedcourseid) ? ' selected' : '';
                $opts .= "<option value=\"$cid\"$sel>".s($short)."</option>";
            }
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

        // Build payload
        if ($mode === 'teacher' && $selectedcourseid) {
            // Average percent per activity (ACTUAL)
            $rows = $DB->get_records_sql("
                SELECT
                  COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule,' #',gi.id)) AS itemname,
                  ROUND(AVG(gg.finalgrade/NULLIF(gi.grademax,0))*100,1) AS percent,
                  gi.sortorder, gi.id
                FROM {grade_items} gi
                JOIN {grade_grades} gg ON gg.itemid=gi.id
               WHERE gi.courseid=:cid
                 AND gi.itemtype IN ('mod','manual','course')
                 AND gi.gradetype=1
            GROUP BY gi.id, gi.itemname, gi.sortorder
            ORDER BY gi.sortorder, gi.id
            ", ['cid'=>$selectedcourseid]);

            $labels=[]; $actual=[];
            if ($rows) {
                foreach ($rows as $r) { $labels[] = trim($r->itemname); $actual[] = $r->percent===null?null:(float)$r->percent; }
            }
            // Expected line: simple constant target (e.g., pass mark 60). You can compute something smarter if you like.
            $expected = array_fill(0, max(1,count($labels)), 60.0);

            if (empty($labels)) {
                $labels   = ['Demo: Quiz 1','Demo: Assign 1','Demo: Final'];
                $actual   = [48.0, 73.0, 66.0];
                $expected = [60.0, 60.0, 60.0];
            }

            $payload = [
                'mode'     => 'teacher',       // still teacher but using “curves” visualization
                'chart'    => 'curves',
                'canvasid' => $canvasid,
                'labels'   => $labels,
                'actual'   => $actual,
                'expected' => $expected
            ];

        } else {
            // Student: their own grades across all enrolled courses (smooth line)
            $labels=[]; $series=[];
            if (!empty($enrolled)) {
                $courseids = array_keys($enrolled);
                list($inSql,$inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
                $rows = $DB->get_records_sql("
                    SELECT gi.id AS id,
                           gi.courseid,
                           COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule,' #',gi.id)) AS itemname,
                           gi.sortorder, gi.grademax, gg.finalgrade
                      FROM {grade_items} gi
                      JOIN {grade_grades} gg ON gg.itemid=gi.id AND gg.userid=:uid
                     WHERE gi.courseid $inSql
                       AND gi.itemtype IN ('mod','manual','course')
                       AND gi.gradetype=1
                  ORDER BY gi.courseid, gi.sortorder, gi.id
                ", array_merge($inParams,['uid'=>$USER->id]));

                foreach ($rows as $r) {
                    $c = $enrolled[$r->courseid]->shortname ?? ('C'.$r->courseid);
                    $labels[] = trim($c.': '.$r->itemname);
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

        // JS (AMD-safe)
        $init = <<<JS
(function(){
  var p = $json;
  var cv = document.getElementById(p.canvasid);
  if (!cv) return;
  var ctx = cv.getContext('2d');

  // Dropdown listener
  var sel = document.getElementById('ghm-course-select-'+p.canvasid);
  if (sel) sel.addEventListener('change', function(){
    var url = new URL(window.location.href);
    url.searchParams.set('ghm_courseid', this.value);
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

  // Dark theme gradients & glow for “curves”
  function drawTeacherCurves(){
    if (typeof Chart==='undefined'){ cv.insertAdjacentHTML('beforebegin','<div style="color:#f55">Chart.js missing</div>'); return; }
    var count = (p.labels||[]).length;
    cv.width  = Math.max(900, count*70);
    cv.height = 340;

    // Create vertical hover band plugin
    const hoverBand = {
      id: 'hoverBand',
      afterDatasetsDraw(chart, args, pluginOptions) {
        const {ctx, tooltip, chartArea:{top,bottom}} = chart;
        if (!tooltip || !tooltip.getActiveElements().length) return;
        const x = tooltip.caretX;
        ctx.save();
        ctx.fillStyle = 'rgba(180,200,255,0.07)';
        ctx.fillRect(x-30, top, 60, bottom-top);
        ctx.restore();
      }
    };

    // Blue glow stroke
    const glow = {
      id: 'glow',
      beforeDatasetsDraw(chart, args, opts){
        const {ctx, chartArea:{left,right,top,bottom}} = chart;
        const ds = chart.getDatasetMeta(0);
        if (!ds || !ds.dataset) return;
        ctx.save();
        ctx.shadowColor = 'rgba(57,154,255,0.6)';
        ctx.shadowBlur = 15;
        ds.dataset.draw(ctx, args, opts);
        ctx.restore();
      }
    };

    const bg = ctx.createLinearGradient(0,0,0,cv.height);
    bg.addColorStop(0,'rgba(57,154,255,0.18)');
    bg.addColorStop(1,'rgba(57,154,255,0.00)');

    new Chart(ctx,{
      type:'line',
      plugins:[hoverBand, glow],
      data:{
        labels:p.labels,
        datasets:[
          { // ACTUAL (solid blue with glow)
            label:'Actual',
            data:p.actual,
            tension:.42,
            cubicInterpolationMode:'monotone',
            borderColor:'#399AFF',
            backgroundColor:bg,
            borderWidth:3,
            pointRadius:4,
            pointBackgroundColor:'#399AFF',
            fill:true
          },
          { // EXPECTED (dashed yellow)
            label:'Expected',
            data:p.expected,
            tension:.42,
            borderColor:'#FFD44A',
            borderDash:[6,6],
            pointRadius:0,
            borderWidth:2,
            fill:false
          }
        ]
      },
      options:{
        responsive:true, maintainAspectRatio:false, animation:{duration:500},
        layout:{padding:{left:8,right:8,top:0,bottom:0}},
        scales:{
          y:{min:0,max:100,grid:{color:'rgba(255,255,255,0.06)'},
             ticks:{color:'#CFE3FF', callback:v=>v+'%'}},
          x:{grid:{display:false},ticks:{color:'#CFE3FF', autoSkip:false, maxRotation:0}}
        },
        plugins:{
          legend:{labels:{color:'#E6F0FF'}},
          tooltip:{
            padding:10,
            backgroundColor:'rgba(8,12,20,0.92)',
            titleColor:'#E6F0FF',
            bodyColor:'#CFE3FF',
            borderColor:'rgba(255,255,255,0.12)',
            borderWidth:1,
            callbacks:{
              label: c => c.dataset.label+': '+(c.parsed.y==null?'—':c.parsed.y+'%')
            }
          }
        }
      }
    });
  }

  function drawStudent(){
    if (typeof Chart==='undefined'){ cv.insertAdjacentHTML('beforebegin','<div style="color:#a00">Chart.js missing</div>'); return; }
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
          y:{min:0,max:100,grid:{color:'rgba(0,0,0,.06)'},
             title:{display:true,text:'Grade (%)'},ticks:{callback:v=>v+'%'}},
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
