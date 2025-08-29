<?php
namespace local_orgbranding;

defined('MOODLE_INTERNAL') || die();

use \moodle_page;
use \context_coursecat;
use const \CONTEXT_COURSECAT;
use const \CONTEXT_COURSE;

class helper {

    /** Get current category id from page context (0 if none). */
    public static function current_page_categoryid(moodle_page $page): int {
        $ctx = $page->context;
        if ($ctx->contextlevel === CONTEXT_COURSECAT) {
            return (int)$ctx->instanceid;
        }
        if ($ctx->contextlevel === CONTEXT_COURSE && !empty($page->course->category)) {
            return (int)$page->course->category;
        }
        return 0;
    }

    /** Build pluginfile URL for the org logo of given category, or null. */
    public static function category_logo_url_from_catid(int $catid): ?\moodle_url {
        if ($catid <= 0) return null;

        $ctx = context_coursecat::instance($catid, IGNORE_MISSING);
        if (!$ctx) return null;

        $fs = get_file_storage();
        $files = $fs->get_area_files($ctx->id, 'local_orgbranding', 'orglogo', $catid, 'itemid, filename', false);
        if (!$files) return null;

        $f = reset($files);
        return \moodle_url::make_pluginfile_url(
            $ctx->id, 'local_orgbranding', 'orglogo', $catid, $f->get_filepath(), $f->get_filename()
        );
    }
}
