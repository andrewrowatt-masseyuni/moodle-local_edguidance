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
 * One guidance block, wherever it appears: in an activity card, a book chapter or a lesson page.
 *
 * Both states are exported - the guidance, and the help icon it collapses to once dismissed - and
 * the server decides which one starts hidden. The AMD module only toggles between them, so
 * restoring needs no round trip for markup and nothing is rendered client-side.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block implements \renderable, \templatable {
    /**
     * Constructor.
     *
     * @param \stdClass $row The local_edguidance row.
     * @param \stdClass $resolved What guidance::resolve() returned for it.
     */
    public function __construct(
        /** @var \stdClass The local_edguidance row. */
        protected \stdClass $row,
        /** @var \stdClass What guidance::resolve() returned for it. */
        protected \stdClass $resolved,
    ) {
    }

    /**
     * Data for the template.
     *
     * @param \renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        return [
            'id' => (int)$this->row->id,
            'body' => $this->resolved->content,
            'missing' => $this->resolved->missing,
            'dismissed' => dismissed::is_dismissed((int)$this->row->id),
        ];
    }

    /**
     * Render one row, or nothing if it has nothing to say.
     *
     * Callers are responsible for the capability check: this renders for whoever asks.
     *
     * The output never contains the token attribute, which is what makes a second filter pass over
     * already-filtered text a no-op. mod_lesson does exactly that to page contents shown as
     * feedback.
     *
     * @param \stdClass $row The local_edguidance row.
     * @return string HTML, or '' if the block resolves to nothing.
     */
    public static function render_row(\stdClass $row): string {
        global $OUTPUT;

        $resolved = guidance::resolve($row);
        if ($resolved->content === '' && !$resolved->missing) {
            return '';
        }

        $block = new self($row, $resolved);

        return $OUTPUT->render_from_template('local_edguidance/block', $block->export_for_template($OUTPUT));
    }
}
