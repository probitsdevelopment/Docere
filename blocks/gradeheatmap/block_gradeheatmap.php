<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        try { $this->title = get_string('pluginname', 'block_gradeheatmap'); }
        catch (\Throwable $e) { $this->title = 'My Grade Trend'; }
    }

    // Show on Dashboard (you can add 'course-view'=>true if you want it there too).
    public function applicable_formats() {
        return ['my' => true];
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE;
        if ($this->content !== null) return $this->content;
        $this->content = new stdClass();

        // Simple styling (card + centering).
        $PAGE->requires->css(new moodle_url('/blocks/gradeheatmap/styles.css'));

        // Pull current user's grades across enrolled courses (numeric only).
        require_once($CFG->dirroot . '/course/lib.php');
        $courses = enrol_get_users_courses($USER->id, true, 'id,shortname');
        $labels = [];
        $series = [];

        if (!empty($courses)) {
            $courseids = array_keys($courses);
            list($inSql, $inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $params = array_merge($inParams, ['userid' => $USER->id]);

            $sql = "SELECT gi.courseid,
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
                    $series[] = ($r->grademax > 0 && isset($r->finalgrade))
                        ? round(($r->finalgrade / $r->grademax) * 100, 1)
                        : null; // null creates a gap
                }
            }
        }

        // Friendly demo if no grades yet.
        if (empty($labels)) {
            $labels = ['Demo: Quiz 1','Demo: Assignment 1','Demo: Final'];
            $series = [62, 84, null];
            $note = html_writer::div('No grades yet — showing example data.', 'small text-muted',
                                     ['style'=>'margin:6px 12px;']);
        } else {
            $note = '';
        }

        // Canvas
        $canvasid = html_writer::random_id('gradetrend_');
        $canvas   = html_writer::tag('canvas', '', ['id'=>$canvasid]);
        $wrap     = html_writer::tag('div', $canvas, ['class'=>'heatmap-wrap']);
        $this->content->text = html_writer::div($note.$wrap, 'block_gradeheatmap');

        // Payload
        $payload = ['canvasid'=>$canvasid, 'labels'=>array_values($labels), 'series'=>array_values($series)];
        $json = json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        // Load Chart.js without RequireJS conflicts, then draw a nice line.
        $init = <<<JS
(function(){
  var p = $json;
  var cv = document.getElementById(p.canvasid);
  if (!cv) return;
  var ctx = cv.getContext('2d');

  function loadNoAMD(src, cb){
    var od = window.define, om = window.module, oe = window.exports;
    try { window.define=undefined; window.module=undefined; window.exports=undefined; } catch(e){}
    var s=document.createElement('script'); s.src=src; s.async=true;
    s.onload=function(){ window.define=od; window.module=om; window.exports=oe; cb(); };
    s.onerror=function(){ window.define=od; window.module=om; window.exports=oe; cb(new Error('loadfail')); };
    document.head.appendChild(s);
  }

  function draw(){
    if (typeof Chart==='undefined'){ cv.insertAdjacentHTML('beforebegin','<div style="color:#a00">Chart.js failed to load.</div>'); return; }

    cv.width  = Math.max(900, p.labels.length*60);
    cv.height = 360;

    // Pretty gradient fill
    var g = ctx.createLinearGradient(0,0,0,cv.height);
    g.addColorStop(0,  'rgba(46,204,113,0.30)');   // green
    g.addColorStop(1,  'rgba(46,204,113,0.00)');

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: p.labels,
        datasets: [{
          label: 'Grade (%)',
          data: p.series,
          spanGaps: true,
          tension: 0.35,
          cubicInterpolationMode: 'monotone',
          borderColor: '#2ecc71',
          backgroundColor: g,
          borderWidth: 3,
          pointRadius: 3,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 600 },
        scales: {
          y: {
            min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.06)' },
            title: { display: true, text: 'Grade (%)' },
            ticks: { callback: v => v + '%' }
          },
          x: {
            grid: { display: false },
            ticks: { autoSkip: false, maxRotation: 60, minRotation: 0 }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (c)=> (c.parsed.y==null ? 'No grade' : c.parsed.y+'%')
            }
          }
        }
      }
    });
  }

  // Load Chart.js (local optional → CDN fallback)
  function start(){
    if (typeof Chart!=='undefined') return draw();
    loadNoAMD('/blocks/gradeheatmap/js/chart.umd.min.js', function(err){
      if (err || typeof Chart==='undefined'){
        loadNoAMD('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js', draw);
      } else { draw(); }
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
