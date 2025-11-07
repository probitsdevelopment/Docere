<?php
// local/orgadmin/review_assessment.php - Assessment Review Page
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/review_assessment.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title('Assessment Review');
$PAGE->set_heading('');

// Verify user should access L&D dashboard
if (!orgadmin_role_detector::should_show_lnd_dashboard()) {
    // Redirect non-L&D users to appropriate dashboard
    $dashboardurl = orgadmin_role_detector::get_dashboard_url();
    redirect($dashboardurl);
}

// Get assessment ID from URL parameter
$assessmentid = optional_param('id', 0, PARAM_INT);

// Get real assessment data from database
function get_assessment_for_review($id) {
    global $DB;

    try {
        // Get assessment with creator info
        $assessment = $DB->get_record_sql("
            SELECT a.*, u.firstname, u.lastname
            FROM {orgadmin_assessments} a
            JOIN {user} u ON u.id = a.userid
            WHERE a.id = ?
        ", [$id]);

        if (!$assessment) {
            // Return fallback data if assessment not found
            return [
                'title' => 'Assessment Not Found',
                'creator' => 'Unknown',
                'questions' => 1,
                'time' => 45,
                'marks' => 100,
                'description' => 'Assessment could not be loaded from database.'
            ];
        }

        return [
            'title' => $assessment->title,
            'creator' => $assessment->firstname . ' ' . $assessment->lastname,
            'questions' => 1, // Default for now
            'time' => $assessment->duration,
            'marks' => $assessment->total_marks,
            'description' => $assessment->instructions ?: 'No description provided.'
        ];

    } catch (Exception $e) {
        // Return fallback data on error
        return [
            'title' => 'Java Basics Test',
            'creator' => 'System',
            'questions' => 1,
            'time' => 45,
            'marks' => 100,
            'description' => 'Assessment data could not be loaded.'
        ];
    }
}

$currentAssessment = get_assessment_for_review($assessmentid);

// Debug output
echo "<!-- DEBUG: Assessment ID received: " . $assessmentid . " -->";
echo "<!-- DEBUG: Assessment title: " . $currentAssessment['title'] . " -->";
echo "<!-- DEBUG: Creator: " . $currentAssessment['creator'] . " -->";

echo $OUTPUT->header();

// Custom CSS for Review Assessment Page
echo html_writer::start_tag('style');
echo '
/* Reset and base styles for full width */
html, body {
    background-color: #ffffff !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden !important;
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

/* Review Page Container */
.review-container {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 20px 30px;
    background: #ffffff;
    overflow-x: hidden;
    overflow-y: auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* Assessment Header */
.assessment-header {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.assessment-title-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.assessment-title-left {
    flex: 1;
}

.assessment-title {
    font-size: 1.5em;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 15px 0;
}

.assessment-meta-row {
    display: flex;
    gap: 40px;
    align-items: center;
    margin-bottom: 15px;
}

.assessment-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #7f8c8d;
    font-size: 0.95em;
}

.meta-icon {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85em;
    color: white;
}

.meta-icon.creator {
    background: #3498db;
}

.meta-icon.questions {
    background: #f39c12;
}

.meta-icon.time {
    background: #9b59b6;
}

.meta-icon.marks {
    background: #e74c3c;
}

.assessment-description {
    color: #5a6c7d;
    font-size: 0.95em;
    line-height: 1.5;
    margin: 0;
}

.assessment-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.action-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.95em;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    min-width: 100px;
    justify-content: center;
}

.action-btn.preview {
    background: #1CB0F6;
    color: white;
}

.action-btn.preview:hover {
    background: #0EA5E9;
    transform: translateY(-1px);
}

.action-btn.approved {
    background: #58CC02;
    color: white;
}

.action-btn.approved:hover {
    background: #4CAF02;
    transform: translateY(-1px);
}

/* Main Content Area */
.main-content {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
    margin-bottom: 0px;
}

/* Assessment Details Section */
.details-section {
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    max-height: 250px;
}

.section-title {
    font-size: 1.2em;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 15px 0;
}

.date-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.date-group {
    display: flex;
    flex-direction: column;
}

.date-label {
    font-size: 0.9em;
    font-weight: 600;
    color: #5a6c7d;
    margin-bottom: 8px;
}

.date-input {
    padding: 10px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.9em;
    background: #f8f9fa;
    color: #5a6c7d;
    position: relative;
}

.date-input:focus {
    border-color: #3498db;
    outline: none;
    background: white;
}

.settings-section {
    margin-top: 15px;
}

.settings-title {
    font-size: 1em;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 12px 0;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #3498db;
}

.checkbox-item label {
    font-size: 0.95em;
    color: #5a6c7d;
    cursor: pointer;
}

/* Add Students Section */
.students-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    display: flex;
    flex-direction: column;
    height: 600px;
    max-height: 600px;
    overflow: hidden;
}

.student-tabs {
    display: flex;
    gap: 0;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 4px;
    margin-bottom: 15px;
}

.tab-btn {
    flex: 1;
    padding: 8px 16px;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85em;
    font-weight: 500;
    transition: all 0.3s ease;
    color: #7f8c8d;
}

.tab-btn.active {
    background: #1CB0F6;
    color: white;
}

.tab-btn:hover:not(.active) {
    background: #e9ecef;
    color: #2c3e50;
}

.search-section {
    margin-bottom: 15px;
}

.search-label {
    font-size: 0.9em;
    font-weight: 600;
    color: #5a6c7d;
    margin-bottom: 8px;
    display: block;
}

.search-input {
    width: 100%;
    padding: 10px 35px 10px 14px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 0.9em;
    position: relative;
    background: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%237f8c8d\' stroke-width=\'2\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'/%3E%3Cpath d=\'m21 21-4.35-4.35\'/%3E%3C/svg%3E") no-repeat right 10px center;
}

.search-input:focus {
    border-color: #3498db;
    outline: none;
}

.selected-students {
    margin-bottom: 15px;
    flex-shrink: 0;
}

.selected-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.selected-title {
    font-size: 0.9em;
    font-weight: 600;
    color: #2c3e50;
}

.selected-count {
    font-size: 0.8em;
    color: #7f8c8d;
}

.clear-all-btn {
    background: none;
    border: none;
    color: #3498db;
    font-size: 0.85em;
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
}

.clear-all-btn:hover {
    color: #2980b9;
}

.selected-list {
    max-height: 100px;
    overflow-y: auto;
    margin-bottom: 12px;
}

.student-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 6px;
    margin-bottom: 6px;
}

.student-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3498db, #2980b9);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8em;
}

.student-info {
    flex: 1;
}

.student-name {
    font-size: 0.9em;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 2px 0;
}

.student-email {
    font-size: 0.8em;
    color: #7f8c8d;
    margin: 0;
}

.remove-btn {
    background: #e74c3c;
    border: none;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
}

.remove-btn:hover {
    background: #c0392b;
}

.list-students {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}

.list-title {
    font-size: 0.9em;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 12px 0;
}

.student-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding-right: 5px;
}

/* Custom scrollbar for student list */
.student-list::-webkit-scrollbar {
    width: 6px;
}

.student-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.student-list::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.student-list::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.list-student-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin-bottom: 6px;
    transition: all 0.3s ease;
}

.list-student-card:hover {
    background: #f8f9fa;
    border-color: #3498db;
}

.add-btn {
    background: #58CC02;
    border: none;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8em;
    font-weight: 600;
}

.add-btn:hover {
    background: #4CAF02;
}

/* Assessment List Section */
.assessment-list-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    width: calc(60% - 10px);
    gap:10px;
    margin-top: -320px;
}

.list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.list-tabs {
    display: flex;
    gap: 0;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 4px;
}

.list-tab-btn {
    padding: 10px 20px;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.9em;
    font-weight: 500;
    transition: all 0.3s ease;
    color: #7f8c8d;
}

.list-tab-btn.active {
    background: #1CB0F6;
    color: white;
}

.list-tab-btn:hover:not(.active) {
    background: #e9ecef;
    color: #2c3e50;
}


/* Assessment cards in list */
.assessment-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.assessment-card {
    padding: 20px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.assessment-card:hover {
    background: #f8f9fa;
    border-color: #3498db;
}

.assessment-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.assessment-info {
    flex: 1;
}

.assessment-card-title {
    font-size: 1.1em;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.7em;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending {
    background: #ffeaa7;
    color: #d63031;
}

.assessment-card-meta {
    display: flex;
    gap: 20px;
    color: #7f8c8d;
    font-size: 0.85em;
}

.card-actions {
    display: flex;
    gap: 8px;
}

.card-action-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85em;
    font-weight: 500;
    transition: all 0.3s ease;
}

.card-action-btn.view {
    background: #1CB0F6;
    color: white;
}

.card-action-btn.approve {
    background: #58CC02;
    color: white;
}

.card-action-btn:hover {
    transform: translateY(-1px);
}
';
echo html_writer::end_tag('style');

// Main Review Container
echo html_writer::start_div('review-container');

// Assessment Header
echo html_writer::start_div('assessment-header');
echo html_writer::start_div('assessment-title-section');

echo html_writer::start_div('assessment-title-left');
echo html_writer::tag('h1', $currentAssessment['title'], ['class' => 'assessment-title']);

echo html_writer::start_div('assessment-meta-row');
echo html_writer::start_div('assessment-meta-item');
echo html_writer::div('👤', 'meta-icon creator');
echo html_writer::span('Created By: ' . $currentAssessment['creator'], '');
echo html_writer::end_div();

echo html_writer::start_div('assessment-meta-item');
echo html_writer::div('❓', 'meta-icon questions');
echo html_writer::span($currentAssessment['questions'] . ' Questions', '');
echo html_writer::end_div();

echo html_writer::start_div('assessment-meta-item');
echo html_writer::div('⏱️', 'meta-icon time');
echo html_writer::span($currentAssessment['time'] . ' mins', '');
echo html_writer::end_div();

echo html_writer::start_div('assessment-meta-item');
echo html_writer::div('📊', 'meta-icon marks');
echo html_writer::span('Max marks:' . $currentAssessment['marks'], '');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::tag('p', $currentAssessment['description'], ['class' => 'assessment-description']);
echo html_writer::end_div();

echo html_writer::start_div('assessment-actions');
echo html_writer::tag('button', '👁️ Preview Assessment', ['class' => 'action-btn preview', 'onclick' => 'previewAssessment()']);
echo html_writer::tag('button', '✓ Approved & Assigned', ['class' => 'action-btn approved', 'onclick' => 'approveAndAssign()']);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

// Main Content Area
echo html_writer::start_div('main-content');

// Assessment Details Section
echo html_writer::start_div('details-section');
echo html_writer::tag('h2', 'Assessment Details', ['class' => 'section-title']);

echo html_writer::start_div('date-inputs');
echo html_writer::start_div('date-group');
echo html_writer::tag('label', 'Start Date', ['class' => 'date-label']);
echo html_writer::empty_tag('input', ['type' => 'date', 'class' => 'date-input', 'value' => date('Y-m-d')]);
echo html_writer::end_div();

echo html_writer::start_div('date-group');
echo html_writer::tag('label', 'Due Date', ['class' => 'date-label']);
echo html_writer::empty_tag('input', ['type' => 'date', 'class' => 'date-input', 'value' => date('Y-m-d', strtotime('+7 days'))]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('settings-section');
echo html_writer::tag('h3', 'Assessment Settings', ['class' => 'settings-title']);

echo html_writer::start_div('checkbox-item');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'multiple-attempts', 'name' => 'allow_multiple_attempts', 'value' => '1', 'checked' => true]);
echo html_writer::tag('label', 'Allow multiple attempts', ['for' => 'multiple-attempts']);
echo html_writer::end_div();

echo html_writer::start_div('checkbox-item');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'email-notifications', 'name' => 'send_email_notifications', 'value' => '1']);
echo html_writer::tag('label', 'Send email notifications to students', ['for' => 'email-notifications']);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

// Add Students Section
echo html_writer::start_div('students-section');
echo html_writer::tag('h2', 'Add Students', ['class' => 'section-title']);

echo html_writer::start_div('student-tabs');
echo html_writer::tag('button', 'Individual', ['class' => 'tab-btn active', 'onclick' => 'switchStudentTab("individual")']);
echo html_writer::tag('button', 'Group', ['class' => 'tab-btn', 'onclick' => 'switchStudentTab("group")']);
echo html_writer::end_div();

echo html_writer::start_div('search-section');
echo html_writer::tag('label', 'Search Students', ['class' => 'search-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 
    'class' => 'search-input',
    'placeholder' => 'Search by name, ID, Email...',
    'oninput' => 'searchStudents(this.value)'
]);
echo html_writer::end_div();

// Get real students based on L&D role (Site vs Organization) - DEFINE FIRST
function get_real_students_for_lnd_early() {
    global $DB, $USER;

    // Check if this is Site L&D or Organization L&D
    $is_site_lnd = $DB->record_exists_sql("
        SELECT 1
        FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ra.userid = ? AND r.shortname = 'coursecreator' AND ctx.contextlevel = 10
    ", [$USER->id]);

    if ($is_site_lnd) {
        // Site L&D - get students with site-level roles only
        $students_sql = "
            SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            JOIN {context} ctx ON ctx.id = ra.contextid
            WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
            AND r.shortname = 'student'
            AND ctx.contextlevel = 10
            ORDER BY u.firstname, u.lastname
            LIMIT 20
        ";
        $student_records = $DB->get_records_sql($students_sql);
    } else {
        // Organization L&D - get students from their organization only
        $categories = $DB->get_records_sql("
            SELECT DISTINCT cc.id, cc.name
            FROM {role_assignments} ra
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {role} r ON r.id = ra.roleid
            JOIN {course_categories} cc ON cc.id = ctx.instanceid
            WHERE ra.userid = ? AND ctx.contextlevel = 40 AND r.shortname = 'coursecreator'
        ", [$USER->id]);

        if (!empty($categories)) {
            $category = reset($categories);
            $students_sql = "
                SELECT DISTINCT u.id, u.firstname, u.lastname, u.email
                FROM {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role} r ON r.id = ra.roleid
                JOIN {context} ctx ON ctx.id = ra.contextid
                WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
                AND r.shortname = 'student'
                AND ctx.contextlevel = 40 AND ctx.instanceid = ?
                ORDER BY u.firstname, u.lastname
                LIMIT 20
            ";
            $student_records = $DB->get_records_sql($students_sql, [$category->id]);
        } else {
            $student_records = [];
        }
    }

    // Convert to array format expected by the frontend
    $students = [];
    foreach ($student_records as $student) {
        $full_name = trim($student->firstname . ' ' . $student->lastname);
        $initials = strtoupper(substr($student->firstname, 0, 1) . substr($student->lastname, 0, 1));

        $students[] = [
            'name' => $full_name,
            'email' => $student->email,
            'initials' => $initials,
            'id' => $student->id
        ];
    }

    return $students;
}

// Get real students EARLY
$students_early = get_real_students_for_lnd_early();

// If no students found, show a message
if (empty($students_early)) {
    $students_early = [
        ['name' => 'No Students Found', 'email' => 'No students in your scope', 'initials' => 'NS', 'id' => 0]
    ];
}

echo html_writer::start_div('selected-students');
echo html_writer::start_div('selected-header');
echo html_writer::tag('span', 'Selected Students', ['class' => 'selected-title']);

$selected_count = min(2, count(array_filter($students_early, function($s) { return $s['id'] != 0; })));
echo html_writer::tag('span', '(' . $selected_count . ')', ['class' => 'selected-count', 'id' => 'selected-count']);

echo html_writer::tag('button', 'Clear All', ['class' => 'clear-all-btn', 'onclick' => 'clearAllStudents()']);
echo html_writer::end_div();

echo html_writer::start_div('selected-list', ['id' => 'selected-list']);

// Show first 2 real students as "selected" for demo
$selected_students = array_slice($students_early, 0, 2);
foreach ($selected_students as $student) {
    if ($student['id'] == 0) break; // Skip "No Students Found" entry

    echo html_writer::start_div('student-card');
    echo html_writer::div($student['initials'], 'student-avatar');
    echo html_writer::start_div('student-info');
    echo html_writer::tag('div', $student['name'], ['class' => 'student-name']);
    echo html_writer::tag('div', $student['email'], ['class' => 'student-email']);
    echo html_writer::end_div();
    echo html_writer::tag('button', '✕', ['class' => 'remove-btn', 'onclick' => 'removeStudent(this)']);
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('list-students');
echo html_writer::tag('h3', 'List Students', ['class' => 'list-title']);
echo html_writer::start_div('student-list', ['id' => 'student-list']);

// Use the same students data from earlier
$students = $students_early;

foreach ($students as $student) {
    echo html_writer::start_div('list-student-card');
    echo html_writer::div($student['initials'], 'student-avatar');
    echo html_writer::start_div('student-info');
    echo html_writer::tag('div', $student['name'], ['class' => 'student-name']);
    echo html_writer::tag('div', $student['email'], ['class' => 'student-email']);
    echo html_writer::end_div();
    echo html_writer::tag('button', '+', ['class' => 'add-btn', 'onclick' => 'addStudent(this)']);
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::end_div(); // End main content

// Assessment List Section
echo html_writer::start_div('assessment-list-section');
echo html_writer::start_div('list-header');

echo html_writer::start_div('list-tabs');
echo html_writer::tag('button', 'Pending', ['class' => 'list-tab-btn active', 'onclick' => 'switchListTab("pending")']);
echo html_writer::tag('button', 'Approved', ['class' => 'list-tab-btn', 'onclick' => 'switchListTab("approved")']);
echo html_writer::tag('button', 'Completed Assessment', ['class' => 'list-tab-btn', 'onclick' => 'switchListTab("completed")']);
echo html_writer::end_div();

echo html_writer::end_div();

echo html_writer::start_div('assessment-list', ['id' => 'assessment-list']);

// Get real assessment list data from database
function get_real_assessment_list() {
    global $DB;

    try {
        // Get all pending assessments from database
        $assessments = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);
        $listAssessments = [];

        foreach ($assessments as $assessment) {
            // Get user info
            $user = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname');
            $creator_name = $user ? $user->firstname . ' ' . $user->lastname : 'Unknown User';

            $listAssessments[] = [
                'id' => $assessment->id,
                'title' => $assessment->title,
                'creator' => $creator_name,
                'questions' => 1, // Default for now since we don't store question count
                'time' => $assessment->duration ?: 45,
                'students' => 0 // Default for now since we don't track this yet
            ];
        }

        return $listAssessments;

    } catch (Exception $e) {
        error_log('Error getting assessment list: ' . $e->getMessage());
        return []; // Return empty array instead of dummy data
    }
}

$listAssessments = get_real_assessment_list();

// Assessment cards
foreach ($listAssessments as $index => $assessment) {
    echo html_writer::start_div('assessment-card');
    echo html_writer::start_div('assessment-row');
    
    echo html_writer::start_div('assessment-info');
    echo html_writer::start_div('assessment-card-title');
    echo html_writer::span($assessment['title'], '');
    echo html_writer::span('Pending', 'status-badge pending');
    echo html_writer::end_div();
    
    echo html_writer::start_div('assessment-card-meta');
    echo html_writer::span('👤 Created By: ' . $assessment['creator'], '');
    echo html_writer::span('❓ ' . $assessment['questions'] . ' Questions', '');
    echo html_writer::span('⏱️ ' . $assessment['time'] . ' mins', '');
    echo html_writer::span('👥 ' . $assessment['students'] . ' Students', '');
    echo html_writer::end_div();
    echo html_writer::end_div();
    
    echo html_writer::start_div('card-actions');
    echo html_writer::tag('button', '👁️ View', ['class' => 'card-action-btn view', 'onclick' => 'viewAssessment("' . $assessment['id'] . '")']);
    echo html_writer::tag('button', '✓ Approve', ['class' => 'card-action-btn approve', 'onclick' => 'approveAssessment("' . $assessment['id'] . '")']);
    echo html_writer::end_div();
    
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // End review container

// JavaScript for functionality
echo html_writer::start_tag('script');
echo '
// Base URL for navigation
var baseURL = "' . $CFG->wwwroot . '";

// Tab switching for student selection
function switchStudentTab(tab) {
    document.querySelectorAll(".tab-btn").forEach(btn => {
        btn.classList.remove("active");
    });
    event.target.classList.add("active");
    
    if (tab === "group") {
        alert("Group selection feature would be implemented here");
    }
}

// Tab switching for assessment list
function switchListTab(tab) {
    document.querySelectorAll(".list-tab-btn").forEach(btn => {
        btn.classList.remove("active");
    });
    event.target.classList.add("active");
    
    if (tab === "approved") {
        alert("Approved assessments would be shown here");
    } else if (tab === "completed") {
        alert("Completed assessments would be shown here");
    }
}

// Student management functions
function addStudent(button) {
    const card = button.closest(".list-student-card");
    const name = card.querySelector(".student-name").textContent;
    const email = card.querySelector(".student-email").textContent;
    const initials = card.querySelector(".student-avatar").textContent;
    
    // Create new selected student card
    const selectedList = document.getElementById("selected-list");
    const newCard = document.createElement("div");
    newCard.className = "student-card";
    newCard.innerHTML = `
        <div class="student-avatar">${initials}</div>
        <div class="student-info">
            <div class="student-name">${name}</div>
            <div class="student-email">${email}</div>
        </div>
        <button class="remove-btn" onclick="removeStudent(this)">✕</button>
    `;
    
    selectedList.appendChild(newCard);
    
    // Update count
    updateSelectedCount();
    
    // Remove from available list
    card.style.display = "none";
}

function removeStudent(button) {
    const card = button.closest(".student-card");
    const name = card.querySelector(".student-name").textContent;
    
    // Remove from selected list
    card.remove();
    
    // Show back in available list
    const availableCards = document.querySelectorAll(".list-student-card");
    availableCards.forEach(availableCard => {
        const availableName = availableCard.querySelector(".student-name").textContent;
        if (availableName === name) {
            availableCard.style.display = "flex";
        }
    });
    
    // Update count
    updateSelectedCount();
}

function clearAllStudents() {
    const selectedList = document.getElementById("selected-list");
    const selectedCards = selectedList.querySelectorAll(".student-card");
    
    selectedCards.forEach(card => {
        const name = card.querySelector(".student-name").textContent;
        
        // Show back in available list
        const availableCards = document.querySelectorAll(".list-student-card");
        availableCards.forEach(availableCard => {
            const availableName = availableCard.querySelector(".student-name").textContent;
            if (availableName === name) {
                availableCard.style.display = "flex";
            }
        });
    });
    
    selectedList.innerHTML = "";
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll("#selected-list .student-card").length;
    document.getElementById("selected-count").textContent = `(${count})`;
}

function searchStudents(query) {
    const cards = document.querySelectorAll(".list-student-card");
    cards.forEach(card => {
        const name = card.querySelector(".student-name").textContent.toLowerCase();
        const email = card.querySelector(".student-email").textContent.toLowerCase();
        
        if (name.includes(query.toLowerCase()) || email.includes(query.toLowerCase())) {
            card.style.display = "flex";
        } else {
            card.style.display = "none";
        }
    });
}

// Assessment actions
function previewAssessment() {
    alert("📝 Opening Assessment Preview...\\n\\n• Question review\\n• Settings verification\\n• Student view simulation");
}

function approveAndAssign() {
    const selectedCount = document.querySelectorAll("#selected-list .student-card").length;
    if (selectedCount === 0) {
        alert("Please select at least one student to assign this assessment.");
        return;
    }

    // Get assessment settings
    const allowMultipleAttempts = document.getElementById('multiple-attempts').checked ? 1 : 0;
    const sendEmailNotifications = document.getElementById('email-notifications').checked ? 1 : 0;

    // Get assessment ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const assessmentId = urlParams.get('id');

    if (!assessmentId) {
        alert('Error: Assessment ID not found');
        return;
    }

    if (confirm(`✓ Approve and assign this assessment to ${selectedCount} selected students?\\n\\nSettings:\\n• Multiple attempts: ${allowMultipleAttempts ? 'Enabled' : 'Disabled'}\\n• Email notifications: ${sendEmailNotifications ? 'Enabled' : 'Disabled'}`)) {

        // Create form data
        const formData = new FormData();
        formData.append('action', 'approve_and_assign');
        formData.append('assessment_id', assessmentId);
        formData.append('allow_multiple_attempts', allowMultipleAttempts);
        formData.append('send_email_notifications', sendEmailNotifications);
        formData.append('sesskey', M.cfg.sesskey);

        // Collect selected students
        const selectedStudents = Array.from(document.querySelectorAll("#selected-list .student-card")).map(card => {
            return {
                id: card.dataset.studentId,
                name: card.querySelector('.student-name').textContent,
                email: card.querySelector('.student-email').textContent
            };
        });
        formData.append('selected_students', JSON.stringify(selectedStudents));

        // Submit to assessment handler
        fetch(M.cfg.wwwroot + '/local/orgadmin/assessment_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Assessment approved and assigned successfully!\\n\\n• ${selectedCount} students assigned\\n• Settings applied\\n• ${sendEmailNotifications ? 'Email notifications sent' : 'No email notifications sent'}`);

                // Redirect back to LND dashboard
                window.location.href = M.cfg.wwwroot + '/local/orgadmin/lnd_dashboard.php';
            } else {
                alert('Error: ' + (data.error || 'Unknown error occurred'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting assessment approval');
        });
    }
}

function viewAssessment(id) {
    // Redirect to review assessment page with the assessment ID
    window.location.href = baseURL + "/local/orgadmin/review_assessment.php?id=" + id;
}

function approveAssessment(id) {
    if (confirm("✓ Approve this assessment?")) {
        alert("Assessment approved successfully!");
    }
}


// Initialize page
document.addEventListener("DOMContentLoaded", function() {
    console.log("Assessment Review page initialized");
    updateSelectedCount();
});
';
echo html_writer::end_tag('script');

echo $OUTPUT->footer();
?>