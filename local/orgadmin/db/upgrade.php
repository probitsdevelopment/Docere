
<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_orgadmin_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Bump this number each time you change schema.
    if ($oldversion < 2025100700) {

        // 1) local_questions
        $table = new xmldb_table('local_questions');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('qid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('qtitle', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('expectation', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('programming_language', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
            $table->add_field('max_marks', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('uniq_qid', XMLDB_INDEX_UNIQUE, ['qid']);
            $dbman->create_table($table);
        } else {
            $index = new xmldb_index('uniq_qid', XMLDB_INDEX_UNIQUE, ['qid']);
            if (!$dbman->index_exists($table, $index)) { $dbman->add_index($table, $index); }
        }

        // 2) local_question_details
        $table = new xmldb_table('local_question_details');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('qdetailid', XMLDB_TYPE_CHAR, '50', null, null);
            $table->add_field('qdetailtitle', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('max_marks', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('question_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_details_question', XMLDB_INDEX_NOTUNIQUE, ['question_id']);
            $dbman->create_table($table);
        } else {
            $index = new xmldb_index('idx_details_question', XMLDB_INDEX_NOTUNIQUE, ['question_id']);
            if (!$dbman->index_exists($table, $index)) { $dbman->add_index($table, $index); }
        }

        // 3) local_question_scores
        $table = new xmldb_table('local_question_scores');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('question_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('detail_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('marks', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_field('attempt_no', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_scores_lookup', XMLDB_INDEX_NOTUNIQUE, ['question_id','userid','attempt_no']);
            $table->add_index('idx_scores_detail', XMLDB_INDEX_NOTUNIQUE, ['detail_id']);
            $table->add_index('uniq_one_score_per_attempt', XMLDB_INDEX_UNIQUE, ['question_id','detail_id','userid','attempt_no']);
            $dbman->create_table($table);
        } else {
            $i1 = new xmldb_index('idx_scores_lookup', XMLDB_INDEX_NOTUNIQUE, ['question_id','userid','attempt_no']);
            if (!$dbman->index_exists($table, $i1)) { $dbman->add_index($table, $i1); }
            $i2 = new xmldb_index('idx_scores_detail', XMLDB_INDEX_NOTUNIQUE, ['detail_id']);
            if (!$dbman->index_exists($table, $i2)) { $dbman->add_index($table, $i2); }
            $i3 = new xmldb_index('uniq_one_score_per_attempt', XMLDB_INDEX_UNIQUE, ['question_id','detail_id','userid','attempt_no']);
            if (!$dbman->index_exists($table, $i3)) { $dbman->add_index($table, $i3); }
        }

        upgrade_plugin_savepoint(true, 2025100700, 'local', 'orgadmin');
    }

    return true;
}
