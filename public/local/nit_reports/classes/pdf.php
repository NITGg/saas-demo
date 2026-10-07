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

namespace local_nit_reports;

/**
 * A report as a PDF: landscape A4, the site and report name, when it was made and
 * with which filters, the number cards, then the table (its header repeated on
 * every page). Right-to-left with an Arabic-capable font when the page language
 * is right-to-left (FreeSerif ships with Moodle's TCPDF and covers Arabic).
 *
 * @package    local_nit_reports
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf {

    /** Most rows put in one PDF (a bigger table belongs in Excel). */
    public const MAX_ROWS = 3000;

    /**
     * Build and send the PDF.
     *
     * @param string $filename without extension
     * @param string $title
     * @param string $filterline
     * @param array $cards [['label', 'value'], …]
     * @param array $columns key => label
     * @param array $rows
     * @param array $help column key => what it means (listed after the table)
     */
    public static function download(string $filename, string $title, string $filterline, array $cards, array $columns,
            array $rows, array $help = []): void {
        global $CFG, $SITE;
        require_once($CFG->libdir . '/pdflib.php');

        $truncated = count($rows) > self::MAX_ROWS;
        $rows = array_slice($rows, 0, self::MAX_ROWS);

        $doc = new \pdf('L', 'mm', 'A4', true, 'UTF-8');
        $doc->SetCreator(format_string($SITE->fullname));
        $doc->SetTitle($title);
        $doc->setPrintHeader(false);
        $doc->setFooterFont(['freeserif', '', 8]);
        $doc->SetMargins(10, 10, 10);
        $doc->SetAutoPageBreak(true, 12);
        $doc->setRTL(right_to_left());
        $doc->SetFont('freeserif', '', 9);
        $doc->AddPage();

        $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $s = fn($k, $a = null) => get_string($k, 'local_nit_reports', $a);
        $html = '<h2>' . $e($title) . '</h2>'
            . '<div style="color:#555555;">' . $e(format_string($SITE->fullname)) . ' — '
            . $e($s('generatedat', userdate(time(), get_string('strftimedatetimeshort', 'langconfig')))) . '<br>'
            . $e($filterline) . '</div><br>';

        if ($cards) {
            $html .= '<table cellpadding="5" border="1" style="border-color:#cccccc;"><tr>';
            foreach ($cards as $card) {
                $html .= '<td style="background-color:#f4f6f8;"><span style="color:#555555;">' . $e($card['label'])
                    . '</span><br><b style="font-size:12pt;">' . $e($card['value']) . '</b></td>';
            }
            $html .= '</tr></table><br>';
        }

        $html .= '<table cellpadding="4" border="1" style="border-color:#bbbbbb;"><thead><tr style="background-color:#e9ecef;">';
        foreach ($columns as $label) {
            $html .= '<th><b>' . $e($label) . '</b></th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $i => $row) {
            $html .= '<tr' . ($i % 2 ? ' style="background-color:#fafafa;"' : '') . '>';
            foreach (array_keys($columns) as $col) {
                $html .= '<td>' . $e($row[$col] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
        if (!$rows) {
            $html .= '<tr><td colspan="' . count($columns) . '">' . $e($s('norows')) . '</td></tr>';
        }
        $html .= '</tbody></table>';
        if ($truncated) {
            $html .= '<p>' . $e($s('pdftruncated', self::MAX_ROWS)) . '</p>';
        }

        // What the columns mean.
        $help = array_intersect_key($help, $columns);
        if ($help) {
            $html .= '<br><h3>' . $e($s('columnmeanings')) . '</h3>'
                . '<table cellpadding="4" border="1" style="border-color:#cccccc;">';
            foreach ($help as $col => $text) {
                $html .= '<tr><td width="20%" style="background-color:#f4f6f8;"><b>' . $e($columns[$col]) . '</b></td>'
                    . '<td width="80%">' . $e($text) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $doc->writeHTML($html, true, false, true, false, '');
        $doc->Output($filename . '.pdf', 'D');
        exit;
    }
}
