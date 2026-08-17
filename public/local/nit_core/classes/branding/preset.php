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

namespace local_nit_core\branding;

/**
 * A branding preset: a bundle of semantic token defaults + font + density.
 *
 * Presets are immutable data. The resolver merges a preset with admin overrides
 * to produce the final token set.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preset {
    /**
     * @param string $key preset identifier
     * @param string $primary primary colour hex
     * @param string $font font key (sans|rounded|serif)
     * @param string $density tight|comfortable|spacious|large
     */
    public function __construct(
        /** @var string Preset identifier. */
        private string $key,
        /** @var string Primary colour hex. */
        private string $primary,
        /** @var string Font key. */
        private string $font,
        /** @var string Density key. */
        private string $density,
    ) {
    }

    /**
     * @return string preset key
     */
    public function key(): string {
        return $this->key;
    }

    /**
     * @return string primary colour hex
     */
    public function primary(): string {
        return $this->primary;
    }

    /**
     * @return string font key
     */
    public function font(): string {
        return $this->font;
    }

    /**
     * @return string density key
     */
    public function density(): string {
        return $this->density;
    }
}
