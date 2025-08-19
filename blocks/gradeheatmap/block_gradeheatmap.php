<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() { $this->title = 'Grade Trend'; }
    public function applicable_formats() { return ['my' => true]; } // dashboard only

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE;

        if ($this->content !== null) return $this->content;
        $this->content = new stdClass();

        // Simple card styling.
        $PAGE->requires->css(new moodle_url('/blocks/gradeheatmap/styles.css'));

        require_once($CFG->dirroot . '/course/lib.php');

        $courses = enrol_get_users_courses($USER->id, true, 'id,shortname');
        $mode = 'student';
        $courseid = null;
        $courseshort = '';

        // If user can view all grades in any course, show teacher/admin view for that first course.
        foreach ($courses as $c) {
            $ctx = context_course::instance($c->id);
            if (has_capability('moodle/grade:viewall', $ctx)) {
                $mode = 'teacher';
                $courseid = $c->id;
                $courseshort = $c->shortname;
                break;
            }
        }

        $canvasid = html_writer::random_id('gradeline_');
        $canvas   = html_writer::tag('canvas', '', ['id'=>$canvasid]);
        $wrap     = html_writer::tag('div', $canvas, ['class'=>'heatmap-wrap']);
        $this->content->text = html_writer::div($wrap, 'block_gradeheatmap');

        if ($mode === 'teacher' && $courseid) {
            // ===== TEACHER / ADMIN: Average line (+ optional faint student lines) =====
            $params = ['courseid'=>$courseid];
            $sql = "SELECT
                        gi.id AS id,
                        gi.courseid,
                        COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule,' #',gi.id)) AS itemname,
                        gi.sortorder, gi.grademax,
                        gg.finalgrade, gg.userid,
                        CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,'')) AS student
                    FROM {grade_items} gi
                    JOIN {grade_grades} gg ON gg.itemid = gi.id
                    JOIN {user} u ON u.id = gg.userid
                   WHERE gi.courseid = :courseid
                     AND gi.itemtype IN ('mod','manual','course')
                     AND gi.gradetype = 1
                ORDER BY gi.sortorder, gi.id, u.lastname, u.firstname";

            $rows = $DB->get_records_sql($sql, $params);
            $labels = []; // activities
            $students = []; // name => [itemname => percent]
            if ($rows) {
                $labels = array_values(array_unique(array_map(fn($r)=>trim($r->itemname), $rows)));
                foreach ($rows as $r) {
                    $name = trim($r->student);
                    $pct  = ($r->grademax > 0 && $r->finalgrade !== null) ? round(($r->finalgrade/$r->grademax)*100,1) : null;
                    $students[$name][trim($r->itemname)] = $pct;
                }
            }

            // Build average per activity & optional student series.
            $avg = [];
            $MAXSTUDENTLINES = 12;        // ← change to 0 if you want ONLY the average curve
            $studentseries   = [];

            foreach ($labels as $lab) {
                $sum = 0; $cnt = 0;
                foreach ($students as $name => $map) {
                    if (array_key_exists($lab, $map) && $map[$lab] !== null) { $sum += $map[$lab]; $cnt++; }
                }
                $avg[] = $cnt ? round($sum/$cnt,1) : null;
            }
            // Build faint lines (limited)
            $k = 0;
            foreach ($students as $name => $map) {
                if ($k >= $MAXSTUDENTLINES) break;
                $row = [];
                foreach ($labels as $lab) { $row[] = $map[$lab] ?? null; }
                $studentseries[] = ['name'=>$name, 'data'=>$row];
                $k++;
            }

            $payload = [
                'mode'     => 'teacher',
                'canvasid' => $canvasid,
                'title'    => $courseshort ?: 'Course',
                'labels'   => $labels,
                'avg'      => $avg,
                'lines'    => $studentseries,   // may be empty
            ];

        } else {
            // ===== STUDENT: single smooth line of own grades across activities =====
            $labels = [];
            $series = [];
            if (!empty($courses)) {
                $courseids = array_keys($courses);
                list($inSql, $inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
                $params = array_merge($inParams, ['userid'=>$USER->id]);

                $sql = "SELECT gi.id AS id,
                               gi.courseid,
                               COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule,' #',gi.id)) AS itemname,
                               gi.sortorder, gi.grademax, gg.finalgrade
                          FROM {grade_items} gi
                          JOIN {grade_grades} gg
                            ON gg.itemid = gi.id AND gg.userid = :userid
                         WHERE gi.courseid $inSql
                           AND gi.itemtype IN ('mod','manual','course')
                           AND gi.gradetype = 1
                      ORDER BY gi.courseid, gi.sortorder, gi.id";
                $rows = $DB->get_records_sql($sql, $params);

                if ($rows) {
                    foreach ($rows as $r) {
                        $c = $courses[$r->courseid]->shortname ?? ('C'.$r->courseid);
                        $labels[] = trim($c.': '.$r->itemname);
                        $series[] = ($r->grademax > 0 && $r->finalgrade !== null)
                            ? round(($r->finalgrade / $r->grademax) * 100, 1)
                            : null;
                    }
                }
            }

            if (empty($labels)) {
                $labels = ['Demo: Quiz 1','Demo: Assignment 1','Demo: Final'];
                $series = [62, 84, null];
            }

            $payload = [
                'mode'     => 'student',
                'canvasid' => $canvasid,
                'labels'   => $labels,
                'series'   => $series
            ];
        }

        $json = json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        // AMD-safe Chart.js loader + drawing
        $init = <<<JS
(function(){
  var p = $json;
  var cv = document.getElementById(p.canvasid);
  if (!cv) return;
  var ctx = cv.getContext('2d');

  function noAMD(src, cb){
    var od=window.define, om=window.module, oe=window.exports;
    try{ window.define=undefined; window.module=undefined; window.exports=undefined; }catch(e){}
    var s=document.createElement('script'); s.src=src; s.async=true;
    s.onload=function(){ window.define=od; window.module=om; window.exports=oe; cb(); };
    s.onerror=function(){ window.define=od; window.module=om; window.exports=oe; cb(new Error('loadfail '+src)); };
    document.head.appendChild(s);
  }

  function drawStudent(){
    if (typeof Chart==='undefined'){ cv.insertAdjacentHTML('beforebegin','<div style="color:#a00">Chart.js missing</div>'); return; }
    cv.width  = Math.max(900, (p.labels||[]).length*60);
    cv.height = 360;

    var g = ctx.createLinearGradient(0,0,0,cv.height);
    g.addColorStop(0,'rgba(46,204,113,0.30)');
    g.addColorStop(1,'rgba(46,204,113,0.00)');

    new Chart(ctx,{
      type:'line',
      data:{ labels:p.labels, datasets:[{
        label:'Grade (%)', data:p.series, spanGaps:true,
        tension:.35, cubicInterpolationMode:'monotone',
        borderColor:'#2ecc71', backgroundColor:g, borderWidth:3, pointRadius:3, fill:true
      }]},
      options:{
        responsive:true, maintainAspectRatio:false, animation:{duration:600},
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

  function drawTeacher(){
    if (typeof Chart==='undefined'){ cv.insertAdjacentHTML('beforebegin','<div style="color:#a00">Chart.js missing</div>'); return; }
    cv.width  = Math.max(900, (p.labels||[]).length*60);
    cv.height = 380;

    var datasets = [];

    // faint student lines
    (p.lines||[]).forEach(function(row){
      datasets.push({
        label: row.name,
        data: row.data,
        spanGaps: true,
        tension: .25,
        borderColor: 'rgba(0,0,0,0.25)',
        pointRadius: 0,
        borderWidth: 1,
        fill: false
      });
    });

    // bold average line on top
    datasets.push({
      label: (p.title ? p.title+' ' : '')+'Average',
      data: p.avg || [],
      spanGaps: true,
      tension: .35,
      borderColor: '#2ecc71',
      backgroundColor: 'rgba(46,204,113,0.15)',
      pointRadius: 2,
      borderWidth: 3,
      fill: true
    });

    new Chart(ctx,{
      type:'line',
      data:{ labels:p.labels, datasets: datasets },
      options:{
        responsive:true, maintainAspectRatio:false, animation:{duration:600},
        plugins:{ legend:{display:false},
          tooltip:{callbacks:{ label:c => (c.parsed.y==null ? 'No grade' : c.parsed.y+'%') }}
        },
        scales:{
          y:{min:0,max:100,grid:{color:'rgba(0,0,0,.06)'},
             title:{display:true,text:'Grade (%)'},ticks:{callback:v=>v+'%'}},
          x:{grid:{display:false},ticks:{autoSkip:false,maxRotation:60,minRotation:0}}
        }
      }
    });
  }

  function start(){
    if (typeof Chart!=='undefined') return (p.mode==='teacher'?drawTeacher():drawStudent());
    noAMD('/blocks/gradeheatmap/js/chart.umd.min.js', function(err){
      if (err || typeof Chart==='undefined'){
        noAMD('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js', function(){ (p.mode==='teacher'?drawTeacher():drawStudent()); });
      } else { (p.mode==='teacher'?drawTeacher():drawStudent()); }
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
