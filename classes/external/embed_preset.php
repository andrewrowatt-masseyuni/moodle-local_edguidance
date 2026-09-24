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
use core_external\external_single_structure;
use core_external\external_value;
use local_edguidance\api;

/**
 * Create a guidance block that uses a site preset, for the editor to embed.
 *
 * The one-click "Use a preset" path. There is nothing for the teacher to type, so there is no form:
 * the editor calls this and inserts the token for the key that comes back.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed_preset extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The context of the editor the block is embedded from'),
            'presetslot' => new external_value(PARAM_INT, 'The site preset slot to use'),
        ]);
    }

    /**
     * Create the block.
     *
     * @param int $contextid The editor's context.
     * @param int $presetslot The preset slot.
     * @return array
     */
    public static function execute(int $contextid, int $presetslot): array {
        ['contextid' => $contextid, 'presetslot' => $presetslot] = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'presetslot' => $presetslot]
        );

        $context = \context::instance_by_id($contextid);
        self::validate_context($context);
        require_capability('local/edguidance:manage', $context);

        return ['key' => api::save_embed($context, null, $presetslot)];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'key' => new external_value(PARAM_ALPHANUM, 'The new block\'s key, for the token'),
        ]);
    }
}
