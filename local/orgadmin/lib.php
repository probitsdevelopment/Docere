<?php
defined('MOODLE_INTERNAL') || die();

use local_orgadmin\local\org_helper;

function local_orgadmin_extend_primary_navigation($primary): void {
    global $USER;

    if (is_siteadmin($USER)) {
        return;
    }

    $orgcatctxs = org_helper::user_org_categories();
    if (empty($orgcatctxs)) {
        return;
    }

    $url = new moodle_url('/local/orgadmin/index.php');

    $primary->add(
        get_string('dashboardtitle', 'local_orgadmin'),
        $url,
        'orgadmin',
        null,
        new pix_icon('i/siteevent', '')
    );
}
