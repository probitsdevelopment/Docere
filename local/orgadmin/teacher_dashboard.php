<?php
// local/orgadmin/teacher_dashboard.php - Teacher/Trainer Dashboard

require_once('../../config.php');
require_once('./role_detector.php');

// Require login
require_login();

// Check if user should see teacher dashboard
if (!orgadmin_role_detector::should_show_teacher_dashboard()) {
    redirect(new moodle_url('/my/index.php'));
}

// Get parameters for filtering and mode
$filter = optional_param('filter', 'all', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$mode = optional_param('mode', 'view', PARAM_TEXT); // 'view', 'create', or 'edit'
$edit_id = optional_param('edit_id', 0, PARAM_INT); // Assessment ID to edit
$perpage = 10;

// Set up page
$PAGE->set_url('/local/orgadmin/teacher_dashboard.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Teacher Dashboard');
$PAGE->set_heading('');

// Get teacher's assessments (mock data for now - replace with real Moodle data)
function get_teacher_assessments($filter = 'all', $page = 0, $perpage = 10) {
    // Mock data - replace with real Moodle quiz/assignment data
    $assessments = [
        [
            'id' => 1,
            'title' => 'Java Basics Test',
            'questions' => 1,
            'duration' => 45,
            'students' => 156,
            'status' => 'published'
        ],
        [
            'id' => 2,
            'title' => 'Java Advanced Test', 
            'questions' => 1,
            'duration' => 45,
            'students' => 156,
            'status' => 'draft'
        ],
        [
            'id' => 3,
            'title' => 'Python Basics Test',
            'questions' => 1,
            'duration' => 45,
            'students' => 156,
            'status' => 'published'
        ],
        [
            'id' => 4,
            'title' => 'React Test',
            'questions' => 1,
            'duration' => 45,
            'students' => 156,
            'status' => 'published'
        ]
    ];
    
    // Filter assessments
    if ($filter !== 'all') {
        $assessments = array_filter($assessments, function($assessment) use ($filter) {
            return $assessment['status'] === $filter;
        });
    }
    
    $total = count($assessments);
    $assessments = array_slice($assessments, $page * $perpage, $perpage);
    
    return [
        'assessments' => $assessments,
        'total' => $total,
        'current_page' => $page,
        'per_page' => $perpage,
        'total_pages' => ceil($total / $perpage)
    ];
}

function get_teacher_statistics() {
    return [
        'total_assessments' => 24,
        'published' => 18,
        'drafted' => 6,
        'total_students' => 1253
    ];
}

function get_assessment_by_id($id) {
    // Mock function to get assessment data by ID - replace with real Moodle data
    $assessments = [
        1 => [
            'id' => 1,
            'title' => 'Java Basics Test',
            'duration' => 45,
            'total_marks' => 100,
            'pass_percentage' => 70,
            'language' => 'en',
            'instructions' => 'This is a comprehensive test covering Java basics including variables, data types, control structures, and object-oriented programming concepts.',
            'questions' => 1,
            'students' => 156,
            'status' => 'published'
        ],
        2 => [
            'id' => 2,
            'title' => 'Java Advanced Test',
            'duration' => 60,
            'total_marks' => 150,
            'pass_percentage' => 75,
            'language' => 'en',
            'instructions' => 'Advanced Java concepts including collections, threads, and design patterns.',
            'questions' => 1,
            'students' => 156,
            'status' => 'draft'
        ],
        3 => [
            'id' => 3,
            'title' => 'Python Basics Test',
            'duration' => 40,
            'total_marks' => 80,
            'pass_percentage' => 65,
            'language' => 'en',
            'instructions' => 'Basic Python programming concepts and syntax.',
            'questions' => 1,
            'students' => 156,
            'status' => 'published'
        ],
        4 => [
            'id' => 4,
            'title' => 'React Test',
            'duration' => 50,
            'total_marks' => 120,
            'pass_percentage' => 80,
            'language' => 'en',
            'instructions' => 'React components, hooks, and state management.',
            'questions' => 1,
            'students' => 156,
            'status' => 'published'
        ]
    ];

    return isset($assessments[$id]) ? $assessments[$id] : null;
}

function get_recent_activity() {
    return [
        ['text' => 'Java Test completed by 23 students', 'type' => 'completed'],
        ['text' => 'Python Test published', 'type' => 'published'],  
        ['text' => 'React Test saved as draft', 'type' => 'draft']
    ];
}

$assessments_data = get_teacher_assessments($filter, $page, $perpage);
$statistics = get_teacher_statistics();
$recent_activity = get_recent_activity();

// Get assessment data for edit mode
$edit_assessment = null;
if ($mode === 'edit' && $edit_id > 0) {
    $edit_assessment = get_assessment_by_id($edit_id);
    if (!$edit_assessment) {
        // Assessment not found, redirect to view mode
        redirect(new moodle_url('/local/orgadmin/teacher_dashboard.php'));
    }
}

// Start output
echo $OUTPUT->header();
?>

<style>
@import url('https://fonts.googleapis.com/icon?family=Material+Icons');

body {
    background: #ffffff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.teacher-container {
    max-width: 1400px;
    margin: -60px auto 0;
    padding: 20px 20px 20px;
    min-height: 100vh;
}

.teacher-welcome {
    background: #CDEBFA;
    border-radius: 16px;
    padding: 30px;
    color: #2d3748;
    margin: 0 0 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.teacher-welcome::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}

.teacher-welcome-content h1 {
    margin: 0 0 10px 0;
    font-size: 2em;
    font-weight: 700;
}

.teacher-welcome-date {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
    opacity: 0.9;
}

.teacher-welcome-date .material-icons {
    margin-right: 8px;
    font-size: 18px;
}

.teacher-welcome-subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1em;
}

.teacher-welcome-character {
    position: relative;
    z-index: 2;
}

.teacher-character {
    width: 80px;
    height: 100px;
    margin-right: 20px;
}

.teacher-speech-bubble {
    background: white;
    color: #2d3748;
    padding: 15px;
    border-radius: 20px;
    position: relative;
    max-width: 200px;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.teacher-speech-bubble::before {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 20px;
    width: 0;
    height: 0;
    border-left: 10px solid transparent;
    border-right: 10px solid transparent;
    border-top: 10px solid white;
}

.teacher-main-content {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 30px;
    margin-bottom: 30px;
}

.teacher-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    width: 100%;
}

.teacher-filter-tabs {
    display: flex;
    gap: 0;
    background: #e2e8f0;
    border-radius: 8px;
    padding: 4px;
}

.teacher-filter-tab {
    padding: 12px 24px;
    border: none;
    background: transparent;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    color: #64748b;
}

.teacher-filter-tab.active {
    background: #1CB0F6;
    color: white;
}

.teacher-add-btn {
    background: #58CC02;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: background-color 0.2s;
}

.teacher-add-btn:hover {
    background: #4DB300;
    color: white;
    text-decoration: none;
}

.teacher-add-btn .material-icons {
    margin-right: 8px;
    font-size: 18px;
}

.assessment-library {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.library-header {
    padding: 20px 30px;
    border-bottom: 1px solid #e2e8f0;
}

.library-title {
    font-size: 1.5em;
    font-weight: 700;
    margin: 0;
    color: #2d3748;
}

.assessment-item {
    padding: 20px 30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.assessment-item:last-child {
    border-bottom: none;
}

.assessment-info {
    flex: 1;
}

.assessment-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 8px 0;
    color: #2d3748;
}

.assessment-meta {
    display: flex;
    gap: 20px;
    align-items: center;
}

.assessment-meta-item {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #64748b;
    font-size: 14px;
}

.assessment-meta-item .material-icons {
    font-size: 16px;
    color: #1CB0F6;
}

.assessment-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.assessment-status {
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 600;
    margin-right: 12px;
}

.assessment-status.published {
    background: #d4edda;
    color: #155724;
}

.assessment-status.draft {
    background: #fff3cd;
    color: #856404;
}

.assessment-action-btn {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.2s;
}

.assessment-action-btn.edit {
    background: #ebf8ff;
    color: #3182ce;
}

.assessment-action-btn.edit:hover {
    background: #bee3f8;
}

.assessment-action-btn.delete {
    background: #fed7d7;
    color: #e53e3e;
}

.assessment-action-btn.delete:hover {
    background: #feb2b2;
}

.teacher-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.sidebar-header {
    padding: 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.sidebar-title {
    font-size: 1.2em;
    font-weight: 700;
    margin: 0;
    color: #2d3748;
}

.sidebar-content {
    padding: 20px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: #64748b;
    font-size: 14px;
}

.stat-value {
    font-weight: 700;
    font-size: 16px;
}

.stat-value.total {
    color: #3b82f6;
}

.stat-value.published {
    color: #10b981;
}

.stat-value.draft {
    color: #f59e0b;
}

.stat-value.students {
    color: #8b5cf6;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}

.activity-indicator.completed {
    background: #10b981;
}

.activity-indicator.published {
    background: #3b82f6;
}

.activity-indicator.draft {
    background: #f59e0b;
}

.activity-text {
    font-size: 14px;
    color: #4a5568;
    line-height: 1.4;
}

/* Create Assessment Form Styles */
.create-assessment-form {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 15px;
}

.form-section-title {
    font-size: 1.2em;
    font-weight: 700;
    margin: 0 0 10px 0;
    color: #2d3748;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 8px;
}

.form-group {
    margin-bottom: 8px;
}

.form-group.language-dropdown {
    max-width: 50%;
}

.form-group.language-dropdown label {
    color: #1CB0F6 !important;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 5px;
    font-size: 14px;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 8px 10px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
}

.form-textarea {
    resize: vertical;
    min-height: 50px;
}

.upload-label {
    display: block;
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 5px;
    font-size: 14px;
}

.upload-area {
    border: 2px dashed #cbd5e0;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #f8fafc;
}

.upload-area:hover {
    border-color: #3b82f6;
    background: #ebf8ff;
}

.upload-icon {
    margin-bottom: 12px;
}

.upload-icon .material-icons {
    font-size: 48px;
    color: #cbd5e0;
}

.upload-text {
    color: #64748b;
    font-size: 16px;
    font-weight: 500;
}

/* Actions Section Styles */
.actions-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.actions-content {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.action-btn {
    width: 100%;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    text-decoration: none;
    display: inline-block;
}

.action-btn.draft {
    background: #1CB0F6;
    color: white;
}

.action-btn.draft:hover {
    background: #1A9BD8;
    text-decoration: none;
}

.action-btn.preview {
    background: #58CC02;
    color: white;
}

.action-btn.preview:hover {
    background: #4DB300;
    text-decoration: none;
}

.action-btn.cancel {
    background: #6b7280;
    color: white;
}

.action-btn.cancel:hover {
    background: #4b5563;
    text-decoration: none;
}
</style>

<div class="teacher-container">
    <!-- Welcome Banner -->
    <div class="teacher-welcome">
        <div class="teacher-welcome-content">
            <h1>Welcome Back, <?php echo fullname($USER); ?></h1>
            <div class="teacher-welcome-date">
                <span class="material-icons">calendar_today</span>
                <?php echo date('D, j M Y'); ?>
            </div>
            <p class="teacher-welcome-subtitle">Create and manage assessments for your students</p>
        </div>
        <div class="teacher-welcome-character">
            <div style="display: flex; align-items: flex-end; gap: 10px;">
                <!-- Professional Character Illustration -->
                <div style="position: relative; width: 80px; height: 100px;">
                    <!-- Body/Suit -->
                    <div style="width: 45px; height: 55px; background: #2c3e50; border-radius: 8px 8px 0 0; position: absolute; bottom: 0; left: 17px;"></div>
                    <!-- Tie -->
                    <div style="width: 6px; height: 25px; background: #34495e; position: absolute; bottom: 20; left: 37px;"></div>
                    <!-- Shirt -->
                    <div style="width: 35px; height: 15px; background: #ecf0f1; position: absolute; bottom: 35px; left: 22px;"></div>
                    <!-- Head -->
                    <div style="width: 40px; height: 45px; background: #f4a261; border-radius: 50% 50% 45% 45%; position: absolute; top: 0; left: 20px;"></div>
                    <!-- Hair -->
                    <div style="width: 35px; height: 20px; background: #8b4513; border-radius: 50% 50% 40% 40%; position: absolute; top: 2px; left: 22px;"></div>
                    <!-- Glasses -->
                    <div style="position: absolute; top: 18px; left: 25px;">
                        <!-- Frame -->
                        <div style="width: 30px; height: 2px; background: #2c3e50; margin-bottom: 2px;"></div>
                        <!-- Left lens -->
                        <div style="width: 12px; height: 10px; border: 2px solid #2c3e50; border-radius: 50%; display: inline-block; background: rgba(255,255,255,0.1);"></div>
                        <!-- Bridge -->
                        <div style="width: 2px; height: 2px; background: #2c3e50; display: inline-block; margin: 0 2px;"></div>
                        <!-- Right lens -->
                        <div style="width: 12px; height: 10px; border: 2px solid #2c3e50; border-radius: 50%; display: inline-block; background: rgba(255,255,255,0.1);"></div>
                    </div>
                    <!-- Eyes -->
                    <div style="width: 3px; height: 3px; background: #2c3e50; border-radius: 50%; position: absolute; top: 22px; left: 30px;"></div>
                    <div style="width: 3px; height: 3px; background: #2c3e50; border-radius: 50%; position: absolute; top: 22px; left: 45px;"></div>
                    <!-- Smile -->
                    <div style="width: 8px; height: 4px; border: 2px solid #2c3e50; border-top: none; border-radius: 0 0 8px 8px; position: absolute; top: 32px; left: 36px;"></div>
                </div>
                
                <div class="teacher-speech-bubble">
                    Good to see you back, <?php echo $USER->firstname; ?>!<br>Ready to learn?
                </div>
            </div>
        </div>
    </div>

    <?php if ($mode === 'create'): ?>
        <!-- Create Mode Header -->
        <div class="teacher-filters">
            <div>
                <h2 style="margin: 0; color: #2d3748; font-size: 1.5em;">Create New Assessment</h2>
            </div>
            <button class="teacher-add-btn" onclick="saveAndPublish()">
                Save & Publish
            </button>
        </div>
    <?php elseif ($mode === 'edit'): ?>
        <!-- Edit Mode Header -->
        <div class="teacher-filters">
            <div>
                <h2 style="margin: 0; color: #2d3748; font-size: 1.5em;">Edit Assessment</h2>
            </div>
            <button class="teacher-add-btn" onclick="saveAndPublish()">
                Save & Publish
            </button>
        </div>
    <?php else: ?>
        <!-- View Mode Header -->
        <div class="teacher-filters">
            <div class="teacher-filter-tabs">
                <a href="?filter=all" class="teacher-filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All Assessment</a>
                <a href="?filter=draft" class="teacher-filter-tab <?php echo $filter === 'draft' ? 'active' : ''; ?>">Draft</a>
                <a href="?filter=published" class="teacher-filter-tab <?php echo $filter === 'published' ? 'active' : ''; ?>">Published</a>
            </div>
            <a href="#" class="teacher-add-btn" onclick="addNewAssessment()">
                <span class="material-icons">add</span>
                Add New Assessment
            </a>
        </div>
    <?php endif; ?>

    <div class="teacher-main-content">
        <?php if ($mode === 'create' || $mode === 'edit'): ?>
            <!-- Create/Edit Assessment Form (Left) -->
            <div class="create-assessment-form">
                <div class="form-section">
                    <h3 class="form-section-title">Assessment Details</h3>
                    <form id="assessmentForm">
                        <?php if ($mode === 'edit'): ?>
                            <input type="hidden" id="assessmentId" name="assessment_id" value="<?php echo $edit_assessment['id']; ?>">
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="assessmentTitle">Assessments Title</label>
                                <input type="text" id="assessmentTitle" name="title" class="form-input"
                                       placeholder="Enter assessment title"
                                       value="<?php echo $mode === 'edit' ? htmlspecialchars($edit_assessment['title']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="duration">Duration (minutes)</label>
                                <input type="number" id="duration" name="duration" class="form-input"
                                       placeholder="45"
                                       value="<?php echo $mode === 'edit' ? $edit_assessment['duration'] : ''; ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="totalMarks">Total Marks</label>
                                <input type="number" id="totalMarks" name="total_marks" class="form-input"
                                       placeholder="100"
                                       value="<?php echo $mode === 'edit' ? $edit_assessment['total_marks'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="passPercentage">Pass Percentage</label>
                                <input type="number" id="passPercentage" name="pass_percentage" class="form-input"
                                       placeholder="70" max="100"
                                       value="<?php echo $mode === 'edit' ? $edit_assessment['pass_percentage'] : ''; ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group language-dropdown">
                                <label for="language">Language</label>
                                <select id="language" name="language" class="form-select">
                                    <option value="">Select Language</option>
                                    <option value="en" <?php echo ($mode === 'edit' && $edit_assessment['language'] === 'en') ? 'selected' : ''; ?>>English</option>
                                    <option value="es" <?php echo ($mode === 'edit' && $edit_assessment['language'] === 'es') ? 'selected' : ''; ?>>Spanish</option>
                                    <option value="fr" <?php echo ($mode === 'edit' && $edit_assessment['language'] === 'fr') ? 'selected' : ''; ?>>French</option>
                                    <option value="de" <?php echo ($mode === 'edit' && $edit_assessment['language'] === 'de') ? 'selected' : ''; ?>>German</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="instructions">Instructions</label>
                            <textarea id="instructions" name="instructions" class="form-textarea" rows="4"
                                      placeholder="Enter assessment instructions..."><?php echo $mode === 'edit' ? htmlspecialchars($edit_assessment['instructions']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="upload-label">Upload the question</label>
                            <div class="upload-area" onclick="document.getElementById('questionFile').click()">
                                <div class="upload-icon">
                                    <span class="material-icons">file_upload</span>
                                </div>
                                <div class="upload-text">Upload the question</div>
                                <input type="file" id="questionFile" name="question_file" style="display: none;" accept=".pdf,.doc,.docx,.txt">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Assessment Library (Left) -->
            <div>
                <!-- Assessment Library -->
                <div class="assessment-library">
                    <div class="library-header">
                        <h2 class="library-title">Assessment Library</h2>
                    </div>
                    
                    <?php foreach ($assessments_data['assessments'] as $assessment): ?>
                    <div class="assessment-item">
                        <div class="assessment-info">
                            <h3 class="assessment-title"><?php echo htmlspecialchars($assessment['title']); ?></h3>
                            <div class="assessment-meta">
                                <div class="assessment-meta-item">
                                    <span class="material-icons">quiz</span>
                                    <?php echo $assessment['questions']; ?> Questions
                                </div>
                                <div class="assessment-meta-item">
                                    <span class="material-icons">schedule</span>
                                    <?php echo $assessment['duration']; ?> mins
                                </div>
                                <div class="assessment-meta-item">
                                    <span class="material-icons">group</span>
                                    <?php echo $assessment['students']; ?> Students
                                </div>
                            </div>
                        </div>
                        <div class="assessment-actions">
                            <span class="assessment-status <?php echo $assessment['status']; ?>">
                                <?php echo ucfirst($assessment['status']); ?>
                            </span>
                            <button class="assessment-action-btn edit" onclick="editAssessment(<?php echo $assessment['id']; ?>)" title="Edit">
                                <span class="material-icons" style="font-size: 16px;">edit</span>
                            </button>
                            <button class="assessment-action-btn delete" onclick="deleteAssessment(<?php echo $assessment['id']; ?>, '<?php echo htmlspecialchars($assessment['title']); ?>')" title="Delete">
                                <span class="material-icons" style="font-size: 16px;">delete</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sidebar (Right) -->
        <div class="teacher-sidebar">
            <?php if ($mode === 'create' || $mode === 'edit'): ?>
                <!-- Assessment Statistics -->
                <div class="sidebar-section">
                    <div class="sidebar-header">
                        <h3 class="sidebar-title">Assessment Library</h3>
                    </div>
                    <div class="sidebar-content">
                        <div class="stat-item">
                            <span class="stat-label">Total Assessments</span>
                            <span class="stat-value total"><?php echo $statistics['total_assessments']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Published</span>
                            <span class="stat-value published"><?php echo $statistics['published']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Drafted</span>
                            <span class="stat-value draft"><?php echo $statistics['drafted']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Students</span>
                            <span class="stat-value students"><?php echo number_format($statistics['total_students']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Actions Section -->
                <div class="actions-section">
                    <div class="sidebar-header">
                        <h3 class="sidebar-title">Actions</h3>
                    </div>
                    <div class="actions-content">
                        <button class="action-btn draft" onclick="saveAsDraft()">Save as Draft</button>
                        <button class="action-btn preview" onclick="previewAssessment()">Preview Assessment</button>
                        <button class="action-btn cancel" onclick="cancelAssessment()">Cancel</button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Assessment Statistics -->
                <div class="sidebar-section">
                    <div class="sidebar-header">
                        <h3 class="sidebar-title">Assessment Library</h3>
                    </div>
                    <div class="sidebar-content">
                        <div class="stat-item">
                            <span class="stat-label">Total Assessments</span>
                            <span class="stat-value total"><?php echo $statistics['total_assessments']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Published</span>
                            <span class="stat-value published"><?php echo $statistics['published']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Drafted</span>
                            <span class="stat-value draft"><?php echo $statistics['drafted']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Total Students</span>
                            <span class="stat-value students"><?php echo number_format($statistics['total_students']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="sidebar-section">
                    <div class="sidebar-header">
                        <h3 class="sidebar-title">Recent Activity</h3>
                    </div>
                    <div class="sidebar-content">
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-indicator <?php echo $activity['type']; ?>"></div>
                            <div class="activity-text"><?php echo htmlspecialchars($activity['text']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function addNewAssessment() {
    // Switch to create mode
    window.location.href = 'teacher_dashboard.php?mode=create';
}

function editAssessment(id) {
    // Redirect to edit mode with the assessment ID
    window.location.href = 'teacher_dashboard.php?mode=edit&edit_id=' + id;
}

function deleteAssessment(id, title) {
    if (confirm('Are you sure you want to delete assessment "' + title + '"?\n\nThis action cannot be undone.')) {
        alert('Delete Assessment ' + id + ' - to be integrated with Moodle deletion API');
        // AJAX call to delete assessment
    }
}

// Create Assessment Functions
function saveAndPublish() {
    // Validate form first
    if (!validateAssessmentForm()) {
        return;
    }
    
    // Get form data
    var formData = getFormData();
    
    // Show confirmation
    if (confirm('Are you sure you want to save and publish this assessment?\n\nOnce published, students will be able to access it.')) {
        // TODO: AJAX call to save and publish assessment to Moodle
        alert('Assessment saved and published successfully!\n\nRedirecting to LND Dashboard...');
        
        // Redirect to LND dashboard assessment section
        window.location.href = '/local/orgadmin/lnd_dashboard.php?section=assessments';
    }
}

function saveAsDraft() {
    // Get form data
    var formData = getFormData();
    
    // TODO: AJAX call to save assessment as draft to Moodle
    alert('Assessment saved as draft successfully!\n\nYou can continue editing it later.');
    
    // Optionally redirect back to view mode or stay on the form
    window.location.href = 'teacher_dashboard.php?filter=draft';
}

function previewAssessment() {
    // Validate form first
    if (!validateAssessmentForm()) {
        return;
    }
    
    // Get form data
    var formData = getFormData();
    
    // TODO: Open preview in a new window/modal
    alert('Opening assessment preview...\n\n(This will be integrated with Moodle\'s preview functionality)');
    
    // For now, just log the form data
    console.log('Preview Assessment Data:', formData);
}

function cancelAssessment() {
    // Check if form has unsaved changes
    var hasChanges = checkForUnsavedChanges();
    
    if (hasChanges) {
        if (confirm('Are you sure you want to cancel?\n\nAll unsaved changes will be lost.')) {
            window.location.href = 'teacher_dashboard.php';
        }
    } else {
        window.location.href = 'teacher_dashboard.php';
    }
}

// Helper Functions
function validateAssessmentForm() {
    var title = document.getElementById('assessmentTitle').value.trim();
    var duration = document.getElementById('duration').value;
    var totalMarks = document.getElementById('totalMarks').value;
    var passPercentage = document.getElementById('passPercentage').value;
    
    if (!title) {
        alert('Please enter an assessment title.');
        document.getElementById('assessmentTitle').focus();
        return false;
    }
    
    if (!duration || duration <= 0) {
        alert('Please enter a valid duration in minutes.');
        document.getElementById('duration').focus();
        return false;
    }
    
    if (!totalMarks || totalMarks <= 0) {
        alert('Please enter valid total marks.');
        document.getElementById('totalMarks').focus();
        return false;
    }
    
    if (!passPercentage || passPercentage < 0 || passPercentage > 100) {
        alert('Please enter a valid pass percentage (0-100).');
        document.getElementById('passPercentage').focus();
        return false;
    }
    
    return true;
}

function getFormData() {
    return {
        title: document.getElementById('assessmentTitle').value.trim(),
        duration: parseInt(document.getElementById('duration').value),
        totalMarks: parseInt(document.getElementById('totalMarks').value),
        passPercentage: parseInt(document.getElementById('passPercentage').value),
        language: document.getElementById('language').value,
        instructions: document.getElementById('instructions').value.trim(),
        questionFile: document.getElementById('questionFile').files[0] || null
    };
}

function checkForUnsavedChanges() {
    // Check if any form fields have been filled
    var title = document.getElementById('assessmentTitle').value.trim();
    var duration = document.getElementById('duration').value;
    var totalMarks = document.getElementById('totalMarks').value;
    var passPercentage = document.getElementById('passPercentage').value;
    var language = document.getElementById('language').value;
    var instructions = document.getElementById('instructions').value.trim();
    var hasFile = document.getElementById('questionFile').files.length > 0;
    
    return title || duration || totalMarks || passPercentage || language || instructions || hasFile;
}

// File upload functionality
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('questionFile');
    const uploadArea = document.querySelector('.upload-area');
    const uploadText = document.querySelector('.upload-text');
    
    if (fileInput && uploadArea) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                uploadText.textContent = this.files[0].name;
                uploadArea.style.borderColor = '#10b981';
                uploadArea.style.backgroundColor = '#ecfdf5';
            } else {
                uploadText.textContent = 'Upload the question';
                uploadArea.style.borderColor = '#cbd5e0';
                uploadArea.style.backgroundColor = '#f8fafc';
            }
        });
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>