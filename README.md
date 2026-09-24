# Teacher guidance (local_edguidance)

Shows guidance to teaching staff, embedded where it is needed: in an activity's description (and so
in its activity card on the course page), in a book chapter, or in a lesson page. Students never see
it.

Each teacher can dismiss a piece of guidance they have finished with. It collapses to a small help
icon whose popover reads "Review teacher guidance for this activity"; clicking the icon brings the
guidance back. Dismissing is personal and never changes what a colleague sees.

Administrators can keep up to ten site-wide **guidance presets**. A teacher can use a preset as it
stands - it cannot be edited, and it updates everywhere the moment the administrator changes it - or
start from a copy and edit that.

This plugin replaces `mod_ednote`, which put guidance in a separate label-like activity between the
cards.

## The three plugins

Guidance takes three plugins, because Moodle has three extension points it needs and each belongs to
a different plugin type:

| Plugin | Why it has to be its own plugin |
| --- | --- |
| `local_edguidance` (this one) | Everything else - storage, presets, rendering, dismissing, backup. It is a *local* plugin because core only lets `format`, `report`, `plagiarism`, `local` and `tool` plugins add data to another module's backup (`backup/moodle2/backup_stepslib.php`), and guidance has to follow its activity through backup, restore, duplicate and course copy. |
| `filter_edguidance` | Turns an embedded token into the guidance, for teachers only. Only a filter sees every piece of formatted text. |
| `tiny_edguidance` | The TinyMCE button teachers use to add and edit guidance. |

Both of the others declare a dependency on this one.

## Requirements

| | |
| --- | --- |
| Moodle | 4.5 (build 2024100700). `$plugin->supported` is pinned to `[405, 405]`. |
| PHP | 8.1 or later. |
| Editor | TinyMCE, for adding guidance. Guidance already in the text is shown whichever editor is in use. |

The pin is deliberate: the course page fallback (see *Guidance in the activity card*) leans on core
internals a later release could move.

## Installation

1. Install all three plugins:

   ```
   local/edguidance
   filter/edguidance
   lib/editor/tiny/plugins/edguidance
   ```

2. Visit **Site administration > Notifications**, or run `php admin/cli/upgrade.php`.

The filter switches itself on site-wide when it is installed, at the end of the filter order. Without
it nobody sees any guidance, so check it is still **On** under *Site administration > Plugins >
Filters > Manage filters* if guidance ever disappears everywhere.

Presets are optional: *Site administration > Plugins > Local plugins > Teacher guidance*.

Who can read guidance is decided entirely by **View teacher guidance** (`local/edguidance:view`),
given to the non-editing teacher, editing teacher and manager archetypes and withheld from students.
Who can add it is **Add and edit teacher guidance** (`local/edguidance:manage`): editing teacher and
manager.

## Usage

### Adding guidance

In an activity description, a book chapter or a lesson page, use the **Teacher guidance** button on
the editor toolbar (also under **Insert**):

* **Use a preset** > *title* - links the site preset. Nothing to type; it cannot be edited, and it
  follows the administrator's changes.
* **Start with a preset** > *title* - opens the guidance editor with a copy of the preset, to edit.
  The copy is your own and does not change when the preset does.
* **Start with blank** - opens the guidance editor empty.

The guidance appears in the editor as a labelled chip. Click it (or choose **Edit this guidance**
with the cursor on it) to change it, to switch it to a different preset, or to turn a linked preset
into an editable copy by choosing *My own text*. Delete the chip to remove the guidance.

### Where it shows

* **Activity description**, with *Display description on course page* ticked: where the chip is in
  the description, in the activity card.
* **Activity description**, with that setting off: at the foot of the activity card (Boost), or in
  the card's top row (Snap). The rest of the description stays off the course page, as before.
* **Book chapter** and **lesson page**: where the chip is.

A student sees the text around the guidance and nothing of the guidance. A description that was
nothing but guidance shows the student no description at all.

## Technical details

### The token

What goes into the text is an empty element:

```html
<div class="edguidance-embed" data-edguidance="0123456789abcdef"></div>
```

The key is 16 random hex characters. The guidance itself lives in the `local_edguidance` table, and
**never in the host text**. That is the reason for the whole design. Guidance written into a book
chapter and hidden by a filter would still reach students:

* through global search, which indexes chapter text without running filters
  (`mod/book/classes/search/chapter.php`);
* through lesson web services, which return page contents with filters off by default, or raw on
  request (`mod/lesson/classes/external.php`);
* through any editing teacher, who can switch a filter off in their own course (`filter/manage.php`).

With only the token in the text, all three expose nothing. Where the filter does not run, the token
renders as nothing: TinyMCE pads an empty div with `&nbsp;`, so `styles.css` hides unreplaced tokens.

The token survives because every host this is for displays with `noclean`: book chapters
(`mod/book/view.php`), lesson pages (`lesson_page::get_contents()`) and activity descriptions, which
`format_module_intro()` caches unfiltered and `cm_info::get_formatted_content()` filters per request.
HTMLPurifier strips `data-*` attributes, so text that *is* cleaned - anywhere, or everywhere under
`$CFG->forceclean` - loses the token and shows no guidance. That fails closed, and
`filter_edguidance`'s tests pin it.

A key belongs to one activity. Lookups are always by (activity, key), so a token copied into another
activity does not resolve there: editors see a "not found" notice and everyone else sees nothing.

### Data

| Column | Role |
| --- | --- |
| `courseid`, `cmid` | Where the block belongs. `cmid = 0` is a *draft* - see below. |
| `embedkey` | Matches the token. Unique per activity. Never remapped. |
| `introorder` | 0 if the block is not in the activity description; otherwise its position there. |
| `presetslot` | 0 for the block's own text; 1-10 to use that site preset. |
| `guidance`, `guidanceformat` | Own text - or, for a preset block, a snapshot taken when it was linked. |

Files embedded in a block's own text live in filearea `guidance`, itemid = the row id, in the
activity's context, served by `local_edguidance_pluginfile()` to holders of `view` only.

### Resolving the text

`guidance::resolve()` decides what a block shows, on every request:

1. the site preset, if the block uses one and the slot is still in use;
2. otherwise the block's own text - which for a preset block is the snapshot, flagged as possibly out
   of date so nobody acts on stale guidance;
3. otherwise nothing.

Rows are read a whole course at a time (`guidance::for_course()`) and held for the request: a course
page can run the filter over a dozen descriptions, and one query per course keeps that flat.

Guidance text is formatted with the filters on, and `filter_edguidance` is one of them. While a block
is being formatted `guidance::is_rendering()` is true and the filter strips any token instead of
rendering a block inside a block.

### Site presets

Ten fixed slots in admin settings (`presettitle{n}`, `presetguidance{n}`), read with `get_config()`,
so they are cached and need no pages of their own. A slot is in use only with both a title and some
guidance.

A slot *is* the identity: a block stores `presetslot = 3` and shows whatever slot 3 holds. Replacing
slot 3 with unrelated guidance changes every block that used it; emptying it makes those blocks fall
back to their snapshots. The settings page says so.

Presets are the **only** thing that stays live. Copying an activity - duplicate, backup and restore,
course copy, `mod_edpreset` - copies a block's own text as it stands, but carries `presetslot`
verbatim, so a preset block stays live in every copy. `presetslot` names a site setting rather than
anything in the course, so on another site it shows that site's preset in that slot (presets are
generic guidance, so nothing course-private can leak) or the snapshot if the slot is empty.

### Guidance in the activity card

With *Display description on course page* ticked, the description is filtered when the card renders,
and the filter puts the block where its token sits. Nothing else is needed - in either theme, and on
every path that re-renders a card over AJAX, because all of them format the description.

With it unticked, the description never reaches the course page, so `local\card_injector` puts the
description's blocks into the card's **afterlink** instead: the slot Boost prints at the foot of the
card (`course/format/templates/local/content/cm/activity.mustache`) and Snap prints in its card meta
row (`theme/snap/classes/output/core/course_renderer.php`). **No theme is changed.**

Core 4.5 has no hook for adding to another module's card - afterlink is normally set by the owning
module's own `_cm_info_view()`. This works because:

* the course page renders from the same per-request `course_modinfo` instance that
  `get_fast_modinfo()` returns (`lib/modinfolib.php`), and `cm_info::set_after_link()` has no state
  check;
* it runs early enough: from the `before_http_headers` hook on course pages (`course/view.php` sets
  the page type before calling `header()`, and renders the content after), and from
  `local_edguidance_override_webservice_execution()` before the web services that redraw a card
  (`core_get_fragment` for `core_courseformat` / `theme_snap`, `core_course_get_module`,
  `core_course_edit_module`). That callback always returns `false`, so core then runs the real
  function against the primed modinfo;
* it reads the existing afterlink first, which runs the module's own `_cm_info_view()` so that it
  cannot overwrite ours later, and appends.

Known limitation, accepted: Snap redraws some cards through its own web services (after *Duplicate*,
or a completion or availability refresh). Those cards show no fallback guidance until the page is
reloaded.

Snap shows a page's or book's description in its card even with the setting off, which would show a
block twice. `card_injector` records every block it placed, and the filter skips those.

Only the description's blocks go in the card, which is why `introorder` exists: the course page must
tell description blocks from chapter and page blocks without reading every module's intro. It is
recorded by `local_edguidance_coursemodule_edit_post_actions()` from the saved intro whenever an
activity is saved through its settings form - so the record follows what is actually in the text.

`tests/card_injector_test.php` renders a real card, and a real `cmitem` fragment, after priming. A
core change that breaks any of the above fails a test rather than quietly emptying cards.

### Drafts

The description on the *add an activity* form is written before the activity exists, so a block
made there cannot belong to it yet. The editor's context there is the course, and
`api::embed_target()` turns that into a **draft**: `cmid = 0`, files in the course context. When
the activity is saved, `api::adopt_intro()` finds the draft by (course, key), moves its files into
the new module context and gives it the activity's id. A daily task (`purge_drafts`) deletes drafts
more than a day old, which is what a cancelled form leaves behind.

The editor button is offered in a course context only on `/course/modedit.php`, so there is no other
way to make a draft.

### Dismissing

Per user, per block, in `core_favourites` (component `local_edguidance`, item type `dismissed`,
item id = the row id) in the user's own context - the same store `mod_ednote` used, for the same
reasons: it is core's general "this user has flagged this item" store, with a privacy story and a
bulk read. Keyed on the row rather than the text, so editing guidance, or an administrator rewording
a preset, does not quietly un-dismiss it.

`dismissed::set()` checks before writing: core's `create_favourite()` inserts into a unique index and
`delete_favourite()` throws when there is nothing to delete, so a double click would otherwise be a
500.

Both states are rendered on the server (`templates/block.mustache`) and the server's dismissed state
decides which starts hidden; `amd/src/guidance.js` only toggles them. The popover is created by hand
with `trigger: 'manual'` rather than `data-toggle="popover"`, because `theme_boost/loader` shows every
`data-toggle` popover on click, which would fight clicking the icon to restore.

Click handling is delegated from the document in the capture phase and stops there: a block can sit
inside a card that is itself a link (Snap's resource cards), and dismissing must not open the
activity. The JavaScript is loaded from `before_footer_html_generation` for anyone holding `view` on
the page, rather than only when a block rendered, because blocks also arrive after load in cards
redrawn over AJAX.

`mod_lesson` formats page contents twice when showing them as feedback, so the rendered block never
contains the token attribute and a second filter pass is a no-op.

### Deleting

Nothing in core deletes this plugin's rows or anyone's dismissals, so:

* `local_edguidance_pre_course_module_delete()` deletes an activity's blocks and purges their
  favourites. `tool_recyclebin`'s own callback runs first (tool before local), so a recycled activity
  is backed up with its guidance;
* `\core_course\hook\before_course_deleted` does the same for a course, because course deletion
  removes modules without the per-module callbacks.

### Backup and restore

`backup_local_edguidance_plugin` adds an activity's blocks to its `module.xml`, which covers course
backup and restore, import, course copy, *Duplicate*, the recycle bin and `mod_edpreset`'s copies.
`embedkey` and `presetslot` are carried verbatim - the token in the restored text matches the same
key.

`restore_local_edguidance_plugin` maps the blocks and restores their files in
`after_restore_module()`, not as each block is processed. The plugin hangs off the module step, which
runs before the activity's own step has told the task the module's old context id, and a file mapping
made then records 0 and matches no file.

Dismissals are a preference of each user, not course content, and are not backed up.

### Privacy

Blocks are course content and name nobody. The only personal data is which blocks a teacher has
dismissed, in `core_favourites` in their own context; `privacy\provider` declares that link and
exports and deletes only there.

## Testing

```
vendor/bin/phpunit --testsuite local_edguidance_testsuite
vendor/bin/phpunit --testsuite filter_edguidance_testsuite
vendor/bin/phpunit --testsuite tiny_edguidance_testsuite
php admin/tool/behat/cli/run.php --tags=@local_edguidance
```

Behat covers card guidance with the description shown and hidden, in Boost and in Snap; dismiss and
restore; per-teacher dismissal; a preset updating live; book chapters and lesson pages; and adding
guidance with the editor button.

Two things to know when adding Behat coverage:

* `mod_book`'s generator makes chapters in `FORMAT_MOODLE`, which TinyMCE does not edit - Moodle
  quietly picks another editor and there is no button. Give chapters `contentformat` 1.
* The `Insert > ...` menu step cannot reach a third menu level. Click the toolbar button and pick
  menu items by `[role^='menuitem'][aria-label='...']` instead, as `editor.feature` does.

Static caches (`guidance`, `dismissed`, `card_injector`) are keyed on ids PHPUnit reuses between
tests; reset them in `setUp()`.

## License

GNU GPL v3 or later - see [LICENSE](LICENSE).

## Author

Andrew Rowatt &lt;A.J.Rowatt@massey.ac.nz&gt;, Massey University.
