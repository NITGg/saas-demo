<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Build (or inspect) the read-only guest-browsing token that getsettings.php publishes.
 *
 * The token is readable by anyone who requests the public settings feed, so it must
 * belong to a dedicated account that can do nothing but the pre-login reads the app
 * needs. This script sets up exactly that, idempotently:
 *
 *   1. a service (default shortname `nit_app_guest`) restricted to authorised users and
 *      carrying only the functions listed below (or in --functions);
 *   2. a user (default username `appguest`, auth `manual`, random unknown password);
 *   3. a system role `nit_app_guest` granting only `webservice/rest:use`, assigned to it;
 *   4. the user authorised on the service, and a permanent token for the pair.
 *
 * Usage (inside the container, from the Moodle code root):
 *   php public/local/multitopics/cli/app_guest_token.php                # report only
 *   php public/local/multitopics/cli/app_guest_token.php --create       # build what is missing
 *   php public/local/multitopics/cli/app_guest_token.php --create --save
 *        # ...and store the token in the Mobile app settings page (admin_token)
 *   php public/local/multitopics/cli/app_guest_token.php --create --functions=a,b,c
 *        # replace the default function list (the app team can send the full one)
 *   php public/local/multitopics/cli/app_guest_token.php --create --userid=22
 *        # the app already browses on an existing account: adopt it (role, service,
 *        # token) instead of creating "appguest" — its current token keeps working
 *
 * Without --create nothing is written.
 *
 * Pricing needs nothing from this account beyond an EMPTY profile country: a member
 * without one is priced like a visitor (IP → the app's country hint → the Default
 * price), which is exactly what the phone in a stranger's hand should get.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

[$options, $unrecognized] = cli_get_params(
    ['service' => 'nit_app_guest', 'username' => 'appguest', 'userid' => 0, 'functions' => '',
     'create' => false, 'save' => false, 'help' => false],
    ['h' => 'help']
);

if ($options['help']) {
    echo "Build or inspect the read-only guest-browsing token published by getsettings.php.\n\n"
        . "Options:\n"
        . "  --service=SHORTNAME   external service shortname (default nit_app_guest)\n"
        . "  --username=NAME       the guest account (default appguest)\n"
        . "  --userid=ID           adopt an existing account instead (wins over --username)\n"
        . "  --functions=a,b,c     replace the default function list\n"
        . "  --create              create whatever is missing (otherwise report only)\n"
        . "  --save                also store the token as local_multitopics/admin_token\n";
    exit(0);
}

/**
 * The functions the app calls before sign-in. Reads only — nothing here creates,
 * updates or deletes users, enrolments or payments. The app team can extend the
 * list with --functions.
 */
$defaultfunctions = [
    // Site handshake and catalogue.
    'core_webservice_get_site_info',
    'core_course_get_categories',
    'core_course_get_courses_by_field',
    'core_course_search_courses',
    // Ours.
    'local_payments_get_courses_with_pricing',
    'local_nit_category_search',
    'local_nit_category_get_home_hero',
    // The course page's lesson list, every lesson locked for a guest but the free
    // previews. Not get_saved_courses / set_course_saved: saving needs an account.
    'local_nit_category_get_course_lessons',
    'local_nit_commerce_get_available_coupons',
    'local_nit_commerce_preview_discount',
    'local_nit_subscriptions_get_available_subscriptions',
    'local_nit_subscriptions_get_my_subscriptions',
    'local_academy_get_my_certificates',
    // Sign-up / public pages.
    'local_profilefields_get_signup_form',
    'local_profilefields_get_profile_fields',
    'local_profilefields_signup_user',
    'local_profilefields_resend_confirmation',
    'local_profilefields_get_policy_documents',
    'local_profilefields_get_static_pages',
    'local_profilefields_get_static_page',
    'local_profilefields_get_footer',
    // Finishing a Google sign-up. An OAuth2 account skipped the sign-up form, so
    // the app shows /local/profilefields/complete.php's screen — and it builds
    // that screen from these two reads (the field schema, and what is still
    // outstanding). Without them here the token answers accessexception and the
    // completion screen never appears. Both are reads; the SAVE goes through the
    // signed-in user's own token (local_profilefields_update_profile), so that
    // write stays off this public guest service.
    'local_profilefields_get_profile_form',
    'local_profilefields_get_completion_status',
];

$wanted = $options['functions'] !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $options['functions']))))
    : $defaultfunctions;

$create = !empty($options['create']);
$webservice = new webservice();
$systemcontext = context_system::instance();

$say = static function (string $status, string $line): void {
    echo str_pad("[$status]", 10) . $line . "\n";
};

// ── 0. Web services must be on, with the REST protocol ─────────────────────
if (empty($CFG->enablewebservices)) {
    $say('MISSING', 'Web services are disabled (Site administration → General → Advanced features).');
}
if (!in_array('rest', explode(',', (string) $CFG->webserviceprotocols), true)) {
    $say('MISSING', 'The REST protocol is not enabled (Site administration → Server → Web services → Manage protocols).');
}

// ── 1. Functions that do not exist cannot be added ─────────────────────────
$known = $DB->get_fieldset_select('external_functions', 'name', '');
$unknown = array_diff($wanted, $known);
foreach ($unknown as $name) {
    $say('WARN', "$name is not a registered external function - skipped (plugin not installed or upgrade not run?).");
}
$wanted = array_values(array_intersect($wanted, $known));

// ── 2. The service ─────────────────────────────────────────────────────────
$service = $DB->get_record('external_services', ['shortname' => $options['service']]);
if (!$service) {
    if ($create) {
        $service = (object) [
            'name' => 'NIT mobile app - guest browsing',
            'shortname' => $options['service'],
            'enabled' => 1,
            'restrictedusers' => 1,
            'downloadfiles' => 1,
            'uploadfiles' => 0,
            'component' => null,
        ];
        $service->id = $webservice->add_external_service($service);
        $say('CREATED', "service {$options['service']} (id {$service->id})");
    } else {
        $say('MISSING', "service {$options['service']}");
    }
} else {
    $say('OK', "service {$options['service']} (id {$service->id}, "
        . ($service->enabled ? 'enabled' : 'DISABLED') . ', '
        . ($service->restrictedusers ? 'authorised users only' : 'ALL users') . ')');
    if ($create && (!$service->enabled || !$service->restrictedusers)) {
        $service->enabled = 1;
        $service->restrictedusers = 1;
        $webservice->update_external_service($service);
        $say('FIXED', 'service enabled and restricted to authorised users');
    }
}

if ($service) {
    $have = $DB->get_fieldset_select('external_services_functions', 'functionname',
        'externalserviceid = ?', [$service->id]);
    foreach ($wanted as $name) {
        if (in_array($name, $have, true)) {
            $say('OK', "  $name");
        } else if ($create) {
            $webservice->add_external_function_to_service($name, $service->id);
            $say('ADDED', "  $name");
        } else {
            $say('MISSING', "  $name");
        }
    }
    foreach (array_diff($have, $wanted) as $name) {
        $say('EXTRA', "  $name is on the service but not in the list (left as is - remove it by hand if unwanted)");
    }
}

// ── 3. The account ─────────────────────────────────────────────────────────
// By id when the app is already browsing on some account (whatever it is called),
// otherwise by username — which is also the name a missing account is created under.
$userid = (int) $options['userid'];
if ($userid > 0) {
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
    if (!$user) {
        cli_error("No user with id $userid.");
    }
    $options['username'] = $user->username;
} else {
    $user = $DB->get_record('user', ['username' => $options['username'], 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
}
if (!$user) {
    if ($create) {
        $user = (object) [
            'username' => $options['username'],
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname' => 'Mobile app',
            'lastname' => 'guest',
            'email' => $options['username'] . '@' . parse_url($CFG->wwwroot, PHP_URL_HOST),
            'lang' => $CFG->lang,
            'description' => 'Read-only account behind the public guest-browsing token. Do not sign in as it.',
        ];
        $user->id = user_create_user($user, false, false);
        // Never meant to be signed in as: set a password nobody knows.
        update_internal_user_password(core_user::get_user($user->id), random_string(40), false);
        $say('CREATED', "user {$options['username']} (id {$user->id})");
    } else {
        $say('MISSING', "user {$options['username']}");
    }
} else {
    $flags = [];
    if ($user->suspended) {
        $flags[] = 'SUSPENDED';
    }
    if ($user->auth === 'nologin') {
        $flags[] = 'auth=nologin (web services refuse it)';
    }
    if (is_siteadmin($user)) {
        $flags[] = 'IS A SITE ADMIN - do not publish a token for this account';
    }
    $say(empty($flags) ? 'OK' : 'WARN', "user {$options['username']} (id {$user->id})"
        . (empty($flags) ? '' : ': ' . implode(', ', $flags)));
}

// ── 3b. Required custom profile fields ─────────────────────────────────────
// A web-service call runs require_login() in strict mode, which refuses any
// account whose required profile fields (the sign-up phone, etc.) are empty
// with "usernotfullysetup". The account never appears anywhere, so a
// placeholder per empty required field is all it needs.
if ($user) {
    require_once($CFG->dirroot . '/user/profile/lib.php');
    $empty = [];
    foreach (profile_get_user_fields_with_data($user->id) as $field) {
        if ($field->is_required() && !$field->is_locked() && $field->is_empty()
                && $field->get_field_config_for_external()['visible']) {
            $empty[] = $field;
        }
    }
    foreach ($empty as $field) {
        $shortname = $field->field->shortname;
        if ($create) {
            $DB->insert_record('user_info_data', (object) [
                'userid' => $user->id,
                'fieldid' => $field->fieldid,
                'data' => $field->field->datatype === 'phone' ? 'EG:0000000000' : 'n/a',
                'dataformat' => 0,
            ]);
            $say('FILLED', "required profile field '$shortname' given a placeholder");
        } else {
            $say('MISSING', "required profile field '$shortname' is empty - web services would refuse this account");
        }
    }
}

// ── 4. A role carrying only webservice/rest:use, assigned in the system context ─
$roleid = $DB->get_field('role', 'id', ['shortname' => 'nit_app_guest']);
if (!$roleid) {
    if ($create) {
        $roleid = create_role('Mobile app guest (REST only)', 'nit_app_guest',
            'Grants webservice/rest:use and nothing else. Held by the guest-browsing account.');
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        assign_capability('webservice/rest:use', CAP_ALLOW, $roleid, $systemcontext->id, true);
        $say('CREATED', "role nit_app_guest (id $roleid) with webservice/rest:use");
    } else {
        $say('MISSING', 'role nit_app_guest');
    }
} else {
    $say('OK', "role nit_app_guest (id $roleid)");
}

if ($user && $roleid) {
    if (user_has_role_assignment($user->id, $roleid, $systemcontext->id)) {
        $say('OK', 'role assigned to the user');
    } else if ($create) {
        role_assign($roleid, $user->id, $systemcontext->id);
        $say('ADDED', 'role assigned to the user');
    } else {
        $say('MISSING', 'role assignment');
    }
}

if ($user && !has_capability('webservice/rest:use', $systemcontext, $user)) {
    $say($create ? 'WARN' : 'MISSING', 'the user cannot use REST yet (webservice/rest:use)'
        . ($create ? ' - the assignment above should fix it on the next request' : ''));
}

// ── 5. Authorise the user on the service and issue the token ───────────────
$token = '';
if ($service && $user) {
    if (!$DB->record_exists('external_services_users', ['externalserviceid' => $service->id, 'userid' => $user->id])) {
        if ($create) {
            $webservice->add_ws_authorised_user((object) ['externalserviceid' => $service->id, 'userid' => $user->id]);
            $say('ADDED', 'user authorised on the service');
        } else {
            $say('MISSING', 'user is not authorised on the service');
        }
    } else {
        $say('OK', 'user authorised on the service');
    }

    $existing = $DB->get_records('external_tokens', ['externalserviceid' => $service->id, 'userid' => $user->id,
        'tokentype' => EXTERNAL_TOKEN_PERMANENT], 'id ASC');
    if ($existing) {
        $token = reset($existing)->token;
        $say('OK', "token exists: $token");
    } else if ($create) {
        $token = external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $user->id, $systemcontext);
        $say('CREATED', "token: $token");
    } else {
        $say('MISSING', 'token');
    }
}

// ── 6. Publish it ──────────────────────────────────────────────────────────
$published = (string) get_config('local_multitopics', 'admin_token');
if ($token !== '' && $published === $token) {
    $say('OK', 'getsettings.php already publishes this token');
} else if ($token !== '' && !empty($options['save'])) {
    set_config('admin_token', $token, 'local_multitopics');
    $say('SAVED', 'stored as local_multitopics/admin_token - getsettings.php now publishes it');
} else if ($token !== '') {
    $say('TODO', 'paste the token into Site administration → Plugins → Local plugins → Mobile app settings, or re-run with --save');
}

if (!$create) {
    echo "\nReport only. Re-run with --create to build what is marked MISSING.\n";
}
