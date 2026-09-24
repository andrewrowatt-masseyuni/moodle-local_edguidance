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
use local_edguidance\dismissed;

/**
 * Dismiss or restore a guidance block for the current user.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_dismissed extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'guidanceid' => new external_value(PARAM_INT, 'The guidance block'),
            'dismissed' => new external_value(PARAM_BOOL, 'Whether it should be dismissed'),
        ]);
    }

    /**
     * Dismiss or restore.
     *
     * @param int $guidanceid The guidance block.
     * @param bool $dismissed Whether it should be dismissed.
     * @return array
     */
    public static function execute(int $guidanceid, bool $dismissed): array {
        global $DB;

        ['guidanceid' => $guidanceid, 'dismissed' => $dismissed] = self::validate_parameters(
            self::execute_parameters(),
            ['guidanceid' => $guidanceid, 'dismissed' => $dismissed]
        );

        // Drafts (cmid 0) are never rendered, so there is nothing to dismiss.
        $row = $DB->get_record_select('local_edguidance', 'id = :id AND cmid > 0', ['id' => $guidanceid], '*', MUST_EXIST);

        $context = \context_module::instance($row->cmid);
        self::validate_context($context);
        // The real gate: it is what stops a student quietly accumulating rows against blocks they
        // were never shown.
        require_capability('local/edguidance:view', $context);

        dismissed::set((int)$row->id, $dismissed);

        return ['dismissed' => $dismissed];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'dismissed' => new external_value(PARAM_BOOL, 'Whether the block is now dismissed'),
        ]);
    }
}
