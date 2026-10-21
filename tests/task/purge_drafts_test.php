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

namespace local_edguidance\task;

use local_edguidance\dismissed;
use local_edguidance\guidance;

/**
 * Tests for the scheduled task that deletes abandoned guidance drafts.
 *
 * What counts as abandoned is api::purge_drafts()'s, and tested with it; this is the task itself.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\task\purge_drafts
 */
final class purge_drafts_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
    }

    /**
     * The task deletes drafts more than a day old, keeps newer ones, and says how many it deleted.
     */
    public function test_execute(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');
        $course = $this->getDataGenerator()->create_course();
        $old = $generator->create_block(['courseid' => $course->id, 'timemodified' => time() - 2 * DAYSECS]);
        $recent = $generator->create_block(['courseid' => $course->id]);

        $task = new purge_drafts();
        $this->assertNotEmpty($task->get_name());

        $this->expectOutputString("Deleted 1 abandoned teacher guidance drafts.\n");
        $task->execute();

        $this->assertFalse($DB->record_exists('local_edguidance', ['id' => $old->id]));
        $this->assertTrue($DB->record_exists('local_edguidance', ['id' => $recent->id]));
    }
}
