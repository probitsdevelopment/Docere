
/* eslint-disable */
define([], function() {
  function valueToColor(v) {
    if (v === null || isNaN(v)) return 'rgba(220,220,220,0.35)'; // no grade
    var t = Math.max(0, Math.min(1, v / 100)); // 0..1
    var r = Math.round(255 * (1 - t));
    var g = Math.round(255 * t);
    return 'rgba(' + r + ',' + g + ',0.85)'; // red→green
  }

  return {
    init: function(payload) {
      var cv = document.getElementById(payload.canvasid);
      if (!cv || typeof Chart === 'undefined') return;

      // Size canvas so it scrolls nicely.
      cv.width  = Math.max(600, payload.xlabels.length * 40);
      cv.height = Math.max(300, payload.ylabels.length * 28);
      var ctx = cv.getContext('2d');

      // Convert to labelled matrix data.
      var data = payload.cells.map(function(c) {
        return { x: payload.xlabels[c.x], y: payload.ylabels[c.y], v: c.v };
      });

      new Chart(ctx, {
        type: 'matrix',
        data: {
          datasets: [{
            label: 'Grades (%)',
            data: data,
            backgroundColor: function(ctx) { return valueToColor(ctx.raw.v); },
            borderWidth: 1,
            borderColor: 'rgba(0,0,0,0.08)',
            tension: 0.4 , 
            width:  function(ctx){ var a=ctx.chart.chartArea; return (a.right-a.left)/payload.xlabels.length - 2; },
            height: function(ctx){ var a=ctx.chart.chartArea; return (a.bottom-a.top)/payload.ylabels.length - 2; }
          }]
        },
        options: {
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                title: function(items){ var it=items[0]; return it.raw.y+' — '+it.raw.x; },
                label: function(item){ var v=item.raw.v; return (v==null||isNaN(v))?'No grade':(v+'%'); }
              }
            }
          },
          scales: {
            x: { type:'category', labels: payload.xlabels, position:'top', ticks:{autoSkip:false,maxRotation:60,minRotation:0} },
            y: { type:'category', labels: payload.ylabels, reverse:true, ticks:{autoSkip:false} }
          }
        }
      });
    }
  };
});
