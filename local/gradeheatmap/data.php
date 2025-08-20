
<?php
require('../../config.php');
require_login();

$courseid = required_param('courseid', PARAM_INT);
$userid   = optional_param('userid', 0, PARAM_INT);

$context = context_course::instance($courseid);
require_capability('moodle/grade:view', $context);

$canviewall = has_capability('moodle/grade:viewall', $context) || is_siteadmin();

if ($userid && $userid != $USER->id && !$canviewall) {
    throw new required_capability_exception($context, 'moodle/grade:viewall', 'nopermissions', '');
}

$params = ['courseid' => $courseid];
$usersql = '';
if (!$canviewall) {
    $usersql = ' AND g.userid = :myid';
    $params['myid'] = $USER->id;
} else if ($userid) {
    $usersql = ' AND g.userid = :uid';
    $params['uid'] = $userid;
}

$sql = "
SELECT gi.id AS gradeitemid,
       COALESCE(NULLIF(gi.itemname,''), CONCAT(gi.itemmodule,' #',gi.id)) AS activity,
       u.id AS userid,
       ". $DB->sql_fullname() ." AS fullname,
       g.finalgrade,
       gi.grademax,
       gi.sortorder
FROM {grade_items} gi
JOIN {grade_grades} g ON g.itemid = gi.id
JOIN {user} u ON u.id = g.userid
WHERE gi.courseid = :courseid
  AND gi.itemtype IN ('mod','manual','course')
  AND gi.gradetype = 1
  $usersql
ORDER BY fullname, gi.sortorder, gi.id
";
$rows = $DB->get_records_sql($sql, $params);

$xlabels = []; $ylabels = []; $cells = [];
$ax = []; $ay = []; $xidx = 0; $yidx = 0;

foreach ($rows as $r) {
    if (!isset($ax[$r->activity])) { $ax[$r->activity] = $xidx++; $xlabels[] = $r->activity; }
    if (!isset($ay[$r->fullname])) { $ay[$r->fullname] = $yidx++; $ylabels[] = $r->fullname; }
    $x = $ax[$r->activity];
    $y = $ay[$r->fullname];
    $v = (is_null($r->finalgrade) || $r->grademax <= 0) ? null : round(($r->finalgrade / $r->grademax) * 100, 1);
    $cells[] = ['x'=>$x, 'y'=>$y, 'v'=>$v];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['xlabels'=>$xlabels,'ylabels'=>$ylabels,'cells'=>$cells]);
