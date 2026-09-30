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

use local_edguidance\event\guidance_created;
use local_edguidance\event\guidance_updated;
use local_edguidance\external\embed_preset;

/**
 * Tests for the events logged when guidance is written and edited.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\api::save_embed
 * @covers     \local_edguidance\event\guidance_created
 * @covers     \local_edguidance\event\guidance_updated
 */
final class events_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
    }

    /**
     * The editor's value for some text.
     *
     * @param string $text HTML.
     * @return array
     */
    private function editor(string $text): array {
        return ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()];
    }

    /**
     * Writing guidance logs that it was created; each save after that, one update - however much
     * changed, ticks written by hand into its checklist included.
     */
    public function test_created_then_updated(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $sink = $this->redirectEvents();
        $key = api::save_embed($context, null, 0, $this->editor('<ul><li>[ ] One</li><li>[ ] Two</li></ul>'));
        $events = $sink->get_events();
        $id = (int)$DB->get_field('local_edguidance', 'id', ['embedkey' => $key]);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(guidance_created::class, $events[0]);
        $this->assertSame($id, (int)$events[0]->objectid);
        $this->assertSame((int)$teacher->id, (int)$events[0]->userid);
        $this->assertSame($context->id, $events[0]->get_context()->id);
        $this->assertSame('c', $events[0]->crud);
        $url = new \moodle_url('/course/view.php', ['id' => $course->id], 'module-' . $page->cmid);
        $this->assertEquals($url, $events[0]->get_url());
        $this->assertEventContextNotUsed($events[0]);

        $sink->clear();
        api::save_embed($context, $key, 0, $this->editor('<ul><li>[x] One</li><li>[x] Two</li></ul>'), 0, category::TASK);
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(guidance_updated::class, $events[0]);
        $this->assertSame($id, (int)$events[0]->objectid);
        $this->assertSame('u', $events[0]->crud);
    }

    /**
     * Using a preset writes guidance too.
     */
    public function test_using_a_preset_is_logged(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(1, 'Dates', '<p>Dates.</p>');
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $sink = $this->redirectEvents();
        embed_preset::execute(\context_module::instance($page->cmid)->id, 1);

        $this->assertCount(1, $sink->get_events());
        $this->assertInstanceOf(guidance_created::class, $sink->get_events()[0]);
    }

    /**
     * A section's guidance is logged against its course.
     */
    public function test_section_guidance_is_logged_in_the_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $sink = $this->redirectEvents();
        api::save_embed($context, null, 0, $this->editor('<p>For this week.</p>'), (int)get_fast_modinfo($course->id)
            ->get_section_info(1)->id);

        $this->assertSame($context->id, $sink->get_events()[0]->get_context()->id);
        $this->assertSame((int)$course->id, (int)$sink->get_events()[0]->courseid);
    }
}
