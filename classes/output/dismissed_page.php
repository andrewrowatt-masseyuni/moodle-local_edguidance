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

namespace local_edguidance\output;

use local_edguidance\dismissed;
use local_edguidance\guidance;

/**
 * The guidance blocks the current user has dismissed in a course, each with a way to restore it.
 *
 * A dismissed block leaves nothing behind on the page or in the editor, so this is the only way
 * back. Blocks are listed in course order - each section's own blocks, then its activities' - and
 * only where the user could see them if they were restored.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dismissed_page implements \renderable, \templatable {
    /** @var int How long an excerpt of a block's guidance may be, in characters. */
    protected const EXCERPT = 150;

    /**
     * Constructor.
     *
     * @param \stdClass $course The course being listed.
     */
    public function __construct(
        /** @var \stdClass The course being listed. */
        protected \stdClass $course,
    ) {
    }

    /**
     * Whether the current user has dismissed anything in a course.
     *
     * Decides whether the course navigation offers this page: it is the only way back to dismissed
     * guidance, but an always-present link to an always-empty page is just noise. Called on every
     * course page, so it asks the two cheap questions - anything dismissed at all, anything
     * dismissed here - before the one that walks the course.
     *
     * @param int $courseid The course.
     * @return bool
     */
    public static function has_dismissed(int $courseid): bool {
        $ids = dismissed::get_for_user();
        if (!$ids) {
            return false;
        }

        $here = false;
        foreach (guidance::for_sections($courseid) as $row) {
            $here = $here || isset($ids[(int)$row->id]);
        }
        foreach (guidance::for_course($courseid) as $rows) {
            foreach ($rows as $row) {
                $here = $here || isset($ids[(int)$row->id]);
            }
        }

        return $here && self::find($courseid) !== [];
    }

    /**
     * The course's blocks the current user has dismissed and could see if they were restored.
     *
     * Visibility is checked as the page would check it: a section or activity the user cannot see
     * is left out, and so is an activity where a role override has taken away the view capability.
     * The course's own view capability is the caller's to check.
     *
     * @param int $courseid The course.
     * @return array<int, array{row: \stdClass, name: string, url: \moodle_url}> In course order.
     */
    protected static function find(int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $ids = dismissed::get_for_user();
        $bycm = guidance::for_course($courseid);

        $bysection = [];
        foreach (guidance::for_sections($courseid) as $row) {
            $bysection[(int)$row->sectionid][] = $row;
        }

        $modinfo = get_fast_modinfo($courseid);
        $found = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }

            foreach ($bysection[(int)$section->id] ?? [] as $row) {
                if (isset($ids[(int)$row->id])) {
                    $found[] = [
                        'row' => $row,
                        'name' => get_section_name($courseid, $section),
                        'url' => course_get_url($courseid, $section->section, ['navigation' => true]),
                    ];
                }
            }

            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $rows = array_filter($bycm[$cmid] ?? [], fn($row) => isset($ids[(int)$row->id]));
                if ($rows) {
                    array_push($found, ...self::find_in_cm($modinfo->get_cm($cmid), $rows));
                }
            }
        }

        return $found;
    }

    /**
     * One activity's part of find(): its dismissed blocks, if the user could see them.
     *
     * @param \cm_info $cm The activity.
     * @param \stdClass[] $rows Its blocks the user has dismissed.
     * @return array<int, array{row: \stdClass, name: string, url: \moodle_url}>
     */
    protected static function find_in_cm(\cm_info $cm, array $rows): array {
        if (!$cm->uservisible || !has_capability('local/edguidance:view', $cm->context)) {
            return [];
        }

        // A label has no page of its own, so its section stands in.
        $url = $cm->get_url() ?? course_get_url((int)$cm->course, $cm->sectionnum, ['navigation' => true]);

        return array_map(fn($row) => ['row' => $row, 'name' => $cm->get_formatted_name(), 'url' => $url], array_values($rows));
    }

    /**
     * A short plain-text excerpt of what a block shows, so a teacher can tell one from another.
     *
     * @param \stdClass $row The local_edguidance row.
     * @return string Plain text, possibly '' for a block with nothing to say.
     */
    protected static function excerpt(\stdClass $row): string {
        $content = guidance::resolve($row)->content;
        $text = trim(preg_replace('/\s+/u', ' ', html_to_text($content, 0, false)));

        return shorten_text($text, self::EXCERPT);
    }

    /**
     * A link that restores one block and comes back here.
     *
     * @param int $courseid The course.
     * @param int $guidanceid The local_edguidance id.
     * @return \moodle_url
     */
    public static function restore_url(int $courseid, int $guidanceid): \moodle_url {
        return new \moodle_url('/local/edguidance/dismissed.php', [
            'course' => $courseid,
            'restore' => $guidanceid,
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Data for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $courseid = (int)$this->course->id;

        $items = [];
        foreach (self::find($courseid) as ['row' => $row, 'name' => $name, 'url' => $url]) {
            $items[] = [
                'heading' => guidance::format_heading($row),
                'excerpt' => self::excerpt($row),
                'location' => $name,
                'locationurl' => $url->out(false),
                'restoreurl' => self::restore_url($courseid, (int)$row->id)->out(false),
                'restorelabel' => get_string('restoreguidance', 'local_edguidance', $name),
            ];
        }

        return [
            'courseurl' => course_get_url($courseid)->out(false),
            'items' => $items,
            'hasany' => $items !== [],
        ];
    }
}
