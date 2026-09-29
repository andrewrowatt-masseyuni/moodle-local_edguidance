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

namespace local_edguidance\form;

use core_form\dynamic_form;
use local_edguidance\api;
use local_edguidance\dismissed;
use local_edguidance\presets;

/**
 * Write or choose the guidance for one embedded block.
 *
 * Opened by tiny_edguidance in a modal, for "Start with a preset", "Start with blank" and editing a
 * block already in the text. ("Use a preset" needs no form: see external\embed_preset.)
 *
 * One "source" select drives the whole form. A preset is read-only - the form shows a preview in
 * place of the editor - and stays linked, so the administrator's edits reach it. "My own text"
 * shows the editor. The editor is always pre-filled with whatever the block shows now, so turning
 * a preset block into an editable copy is a single change of the select.
 *
 * Arguments: contextid (the editor's context), and optionally sectionid (the section whose summary
 * is being edited, in a course context), key (the block being edited) and startslot (a preset to
 * copy into the editor for a new block).
 *
 * A block the current user has dismissed opens with a notice saying so, which also carries the
 * block's id: tiny_edguidance offers to restore the block when, and only when, the notice is there.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class embed_form extends dynamic_form {
    /** @var \stdClass|null|false The block being edited, null for none, or false before it is looked up. */
    protected $row = false;

    /**
     * The editor's context: an activity, or the course for a section summary or while an activity
     * is being added.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $context = \context::instance_by_id($this->optional_param('contextid', 0, PARAM_INT));
        // Throws for any context a block cannot belong to, or a section not in the course.
        api::embed_target($context, $this->get_sectionid());

        return $context;
    }

    /**
     * The section whose summary is being edited, or 0.
     *
     * @return int
     */
    protected function get_sectionid(): int {
        return $this->optional_param('sectionid', 0, PARAM_INT);
    }

    /**
     * The block being edited, if there is one here.
     *
     * @return \stdClass|null
     */
    protected function get_row(): ?\stdClass {
        if ($this->row === false) {
            $key = $this->optional_param('key', '', PARAM_ALPHANUM);
            $this->row = $key !== ''
                ? api::get_embed($this->get_context_for_dynamic_submission(), $key, $this->get_sectionid())
                : null;
        }

        return $this->row;
    }

    /**
     * Only people who may manage guidance may write it.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('local/edguidance:manage', $this->get_context_for_dynamic_submission());
    }

    /**
     * The page this form is notionally on.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return $this->get_context_for_dynamic_submission()->get_url();
    }

    /**
     * The form.
     */
    public function definition() {
        $mform = $this->_form;
        $context = $this->get_context_for_dynamic_submission();

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('hidden', 'sectionid');
        $mform->setType('sectionid', PARAM_INT);

        $mform->addElement('hidden', 'key');
        $mform->setType('key', PARAM_ALPHANUM);

        // Editing is not undismissing: the block stays out of this teacher's way until they say so.
        $row = $this->get_row();
        if ($row && dismissed::is_dismissed((int)$row->id)) {
            $mform->addElement('html', \html_writer::div(
                get_string('formdismissed', 'local_edguidance'),
                'alert alert-info',
                ['data-region' => 'edguidance-dismissednotice', 'data-guidanceid' => (int)$row->id]
            ));
        }

        $presets = presets::all();

        if ($presets) {
            $options = [0 => get_string('sourceown', 'local_edguidance')];
            foreach ($presets as $slot => $title) {
                // Unescaped here because the select element escapes its option labels itself.
                $title = format_string($title, true, ['context' => $context, 'escape' => false]);
                $options[$slot] = get_string('sourcepreset', 'local_edguidance', $title);
            }
            $mform->addElement('select', 'source', get_string('source', 'local_edguidance'), $options);
            $mform->addHelpButton('source', 'source', 'local_edguidance');

            foreach (array_keys($presets) as $slot) {
                $preview = format_text(presets::get($slot)->guidance, FORMAT_HTML, ['context' => $context]);
                $mform->addElement(
                    'static',
                    'preview' . $slot,
                    get_string('presetpreview', 'local_edguidance'),
                    \html_writer::div($preview, 'edguidance-preview')
                );
                $mform->hideIf('preview' . $slot, 'source', 'neq', $slot);
            }
        } else {
            $mform->addElement('hidden', 'source', 0);
        }
        $mform->setType('source', PARAM_INT);

        $mform->addElement(
            'editor',
            'guidance_editor',
            get_string('guidance', 'local_edguidance'),
            null,
            api::editor_options($context)
        );
        $mform->setType('guidance_editor', PARAM_RAW);
        if ($presets) {
            $mform->hideIf('guidance_editor', 'source', 'neq', 0);
        }
    }

    /**
     * Validation.
     *
     * @param array $data The submitted data.
     * @param array $files The submitted files.
     * @return array Errors, keyed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $source = (int)($data['source'] ?? 0);
        if ($source > 0 && !presets::get($source)) {
            $errors['source'] = get_string('presetunavailable', 'local_edguidance');
        }
        if ($source === 0 && html_is_blank($data['guidance_editor']['text'] ?? '')) {
            $errors['guidance_editor'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Load the block being edited, or the preset being started from.
     */
    public function set_data_for_dynamic_submission(): void {
        $context = $this->get_context_for_dynamic_submission();
        $startslot = $this->optional_param('startslot', 0, PARAM_INT);

        $row = $this->get_row();

        $source = 0;
        $text = '';
        $format = FORMAT_HTML;
        $itemid = null;

        if ($row) {
            $preset = (int)$row->presetslot > 0 ? presets::get((int)$row->presetslot) : null;
            if ($preset) {
                $source = (int)$preset->slot;
                // Pre-filled so that switching to "My own text" starts from what the block shows.
                $text = $preset->guidance;
            } else {
                // The block's own text - or, for a preset whose slot has since been emptied, the
                // snapshot it was showing, which saving here turns into its own text.
                $text = (string)$row->guidance;
                $format = (int)$row->guidanceformat;
                $itemid = (int)$row->id;
            }
        } else if ($startslot && ($preset = presets::get($startslot))) {
            $text = $preset->guidance;
        }

        $data = file_prepare_standard_editor(
            (object)['guidance' => $text, 'guidanceformat' => $format],
            'guidance',
            api::editor_options($context),
            $context,
            'local_edguidance',
            api::FILEAREA,
            $itemid
        );

        $this->set_data([
            'contextid' => $context->id,
            'sectionid' => $this->get_sectionid(),
            'key' => $row ? $row->embedkey : '',
            'source' => $source,
            'guidance_editor' => $data->guidance_editor,
        ]);
    }

    /**
     * Save the block.
     *
     * @return array ['key' => the block's key, for the editor to put in the token]
     */
    public function process_dynamic_submission() {
        $context = $this->get_context_for_dynamic_submission();
        $data = $this->get_data();
        $source = (int)$data->source;

        $key = api::save_embed(
            $context,
            $data->key !== '' ? $data->key : null,
            $source,
            $source > 0 ? null : $data->guidance_editor,
            $this->get_sectionid()
        );

        return ['key' => $key];
    }
}
