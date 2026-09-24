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

namespace local_edguidance\local;

use core_external\external_api;
use local_edguidance\guidance;
use local_edguidance\output\block;

/**
 * Puts description guidance into the activity card when the description itself is not shown.
 *
 * With "Display description on course page" ticked, guidance in a description needs nothing from
 * here: the description is filtered when the card renders, and filter_edguidance puts the block
 * where its token sits. With it unticked, the description never reaches the course page, so this
 * class appends the blocks to the activity's afterlink instead - the slot core prints at the foot
 * of a Boost card (course/format/templates/local/content/cm/activity.mustache) and theme_snap
 * prints in its card meta row (theme/snap/classes/output/core/course_renderer.php). No theme is
 * changed.
 *
 * Core 4.5 offers no hook for adding to another module's card; afterlink is normally set only by
 * the owning module's own _cm_info_view() callback. What makes this work is that the course page
 * renders from the same per-request course_modinfo instance that get_fast_modinfo() hands us here,
 * and cm_info::set_after_link() has no state check. Two consequences:
 *
 * - It must run before the course content renders: on the page, from the before_http_headers hook;
 *   when a card is re-rendered over AJAX, from local_edguidance_override_webservice_execution().
 *   Snap redraws some cards through its own web services, which are not covered: those cards show
 *   no fallback guidance until the page is reloaded. That limitation was accepted.
 * - The existing afterlink must be read first. Reading it forces the module's own cm_info_view(),
 *   which would otherwise run later and overwrite what we set.
 *
 * tests/card_injector_test.php renders a real card after priming, so a core change that breaks
 * any of this fails a test rather than quietly emptying cards.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class card_injector {
    /** @var string[] The fragments that re-render course page cards, as component/callback. */
    protected const FRAGMENTS = [
        'core_courseformat/cmitem',
        'core_courseformat/section',
        'theme_snap/section',
    ];

    /**
     * Guidance ids placed into a card during this request.
     *
     * filter_edguidance skips these. theme_snap shows page and book descriptions in their cards
     * whether or not "Display description" is ticked, so without this the same block would appear
     * twice on one card.
     *
     * @var array<int, true>
     */
    protected static $emitted = [];

    /**
     * Put description guidance into every card on a course that needs it.
     *
     * @param int $courseid The course.
     */
    public static function prime(int $courseid): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        $bycm = guidance::for_course($courseid);
        if (!$bycm) {
            return;
        }

        $cms = get_fast_modinfo($courseid)->get_cms();
        foreach (array_keys($bycm) as $cmid) {
            $cm = $cms[$cmid] ?? null;
            if ($cm && self::wants_card_guidance($cm)) {
                self::prime_cm($cm);
            }
        }
    }

    /**
     * Whether a card should carry its description guidance in afterlink.
     *
     * Only when the description is hidden from the course page. A module that does not support
     * showing its description - mod_label - always shows its content, so the filter covers it.
     *
     * @param \cm_info $cm The course module.
     * @return bool
     */
    protected static function wants_card_guidance(\cm_info $cm): bool {
        return !$cm->showdescription
            && plugin_supports('mod', $cm->modname, FEATURE_SHOW_DESCRIPTION, false)
            && $cm->uservisible
            && has_capability('local/edguidance:view', $cm->context);
    }

    /**
     * Append one card's description guidance to its afterlink.
     *
     * Idempotent: a block already in the afterlink is not added again, so priming twice in one
     * request - a page and then a fragment, say - is harmless.
     *
     * @param \cm_info $cm The course module.
     */
    protected static function prime_cm(\cm_info $cm): void {
        // Read first: this runs the module's own _cm_info_view(), which may set afterlink itself.
        $existing = (string)$cm->afterlink;

        $html = '';
        foreach (guidance::for_intro((int)$cm->course, (int)$cm->id) as $row) {
            $id = (int)$row->id;
            if (strpos($existing, 'data-guidanceid="' . $id . '"') === false) {
                $html .= block::render_row($row);
            }
            self::$emitted[$id] = true;
        }

        if ($html !== '') {
            $cm->set_after_link($existing . $html);
        }
    }

    /**
     * Prime ahead of a web service call that is about to re-render course page cards.
     *
     * Called for every web service call, so it bails on anything else before doing any work. The
     * context is validated here, as the call itself will do in a moment, because rendering a block
     * needs $PAGE to have one; validate_context() resets the theme and output, so rendering early
     * cannot disturb the call. If validation fails the call will fail the same way, so the
     * exception is left for it to raise.
     *
     * @param string $function The web service function name.
     * @param array $params Its validated parameters, in declaration order.
     */
    public static function prime_for_webservice(string $function, array $params): void {
        $coursecontext = null;

        if ($function === 'core_get_fragment') {
            [$component, $callback, $contextid] = $params;
            if (in_array($component . '/' . $callback, self::FRAGMENTS, true)) {
                $context = \context::instance_by_id((int)$contextid, IGNORE_MISSING);
                $coursecontext = $context ? $context->get_course_context(false) : null;
            }
        } else if ($function === 'core_course_get_module' || $function === 'core_course_edit_module') {
            // Parameters: get_module(id, sectionreturn); edit_module(action, id, sectionreturn).
            $cmid = (int)($function === 'core_course_get_module' ? $params[0] : $params[1]);
            $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
            $coursecontext = $cm ? \context_course::instance($cm->course) : null;
        }

        if (!$coursecontext) {
            return;
        }

        try {
            external_api::validate_context($coursecontext);
        } catch (\moodle_exception $e) {
            return;
        }

        self::prime((int)$coursecontext->instanceid);
    }

    /**
     * Whether a block has already been placed into a card during this request.
     *
     * @param int $guidanceid The local_edguidance id.
     * @return bool
     */
    public static function was_emitted(int $guidanceid): bool {
        return isset(self::$emitted[$guidanceid]);
    }

    /**
     * Forget what was placed during this request.
     */
    public static function reset(): void {
        self::$emitted = [];
    }
}
