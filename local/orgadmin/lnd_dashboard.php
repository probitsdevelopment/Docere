<?php
// local/orgadmin/lnd_dashboard.php - L&D Manager Dashboard (Exact Screenshot Match)
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/lnd_dashboard.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title('Dashboard');
$PAGE->set_heading('');

// Verify user should access L&D dashboard
if (!orgadmin_role_detector::should_show_lnd_dashboard()) {
    // Redirect non-L&D users to appropriate dashboard
    $dashboardurl = orgadmin_role_detector::get_dashboard_url();
    redirect($dashboardurl);
}

echo $OUTPUT->header();

// Include Google Material Icons
echo html_writer::empty_tag('link', [
    'rel' => 'stylesheet',
    'href' => 'https://fonts.googleapis.com/icon?family=Material+Icons'
]);

// Get current user's name for welcome message
$username = $USER->firstname ?: 'User';

// Custom CSS for L&D Dashboard (Exact Screenshot Match)
echo html_writer::start_tag('style');
echo '
/* Reset and base styles for full width */
html, body {
    background-color: #ffffff !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden !important;
    overflow-y: hidden !important;
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

/* Fix double scrollbar issue */
#page-wrapper {
    overflow: visible !important;
}

#page {
    overflow: visible !important;
    height: auto !important;
}

#region-main {
    overflow: visible !important;
}

/* Hide default page headings */
.page-header-headings {
    display: none !important;
}

/* Style the navbar to match our design */
.navbar {
    background: #fff !important;
    border-bottom: 1px solid #e9ecef !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
}

.navbar-nav .nav-link {
    color: #5a6c7d !important;
    font-weight: 500 !important;
}

.navbar-nav .nav-link:hover,
.navbar-nav .nav-link.active {
    color: #007cba !important;
}

/* L&D Dashboard Styles - Compact Layout */
.lnd-container {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 15px 30px;
    background: #ffffff;
    height: calc(100vh - 70px);
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Welcome Banner - Compact */
.lnd-welcome-banner {
    background: #CDEBFA;
    border-radius: 15px;
    padding: 20px 25px;
    margin-bottom: 25px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(205, 235, 250, 0.3);
    border-top: 1px solid #149EDF;
    border-left: 1px solid #149EDF;
    border-right: 1px solid #149EDF;
    border-bottom: 4px solid #149EDF;
}

.lnd-welcome-content {
    position: relative;
    z-index: 2;
}

.lnd-welcome-title {
    font-size: 1.8em;
    font-weight: 700;
    color: #2d3436;
    margin: 0 0 6px 0;
}

.lnd-welcome-date {
    color: #2d3436;
    font-size: 0.9em;
    margin: 0 0 6px 0;
    opacity: 0.8;
}

.lnd-welcome-subtitle {
    color: #2d3436;
    font-size: 0.95em;
    margin: 0;
    opacity: 0.9;
}

.lnd-character {
    position: absolute;
    right: 30px;
    bottom: -10px;
    width: 120px;
    height: 140px;
    background: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 120 140\'%3E%3Cg%3E%3Ccircle cx=\'60\' cy=\'45\' r=\'35\' fill=\'%23f4b99d\'/%3E%3Ccircle cx=\'50\' cy=\'40\' r=\'3\' fill=\'%23333\'/%3E%3Ccircle cx=\'70\' cy=\'40\' r=\'3\' fill=\'%23333\'/%3E%3Cpath d=\'M45 50 Q60 60 75 50\' stroke=\'%23333\' stroke-width=\'2\' fill=\'none\'/%3E%3Crect x=\'35\' y=\'75\' width=\'50\' height=\'60\' rx=\'5\' fill=\'%234a90e2\'/%3E%3Crect x=\'45\' y=\'85\' width=\'30\' height=\'8\' rx=\'4\' fill=\'%23333\'/%3E%3Cellipse cx=\'25\' cy=\'25\' rx=\'20\' ry=\'35\' fill=\'%23d4931a\'/%3E%3Cellipse cx=\'95\' cy=\'25\' rx=\'20\' ry=\'35\' fill=\'%23d4931a\'/%3E%3C/g%3E%3C/svg%3E") no-repeat center center;
    background-size: contain;
}

.lnd-speech-bubble {
    position: absolute;
    right: 160px;
    top: 20px;
    background: white;
    border-radius: 15px;
    padding: 15px 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    font-size: 0.9em;
    color: #2d3436;
    max-width: 180px;
    z-index: 3;
}

.lnd-speech-bubble::after {
    content: "";
    position: absolute;
    right: -8px;
    top: 20px;
    width: 0;
    height: 0;
    border-left: 8px solid white;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
}

/* Metrics Cards - Borderless Design */
.lnd-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.lnd-metric-card {
    background: white;
    border-radius: 12px;
    padding: 20px 16px;
    text-align: left;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #e9ecef;
    position: relative;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 12px;
    min-height: 85px;
    max-width: 300px;
}


.lnd-metric-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-left: auto;
}

.lnd-metric-icon .material-icons {
    font-size: 16px;
    color: white;
}

.lnd-metric-card.pending .lnd-metric-icon {
    background: #f39c12;
}

.lnd-metric-card.active .lnd-metric-icon {
    background: #2ecc71;
}

.lnd-metric-card.students .lnd-metric-icon {
    background: #3498db;
}

.lnd-metric-card.completion .lnd-metric-icon {
    background: #9b59b6;
}

.lnd-metric-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.lnd-metric-title {
    font-size: 0.75em;
    color: #7f8c8d;
    margin: 0 0 8px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    line-height: 1.3;
    font-weight: 500;
}

.lnd-metric-value {
    font-size: 1.8em;
    font-weight: 700;
    margin: 0;
    line-height: 1;
}

.lnd-metric-card.pending .lnd-metric-value {
    color: #f39c12;
}

.lnd-metric-card.active .lnd-metric-value {
    color: #2ecc71;
}

.lnd-metric-card.students .lnd-metric-value {
    color: #3498db;
}

.lnd-metric-card.completion .lnd-metric-value {
    color: #9b59b6;
}

/* Assessment Tabs - Compact */
.lnd-assessment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.lnd-assessment-tabs {
    display: flex;
    gap: 0;
    background: white;
    border-radius: 8px;
    padding: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.lnd-tab-btn {
    padding: 12px 24px;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    color: #7f8c8d;
}

.lnd-tab-btn.active {
    background: #5FC6F8;
    color: white;
    box-shadow: 0 2px 8px rgba(95, 198, 248, 0.3);
}

.lnd-tab-btn:hover:not(.active) {
    background: #ecf0f1;
    color: #2c3e50;
}

.lnd-view-analysis-btn {
    background: #89DA4D;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(137, 218, 77, 0.3);
}

.lnd-view-analysis-btn:hover {
    background: #7BC73F;
    transform: translateY(-1px);
}

/* Assessment Cards - Compact with Scroll */
.lnd-assessment-list {
    background: white;
    border-radius: 10px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-height: 250px;
    overflow-y: auto;
}

.lnd-assessment-card {
    background: #f8f9fa;
    padding: 12px 16px;
    border-radius: 6px;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
}

.lnd-assessment-card:hover {
    background: white;
    border-color: #3498db;
    box-shadow: 0 2px 8px rgba(52, 152, 219, 0.1);
}

.lnd-assessment-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.lnd-assessment-info {
    flex: 1;
}

.lnd-assessment-title {
    font-size: 1.1em;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.lnd-status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.lnd-status-badge.pending {
    background: #ffeaa7;
    color: #d63031;
}

.lnd-status-badge.active {
    background: #00b894;
    color: white;
}

.lnd-assessment-meta {
    display: flex;
    gap: 20px;
    color: #7f8c8d;
    font-size: 0.85em;
    margin-bottom: 6px;
}

.lnd-assessment-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.lnd-assessment-meta-icon {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.lnd-assessment-meta-icon .material-icons {
    font-size: 12px;
}

.lnd-assessment-meta-icon.creator {
    background: #3498db;
}

.lnd-assessment-meta-icon.questions {
    background: #f39c12;
}

.lnd-assessment-meta-icon.time {
    background: #9b59b6;
}

.lnd-assessment-meta-icon.students {
    background: #2ecc71;
}

.lnd-assessment-meta-icon.completed {
    background: #34495e;
}

.lnd-assessment-meta-icon.score {
    background: #e74c3c;
}

.lnd-assessment-meta-icon.below {
    background: #e67e22;
}

.lnd-assessment-actions {
    display: flex;
    gap: 10px;
}

.lnd-action-btn {
    padding: 8px 14px;
    border: none;
    border-radius: 18px;
    cursor: pointer;
    font-size: 0.8em;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.3s ease;
    min-width: 85px;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.lnd-action-btn.review {
    background: #5FC6F8;
    color: white;
}

.lnd-action-btn.review:hover {
    background: #4AB8F1;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(95, 198, 248, 0.3);
}

.lnd-action-btn.approve {
    background: #89DA4D;
    color: white;
}

.lnd-action-btn.approve:hover {
    background: #7BC73F;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(137, 218, 77, 0.3);
}

.lnd-action-btn.reject {
    background: #E56666;
    color: white;
}

.lnd-action-btn.reject:hover {
    background: #DF4F4F;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(229, 102, 102, 0.3);
}

.lnd-action-btn.add-students {
    background: #5FC6F8;
    color: white;
}

.lnd-action-btn.add-students:hover {
    background: #4AB8F1;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(95, 198, 248, 0.3);
}

.lnd-action-btn.view-report {
    background: #f39c12;
    color: white;
}

.lnd-action-btn.view-report:hover {
    background: #e67e22;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);
}
';
echo html_writer::end_tag('style');

// Main L&D Dashboard Container
echo html_writer::start_div('lnd-container');

// Welcome Banner
echo html_writer::start_div('lnd-welcome-banner');
echo html_writer::start_div('lnd-welcome-content');
echo html_writer::tag('h1', "Welcome Back, $username", ['class' => 'lnd-welcome-title']);
echo html_writer::tag('div', '📅 ' . date('D, d M Y'), ['class' => 'lnd-welcome-date']);
echo html_writer::tag('p', 'Continue your learning journey and take your upcoming assessments', ['class' => 'lnd-welcome-subtitle']);
echo html_writer::end_div();

// Character and Speech Bubble
echo html_writer::div('', 'lnd-character');
echo html_writer::div("Good to see you back, $username.<br>Ready to learn?", 'lnd-speech-bubble');
echo html_writer::end_div();

// Metrics Grid
echo html_writer::start_div('lnd-metrics-grid');

// Pending Assessments
echo html_writer::start_div('lnd-metric-card pending');
echo html_writer::start_div('lnd-metric-content');
echo html_writer::tag('div', 'Pending Assessments', ['class' => 'lnd-metric-title']);
echo html_writer::tag('div', '30', ['class' => 'lnd-metric-value']);
echo html_writer::end_div();
echo html_writer::start_div('lnd-metric-icon');
echo html_writer::tag('i', 'assignment', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Active Assessments
echo html_writer::start_div('lnd-metric-card active');
echo html_writer::start_div('lnd-metric-content');
echo html_writer::tag('div', 'Active Assessments', ['class' => 'lnd-metric-title']);
echo html_writer::tag('div', '80', ['class' => 'lnd-metric-value']);
echo html_writer::end_div();
echo html_writer::start_div('lnd-metric-icon');
echo html_writer::tag('i', 'check_circle', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Assigned Students
echo html_writer::start_div('lnd-metric-card students');
echo html_writer::start_div('lnd-metric-content');
echo html_writer::tag('div', 'Assigned Students', ['class' => 'lnd-metric-title']);
echo html_writer::tag('div', '1,250', ['class' => 'lnd-metric-value']);
echo html_writer::end_div();
echo html_writer::start_div('lnd-metric-icon');
echo html_writer::tag('i', 'group', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

// Avg Completion
echo html_writer::start_div('lnd-metric-card completion');
echo html_writer::start_div('lnd-metric-content');
echo html_writer::tag('div', 'Avg completion', ['class' => 'lnd-metric-title']);
echo html_writer::tag('div', '50%', ['class' => 'lnd-metric-value']);
echo html_writer::end_div();
echo html_writer::start_div('lnd-metric-icon');
echo html_writer::tag('i', 'trending_up', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End metrics grid

// Assessment Header with Tabs and View Analysis Button
echo html_writer::start_div('lnd-assessment-header');

// Assessment Tabs
echo html_writer::start_div('lnd-assessment-tabs');
echo html_writer::tag('button', 'Pending', ['class' => 'lnd-tab-btn active', 'onclick' => 'switchAssessmentTab("pending")']);
echo html_writer::tag('button', 'Approved', ['class' => 'lnd-tab-btn', 'onclick' => 'switchAssessmentTab("approved")']);
echo html_writer::tag('button', 'Completed Assessment', ['class' => 'lnd-tab-btn', 'onclick' => 'switchAssessmentTab("completed")']);
echo html_writer::end_div();

// View Analysis Button
echo html_writer::start_tag('button', ['class' => 'lnd-view-analysis-btn', 'onclick' => 'viewAnalysis()']);
echo html_writer::tag('i', 'assessment', ['class' => 'material-icons', 'style' => 'font-size: 16px; margin-right: 6px;']);
echo html_writer::span('View Analysis');
echo html_writer::end_tag('button');

echo html_writer::end_div(); // End assessment header

// Assessment List Container
echo html_writer::start_div('lnd-assessment-list');

// Define dynamic assessment data for LND dashboard
$lndAssessments = [
    ['id' => 'java-basics-1', 'title' => 'Java Basics Test', 'creator' => 'Anita Sharma', 'questions' => 1, 'time' => 45, 'students' => 156],
    ['id' => 'assessment-1', 'title' => 'Advanced Java Programming', 'creator' => 'Anita Sharma', 'questions' => 5, 'time' => 90, 'students' => 89],
    ['id' => 'assessment-2', 'title' => 'Database Fundamentals', 'creator' => 'Anita Sharma', 'questions' => 3, 'time' => 60, 'students' => 234]
];

// Generate pending assessment cards dynamically
foreach ($lndAssessments as $index => $assessment) {
    $cardId = ($index === 0) ? 'pending-assessments' : '';
    
    echo html_writer::start_div('lnd-assessment-card', $cardId ? ['id' => $cardId] : []);
    echo html_writer::start_div('lnd-assessment-row');

    echo html_writer::start_div('lnd-assessment-info');
    echo html_writer::start_div('lnd-assessment-title');
    echo html_writer::span($assessment['title'], '');
    echo html_writer::span('Pending', 'lnd-status-badge pending');
    echo html_writer::end_div();

    echo html_writer::start_div('lnd-assessment-meta');
    echo html_writer::start_div('lnd-assessment-meta-item');
    echo html_writer::start_div('lnd-assessment-meta-icon creator');
    echo html_writer::tag('i', 'person', ['class' => 'material-icons']);
    echo html_writer::end_div();
    echo html_writer::span('Created By: ' . $assessment['creator'], '');
    echo html_writer::end_div();

    echo html_writer::start_div('lnd-assessment-meta-item');
    echo html_writer::start_div('lnd-assessment-meta-icon questions');
    echo html_writer::tag('i', 'help_outline', ['class' => 'material-icons']);
    echo html_writer::end_div();
    echo html_writer::span($assessment['questions'] . ' Questions', '');
    echo html_writer::end_div();

    echo html_writer::start_div('lnd-assessment-meta-item');
    echo html_writer::start_div('lnd-assessment-meta-icon time');
    echo html_writer::tag('i', 'schedule', ['class' => 'material-icons']);
    echo html_writer::end_div();
    echo html_writer::span($assessment['time'] . ' mins', '');
    echo html_writer::end_div();

    echo html_writer::start_div('lnd-assessment-meta-item');
    echo html_writer::start_div('lnd-assessment-meta-icon students');
    echo html_writer::tag('i', 'group', ['class' => 'material-icons']);
    echo html_writer::end_div();
    echo html_writer::span($assessment['students'] . ' Students', '');
    echo html_writer::end_div();
    echo html_writer::end_div(); // End meta
    echo html_writer::end_div(); // End info

    echo html_writer::start_div('lnd-assessment-actions');
    
    echo html_writer::start_tag('button', ['class' => 'lnd-action-btn review', 'onclick' => 'reviewAssessment("' . $assessment['id'] . '")']);
    echo html_writer::tag('i', 'visibility', ['class' => 'material-icons', 'style' => 'font-size: 14px; margin-right: 4px;']);
    echo html_writer::span('Review');
    echo html_writer::end_tag('button');
    
    echo html_writer::start_tag('button', ['class' => 'lnd-action-btn approve', 'onclick' => 'approveAssessment("' . $assessment['id'] . '")']);
    echo html_writer::tag('i', 'check', ['class' => 'material-icons', 'style' => 'font-size: 14px; margin-right: 4px;']);
    echo html_writer::span('Approve');
    echo html_writer::end_tag('button');
    
    echo html_writer::start_tag('button', ['class' => 'lnd-action-btn reject', 'onclick' => 'rejectAssessment("' . $assessment['id'] . '")']);
    echo html_writer::tag('i', 'close', ['class' => 'material-icons', 'style' => 'font-size: 14px; margin-right: 4px;']);
    echo html_writer::span('Reject');
    echo html_writer::end_tag('button');
    
    echo html_writer::end_div();

    echo html_writer::end_div(); // End row
    echo html_writer::end_div(); // End card
}

// Active Assessment (Java Basics Test)
echo html_writer::start_div('lnd-assessment-card', ['id' => 'active-assessments']);
echo html_writer::start_div('lnd-assessment-row');

echo html_writer::start_div('lnd-assessment-info');
echo html_writer::start_div('lnd-assessment-title');
echo html_writer::span('Java Basics Test', '');
echo html_writer::span('Active', 'lnd-status-badge active');
echo html_writer::end_div();

echo html_writer::start_div('lnd-assessment-meta');
echo html_writer::start_div('lnd-assessment-meta-item');
echo html_writer::start_div('lnd-assessment-meta-icon students');
echo html_writer::tag('i', 'group', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::span('156 Students Assigned', '');
echo html_writer::end_div();

echo html_writer::start_div('lnd-assessment-meta-item');
echo html_writer::start_div('lnd-assessment-meta-icon completed');
echo html_writer::tag('i', 'check_circle', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::span('89 Completed', '');
echo html_writer::end_div();

echo html_writer::start_div('lnd-assessment-meta-item');
echo html_writer::start_div('lnd-assessment-meta-icon score');
echo html_writer::tag('i', 'bar_chart', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::span('Avg Score: 65%', '');
echo html_writer::end_div();

echo html_writer::start_div('lnd-assessment-meta-item');
echo html_writer::start_div('lnd-assessment-meta-icon below');
echo html_writer::tag('i', 'trending_down', ['class' => 'material-icons']);
echo html_writer::end_div();
echo html_writer::span('Below 60%: 12', '');
echo html_writer::end_div();
echo html_writer::end_div(); // End meta
echo html_writer::end_div(); // End info

echo html_writer::start_div('lnd-assessment-actions');

echo html_writer::start_tag('button', ['class' => 'lnd-action-btn add-students', 'onclick' => 'addStudents("java-basics-active")']);
echo html_writer::tag('i', 'add', ['class' => 'material-icons', 'style' => 'font-size: 14px; margin-right: 4px;']);
echo html_writer::span('Add Students');
echo html_writer::end_tag('button');

echo html_writer::start_tag('button', ['class' => 'lnd-action-btn view-report', 'onclick' => 'viewReport("java-basics-active")']);
echo html_writer::tag('i', 'assessment', ['class' => 'material-icons', 'style' => 'font-size: 14px; margin-right: 4px;']);
echo html_writer::span('View Report');
echo html_writer::end_tag('button');

echo html_writer::end_div();

echo html_writer::end_div(); // End row
echo html_writer::end_div(); // End card

echo html_writer::end_div(); // End assessment list

echo html_writer::end_div(); // End main container

// JavaScript for L&D Dashboard Functionality
echo html_writer::start_tag('script');
echo '
// Base URL for navigation
var baseURL = "' . $CFG->wwwroot . '";

// Assessment Tab Switching
function switchAssessmentTab(tabName) {
    // Remove active class from all tabs
    document.querySelectorAll(".lnd-tab-btn").forEach(function(btn) {
        btn.classList.remove("active");
    });
    
    // Add active class to clicked tab
    event.target.classList.add("active");
    
    // Hide all assessment cards
    document.querySelectorAll(".lnd-assessment-card").forEach(function(card) {
        card.style.display = "none";
    });
    
    // Show relevant cards based on tab
    if (tabName === "pending") {
        document.getElementById("pending-assessments").style.display = "block";
        document.querySelector(".lnd-assessment-card:nth-child(2)").style.display = "block";
    } else if (tabName === "approved") {
        // Show approved assessments (none in current demo)
        alert("Approved assessments would be displayed here");
    } else if (tabName === "completed") {
        // Show completed assessments (none in current demo) 
        alert("Completed assessments would be displayed here");
    }
}

// View Analysis Function
function viewAnalysis() {
    alert("📊 Loading Assessment Analytics Dashboard...\\n\\n• Overall completion rates\\n• Performance trends\\n• Student engagement metrics\\n• Difficulty analysis\\n• Time-based insights");
    // In real implementation: window.location.href = "/local/orgadmin/analytics.php";
}

// Assessment Action Functions
function reviewAssessment(assessmentId) {
    window.location.href = baseURL + "/local/orgadmin/review_assessment.php?id=" + assessmentId;
}

function approveAssessment(assessmentId) {
    if (confirm("✓ Approve this assessment?\\n\\nThis will make it available to assigned students.")) {
        alert("Assessment " + assessmentId + " has been approved!\\n\\n• Students will be notified\\n• Assessment is now active\\n• Performance tracking enabled");
        // In real implementation: AJAX call to approve assessment
    }
}

function rejectAssessment(assessmentId) {
    const reason = prompt("✗ Reject Assessment\\n\\nPlease provide a reason for rejection:");
    if (reason) {
        alert("Assessment " + assessmentId + " has been rejected.\\n\\nReason: " + reason + "\\n\\n• Creator will be notified\\n• Assessment returned for revision");
        // In real implementation: AJAX call to reject assessment with reason
    }
}

function addStudents(assessmentId) {
    alert("👥 Add Students to Assessment: " + assessmentId + "\\n\\n• Bulk enrollment options\\n• Individual student selection\\n• Group-based assignment\\n• Email notifications");
    // In real implementation: window.location.href = "/local/orgadmin/enroll_students.php?assessment=" + assessmentId;
}

function viewReport(assessmentId) {
    window.location.href = baseURL + "/local/orgadmin/assessment_report.php?id=" + assessmentId;
}

// Initialize dashboard
document.addEventListener("DOMContentLoaded", function() {
    console.log("L&D Dashboard (Screenshot Match) initialized successfully");
    
    // Set default tab to pending
    switchAssessmentTab("pending");
});
';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();
?>