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

use local_edguidance\output\block;

/**
 * Tests for guidance categories and headings, and how a block shows them.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\category
 * @covers     \local_edguidance\output\block
 * @covers     \local_edguidance\guidance::format_heading
 */
final class category_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
    }

    /**
     * A block in a page, with a teacher looking at it.
     *
     * @param array $record Overrides for the block.
     * @return \stdClass The row.
     */
    private function make_block(array $record = []): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        return $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block(
            ['cmid' => $page->cmid] + $record
        );
    }

    /**
     * Every category, and whether it is a task.
     *
     * @return array
     */
    public static function categories_provider(): array {
        return [
            'note' => [category::NOTE, 'Note', false],
            'recommendation' => [category::RECOMMENDATION, 'Recommendation', false],
            'task' => [category::TASK, 'Task', true],
            'optional task' => [category::OPTIONALTASK, 'Optional task', true],
        ];
    }

    /**
     * A known category is kept; anything else - nothing stored, or a name from a later version - is a
     * note.
     */
    public function test_normalise(): void {
        foreach (category::ALL as $category) {
            $this->assertSame($category, category::normalise($category));
        }
        $this->assertSame(category::NOTE, category::normalise(null));
        $this->assertSame(category::NOTE, category::normalise(''));
        $this->assertSame(category::NOTE, category::normalise('urgent'));
        $this->assertFalse(category::is_valid('urgent'));
    }

    /**
     * The options for the form are every category, in order, by name.
     */
    public function test_options(): void {
        $this->assertSame(
            ['note' => 'Note', 'recommendation' => 'Recommendation', 'task' => 'Task', 'optionaltask' => 'Optional task'],
            category::options()
        );
    }

    /**
     * A block is dressed as its category and names it; a task is marked as complete, anything else
     * as read, and the confirmation matches.
     *
     * @dataProvider categories_provider
     * @param string $category The category.
     * @param string $name Its name.
     * @param bool $task Whether it is a task.
     */
    public function test_a_block_shows_its_category(string $category, string $name, bool $task): void {
        $this->resetAfterTest();
        $row = $this->make_block(['category' => $category]);

        $html = block::render_row($row);

        $this->assertStringContainsString('class="edguidance edguidance-' . $category . '"', $html);
        $this->assertStringContainsString('<span class="edguidance-title">' . $name . '</span>', $html);
        $this->assertSame(category::is_task($category), $task);

        $complete = get_string('markascomplete', 'local_edguidance');
        $read = get_string('markasread', 'local_edguidance');
        $this->assertSame($task, str_contains($html, $complete));
        $this->assertSame(!$task, str_contains($html, $read));
        $this->assertSame($task, str_contains($html, get_string('completedconfirm', 'local_edguidance')));
        $this->assertSame(!$task, str_contains($html, get_string('dismissedconfirm', 'local_edguidance')));
    }

    /**
     * A category this version does not know is shown as a note, rather than as nothing.
     */
    public function test_an_unknown_category_shows_as_a_note(): void {
        $this->resetAfterTest();
        $row = $this->make_block(['category' => 'urgent']);

        $html = block::render_row($row);

        $this->assertStringContainsString('edguidance-note', $html);
        $this->assertStringNotContainsString('edguidance-urgent', $html);
        $this->assertStringContainsString(get_string('markasread', 'local_edguidance'), $html);
    }

    /**
     * A heading is an h5 above the guidance, formatted as a string: escaped, and stripped of tags.
     */
    public function test_a_heading_is_an_h5(): void {
        $this->resetAfterTest();
        $row = $this->make_block(['heading' => 'Before <b>week one</b> & after']);

        $html = block::render_row($row);

        $this->assertStringContainsString('<h5 class="edguidance-heading">Before week one &amp; after</h5>', $html);
        $this->assertLessThan(strpos($html, 'edguidance-body'), strpos($html, 'edguidance-heading'));
    }

    /**
     * No heading, or one that is only spaces, is no h5 at all.
     */
    public function test_no_heading_is_no_h5(): void {
        $this->resetAfterTest();

        $this->assertStringNotContainsString('<h5', block::render_row($this->make_block()));
        $this->assertStringNotContainsString('<h5', block::render_row($this->make_block(['heading' => '   '])));
    }

    /**
     * A block using a preset keeps its own category and heading, around the preset's text.
     */
    public function test_a_preset_block_has_its_own_category_and_heading(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(1, 'Dates', '<p>Preset words.</p>');
        $row = $this->make_block(['presetslot' => 1, 'category' => category::TASK, 'heading' => 'Dates']);

        $html = block::render_row($row);

        $this->assertStringContainsString('Preset words.', $html);
        $this->assertStringContainsString('edguidance-task', $html);
        $this->assertStringContainsString('<h5 class="edguidance-heading">Dates</h5>', $html);
    }

    /**
     * The editor's preview shows the category and heading, and - for a teacher, who may tick - the
     * button to mark it complete.
     */
    public function test_the_preview_shows_category_and_heading(): void {
        $this->resetAfterTest();
        $row = $this->make_block(['category' => category::OPTIONALTASK, 'heading' => 'If there is time']);

        $html = block::render_preview($row);

        $this->assertStringContainsString('edguidance-optionaltask', $html);
        $this->assertStringContainsString('Optional task', $html);
        $this->assertStringContainsString('<h5 class="edguidance-heading">If there is time</h5>', $html);
        $this->assertStringContainsString(get_string('markascomplete', 'local_edguidance'), $html);
    }
}
