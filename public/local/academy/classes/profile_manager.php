<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Current-user profile for the app, with ready-to-use (token-embedded) image URLs.
 */
class profile_manager {

    /**
     * The signed-in user's basic profile. The image URLs are already tokenized
     * (webservice/pluginfile.php + token) so the client can load them directly —
     * no URL rewriting needed on the app side.
     *
     * @param \stdClass $user  the token's user (as set by token_auth::validate)
     * @param string $token    the caller's web-service token
     */
    public static function get_my_profile(\stdClass $user, string $token): array {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());

        $big = new \user_picture($user);
        $big->size = 100; // f3 / large
        $small = new \user_picture($user);
        $small->size = 35; // f2 / small

        // Auth method: lets the app show the "change password" screen only for
        // password-based (manual/email) accounts and hide it for Google/OAuth2.
        $auth = $user->auth ?? 'manual';
        $canchangepassword = false;
        if (($authplugin = get_auth_plugin($auth))) {
            // True only for internal (password) auths — false for oauth2/google.
            $canchangepassword = $authplugin->can_change_password() && $authplugin->is_internal();
        }

        return [
            'userid'               => (int) $user->id,
            'username'             => $user->username ?? '',
            'fullname'             => fullname($user),
            'firstname'            => $user->firstname ?? '',
            'lastname'             => $user->lastname ?? '',
            'email'                => $user->email ?? '',
            'auth'                 => $auth,
            'isgoogle'             => ($auth === 'oauth2'),
            'canchangepassword'    => (bool) $canchangepassword,
            'profileimageurl'      => ws_files::tokenize($big->get_url($page)->out(false), $token),
            'profileimageurlsmall' => ws_files::tokenize($small->get_url($page)->out(false), $token),
        ];
    }

    /**
     * The academy profile fields as a form spec for the app: one entry per field
     * with its group, label, type and (for dropdowns) the options. A dropdown's
     * `value` is what must be sent back; `label` is for display. Divisions carry
     * the study systems they belong to so the app can link the two dropdowns.
     *
     * @param array $values the user's current values (shortname => raw text), or []
     * @return array
     */
    public static function field_specs(array $values = []): array {
        global $DB;
        $records = $DB->get_records_list('user_info_field', 'shortname',
            array_keys(\local_academy\local\user_fields::definitions()), '', 'shortname, id, name, datatype, categoryid');
        $categories = $DB->get_records_menu('user_info_category', null, '', 'id, name');
        $label = static fn(string $s): string => format_string($s, true,
            ['context' => \context_system::instance(), 'escape' => false]);

        // Division => the systems it belongs to.
        $divsystems = [];
        foreach (\local_academy\local\academic_structure::map() as $system => $divisions) {
            foreach ($divisions as $division) {
                $divsystems[$division][] = $system;
            }
        }

        $out = [];
        foreach (\local_academy\local\user_fields::definitions() as $shortname => $def) {
            $rec = $records[$shortname] ?? null;
            if (!$rec) {
                continue; // Field not installed on this academy.
            }
            $options = [];
            if ($rec->datatype === 'menu') {
                foreach (\local_academy\local\user_fields::menu_options($shortname) as $option) {
                    $entry = ['value' => $option, 'label' => $label($option)];
                    if ($shortname === \local_academy\local\academic_structure::USER_DIVISION) {
                        $entry['systems'] = $divsystems[$option] ?? [];
                    }
                    $options[] = $entry;
                }
            }
            $value = (string) ($values[$shortname] ?? '');
            $out[] = [
                'shortname'  => $shortname,
                'name'       => $label((string) $rec->name),
                'group'      => $def['category'],
                'groupname'  => $label((string) ($categories[$rec->categoryid] ?? '')),
                'type'       => $rec->datatype === 'menu' ? 'menu' : 'text',
                'value'      => $value,
                'valuelabel' => $value === '' ? '' : $label($value),
                'options'    => $options,
            ];
        }
        return $out;
    }

    /**
     * The signed-in user's full profile: basic data + phone, bio, language, roles
     * and every academy profile field (as a form spec with the current values).
     *
     * @param \stdClass $user
     * @param string $token
     * @return array
     */
    public static function get_full_profile(\stdClass $user, string $token): array {
        global $DB;
        $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        $data = self::get_my_profile($user, $token);
        $syscontext = \context_system::instance();
        $data += [
            'phone'       => (string) $user->phone1,
            'phone2'      => (string) $user->phone2,
            'city'        => (string) $user->city,
            'country'     => (string) $user->country,
            'bio'         => trim(html_to_text((string) $user->description, 0, false)),
            'language'    => (string) ($user->lang ?: get_config('core', 'lang')),
            'timecreated' => (int) $user->timecreated,
            'lastaccess'  => (int) $user->lastaccess,
            'hasphoto'    => !empty($user->picture),
            'roles'       => [
                'is_siteadmin' => is_siteadmin($user),
                'is_manager'   => has_capability('local/academy:manageplatform', $syscontext, $user),
                'is_teacher'   => teacher_manager::is_teacher((int) $user->id),
            ],
            'fields'      => self::field_specs(\local_academy\local\user_fields::values((int) $user->id)),
            'languages'   => self::languages(),
        ];
        return $data;
    }

    /**
     * Installed languages as [{code, name}].
     *
     * @return array
     */
    public static function languages(): array {
        $out = [];
        foreach (get_string_manager()->get_list_of_translations() as $code => $name) {
            $out[] = ['code' => $code, 'name' => $name];
        }
        return $out;
    }

    /**
     * Validate a phone number (the same rule as the web profile page).
     *
     * @param string $phone
     * @return bool
     */
    public static function valid_phone(string $phone): bool {
        return (bool) preg_match('/^[+]?[0-9\s\-()]{7,25}$/', $phone)
            && strlen(preg_replace('/\D+/', '', $phone)) >= 7;
    }

    /**
     * Update the signed-in user's profile. Only the keys present in $data change.
     * Same rules as /local/academy/profile.php: dropdowns accept only their own
     * options, a division must belong to the chosen study system, phones and the
     * 14-digit national ID are validated. The teacher title is staff-managed.
     *
     * @param int $userid
     * @param array $data keys: firstname, lastname, phone, bio, language, fields (shortname => value)
     * @throws \moodle_exception with a readable message when a value is invalid
     */
    public static function update_profile(int $userid, array $data): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $DB->get_record('user', ['id' => $userid], 'id', MUST_EXIST);
        $upd = (object) ['id' => $userid];
        foreach (['firstname', 'lastname'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = trim(clean_param((string) $data[$key], PARAM_TEXT));
                if ($value === '') {
                    throw new \moodle_exception('err_requiredfield', 'local_academy', '', $key);
                }
                $upd->$key = $value;
            }
        }
        if (array_key_exists('phone', $data)) {
            $phone = trim(clean_param((string) $data['phone'], PARAM_TEXT));
            if ($phone !== '' && !self::valid_phone($phone)) {
                throw new \moodle_exception('err_invalidphone', 'local_academy', '', 'phone');
            }
            $upd->phone1 = $phone;
        }
        if (array_key_exists('bio', $data)) {
            $upd->description = clean_param((string) $data['bio'], PARAM_TEXT);
            $upd->descriptionformat = FORMAT_HTML;
        }
        if (array_key_exists('language', $data) && (string) $data['language'] !== '') {
            $lang = clean_param((string) $data['language'], PARAM_LANG);
            if ($lang === '') {
                throw new \moodle_exception('err_invalidlanguage', 'local_academy');
            }
            $upd->lang = $lang;
        }

        // Academy profile fields.
        $custom = [];
        $defs = \local_academy\local\user_fields::definitions();
        $current = \local_academy\local\user_fields::values($userid);
        foreach ((array) ($data['fields'] ?? []) as $shortname => $value) {
            if (!isset($defs[$shortname]) || $shortname === 'teachertitle') {
                continue; // Unknown, or staff-managed.
            }
            $value = trim(clean_param((string) $value, PARAM_TEXT));
            if ($defs[$shortname]['type'] === 'menu' && $value !== ''
                    && !in_array($value, \local_academy\local\user_fields::menu_options($shortname), true)) {
                throw new \moodle_exception('err_invalidoption', 'local_academy', '', $shortname);
            }
            $custom[$shortname] = $value;
        }
        $sysfield = \local_academy\local\academic_structure::USER_SYSTEM;
        $divfield = \local_academy\local\academic_structure::USER_DIVISION;
        if (array_key_exists($sysfield, $custom) || array_key_exists($divfield, $custom)) {
            $sys = $custom[$sysfield] ?? $current[$sysfield];
            $div = $custom[$divfield] ?? $current[$divfield];
            $map = \local_academy\local\academic_structure::map();
            if ($div !== '' && ($sys === '' || !in_array($div, $map[$sys] ?? [], true))) {
                if (array_key_exists($divfield, $custom)) {
                    throw new \moodle_exception('err_divisionmismatch', 'local_academy');
                }
                $custom[$divfield] = ''; // The system changed: the old division no longer fits.
            }
        }
        foreach (['fatherphone', 'motherphone', 'parentphone'] as $key) {
            if (($custom[$key] ?? '') !== '' && !self::valid_phone($custom[$key])) {
                throw new \moodle_exception('err_invalidphone', 'local_academy', '', $key);
            }
        }
        if (($custom['nationalid'] ?? '') !== '' && !preg_match('/^[0-9]{14}$/', $custom['nationalid'])) {
            throw new \moodle_exception('err_invalidnationalid', 'local_academy');
        }

        if (count((array) $upd) > 1) {
            user_update_user($upd, false, true);
        }
        if ($custom) {
            // The guardian phone's shortname is a local_parent setting.
            $parentfield = (string) (get_config('local_parent', 'parentphonefield') ?: 'parentphone');
            if (array_key_exists('parentphone', $custom) && $parentfield !== 'parentphone') {
                $custom[$parentfield] = $custom['parentphone'];
                unset($custom['parentphone']);
            }
            // Same writer as the web profile page (stores dropdown answers as text).
            profile_save_custom_fields($userid, $custom);
        }
    }
}
