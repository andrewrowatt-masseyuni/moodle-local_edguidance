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

use local_edguidance\local\card_injector;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/format/lib.php');
require_once($CFG->dirroot . '/local/edguidance/lib.php');

/**
 * Tests for putting description guidance into course page cards.
 *
 * These render real cards through the course format rather than only checking afterlink, because
 * the whole technique rests on core internals - the per-request modinfo instance, and afterlink
 * reaching the card template - and a core change that broke either should fail here.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_edguidance\local\card_injector
 * @covers     ::local_edguidance_override_webservice_execution
 */
final class card_injector_test extends \advanced_testcase {
    /**
     * Start each test with nothing cached.
     */
    protected function setUp(): void {
        parent::setUp();
        guidance::reset_cache();
        dismissed::reset_cache();
        card_injector::reset();
    }

    /**
     * A course with one page whose description holds a guidance block.
     *
     * @param array $pageoptions Extra options for the page.
     * @param string $modname The module to create.
     * @return array [course, cm id, guidance row]
     */
    private function make_course(array $pageoptions = [], string $modname = 'page'): array {
        $course = $this->getDataGenerator()->create_course();
        $key = token::new_key();
        $module = $this->getDataGenerator()->create_module($modname, array_merge([
            'course' => $course->id,
            'intro' => '<p>Description.</p>' . token::html($key),
            'showdescription' => 0,
        ], $pageoptions));

        $row = $this->getDataGenerator()->get_plugin_generator('local_edguidance')->create_block([
            'cmid' => $module->cmid,
            'embedkey' => $key,
            'introorder' => 1,
            'guidance' => '<p>Unique guidance words.</p>',
        ]);

        return [$course, (int)$module->cmid, $row];
    }

    /**
     * Render one card the way the course page does.
     *
     * @param \stdClass $course The course.
     * @param int $cmid The course module.
     * @return string
     */
    private function render_card(\stdClass $course, int $cmid): string {
        global $PAGE;

        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $PAGE->set_context(\context_course::instance($course->id));

        // By id: the generator's course object carries a cacherev from before its modules were
        // created, and handing that to the format would build fresh modinfo instead of the primed
        // one. A real page loads the course after its modules exist, so never sees this.
        $format = course_get_format((int)$course->id);
        $cm = $format->get_modinfo()->get_cm($cmid);

        return $format->get_renderer($PAGE)->course_section_updated_cm_item($format, $cm->get_section_info(), $cm);
    }

    /**
     * A teacher sees description guidance in the card when the description is hidden.
     */
    public function test_teacher_sees_guidance_in_the_card(): void {
        $this->resetAfterTest();
        [$course, $cmid, $row] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        card_injector::prime((int)$course->id);
        $html = $this->render_card($course, $cmid);

        $this->assertStringContainsString('data-guidanceid="' . $row->id . '"', $html);
        $this->assertStringContainsString('Unique guidance words.', $html);
        $this->assertTrue(card_injector::was_emitted((int)$row->id));
    }

    /**
     * A non-editing teacher sees it too.
     */
    public function test_non_editing_teacher_sees_guidance(): void {
        $this->resetAfterTest();
        [$course, $cmid] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'teacher'));

        card_injector::prime((int)$course->id);

        $this->assertStringContainsString('Unique guidance words.', $this->render_card($course, $cmid));
    }

    /**
     * A student never does.
     *
     * A separate test from the teachers' rather than a user switch within one: course_get_format()
     * holds on to the modinfo it first built, so a second user in the same request would be
     * rendered from the first user's cards - which a real request never does.
     */
    public function test_student_never_sees_guidance(): void {
        $this->resetAfterTest();
        [$course, $cmid, $row] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        card_injector::prime((int)$course->id);

        $this->assertStringNotContainsString('Unique guidance words.', $this->render_card($course, $cmid));
        $this->assertFalse(card_injector::was_emitted((int)$row->id));
    }

    /**
     * A teacher who has dismissed the guidance gets none of it in the card, not even an empty
     * wrapper; the filter is told it was handled, so a description Snap shows does not bring it back.
     */
    public function test_dismissed_guidance_is_left_out_of_the_card(): void {
        $this->resetAfterTest();
        [$course, $cmid, $row] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        dismissed::set((int)$row->id, true);

        card_injector::prime((int)$course->id);
        $html = $this->render_card($course, $cmid);

        $this->assertStringNotContainsString('Unique guidance words.', $html);
        $this->assertStringNotContainsString('edguidance', $html);
        $this->assertTrue(card_injector::was_emitted((int)$row->id));
    }

    /**
     * With the description on the course page, the filter places it; the card is left alone.
     */
    public function test_shown_description_is_left_to_the_filter(): void {
        $this->resetAfterTest();
        [$course, $cmid, $row] = $this->make_course(['showdescription' => 1]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        card_injector::prime((int)$course->id);

        $this->assertStringNotContainsString(
            'data-guidanceid="' . $row->id . '"',
            (string)get_fast_modinfo((int)$course->id)->get_cm($cmid)->afterlink
        );
        $this->assertFalse(card_injector::was_emitted((int)$row->id));
    }

    /**
     * A label always shows its content, so it never needs the fallback.
     */
    public function test_labels_are_left_to_the_filter(): void {
        $this->resetAfterTest();
        [$course, $cmid] = $this->make_course([], 'label');
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        card_injector::prime((int)$course->id);

        $afterlink = (string)get_fast_modinfo((int)$course->id)->get_cm($cmid)->afterlink;
        $this->assertStringNotContainsString('data-guidanceid', $afterlink);
    }

    /**
     * Priming twice in one request - a page, then a fragment - adds the block once.
     */
    public function test_priming_is_idempotent(): void {
        $this->resetAfterTest();
        [$course, $cmid, $row] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        card_injector::prime((int)$course->id);
        card_injector::prime((int)$course->id);

        $afterlink = (string)get_fast_modinfo((int)$course->id)->get_cm($cmid)->afterlink;
        $this->assertSame(1, substr_count($afterlink, 'data-guidanceid="' . $row->id . '"'));
        // Nor an empty wrapper the second time.
        $this->assertSame(1, substr_count($afterlink, 'no-overflow'));
    }

    /**
     * The guidance is wrapped as a description shown in the card would be.
     */
    public function test_guidance_is_wrapped_as_a_description(): void {
        $this->resetAfterTest();
        [$course, $cmid, $row] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        card_injector::prime((int)$course->id);

        $this->assertMatchesRegularExpression(
            '~<div class="no-overflow">\s*<div class="edguidance[^"]*" data-region="edguidance" data-guidanceid="' . $row->id . '"~',
            (string)get_fast_modinfo((int)$course->id)->get_cm($cmid)->afterlink
        );
    }

    /**
     * A card re-rendered over AJAX keeps its guidance.
     *
     * Exercises the path the Boost course editor takes after moving, hiding or indenting an
     * activity: core_get_fragment for core_courseformat/cmitem.
     */
    public function test_a_card_fragment_keeps_its_guidance(): void {
        $this->resetAfterTest();
        [$course, $cmid] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $info = (object)['name' => 'core_get_fragment'];
        $params = ['core_courseformat', 'cmitem', \context_course::instance($course->id)->id, []];
        $this->assertFalse(local_edguidance_override_webservice_execution($info, $params));

        $html = core_courseformat_output_fragment_cmitem(['id' => $cmid]);

        $this->assertStringContainsString('Unique guidance words.', $html);
    }

    /**
     * Web service calls that do not re-render cards are ignored without any work.
     */
    public function test_other_web_services_are_ignored(): void {
        global $DB;

        $this->resetAfterTest();
        [$course] = $this->make_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $before = $DB->perf_get_reads();
        local_edguidance_override_webservice_execution((object)['name' => 'core_course_get_contents'], [$course->id]);
        local_edguidance_override_webservice_execution(
            (object)['name' => 'core_get_fragment'],
            ['mod_assign', 'gradingpanel', \context_course::instance($course->id)->id, []]
        );

        $this->assertSame(0, $DB->perf_get_reads() - $before);
    }
}
