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

namespace local_edguidance\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_edguidance\dismissed;

/**
 * Tests for the privacy provider.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        dismissed::reset_cache();
    }

    /**
     * Two users who have each dismissed something.
     *
     * @return \stdClass[]
     */
    private function two_users_with_dismissals(): array {
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();

        $this->setUser($first);
        dismissed::set(11, true);
        dismissed::set(12, true);
        $this->setUser($second);
        dismissed::set(11, true);
        $this->setUser();

        return [$first, $second];
    }

    /**
     * Only the user's own context is ever reported.
     */
    public function test_contexts_and_users(): void {
        $this->resetAfterTest();
        [$first, $second] = $this->two_users_with_dismissals();

        $contexts = provider::get_contexts_for_userid($first->id)->get_contextids();
        $this->assertSame([(int)\context_user::instance($first->id)->id], array_map('intval', $contexts));

        $userlist = new userlist(\context_user::instance($second->id), 'local_edguidance');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int)$second->id], array_map('intval', $userlist->get_userids()));
    }

    /**
     * Export writes the dismissed block ids into the user's context.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        [$first] = $this->two_users_with_dismissals();
        $context = \context_user::instance($first->id);

        provider::export_user_data(new approved_contextlist($first, 'local_edguidance', [$context->id]));

        $data = writer::with_context($context)->get_data([get_string('privacy:path:dismissed', 'local_edguidance')]);
        $this->assertCount(2, $data->dismissed);
    }

    /**
     * Deleting one user leaves the other alone.
     */
    public function test_delete_for_user(): void {
        global $DB;

        $this->resetAfterTest();
        [$first, $second] = $this->two_users_with_dismissals();
        $context = \context_user::instance($first->id);

        provider::delete_data_for_user(new approved_contextlist($first, 'local_edguidance', [$context->id]));

        $this->assertSame(0, $DB->count_records('favourite', ['userid' => $first->id, 'component' => 'local_edguidance']));
        $this->assertSame(1, $DB->count_records('favourite', ['userid' => $second->id, 'component' => 'local_edguidance']));
    }

    /**
     * Deleting a context, or a userlist in it, clears that user only.
     */
    public function test_delete_by_context_and_userlist(): void {
        global $DB;

        $this->resetAfterTest();
        [$first, $second] = $this->two_users_with_dismissals();

        provider::delete_data_for_all_users_in_context(\context_user::instance($first->id));
        $this->assertSame(0, $DB->count_records('favourite', ['userid' => $first->id, 'component' => 'local_edguidance']));

        $context = \context_user::instance($second->id);
        provider::delete_data_for_users(new approved_userlist($context, 'local_edguidance', [$second->id]));
        $this->assertSame(0, $DB->count_records('favourite', ['userid' => $second->id, 'component' => 'local_edguidance']));
    }
}
