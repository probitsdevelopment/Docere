<?php
// local/orgadmin/assessment_handler.php - Handle assessment AJAX operations

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

// Check if user should have access to teacher functionality OR L&D functionality
if (!orgadmin_role_detector::should_show_teacher_dashboard() &&
    !orgadmin_role_detector::should_show_lnd_dashboard() &&
    !is_siteadmin()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Access denied']));
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Get action from request
$action = required_param('action', PARAM_TEXT);

// Get database connection
$DB = $GLOBALS['DB'];

// Create assessments table if it doesn't exist
function create_assessments_table() {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('orgadmin_assessments');

    if (!$dbman->table_exists($table)) {
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('organization_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('total_marks', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('pass_percentage', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('language', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('question_file', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('rejection_reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        $dbman->create_table($table);
    }
}

// Get trainer's organization ID
function get_trainer_organization_id($userid) {
    global $DB;

    // Check if trainer has role at category level (organization trainer)
    $category_roles = $DB->get_records_sql("
        SELECT DISTINCT cc.id as category_id
        FROM {role_assignments} ra
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        JOIN {course_categories} cc ON cc.id = ctx.instanceid
        WHERE ra.userid = ? AND ctx.contextlevel = 40 AND r.shortname = 'editingteacher'
        ORDER BY cc.id ASC
    ", [$userid]);

    if (!empty($category_roles)) {
        // Return the first category ID (trainer's primary organization)
        $first_category = reset($category_roles);
        return $first_category->category_id;
    }

    // If not found at category level, check if trainer has system-level role
    $system_role = $DB->record_exists_sql("
        SELECT 1
        FROM {role_assignments} ra
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        WHERE ra.userid = ? AND ctx.contextlevel = 10 AND r.shortname = 'editingteacher'
    ", [$userid]);

    if ($system_role) {
        // System-level trainer - return 0 to indicate site-level
        return 0;
    }

    // No organization found - default to null
    return null;
}

// Initialize table
create_assessments_table();

header('Content-Type: application/json');

try {
    switch ($action) {
        
        case 'save_draft':
            // Get form data
            $title = required_param('title', PARAM_TEXT);
            $duration = optional_param('duration', 0, PARAM_INT);
            $total_marks = optional_param('total_marks', 0, PARAM_INT);
            $pass_percentage = optional_param('pass_percentage', 0, PARAM_INT);
            $language = optional_param('language', '', PARAM_TEXT);
            $instructions = optional_param('instructions', '', PARAM_TEXT);
            $assessment_id = optional_param('assessment_id', 0, PARAM_INT);

            $time = time();
            $organization_id = get_trainer_organization_id($USER->id);

            $record = new stdClass();
            $record->userid = $USER->id;
            $record->organization_id = $organization_id;
            $record->title = $title;
            $record->duration = $duration;
            $record->total_marks = $total_marks;
            $record->pass_percentage = $pass_percentage;
            $record->language = $language;
            $record->instructions = $instructions;
            $record->status = 'draft';
            $record->timemodified = $time;

            if ($assessment_id > 0) {
                // Update existing assessment
                $record->id = $assessment_id;
                $DB->update_record('orgadmin_assessments', $record);
                $id = $assessment_id;
            } else {
                // Create new assessment
                $record->timecreated = $time;
                $id = $DB->insert_record('orgadmin_assessments', $record);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Assessment saved as draft successfully!',
                'assessment_id' => $id
            ]);
            break;

        case 'submit_review':
            // Log the submission attempt
            error_log('Assessment Handler: Received submit_review request from user ' . $USER->id);

            // Get form data
            $title = required_param('title', PARAM_TEXT);
            $duration = required_param('duration', PARAM_INT);
            $total_marks = required_param('total_marks', PARAM_INT);
            $pass_percentage = required_param('pass_percentage', PARAM_INT);
            $language = optional_param('language', '', PARAM_TEXT);
            $instructions = optional_param('instructions', '', PARAM_TEXT);
            $assessment_id = optional_param('assessment_id', 0, PARAM_INT);

            error_log('Assessment data: Title=' . $title . ', Duration=' . $duration . ', Marks=' . $total_marks);

            // Validate required fields
            if (empty($title)) {
                throw new Exception('Assessment title is required');
            }
            if ($duration <= 0) {
                throw new Exception('Duration must be greater than 0');
            }
            if ($total_marks <= 0) {
                throw new Exception('Total marks must be greater than 0');
            }
            if ($pass_percentage < 0 || $pass_percentage > 100) {
                throw new Exception('Pass percentage must be between 0 and 100');
            }

            $time = time();
            $organization_id = get_trainer_organization_id($USER->id);

            $record = new stdClass();
            $record->userid = $USER->id;
            $record->organization_id = $organization_id;
            $record->title = $title;
            $record->duration = $duration;
            $record->total_marks = $total_marks;
            $record->pass_percentage = $pass_percentage;
            $record->language = $language;
            $record->instructions = $instructions;
            $record->status = 'pending_review';
            $record->timemodified = $time;

            error_log('Assessment record created with status: ' . $record->status);

            if ($assessment_id > 0) {
                // Update existing assessment
                $record->id = $assessment_id;
                $result = $DB->update_record('orgadmin_assessments', $record);
                $id = $assessment_id;
                error_log('Updated existing assessment ID: ' . $id . ', Result: ' . ($result ? 'success' : 'failed'));
            } else {
                // Create new assessment
                $record->timecreated = $time;
                $id = $DB->insert_record('orgadmin_assessments', $record);
                error_log('Created new assessment ID: ' . $id);
            }

            // Verify the record was saved correctly
            $saved_record = $DB->get_record('orgadmin_assessments', ['id' => $id]);
            if ($saved_record) {
                error_log('Verification: Assessment ID ' . $id . ' saved with status: ' . $saved_record->status);
            } else {
                error_log('ERROR: Assessment ID ' . $id . ' not found after saving!');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Assessment submitted for review successfully!',
                'assessment_id' => $id
            ]);
            break;

        case 'approve':
            // This action is for L&D users to approve assessments
            $assessment_id = required_param('assessment_id', PARAM_INT);

            error_log('Assessment Handler: Received approve request for assessment ID: ' . $assessment_id . ' from user ' . $USER->id);

            // Get the assessment to approve
            $assessment = $DB->get_record('orgadmin_assessments', ['id' => $assessment_id]);

            if (!$assessment) {
                throw new Exception('Assessment not found');
            }

            error_log('Assessment found with status: ' . $assessment->status);

            // Update status to published
            $record = new stdClass();
            $record->id = $assessment_id;
            $record->status = 'published';
            $record->timemodified = time();

            $result = $DB->update_record('orgadmin_assessments', $record);
            error_log('Approval update result: ' . ($result ? 'success' : 'failed'));

            // Verify the update
            $updated_assessment = $DB->get_record('orgadmin_assessments', ['id' => $assessment_id]);
            if ($updated_assessment) {
                error_log('Verification: Assessment ID ' . $assessment_id . ' now has status: ' . $updated_assessment->status);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Assessment approved and published successfully!',
                'assessment_id' => $assessment_id
            ]);
            break;

        case 'reject':
            // This action is for L&D users to reject assessments
            $assessment_id = required_param('assessment_id', PARAM_INT);
            $reason = optional_param('reason', '', PARAM_TEXT);

            error_log('Assessment Handler: Received reject request for assessment ID: ' . $assessment_id . ' from user ' . $USER->id);

            // Get the assessment to reject
            $assessment = $DB->get_record('orgadmin_assessments', ['id' => $assessment_id]);

            if (!$assessment) {
                throw new Exception('Assessment not found');
            }

            // Update status to rejected
            $record = new stdClass();
            $record->id = $assessment_id;
            $record->status = 'rejected';
            $record->timemodified = time();

            if (!empty($reason)) {
                $record->rejection_reason = $reason;
            }

            $result = $DB->update_record('orgadmin_assessments', $record);
            error_log('Rejection update result: ' . ($result ? 'success' : 'failed'));

            echo json_encode([
                'success' => true,
                'message' => 'Assessment rejected successfully!',
                'assessment_id' => $assessment_id
            ]);
            break;

        case 'delete':
            $assessment_id = required_param('assessment_id', PARAM_INT);

            // Check if assessment belongs to current user
            $assessment = $DB->get_record('orgadmin_assessments', [
                'id' => $assessment_id,
                'userid' => $USER->id
            ]);

            if (!$assessment) {
                throw new Exception('Assessment not found or access denied');
            }

            $DB->delete_records('orgadmin_assessments', ['id' => $assessment_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Assessment deleted successfully!'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>