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

namespace theme_nit\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Applies a homepage template ({@see homepage_templates}) to the Site-home page.
 *
 * For each of the nine sections it finds the nit_section block that currently
 * plays that role (by content signature) and swaps its HTML for the chosen
 * template's section file; a section with no existing block is created in the
 * right full-width region. The footer is forced to the bottom region. Existing
 * blocks keep their region/weight so an owner's arrangement survives a re-apply.
 *
 * NOTE: applying REPLACES a section's HTML, so any images/copy the owner already
 * pasted into that section are overwritten. The intended order is: NIT picks the
 * template first, then the owner applies images + brand data.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_applier {

    /**
     * Apply the template with the given id.
     *
     * @param string $id      template id (t1..t10)
     * @param array|null $log out: human-readable per-section actions taken
     * @return bool true on success
     * @throws \moodle_exception when the id is unknown
     */
    public static function apply(string $id, ?array &$log = null): bool {
        global $DB;

        $log = [];
        if (!homepage_templates::exists($id)) {
            throw new \moodle_exception('invalidtemplate', 'theme_nit', '', $id);
        }

        // All nit_section blocks on the Site-home page, decoded once.
        $blocks = $DB->get_records('block_instances', [
            'blockname' => 'nit_section', 'pagetypepattern' => 'site-index',
        ]);
        $decoded = [];
        foreach ($blocks as $bi) {
            $decoded[$bi->id] = self::config_of($bi);
        }
        // A prototype row to clone context/page fields from when creating blocks.
        $proto = $blocks ? reset($blocks) : null;

        $claimed = [];
        foreach (homepage_templates::sections() as $section) {
            $html = self::read_html($id, $section['file']);
            if ($html === null) {
                $log[] = $section['key'] . ': skipped (template file missing)';
                continue;
            }

            $target = self::match_block($blocks, $decoded, $claimed, $section['signatures']);
            if ($target) {
                $claimed[$target->id] = true;
                self::write_config($target, $decoded[$target->id], $html);
                if ($section['key'] === 'footer') {
                    \theme_nit\local\editor::footer_to_bottom($target);
                }
                $log[] = $section['key'] . ': updated block #' . $target->id;
            } else {
                $new = self::create_block($section, $html, $proto);
                $claimed[$new->id] = true;
                $blocks[$new->id] = $new;               // so later sections can clone from it too.
                $decoded[$new->id] = self::config_of($new);
                if (!$proto) {
                    $proto = $new;
                }
                $log[] = $section['key'] . ': created block #' . $new->id . ' in ' . $section['region'];
            }
        }

        set_config(homepage_templates::CONFIG_KEY, $id, 'theme_nit');
        purge_all_caches();
        return true;
    }

    /** Decode a block's configdata into a stdClass (empty object when absent). */
    private static function config_of(\stdClass $bi): \stdClass {
        $cfg = $bi->configdata ? @unserialize(base64_decode($bi->configdata)) : null;
        return is_object($cfg) ? $cfg : new \stdClass();
    }

    /** The stored HTML of a section block, whatever mode it was saved in. */
    private static function html_of(\stdClass $cfg): string {
        return (string) ($cfg->htmltext ?? $cfg->visualtext ?? $cfg->text ?? '');
    }

    /**
     * Find the first not-yet-claimed block whose HTML contains any of $signatures.
     *
     * @param array $blocks  id => block_instances row
     * @param array $decoded id => config stdClass
     * @param array $claimed id => true for blocks already taken this run
     * @param string[] $signatures
     */
    private static function match_block(array $blocks, array $decoded, array $claimed, array $signatures): ?\stdClass {
        foreach ($blocks as $bi) {
            if (!empty($claimed[$bi->id])) {
                continue;
            }
            $html = self::html_of($decoded[$bi->id]);
            foreach ($signatures as $sig) {
                if ($html !== '' && strpos($html, $sig) !== false) {
                    return $bi;
                }
            }
        }
        return null;
    }

    /** Absolute path -> file contents, or null when the file is absent/empty. */
    private static function read_html(string $id, string $file): ?string {
        $path = homepage_templates::dir($id) . '/' . $file;
        if (!is_readable($path)) {
            return null;
        }
        $html = file_get_contents($path);
        return ($html === false || trim($html) === '') ? null : $html;
    }

    /**
     * Write new HTML into an existing block, forcing full-width / no-chrome so
     * the section spans the page like the design. Does not purge caches (the
     * caller purges once at the end).
     */
    private static function write_config(\stdClass $bi, \stdClass $cfg, string $html): void {
        global $DB;
        $cfg->mode = 'html';
        $cfg->htmltext = $html;
        $cfg->width = 'full';
        $cfg->plain = 1;
        $cfg->showtitle = 0;
        $bi->configdata = base64_encode(serialize($cfg));
        $bi->timemodified = time();
        $DB->update_record('block_instances', $bi);
    }

    /**
     * Create a new nit_section block for a section, cloning the page/context
     * fields from an existing sibling ($proto) or falling back to the Site-home
     * course context.
     *
     * @param array $section  a homepage_templates::sections() entry
     * @param string $html    the section HTML
     * @param \stdClass|null $proto an existing block_instances row to clone from
     * @return \stdClass the inserted block_instances row (with id)
     */
    private static function create_block(array $section, string $html, ?\stdClass $proto): \stdClass {
        global $DB;

        $cfg = new \stdClass();
        $cfg->mode = 'html';
        $cfg->htmltext = $html;
        $cfg->width = 'full';
        $cfg->plain = 1;
        $cfg->align = 'stretch';
        $cfg->showtitle = 0;

        $bi = new \stdClass();
        $bi->blockname = 'nit_section';
        if ($proto) {
            $bi->parentcontextid  = $proto->parentcontextid;
            $bi->showinsubcontexts = $proto->showinsubcontexts;
            $bi->requiredbytheme  = $proto->requiredbytheme;
            $bi->subpagepattern   = $proto->subpagepattern;
        } else {
            // Standard Site-home block parent: the course context of the site.
            $bi->parentcontextid  = \context_course::instance(SITEID)->id;
            $bi->showinsubcontexts = 1;
            $bi->requiredbytheme  = 0;
            $bi->subpagepattern   = null;
        }
        $bi->pagetypepattern = 'site-index';
        $bi->defaultregion   = $section['region'];
        $bi->defaultweight   = $section['weight'];
        $bi->configdata      = base64_encode(serialize($cfg));
        $bi->timecreated     = time();
        $bi->timemodified    = time();

        $bi->id = $DB->insert_record('block_instances', $bi);
        // Materialise the block context so rendering/file handling behaves.
        \context_block::instance($bi->id);
        return $bi;
    }
}
