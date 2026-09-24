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
 * Finds guidance blocks and resolves the text each one displays.
 *
 * A block that uses a site preset is a live view onto that preset rather than a copy of it, so
 * editing the preset updates every block already sitting in a course. That is why the text is
 * resolved here, at render time, on every request.
 *
 * Rows are read a whole course at a time and held for the request. The filter asks about one
 * activity's blocks at a time, and a course page may run it over a dozen descriptions; paying one
 * query per course rather than one per activity keeps that flat.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class guidance {
    /** @var string The columns every lookup returns. */
    protected const FIELDS = 'id, courseid, cmid, embedkey, introorder, presetslot, guidance, guidanceformat';

    /** @var array<int, array<int, array<string, \stdClass>>> Rows keyed by course id, cm id, then key. */
    protected static $cache = [];

    /** @var int How deep we are inside formatting a block's own text. */
    protected static $rendering = 0;

    /**
     * Every block in a course, keyed by cm id and then by key. Drafts are not included.
     *
     * @param int $courseid The course.
     * @return array<int, array<string, \stdClass>>
     */
    public static function for_course(int $courseid): array {
        global $DB;

        if (isset(self::$cache[$courseid])) {
            return self::$cache[$courseid];
        }

        $rows = $DB->get_records_select(
            'local_edguidance',
            'courseid = :courseid AND cmid > 0',
            ['courseid' => $courseid],
            'cmid, introorder, id',
            self::FIELDS
        );

        $bycm = [];
        foreach ($rows as $row) {
            $bycm[(int)$row->cmid][$row->embedkey] = $row;
        }

        return self::$cache[$courseid] = $bycm;
    }

    /**
     * One activity's blocks, keyed by key.
     *
     * @param int $courseid The activity's course. Passed in rather than looked up because every
     *     caller already has it, and a lookup here would cost the query this class exists to save.
     * @param int $cmid The course module.
     * @return array<string, \stdClass>
     */
    public static function for_cm(int $courseid, int $cmid): array {
        return self::for_course($courseid)[$cmid] ?? [];
    }

    /**
     * The blocks in an activity's description, in the order they appear there.
     *
     * @param int $courseid The activity's course.
     * @param int $cmid The course module.
     * @return \stdClass[]
     */
    public static function for_intro(int $courseid, int $cmid): array {
        $rows = array_filter(self::for_cm($courseid, $cmid), fn($row) => (int)$row->introorder > 0);
        usort($rows, fn($a, $b) => (int)$a->introorder <=> (int)$b->introorder);

        return $rows;
    }

    /**
     * The text a block displays, formatted for output.
     *
     * In order: the site preset the block uses, if that slot is still in use; otherwise the
     * block's own text, which for a preset block is the snapshot taken when it was linked, flagged
     * as missing so the reader knows it may be stale; otherwise nothing.
     *
     * @param \stdClass $row A local_edguidance row.
     * @return \stdClass With ->content (HTML, possibly '') and ->missing (bool).
     */
    public static function resolve(\stdClass $row): \stdClass {
        $context = \context_module::instance((int)$row->cmid);
        $missing = false;

        $preset = (int)$row->presetslot > 0 ? presets::get((int)$row->presetslot) : null;
        if ($preset) {
            $text = $preset->guidance;
            $format = FORMAT_HTML;
        } else {
            $missing = (int)$row->presetslot > 0;
            $format = (int)$row->guidanceformat;
            $text = file_rewrite_pluginfile_urls(
                (string)$row->guidance,
                'pluginfile.php',
                $context->id,
                'local_edguidance',
                'guidance',
                (int)$row->id
            );
        }

        if (html_is_blank($text)) {
            return (object)['content' => '', 'missing' => $missing];
        }

        // Filters run over the guidance itself, and filter_edguidance among them. It asks
        // is_rendering() and strips any token it finds rather than rendering a block inside a
        // block, which would otherwise recurse for as long as someone cared to nest them.
        self::$rendering++;
        try {
            $content = format_text($text, $format, ['context' => $context]);
        } finally {
            self::$rendering--;
        }

        return (object)['content' => $content, 'missing' => $missing];
    }

    /**
     * Whether a block's own text is being formatted right now.
     *
     * @return bool
     */
    public static function is_rendering(): bool {
        return self::$rendering > 0;
    }

    /**
     * Forget what was read for this request.
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }
}
