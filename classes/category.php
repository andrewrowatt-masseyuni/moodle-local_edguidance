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
 * The kinds of guidance a block can be, which decide how it looks and what marking it off is called.
 *
 * A category belongs to the block, not to the text it shows: a block that uses a preset has one of
 * its own, and changing it never unlinks the preset. It can be changed whenever the block is edited.
 *
 * Stored by name rather than number, so that the backup and the table say what they mean. A name
 * this version does not know - from a later version's backup, say - is shown as a note.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category {
    /** @var string The default: something worth knowing. */
    public const NOTE = 'note';

    /** @var string Something the teacher is advised to do. */
    public const RECOMMENDATION = 'recommendation';

    /** @var string Something the teacher must do. */
    public const TASK = 'task';

    /** @var string Something the teacher may do. */
    public const OPTIONALTASK = 'optionaltask';

    /** @var string[] Every category, in the order they are offered. */
    public const ALL = [self::NOTE, self::RECOMMENDATION, self::TASK, self::OPTIONALTASK];

    /**
     * Whether a name is a category.
     *
     * @param string $category The name.
     * @return bool
     */
    public static function is_valid(string $category): bool {
        return in_array($category, self::ALL, true);
    }

    /**
     * A stored category, or a note for anything else.
     *
     * @param string|null $category The stored name.
     * @return string
     */
    public static function normalise(?string $category): string {
        return $category !== null && self::is_valid($category) ? $category : self::NOTE;
    }

    /**
     * Whether a category is something to do, which the teacher marks as complete rather than as read.
     *
     * @param string $category The name.
     * @return bool
     */
    public static function is_task(string $category): bool {
        return $category === self::TASK || $category === self::OPTIONALTASK;
    }

    /**
     * A category's name, for people.
     *
     * @param string $category The name.
     * @return string
     */
    public static function name(string $category): string {
        return get_string('category' . self::normalise($category), 'local_edguidance');
    }

    /**
     * Every category, as name => name for people, for a select.
     *
     * @return array<string, string>
     */
    public static function options(): array {
        $options = [];
        foreach (self::ALL as $category) {
            $options[$category] = self::name($category);
        }

        return $options;
    }
}
