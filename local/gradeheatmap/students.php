
<?php
require('../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);
require_capability('moodle/grade:view', $context);

$canviewall = has_capability('moodle/grade:viewall', $context) || is_siteadmin();

if (!$canviewall) {
    // Only the current user (student view)
    $me = $DB->get_record('user', ['id' => $USER->id], 'id, firstname, lastname', MUST_EXIST);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['selfOnly'=>true, 'id'=>$me->id, 'fullname'=>fullname($me)]);
    exit;
}

$sql = "
SELECT DISTINCT u.id, u.firstname, u.lastname
FROM {grade_items} gi
JOIN {grade_grades} g ON g.itemid = gi.id
JOIN {user} u ON u.id = g.userid
WHERE gi.courseid = :courseid
  AND gi.itemtype IN ('mod','manual','course')
  AND gi.gradetype = 1
ORDER BY u.firstname, u.lastname, u.id
";
$students = $DB->get_records_sql($sql, ['courseid' => $courseid]);

$out = ['students' => []];
foreach ($students as $u) {
    $out['students'][] = ['id' => $u->id, 'fullname' => fullname($u)];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
