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

namespace theme_nit\output;

use local_nit_core\output\view_model;

/**
 * NIT core renderer — the "how to show" half of the rendering seam.
 *
 * Picked up automatically via theme_overridden_renderer_factory (set in
 * config.php). Extends Boost's renderer (NIT is a Boost child) so all of
 * Boost's renderer methods are inherited. Deliberately thin: it renders SDK
 * view-models and holds no business logic (Reference Architecture Rule 1).
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /**
     * Render a NIT view-model through its (theme-overridable) template.
     *
     * @param view_model $viewmodel the view-model to render
     * @return string HTML
     */
    public function render_nit(view_model $viewmodel): string {
        return $this->render_from_template(
            $viewmodel->template_name(),
            $viewmodel->export_for_template($this)
        );
    }

    /**
     * Render the standalone navbar language menu for every user.
     *
     * Core exposes the standalone language menu (primary::export_for_template)
     * only to logged-out/guest users; once logged in, the switcher is folded
     * into the user menu. The NIT navbar keeps a persistent language button
     * beside the brand (matching the legacy site), so it builds the menu here
     * regardless of login state. Returns '' when the menu should not show
     * (language menu disabled, or a single installed language).
     *
     * @return string HTML, or '' when there is nothing to show
     */
    public function navbar_language_menu(): string {
        $languagemenu = new \core\output\language_menu($this->page);
        $langmenu = $languagemenu->export_for_template($this);
        if (empty($langmenu)) {
            return '';
        }
        return $this->render_from_template('theme_boost/language_menu', $langmenu);
    }

    /**
     * Owner-facing "Upgrade" link for the gear dropdown → the control-plane
     * account page, anchored to THIS academy so the client lands on it directly.
     *
     * The dashboard origin can't be derived from the academy's own domain, so
     * the control plane pushes it as theme_nit/accounturl (+ theme_nit/academyslug)
     * at provision / apply-settings time; the slug falls back to the first label
     * of $CFG->wwwroot. Returns '' for non-owners or when no base is configured,
     * so the row simply doesn't render.
     *
     * @return string HTML anchor, or '' when it should not show
     */
    public function nit_account_link(): string {
        global $CFG;
        if (!\theme_nit\local\editor::can_edit()) {
            return '';
        }
        $base = rtrim((string) get_config('theme_nit', 'accounturl'), '/');
        if ($base === '') {
            return '';
        }
        // Hide when the control plane has marked this academy as not upgradable
        // (already on the top paid tier, or no higher tier exists). The flag rides
        // in local_license/definition; an absent flag (legacy) leaves it shown.
        $def = json_decode((string) get_config('local_license', 'definition'), true);
        if (is_array($def) && array_key_exists('upgradable', $def) && $def['upgradable'] === false) {
            return '';
        }
        $slug = trim((string) get_config('theme_nit', 'academyslug'));
        if ($slug === '') {
            $host = (string) parse_url($CFG->wwwroot, PHP_URL_HOST);
            $slug = $host !== '' ? explode('.', $host)[0] : '';
        }
        $lang = (current_language() === 'ar') ? 'ar' : 'en';
        $url = $base . '/' . $lang . '/account' . ($slug !== '' ? '#' . rawurlencode($slug) : '');
        return \html_writer::link($url, get_string('upgrade_link', 'theme_nit'), [
            'class'  => 'dropdown-item nit-navmenu-child nit-navmenu-upgrade',
            'target' => '_blank',
            'rel'    => 'noopener',
        ]);
    }
}
