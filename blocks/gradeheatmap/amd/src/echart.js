/* eslint-disable */
define([], function () {
  function init(cfg) {
    if (typeof window.echarts === 'undefined') {
      console.error('ECharts not loaded'); return;
    }
    var el = document.getElementById(cfg.el || 'gh-echart');
    if (!el) return;

    el.style.width = '100%';
    el.style.minHeight = (cfg.minHeight || 320) + 'px';

    var chart = window.echarts.init(el, null, {renderer:'canvas', useDirtyRect:false});

    // If called with no option, render later when event is dispatched
    if (cfg.option) chart.setOption(cfg.option);

    // Listen for our custom update event so PHP can push data after render.
    if (cfg.on) {
      document.addEventListener('gh:echart:update', function(e){
        chart.setOption(e.detail.option || {}, true);
      });
    }

    window.addEventListener('resize', function(){ chart.resize(); });
  }
  return { init };
});
