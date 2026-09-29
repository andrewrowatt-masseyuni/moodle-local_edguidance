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

use local_edguidance\output\dismissed_page;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/edguidance/lib.php');

/**
 * Tests for the dismissed guidance page and its link in the course navigation.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\output\dismissed_page
 * @covers     ::local_edguidance_extend_navigation_course
 */
final class dismissed_page_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
    }

    /**
     * The generator.
     *
     * @return \local_edguidance_generator
     */
    private function generator(): \local_edguidance_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_edguidance');
    }

    /**
     * What the page would list for the current user.
     *
     * @param \stdClass $course The course.
     * @return array The template data.
     */
    private function export(\stdClass $course): array {
        global $PAGE;

        return (new dismissed_page($course))->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Whether the course navigation offers the page to the current user.
     *
     * @param \stdClass $course The course.
     * @return bool
     */
    private function linked(\stdClass $course): bool {
        $node = \navigation_node::create('Course');
        local_edguidance_extend_navigation_course($node, $course, \context_course::instance($course->id));

        return (bool)$node->get('edguidancedismissed');
    }

    /**
     * Only what this user has dismissed in this course, in course order: a section's own guidance
     * before its activities', each named by where it is, with a short excerpt of what it says.
     */
    public function test_lists_dismissed_guidance_in_course_order(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 2]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 0, 'name' => 'Intro page']);
        $book = $this->getDataGenerator()->create_module('book', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Course book',
        ]);

        $inbook = $this->generator()->create_block(['cmid' => $book->cmid, 'guidance' => '<p>Pause for the group task.</p>']);
        $insection = $this->generator()->create_block([
            'sectionid' => get_fast_modinfo($course)->get_section_info(1)->id,
            'guidance' => '<h3>Week one</h3><p>Introduce yourself first.</p>',
        ]);
        $inpage = $this->generator()->create_block(['cmid' => $page->cmid, 'guidance' => '<p>Read this before class.</p>']);
        $this->generator()->create_block(['cmid' => $page->cmid, 'guidance' => '<p>Still showing.</p>']);

        $other = $this->getDataGenerator()->create_course();
        $elsewhere = $this->generator()->create_block([
            'cmid' => $this->getDataGenerator()->create_module('page', ['course' => $other->id])->cmid,
        ]);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $other->id, 'teacher');
        $this->setUser($teacher);
        foreach ([$inbook, $insection, $inpage, $elsewhere] as $row) {
            dismissed::set((int)$row->id, true);
        }

        $data = $this->export($course);

        $this->assertTrue($data['hasany']);
        $this->assertSame(
            ['Intro page', get_section_name($course, 1), 'Course book'],
            array_column($data['items'], 'location')
        );
        $this->assertSame('Read this before class.', $data['items'][0]['excerpt']);
        $this->assertStringContainsString('Introduce yourself first.', $data['items'][1]['excerpt']);
        $this->assertStringNotContainsString('<', $data['items'][1]['excerpt']);
        $this->assertStringContainsString('restore=' . $inpage->id, $data['items'][0]['restoreurl']);
        $this->assertStringContainsString('sesskey=' . sesskey(), $data['items'][0]['restoreurl']);
        $this->assertStringNotContainsString('Still showing.', json_encode($data));

        $this->assertTrue($this->linked($course));
    }

    /**
     * Nothing dismissed: the page says so, and the navigation does not offer it.
     */
    public function test_nothing_dismissed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->generator()->create_block(['cmid' => $page->cmid]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->assertFalse($this->export($course)['hasany']);
        $this->assertFalse($this->linked($course));

        // Dismissing something in another course does not count here.
        $other = $this->getDataGenerator()->create_course();
        $elsewhere = $this->generator()->create_block([
            'cmid' => $this->getDataGenerator()->create_module('page', ['course' => $other->id])->cmid,
        ]);
        dismissed::set((int)$elsewhere->id, true);
        $this->assertFalse($this->linked($course));

        // Restoring takes it back off the list.
        dismissed::set((int)$row->id, true);
        $this->assertTrue($this->linked($course));
        dismissed::set((int)$row->id, false);
        $this->assertFalse($this->linked($course));
    }

    /**
     * Guidance the user could not see if they restored it is not listed: its text would leak.
     */
    public function test_leaves_out_guidance_the_user_cannot_see(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->generator()->create_block(['cmid' => $page->cmid, 'guidance' => '<p>Private words.</p>']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher);
        dismissed::set((int)$row->id, true);

        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/edguidance:view', CAP_PROHIBIT, $roleid, \context_module::instance($page->cmid)->id);

        $data = $this->export($course);

        $this->assertFalse($data['hasany']);
        $this->assertStringNotContainsString('Private words.', json_encode($data));
        $this->assertFalse($this->linked($course));
    }

    /**
     * A student is never offered the page, even with a dismissal on record - one left from when
     * they held a teaching role, say.
     */
    public function test_students_are_not_offered_the_page(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $row = $this->generator()->create_block(['cmid' => $page->cmid]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        dismissed::set((int)$row->id, true);

        $this->assertFalse($this->linked($course));
    }
}
