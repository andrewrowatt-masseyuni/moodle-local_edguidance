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
}
