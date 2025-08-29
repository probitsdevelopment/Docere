<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serve org (category) logos.
 * URL: /pluginfile.php/{contextid}/local_orgbranding/orglogo/{categoryid}/{filepath}{filename}
 */
function local_orgbranding_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($filearea !== 'orglogo' || $context->contextlevel !== CONTEXT_COURSECAT) {
        send_file_not_found();
    }

    require_login();

    if (empty($args)) {
        send_file_not_found();
    }

    // /{itemid}/{...}/{filename}
    $itemid = (int) array_shift($args);  // category id
    if ($itemid <= 0) {
        send_file_not_found();
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_orgbranding', 'orglogo', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    // 4.1+ uses $sendfileoptions; older uses $options.
    $sendfileoptions = isset($options) ? $options : [];
    send_stored_file($file, 0, 0, $forcedownload, $sendfileoptions);
}
