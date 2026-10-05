<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_academy_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026070404) {
        // Password-reset OTP table.
        $table = new xmldb_table('academy_password_otps');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('otphash', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('resettoken', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('verified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('email_idx', XMLDB_INDEX_NOTUNIQUE, ['email']);
            $table->add_index('resettoken_idx', XMLDB_INDEX_NOTUNIQUE, ['resettoken']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026070404, 'local', 'academy');
    }

    if ($oldversion < 2026100400) {
        // Course settings: group "custom fields" with the "is-special" checkbox
        // (home page "selected courses").
        \local_academy\local\course_fields::ensure();
        upgrade_plugin_savepoint(true, 2026100400, 'local', 'academy');
    }

    if ($oldversion < 2026100401) {
        // Year / Study system / Division dropdowns on courses and students, and
        // the registration data as student profile fields.
        \local_academy\local\academic_structure::sync();
        upgrade_plugin_savepoint(true, 2026100401, 'local', 'academy');
    }

    if ($oldversion < 2026100402) {
        // Teacher title (the line under the name on the home page teacher cards).
        \local_academy\local\user_fields::ensure();
        upgrade_plugin_savepoint(true, 2026100402, 'local', 'academy');
    }

    if ($oldversion < 2026100403) {
        // Years are the course categories (REQUIREMENTS: "Categories = Year
        // only"): drop the course "Year" dropdown and give the student "Year"
        // dropdown the categories.
        \local_academy\local\academic_structure::remove_old_course_year();
        \local_academy\local\academic_structure::sync();
        upgrade_plugin_savepoint(true, 2026100403, 'local', 'academy');
    }

    if ($oldversion < 2026100404) {
        // Arabic + English ({mlang}) for the profile groups, field names and fixed
        // options, and for the default study systems / divisions — answers kept.
        \local_academy\local\user_fields::translate();
        \local_academy\local\academic_structure::translate();
        // local_nit_finance's teacher-percent field sits in our "teacher" group.
        $ml = \local_academy\local\user_fields::ml('نسبة المدرس من الأرباح %', 'Teacher share of earnings %');
        $DB->set_field('user_info_field', 'name', $ml, ['shortname' => 'teacherpercent', 'name' => 'نسبة المدرس من الأرباح %']);
        upgrade_plugin_savepoint(true, 2026100404, 'local', 'academy');
    }

    if ($oldversion < 2026100500) {
        // Production lacked the dev site settings: the multilang2 filter (raw
        // {mlang} tags on the register page) and the site home page (the logo
        // went to /my/).
        \local_academy\local\site_defaults::apply();
        upgrade_plugin_savepoint(true, 2026100500, 'local', 'academy');
    }

    if ($oldversion < 2026100501) {
        // Production had "Force users to log in" on: "/" went to the login page.
        \local_academy\local\site_defaults::apply();
        upgrade_plugin_savepoint(true, 2026100501, 'local', 'academy');
    }

    if ($oldversion < 2026100502) {
        // Production had no Arabic language pack: no AR/EN menu in the navbar.
        \local_academy\local\site_defaults::ensure_arabic();
        upgrade_plugin_savepoint(true, 2026100502, 'local', 'academy');
    }

    if ($oldversion < 2026100503) {
        // Lesson times were 2–3 hours off (site timezone Europe/London): Cairo. And new
        // students confirm their email first, like Moodle's email self-registration.
        \local_academy\local\site_defaults::apply();
        \local_academy\local\site_defaults::email_confirmation();
        upgrade_plugin_savepoint(true, 2026100503, 'local', 'academy');
    }

    if ($oldversion < 2026100504) {
        // Course field "Subject" (المادة): the home subject cards and the subject page.
        \local_academy\local\course_fields::ensure_subject();
        upgrade_plugin_savepoint(true, 2026100504, 'local', 'academy');
    }

    return true;
}
