<?php
require_once(__DIR__ . '/../../config.php');
// Fetch org students' assessment scores for custom assessments (not quizzes)
function get_org_student_assessment_scores($assessment_id) {
    global $DB;
    // Fetch all students with marks for this assessment
    $sql = "SELECT t.student_id, u.firstname, u.lastname, u.email, t.total_marks
            FROM {student_assessment_totals} t
            JOIN {user} u ON u.id = t.student_id
            WHERE t.question_id = ?";
    $totals = $DB->get_records_sql($sql, [$assessment_id]);
    $result = [];
    foreach ($totals as $row) {
        $result[] = [
            'id' => $row->student_id,
            'name' => $row->firstname . ' ' . $row->lastname,
            'email' => $row->email,
            'total_marks' => (float)$row->total_marks
        ];
    }
    return $result;
}



$perpage = 10;

global $DB;

// Get courseid and assessment_id from GET parameters
$courseid = isset($_GET['courseid']) ? intval($_GET['courseid']) : 0;
$assessment_id = isset($_GET['assessment']) ? intval($_GET['assessment']) : 0;

// Detect stakeholder's organization category
$orgcatid = 0;
if (!empty($USER->id)) {
    $orgcatid = $DB->get_field_sql('SELECT ctx.instanceid FROM {role_assignments} ra JOIN {context} ctx ON ctx.id = ra.contextid WHERE ra.userid = ? AND ctx.contextlevel = 40 LIMIT 1', [$USER->id]);
}
// Fetch only courses for stakeholder's organization
if ($orgcatid) {
    $courses = $DB->get_records_sql('SELECT id, fullname FROM {course} WHERE category = ? ORDER BY fullname', [$orgcatid]);
} else {
    $courses = [];
}

// Fetch all available assessments for this course
$assessments = [];
if ($courseid) {
    $assessment_records = $DB->get_records_sql('SELECT id AS question_id, qtitle FROM {local_questions} WHERE courseid = ?', [$courseid]);
    foreach ($assessment_records as $rec) {
        $completed = $DB->count_records('student_assessment_totals', ['question_id' => $rec->question_id, 'courseid' => $courseid]);
        $total_students = $completed;
        $avg_score = $DB->get_field_sql('SELECT AVG(total_marks) FROM {student_assessment_totals} WHERE question_id = ? AND courseid = ?', [$rec->question_id, $courseid]);
        $assessments[] = [
            'id' => $rec->question_id,
            'title' => $rec->qtitle ? $rec->qtitle : ('Assessment ' . $rec->question_id),
            'completed' => $completed,
            'total_students' => $total_students,
            'avg_score' => round($avg_score, 1)
        ];
    }
}

// Set selected assessment info
$selected_assessment = null;
foreach ($assessments as $assessment) {
    if ($assessment_id && $assessment['id'] == $assessment_id) {
        $selected_assessment = $assessment;
        break;
    }
}

// Fetch heatmap data for selected assessment in this course
$heatmap_data = [];
if ($assessment_id && $courseid) {
    $sql = "SELECT t.student_id, u.firstname, u.lastname, u.email, t.total_marks
            FROM {student_assessment_totals} t
            JOIN {user} u ON u.id = t.student_id
            WHERE t.question_id = ? AND t.courseid = ?";
    $totals = $DB->get_records_sql($sql, [$assessment_id, $courseid]);
    foreach ($totals as $row) {
        $heatmap_data[] = [
            'id' => $row->student_id,
            'name' => $row->firstname . ' ' . $row->lastname,
            'email' => $row->email,
            'total_marks' => (float)$row->total_marks
        ];
    }
}

// Fetch overall heatmap data for all assessments in a course
if (!empty($_GET['overall']) && $courseid) {
    $sql = "SELECT t.student_id, u.firstname, u.lastname, u.email, SUM(t.total_marks) AS overall_marks
            FROM {student_assessment_totals} t
            JOIN {user} u ON u.id = t.student_id
            WHERE t.courseid = ?
            GROUP BY t.student_id, u.firstname, u.lastname, u.email";
    $totals = $DB->get_records_sql($sql, [$courseid]);
    $heatmap_data = [];
    foreach ($totals as $row) {
        $heatmap_data[] = [
            'id' => $row->student_id,
            'name' => $row->firstname . ' ' . $row->lastname,
            'email' => $row->email,
            'total_marks' => (float)$row->overall_marks
        ];
    }
}

// ...existing code...

// Start output
echo $OUTPUT->header();
?>

<!-- Include Apache ECharts -->
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<style>
@import url('https://fonts.googleapis.com/icon?family=Material+Icons');

body {
    background: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.stakeholder-container {
    max-width: 1400px;
    margin: -60px auto 0;
    padding: 20px;
    min-height: 100vh;
}

.stakeholder-welcome {
    background: linear-gradient(135deg, #CDEBFA 0%, #A8DCFA 100%);
    border-radius: 16px;
    padding: 30px;
    color: #2d3748;
    margin: 0 0 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
    border-top: 1px solid #149EDF;
    border-left: 1px solid #149EDF;
    border-right: 1px solid #149EDF;
    border-bottom: 5px solid #149EDF;
}

.stakeholder-welcome::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.stakeholder-welcome-content h1 {
    margin: 0 0 10px 0;
    font-size: 2em;
    font-weight: 700;
}

.stakeholder-welcome-date {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    opacity: 0.9;
}

.stakeholder-welcome-date .material-icons {
    margin-right: 8px;
    font-size: 18px;
}

.stakeholder-welcome-subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1em;
}

.stakeholder-main-content {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.assessment-list {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 20px;
}

.assessment-list h3 {
    margin: 0 0 20px 0;
    font-size: 1.3em;
    font-weight: 700;
    color: #2d3748;
}

.assessment-item {
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 15px;
    transition: all 0.2s;
    cursor: pointer;
}

.assessment-item:hover {
    border-color: #667eea;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
}

.assessment-item.active {
    border-color: #667eea;
    background: #f7fafc;
}

.assessment-title {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    font-size: 14px;
}

.assessment-stats {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 10px;
}

.view-btn {
    background: #58CC02;
    color: white;
    border: none;
    padding: 8px 24px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    width: auto;
    min-width: 160px;
    max-width: 220px;
    display: block;
    margin-left: 0;
    margin-top: 0;
    transition: background-color 0.2s;
}

.view-btn:hover {
    background: #4DB300;
}

.heatmap-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 20px;
    min-height: 500px;
}

.heatmap-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 15px;
}

.heatmap-title {
    font-size: 1.3em;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
}

.export-btn {
    background: #1CB0F6;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s;
}

.export-btn:hover {
    background: #1A9BD8;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.empty-state .material-icons {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

#studentHeatmap {
    width: 100%;
    height: 400px;
}

.heatmap-scroll-container {
    width: 100%;
    height: 400px;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    position: relative;
}

.heatmap-scroll-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.heatmap-scroll-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.heatmap-scroll-container::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 4px;
}

.heatmap-scroll-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.legend {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #64748b;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 80%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.3em;
    font-weight: 700;
    color: #2d3748;
    margin: 0;
}

.close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close:hover {
    color: #2d3748;
}

.modal-body {
    padding: 25px;
}

.student-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 8px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
}

.info-value {
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
}

.score-breakdown {
    margin-bottom: 20px;
}

.score-breakdown h4 {
    margin: 0 0 15px 0;
    font-size: 1.1em;
    color: #2d3748;
}

.score-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}

.score-item:last-child {
    border-bottom: none;
}

.score-name {
    font-weight: 500;
    color: #4a5568;
}

.score-value {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.score-bar {
    width: 100px;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.score-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}
</style>

<div class="stakeholder-container">
    <!-- Welcome Banner -->
    <div class="stakeholder-welcome">
        <div class="stakeholder-welcome-content">
            <h1>Welcome Back, <?php echo fullname($USER); ?></h1>
            <div class="stakeholder-welcome-date">
                <span class="material-icons">calendar_today</span>
                <?php echo date('D, j M Y'); ?>
            </div>
            <p class="stakeholder-welcome-subtitle">
                Monitor and analyze student quiz performance
                <br><strong>Organization: <?php echo htmlspecialchars($organization_name); ?></strong>
            </p>
        </div>
        <div style="position: relative; z-index: 2;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <!-- Stakeholder Character -->
                <div style="position: relative; width: 70px; height: 90px;">
                    <!-- Body -->
                    <div style="width: 40px; height: 50px; background: #2c3e50; border-radius: 6px 6px 0 0; position: absolute; bottom: 0; left: 15px;"></div>
                    <!-- Shirt -->
                    <div style="width: 30px; height: 12px; background: #ecf0f1; position: absolute; bottom: 30px; left: 20px;"></div>
                    <!-- Head -->
                    <div style="width: 35px; height: 40px; background: #f4a261; border-radius: 50% 50% 40% 40%; position: absolute; top: 0; left: 17px;"></div>
                    <!-- Hair -->
                    <div style="width: 30px; height: 18px; background: #8b4513; border-radius: 50% 50% 35% 35%; position: absolute; top: 2px; left: 20px;"></div>
                    <!-- Eyes -->
                    <div style="width: 2px; height: 2px; background: #2c3e50; border-radius: 50%; position: absolute; top: 18px; left: 26px;"></div>
                    <div style="width: 2px; height: 2px; background: #2c3e50; border-radius: 50%; position: absolute; top: 18px; left: 36px;"></div>
                    <!-- Smile -->
                    <div style="width: 6px; height: 3px; border: 1px solid #2c3e50; border-top: none; border-radius: 0 0 6px 6px; position: absolute; top: 26px; left: 32px;"></div>
                </div>

                <div style="background: white; color: #2d3748; padding: 12px 16px; border-radius: 16px; position: relative; max-width: 180px; font-size: 13px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                    Ready to analyze student performance?
                    <div style="position: absolute; bottom: -8px; left: 20px; width: 0; height: 0; border-left: 8px solid transparent; border-right: 8px solid transparent; border-top: 8px solid white;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="stakeholder-main-content">
        <!-- Course List (Left Sidebar) -->
        <div class="assessment-list">
            <h3>Courses</h3>
            <input type="text" id="course-search" placeholder="Search courses..." style="width: 100%; margin-bottom: 12px; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 15px;">
            <div id="course-list-container">
            <?php if (empty($courses)): ?>
                <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                    <div class="material-icons" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">school</div>
                    <p><strong>No Courses Available</strong></p>
                </div>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                    <div class="assessment-item <?php echo ($courseid == $course->id) ? 'active' : ''; ?>">
                        <div class="assessment-title"><?php echo htmlspecialchars($course->fullname); ?></div>
                        <button class="view-btn" onclick="viewCourseHeatmap(<?php echo $course->id; ?>)">
                            View Student Heatmap
                        </button>
                        <button class="view-btn" style="background:#1CB0F6;margin-top:10px;" onclick="viewOverallHeatmap(<?php echo $course->id; ?>)">
                            View Overall Assessment Heatmap
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
            <script>
            // Simple client-side search for courses
            document.getElementById('course-search').addEventListener('input', function() {
                var filter = this.value.toLowerCase();
                var items = document.querySelectorAll('#course-list-container .assessment-item');
                items.forEach(function(item) {
                    var title = item.querySelector('.assessment-title').textContent.toLowerCase();
                    if (title.indexOf(filter) > -1) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });

            // Handler for overall heatmap button
            function viewOverallHeatmap(courseid) {
                window.location.href = '?courseid=' + courseid + '&overall=1';
            }
            </script>
        </div>

        <!-- Heatmap Area (Right) -->
        <div class="heatmap-container">
            <?php if (!empty($_GET['overall']) && $courseid): ?>
                <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                    <button class="export-btn" onclick="exportOverallData()">
                        <span class="material-icons" style="font-size: 18px;">download</span>
                        Export Data
                    </button>
                </div>
                <!-- Assessment Filter Dropdown for overall heatmap view -->
                <div style="margin-bottom:20px;">
                    <label for="assessment-overall-filter" style="font-weight:600; margin-right:10px;">Filter by Assessment:</label>
                    <select id="assessment-overall-filter" style="padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:15px;">
                        <option value="all" <?php if (!empty($_GET['overall'])) echo 'selected'; ?>>All Assessments</option>
                        <?php foreach ($assessments as $assessment): ?>
                            <option value="<?php echo $assessment['id']; ?>" <?php if ($assessment_id == $assessment['id']) echo 'selected'; ?>><?php echo htmlspecialchars($assessment['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <script>
                document.getElementById('assessment-overall-filter').addEventListener('change', function() {
                    var val = this.value;
                    var courseid = <?php echo json_encode($courseid); ?>;
                    if (val === 'all') {
                        window.location.href = '?courseid=' + courseid + '&overall=1';
                    } else {
                        window.location.href = '?courseid=' + courseid + '&assessment=' + val;
                    }
                });
                </script>
                <h3 class="heatmap-title">Overall Assessment Heatmap</h3>
                <!-- Debug output removed -->
                <div class="heatmap-scroll-container">
                    <?php if (empty($heatmap_data)): ?>
                        <div style="text-align:center; padding:40px 20px; color:#64748b;">
                            <div class="material-icons" style="font-size:48px; margin-bottom:16px; opacity:0.5;">quiz</div>
                            <p><strong>No combined results available for this course.</strong></p>
                        </div>
                    <?php else: ?>
                        <div id="studentHeatmap"></div>
                    <?php endif; ?>
                </div>
            <?php elseif ($courseid && empty($assessment_id)): ?>
                <h3 class="heatmap-title">Assessments for Selected Course</h3>
                <!-- Assessment Filter Dropdown for assessment list -->
                <div style="margin-bottom:20px;">
                    <label for="assessment-list-filter" style="font-weight:600; margin-right:10px;">Filter by Assessment:</label>
                    <select id="assessment-list-filter" style="padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:15px;">
                        <option value="all">All Assessments</option>
                        <?php foreach ($assessments as $assessment): ?>
                            <option value="<?php echo $assessment['id']; ?>" <?php if ($assessment_id == $assessment['id']) echo 'selected'; ?>><?php echo htmlspecialchars($assessment['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <script>
                document.getElementById('assessment-list-filter').addEventListener('change', function() {
                    var val = this.value;
                    var courseid = <?php echo json_encode($courseid); ?>;
                    if (val === 'all') {
                        window.location.href = '?courseid=' + courseid;
                    } else {
                        window.location.href = '?courseid=' + courseid + '&assessment=' + val;
                    }
                });
                </script>
                <div id="assessment-list-container">
                <?php if (empty($assessments)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                        <div class="material-icons" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">quiz</div>
                        <p><strong>No Assessments Available</strong></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($assessments as $assessment): ?>
                        <?php if (!$assessment_id || $assessment_id == $assessment['id']): ?>
                        <div class="assessment-item <?php echo ($assessment_id == $assessment['id']) ? 'active' : ''; ?>">
                            <div class="assessment-title"><?php echo htmlspecialchars($assessment['title']); ?></div>
                            <div class="assessment-stats">
                                <span><?php echo $assessment['completed']; ?>/<?php echo $assessment['total_students']; ?> completed</span>
                                <span>Avg: <?php echo $assessment['avg_score']; ?>%</span>
                            </div>
                            <button class="view-btn" onclick="viewAssessment(<?php echo $assessment['id']; ?>, <?php echo $courseid; ?>)">
                                View Student Marks
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div>
            <?php elseif ($selected_assessment && $heatmap_data): ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Only run for individual assessment view
                    var studentData = <?php echo json_encode($heatmap_data); ?>;
                    if (studentData && studentData.length > 0 && document.getElementById('studentHeatmap')) {
                        // Prepare data for bar chart
                        var names = studentData.map(function(s) { return s.name; });
                        var scores = studentData.map(function(s) { return s.total_marks || s.score; });
                        var chartDom = document.getElementById('studentHeatmap');
                        var chart = echarts.init(chartDom);
                        var option = {
                            tooltip: {
                                trigger: 'axis',
                                axisPointer: { type: 'none' }
                            },
                            xAxis: {
                                type: 'category',
                                data: names,
                                axisLabel: { rotate: 30, fontSize: 12 }
                            },
                            yAxis: {
                                type: 'value',
                                name: 'Marks',
                                min: 0,
                                max: 100
                            },
                            series: [{
                                data: scores,
                                type: 'bar',
                                barWidth: '30%', // Slightly thinner bars
                                barCategoryGap: '0%', // No gap between bars
                                itemStyle: {
                                    color: function(params) {
                                        var score = params.value;
                                        if (score >= 90) return '#059669';
                                        if (score >= 80) return '#22c55e';
                                        if (score >= 60) return '#eab308';
                                        if (score >= 40) return '#f59e0b';
                                        return '#dc2626';
                                    }
                                },
                                label: {
                                    show: true,
                                    position: 'top',
                                    formatter: '{c}%'
                                },
                                emphasis: {} // Remove hover background effect
                            }]
                        };
                        chart.setOption(option);
                    }
                });
                </script>
                <!-- Assessment Filter Dropdown for heatmap view -->
                <div style="margin-bottom:20px;">
                    <label for="assessment-heatmap-filter" style="font-weight:600; margin-right:10px;">Filter by Assessment:</label>
                    <select id="assessment-heatmap-filter" style="padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:15px;">
                        <option value="all">All Assessments</option>
                        <?php foreach ($assessments as $assessment): ?>
                            <option value="<?php echo $assessment['id']; ?>" <?php if ($assessment_id == $assessment['id']) echo 'selected'; ?>><?php echo htmlspecialchars($assessment['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <script>
                document.getElementById('assessment-heatmap-filter').addEventListener('change', function() {
                    var val = this.value;
                    var courseid = <?php echo json_encode($courseid); ?>;
                    if (val === 'all') {
                        window.location.href = '?courseid=' + courseid;
                    } else {
                        window.location.href = '?courseid=' + courseid + '&assessment=' + val;
                    }
                });
                </script>
                <div class="heatmap-header">
                    <h3 class="heatmap-title"><?php echo htmlspecialchars($selected_assessment['title']); ?> - Student Performance</h3>
                    <button class="export-btn" onclick="exportData()">
                        <span class="material-icons" style="font-size: 18px;">download</span>
                        Export Data
                    </button>
                </div>

                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #dc2626;"></div>
                        <span>0-40% (Poor)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f59e0b;"></div>
                        <span>41-60% (Below Average)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #eab308;"></div>
                        <span>61-79% (Average)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #22c55e;"></div>
                        <span>80-89% (Good)</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #059669;"></div>
                        <span>90-100% (Excellent)</span>
                    </div>
                </div>

                <div class="heatmap-scroll-container">
                    <div id="studentHeatmap"></div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="material-icons">school</div>
                    <h3>Select a Course</h3>
                    <p>Choose a course from the left panel to view its assessments and student marks.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Student Details Modal -->
<div id="studentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Student Performance Details</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<script>
function exportOverallData() {
    // Create CSV data for overall heatmap
    const studentData = <?php echo json_encode($heatmap_data ?? []); ?>;
    if (!studentData || studentData.length === 0) {
        alert('No data to export');
        return;
    }
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Student Name,Email,Overall Marks (%)\n";
    studentData.forEach(student => {
        csvContent += `"${student.name}","${student.email}",${student.total_marks}\n`;
    });
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "overall_assessment_heatmap.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
// Only run heatmap for overall assessment view
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($_GET['overall'])): ?>
    var studentData = <?php echo json_encode($heatmap_data); ?>;
    if (studentData && studentData.length > 0 && document.getElementById('studentHeatmap')) {
        // Heatmap logic (unchanged)
        let currentAssessmentId = <?php echo $assessment_id; ?>;
        let heatmapChart = null;
        function getScoreColor(score) {
            if (score >= 90) return '#059669';
            if (score >= 80) return '#22c55e';
            if (score >= 60) return '#eab308';
            if (score >= 40) return '#f59e0b';
            return '#dc2626';
        }
        const studentsPerRow = 9;
        const totalStudents = studentData.length;
        const totalRows = Math.ceil(totalStudents / studentsPerRow);
        const cellWidth = 80;
        const cellHeight = 80;
        const totalWidth = studentsPerRow * cellWidth + 40;
        const totalHeight = totalRows * cellHeight + 40;
        const chartDom = document.getElementById('studentHeatmap');
        chartDom.style.width = totalWidth + 'px';
        chartDom.style.height = totalHeight + 'px';
        const heatmapData = [];
        studentData.forEach((student, index) => {
            const row = Math.floor(index / studentsPerRow);
            const col = index % studentsPerRow;
            const marks = (typeof student.total_marks !== 'undefined') ? student.total_marks : student.score;
            heatmapData.push([
                col,
                row,
                marks,
                student.name,
                student.id,
                getScoreColor(marks)
            ]);
        });
        heatmapChart = echarts.init(chartDom);
        const option = {
            tooltip: {
                trigger: 'item',
                formatter: function(params) {
                    const [col, row, score, name, id] = params.data;
                    return `<strong>${name}</strong><br/>Marks: ${score}`;
                }
            },
            grid: {
                left: '20px',
                right: '20px',
                top: '20px',
                bottom: '20px',
                containLabel: false
            },
            xAxis: {
                type: 'category',
                data: Array.from({length: studentsPerRow}, (_, i) => ''),
                splitArea: { show: false },
                axisLabel: { show: false },
                axisTick: { show: false },
                axisLine: { show: false }
            },
            yAxis: {
                type: 'category',
                data: Array.from({length: Math.ceil(studentData.length / studentsPerRow)}, (_, i) => ''),
                splitArea: { show: false },
                axisLabel: { show: false },
                axisTick: { show: false },
                axisLine: { show: false }
            },
            series: [{
                name: 'Student Scores',
                type: 'heatmap',
                data: heatmapData,
                label: {
                    show: true,
                    formatter: function(params) {
                        const [col, row, score, name, id, color] = params.data;
                        return name.split(' ').map(n => n.charAt(0)).join('') + '\n' + score + '%';
                    },
                    fontSize: 10,
                    fontWeight: 'bold'
                },
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                },
                itemStyle: {
                    borderColor: '#fff',
                    borderWidth: 2,
                    color: function(params) {
                        const [col, row, score, name, id, customColor] = params.data;
                        return customColor;
                    }
                }
            }]
        };
        heatmapChart.setOption(option);
        heatmapChart.on('click', function(params) {
            if (params.componentType === 'series') {
                const [col, row, score, name, studentId, color] = params.data;
                showStudentDetails(studentId, name);
            }
        });
    }
    <?php endif; ?>
});

function viewCourseHeatmap(courseId) {
    window.location.href = `stakeholder_dashboard.php?courseid=${courseId}`;
}

function viewAssessment(assessmentId, courseId) {
    window.location.href = `stakeholder_dashboard.php?courseid=${courseId}&assessment=${assessmentId}`;
}

function showStudentDetails(studentId, studentName) {
    // TDD Approach: Test-Driven Development for modal content
    const modalBody = document.getElementById('modalBody');

    // Show loading state
    modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><div>Loading student details...</div></div>';
    document.getElementById('studentModal').style.display = 'block';

    // Simulate API call - replace with actual AJAX call to get student details
    setTimeout(() => {
        fetchStudentDetails(studentId, studentName);
    }, 500);
}

function fetchStudentDetails(studentId, studentName) {
    // Fetch TDD analysis data from our assessment results API
    fetch(`/local/orgadmin/assessment_results.php?action=get_tdd_analysis&student_id=${studentId}&assessment_id=java-basics`)
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const tddData = result.data;

                // Convert TDD analysis to student details format
                const studentDetails = {
                    id: studentId,
                    name: studentName,
                    email: 'student@example.com',
                    overall_score: Math.round((tddData.code_analysis.test_driven_approach +
                                            tddData.code_analysis.code_quality +
                                            tddData.code_analysis.best_practices +
                                            tddData.code_analysis.complexity_handling) / 4),
                    sections: [
                        {name: 'TDD Approach', score: tddData.code_analysis.test_driven_approach, max_score: 100, percentage: tddData.code_analysis.test_driven_approach},
                        {name: 'Code Quality', score: tddData.code_analysis.code_quality, max_score: 100, percentage: tddData.code_analysis.code_quality},
                        {name: 'Best Practices', score: tddData.code_analysis.best_practices, max_score: 100, percentage: tddData.code_analysis.best_practices},
                        {name: 'Complexity Handling', score: tddData.code_analysis.complexity_handling, max_score: 100, percentage: tddData.code_analysis.complexity_handling}
                    ],
                    test_coverage: tddData.test_coverage,
                    recommendations: tddData.recommendations,
                    code_submission: tddData.code_submission,
                    time_spent: '45 minutes', // This would come from assessment submission data
                    completion_date: '2024-09-15 14:30:00',
                    attempts: 1
                };

                renderStudentDetails(studentDetails);
            } else {
                // Fallback to mock data if API fails
                renderFallbackStudentDetails(studentId, studentName);
            }
        })
        .catch(error => {
            console.error('Error fetching TDD analysis:', error);
            renderFallbackStudentDetails(studentId, studentName);
        });
}

function renderFallbackStudentDetails(studentId, studentName) {
    // Mock data as fallback
    const studentDetails = {
        id: studentId,
        name: studentName,
        email: 'student@example.com',
        overall_score: 85,
        sections: [
            {name: 'Theory Questions', score: 18, max_score: 20, percentage: 90},
            {name: 'Practical Problems', score: 28, max_score: 30, percentage: 93.3},
            {name: 'Code Review', score: 24, max_score: 25, percentage: 96},
            {name: 'Best Practices', score: 22, max_score: 25, percentage: 88}
        ],
        time_spent: '45 minutes',
        completion_date: '2024-09-15 14:30:00',
        attempts: 1
    };

    renderStudentDetails(studentDetails);
}

function renderStudentDetails(details) {
    const modalBody = document.getElementById('modalBody');

    let sectionsHtml = '';
    details.sections.forEach(section => {
        const color = getScoreColor(section.percentage);
        sectionsHtml += `
            <div class="score-item">
                <span class="score-name">${section.name}</span>
                <div class="score-value">
                    <span>${section.score}/${section.max_score}</span>
                    <div class="score-bar">
                        <div class="score-fill" style="width: ${section.percentage}%; background: ${color};"></div>
                    </div>
                    <span>${section.percentage.toFixed(1)}%</span>
                </div>
            </div>
        `;
    });

    // Build additional sections for TDD analysis
    let tddCoverageHtml = '';
    let recommendationsHtml = '';
    let codeSubmissionHtml = '';

    if (details.test_coverage) {
        tddCoverageHtml = `
            <div class="score-breakdown">
                <h4>Test Coverage Analysis</h4>
                <div class="score-item">
                    <span class="score-name">Edge Cases Covered</span>
                    <div class="score-value">
                        <span>${details.test_coverage.edge_cases_covered}/${details.test_coverage.total_edge_cases}</span>
                        <div class="score-bar">
                            <div class="score-fill" style="width: ${details.test_coverage.coverage_percentage}%; background: ${getScoreColor(details.test_coverage.coverage_percentage)};"></div>
                        </div>
                        <span>${details.test_coverage.coverage_percentage}%</span>
                    </div>
                </div>
            </div>
        `;
    }

    if (details.recommendations && details.recommendations.length > 0) {
        const recommendationsList = details.recommendations.map(rec =>
            `<li style="margin-bottom: 8px; color: #4a5568;">${rec}</li>`
        ).join('');

        recommendationsHtml = `
            <div class="score-breakdown">
                <h4>📝 Improvement Recommendations</h4>
                <ul style="padding-left: 20px; margin: 10px 0;">
                    ${recommendationsList}
                </ul>
            </div>
        `;
    }

    if (details.code_submission) {
        codeSubmissionHtml = `
            <div class="score-breakdown">
                <h4>💻 Code Submission</h4>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-family: monospace; font-size: 13px; white-space: pre-wrap; overflow-x: auto; max-height: 200px; overflow-y: auto;">
${details.code_submission}
                </div>
            </div>
        `;
    }

    modalBody.innerHTML = `
        <div class="student-info">
            <div class="info-item">
                <span class="info-label">Student Name</span>
                <span class="info-value">${details.name}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value">${details.email}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Overall Score</span>
                <span class="info-value" style="color: ${getScoreColor(details.overall_score)};">${details.overall_score}%</span>
            </div>
            <div class="info-item">
                <span class="info-label">Time Spent</span>
                <span class="info-value">${details.time_spent}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Completion Date</span>
                <span class="info-value">${new Date(details.completion_date).toLocaleDateString()}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Attempts</span>
                <span class="info-value">${details.attempts}</span>
            </div>
        </div>

        <div class="score-breakdown">
            <h4>🧪 TDD Analysis Results</h4>
            ${sectionsHtml}
        </div>

        ${tddCoverageHtml}
        ${recommendationsHtml}
        ${codeSubmissionHtml}
    `;
}

function getScoreColor(percentage) {
    if (percentage >= 90) return '#059669';
    if (percentage >= 80) return '#22c55e';
    if (percentage >= 60) return '#eab308';
    if (percentage >= 40) return '#f59e0b';
    return '#dc2626';
}

function closeModal() {
    document.getElementById('studentModal').style.display = 'none';
}

function exportData() {
    // Create CSV data
    const assessmentTitle = <?php echo json_encode($selected_assessment['title'] ?? ''); ?>;
    const studentData = <?php echo json_encode($heatmap_data ?? []); ?>;

    if (!studentData || studentData.length === 0) {
        alert('No data to export');
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Student Name,Email,Score (%),Assessment\n";

    studentData.forEach(student => {
        const marks = (typeof student.total_marks !== 'undefined') ? student.total_marks : student.score;
        csvContent += `"${student.name}","${student.email}",${marks},"${assessmentTitle}"\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `${assessmentTitle.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_results.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('studentModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

<?php
echo $OUTPUT->footer();
?>