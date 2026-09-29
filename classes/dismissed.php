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

use core_favourites\service_factory;

/**
 * Which guidance blocks the current user has dismissed.
 *
 * Dismissing is always personal: one teacher tidying their view never changes what a colleague on
 * the same course sees. A dismissed block renders nothing for that teacher, on the page or in the
 * editor, so dismissed.php - listing what they have dismissed in a course - is the way back.
 *
 * Keyed on the guidance row id rather than on the text, so that editing a block, or an
 * administrator rewording the preset it uses, does not silently un-dismiss it for everyone.
 *
 * State lives in core_favourites rather than a table of this plugin's own, as mod_ednote's did.
 * "Favourite" reads oddly for a negative flag, but the table is core's general "this user has
 * flagged this item" store and it brings a privacy story and a bulk-read API with it. Rows are
 * stored against the user's own context: they describe a preference of the user rather than a
 * thing in a course.
 *
 * @package    local_edguidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dismissed {
    /** @var string The favourites component these rows belong to. */
    public const COMPONENT = 'local_edguidance';

    /** @var string The favourites item type, keyed on local_edguidance.id. */
    public const ITEMTYPE = 'dismissed';

    /**
     * Dismissed guidance ids, keyed by user id.
     *
     * Keyed by user rather than just held for "the current user" because the user can change
     * within a request - "log in as" and cron both do it.
     *
     * @var array<int, array<int, true>>
     */
    protected static $cache = [];

    /**
     * The current user's dismissed guidance ids.
     *
     * Read once per request and held: a course page asks about every block on it.
     *
     * @return array<int, true>
     */
    public static function get_for_user(): array {
        global $USER;

        $userid = (int)($USER->id ?? 0);

        if (isset(self::$cache[$userid])) {
            return self::$cache[$userid];
        }

        if (!isloggedin() || isguestuser()) {
            return self::$cache[$userid] = [];
        }

        $service = service_factory::get_service_for_user_context(\context_user::instance($userid));

        $ids = [];
        foreach ($service->find_favourites_by_type(self::COMPONENT, self::ITEMTYPE) as $favourite) {
            $ids[(int)$favourite->itemid] = true;
        }

        return self::$cache[$userid] = $ids;
    }

    /**
     * Whether the current user has dismissed a block.
     *
     * @param int $guidanceid The local_edguidance id.
     * @return bool
     */
    public static function is_dismissed(int $guidanceid): bool {
        return isset(self::get_for_user()[$guidanceid]);
    }

    /**
     * Dismiss or restore a block for the current user.
     *
     * @param int $guidanceid The local_edguidance id.
     * @param bool $dismiss True to dismiss, false to restore.
     */
    public static function set(int $guidanceid, bool $dismiss): void {
        global $USER;

        $usercontext = \context_user::instance($USER->id);
        $service = service_factory::get_service_for_user_context($usercontext);

        // Both calls are unguarded in core - create_favourite() inserts straight into a table with
        // a unique index, and delete_favourite() throws when there is nothing to delete - so a
        // double-click or a second browser tab would otherwise produce a 500.
        $exists = $service->favourite_exists(self::COMPONENT, self::ITEMTYPE, $guidanceid, $usercontext);
        if ($dismiss && !$exists) {
            $service->create_favourite(self::COMPONENT, self::ITEMTYPE, $guidanceid, $usercontext);
        } else if (!$dismiss && $exists) {
            $service->delete_favourite(self::COMPONENT, self::ITEMTYPE, $guidanceid, $usercontext);
        }

        self::reset_cache();
    }

    /**
     * Drop every user's dismissal of some blocks.
     *
     * Called when the blocks are deleted. Rows live in each user's own context, so nothing in core
     * cleans them up on our behalf. Passing no context is what makes this cross every user rather
     * than only the one doing the deleting.
     *
     * @param int[] $guidanceids The local_edguidance ids.
     */
    public static function purge(array $guidanceids): void {
        if (!$guidanceids) {
            return;
        }

        $service = service_factory::get_service_for_component(self::COMPONENT);
        foreach ($guidanceids as $guidanceid) {
            $service->delete_favourites_by_type_and_item(self::ITEMTYPE, (int)$guidanceid);
        }

        self::reset_cache();
    }

    /**
     * Forget what was read for this request.
     */
    public static function reset_cache(): void {
        self::$cache = [];
    }
}
