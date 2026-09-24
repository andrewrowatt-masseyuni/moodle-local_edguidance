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

    /**
     * Where a block created from an editor in this context belongs.
     *
     * An editor in a module context - a chapter, a lesson page, an existing activity's description
     * - makes a block for that module. An editor in a course context is the description on the
     * "add an activity" form, where the module does not exist yet: that makes a draft, with cm id
     * 0, which the activity adopts when it is saved.
     *
     * @param \context $context The editor's context.
     * @return int[] [course id, cm id or 0 for a draft]
     * @throws \invalid_parameter_exception For any other context.
     */
    public static function embed_target(\context $context): array {
        if ($context instanceof \context_module) {
            $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
            return [(int)$cm->course, (int)$cm->id];
        }

        if ($context instanceof \context_course && (int)$context->instanceid !== SITEID) {
            return [(int)$context->instanceid, 0];
        }

        throw new \invalid_parameter_exception('Teacher guidance can only be embedded in an activity.');
    }

    /**
     * A block by key, within the context an editor is working in.
     *
     * Always scoped to that context, so a key copied from another activity cannot be used to read or
     * edit a block that belongs somewhere else.
     *
     * @param \context $context The editor's context.
     * @param string $key The block's key.
     * @return \stdClass|null
     */
    public static function get_embed(\context $context, string $key): ?\stdClass {
        global $DB;

        [$courseid, $cmid] = self::embed_target($context);

        return $DB->get_record('local_edguidance', [
            'courseid' => $courseid,
            'cmid' => $cmid,
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
     * @return string The block's key.
     * @throws \invalid_parameter_exception If the preset slot is not in use.
     */
    public static function save_embed(\context $context, ?string $key, int $presetslot, ?array $editor = null): string {
        global $DB;

        $preset = null;
        if ($presetslot > 0 && !($preset = presets::get($presetslot))) {
            throw new \invalid_parameter_exception('That teacher guidance preset is not in use.');
        }

        [$courseid, $cmid] = self::embed_target($context);
        $now = time();

        $row = ($key !== null && $key !== '') ? self::get_embed($context, $key) : null;
        if (!$row) {
            $row = (object)[
                'courseid' => $courseid,
                'cmid' => $cmid,
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

            $draft = $DB->get_record('local_edguidance', ['courseid' => $cm->course, 'cmid' => 0, 'embedkey' => $key]);
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
            'cmid = 0 AND timemodified < :before',
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
