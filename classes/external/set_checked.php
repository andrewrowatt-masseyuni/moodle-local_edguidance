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
use local_edguidance\guidance;

/**
 * Tick or untick an item on a guidance block's checklist, for every teacher.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_checked extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'guidanceid' => new external_value(PARAM_INT, 'The guidance block'),
            'index' => new external_value(PARAM_INT, 'The item, numbered from 0'),
            'hash' => new external_value(PARAM_ALPHANUM, 'The item\'s hash, as the page showed it'),
            'checked' => new external_value(PARAM_BOOL, 'Whether it should be ticked'),
        ]);
    }

    /**
     * Tick or untick.
     *
     * @param int $guidanceid The guidance block.
     * @param int $index The item.
     * @param string $hash The item's hash.
     * @param bool $checked Whether it should be ticked.
     * @return array
     */
    public static function execute(int $guidanceid, int $index, string $hash, bool $checked): array {
        global $DB;

        ['guidanceid' => $guidanceid, 'index' => $index, 'hash' => $hash, 'checked' => $checked] = self::validate_parameters(
            self::execute_parameters(),
            ['guidanceid' => $guidanceid, 'index' => $index, 'hash' => $hash, 'checked' => $checked]
        );

        // Drafts (neither an activity nor a section) are never rendered, so there is nothing to tick.
        $row = $DB->get_record_select(
            'local_edguidance',
            'id = :id AND (cmid > 0 OR sectionid > 0)',
            ['id' => $guidanceid],
            '*',
            MUST_EXIST
        );

        $context = guidance::context_for($row);
        self::validate_context($context);
        // View first: nobody may change guidance they may not read, whatever else they hold.
        require_capability('local/edguidance:view', $context);
        require_capability('local/edguidance:tick', $context);

        api::set_checked((int)$row->id, $index, $hash, $checked);

        return ['checked' => $checked];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'checked' => new external_value(PARAM_BOOL, 'Whether the item is now ticked'),
        ]);
    }
}
