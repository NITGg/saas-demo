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

namespace theme_nit;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting: one text edited as two fields, English and Arabic.
 *
 * Stored as one {mlang en}…{mlang}{mlang ar}…{mlang} text (multilang2 shows the
 * visitor's language through format_string); a single filled side is stored as
 * plain text, so it shows in both languages. A stored plain text opens in both fields.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_bilingualtext extends \admin_setting {

    /** @var int textarea rows; 0 = a one-line input */
    private $rows;

    /**
     * @param string $name 'plugin/name'
     * @param string $visiblename
     * @param string $description
     * @param string $defaultsetting
     * @param int $rows textarea rows; 0 = a one-line input
     */
    public function __construct(string $name, string $visiblename, string $description, string $defaultsetting,
            int $rows = 0) {
        $this->rows = $rows;
        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * The English and Arabic inputs of one text.
     *
     * @param string $nameen input name of the English side
     * @param string $namear input name of the Arabic side
     * @param array $pair ['en' => string, 'ar' => string]
     * @param int $rows textarea rows; 0 = a one-line input
     * @return string HTML
     */
    public static function fields(string $nameen, string $namear, array $pair, int $rows = 0): string {
        $html = '<div class="d-flex flex-column gap-1">';
        foreach (['en' => [$nameen, 'ltr'], 'ar' => [$namear, 'rtl']] as $lang => [$name, $dir]) {
            $label = s(get_string('bilingual_' . $lang, 'theme_nit'));
            $attrs = 'class="form-control" dir="' . $dir . '" lang="' . $lang . '" name="' . $name . '"'
                . ' placeholder="' . $label . '" aria-label="' . $label . '"';
            $html .= $rows > 0
                ? '<textarea ' . $attrs . ' rows="' . $rows . '">' . s($pair[$lang] ?? '') . '</textarea>'
                : '<input type="text" ' . $attrs . ' value="' . s($pair[$lang] ?? '') . '">';
        }
        return $html . '</div>';
    }

    /**
     * The stored text of a posted English / Arabic pair.
     *
     * @param mixed $en
     * @param mixed $ar
     * @return string
     */
    public static function join($en, $ar): string {
        return \theme_nit\local\editor::mlang_build(clean_param((string) $en, PARAM_TEXT),
            clean_param((string) $ar, PARAM_TEXT));
    }

    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * @param mixed $data ['en' => string, 'ar' => string] (or a plain string)
     * @return string '' on success, else an error message
     */
    public function write_setting($data) {
        $value = is_array($data) ? self::join($data['en'] ?? '', $data['ar'] ?? '')
            : trim(clean_param((string) $data, PARAM_TEXT));
        return $this->config_write($this->name, $value) ? '' : get_string('errorsetting', 'admin');
    }

    public function output_html($data, $query = '') {
        $pair = is_array($data) ? ['en' => $data['en'] ?? '', 'ar' => $data['ar'] ?? '']
            : \theme_nit\local\editor::mlang_parse((string) $data);
        $full = $this->get_full_name();
        $html = self::fields($full . '[en]', $full . '[ar]', $pair, $this->rows);
        $default = $this->get_defaultsetting();
        return format_admin_setting($this, $this->visiblename, $html, $this->description, false, '',
            $default !== null ? format_string($default) : null, $query);
    }
}
