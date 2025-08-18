<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        // Title from lang pack with fallback.
        try { $this->title = get_string('pluginname', 'block_gradeheatmap'); }
        catch (\Throwable $e) { $this->title = 'Grade Heatmap'; }
    }

    // Allow on Dashboard and course pages.
    public function applicable_formats() {
        return ['my' => true, 'course-view' => true];
    }

    public function get_content() {
        global $CFG, $DB, $USER, $PAGE, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();

        // Styles (centers the chart and gives it a card look).
        $PAGE->requires->css(new moodle_url('/blocks/gradeheatmap/styles.css'));

        // Identify Dashboard context.
        $isdashboard = ($PAGE->pagelayout === 'mydashboard') || ($PAGE->context->contextlevel == CONTEXT_USER);

        // We need course helpers.
        require_once($CFG->dirroot . '/course/lib.php');

        $xlabels = [];   // columns (activities)
        $ylabels = [];   // rows (students or just the current user)
        $cells   = [];   // [{x, y, v}], v = % grade
        $usedemo = false;

        if ($isdashboard) {
            // DASHBOARD: current user's grades across their enrolled courses.
            $mycourses = enrol_get_users_courses($USER->id, true, 'id,shortname');
            if (!empty($mycourses)) {
                $courseids = array_keys($mycourses);
                list($inSql, $inParams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
                $params = array_merge($inParams, ['userid' => $USER->id]);

                $sql = "SELECT gi.id AS itemid, gi.courseid,
                               COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule, ' #', gi.id)) AS itemname,
                               gi.itemtype, gi.itemmodule, gi.sortorder, gi.grademax,
                               gg.finalgrade
                          FROM {grade_items} gi
                          JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                         WHERE gi.courseid $inSql
                           AND gi.itemtype IN ('mod','manual')
                           AND gi.gradetype = 1
                      ORDER BY gi.courseid, gi.sortorder, gi.id";
                $rows = $DB->get_records_sql($sql, $params);
            } else {
                $rows = [];
            }

            if (!$rows) {
                // Demo data so you can see the chart before real grades exist.
                $usedemo = true;
                $xlabels = ['Demo: Quiz 1','Demo: Assignment 1','Demo: Final'];
                $ylabels = [fullname($USER)];
                $vals    = [62, 84, null];
                foreach ($xlabels as $i => $_) { $cells[] = ['x'=>$i,'y'=>0,'v'=>$vals[$i]]; }
            } else {
                // Columns = "COURSE: Item".
                foreach ($rows as $r) {
                    $cshort = $mycourses[$r->courseid]->shortname ?? ('C'.$r->courseid);
                    $xlabels[] = trim($cshort . ': ' . $r->itemname);
                }
                $xlabels = array_values(array_unique($xlabels));
                $ylabels = [fullname($USER)];

                $lookup = [];
                foreach ($rows as $r) {
                    $cshort = $mycourses[$r->courseid]->shortname ?? ('C'.$r->courseid);
                    $lab = trim($cshort . ': ' . $r->itemname);
                    $pct = ($r->grademax > 0 && isset($r->finalgrade))
                           ? round(($r->finalgrade / $r->grademax) * 100, 1)
                           : null;
                    $lookup[$lab] = $pct;
                }
                for ($x = 0; $x < count($xlabels); $x++) {
                    $iname = $xlabels[$x];
                    $cells[] = ['x'=>$x,'y'=>0,'v'=>($lookup[$iname] ?? null)];
                }
            }

        } else {
            // COURSE PAGE: grid (all students if allowed, otherwise just current user).
            if (empty($COURSE) || empty($COURSE->id)) {
                $this->content->text = html_writer::div('No course context.', 'text-muted');
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

            $sql = "SELECT u.id AS userid, u.firstname, u.lastname,
                           gi.id AS itemid,
                           COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule, ' #', gi.id)) AS itemname,
                           gi.itemtype, gi.itemmodule, gi.sortorder, gi.grademax,
                           gg.finalgrade
                      FROM {grade_items} gi
                      JOIN {grade_grades} gg ON gg.itemid = gi.id
                      JOIN {user} u ON u.id = gg.userid
                     WHERE gi.courseid = :courseid
                       AND gi.itemtype IN ('mod','manual')
                       AND gi.gradetype = 1
                           $userfilter
                  ORDER BY u.lastname, u.firstname, gi.sortorder";
            $rows = $DB->get_records_sql($sql, $params);

            if (!$rows) {
                $usedemo = true;
                $xlabels = ['Quiz 1','Assignment 1','Final'];
                $ylabels = [fullname($USER)];
                $vals    = [55, 91, 78];
                foreach ($xlabels as $i => $_) { $cells[] = ['x'=>$i,'y'=>0,'v'=>$vals[$i]]; }
            } else {
                $xlabels = array_values(array_unique(array_map(function($r){ return trim($r->itemname); }, $rows)));
                $ylabels = array_values(array_unique(array_map(function($r){ return trim(($r->firstname ?? '').' '.($r->lastname ?? '')); }, $rows)));

                $lookup = [];
                foreach ($rows as $r) {
                    $sname = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
                    $iname = trim($r->itemname);
                    $pct = ($r->grademax > 0 && isset($r->finalgrade))
                           ? round(($r->finalgrade / $r->grademax) * 100, 1)
                           : null;
                    $lookup[$sname][$iname] = $pct;
                }
                for ($y = 0; $y < count($ylabels); $y++) {
                    $sname = $ylabels[$y];
                    for ($x = 0; $x < count($xlabels); $x++) {
                        $iname = $xlabels[$x];
                        $cells[] = ['x'=>$x,'y'=>$y,'v'=>($lookup[$sname][$iname] ?? null)];
                    }
                }
            }
        }

        // If still no data, show a tiny demo row so you can confirm rendering.
        if (empty($cells)) {
            $usedemo = true;
            $xlabels = ['Demo: Quiz 1', 'Demo: Assignment 1', 'Demo: Final'];
            $ylabels = [fullname($USER)];
            $cells   = [
                ['x'=>0,'y'=>0,'v'=>62],
                ['x'=>1,'y'=>0,'v'=>84],
                ['x'=>2,'y'=>0,'v'=>null],
            ];
        }

        // Build HTML wrapper + canvas.
        $canvasid = html_writer::random_id('gradeheatmap_');
        $canvas   = html_writer::tag('canvas', '', ['id'=>$canvasid]);
        $wrap     = html_writer::tag('div', $canvas, ['class'=>'heatmap-wrap']);
        $note     = $usedemo ? html_writer::div('Showing sample data — add & grade an activity to see real values.', 'small text-muted', ['style'=>'margin:6px 12px;']) : '';
        $this->content->text = html_writer::div($note.$wrap, 'block_gradeheatmap');

        // Pass data to JS.
        $payload = [
            'canvasid' => $canvasid,
            'xlabels'  => array_values($xlabels),
            'ylabels'  => array_values($ylabels),
            'cells'    => array_values($cells),
        ];
        $payloadjson = json_encode($payload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

        // IMPORTANT: Do NOT include Chart.js via $PAGE->requires->js() because Moodle uses RequireJS
        // and the UMD build will "define()" → mismatch error. Load without AMD below instead.
        $init = <<<JS
(function(){
  var p = $payloadjson;
  var cv = document.getElementById(p.canvasid);
  if (!cv) { console.warn('Heatmap: canvas not found'); return; }

  function valueToColor(v){
    if (v===null || isNaN(v)) return 'rgba(220,220,220,0.35)';
    var t=Math.max(0,Math.min(1,v/100));
    var r=Math.round(255*(1-t)), g=Math.round(255*t);
    return 'rgba('+r+','+g+',0,0.85)';
  }

  function draw(){
    try {
      if (typeof Chart === 'undefined' || !Chart.registry || !Chart.registry.getController('matrix')) {
        cv.insertAdjacentHTML('beforebegin','<div style="color:#a00">Chart still not available. Check JS paths.</div>');
        return;
      }
      // Size for dashboard center.
      cv.width  = Math.max(900, p.xlabels.length * 60);
      cv.height = Math.max(380, p.ylabels.length * 36);

      var ctx = cv.getContext('2d');
      var data = p.cells.map(function(c){ return {x: p.xlabels[c.x], y: p.ylabels[c.y], v: c.v}; });

      new Chart(ctx, {
        type: 'matrix',
        data: { datasets: [{
          label: 'Grades (%)',
          data: data,
          backgroundColor: function(ctx){ return valueToColor(ctx.raw.v); },
          borderWidth: 1,
          borderColor: 'rgba(0,0,0,0.08)',
         width:  ({chart}) => {
  const a = chart.chartArea;
  const cols = Math.max(1, p.xlabels.length);
  // fallback before chartArea exists (first layout pass)
  return a ? (a.right - a.left) / cols - 2 : Math.max(12, (chart.width / cols) - 2);
},
height: ({chart}) => {
  const a = chart.chartArea;
  const rows = Math.max(1, p.ylabels.length);
  return a ? (a.bottom - a.top) / rows - 2 : Math.max(12, (chart.height / rows) - 2);
}


        }]},
        options: {
          maintainAspectRatio:false,
            responsive: true,
             maintainAspectRatio: false,
          plugins:{ legend:{display:false},
            tooltip:{callbacks:{
              title:function(items){var it=items[0]; return it.raw.y+' — '+it.raw.x;},
              label:function(item){var v=item.raw.v; return (v==null||isNaN(v))?'No grade':(v+'%');}
            }}
          },
          scales:{
            x:{type:'category',labels:p.xlabels,position:'top',ticks:{autoSkip:false,maxRotation:60,minRotation:0}},
            y:{type:'category',labels:p.ylabels,reverse:true,ticks:{autoSkip:false}}
          }
        }
      });
    } catch (e) {
      console.error(e);
      cv.insertAdjacentHTML('beforebegin','<div style="color:#a00">Heatmap error: '+String(e)+'</div>');
    }
  }

  // Load a script with AMD temporarily disabled so RequireJS doesn't intercept it.
  function loadScriptNoAMD(src, cb){
    var oldDefine = window.define, oldModule = window.module, oldExports = window.exports;
    try { window.define = undefined; window.module = undefined; window.exports = undefined; } catch(e){}
    var s=document.createElement('script');
    s.src = src; s.async = true;
    s.onload = function(){ window.define = oldDefine; window.module = oldModule; window.exports = oldExports; cb(); };
    s.onerror = function(){ window.define = oldDefine; window.module = oldModule; window.exports = oldExports; cb(new Error('loadfail '+src)); };
    document.head.appendChild(s);
  }

  function loadAll(){
    // Try local Chart.js first, fallback to CDN, then load matrix plugin (local→CDN).
    loadScriptNoAMD('/blocks/gradeheatmap/js/chart.umd.min.js', function(err1){
      var afterChart = function(){
        loadScriptNoAMD('/blocks/gradeheatmap/js/chartjs-chart-matrix.min.js', function(err2){
          if (err2 || !Chart.registry || !Chart.registry.getController('matrix')) {
            loadScriptNoAMD('https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@3/dist/chartjs-chart-matrix.min.js', draw);
          } else {
            draw();
          }
        });
      };
      if (err1 || typeof Chart === 'undefined') {
        loadScriptNoAMD('https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js', afterChart);
      } else {
        afterChart();
      }
    });
  }

  loadAll();
})();
JS;

        $PAGE->requires->js_init_code($init);

        $this->content->footer = '';
        return $this->content;
    }
}
