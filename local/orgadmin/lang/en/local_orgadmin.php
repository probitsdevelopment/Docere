<?php
// English strings for local_orgadmin.

$string['pluginname'] = 'User';

// Capability string (needed for db/access.php).
$string['orgadmin:adduser'] = 'Can add/assign users within their organization';

// Navigation / Headings
$string['nav_adduser'] = 'Add User';
$string['heading_adduser'] = 'Add / Assign User to Org';
$string['intro_adduser'] = 'Create a new user (if not existing) or attach an existing user by email, and assign a role at an Org (category) level.';

// Form fields
$string['f_category'] = 'Organisation (Course category)';
$string['f_role'] = 'Role to assign';
$string['f_email'] = 'Email';
$string['f_firstname'] = 'First name';
$string['f_lastname'] = 'Last name';
$string['f_username'] = 'Username';
$string['f_password'] = 'New password';
$string['f_auth'] = 'Choose an authentication method';
$string['f_createifmissing'] = 'Create the user if email not found';

// Buttons
$string['btn_submit'] = 'Save & Assign';

// Messages
$string['msg_user_created'] = 'Created new user: {$a}';
$string['msg_user_found']   = 'Found existing user: {$a}';
$string['msg_assigned']     = 'Assigned role to user in the selected Organisation.';

// Errors
$string['err_email_required'] = 'Email is required.';
$string['err_category_required'] = 'Please choose an Organisation (category).';
$string['err_role_required'] = 'Please choose a role to assign.';
$string['err_no_permission_any_category'] = 'You do not have Org Admin rights in any category.';
$string['err_user_not_found'] = 'User not found and “Create if missing” was not selected.';

// Summary (optional if you use summaries)
$string['summary_title'] = 'Action summary';
$string['summary_created'] = 'New account created';
$string['summary_existing'] = 'Existing account used';
$string['summary_username'] = 'Username';
$string['summary_temp_password'] = 'Temporary password';
$string['summary_role'] = 'Assigned role';
$string['summary_category'] = 'Organisation (category)';

// Extra profile fields (optional, if you decide to extend the form later)
$string['f_city'] = 'City/town';
$string['f_country'] = 'Select a country';
$string['f_timezone'] = 'Timezone';
$string['f_lang'] = 'Preferred language';
$string['f_description'] = 'Description';

$string['f_suspended'] = 'Suspended account';
$string['f_genpassword'] = 'Generate password';
$string['f_forcepasswordchange'] = 'Force password change';
$string['f_maildisplay'] = 'Email visibility';



