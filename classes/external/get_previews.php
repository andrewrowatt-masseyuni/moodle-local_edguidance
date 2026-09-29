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

namespace local_edguidance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_edguidance\api;
use local_edguidance\dismissed;
use local_edguidance\output\block;

/**
 * Render guidance blocks as the page shows them, for the editor to preview.
 *
 * The editor asks for every token in its text at once, and puts what comes back beside each token
 * rather than in it (see tiny_edguidance/previews), so none of it is ever saved into the text.
 *
 * Blocks are looked up as the guidance form looks them up - within the editor's context, by
 * api::get_embeds() - so a token copied in from elsewhere previews as the notice the filter will
 * show there, and a draft on the "add an activity" form previews at all. That last is why this
 * needs manage rather than view: drafts are only ever seen by whoever is writing them.
 *
 * A block the current user has dismissed is rendered in full and flagged. The editor hides it unless
 * the teacher asks to see dismissed guidance, which it can then do without asking again.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_previews extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context of the editor the blocks are embedded in'),
            'keys' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUM, 'A block\'s key, from its token')
            ),
            'sectionid' => new external_value(
                PARAM_INT,
                'The section whose summary the blocks are embedded in, for an editor in a course context',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Render the previews.
     *
     * @param int $contextid The editor's context.
     * @param string[] $keys The blocks' keys.
     * @param int $sectionid The section whose summary is being edited, or 0.
     * @return array One preview per distinct key, in the order asked.
     */
    public static function execute(int $contextid, array $keys, int $sectionid = 0): array {
        ['contextid' => $contextid, 'keys' => $keys, 'sectionid' => $sectionid] = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'keys' => $keys, 'sectionid' => $sectionid]
        );

        $context = \context::instance_by_id($contextid);
        self::validate_context($context);
        require_capability('local/edguidance:manage', $context);

        $keys = array_values(array_unique($keys));
        $rows = api::get_embeds($context, $keys, $sectionid);

        $previews = [];
        foreach ($keys as $key) {
            $row = $rows[$key] ?? null;
            $previews[] = [
                'key' => $key,
                'html' => block::render_preview($row),
                'dismissed' => $row && dismissed::is_dismissed((int)$row->id),
            ];
        }

        return $previews;
    }

    /**
     * Return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUM, 'The block\'s key'),
                'html' => new external_value(PARAM_RAW, 'The block as the page shows it, or a notice saying why it will not show'),
                'dismissed' => new external_value(PARAM_BOOL, 'Whether the current user has dismissed the block'),
            ])
        );
    }
}
