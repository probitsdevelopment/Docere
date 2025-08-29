
<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serve files for local_orgbranding.
 * URL: /pluginfile.php/{contextid}/local_orgbranding/{filearea}/{itemid}/{filepath}{filename}
 */
function local_orgbranding_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // Only serve category logos: contextlevel must be CONTEXT_COURSECAT and filearea 'orglogo'.
    if ($filearea !== 'orglogo' || $context->contextlevel !== CONTEXT_COURSECAT) {
        send_file_not_found();
    }

    // Require login (adjust if you need public access).
    require_login();

    // Expect /{itemid}/{filepath...}/{filename}
    if (empty($args)) {
        send_file_not_found();
    }
    $itemid  = (int) array_shift($args);
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
