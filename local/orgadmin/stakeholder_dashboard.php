<?php
// local/orgadmin/stakeholder_dashboard.php - Stakeholder Dashboard

require_once('../../config.php');
require_once('./role_detector.php');

// Require login
require_login();

// Check if user should see stakeholder dashboard
if (!orgadmin_role_detector::should_show_stakeholder_dashboard()) {
    redirect(new moodle_url('/my/index.php'));
}

// Get parameters
$assessment_id = optional_param('assessment', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 10;

// Set up page
$PAGE->set_url('/local/orgadmin/stakeholder_dashboard.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Stakeholder Dashboard');
$PAGE->set_heading('');

// Mock data functions - replace with real Moodle data
function get_stakeholder_assessments() {
    return [
        [
            'id' => 1,
            'title' => 'Java Basics Assessment',
            'total_students' => 45,
            'completed' => 38,
            'avg_score' => 78.5,
            'status' => 'active'
        ],
        [
            'id' => 2,
            'title' => 'PHP Programming Assessment',
            'total_students' => 32,
            'completed' => 29,
            'avg_score' => 82.3,
            'status' => 'active'
        ],
        [
            'id' => 3,
            'title' => 'Python Fundamentals Assessment',
            'total_students' => 28,
            'completed' => 25,
            'avg_score' => 75.8,
            'status' => 'active'
        ],
        [
            'id' => 4,
            'title' => 'React Development Assessment',
            'total_students' => 35,
            'completed' => 30,
            'avg_score' => 80.2,
            'status' => 'active'
        ]
    ];
}

function get_student_heatmap_data($assessment_id) {
    // Generate different student data based on assessment ID
    $base_students = [
        ['id' => 1, 'name' => 'John Smith', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Emma Wilson', 'email' => 'emma@example.com'],
        ['id' => 3, 'name' => 'Michael Brown', 'email' => 'michael@example.com'],
        ['id' => 4, 'name' => 'Sarah Davis', 'email' => 'sarah@example.com'],
        ['id' => 5, 'name' => 'David Miller', 'email' => 'david@example.com'],
        ['id' => 6, 'name' => 'Lisa Anderson', 'email' => 'lisa@example.com'],
        ['id' => 7, 'name' => 'James Wilson', 'email' => 'james@example.com'],
        ['id' => 8, 'name' => 'Jennifer Taylor', 'email' => 'jennifer@example.com'],
        ['id' => 9, 'name' => 'Robert Johnson', 'email' => 'robert@example.com'],
        ['id' => 10, 'name' => 'Mary Williams', 'email' => 'mary@example.com'],
        ['id' => 11, 'name' => 'Christopher Lee', 'email' => 'chris@example.com'],
        ['id' => 12, 'name' => 'Amanda Garcia', 'email' => 'amanda@example.com'],
        ['id' => 13, 'name' => 'Daniel Martinez', 'email' => 'daniel@example.com'],
        ['id' => 14, 'name' => 'Ashley Rodriguez', 'email' => 'ashley@example.com'],
        ['id' => 15, 'name' => 'Matthew Hernandez', 'email' => 'matthew@example.com'],
        ['id' => 16, 'name' => 'Jessica Lopez', 'email' => 'jessica@example.com'],
        ['id' => 17, 'name' => 'Joshua Gonzalez', 'email' => 'joshua@example.com'],
        ['id' => 18, 'name' => 'Nicole Perez', 'email' => 'nicole@example.com'],
        ['id' => 19, 'name' => 'Anthony Torres', 'email' => 'anthony@example.com'],
        ['id' => 20, 'name' => 'Stephanie Rivera', 'email' => 'stephanie@example.com'],
        // Add more students to test scrolling (simulate larger classes)
        ['id' => 21, 'name' => 'Kevin Brown', 'email' => 'kevin@example.com'],
        ['id' => 22, 'name' => 'Rachel Green', 'email' => 'rachel@example.com'],
        ['id' => 23, 'name' => 'Mark Thompson', 'email' => 'mark@example.com'],
        ['id' => 24, 'name' => 'Lisa Chen', 'email' => 'lisa.chen@example.com'],
        ['id' => 25, 'name' => 'Alex Parker', 'email' => 'alex@example.com'],
        ['id' => 26, 'name' => 'Sophie Miller', 'email' => 'sophie@example.com'],
        ['id' => 27, 'name' => 'Tom Wilson', 'email' => 'tom@example.com'],
        ['id' => 28, 'name' => 'Anna Davis', 'email' => 'anna@example.com'],
        ['id' => 29, 'name' => 'Chris Martin', 'email' => 'chris.martin@example.com'],
        ['id' => 30, 'name' => 'Maya Patel', 'email' => 'maya@example.com'],
        ['id' => 31, 'name' => 'Jake Johnson', 'email' => 'jake@example.com'],
        ['id' => 32, 'name' => 'Emily White', 'email' => 'emily@example.com'],
        ['id' => 33, 'name' => 'Ryan Clark', 'email' => 'ryan@example.com'],
        ['id' => 34, 'name' => 'Grace Lee', 'email' => 'grace@example.com'],
        ['id' => 35, 'name' => 'Nathan Hall', 'email' => 'nathan@example.com']
    ];

    // Generate different score patterns based on assessment type
    $students = [];
    foreach ($base_students as $student) {
        $score = 0;

        switch ($assessment_id) {
            case 1: // Java Basics Assessment - Mixed performance
                $scores = [95, 88, 92, 76, 85, 67, 91, 73, 89, 84, 55, 78, 82, 70, 86, 93, 79, 87, 64, 90,
                          83, 77, 91, 68, 85, 72, 88, 75, 81, 79, 86, 74, 89, 76, 83];
                $score = $scores[$student['id'] - 1] ?? 75;
                break;

            case 2: // PHP Programming Assessment - Generally higher scores
                $scores = [98, 91, 95, 83, 89, 77, 94, 81, 92, 88, 72, 85, 87, 79, 90, 96, 84, 91, 75, 93,
                          89, 86, 94, 80, 92, 87, 95, 82, 90, 85, 93, 81, 96, 84, 91];
                $score = $scores[$student['id'] - 1] ?? 85;
                break;

            case 3: // Python Fundamentals Assessment - Lower average scores
                $scores = [82, 75, 88, 62, 71, 54, 86, 68, 77, 73, 43, 65, 74, 58, 79, 89, 66, 81, 51, 84,
                          69, 72, 65, 58, 76, 63, 80, 67, 74, 61, 78, 64, 82, 69, 75];
                $score = $scores[$student['id'] - 1] ?? 65;
                break;

            case 4: // React Development Assessment - Variable performance
                $scores = [89, 94, 78, 85, 92, 69, 87, 76, 91, 83, 58, 81, 88, 72, 86, 95, 74, 89, 63, 92,
                          85, 79, 93, 71, 88, 75, 91, 77, 84, 80, 87, 73, 90, 76, 89];
                $score = $scores[$student['id'] - 1] ?? 80;
                break;

            default:
                // Random scores for unknown assessments
                $score = rand(40, 98);
        }

        $students[] = [
            'id' => $student['id'],
            'name' => $student['name'],
            'email' => $student['email'],
            'score' => $score
        ];
    }

    return $students;
}

function get_student_details($student_id, $assessment_id) {
    // Mock detailed student performance data
    return [
        'id' => $student_id,
        'name' => 'John Smith',
        'email' => 'john@example.com',
        'overall_score' => 95,
        'sections' => [
            ['name' => 'Theory Questions', 'score' => 18, 'max_score' => 20, 'percentage' => 90],
            ['name' => 'Practical Problems', 'score' => 28, 'max_score' => 30, 'percentage' => 93.3],
            ['name' => 'Code Review', 'score' => 24, 'max_score' => 25, 'percentage' => 96],
            ['name' => 'Best Practices', 'score' => 22, 'max_score' => 25, 'percentage' => 88]
        ],
        'time_spent' => '45 minutes',
        'completion_date' => '2025-09-10 14:30:00',
        'attempts' => 1
    ];
}

$assessments = get_stakeholder_assessments();
$selected_assessment = null;
$heatmap_data = null;

if ($assessment_id > 0) {
    foreach ($assessments as $assessment) {
        if ($assessment['id'] == $assessment_id) {
            $selected_assessment = $assessment;
            $heatmap_data = get_student_heatmap_data($assessment_id);
            break;
        }
    }
}

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
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
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
            <p class="stakeholder-welcome-subtitle">Monitor and analyze student assessment performance</p>
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
        <!-- Assessment List (Left Sidebar) -->
        <div class="assessment-list">
            <h3>Assessment Overview</h3>
            <?php foreach ($assessments as $assessment): ?>
                <div class="assessment-item <?php echo ($assessment_id == $assessment['id']) ? 'active' : ''; ?>">
                    <div class="assessment-title"><?php echo htmlspecialchars($assessment['title']); ?></div>
                    <div class="assessment-stats">
                        <span><?php echo $assessment['completed']; ?>/<?php echo $assessment['total_students']; ?> completed</span>
                        <span>Avg: <?php echo $assessment['avg_score']; ?>%</span>
                    </div>
                    <button class="view-btn" onclick="viewAssessment(<?php echo $assessment['id']; ?>)">
                        View Student Heatmap
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Heatmap Area (Right) -->
        <div class="heatmap-container">
            <?php if ($selected_assessment && $heatmap_data): ?>
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
                    <div class="material-icons">assessment</div>
                    <h3>Select an Assessment</h3>
                    <p>Choose an assessment from the left panel to view the student performance heatmap.</p>
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
let currentAssessmentId = <?php echo $assessment_id; ?>;
let heatmapChart = null;

<?php if ($selected_assessment && $heatmap_data): ?>
// Initialize heatmap on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeHeatmap();
});

function initializeHeatmap() {
    const chartDom = document.getElementById('studentHeatmap');
    heatmapChart = echarts.init(chartDom);

    // Student data from PHP
    const studentData = <?php echo json_encode($heatmap_data); ?>;

    // Function to get color based on score
    function getScoreColor(score) {
        if (score >= 90) return '#059669'; // Dark green for 90-100%
        if (score >= 80) return '#22c55e'; // Light green for 80-89%
        if (score >= 60) return '#eab308'; // Yellow for 60-79%
        if (score >= 40) return '#f59e0b'; // Orange for 40-59%
        return '#dc2626'; // Red for 0-39%
    }

    // Calculate dynamic dimensions
    const studentsPerRow = 9; // 9 students per row (9x9 grid)
    const totalStudents = studentData.length;
    const totalRows = Math.ceil(totalStudents / studentsPerRow);
    const actualColumnsInLastRow = totalStudents % studentsPerRow || studentsPerRow;

    // Cell dimensions
    const cellWidth = 80;  // Width of each student cell
    const cellHeight = 80; // Height of each student cell (square)

    // Calculate total dimensions based on actual data
    const totalWidth = studentsPerRow * cellWidth + 40; // Add padding
    const totalHeight = totalRows * cellHeight + 40; // Add padding

    // Set chart container size
    chartDom.style.width = totalWidth + 'px';
    chartDom.style.height = totalHeight + 'px';

    // Prepare data for heatmap (grid layout)
    const heatmapData = [];

    studentData.forEach((student, index) => {
        const row = Math.floor(index / studentsPerRow);
        const col = index % studentsPerRow;

        heatmapData.push([
            col, // x coordinate
            row, // y coordinate
            student.score, // value for color
            student.name,
            student.id,
            getScoreColor(student.score) // custom color
        ]);
    });

    const option = {
        tooltip: {
            trigger: 'item',
            formatter: function(params) {
                const [col, row, score, name, id] = params.data;
                return `<strong>${name}</strong><br/>Score: ${score}%`;
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
            splitArea: {
                show: false
            },
            axisLabel: {
                show: false
            },
            axisTick: {
                show: false
            },
            axisLine: {
                show: false
            }
        },
        yAxis: {
            type: 'category',
            data: Array.from({length: Math.ceil(studentData.length / studentsPerRow)}, (_, i) => ''),
            splitArea: {
                show: false
            },
            axisLabel: {
                show: false
            },
            axisTick: {
                show: false
            },
            axisLine: {
                show: false
            }
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

    // Add click event
    heatmapChart.on('click', function(params) {
        if (params.componentType === 'series') {
            const [col, row, score, name, studentId, color] = params.data;
            showStudentDetails(studentId, name);
        }
    });
}
<?php endif; ?>

function viewAssessment(assessmentId) {
    window.location.href = `stakeholder_dashboard.php?assessment=${assessmentId}`;
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
    // Mock data - replace with actual AJAX call
    const studentDetails = {
        id: studentId,
        name: studentName,
        email: 'student@example.com',
        overall_score: 95,
        sections: [
            {name: 'Theory Questions', score: 18, max_score: 20, percentage: 90},
            {name: 'Practical Problems', score: 28, max_score: 30, percentage: 93.3},
            {name: 'Code Review', score: 24, max_score: 25, percentage: 96},
            {name: 'Best Practices', score: 22, max_score: 25, percentage: 88}
        ],
        time_spent: '45 minutes',
        completion_date: '2025-09-10 14:30:00',
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
            <h4>Section-wise Performance</h4>
            ${sectionsHtml}
        </div>
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
        csvContent += `"${student.name}","${student.email}",${student.score},"${assessmentTitle}"\n`;
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