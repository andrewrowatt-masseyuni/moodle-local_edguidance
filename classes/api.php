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
 * Creating, adopting and deleting guidance blocks.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var int How long a draft may wait for its activity to be saved before it is purged. */
    public const DRAFT_LIFETIME = DAYSECS;

    /** @var string The file area holding a block's own embedded files. */
    public const FILEAREA = 'guidance';

    /** @var array<int, true> Courses whose section summaries are not to be claimed for now. */
    protected static $heldclaims = [];

    /**
     * Where a block created from an editor in this context belongs.
     *
     * An editor in a module context - a chapter, a lesson page, an existing activity's description
     * - makes a block for that module. An editor in a course context is either a section summary,
     * named by $sectionid, which makes a block for that section; or the description on the "add an
     * activity" form, where the module does not exist yet, which makes a draft (cm id and section id
     * both 0) that the activity adopts when it is saved.
     *
     * The section id comes from the client, which is why it is checked against the course here.
     *
     * @param \context $context The editor's context.
     * @param int $sectionid The section whose summary is being edited, or 0.
     * @return int[] [course id, cm id or 0, section id or 0]
     * @throws \invalid_parameter_exception For any other context, or a section not in the course.
     */
    public static function embed_target(\context $context, int $sectionid = 0): array {
        global $DB;

        if ($context instanceof \context_module && !$sectionid) {
            $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
            return [(int)$cm->course, (int)$cm->id, 0];
        }

        if ($context instanceof \context_course && (int)$context->instanceid !== (int)SITEID) {
            $courseid = (int)$context->instanceid;
            if ($sectionid && !$DB->record_exists('course_sections', ['id' => $sectionid, 'course' => $courseid])) {
                throw new \invalid_parameter_exception('That section is not in this course.');
            }
            return [$courseid, 0, $sectionid];
        }

        throw new \invalid_parameter_exception('Teacher guidance can only be embedded in an activity or a section.');
    }

    /**
     * A block by key, within the context an editor is working in.
     *
     * Always scoped to that context, so a key copied from another activity or section cannot be
     * used to read or edit a block that belongs somewhere else.
     *
     * @param \context $context The editor's context.
     * @param string $key The block's key.
     * @param int $sectionid The section whose summary is being edited, or 0.
     * @return \stdClass|null
     */
    public static function get_embed(\context $context, string $key, int $sectionid = 0): ?\stdClass {
        global $DB;

        [$courseid, $cmid, $sectionid] = self::embed_target($context, $sectionid);

        return $DB->get_record('local_edguidance', [
            'courseid' => $courseid,
            'cmid' => $cmid,
            'sectionid' => $sectionid,
            'embedkey' => $key,
        ]) ?: null;
    }

    /**
     * Editor options for a block's own text.
     *
     * @param \context $context The editor's context.
     * @return array
     */
    public static function editor_options(\context $context): array {
        return [
            'context' => $context,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => 0,
            'trusttext' => false,
            'subdirs' => 0,
            // The editor lives in a modal; an autosave keyed on the page it floats over would offer
            // to "restore" one block's text into the next.
            'autosave' => false,
        ];
    }

    /**
     * Create or update a block.
     *
     * A key that does not match a block in this context - a token pasted in from another activity,
     * say - gets a new block with a new key rather than an error. The caller rewrites the token with
     * whatever key comes back.
     *
     * @param \context $context The editor's context.
     * @param string|null $key The block to update, or null for a new one.
     * @param int $presetslot A site preset slot to use, or 0 for the block's own text.
     * @param array|null $editor The editor's value (text, format, itemid) when $presetslot is 0.
     * @param int $sectionid The section whose summary is being edited, or 0.
     * @return string The block's key.
     * @throws \invalid_parameter_exception If the preset slot is not in use.
     */
    public static function save_embed(
        \context $context,
        ?string $key,
        int $presetslot,
        ?array $editor = null,
        int $sectionid = 0
    ): string {
        global $DB;

        $preset = null;
        if ($presetslot > 0 && !($preset = presets::get($presetslot))) {
            throw new \invalid_parameter_exception('That teacher guidance preset is not in use.');
        }

        [$courseid, $cmid, $sectionid] = self::embed_target($context, $sectionid);
        $now = time();

        $row = ($key !== null && $key !== '') ? self::get_embed($context, $key, $sectionid) : null;
        if (!$row) {
            $row = (object)[
                'courseid' => $courseid,
                'cmid' => $cmid,
                'sectionid' => $sectionid,
                'embedkey' => token::new_key(),
                'introorder' => 0,
                'presetslot' => 0,
                'guidance' => '',
                'guidanceformat' => FORMAT_HTML,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $row->id = $DB->insert_record('local_edguidance', $row);
        }

        if ($preset) {
            // The snapshot is only ever shown if the slot is later emptied; the live text comes
            // from the preset on every render.
            $row->presetslot = $presetslot;
            $row->guidance = $preset->guidance;
            $row->guidanceformat = FORMAT_HTML;
            // A preset block has no files of its own. Drop any left over from its own text.
            get_file_storage()->delete_area_files($context->id, 'local_edguidance', self::FILEAREA, $row->id);
        } else {
            $data = file_postupdate_standard_editor(
                (object)['guidance_editor' => $editor ?? ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0]],
                'guidance',
                self::editor_options($context),
                $context,
                'local_edguidance',
                self::FILEAREA,
                $row->id
            );
            $row->presetslot = 0;
            $row->guidance = $data->guidance;
            $row->guidanceformat = $data->guidanceformat;
        }

        $row->timemodified = $now;
        $DB->update_record('local_edguidance', $row);
        guidance::reset_cache();

        return $row->embedkey;
    }

    /**
     * Record which blocks sit in an activity's description, adopting any drafts found there.
     *
     * Called whenever an activity is saved through its settings form. Reading the saved intro here,
     * rather than having the editor report what it inserted, means the record follows what is
     * actually in the description: a token deleted from the text drops out, a token pasted in
     * joins, and a draft made while the activity was being added finds its home.
     *
     * @param int $cmid The course module.
     */
    public static function adopt_intro(int $cmid): void {
        global $DB;

        $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || !plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, true)) {
            return;
        }

        $keys = token::keys_in((string)$DB->get_field($cm->modname, 'intro', ['id' => $cm->instance]));

        $bykey = [];
        foreach ($DB->get_records('local_edguidance', ['cmid' => $cmid], '', 'id, embedkey, introorder') as $row) {
            $bykey[$row->embedkey] = $row;
        }

        $inintro = [];
        foreach ($keys as $index => $key) {
            $order = $index + 1;

            if (isset($bykey[$key])) {
                $row = $bykey[$key];
                if ((int)$row->introorder !== $order) {
                    $DB->set_field('local_edguidance', 'introorder', $order, ['id' => $row->id]);
                }
                $inintro[(int)$row->id] = true;
                continue;
            }

            // Section id 0: a section's block also has cm id 0, and must not be carried off by an
            // activity whose description its token was pasted into.
            $draft = $DB->get_record('local_edguidance', [
                'courseid' => $cm->course,
                'cmid' => 0,
                'sectionid' => 0,
                'embedkey' => $key,
            ]);
            if ($draft) {
                self::adopt_draft($draft, $cm, $order);
                $inintro[(int)$draft->id] = true;
            }
        }

        foreach ($bykey as $row) {
            if (!isset($inintro[(int)$row->id]) && (int)$row->introorder !== 0) {
                $DB->set_field('local_edguidance', 'introorder', 0, ['id' => $row->id]);
            }
        }

        guidance::reset_cache();
    }

    /**
     * Move a draft into the activity that now carries its token.
     *
     * @param \stdClass $draft The draft row.
     * @param \stdClass $cm The course module.
     * @param int $order Its position among the description's blocks.
     */
    protected static function adopt_draft(\stdClass $draft, \stdClass $cm, int $order): void {
        global $DB;

        $fs = get_file_storage();
        $from = \context_course::instance($cm->course);
        $to = \context_module::instance($cm->id);
        foreach ($fs->get_area_files($from->id, 'local_edguidance', self::FILEAREA, $draft->id, 'id', false) as $file) {
            $fs->create_file_from_storedfile(['contextid' => $to->id], $file);
            $file->delete();
        }

        $DB->update_record('local_edguidance', (object)[
            'id' => $draft->id,
            'cmid' => $cm->id,
            'introorder' => $order,
            'timemodified' => time(),
        ]);
    }

    /**
     * Give a section its own copy of any block whose token was copied into its summary from another
     * section.
     *
     * Called whenever a section is updated. Core duplicates a section by copying its summary
     * verbatim (course_format::duplicate_section() - there is no hook), and a teacher can paste one
     * section's token into another. Either way two summaries would share one block: the filter
     * cannot tell them apart, deleting either section would take the other's guidance with it, and
     * editing one would change both. So the copy gets a block of its own - same text, same preset,
     * same files - under a new key, and its summary is rewritten to match.
     *
     * Tokens whose block is not a section's in this course are left alone: they resolve to nothing,
     * as a token copied between activities does.
     *
     * @param int $sectionid The course section.
     */
    public static function claim_section_summary(int $sectionid): void {
        global $DB;

        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'id, course, summary');
        if (!$section || isset(self::$heldclaims[(int)$section->course])) {
            return;
        }

        $keys = token::keys_in($section->summary);
        if (!$keys) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($keys, SQL_PARAMS_NAMED);
        $params['courseid'] = $section->course;
        $params['sectionid'] = $sectionid;
        $others = $DB->get_records_select(
            'local_edguidance',
            "courseid = :courseid AND cmid = 0 AND sectionid > 0 AND sectionid <> :sectionid AND embedkey $insql",
            $params
        );
        if (!$others) {
            return;
        }

        $rekeyed = [];
        foreach ($others as $row) {
            $rekeyed[$row->embedkey] = self::copy_to_section($row, $sectionid);
        }

        // Straight to the table rather than through course_update_section(), which would fire the
        // event that called this. The cache purge is what that would have done.
        $DB->set_field('course_sections', 'summary', token::rekey($section->summary, $rekeyed), ['id' => $sectionid]);
        \course_modinfo::purge_course_section_cache_by_id((int)$section->course, $sectionid);
        rebuild_course_cache((int)$section->course, false, true);
        guidance::reset_cache();
    }

    /**
     * Hold off, or resume, claiming section summaries in a course.
     *
     * For restore, which fires the same event while it is still sorting out a section's keys. See
     * restore_local_edguidance_plugin::define_section_plugin_structure().
     *
     * @param int $courseid The course.
     * @param bool $hold True to hold off, false to resume.
     */
    public static function hold_claims(int $courseid, bool $hold): void {
        if ($hold) {
            self::$heldclaims[$courseid] = true;
        } else {
            unset(self::$heldclaims[$courseid]);
        }
    }

    /**
     * Copy a section's block, with its files, into another section of the same course.
     *
     * Dismissals are not copied: the copy is a new block.
     *
     * @param \stdClass $row The block to copy.
     * @param int $sectionid The section the copy belongs to.
     * @return string The copy's key.
     */
    protected static function copy_to_section(\stdClass $row, int $sectionid): string {
        global $DB;

        $now = time();
        $copy = clone $row;
        unset($copy->id);
        $copy->sectionid = $sectionid;
        $copy->embedkey = token::new_key();
        $copy->timecreated = $now;
        $copy->timemodified = $now;
        $copy->id = $DB->insert_record('local_edguidance', $copy);

        $fs = get_file_storage();
        $context = \context_course::instance((int)$row->courseid);
        foreach ($fs->get_area_files($context->id, 'local_edguidance', self::FILEAREA, $row->id, 'id', false) as $file) {
            $fs->create_file_from_storedfile(['itemid' => $copy->id], $file);
        }

        return $copy->embedkey;
    }

    /**
     * Delete every block belonging to a course module.
     *
     * @param int $cmid The course module.
     */
    public static function delete_for_cm(int $cmid): void {
        global $DB;

        $ids = $DB->get_fieldset_select('local_edguidance', 'id', 'cmid = :cmid', ['cmid' => $cmid]);
        if (!$ids) {
            return;
        }

        // Nothing in core clears these: they live in each user's own context.
        dismissed::purge($ids);

        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if ($context) {
            get_file_storage()->delete_area_files($context->id, 'local_edguidance', self::FILEAREA);
        }

        $DB->delete_records('local_edguidance', ['cmid' => $cmid]);
        guidance::reset_cache();
    }

    /**
     * Delete a section's blocks, or every section's blocks in a course.
     *
     * Needed when a section is deleted, and when a course's contents are (a restore that deletes
     * the existing content first, say): core deletes those sections without asking anyone.
     *
     * @param int $courseid The course.
     * @param int $sectionid The section, or 0 for every section in the course.
     */
    public static function delete_for_sections(int $courseid, int $sectionid = 0): void {
        global $DB;

        $select = 'courseid = :courseid AND cmid = 0 AND sectionid > 0';
        $params = ['courseid' => $courseid];
        if ($sectionid) {
            $select .= ' AND sectionid = :sectionid';
            $params['sectionid'] = $sectionid;
        }

        $ids = $DB->get_fieldset_select('local_edguidance', 'id', $select, $params);
        if (!$ids) {
            return;
        }

        dismissed::purge($ids);

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            $fs = get_file_storage();
            foreach ($ids as $id) {
                $fs->delete_area_files($context->id, 'local_edguidance', self::FILEAREA, $id);
            }
        }

        $DB->delete_records_select('local_edguidance', $select, $params);
        guidance::reset_cache();
    }

    /**
     * Delete every block in a course, drafts included.
     *
     * Needed because deleting a course removes its modules directly, without the per-module
     * callbacks that delete_for_cm() hangs off. Files go with the contexts.
     *
     * @param int $courseid The course.
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;

        $ids = $DB->get_fieldset_select('local_edguidance', 'id', 'courseid = :courseid', ['courseid' => $courseid]);
        if (!$ids) {
            return;
        }

        dismissed::purge($ids);
        $DB->delete_records('local_edguidance', ['courseid' => $courseid]);
        guidance::reset_cache();
    }

    /**
     * Delete drafts whose activity was never saved.
     *
     * @param int $before Delete drafts last touched before this time.
     * @return int How many were deleted.
     */
    public static function purge_drafts(int $before): int {
        global $DB;

        $drafts = $DB->get_records_select(
            'local_edguidance',
            'cmid = 0 AND sectionid = 0 AND timemodified < :before',
            ['before' => $before],
            '',
            'id, courseid'
        );

        $fs = get_file_storage();
        foreach ($drafts as $draft) {
            $context = \context_course::instance($draft->courseid, IGNORE_MISSING);
            if ($context) {
                $fs->delete_area_files($context->id, 'local_edguidance', self::FILEAREA, $draft->id);
            }
        }

        if ($drafts) {
            dismissed::purge(array_keys($drafts));
            [$insql, $params] = $DB->get_in_or_equal(array_keys($drafts));
            $DB->delete_records_select('local_edguidance', "id $insql", $params);
        }

        return count($drafts);
    }
}
