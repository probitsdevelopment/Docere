<?php
defined('MOODLE_INTERNAL') || die();

class block_gradeheatmap extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_gradeheatmap');
    }

    public function get_content() {
        global $DB, $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';

        // Add ECharts script and CSS file to the page
        $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js'));

        // Add a div to render the chart
        $this->content->text .= '<div id="container" style="height: 400px;"></div>';

        // Fetch grade data
        $grades = $DB->get_records_sql("
            SELECT gi.itemname, gg.finalgrade
            FROM {grade_items} gi
            JOIN {grade_items_history} gg ON gg.iteminstance = gi.iteminstance
            WHERE gi.itemmodule = 'course'
            AND gg.userid = ?
        ", [$USER->id]);

        // Prepare data for the chart
        $labels = [];
        $grade_values = [];
        foreach ($grades as $grade) {
            $labels[] = $grade->itemname;
            $grade_values[] = $grade->finalgrade;
        }

        $labels_json = json_encode($labels);
        $grades_json = json_encode($grade_values);

        // Pass data to JavaScript
        $this->content->text .= '<script type="text/javascript">
            var labels = ' . $labels_json . ';
            var gradeData = ' . $grades_json . ';
        </script>';

        // ECharts setup
        $this->content->text .= '<script type="text/javascript">
            var dom = document.getElementById("container");
            var myChart = echarts.init(dom, null, {
                renderer: "canvas",
                useDirtyRect: false
            });

            var option = {
                title: {
                    text: "Student Grades Overview"
                },
                tooltip: {
                    trigger: "axis"
                },
                xAxis: {
                    type: "category",
                    data: labels
                },
                yAxis: {
                    type: "value"
                },
                series: [{
                    data: gradeData,
                    type: "line",
                    smooth: true,
                    color: "#ff6347"
                }]
            };

            if (option && typeof option === "object") {
                myChart.setOption(option);
            }

            window.addEventListener("resize", myChart.resize);
        </script>';

        return $this->content;
    }
}
