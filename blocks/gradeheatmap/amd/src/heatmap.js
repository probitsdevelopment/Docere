/* eslint-disable */
define([], function() {
  return {
    init: function(cfg) {
      const selCourse  = document.getElementById(cfg.selectcourseid);
      const selStudent = document.getElementById(cfg.selectstudentid);
      const cv         = document.getElementById(cfg.canvasid);
      if (!selCourse || !selStudent || !cv) return;
      const ctx = cv.getContext('2d');
      let chart = null;

      function valueToColor(v) {
        if (v === null || isNaN(v)) return 'rgba(220,220,220,0.35)';
        const t = Math.max(0, Math.min(1, v/100));
        const r = Math.round(255*(1-t)), g = Math.round(255*t);
        return `rgba(${r},${g},0.85)`; // red->green
      }

      function render(payload) {
        const data = (payload.cells || []).map(c => ({ x:c.x, y:c.y, v:c.v }));
        if (chart) chart.destroy();
        chart = new Chart(ctx, {
          type: 'matrix',
          data: {
            datasets: [{
              label: 'Grades (%)',
              data,
              backgroundColor: (c)=> valueToColor(c.raw.v),
              borderWidth: 1,
              borderColor: 'rgba(0,0,0,0.08)',
              width:  (c)=> {
                const a=c.chart.chartArea;
                return Math.max(16, ((a.right-a.left)/Math.max(1,payload.xlabels.length)) - 2);
              },
              height: (c)=> {
                const a=c.chart.chartArea;
                return Math.max(16, ((a.bottom-a.top)/Math.max(1,payload.ylabels.length)) - 2);
              }
            }]
          },
          options: {
            maintainAspectRatio: false,
            plugins: {
              legend: { display:false },
              tooltip: {
                callbacks: {
                  title: (items)=> {
                    const r = items[0].raw;
                    return (payload.ylabels[r.y]||'') + ' — ' + (payload.xlabels[r.x]||'');
                  },
                  label: (i)=> {
                    const v = i.raw.v;
                    return (v==null || isNaN(v)) ? 'No grade' : (v + '%');
                  }
                }
              }
            },
            scales: {
              // Linear axes; ticks map index -> label
              x: {
                type:'linear', position:'top', offset:true,
                grid:{ display:false },
                ticks:{ callback:(v)=> payload.xlabels[v] ?? '', autoSkip:false, maxRotation:60, minRotation:0 }
              },
              y: {
                type:'linear', reverse:true, offset:true,
                ticks:{ callback:(v)=> payload.ylabels[v] ?? '', autoSkip:false }
              }
            }
          }
        });
      }

      async function loadStudents(courseid){
        selStudent.innerHTML = '';
        try {
          const res = await fetch(cfg.studentsurl + '?courseid=' + courseid);
          const list = await res.json(); // {selfOnly,...} or {students:[...]}
          if (!list.selfOnly) {
            selStudent.add(new Option('All students', 'all'));
            (list.students || []).forEach(s => selStudent.add(new Option(s.fullname, String(s.id))));
          } else {
            selStudent.add(new Option(list.fullname, String(list.id)));
          }
        } catch (e) {
          selStudent.add(new Option('Students unavailable', 'all'));
        }
      }

      async function loadHeatmap(){
        const courseid = selCourse.value;
        const userid   = selStudent.value;
        let url = cfg.dataurl + '?courseid=' + encodeURIComponent(courseid);
        if (userid && userid !== 'all') url += '&userid=' + encodeURIComponent(userid);
        const res = await fetch(url);
        if (!res.ok) return;
        const payload = await res.json();
        render(payload);
      }

      selCourse.addEventListener('change', async () => { await loadStudents(selCourse.value); loadHeatmap(); });
      selStudent.addEventListener('change', loadHeatmap);

      (async function init(){
        await loadStudents(selCourse.value);
        loadHeatmap();
      })();
    }
  };
});
