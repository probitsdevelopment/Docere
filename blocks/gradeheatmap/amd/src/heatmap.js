/* eslint-disable */
define([], function () {

  // --- Color for heatmap cells (red -> green). Tweak as you like.
  function valueToColor(v) {
    if (v === null || isNaN(v)) return 'rgba(220,220,220,0.35)'; // no grade
    var t = Math.max(0, Math.min(1, v / 100)); // 0..1
    var r = Math.round(255 * (1 - t));
    var g = Math.round(255 * t);
    return 'rgba(' + r + ',' + g + ',0.85)';
  }

  // Build average (0–100) for each X label from matrix cells {xIdx,yIdx,v}
  function buildColumnAverages(payload) {
    var sums = {}, counts = {};
    // payload.cells = [{x:<idx>, y:<idx>, v:<number|null>}, ...]
    for (var i = 0; i < payload.cells.length; i++) {
      var c = payload.cells[i];
      var xLabel = payload.xlabels[c.x];
      if (c.v !== null && !isNaN(c.v)) {
        sums[xLabel] = (sums[xLabel] || 0) + Number(c.v);
        counts[xLabel] = (counts[xLabel] || 0) + 1;
      }
    }
    // Return [{x:'Course total #1', y:<avg|null>}, ...] in xlabels order
    var out = [];
    for (var j = 0; j < payload.xlabels.length; j++) {
      var xl = payload.xlabels[j];
      var avg = counts[xl] ? (sums[xl] / counts[xl]) : null; // null = gap
      out.push({ x: xl, y: avg });
    }
    return out;
  }

  return {
    init: function (payload) {
      var cv = document.getElementById(payload.canvasid);
      if (!cv || typeof Chart === 'undefined') return;

      // Make canvas large enough so it scrolls horizontally/vertically nicely.
      cv.width  = Math.max(600, payload.xlabels.length * 40);
      cv.height = Math.max(300, payload.ylabels.length * 28);
      var ctx = cv.getContext('2d');

      // Convert indices to labelled matrix data for the heatmap
      var matrixData = payload.cells.map(function (c) {
        return { x: payload.xlabels[c.x], y: payload.ylabels[c.y], v: c.v };
      });

      // Build curved trend line points (average per column)
      var trendPoints = buildColumnAverages(payload); // [{x, y}]

      // --- Heatmap (matrix) dataset
      var matrixDs = {
        type: 'matrix',
        label: 'Grades (%)',
        data: matrixData,
        backgroundColor: function (ctx) { return valueToColor(ctx.raw.v); },
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.08)',
        // cell sizing
        width: function (ctx) {
          var a = ctx.chart.chartArea;
          return (a.right - a.left) / payload.xlabels.length - 2;
        },
        height: function (ctx) {
          var a = ctx.chart.chartArea;
          return (a.bottom - a.top) / payload.ylabels.length - 2;
        }
      };

      // --- Curved line (course average) dataset
      var lineDs = {
        type: 'line',
        label: 'Course average',
        data: trendPoints,             // [{x:'label', y:number|null}]
        parsing: { xAxisKey: 'x', yAxisKey: 'y' },
        yAxisID: 'yLinear',            // use linear % axis on the right
        tension: 0.4,                  // <-- curved line
        borderWidth: 2,
        pointRadius: 3,
        fill: false
      };

      // --- Create chart with dual Y axes
      new Chart(ctx, {
        data: {
          labels: payload.xlabels,     // shared X category axis
          datasets: [matrixDs, lineDs]
        },
        options: {
          maintainAspectRatio: false,
          responsive: true,
          plugins: {
            legend: { display: true },
            tooltip: {
              callbacks: {
                // Title shows "Student — Column" for matrix; just column for line
                title: function (items) {
                  var it = items[0];
                  if (it.dataset.type === 'matrix') {
                    return it.raw.y + ' — ' + it.raw.x;
                  }
                  return it.raw && it.raw.x ? it.raw.x : it.label || '';
                },
                label: function (item) {
                  if (item.dataset.type === 'matrix') {
                    var v = item.raw.v;
                    return (v == null || isNaN(v)) ? 'No grade' : (v + '%');
                  } else {
                    var y = item.parsed.y;
                    return (y == null || isNaN(y)) ? 'Average: No data' : ('Average: ' + y.toFixed(1) + '%');
                  }
                }
              }
            }
          },
          scales: {
            // X: categories (columns)
            x: {
              type: 'category',
              labels: payload.xlabels,
              position: 'top',
              offset: true,
              ticks: { autoSkip: false, maxRotation: 60, minRotation: 0 }
            },
            // Y for heatmap: category rows (students), drawn on left
            y: {
              type: 'category',
              labels: payload.ylabels,
              reverse: true,
              offset: true,
              ticks: { autoSkip: false }
            },
            // Y for line: linear 0–100% on right; no grid over heatmap
            yLinear: {
              type: 'linear',
              position: 'right',
              min: 0,
              max: 100,
              grid: { drawOnChartArea: false },
              ticks: { callback: function (v) { return v + '%'; } }
            }
          }
        }
      });
    }
  };
});
