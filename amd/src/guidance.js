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

/**
 * Dismissing teacher guidance, and undoing that; and ticking the items on its checklists.
 *
 * Dismissing does not remove the block from the page it was clicked on. The server has already
 * recorded the choice, so the block is gone on the next page load; removing it at once would make
 * an accidental click hard to recover from. Instead the guidance is swapped for a line saying what
 * will happen, with an undo. After that, the dismissed guidance page is the way back.
 *
 * A checklist's ticks are shared by every teacher and kept in the guidance itself, so a tick is
 * saved as it is made. If it cannot be - the guidance has been edited since the page was loaded,
 * say - the box goes back to how it was and the reason is shown.
 *
 * Every listener is delegated from the document, once per page, so blocks that arrive after the
 * page has loaded - a course page card re-rendered over AJAX - work without re-initialising.
 *
 * @module     local_edguidance/guidance
 * @copyright  2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';

const SELECTORS = {
    BLOCK: '[data-region="edguidance"]',
    LIVE: '[data-region="edguidance-live"]',
    DISMISSED: '[data-region="edguidance-dismissed"]',
    DISMISS: '[data-action="edguidance-dismiss"]',
    UNDO: '[data-action="edguidance-undo"]',
    CHECK: '[data-action="edguidance-check"]',
    CHECKITEM: '.edguidance-checkitem',
};

let initialised = false;

/**
 * Record a dismissal or an undo against the current user.
 *
 * @param {number} guidanceid The block.
 * @param {boolean} dismissed Whether it should be dismissed.
 * @returns {Promise}
 */
const setDismissed = (guidanceid, dismissed) => fetchMany([{
    methodname: 'local_edguidance_set_dismissed',
    args: {guidanceid, dismissed},
}])[0];

/**
 * Tick or untick a checklist item, for every teacher.
 *
 * @param {number} guidanceid The block.
 * @param {HTMLInputElement} box The item's checkbox.
 * @returns {Promise}
 */
const setChecked = (guidanceid, box) => fetchMany([{
    methodname: 'local_edguidance_set_checked',
    args: {
        guidanceid,
        index: parseInt(box.dataset.checkindex, 10),
        hash: box.dataset.checkhash,
        checked: box.checked,
    },
}])[0];

/**
 * Save a tick as it is made, or put the box back if it cannot be saved.
 *
 * The box is disabled while the tick is saved, so that a second click cannot race the first; and
 * focus, which disabling takes away, is given back.
 *
 * @param {HTMLElement} block The block root.
 * @param {HTMLInputElement} box The item's checkbox.
 */
const tick = async(block, box) => {
    const pending = new Pending('local_edguidance/guidance:setchecked');
    const focused = document.activeElement === box;
    box.disabled = true;

    try {
        await setChecked(parseInt(block.dataset.guidanceid, 10), box);
    } catch (error) {
        box.checked = !box.checked;
        Notification.exception(error);
    }

    box.disabled = false;
    if (focused) {
        box.focus();
    }
    pending.resolve();
};

/**
 * Swap a block between its guidance and the "you have dismissed this" confirmation.
 *
 * The Dismiss button is one of the live regions, so there is no state in which the block is
 * dismissed but still offering to dismiss itself.
 *
 * @param {HTMLElement} block The block root.
 * @param {boolean} dismissed Whether to show the confirmation.
 */
const showState = (block, dismissed) => {
    block.querySelectorAll(SELECTORS.LIVE).forEach((live) => live.toggleAttribute('hidden', dismissed));
    block.querySelector(SELECTORS.DISMISSED)?.toggleAttribute('hidden', !dismissed);
    block.classList.toggle('edguidance-is-dismissed', dismissed);

    // Keep a keyboard user where they were, rather than dropping focus on the element just hidden.
    block.querySelector(dismissed ? SELECTORS.UNDO : SELECTORS.DISMISS)?.focus();
};

/**
 * Record the choice, then swap the block over.
 *
 * Written with try/catch rather than a promise chain because core/ajax hands back a jQuery
 * Deferred, not a native promise: it has no .finally(), and calling one would leave the Pending
 * unresolved and the page permanently "not ready".
 *
 * @param {HTMLElement} block The block root.
 * @param {boolean} dismissed Whether it should be dismissed.
 */
const apply = async(block, dismissed) => {
    const pending = new Pending('local_edguidance/guidance:setdismissed');

    try {
        await setDismissed(parseInt(block.dataset.guidanceid, 10), dismissed);
        showState(block, dismissed);
    } catch (error) {
        Notification.exception(error);
    }

    pending.resolve();
};

/**
 * Wire up the page.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    // Capture phase, and stopped there: a block can sit inside a card that is itself clickable -
    // theme_snap turns whole resource cards into links - and dismissing guidance, or ticking an item,
    // must not also open the activity.
    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;

        // Stopped, but not prevented: the box still ticks, and its change is handled below.
        if (target?.closest(SELECTORS.CHECKITEM)?.closest(SELECTORS.BLOCK)) {
            event.stopPropagation();
            return;
        }

        const control = target?.closest(`${SELECTORS.DISMISS}, ${SELECTORS.UNDO}`);
        const block = control?.closest(SELECTORS.BLOCK);
        if (!block) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        apply(block, control.matches(SELECTORS.DISMISS));
    }, true);

    document.addEventListener('change', (event) => {
        const box = event.target instanceof Element ? event.target.closest(SELECTORS.CHECK) : null;
        const block = box?.closest(SELECTORS.BLOCK);
        if (block) {
            tick(block, box);
        }
    });
};
