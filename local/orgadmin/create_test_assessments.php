<?php
// Create test assessments for debugging
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

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
        echo "✅ Created orgadmin_assessments table\n";
    } else {
        echo "✅ orgadmin_assessments table already exists\n";
    }
}

// Create table first
create_assessments_table();

// Get a valid user ID (we'll use admin user ID 2)
$user = $DB->get_record('user', ['username' => 'admin']);
if (!$user) {
    // Try to get any valid user
    $user = $DB->get_record_sql("SELECT * FROM {user} WHERE deleted = 0 AND suspended = 0 LIMIT 1");
}

if (!$user) {
    echo "❌ No valid user found\n";
    exit(1);
}

echo "Using user: " . $user->firstname . " " . $user->lastname . " (ID: " . $user->id . ")\n";

// Create test assessments with pending_review status
$time = time();

$assessments = [
    [
        'title' => 'mernstack',
        'duration' => 45,
        'total_marks' => 100,
        'pass_percentage' => 70,
        'language' => 'en',
        'instructions' => 'This is a comprehensive test covering MERN stack development.',
        'status' => 'pending_review'
    ],
    [
        'title' => 'Test on Full Stack Development',
        'duration' => 45,
        'total_marks' => 100,
        'pass_percentage' => 70,
        'language' => 'en',
        'instructions' => 'Advanced test on full stack development concepts.',
        'status' => 'pending_review'
    ]
];

foreach ($assessments as $assessment_data) {
    // Check if this assessment already exists
    $existing = $DB->get_record('orgadmin_assessments', [
        'title' => $assessment_data['title'],
        'userid' => $user->id
    ]);

    if ($existing) {
        echo "⏭️ Assessment '" . $assessment_data['title'] . "' already exists (ID: " . $existing->id . ")\n";
        continue;
    }

    $record = new stdClass();
    $record->userid = $user->id;
    $record->organization_id = 0; // Site trainer
    $record->title = $assessment_data['title'];
    $record->duration = $assessment_data['duration'];
    $record->total_marks = $assessment_data['total_marks'];
    $record->pass_percentage = $assessment_data['pass_percentage'];
    $record->language = $assessment_data['language'];
    $record->instructions = $assessment_data['instructions'];
    $record->status = $assessment_data['status'];
    $record->timecreated = $time;
    $record->timemodified = $time;

    $id = $DB->insert_record('orgadmin_assessments', $record);
    echo "✅ Created assessment: " . $assessment_data['title'] . " (ID: $id)\n";
}

// Verify what we created
echo "\n📊 Current assessments in database:\n";
$all_assessments = $DB->get_records('orgadmin_assessments');
foreach ($all_assessments as $assessment) {
    echo "- ID: " . $assessment->id . ", Title: " . $assessment->title . ", Status: " . $assessment->status . "\n";
}

// Check pending specifically
echo "\n⏳ Pending review assessments:\n";
$pending = $DB->get_records('orgadmin_assessments', ['status' => 'pending_review']);
foreach ($pending as $assessment) {
    echo "- " . $assessment->title . " (ID: " . $assessment->id . ")\n";
}

echo "\n✅ Test data creation complete!\n";
?>