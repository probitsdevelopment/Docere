<?php
// local/orgadmin/assessment_report.php - Assessment Report View
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/assessment_report.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title('Assessment Report');
$PAGE->set_heading('');

// Verify user should access L&D dashboard
if (!orgadmin_role_detector::should_show_lnd_dashboard()) {
    $dashboardurl = orgadmin_role_detector::get_dashboard_url();
    redirect($dashboardurl);
}

// Get assessment ID from URL parameter
$assessmentId = optional_param('id', 'java-basics-active', PARAM_ALPHANUMEXT);

// Assessment data mapping - In real implementation, fetch from database
$assessmentDataMap = [
    'java-basics-1' => [
        'title' => 'Java Basics Test - Result',
        'description' => 'Comprehensive assessment covering Java fundamentals, object-oriented programming, data structures, and basic algorithms.',
        'totalStudents' => 156,
        'completedAssessments' => 89,
        'averageScore' => 72,
        'above60' => 78
    ],
    'java-basics-active' => [
        'title' => 'Java Basics Test - Result',
        'description' => 'Comprehensive assessment covering Java fundamentals, object-oriented programming, data structures, and basic algorithms.',
        'totalStudents' => 156,
        'completedAssessments' => 89,
        'averageScore' => 65,
        'above60' => 67
    ],
    'assessment-1' => [
        'title' => 'Advanced Java Programming - Result',
        'description' => 'Advanced assessment covering Spring Framework, microservices, design patterns, and enterprise Java development.',
        'totalStudents' => 89,
        'completedAssessments' => 67,
        'averageScore' => 58,
        'above60' => 45
    ],
    'assessment-2' => [
        'title' => 'Database Fundamentals - Result',
        'description' => 'Comprehensive assessment covering SQL, database design, normalization, indexing, and performance optimization.',
        'totalStudents' => 234,
        'completedAssessments' => 198,
        'averageScore' => 68,
        'above60' => 156
    ]
];

// Get assessment data or default
$assessmentData = isset($assessmentDataMap[$assessmentId]) ? $assessmentDataMap[$assessmentId] : $assessmentDataMap['java-basics-active'];

// Student data mapping based on assessment
$studentDataMap = [
    'java-basics-1' => [
        [
            'id' => 1,
            'name' => 'Amit Kumar',
            'email' => 'amit.kumar@example.com',
            'score' => '95/100',
            'percentage' => 95,
            'grade' => 'A',
            'submittedDate' => 'December 10, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 2,
            'name' => 'Priya Sharma',
            'email' => 'priya.sharma@example.com',
            'score' => '82/100',
            'percentage' => 82,
            'grade' => 'A',
            'submittedDate' => 'December 10, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 3,
            'name' => 'Rohit Singh',
            'email' => 'rohit.singh@example.com',
            'score' => '68/100',
            'percentage' => 68,
            'grade' => 'B',
            'submittedDate' => 'December 11, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 4,
            'name' => 'Sneha Patel',
            'email' => 'sneha.patel@example.com',
            'score' => '45/100',
            'percentage' => 45,
            'grade' => 'F',
            'submittedDate' => 'December 11, 2024',
            'status' => 'Needs Review'
        ],
        [
            'id' => 5,
            'name' => 'Vikram Reddy',
            'email' => 'vikram.reddy@example.com',
            'score' => '-',
            'percentage' => 0,
            'grade' => '-',
            'submittedDate' => '-',
            'status' => 'In Progress'
        ]
    ],
    'java-basics-active' => [
        [
            'id' => 1,
            'name' => 'Devon Lane',
            'email' => 'devon.lane@example.com',
            'score' => '90/100',
            'percentage' => 90,
            'grade' => 'A',
            'submittedDate' => 'December 8, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 2,
            'name' => 'Ralph Edwards',
            'email' => 'ralph.edwards@example.com',
            'score' => '74/100',
            'percentage' => 74,
            'grade' => 'B',
            'submittedDate' => 'December 9, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 3,
            'name' => 'Floyd Miles',
            'email' => 'floyd.miles@example.com',
            'score' => '82/100',
            'percentage' => 82,
            'grade' => 'A',
            'submittedDate' => 'December 9, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 4,
            'name' => 'Brooklyn Simmons',
            'email' => 'brooklyn.simmons@example.com',
            'score' => '60/100',
            'percentage' => 60,
            'grade' => 'B',
            'submittedDate' => 'December 10, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 5,
            'name' => 'Eleanor Pena',
            'email' => 'eleanor.pena@example.com',
            'score' => '55/100',
            'percentage' => 55,
            'grade' => 'F',
            'submittedDate' => 'December 10, 2024',
            'status' => 'Needs Review'
        ],
        [
            'id' => 6,
            'name' => 'Jerome Bell',
            'email' => 'jerome.bell@example.com',
            'score' => '-',
            'percentage' => 0,
            'grade' => '-',
            'submittedDate' => '-',
            'status' => 'In Progress'
        ]
    ],
    'assessment-1' => [
        [
            'id' => 1,
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@example.com',
            'score' => '88/100',
            'percentage' => 88,
            'grade' => 'A',
            'submittedDate' => 'December 5, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 2,
            'name' => 'Michael Chen',
            'email' => 'michael.chen@example.com',
            'score' => '72/100',
            'percentage' => 72,
            'grade' => 'B',
            'submittedDate' => 'December 6, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 3,
            'name' => 'Lisa Rodriguez',
            'email' => 'lisa.rodriguez@example.com',
            'score' => '45/100',
            'percentage' => 45,
            'grade' => 'F',
            'submittedDate' => 'December 6, 2024',
            'status' => 'Needs Review'
        ],
        [
            'id' => 4,
            'name' => 'David Kim',
            'email' => 'david.kim@example.com',
            'score' => '-',
            'percentage' => 0,
            'grade' => '-',
            'submittedDate' => '-',
            'status' => 'In Progress'
        ]
    ],
    'assessment-2' => [
        [
            'id' => 1,
            'name' => 'Anna Williams',
            'email' => 'anna.williams@example.com',
            'score' => '92/100',
            'percentage' => 92,
            'grade' => 'A',
            'submittedDate' => 'December 7, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 2,
            'name' => 'James Brown',
            'email' => 'james.brown@example.com',
            'score' => '78/100',
            'percentage' => 78,
            'grade' => 'B',
            'submittedDate' => 'December 8, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 3,
            'name' => 'Maria Garcia',
            'email' => 'maria.garcia@example.com',
            'score' => '65/100',
            'percentage' => 65,
            'grade' => 'B',
            'submittedDate' => 'December 8, 2024',
            'status' => 'Completed'
        ],
        [
            'id' => 4,
            'name' => 'Robert Davis',
            'email' => 'robert.davis@example.com',
            'score' => '52/100',
            'percentage' => 52,
            'grade' => 'F',
            'submittedDate' => 'December 9, 2024',
            'status' => 'Needs Review'
        ],
        [
            'id' => 5,
            'name' => 'Jennifer Wilson',
            'email' => 'jennifer.wilson@example.com',
            'score' => '-',
            'percentage' => 0,
            'grade' => '-',
            'submittedDate' => '-',
            'status' => 'In Progress'
        ]
    ]
];

// Get student data based on assessment ID
$studentData = isset($studentDataMap[$assessmentId]) ? $studentDataMap[$assessmentId] : $studentDataMap['java-basics-active'];

echo $OUTPUT->header();

// Include Google Material Icons
echo html_writer::empty_tag('link', [
    'rel' => 'stylesheet',
    'href' => 'https://fonts.googleapis.com/icon?family=Material+Icons'
]);

// Custom CSS for Assessment Report
echo html_writer::start_tag('style');
echo '
/* Reset and base styles */
html, body {
    background-color: #ffffff !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    height: 100vh !important;
}


/* Override Moodle container constraints */
#page-wrapper,
#page,
#page-content,
.container-fluid,
#region-main-box,
#region-main,
.row {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden !important;
}

.page-header-headings {
    display: none !important;
}

/* Assessment Report Container */
.report-container {
    width: 100%;
    max-width: none;
    margin: 20px 0px 0 0;
    padding: 10px 20px 0 20px;
    background: #ffffff;
    height: calc(100vh - 90px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Header Section */
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    flex-shrink: 0;
}

.report-title-section {
    flex: 1;
}

.report-title {
    font-size: 1.6em;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 5px 0;
}

.report-description {
    color: #7f8c8d;
    font-size: 0.9em;
    margin: 0;
    max-width: 600px;
    line-height: 1.3;
}

.download-btn {
    background: #58CC02;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
    font-size: 12px;
}

.download-btn:hover {
    background: #4CAF02;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
}

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 15px;
    flex-shrink: 0;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 15px 12px;
    text-align: left;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);

    
    border: 1px solid #e9ecef;
    position: relative;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 12px;
}

.stat-card.total-students {
    background: #CDEBFA;
    border-color: #1CB0F6;
}

.stat-card.completed {
    background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
    border-color: #58CC02;
}

.stat-card.average {
    background: #FAEDCC;
    border-color: #FFBB10;
}

.stat-card.above60 {
    background: #F3D0D1;
    border-color: #DC2626;
}

.stat-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    flex-shrink: 0;
}

.stat-icon .material-icons {
    font-size: 18px;
    color: white;
}

.stat-card.total-students .stat-icon {
    background: #1CB0F6;
}

.stat-card.completed .stat-icon {
    background: #58CC02;
}

.stat-card.average .stat-icon {
    background: #FFBB10;
}

.stat-card.above60 .stat-icon {
    background: #DC2626;
}

.stat-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stat-title {
    font-size: 0.75em;
    color: #5a6c7d;
    margin: 0 0 4px 0;
    font-weight: 600;
    text-transform: capitalize;
    line-height: 1.3;
}

.stat-value {
    font-size: 1.8em;
    font-weight: 700;
    margin: 0;
    line-height: 1;
}

.stat-card.total-students .stat-value {
    color: #1CB0F6;
}

.stat-card.completed .stat-value {
    color: #58CC02;
}

.stat-card.average .stat-value {
    color: #FFBB10;
}

.stat-card.above60 .stat-value {
    color: #DC2626;
}

/* Search and Filter Section */
.search-section {
    background: white;
    border-radius: 6px;
    padding: 4px;
    margin: 0 0 8px 0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    flex-shrink: 0;
}

.search-controls {
    display: flex;
    gap: 12px;
    align-items: center;
}

.search-box {
    flex: 0 0 300px;
    position: relative;
}

.search-input {
    width: 100%;
    padding: 8px 12px 8px 35px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 12px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #3498db;
    background: white;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-icon .material-icons {
    font-size: 16px;
}

.filter-dropdown {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 12px;
    background: white;
    cursor: pointer;
    min-width: 120px;
}

/* Student Table */
.students-table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    overflow: hidden;
    margin: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
}

.table-body-wrapper {
    flex: 1;
    overflow-y: auto;
    max-height: 300px;
}

.students-table thead {
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 1;
}

.students-table th {
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    font-size: 12px;
    border-bottom: 1px solid #e9ecef;
}

.students-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f3f4;
    vertical-align: middle;
    font-size: 12px;
}

.students-table tbody tr:hover {
    background: #f8f9fa;
}

.student-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.student-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #3498db;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 10px;
}

.student-details h4 {
    margin: 0 0 1px 0;
    font-size: 12px;
    font-weight: 600;
    color: #2c3e50;
}

.student-details p {
    margin: 0;
    font-size: 10px;
    color: #7f8c8d;
}

.score-cell {
    font-weight: 600;
    font-size: 11px;
}

.score-good {
    color: #58CC02;
}

.score-average {
    color: #F39C12;
}

.score-poor {
    color: #E74C3C;
}

.grade-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 11px;
    color: white;
}

.grade-a {
    background: #58CC02;
}

.grade-b {
    background: #F39C12;
}

.grade-f {
    background: #E74C3C;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
}

.status-completed {
    background: #D4EDDA;
    color: #58CC02;
}

.status-needs-review {
    background: #F8D7DA;
    color: #721C24;
}

.status-in-progress {
    background: #FFF3CD;
    color: #856404;
}

.action-buttons {
    display: flex;
    gap: 4px;
}

.action-btn {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.action-btn .material-icons {
    font-size: 14px;
}

.action-btn.view {
    background: #1CB0F6;
    color: white;
}

.action-btn.view:hover {
    background: #2980B9;
    transform: scale(1.1);
}

.action-btn.download {
    background: #58CC02;
    color: white;
}

.action-btn.download:hover {
    background: #4CAF02;
    transform: scale(1.1);
}

/* Pagination */
.pagination-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px 0 20px;
    background: white;
    border-top: 1px solid #e9ecef;
    flex-shrink: 0;
    min-height:50px;
}

.pagination-info {
    color: #7f8c8d;
    font-size: 14px;
}

.pagination-controls {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pagination-btn {
    width: 40px;
    height: 40px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination-btn:hover {
    border-color: #3498db;
    color: #3498db;
}

.pagination-btn.active {
    background: #3498db;
    color: white;
    border-color: #3498db;
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
';
echo html_writer::end_tag('style');

// Main Report Container
echo html_writer::start_div('report-container');

// Header Section
echo html_writer::start_div('report-header');
echo html_writer::start_div('report-title-section');
echo html_writer::tag('h1', $assessmentData['title'], ['class' => 'report-title']);
echo html_writer::tag('p', $assessmentData['description'], ['class' => 'report-description']);
echo html_writer::end_div();

echo html_writer::start_tag('button', ['class' => 'download-btn', 'onclick' => 'downloadReport()']);
echo html_writer::tag('i', 'file_download', ['class' => 'material-icons', 'style' => 'font-size: 16px; margin-right: 4px;']);
echo html_writer::span('Download PDF Report');
echo html_writer::end_tag('button');
echo html_writer::end_div();

// Statistics Cards
echo html_writer::start_div('stats-grid');

// Total Students Card
echo html_writer::start_div('stat-card total-students');
echo html_writer::start_div('stat-content');
echo html_writer::tag('p', 'Total Students', ['class' => 'stat-title']);
echo html_writer::tag('h2', number_format($assessmentData['totalStudents']), ['class' => 'stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('stat-icon');
echo html_writer::tag('i', 'group', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Completed Assessment Card
echo html_writer::start_div('stat-card completed');
echo html_writer::start_div('stat-content');
echo html_writer::tag('p', 'Completed Assessment', ['class' => 'stat-title']);
echo html_writer::tag('h2', $assessmentData['completedAssessments'], ['class' => 'stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('stat-icon');
echo html_writer::tag('i', 'check_circle', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Average Score Card
echo html_writer::start_div('stat-card average');
echo html_writer::start_div('stat-content');
echo html_writer::tag('p', 'Average Score', ['class' => 'stat-title']);
echo html_writer::tag('h2', $assessmentData['averageScore'], ['class' => 'stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('stat-icon');
echo html_writer::tag('i', 'bar_chart', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Above 60 Card
echo html_writer::start_div('stat-card above60');
echo html_writer::start_div('stat-content');
echo html_writer::tag('p', 'Above 60', ['class' => 'stat-title']);
echo html_writer::tag('h2', $assessmentData['above60'], ['class' => 'stat-value']);
echo html_writer::end_div();
echo html_writer::start_div('stat-icon');
echo html_writer::tag('i', 'trending_up', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End stats grid

// Search and Filter Section
echo html_writer::start_div('search-section');
echo html_writer::start_div('search-controls');

echo html_writer::start_div('search-box');
echo html_writer::start_div('search-icon');
echo html_writer::tag('i', 'search', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'class' => 'search-input',
    'placeholder' => 'Search Student name, ID...',
    'id' => 'studentSearch',
    'oninput' => 'filterStudents()'
]);
echo html_writer::end_div();

echo html_writer::start_tag('select', ['class' => 'filter-dropdown', 'id' => 'scoreFilter', 'onchange' => 'filterStudents()']);
echo html_writer::tag('option', 'All Scores', ['value' => 'all']);
echo html_writer::tag('option', 'Above 80%', ['value' => 'above80']);
echo html_writer::tag('option', 'Above 60%', ['value' => 'above60']);
echo html_writer::tag('option', 'Below 60%', ['value' => 'below60']);
echo html_writer::end_tag('select');

echo html_writer::end_div();
echo html_writer::end_div();

// Students Table
echo html_writer::start_div('students-table-container');
echo html_writer::start_div('table-body-wrapper');
echo html_writer::start_tag('table', ['class' => 'students-table', 'id' => 'studentsTable']);

// Table Header
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', 'Student Details', ['style' => 'width: 25%;']);
echo html_writer::tag('th', 'Scores', ['style' => 'width: 15%; text-align: center;']);
echo html_writer::tag('th', 'Grade', ['style' => 'width: 10%; text-align: center;']);
echo html_writer::tag('th', 'Submitted Date', ['style' => 'width: 20%; text-align: center;']);
echo html_writer::tag('th', 'Status', ['style' => 'width: 15%; text-align: center;']);
echo html_writer::tag('th', 'Action', ['style' => 'width: 15%; text-align: center;']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

// Table Body
echo html_writer::start_tag('tbody', ['id' => 'studentsTableBody']);

foreach ($studentData as $student) {
    echo html_writer::start_tag('tr', ['data-name' => strtolower($student['name']), 'data-email' => strtolower($student['email']), 'data-percentage' => $student['percentage']]);
    
    // Student Details
    echo html_writer::start_tag('td', ['style' => 'width: 25%;']);
    echo html_writer::start_div('student-info');
    
    // Avatar with initials
    $initials = substr($student['name'], 0, 1) . substr(explode(' ', $student['name'])[1], 0, 1);
    echo html_writer::div($initials, 'student-avatar');
    
    echo html_writer::start_div('student-details');
    echo html_writer::tag('h4', $student['name']);
    echo html_writer::tag('p', $student['email']);
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_tag('td');
    
    // Scores
    echo html_writer::start_tag('td', ['style' => 'width: 15%; text-align: center;']);
    $scoreClass = '';
    if ($student['percentage'] >= 80) {
        $scoreClass = 'score-good';
    } elseif ($student['percentage'] >= 60) {
        $scoreClass = 'score-average';
    } elseif ($student['percentage'] > 0) {
        $scoreClass = 'score-poor';
    }
    
    echo html_writer::span($student['score'], "score-cell $scoreClass");
    if ($student['percentage'] > 0) {
        echo html_writer::span(" ({$student['percentage']}%)", "score-cell $scoreClass");
    }
    echo html_writer::end_tag('td');
    
    // Grade
    echo html_writer::start_tag('td', ['style' => 'width: 10%; text-align: center;']);
    if ($student['grade'] !== '-') {
        $gradeClass = '';
        switch ($student['grade']) {
            case 'A': $gradeClass = 'grade-a'; break;
            case 'B': $gradeClass = 'grade-b'; break;
            case 'F': $gradeClass = 'grade-f'; break;
        }
        echo html_writer::span($student['grade'], "grade-badge $gradeClass");
    } else {
        echo html_writer::span('-', '');
    }
    echo html_writer::end_tag('td');
    
    // Submitted Date
    echo html_writer::tag('td', $student['submittedDate'], ['style' => 'width: 20%; text-align: center;']);
    
    // Status
    echo html_writer::start_tag('td', ['style' => 'width: 15%; text-align: center;']);
    $statusClass = '';
    switch ($student['status']) {
        case 'Completed': $statusClass = 'status-completed'; break;
        case 'Needs Review': $statusClass = 'status-needs-review'; break;
        case 'In Progress': $statusClass = 'status-in-progress'; break;
    }
    echo html_writer::span($student['status'], "status-badge $statusClass");
    echo html_writer::end_tag('td');
    
    // Actions 
    echo html_writer::start_tag('td', ['style' => 'width: 15%; text-align: center;']);
    echo html_writer::start_div('action-buttons');
    
    echo html_writer::start_tag('button', ['class' => 'action-btn view', 'onclick' => "viewStudentDetail({$student['id']})", 'title' => 'View Details']);
    echo html_writer::tag('i', 'visibility', ['class' => 'material-icons']);
    echo html_writer::end_tag('button');
    
    echo html_writer::start_tag('button', ['class' => 'action-btn download', 'onclick' => "downloadStudentReport({$student['id']})", 'title' => 'Download Report']);
    echo html_writer::tag('i', 'download', ['class' => 'material-icons']);
    echo html_writer::end_tag('button');
    
    echo html_writer::end_div();
    echo html_writer::end_tag('td');
    
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div(); // End table body wrapper

// Pagination Section
echo html_writer::start_div('pagination-section');
echo html_writer::span('Showing 1 to 3 of 1245 results', 'pagination-info');

echo html_writer::start_div('pagination-controls');
echo html_writer::tag('button', '‹', ['class' => 'pagination-btn', 'disabled' => true]);
echo html_writer::tag('button', '1', ['class' => 'pagination-btn active']);
echo html_writer::tag('button', '2', ['class' => 'pagination-btn', 'onclick' => 'goToPage(2)']);
echo html_writer::tag('button', '3', ['class' => 'pagination-btn', 'onclick' => 'goToPage(3)']);
echo html_writer::tag('button', '›', ['class' => 'pagination-btn', 'onclick' => 'goToPage(2)']);
echo html_writer::end_div();

echo html_writer::end_div(); // End pagination section

echo html_writer::end_div(); // End table container
echo html_writer::end_div(); // End main container

// JavaScript for functionality
echo html_writer::start_tag('script');
echo '
// Search and filter functionality
function filterStudents() {
    const searchTerm = document.getElementById("studentSearch").value.toLowerCase();
    const scoreFilter = document.getElementById("scoreFilter").value;
    const tableBody = document.getElementById("studentsTableBody");
    const rows = tableBody.getElementsByTagName("tr");
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const name = row.getAttribute("data-name");
        const email = row.getAttribute("data-email");
        const percentage = parseInt(row.getAttribute("data-percentage"));
        
        let showRow = true;
        
        // Search filter
        if (searchTerm && !name.includes(searchTerm) && !email.includes(searchTerm)) {
            showRow = false;
        }
        
        // Score filter
        if (scoreFilter !== "all") {
            switch (scoreFilter) {
                case "above80":
                    if (percentage < 80) showRow = false;
                    break;
                case "above60":
                    if (percentage < 60) showRow = false;
                    break;
                case "below60":
                    if (percentage >= 60 || percentage === 0) showRow = false;
                    break;
            }
        }
        
        row.style.display = showRow ? "" : "none";
    }
}

// Download report functionality
function downloadReport() {
    alert("📄 Generating PDF Report...\\n\\n• Assessment overview\\n• Student performance data\\n• Statistical analysis\\n• Charts and graphs\\n\\nReport will be downloaded shortly.");
}

// View student detail
function viewStudentDetail(studentId) {
    alert("👁 Opening detailed view for Student ID: " + studentId + "\\n\\n• Complete answer review\\n• Time tracking\\n• Question-wise performance\\n• Comparison with class average");
}

// Download student report
function downloadStudentReport(studentId) {
    alert("⬇ Downloading individual report for Student ID: " + studentId + "\\n\\n• PDF format\\n• Detailed performance\\n• Answer analysis\\n• Recommendations");
}

// Pagination
function goToPage(pageNumber) {
    alert("📄 Loading page " + pageNumber + "...\\n\\nIn a real implementation, this would load the next set of students.");
    
    // Update active pagination button
    document.querySelectorAll(".pagination-btn").forEach(btn => {
        btn.classList.remove("active");
        if (btn.textContent == pageNumber) {
            btn.classList.add("active");
        }
    });
}

// Initialize the page
document.addEventListener("DOMContentLoaded", function() {
    console.log("Assessment Report page initialized");
});
';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();
?>