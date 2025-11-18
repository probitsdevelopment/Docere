<?php
// local/orgadmin/adduser.php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/gdlib.php'); // for process_new_icon() if you add avatar later

/**
 * Verify email address by connecting to SMTP server
 * This checks if the specific email address exists on the mail server
 * Works for Gmail, Outlook, Yahoo, and other email providers
 */
function verify_email_smtp($email, $domain, $mxRecords) {
    // Major email providers that block SMTP verification
    // For these, we only verify domain exists, not specific email
    $knownProviders = ['gmail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'aol.com', 'icloud.com'];
    
    // If it's a known provider, just verify domain has MX records (already done)
    // These providers won't tell us if specific email exists for privacy/security
    if (in_array(strtolower($domain), $knownProviders)) {
        // For known providers, we accept if domain is valid (MX records exist)
        // But we still reject obvious fake patterns
        $localPart = explode('@', $email)[0];
        
        // Reject suspicious patterns in email local part
        $suspiciousPatterns = [
            '/^test/i',      // test@gmail.com, testing@outlook.com
            '/^fake/i',      // fake@gmail.com
            '/^dummy/i',     // dummy@outlook.com
            '/^temp/i',      // temp@gmail.com
            '/^sample/i',    // sample@yahoo.com
            '/^demo/i',      // demo@outlook.com
            '/^asdf/i',      // asdf@gmail.com
            '/^qwer/i',      // qwerty@outlook.com
            '/^xyz/i',       // xyz@gmail.com
            '/^aaa/i',       // aaa@gmail.com
            '/^111/i',       // 111@gmail.com
            '/^123/i',       // 123test@gmail.com
            '/^invalid/i',   // invalid@outlook.com
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $localPart)) {
                return false; // Reject suspicious email
            }
        }
        
        return true; // Accept for known providers if not suspicious
    }
    
    // For non-major providers, try SMTP verification
    // Sort MX records by priority
    usort($mxRecords, function($a, $b) {
        return $a['pri'] - $b['pri'];
    });

    // Try to connect to the mail server
    foreach ($mxRecords as $mxRecord) {
        $mxHost = $mxRecord['target'];
        
        // Try to establish SMTP connection with timeout
        $connection = @fsockopen($mxHost, 25, $errno, $errstr, 5);
        if (!$connection) {
            continue; // Try next MX server
        }

        // Set timeout for socket operations
        stream_set_timeout($connection, 5);
        
        // Read initial response
        $response = fgets($connection, 1024);
        if ($response === false) {
            fclose($connection);
            continue;
        }
        
        // Send HELO command
        fputs($connection, "HELO " . gethostname() . "\r\n");
        $response = fgets($connection, 1024);
        if ($response === false) {
            fclose($connection);
            continue;
        }
        
        // Send MAIL FROM command
        fputs($connection, "MAIL FROM: <verify@" . gethostname() . ">\r\n");
        $response = fgets($connection, 1024);
        if ($response === false) {
            fclose($connection);
            continue;
        }
        
        // Send RCPT TO command to verify if email exists
        fputs($connection, "RCPT TO: <" . $email . ">\r\n");
        $response = fgets($connection, 1024);
        
        // Close connection properly
        fputs($connection, "QUIT\r\n");
        fclose($connection);
        
        if ($response === false) {
            continue;
        }
        
        // Check response code
        $responseCode = substr($response, 0, 3);
        
        // 250 or 251 = Email exists and is valid
        if (in_array($responseCode, ['250', '251'])) {
            return true; // Email is valid
        } 
        // 550, 551, 552, 553 = Email doesn't exist or is invalid
        else if (in_array($responseCode, ['550', '551', '552', '553', '554'])) {
            return false; // Email definitely doesn't exist
        }
        // 450, 451, 452 = Temporary error, try next server
        else if (in_array($responseCode, ['450', '451', '452'])) {
            continue; // Try next MX server
        }
    }

    // If we can't verify (greylisting, temporary errors, or all servers failed)
    // Accept it to avoid false negatives, but suspicious patterns already rejected above
    return true;
}

require_login();

$PAGE->set_url(new moodle_url('/local/orgadmin/adduser.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('heading_adduser', 'local_orgadmin'));
$PAGE->set_heading(get_string('heading_adduser', 'local_orgadmin'));

// Per your requirement: hide from site admins.
if (is_siteadmin()) {
    print_error('nopermissions', 'error', '', 'local/orgadmin:adduser');
}

/* ---------- Allowed orgs (categories) ---------- */
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

/* ---------- Whitelisted roles ---------- */
global $DB, $CFG, $OUTPUT;
$whitelistshortnames = ['stakeholder', 'student', 'teacher', 'editingteacher', 'ld', 'orgops', 'coursecreator'];

$firstcatid = (int) array_key_first($allowedcats);
$firstctx   = context_coursecat::instance($firstcatid);

$assignable = get_assignable_roles($firstctx, ROLENAME_ORIGINAL, false); // [roleid => name]

$roleid2short = [];
if ($assignable) {
    list($in, $params) = $DB->get_in_or_equal(array_keys($assignable), SQL_PARAMS_NAMED);
    $recs = $DB->get_records_select('role', "id $in", $params, '', 'id,shortname');
    foreach ($recs as $r) { $roleid2short[$r->id] = $r->shortname; }
}

$filteredroles = [];
foreach ($assignable as $rid => $rname) {
    $sn = $roleid2short[$rid] ?? '';
    if (in_array($sn, $whitelistshortnames, true)) { $filteredroles[$rid] = $rname; }
}
if (!$filteredroles) {
    $need = $DB->get_records_list('role', 'shortname', $whitelistshortnames, '', 'id,shortname,name');
    foreach ($need as $r) { $filteredroles[$r->id] = $r->name ?: $r->shortname; }
}

/* ---------- Build the form ---------- */
$customdata = [
    'categories' => $allowedcats,
    'roles'      => $filteredroles,
];
$mform = new \local_orgadmin\form\adduser(null, $customdata);

/* ---------- Workflow ---------- */
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/orgadmin/index.php'));

} else if ($data = $mform->get_data()) {

    // Validate category and role.
    $categoryid = (int)$data->categoryid;
    if (!isset($allowedcats[$categoryid])) {
        print_error('nopermissions', 'error', '', 'category');
    }
    $catctx = context_coursecat::instance($categoryid);

    $roleid = (int)$data->roleid;
   // 0) Make absolutely sure we are testing the selected category context.
$catctx = context_coursecat::instance($categoryid);

// 1) You must hold BOTH capabilities in this category:
$missing = [];
if (!has_capability('local/orgadmin:adduser', $catctx)) {
    $missing[] = 'local/orgadmin:adduser';
}
if (!has_capability('moodle/role:assign', $catctx)) {
    $missing[] = 'moodle/role:assign';
}

// 2) If anything is missing, show it clearly and stop.
if ($missing) {
    \core\notification::error('Missing capability in this category: '.implode(', ', $missing));
    echo $OUTPUT->footer();
    exit;
}

// 3) Check the allow-assign matrix result in THIS category.
$assignable = get_assignable_roles($catctx, ROLENAME_ORIGINAL, false); // [roleid => name]

// Optional: show what Moodle thinks you can assign here.
\core\notification::info('Assignable roles in this category: '.implode(', ', array_values($assignable)));

$roleid = (int)$data->roleid;
if (!isset($assignable[$roleid])) {
    $rshort = $DB->get_field('role', 'shortname', ['id' => $roleid]);
    $rolename = isset($assignable[$roleid]) ? $assignable[$roleid] : $rshort;

    $msg  = "This role is NOT assignable here: {$rolename}.";
    $msg .= " Reasons: (a) matrix doesn’t allow Acme Org Admin → {$rshort},";
    $msg .= " or (b) that role is not allowed in Category context.";

    \core\notification::error($msg);
    echo $OUTPUT->footer();
    exit;
}


    // Find/create user.
    $email     = trim(core_text::strtolower($data->email));
    $username  = trim($data->username);
    $firstname = trim($data->firstname);
    $lastname  = trim($data->lastname);
    $auth      = $data->auth ?? 'manual';
    $password  = (string)$data->password;

    if ($email === '' || $username === '' || $password === '') {
        print_error('missingfield', 'error'); // form should have blocked this already
    }

    // Additional server-side email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        \core\notification::error('Invalid email format. Please enter a valid email address.');
        redirect($PAGE->url);
    }

    // Check for valid domain with DNS records
    if (strpos($email, '@') !== false) {
        list($local, $domain) = explode('@', $email, 2);
        
        // Reject if no dot in domain
        if (strpos($domain, '.') === false) {
            \core\notification::error('Email must have a valid domain (e.g., user@example.com)');
            redirect($PAGE->url);
        }

        // Block common dummy/test domains
        $dummydomains = ['test.com', 'test.test', 'example.com', 'dummy.com', 'fake.com', 
                        'invalid.com', 'temp.com', 'sample.com', 'demo.com', 'localhost'];
        foreach ($dummydomains as $dummy) {
            if (stripos($domain, $dummy) !== false) {
                \core\notification::error('Please use a valid working email address, not a test/dummy email.');
                redirect($PAGE->url);
            }
        }

        // Verify domain exists via DNS
        if (function_exists('checkdnsrr')) {
            $hasMX = @checkdnsrr($domain, 'MX');
            $hasA = @checkdnsrr($domain, 'A');
            
            if (!$hasMX && !$hasA) {
                \core\notification::error('Email domain "' . $domain . '" does not exist or cannot receive emails. Please use a valid working email address.');
                redirect($PAGE->url);
            }
        }

        // Additional check: Verify SMTP server and email address validity
        if ($hasMX && function_exists('dns_get_record')) {
            $mxRecords = @dns_get_record($domain, DNS_MX);
            if (empty($mxRecords)) {
                \core\notification::error('Email domain "' . $domain . '" cannot receive emails. Please verify your email address.');
                redirect($PAGE->url);
            }

            // Advanced: Try to verify the email address with SMTP server
            $isValidEmail = verify_email_smtp($email, $domain, $mxRecords);
            if (!$isValidEmail) {
                \core\notification::error('The email address "' . $email . '" does not exist or cannot receive emails. Please use a valid working email address.');
                redirect($PAGE->url);
            }
        }
    }

    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);

    if (!$user) {
        if (empty($data->createifmissing)) {
            print_error('err_user_not_found', 'local_orgadmin');
        }

        // Ensure unique username on this host.
        $base = $username; $i = 1;
        while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $username = $base . $i++;
        }

        $new              = new stdClass();
        $new->auth        = $auth ?: 'manual';
        $new->username    = $username;
        $new->password    = $password;
        $new->firstname   = $firstname ?: 'User';
        $new->lastname    = $lastname  ?: 'Org';
        $new->email       = $email;
        $new->mnethostid  = $CFG->mnet_localhost_id;
        $new->confirmed   = 1;  // User confirmed, no email verification needed
        $new->timecreated = time();
        $new->timemodified= time();

        $userid = user_create_user($new, true, false);
        $user   = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        \core\notification::success(get_string('msg_user_created', 'local_orgadmin', fullname($user)));
    } else {
        \core\notification::info(get_string('msg_user_found', 'local_orgadmin', fullname($user)));
    }
    // Assign role at the category.
    role_assign($roleid, $user->id, $catctx->id);
    \core\notification::success(get_string('msg_assigned', 'local_orgadmin'));

    // Redirect back to the dashboard (or stay on the form—your choice).
    redirect(new moodle_url('/local/orgadmin/index.php'));

} else {
    echo $OUTPUT->header();
    echo html_writer::div(get_string('intro_adduser', 'local_orgadmin'), 'mb-3');
    $mform->display();
    echo $OUTPUT->footer();
}