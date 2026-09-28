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

namespace local_nit_ai\quizgen;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

/**
 * qformat_xml reading from a string instead of a file.
 *
 * The importer is built for an upload: it reads a path and reports to a page.
 * Everything else about it — the qtype handling, the answer validation, the four
 * tables it knows how to write — is exactly what we want, so the file is the
 * only part replaced.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xml_importer extends \qformat_xml {

    /** @var string The XML to import. */
    protected string $source = '';

    /**
     * Set the XML this importer will read.
     *
     * @param string $xml
     * @return void
     */
    public function set_source(string $xml): void {
        $this->source = $xml;
    }

    #[\Override]
    protected function readdata($filename) {
        unset($filename);

        return explode("\n", $this->source);
    }

    #[\Override]
    public function importpreprocess() {
        return true;
    }
}
