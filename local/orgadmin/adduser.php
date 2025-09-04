// --- Allowed organisations (categories) for this user.
$allowedcats = [];
foreach (core_course_category::get_all() as $cat) {
    $ctx = context_coursecat::instance($cat->id);
    if (has_capability('local/orgadmin:adduser', $ctx)) {
        $allowedcats[$cat->id] = $cat->get_formatted_name();
    }
}
if (!$allowedcats) {
    print_error('err_no_permission_any_category', 'local_orgadmin');
}

// --- Roles: only show stakeholder, student, teacher, L&D (editingteacher optional).
global $DB;
$whitelistshortnames = ['stakeholder', 'student', 'teacher', 'editingteacher', 'ld'];

$firstcatid = (int) array_key_first($allowedcats);
$firstctx   = context_coursecat::instance($firstcatid);

// All roles the current user can assign *in that context*.
$assignable = get_assignable_roles($firstctx, ROLENAME_ORIGINAL, false); // [roleid => name]

// Map roleid -> shortname.
$roleid2short = [];
if ($assignable) {
    list($in, $params) = $DB->get_in_or_equal(array_keys($assignable), SQL_PARAMS_NAMED);
    $recs = $DB->get_records_select('role', "id $in", $params, '', 'id,shortname');
    foreach ($recs as $r) { $roleid2short[$r->id] = $r->shortname; }
}

// Filter to whitelist.
$filteredroles = [];
foreach ($assignable as $rid => $rname) {
    $sn = $roleid2short[$rid] ?? '';
    if (in_array($sn, $whitelistshortnames, true)) {
        $filteredroles[$rid] = $rname;
    }
}

// Fallback: if nothing matched the whitelist, still show those roles by shortname (if they exist).
if (!$filteredroles) {
    $need = $DB->get_records_list('role', 'shortname', $whitelistshortnames, '', 'id,shortname,name');
    foreach ($need as $r) {
        $filteredroles[$r->id] = $r->name ?: $r->shortname;
    }
}
