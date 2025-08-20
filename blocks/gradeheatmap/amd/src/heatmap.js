
/* eslint-disable */
define([], function() {
  function valueToColor(v) {
    if (v === null || isNaN(v)) return 'rgba(220,220,220,0.35)'; // no grade
    var t = Math.max(0, Math.min(1, v / 100)); // 0..1
    var r = Math.round(255 * (1 - t));
    var g = Math.round(255 * t);
    return 'rgba(' + r + ',' + g + ',0.85)'; // red→green
  }

  function loadECharts(cb){
    if (window.echarts) return cb();
    var s=document.createElement('script');
    s.src='https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js';
    s.onload=function(){ cb(); };
    document.head.appendChild(s);
  }

  return {
    init: function(payload) {
      var container = document.getElementById(payload.canvasid);
      if (!container) return;
      // Ensure it's a div for ECharts
      if (container.tagName.toLowerCase() !== 'div') {
        var div = document.createElement('div');
        div.id = payload.canvasid;
        div.style.width = '900px';
        div.style.height = '340px';
        container.parentNode.replaceChild(div, container);
        container = div;
      } else {
        container.style.width = '900px';
        container.style.height = '340px';
      }

      function drawEChartsLine() {
        var chart = echarts.init(container);
        var option = {
          xAxis: {
            type: 'category',
            data: payload.xlabels || payload.labels || []
          },
          yAxis: {
            type: 'value',
            min: 0,
            max: 100
          },
          series: [{
            data: (payload.series || payload.actual || []),
            type: 'line',
            smooth: true,
            name: (payload.actualLabel || 'Actual'),
            lineStyle: { width: 3 }
          }]
        };
        chart.setOption(option);
      }

      loadECharts(drawEChartsLine);
    }
  };
});
