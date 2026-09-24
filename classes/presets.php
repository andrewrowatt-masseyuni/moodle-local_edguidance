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
 * The site-wide guidance presets.
 *
 * A small, fixed number of slots in admin settings, each a title and a piece of guidance. A
 * guidance block that uses a preset stores only the slot number and reads the text from here on
 * every render, so editing a preset updates every block using it, in every course, at once.
 *
 * Slots rather than a table of their own because there are at most ten and they are edited by an
 * administrator a handful of times a year: config is cached, backed by the standard settings UI,
 * and needs no management pages. The cost is that a slot is the identity - replacing slot 3 with
 * unrelated guidance changes every block that used the old one. That is documented on the
 * settings page.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presets {
    /** @var int How many slots there are. */
    public const MAX = 10;

    /**
     * Every slot in use, as slot => title, in slot order.
     *
     * A slot is in use when it has both a title and some guidance: a titled slot with nothing in it
     * would offer the teacher an empty block.
     *
     * @return array<int, string>
     */
    public static function all(): array {
        $presets = [];
        for ($slot = 1; $slot <= self::MAX; $slot++) {
            if ($preset = self::get($slot)) {
                $presets[$slot] = $preset->title;
            }
        }

        return $presets;
    }

    /**
     * One slot, if it is in use.
     *
     * @param int $slot The slot number.
     * @return \stdClass|null With ->slot, ->title (plain text) and ->guidance (HTML), or null.
     */
    public static function get(int $slot): ?\stdClass {
        if ($slot < 1 || $slot > self::MAX) {
            return null;
        }

        $config = get_config('local_edguidance');
        $title = trim((string)($config->{'presettitle' . $slot} ?? ''));
        $guidance = (string)($config->{'presetguidance' . $slot} ?? '');

        if ($title === '' || html_is_blank($guidance)) {
            return null;
        }

        return (object)[
            'slot' => $slot,
            'title' => $title,
            'guidance' => $guidance,
        ];
    }
}
