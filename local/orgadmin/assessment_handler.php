<?php
// local/orgadmin/assessment_handler.php - Handle assessment AJAX operations

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

// Check if user should have access to teacher functionality OR L&D functionality
if (!orgadmin_role_detector::should_show_teacher_dashboard() &&
    !orgadmin_role_detector::should_show_lnd_dashboard() &&
    !is_siteadmin()) {
    // Log the permission check for debugging
    error_log('Assessment Handler: Access denied for user ' . $USER->id . '. Teacher: ' .
        (orgadmin_role_detector::should_show_teacher_dashboard() ? 'YES' : 'NO') .
        ', L&D: ' . (orgadmin_role_detector::should_show_lnd_dashboard() ? 'YES' : 'NO') .
        ', Admin: ' . (is_siteadmin() ? 'YES' : 'NO'));

    http_response_code(403);
    exit(json_encode(['error' => 'Access denied - insufficient permissions']));
}

// Handle only POST requests
error_log('Assessment Handler: Received ' . $_SERVER['REQUEST_METHOD'] . ' request');
error_log('Assessment Handler: POST data: ' . print_r($_POST, true));
error_log('Assessment Handler: Raw input: ' . file_get_contents('php://input'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed - received ' . $_SERVER['REQUEST_METHOD']]));
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
    $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('total_marks', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('pass_percentage', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('language', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('instructions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'draft');
        $table->add_field('question_file', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('rejection_reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('allow_multiple_attempts', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('send_email_notifications', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
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

// Function to send email notifications to students about new assessments
function send_assessment_notifications($assessment, $students) {
    global $DB, $USER;

    if (empty($students)) {
        return 0;
    }

    $emails_sent = 0;

    // Get assessment creator details
    $creator = $DB->get_record('user', ['id' => $assessment->userid], 'firstname, lastname, email');
    $creator_name = $creator ? $creator->firstname . ' ' . $creator->lastname : 'System';

    foreach ($students as $student_data) {
        try {
            // Get student user object
            $student = $DB->get_record('user', ['email' => $student_data['email']], 'id, firstname, lastname, email');
            if (!$student) {
                error_log('Student not found with email: ' . $student_data['email']);
                continue;
            }

            // Prepare email content
            $subject = 'New Assessment Assignment: ' . $assessment->title;

            $message = "Dear {$student->firstname},\n\n";
            $message .= "You have been assigned a new assessment:\n\n";
            $message .= "Assessment: {$assessment->title}\n";
            $message .= "Duration: {$assessment->duration} minutes\n";
            $message .= "Total Marks: {$assessment->total_marks}\n";
            $message .= "Pass Percentage: {$assessment->pass_percentage}%\n";

            if ($assessment->allow_multiple_attempts) {
                $message .= "Multiple attempts: Allowed\n";
            } else {
                $message .= "Multiple attempts: Not allowed\n";
            }

            if (!empty($assessment->instructions)) {
                $message .= "\nInstructions:\n{$assessment->instructions}\n";
            }

            $message .= "\nPlease log in to the learning platform to take your assessment.\n\n";
            $message .= "Best regards,\n";
            $message .= "{$creator_name}\n";
            $message .= "Learning & Development Team";

            // Send email
            $success = email_to_user($student, $creator, $subject, $message);

            if ($success) {
                $emails_sent++;
                error_log('Email sent successfully to: ' . $student->email);
            } else {
                error_log('Failed to send email to: ' . $student->email);
            }

        } catch (Exception $e) {
            error_log('Error sending email to student: ' . $e->getMessage());
        }
    }

    return $emails_sent;
}

// Function to upgrade assessments table with new settings columns
function upgrade_assessments_table() {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('orgadmin_assessments');

    // Add allow_multiple_attempts column if it doesn't exist
    $field = new xmldb_field('allow_multiple_attempts', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
        error_log('Assessment Handler: Added allow_multiple_attempts column');
    }

    // Add send_email_notifications column if it doesn't exist
    $field = new xmldb_field('send_email_notifications', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
        error_log('Assessment Handler: Added send_email_notifications column');
    }

    // Add courseid column if it doesn't exist (store selected course for the assessment)
    try {
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            error_log('Assessment Handler: Added courseid column to orgadmin_assessments');
        }
    } catch (Exception $e) {
        error_log('Assessment Handler: Error adding courseid to orgadmin_assessments: ' . $e->getMessage());
    }
}

// Ensure local_questions table has a courseid column to link questions to a course
function upgrade_local_questions_table() {
    global $DB;

    try {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_questions');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            error_log('Assessment Handler: Added courseid column to local_questions');
        }
    } catch (Exception $e) {
        error_log('Assessment Handler: Error upgrading local_questions table: ' . $e->getMessage());
    }
}

// Function to save assessment questions and details
function save_assessment_questions($assessment_id, $questions, $time, $courseid = 0) {
    global $DB;

    foreach ($questions as $question_index => $question_data) {
        // Check if question_data is an array
        if (!is_array($question_data)) {
            continue;
        }

        if (empty($question_data['qid']) || empty($question_data['qtitle'])) {
            continue; // Skip invalid questions
        }

        // Save main question
        $question_record = new stdClass();
        $question_record->assessment_id = $assessment_id;
        // Attach courseid to question if provided by the assessment form or question data
        if (!empty($courseid)) {
            $question_record->courseid = intval($courseid);
        } else if (isset($question_data['courseid'])) {
            $question_record->courseid = intval($question_data['courseid']);
        }
        $question_record->qid = is_array($question_data['qid']) ? '' : clean_param($question_data['qid'], PARAM_TEXT);
        $question_record->qtitle = is_array($question_data['qtitle']) ? '' : clean_param($question_data['qtitle'], PARAM_TEXT);
        $question_record->expectation = isset($question_data['expectation']) && !is_array($question_data['expectation']) ? clean_param($question_data['expectation'], PARAM_TEXT) : '';
        $question_record->programming_language = isset($question_data['programming_language']) && !is_array($question_data['programming_language']) ? clean_param($question_data['programming_language'], PARAM_TEXT) : '';

        // Calculate max marks from details
        $max_marks = 0;
        if (isset($question_data['details']) && is_array($question_data['details'])) {
            foreach ($question_data['details'] as $detail) {
                if (is_array($detail) && isset($detail['max_marks']) && !is_array($detail['max_marks'])) {
                    $max_marks += intval($detail['max_marks']);
                }
            }
        }
        $question_record->max_marks = $max_marks;
        $question_record->timecreated = $time;
        $question_record->timemodified = $time;

        $question_id = $DB->insert_record('local_questions', $question_record);

        // Save question details
        if (isset($question_data['details']) && is_array($question_data['details'])) {
            $sortorder = 0;
            foreach ($question_data['details'] as $detail_index => $detail_data) {
                if (!is_array($detail_data)) {
                    continue;
                }

                if (empty($detail_data['qdetailtitle'])) {
                    continue; // Skip invalid details
                }

                $detail_record = new stdClass();
                $detail_record->question_id = $question_id;
                $detail_record->qdetailid = isset($detail_data['qdetailid']) && !is_array($detail_data['qdetailid']) ? clean_param($detail_data['qdetailid'], PARAM_TEXT) : '';
                $detail_record->qdetailtitle = !is_array($detail_data['qdetailtitle']) ? clean_param($detail_data['qdetailtitle'], PARAM_TEXT) : '';
                $detail_record->max_marks = isset($detail_data['max_marks']) && !is_array($detail_data['max_marks']) ? intval($detail_data['max_marks']) : 0;
                $detail_record->sortorder = $sortorder++;

                $DB->insert_record('local_question_details', $detail_record);
            }
        }
    }
}

// Initialize table
create_assessments_table();
upgrade_assessments_table();
// Ensure local_questions has courseid column
upgrade_local_questions_table();

header('Content-Type: application/json');

// Helper to detect XMLHttpRequest/AJAX calls. We prefer checking the X-Requested-With
// header which is commonly set by JavaScript libraries and Moodle's AJAX calls.
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

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
            $allow_multiple_attempts = optional_param('allow_multiple_attempts', 0, PARAM_INT);
            $send_email_notifications = optional_param('send_email_notifications', 0, PARAM_INT);

            // Get questions directly from $_POST to avoid clean_param array errors
            $questions = isset($_POST['questions']) ? $_POST['questions'] : [];

            $time = time();
            $organization_id = get_trainer_organization_id($USER->id);
            // Course selected in the form
            $courseid = optional_param('courseid', 0, PARAM_INT);

            $record = new stdClass();
            $record->userid = $USER->id;
            $record->organization_id = $organization_id;
            $record->courseid = $courseid;
            $record->title = $title;
            $record->duration = $duration;
            $record->total_marks = $total_marks;
            $record->pass_percentage = $pass_percentage;
            $record->language = $language;
            $record->instructions = $instructions;
            $record->status = 'draft';
            $record->allow_multiple_attempts = $allow_multiple_attempts;
            $record->send_email_notifications = $send_email_notifications;
            $record->timemodified = $time;

            if ($assessment_id > 0) {
                // Update existing assessment
                $record->id = $assessment_id;
                $DB->update_record('orgadmin_assessments', $record);
                $id = $assessment_id;

                // Delete existing questions for this assessment
                $existing_questions = $DB->get_records('local_questions', ['assessment_id' => $id]);
                foreach ($existing_questions as $eq) {
                    // Delete question details
                    $DB->delete_records('local_question_details', ['question_id' => $eq->id]);
                }
                $DB->delete_records('local_questions', ['assessment_id' => $id]);
            } else {
                // Create new assessment
                $record->timecreated = $time;
                $id = $DB->insert_record('orgadmin_assessments', $record);
            }

            // Save questions (pass courseid so each question is linked to the course)
            if (!empty($questions)) {
                save_assessment_questions($id, $questions, $time, $courseid);
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
            $duration = optional_param('duration', 0, PARAM_INT);
            $total_marks = optional_param('total_marks', 0, PARAM_INT);
            $pass_percentage = optional_param('pass_percentage', 0, PARAM_INT);
            $language = optional_param('language', '', PARAM_TEXT);
            $instructions = optional_param('instructions', '', PARAM_TEXT);
            $assessment_id = optional_param('assessment_id', 0, PARAM_INT);
            $allow_multiple_attempts = optional_param('allow_multiple_attempts', 0, PARAM_INT);
            $send_email_notifications = optional_param('send_email_notifications', 0, PARAM_INT);

            // Get questions directly from $_POST to avoid clean_param array errors
            $questions = isset($_POST['questions']) ? $_POST['questions'] : [];

            error_log('Assessment data: Title=' . $title . ', Duration=' . $duration . ', Marks=' . $total_marks);

            // Validate required fields
            if (empty($title)) {
                throw new Exception('Assessment title is required');
            }

            $time = time();
            $organization_id = get_trainer_organization_id($USER->id);
            // Course selected in the form
            $courseid = optional_param('courseid', 0, PARAM_INT);

            $record = new stdClass();
            $record->userid = $USER->id;
            $record->organization_id = $organization_id;
            $record->courseid = $courseid;
            $record->title = $title;
            $record->duration = $duration;
            $record->total_marks = $total_marks;
            $record->pass_percentage = $pass_percentage;
            $record->language = $language;
            $record->instructions = $instructions;
            $record->status = 'pending_review';
            $record->allow_multiple_attempts = $allow_multiple_attempts;
            $record->send_email_notifications = $send_email_notifications;
            $record->timemodified = $time;

            error_log('Assessment record created with status: ' . $record->status);

            if ($assessment_id > 0) {
                // Update existing assessment
                $record->id = $assessment_id;
                $result = $DB->update_record('orgadmin_assessments', $record);
                $id = $assessment_id;
                error_log('Updated existing assessment ID: ' . $id . ', Result: ' . ($result ? 'success' : 'failed'));

                // Delete existing questions for this assessment
                $existing_questions = $DB->get_records('local_questions', ['assessment_id' => $id]);
                foreach ($existing_questions as $eq) {
                    // Delete question details
                    $DB->delete_records('local_question_details', ['question_id' => $eq->id]);
                }
                $DB->delete_records('local_questions', ['assessment_id' => $id]);
            } else {
                // Create new assessment
                $record->timecreated = $time;
                $id = $DB->insert_record('orgadmin_assessments', $record);
                error_log('Created new assessment ID: ' . $id);
            }

            // Save questions (pass courseid so each question is linked to the course)
            if (!empty($questions)) {
                save_assessment_questions($id, $questions, $time, $courseid);
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

            error_log('Assessment Handler: Received reject request for assessment ID: ' . $assessment_id . ' from user ' . $USER->id . ' with reason: ' . $reason);

            // Get the assessment to reject
            $assessment = $DB->get_record('orgadmin_assessments', ['id' => $assessment_id]);

            if (!$assessment) {
                error_log('Assessment Handler: Assessment not found for ID: ' . $assessment_id);
                throw new Exception('Assessment not found');
            }

            error_log('Assessment Handler: Found assessment: ' . $assessment->title . ' with current status: ' . $assessment->status);

            // Check if L&D user has permission to reject this assessment
            if (!orgadmin_role_detector::should_show_lnd_dashboard()) {
                throw new Exception('User does not have L&D permissions');
            }

            // Get L&D user's organization and validate access
            require_once(__DIR__ . '/lnd_dashboard.php');
            $lnd_org_id = get_lnd_organization_id();

            if ($lnd_org_id !== null && $lnd_org_id !== 0) {
                // Organization L&D - can only reject assessments from their org or site trainers
                if ($assessment->organization_id != $lnd_org_id &&
                    $assessment->organization_id != 0 &&
                    $assessment->organization_id !== null) {
                    error_log('Assessment Handler: Access denied - L&D user from org ' . $lnd_org_id . ' trying to reject assessment from org ' . $assessment->organization_id);
                    throw new Exception('Access denied - you can only reject assessments from your organization');
                }
            }
            // Site L&D (lnd_org_id === 0) can reject all assessments

            // Update status to rejected
            $record = new stdClass();
            $record->id = $assessment_id;
            $record->status = 'rejected';
            $record->timemodified = time();

            // Only add rejection reason if the column exists
            if (!empty($reason)) {
                try {
                    // Check if rejection_reason column exists
                    $dbman = $DB->get_manager();
                    $table = new xmldb_table('orgadmin_assessments');
                    if ($dbman->field_exists($table, new xmldb_field('rejection_reason'))) {
                        $record->rejection_reason = $reason;
                        error_log('Assessment Handler: Added rejection reason to record');
                    } else {
                        error_log('Assessment Handler: rejection_reason column does not exist, skipping');
                    }
                } catch (Exception $e) {
                    error_log('Assessment Handler: Error checking rejection_reason column: ' . $e->getMessage());
                }
            }

            try {
                $result = $DB->update_record('orgadmin_assessments', $record);
                error_log('Assessment Handler: Update result: ' . ($result ? 'success' : 'failed'));

                if ($result) {
                    // Check if this is an AJAX request or form submission
                    if (!empty($_POST['return_url'])) {
                        // Form submission - if this is an AJAX call, return JSON instructing client to redirect.
                        $return_url = $_POST['return_url'];
                        if (is_ajax_request()) {
                            echo json_encode([
                                'success' => true,
                                'message' => 'Assessment rejected successfully!',
                                'assessment_id' => $assessment_id,
                                'redirect' => $return_url
                            ]);
                        } else {
                            // Non-AJAX form submission - perform server redirect as before.
                            redirect(new moodle_url($return_url), 'Assessment rejected successfully!', 2);
                        }
                    } else {
                        // No return_url provided, just return JSON success for AJAX requests.
                        echo json_encode([
                            'success' => true,
                            'message' => 'Assessment rejected successfully!',
                            'assessment_id' => $assessment_id
                        ]);
                    }
                } else {
                    throw new Exception('Failed to update assessment status');
                }
            } catch (Exception $e) {
                error_log('Assessment Handler: Database update error: ' . $e->getMessage());
                throw new Exception('Database error: ' . $e->getMessage());
            }
            break;

        case 'delete':
            $assessment_id = required_param('assessment_id', PARAM_INT);

            error_log('Assessment Handler: DELETE request - Assessment ID: ' . $assessment_id . ', User ID: ' . $USER->id);

            // Check if assessment belongs to current user
            $assessment = $DB->get_record('orgadmin_assessments', [
                'id' => $assessment_id,
                'userid' => $USER->id
            ]);

            if (!$assessment) {
                error_log('Assessment Handler: DELETE failed - Assessment not found or access denied');
                throw new Exception('Assessment not found or access denied');
            }

            error_log('Assessment Handler: Found assessment: ' . $assessment->title . ', proceeding with deletion');
            $result = $DB->delete_records('orgadmin_assessments', ['id' => $assessment_id]);
            error_log('Assessment Handler: DELETE result: ' . ($result ? 'success' : 'failed'));

            // Check if this is a form submission with return URL. For AJAX requests return JSON
            // with 'redirect' so the client can perform a safe client-side redirect.
            if (!empty($_POST['return_url'])) {
                $return_url = $_POST['return_url'];
                if (is_ajax_request()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Assessment deleted successfully!',
                        'redirect' => $return_url
                    ]);
                } else {
                    redirect(new moodle_url($return_url));
                }
            } else {
                // AJAX request or no return_url - return JSON success.
                echo json_encode([
                    'success' => true,
                    'message' => 'Assessment deleted successfully!'
                ]);
            }
            break;

        case 'approve_and_assign':
            // This action approves an assessment and assigns it to selected students
            $assessment_id = required_param('assessment_id', PARAM_INT);
            $allow_multiple_attempts = optional_param('allow_multiple_attempts', 0, PARAM_INT);
            $send_email_notifications = optional_param('send_email_notifications', 0, PARAM_INT);
            $selected_students = optional_param('selected_students', '', PARAM_TEXT);

            error_log('Assessment Handler: Received approve_and_assign request for assessment ID: ' . $assessment_id);
            error_log('Settings: Multiple attempts=' . $allow_multiple_attempts . ', Email notifications=' . $send_email_notifications);

            // Get the assessment
            $assessment = $DB->get_record('orgadmin_assessments', ['id' => $assessment_id]);
            if (!$assessment) {
                throw new Exception('Assessment not found');
            }

            // Verify L&D permissions
            if (!orgadmin_role_detector::should_show_lnd_dashboard()) {
                throw new Exception('Access denied - L&D permissions required');
            }

            // Update assessment with settings and status
            $record = new stdClass();
            $record->id = $assessment_id;
            $record->status = 'published';
            $record->allow_multiple_attempts = $allow_multiple_attempts;
            $record->send_email_notifications = $send_email_notifications;
            $record->timemodified = time();

            $result = $DB->update_record('orgadmin_assessments', $record);
            if (!$result) {
                throw new Exception('Failed to update assessment');
            }

            // Parse selected students
            $students = [];
            if (!empty($selected_students)) {
                $students = json_decode($selected_students, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('JSON decode error: ' . json_last_error_msg());
                    $students = [];
                }
            }

            // Send email notifications if enabled
            $emails_sent = 0;
            if ($send_email_notifications && !empty($students)) {
                $emails_sent = send_assessment_notifications($assessment, $students);
            }

            error_log('Assessment approved successfully. Email notifications: ' . ($send_email_notifications ? 'enabled' : 'disabled') . ', Emails sent: ' . $emails_sent);

            echo json_encode([
                'success' => true,
                'message' => 'Assessment approved and assigned successfully!',
                'assessment_id' => $assessment_id,
                'students_assigned' => count($students),
                'emails_sent' => $emails_sent
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