<?php
// lang/en/local_orgadmin.php

// General.
$string['pluginname'] = 'Org admin tools';

// Headings / nav.
$string['heading_adduser'] = 'Add / Assign User to Org';
$string['intro_adduser'] = 'Create a new user (if not existing) or attach an existing user by email, and assign a role at an Org (category) level.';
$string['nav_adduser'] = 'Add user';

// Form labels.
$string['f_category'] = 'Organisation (Course category)';
$string['f_role'] = 'Role to assign';

$string['f_username'] = 'Username';
$string['f_auth'] = 'Choose an authentication method';
$string['f_suspended'] = 'Suspended account';
$string['f_genpassword'] = 'Generate password';
$string['f_password'] = 'New password';
$string['f_forcepasswordchange'] = 'Force password change';
$string['f_firstname'] = 'First name';
$string['f_lastname'] = 'Last name';
$string['f_email'] = 'Email address';
$string['f_maildisplay'] = 'Email visibility';

$string['f_createifmissing'] = 'Create the user if email not found';

// Email visibility options.
$string['emaildisplayall'] = 'Visible to everyone';
$string['emaildisplaycourse'] = 'Visible to course participants';
$string['emaildisplayhide'] = 'Hide from everyone';

// Buttons.
$string['btn_submit'] = 'Save';

// Errors / validation.
$string['err_no_permission_any_category'] = 'You do not have Org Admin rights in any category.';
$string['err_user_not_found'] = 'User with this email was not found.';
$string['err_category_required'] = 'Please choose an organisation (category).';
$string['err_role_required'] = 'Please choose a role to assign.';
$string['err_password_length'] = 'Password must be at least 8 characters.';

// Messages / summaries.
$string['msg_user_created'] = 'User {$a} has been created.';
$string['msg_user_found'] = 'User {$a} already exists.';
$string['msg_assigned'] = 'Role assignment completed.';

$string['summary_created'] = 'New user';
$string['summary_existing'] = 'Existing user';
$string['summary_username'] = 'Username';
$string['summary_temp_password'] = 'Temporary password';
$string['summary_role'] = 'Assigned role';
$string['summary_category'] = 'Organisation (category)';
