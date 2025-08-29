
<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serve org (category) logos saved in component 'local_orgbranding', filearea 'orglogo'.
 * URL: /pluginfile.php/{contextid}/local_orgbranding/orglogo/{categoryid}/{filepath}{filename}
 */
function local_orgbranding_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($filearea !== 'orglogo' || $context->contextlevel !== CONTEXT_COURSECAT) {
        send_file_not_found();
    }

    require_login();

    // URL parts: /{itemid}/{filepath...}/{filename}
    $itemid = (int) array_shift($args);
    if ($itemid <= 0) {
        send_file_not_found();
    }

    $filepath = '/';
    if (count($args) > 1) {
        $filename = array_pop($args);
        if (!empty($args)) {
            $filepath .= implode('/', $args) . '/';
        }
    } else {
        $filename = array_shift($args);
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_orgbranding', 'orglogo', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
