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

namespace local_edguidance;

/**
 * Tests for the per-user dismissed state.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\dismissed
 */
final class dismissed_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        dismissed::reset_cache();
    }

    /**
     * Dismissing is personal: a colleague still sees the block.
     */
    public function test_dismissing_is_per_user(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();

        $this->setUser($first);
        dismissed::set(11, true);
        $this->assertTrue(dismissed::is_dismissed(11));
        $this->assertFalse(dismissed::is_dismissed(12));

        $this->setUser($second);
        $this->assertFalse(dismissed::is_dismissed(11));
    }

    /**
     * Restoring puts it back, and setting the same state twice is not an error.
     *
     * core_favourites throws on a duplicate insert and on deleting something that is not there, so
     * a double-click or a second browser tab would otherwise produce a 500.
     */
    public function test_setting_twice_is_harmless(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        dismissed::set(11, true);
        dismissed::set(11, true);
        $this->assertTrue(dismissed::is_dismissed(11));

        dismissed::set(11, false);
        dismissed::set(11, false);
        $this->assertFalse(dismissed::is_dismissed(11));
    }

    /**
     * Purging clears every user's dismissal of a block, and nothing else.
     */
    public function test_purge_crosses_users(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();

        foreach ([$first, $second] as $user) {
            $this->setUser($user);
            dismissed::set(11, true);
            dismissed::set(12, true);
        }

        dismissed::purge([11]);

        foreach ([$first, $second] as $user) {
            $this->setUser($user);
            dismissed::reset_cache();
            $this->assertFalse(dismissed::is_dismissed(11));
            $this->assertTrue(dismissed::is_dismissed(12));
        }
    }

    /**
     * One read per request, however many blocks ask.
     */
    public function test_one_read_per_request(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        dismissed::set(11, true);

        $before = $DB->perf_get_reads();
        for ($id = 1; $id <= 20; $id++) {
            dismissed::is_dismissed($id);
        }
        $this->assertLessThanOrEqual(2, $DB->perf_get_reads() - $before);
    }
}
