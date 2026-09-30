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

use core_form\external\dynamic_form;
use local_edguidance\form\embed_form;

/**
 * Tests for the embed modal form.
 *
 * Driven through core_form's own dynamic_form web service, which is the path the editor's modal
 * takes: it validates the context and checks access before the form is built.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\form\embed_form
 */
final class embed_form_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
    }

    /**
     * Submit the form as the modal would.
     *
     * @param array $data The form fields.
     * @return array The web service result.
     */
    private function submit(array $data): array {
        // The mock adds the submission marker and puts the sesskey where confirm_sesskey() looks.
        return dynamic_form::execute(embed_form::class, $this->query(embed_form::mock_ajax_submit($data)));
    }

    /**
     * Encode form data the way the modal does.
     *
     * The separator is explicit because Moodle sets arg_separator.output to &amp;, which
     * parse_str() on the other side would read as part of the next key.
     *
     * @param array $data The form data.
     * @return string
     */
    private function query(array $data): string {
        return http_build_query($data, '', '&');
    }

    /**
     * Open the form without submitting it, as the modal does first.
     *
     * @param array $args The modal's arguments.
     * @return string The form HTML.
     */
    private function open(array $args): string {
        return dynamic_form::execute(embed_form::class, $this->query($args))['html'];
    }

    /**
     * A course with a book, and an editing teacher on it.
     *
     * @return array [course, book context]
     */
    private function setup_book(): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        return [$course, \context_module::instance($book->cmid)];
    }

    /**
     * "Start with blank": typing guidance creates a block with its own text.
     */
    public function test_own_text_creates_a_block(): void {
        global $DB;

        $this->resetAfterTest();
        [, $context] = $this->setup_book();

        $result = $this->submit([
            'contextid' => $context->id,
            'key' => '',
            'source' => 0,
            'guidance_editor' => [
                'text' => '<p>Typed here.</p>',
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ],
        ]);

        $this->assertTrue($result['submitted']);
        $key = json_decode($result['data'])->key;
        $row = $DB->get_record('local_edguidance', ['cmid' => $context->instanceid, 'embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame(0, (int)$row->presetslot);
        $this->assertStringContainsString('Typed here.', $row->guidance);
    }

    /**
     * Blank own text is refused rather than saved as an empty block.
     */
    public function test_blank_own_text_is_refused(): void {
        $this->resetAfterTest();
        [, $context] = $this->setup_book();

        $result = $this->submit([
            'contextid' => $context->id,
            'key' => '',
            'source' => 0,
            'guidance_editor' => ['text' => '<p></p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
        ]);

        $this->assertFalse($result['submitted']);
    }

    /**
     * "Start with a preset" copies the preset into the editor, and the copy is not linked.
     */
    public function test_start_with_a_preset_copies_it(): void {
        global $DB;

        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(2, 'Dates', '<p>Preset words.</p>');
        [, $context] = $this->setup_book();

        $html = $this->open(['contextid' => $context->id, 'startslot' => 2]);
        $this->assertStringContainsString('Preset words.', $html);

        $result = $this->submit([
            'contextid' => $context->id,
            'key' => '',
            'source' => 0,
            'guidance_editor' => ['text' => '<p>Preset words, edited.</p>', 'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid()],
        ]);

        $key = json_decode($result['data'])->key;
        $this->assertSame(0, (int)$DB->get_field('local_edguidance', 'presetslot', ['embedkey' => $key]));
    }

    /**
     * Choosing a preset in the form links the block to it.
     */
    public function test_choosing_a_preset_links_it(): void {
        global $DB;

        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(2, 'Dates', '<p>Preset words.</p>');
        [, $context] = $this->setup_book();

        $result = $this->submit([
            'contextid' => $context->id,
            'key' => '',
            'source' => 2,
            'guidance_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
        ]);

        $key = json_decode($result['data'])->key;
        $this->assertSame(2, (int)$DB->get_field('local_edguidance', 'presetslot', ['embedkey' => $key]));
    }

    /**
     * Opening a block that uses a preset selects that preset.
     */
    public function test_editing_a_preset_block_selects_it(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_edguidance');
        $generator->set_preset(3, 'Groups', '<p>Group words.</p>');
        [, $context] = $this->setup_book();
        $row = $generator->create_block(['cmid' => $context->instanceid, 'presetslot' => 3]);

        $html = $this->open(['contextid' => $context->id, 'key' => $row->embedkey]);

        $this->assertMatchesRegularExpression('/<option value="3"\s+selected/', $html);
    }

    /**
     * A block this teacher has dismissed opens with a notice saying so, carrying the block's id for
     * the editor's Restore button. Dismissing is personal, so a colleague gets no notice, and editing
     * does not undismiss it.
     */
    public function test_editing_a_dismissed_block_says_so(): void {
        global $DB;

        $this->resetAfterTest();
        [$course, $context] = $this->setup_book();
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block(['cmid' => $context->instanceid]);
        $notice = 'data-region="edguidance-dismissednotice"';

        $this->assertStringNotContainsString($notice, $this->open(['contextid' => $context->id, 'key' => $row->embedkey]));

        dismissed::set((int)$row->id, true);
        $html = $this->open(['contextid' => $context->id, 'key' => $row->embedkey]);
        $this->assertStringContainsString($notice, $html);
        $this->assertStringContainsString('data-guidanceid="' . $row->id . '"', $html);
        // Not for a new block, whatever else this teacher has dismissed.
        $this->assertStringNotContainsString($notice, $this->open(['contextid' => $context->id]));

        $this->submit([
            'contextid' => $context->id,
            'key' => $row->embedkey,
            'source' => 0,
            'guidance_editor' => [
                'text' => '<p>Edited.</p>',
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ],
        ]);
        $this->assertStringContainsString('Edited.', $DB->get_field('local_edguidance', 'guidance', ['id' => $row->id]));
        $this->assertTrue(dismissed::is_dismissed((int)$row->id));

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $this->assertStringNotContainsString($notice, $this->open(['contextid' => $context->id, 'key' => $row->embedkey]));
    }

    /**
     * A non-editing teacher cannot open the form.
     */
    public function test_needs_manage(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        $this->expectException(\required_capability_exception::class);
        $this->open(['contextid' => \context_module::instance($book->cmid)->id]);
    }

    /**
     * The form cannot be pointed at a context no block can belong to.
     */
    public function test_refuses_other_contexts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        $this->open(['contextid' => \context_system::instance()->id]);
    }

    /**
     * From a section summary's editor, the form makes a block for that section.
     */
    public function test_section_summary_block(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $section = get_fast_modinfo($course)->get_section_info(1);
        $context = \context_course::instance($course->id);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->assertStringContainsString('name="sectionid" type="hidden" value="' . $section->id . '"', $this->open([
            'contextid' => $context->id,
            'sectionid' => $section->id,
        ]));

        $result = $this->submit([
            'contextid' => $context->id,
            'sectionid' => $section->id,
            'key' => '',
            'source' => 0,
            'guidance_editor' => [
                'text' => '<p>For this week.</p>',
                'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid(),
            ],
        ]);

        $key = json_decode($result['data'])->key;
        $row = $DB->get_record('local_edguidance', ['embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame((int)$section->id, (int)$row->sectionid);
        $this->assertSame(0, (int)$row->cmid);
    }

    /**
     * The form cannot be pointed at a section in another course.
     */
    public function test_refuses_a_section_elsewhere(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();

        $this->expectException(\invalid_parameter_exception::class);
        $this->open([
            'contextid' => \context_course::instance($course->id)->id,
            'sectionid' => get_fast_modinfo($other)->get_section_info(1)->id,
        ]);
    }

    /**
     * A new block takes the category and heading it is given; a blank heading is none.
     */
    public function test_category_and_heading_are_saved(): void {
        global $DB;

        $this->resetAfterTest();
        [, $context] = $this->setup_book();

        $key = json_decode($this->submit([
            'contextid' => $context->id,
            'key' => '',
            'category' => category::TASK,
            'heading' => '  Before week one  ',
            'source' => 0,
            'guidance_editor' => ['text' => '<p>Set up groups.</p>', 'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid()],
        ])['data'])->key;
        $row = $DB->get_record('local_edguidance', ['embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame(category::TASK, $row->category);
        $this->assertSame('Before week one', $row->heading);

        $key = json_decode($this->submit([
            'contextid' => $context->id,
            'key' => '',
            'category' => category::NOTE,
            'heading' => '   ',
            'source' => 0,
            'guidance_editor' => ['text' => '<p>Just so you know.</p>', 'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid()],
        ])['data'])->key;
        $this->assertNull($DB->get_field('local_edguidance', 'heading', ['embedkey' => $key]));
    }

    /**
     * A block opens with its category selected and its heading filled in, and both can be changed.
     */
    public function test_category_and_heading_can_be_changed(): void {
        global $DB;

        $this->resetAfterTest();
        [, $context] = $this->setup_book();
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'cmid' => $context->instanceid,
            'category' => category::OPTIONALTASK,
            'heading' => 'If there is time',
        ]);

        $html = $this->open(['contextid' => $context->id, 'key' => $row->embedkey]);
        $this->assertMatchesRegularExpression('/<option value="optionaltask"\s+selected/', $html);
        $this->assertStringContainsString('value="If there is time"', $html);

        $this->submit([
            'contextid' => $context->id,
            'key' => $row->embedkey,
            'category' => category::RECOMMENDATION,
            'heading' => 'Worth doing',
            'source' => 0,
            'guidance_editor' => ['text' => '<p>Still the same words.</p>', 'format' => FORMAT_HTML,
                'itemid' => file_get_unused_draft_itemid()],
        ]);

        $saved = $DB->get_record('local_edguidance', ['id' => $row->id], '*', MUST_EXIST);
        $this->assertSame(category::RECOMMENDATION, $saved->category);
        $this->assertSame('Worth doing', $saved->heading);
    }

    /**
     * A new block opens as a note with no heading.
     */
    public function test_a_new_block_opens_as_a_note(): void {
        $this->resetAfterTest();
        [, $context] = $this->setup_book();

        $html = $this->open(['contextid' => $context->id]);

        $this->assertMatchesRegularExpression('/<option value="note"\s+selected/', $html);
    }

    /**
     * A block using a preset has a category and heading of its own, and keeping them does not
     * unlink the preset.
     */
    public function test_a_preset_block_keeps_its_preset(): void {
        global $DB;

        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_edguidance')->set_preset(2, 'Dates', '<p>Preset words.</p>');
        [, $context] = $this->setup_book();

        $key = json_decode($this->submit([
            'contextid' => $context->id,
            'key' => '',
            'category' => category::TASK,
            'heading' => 'Dates',
            'source' => 2,
            'guidance_editor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
        ])['data'])->key;

        $row = $DB->get_record('local_edguidance', ['embedkey' => $key], '*', MUST_EXIST);
        $this->assertSame(2, (int)$row->presetslot);
        $this->assertSame(category::TASK, $row->category);
        $this->assertSame('Dates', $row->heading);
    }

    /**
     * A category that is not one is refused, by the form and by the API behind it.
     */
    public function test_an_unknown_category_is_refused(): void {
        $this->resetAfterTest();
        [, $context] = $this->setup_book();

        $result = $this->submit([
            'contextid' => $context->id,
            'key' => '',
            'category' => 'urgent',
            'source' => 0,
            'guidance_editor' => ['text' => '<p>Words.</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
        ]);
        $this->assertFalse($result['submitted']);

        $this->expectException(\invalid_parameter_exception::class);
        api::save_embed($context, null, 0, ['text' => '<p>Words.</p>', 'format' => FORMAT_HTML, 'itemid' => 0], 0, 'urgent');
    }

    /**
     * A task this teacher has dismissed says it was marked as complete, not as read.
     */
    public function test_editing_a_dismissed_task_says_complete(): void {
        $this->resetAfterTest();
        [, $context] = $this->setup_book();
        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'cmid' => $context->instanceid,
            'category' => category::TASK,
        ]);
        dismissed::set((int)$row->id, true);

        $html = $this->open(['contextid' => $context->id, 'key' => $row->embedkey]);

        $this->assertStringContainsString(get_string('formcompleted', 'local_edguidance'), $html);
        $this->assertStringNotContainsString(get_string('formdismissed', 'local_edguidance'), $html);
    }
}
