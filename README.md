# Teacher guidance (local_edguidance)

Shows guidance to teaching staff, embedded where it is needed: in an activity's description (and so
in its activity card on the course page), in a book chapter, in a lesson page, or in a section
summary. Students never see it.

Each piece of guidance is a **note**, a **recommendation**, a **task** or an **optional task**, each
with its own colour, and can have a heading. Any of them can hold a **checklist**, whose ticks are
shared: a teacher ticks an item off, and every teacher sees it ticked.

Each teacher can mark a piece of guidance as read once they have finished with it. From the next
page load it is gone for them - from the page and from the editor - until they restore it from
**Teacher guidance marked as read** in the course navigation. Marking guidance as read is personal and
never changes what a colleague sees.

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
manager. Who can tick its checklists is **Tick items on teacher guidance checklists**
(`local/edguidance:tick`): the same roles as *view*, so a non-editing teacher can tick off their part.

## Usage

### Adding guidance

In an activity description, a book chapter, a lesson page or a section summary (**Edit section**),
use the **Teacher guidance** button on the editor toolbar (also under **Insert**):

* **Use a preset** > *title* - links the site preset. Nothing to type; it cannot be edited, and it
  follows the administrator's changes.
* **Start with a preset** > *title* - opens the guidance editor with a copy of the preset, to edit.
  The copy is your own and does not change when the preset does.
* **Start with blank** - opens the guidance editor empty.

The guidance editor also sets the guidance's **Category** and an optional **Heading**, shown above
the guidance:

| Category | Colour | Marked off with |
| --- | --- | --- |
| Note (the default) | Yellow | *Mark as read* |
| Recommendation | Blue | *Mark as read* |
| Task | Red | *Mark as complete* |
| Optional task | Orange | *Mark as complete* |

Both belong to this piece of guidance, not to a preset: a linked preset can be a task here and a note
elsewhere, with a heading of its own, and still follow the administrator's changes. *Use a preset*
makes a note with no heading. Either can be changed whenever the guidance is edited.

The guidance appears in the editor as it will on the page, with *Click to edit* where the page has
*Mark as read* or *Mark as complete*. Guidance you have marked as read does not appear at all, as on the page, until you
choose **Show guidance marked as read** from the same toolbar button: then it appears in full over a
light hatch, marked *Marked as read - click to edit* (*Marked as complete*, for a task), for as long
as you stay in that editor. Click it (or choose
**Edit this guidance** with the cursor on it) to change it, to switch it to a different preset, or
to turn a linked preset into an editable copy by choosing *My own text*. The same form offers
**Delete**, which asks first - once the text is saved the guidance is gone for every teacher, not
just for you - and, for guidance you have marked as read, **Restore**. Deleting it as you would any other
block of text does the same as *Delete*, without asking. To move it, hover over it and use the up
and down arrows at its bottom right, which move it past one paragraph (or other block) at a time.

### Checklists

Start each item on a line of its own with `[ ]` and a space:

```
[ ] Set the due date
[ ] Check the groups
```

Write `[x]` for an item that starts ticked. The guidance form's help says the same.

On the page each item is a checkbox, indented 20px. Ticking or unticking one is saved at once, for
every teacher - the checklist is shared, not personal - and logged. A preset's checklist shows, but
its boxes cannot be ticked: the preset is the whole site's. *Start with a preset* copies it into
guidance of your own, which can be.

### Marking as read and restoring

**Mark as read** - **Mark as complete**, for a task or an optional task - with a tick, at the top
right of the guidance, replaces it with a line saying it will be gone when you revisit the page, and
an **Undo**. After that, nothing of it is shown to you
anywhere: not on the page, not in the activity card, and not in the editor unless you choose *Show
guidance marked as read* there.

**Teacher guidance marked as read**, in the course navigation (under *More* in Boost), lists what you
have marked as read in the course, each with a short excerpt and where it is, and a **Restore**
button. The
link only appears while there is something to restore. You can also restore guidance from its form
in the editor.

### Where it shows

* **Activity description**, with *Display description on course page* ticked: where the chip is in
  the description, in the activity card.
* **Activity description**, with that setting off: at the foot of the activity card (Boost), or in
  the card's top row (Snap). The rest of the description stays off the course page, as before.
* **Book chapter** and **lesson page**: where the chip is.
* **Section summary**: where the chip is, on the course page.

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
(`mod/book/view.php`), lesson pages (`lesson_page::get_contents()`), activity descriptions, which
`format_module_intro()` caches unfiltered and `cm_info::get_formatted_content()` filters per request,
and section summaries (`core_courseformat\output\local\content\section\summary`, which Snap uses
too).
HTMLPurifier strips `data-*` attributes, so text that *is* cleaned - anywhere, or everywhere under
`$CFG->forceclean` - loses the token and shows no guidance. That fails closed, and
`filter_edguidance`'s tests pin it.

A key belongs to one activity. Lookups are always by (activity, key), so a token copied into another
activity does not resolve there: editors see a "not found" notice and everyone else sees nothing. A
section's key belongs to its course - see *Guidance in section summaries* for why, and what keeps
two sections from sharing one.

### Data

| Column | Role |
| --- | --- |
| `courseid`, `cmid` | Where the block belongs. `cmid = 0` is a section's block or a *draft* - see below. |
| `sectionid` | The section whose summary holds the block, with `cmid = 0`. 0 for an activity's block and for a draft, so a draft is `cmid = 0 AND sectionid = 0`. |
| `embedkey` | Matches the token. Unique per activity, and per course among sections and drafts (one index: course, cm, key). Remapped only when a restored section's key is already taken in its course. |
| `introorder` | 0 if the block is not in the activity description; otherwise its position there. |
| `presetslot` | 0 for the block's own text; 1-10 to use that site preset. |
| `category` | `note` (the default), `recommendation`, `task` or `optionaltask`. The block's own, preset or not. |
| `heading` | Optional plain text shown above the guidance, or null. The block's own, preset or not. |
| `guidance`, `guidanceformat` | Own text - or, for a preset block, a snapshot taken when it was linked. A checklist's ticks are part of it. |

Files embedded in a block's own text live in filearea `guidance`, itemid = the row id, in the
activity's context - the course's, for a section's block or a draft - served by
`local_edguidance_pluginfile()` to holders of `view` only (`manage`, for a draft).

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
rendering a block inside a block. Formatting goes through `checklist::format()`, which is
`format_text()` plus checkboxes for any checklist (see *Checklists*).

### Site presets

Ten fixed slots in admin settings (`presettitle{n}`, `presetguidance{n}`), read with `get_config()`,
so they are cached and need no pages of their own. A slot is in use only with both a title and some
guidance.

A preset is only text. A block using one keeps its own category and heading (see *Categories and
headings*), so the same preset can be a task in one place and a note in another.

A slot *is* the identity: a block stores `presetslot = 3` and shows whatever slot 3 holds. Replacing
slot 3 with unrelated guidance changes every block that used it; emptying it makes those blocks fall
back to their snapshots. The settings page says so.

Presets are the **only** thing that stays live. Copying an activity or a section - duplicate, backup
and restore, course copy, `mod_edpreset` - copies a block's own text as it stands, but carries
`presetslot` verbatim, so a preset block stays live in every copy. `presetslot` names a site setting rather than
anything in the course, so on another site it shows that site's preset in that slot (presets are
generic guidance, so nothing course-private can leak) or the snapshot if the slot is empty.

### Categories and headings

`local_edguidance\category` names the four categories. They are stored by name rather than number,
so that the table and the backup say what they mean, and a name this version does not know - from a
later version's backup, say - shows as a note rather than as nothing.

A block's header names its category as well as colouring it, so the category never rests on colour
alone (WCAG 1.4.1), and "Only teachers see this" still says who it is for. Each category is a class on
the block (`edguidance-task`, ...) that sets a handful of custom properties in `styles.css` - accent,
tint, ink, text and action - and every coloured rule reads those, so a category is one small block of
CSS. The editor's preview needs nothing extra: its shadow roots link the same stylesheet (see *The
preview in the editor*).

A task or an optional task is marked as *complete* rather than as *read*: the button, the
confirmation, the notice in the guidance form and the editor's hatched hint all say so. Only the
words change - see *Dismissing*.

The heading is plain text (`PARAM_TEXT`, 255 characters), formatted with `format_string()` in the
block's context by `guidance::format_heading()`, and shown as an `h5` above the guidance, sized to the
aside rather than to the page's own `h5`. The dismissed guidance page shows it too, above the excerpt,
since telling one block from another is what that page's excerpt is for.

### Checklists

A checklist is written in the guidance as lines starting `[ ]`, and **its ticks are part of the
text**: ticking an item rewrites `[ ]` as `[x]` in the block's own `guidance`. That is what makes it
shared - there is one text, so there is one set of ticks - and it needs no table of its own.

An item is a marker - `[ ] `, `[x] ` or `[X] ` - at the start of a line: after the start of the text,
a `<br>`, or the opening tag of a paragraph, list item or other block, with only spaces and inline
tags between. Non-breaking spaces, which the editor writes freely, count as spaces. A marker anywhere
else is text, and so is `- [ ] `: the line starts with the dash. There is deliberately no GitHub-style
leading dash, which would fight TinyMCE's default text patterns (a typed "- " starts a bulleted
list). Only `FORMAT_HTML` has checklists - it is what the editor writes and what
presets hold.

`checklist::markers()` is the **only parser**. Rendering numbers the items with it, and ticking finds
the item to rewrite with it, so the page and the server cannot disagree about which item is which.
The page also carries a hash of each item's text, and `api::set_checked()` refuses a tick whose item
no longer has that number and text - the guidance was edited after the page loaded - with *This
checklist has changed since the page was loaded*, rather than ticking whatever item is there now. The
hash leaves out the tick itself, so one teacher's tick never makes another's page stale. The
rewrite happens under a lock on the block, so two teachers ticking at once cannot each save over the
other; a tick that changes nothing (both ticked the same item) is not saved or logged.

Rendering (`checklist::format()`) swaps each marker for a random placeholder, formats the text, then
swaps the placeholders for checkboxes. Not before formatting, because formatting cleans the text and
cleaning removes form controls. Not by finding the markers again after, because a filter can drop
text - multilang shows one language of several - and the survivors would be numbered wrongly. The
nonce means nothing a teacher writes can pass for a placeholder. The swap parses the formatted HTML
with `DOMDocument`, only when there is a checklist, to wrap each box and the rest of its line in a
`<label>` - so the text names the box, and clicking it ticks - and to drop the `<br>` that ended the
line. Each item is indented 20px (`styles.css`). An item in a bulleted list keeps its bullet: nothing
treats list items specially.

A box can be ticked where `block::render_row()` finds `local/edguidance:tick` and the block has its own
text. It is disabled in a preset block - the text is the whole site's; the title says so - in the
editor's preview, which is clicked to edit, and for anyone without the capability. The web service
(`local_edguidance_set_checked`) checks *view* and *tick*, and refuses presets and drafts, itself.
`amd/src/guidance.js` saves each tick as it is made, with the box disabled until it is saved, and puts
the box back and shows why if it cannot be. A click on an item is stopped at the document, as
*Mark as read* is (see *Dismissing*), but not prevented, so the box still ticks.

Known limit: a multilang line holding one language's marker straight after another's -
`<span lang="mi">[ ] Tahi</span><span lang="en">[ ] One</span>` - is one line with a marker in
the middle, so only the first language's item is found. Put a line break at the end of each
language's text, or give each language its own lines.

### Logging

| Event | When |
| --- | --- |
| `guidance_created` | Guidance is written: from the guidance form, or by *Use a preset*. |
| `guidance_updated` | The guidance form saves existing guidance. One event per save, however much changed - including ticks added or removed by hand in the text, which are not logged item by item. |
| `checklist_item_checked`, `checklist_item_unchecked` | A box is ticked or unticked on the page. `other` holds the item's position and its text, shortened, so the log says what was ticked. Ticking does not also log an update. |

All four are logged in the block's context - its activity's, or its course's - at the teaching level,
with the row as their object. Their URL is built from the event's own fields rather than its context,
which may be gone by the time the log is read. Log restore does not map the row
(`NOT_MAPPED`): activity blocks are mapped only once the whole restore is done, after the logs.

### Guidance in the activity card

With *Display description on course page* ticked, the description is filtered when the card renders,
and the filter puts the block where its token sits. Nothing else is needed - in either theme, and on
every path that re-renders a card over AJAX, because all of them format the description.

With it unticked, the description never reaches the course page, so `local\card_injector` puts the
description's blocks into the card's **afterlink** instead: the slot Boost prints at the foot of the
card (`course/format/templates/local/content/cm/activity.mustache`) and Snap prints in its card meta
row (`theme/snap/classes/output/core/course_renderer.php`). **No theme is changed.** The blocks
are wrapped in `<div class="no-overflow">`, as both themes wrap a description they show
(`get_formatted_content()`'s `overflowdiv`), so the guidance sits in the card as it would in a shown
description. The Boost rules in `styles.css` that widen the afterlink row, and drop its divider
once only the collapsed icon is left, select through that wrapper.

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

The editor button is offered in a course context only on `/course/modedit.php` and, for a section's
block, on `/course/editsection.php`, so there is no other way to make a draft. A section's block has
`cmid = 0` too, so every query for drafts - adopting, purging - also says `sectionid = 0`; without
that, pasting a section's token into a new activity would carry the block off, and the daily purge
would delete every section's guidance.

### Guidance in section summaries

A section summary is edited on `/course/editsection.php` in the **course** context - the same
context as the *add an activity* form, whose blocks are drafts. Only the page tells them apart, so
`tiny_edguidance` reads the section id from the page URL and hands it to the editor, which sends it
back with every call; `api::embed_target()` checks it belongs to the course before anything is
saved.

A section summary is formatted in the course context too, and a filter is told the context and
nothing else. So `filter_edguidance` resolves a token in a course context against *any* section's
block in the course (`guidance::for_sections()`): a section's key is scoped to its course, not to its
section. The front page is left out entirely.

That would let two summaries share one block - showing the same guidance, deleting it with either
section, changing both when either is edited - and core makes that happen: duplicating a section
(`course_format::duplicate_section()`) copies its summary verbatim, and a teacher can paste one
section's token into another. Core 4.5 has no hook for either. What it has is the
`course_section_updated` event, fired whenever a section is saved, including the new section during
a duplicate. `observer` hands that to `api::claim_section_summary()`, which gives the section its own
copy - same text, same preset, same files, no dismissals - of any block in its summary that belongs
to another section, under a new key, and rewrites the summary to match. It writes the summary
straight to the table rather than through `course_update_section()`, which would fire the event
again, and purges the section cache as that would have. Tokens that are not a section's block in the
course are left alone and resolve to nothing, as between activities.

Restore merging into an existing section fires the same event *before* this plugin has seen the
section's blocks, and a claim then would copy whichever of the target course's blocks already has
the key, rather than the one in the backup. So the restore plugin holds claims off for the course
while each section step runs, and sorts the keys out itself (see *Backup and restore*).

### The preview in the editor

`tiny_edguidance` shows each token as the page will show its block: the same template, rendered by
`block::render_preview()` (no buttons) and fetched for every token in the text at once from
`local_edguidance_get_previews`, which also flags each block the teacher has dismissed. The editor
shows a flagged block as nothing at all, as the page does, until the teacher chooses *Show guidance
marked as read* from the menu; that item is offered only while such a block is hidden. From then on,
in that editor only, flagged blocks show in full over a hatch, with *Marked as read - click to edit*
in the header, or *Marked as complete - click to edit* for a task. The hatch is neutral grey, so it
reads the same over every category. The choice is held in the editor's own state and never stored, so it lasts until the page is
left, and there is deliberately no way to hide them again short of that. A hidden block takes no
space, so it cannot be clicked: show it first to edit, restore or delete it.

The preview is the guidance, so it must never be saved: it would go wherever the text goes (see
*The token*). Worse, `token::PATTERN` matches a token only up to its first `</div>`, so the filter
would strip the start of a filled token and show students the rest. So the preview is never put
inside the token. It goes in a **shadow root** attached to the token, which `innerHTML` and
`cloneNode()` leave out, and with them everything TinyMCE builds from those: the saved text,
autosave, copying and undo. As a second line, a serializer attribute filter empties every token
and drops its `contenteditable` however the text is read. That also catches anything typed into a
token in the source code view.

TinyMCE rebuilds tokens freely (setting content, paste, drag and drop, undo), and a rebuilt token
has no shadow root. Undo can rewrite the body without firing `SetContent`, so a `MutationObserver`
on the body, not an editor event, attaches previews as tokens arrive.

The editor's iframe carries only the theme's *editor* stylesheet (in Boost, Bootstrap alone), which
knows nothing of `styles.css`. So each shadow root links the page's own theme stylesheets
(`theme_config::css_urls()`, handed to the editor in its configuration), which the page around the
editor has already loaded. Browsers ignore `@font-face` inside a shadow root, so the page's Font
Awesome font faces are also copied onto the editor's document, for the title's icon.

Blocks are looked up as the guidance form looks them up - `api::get_embeds()`, within the editor's
context - so a token pasted in from elsewhere previews as a "not found" notice, and a draft previews
on the *add an activity* form. Drafts are why the service needs `manage` rather than `view`. A block
with nothing to say previews as a notice rather than as nothing, so there is still something to
click.

Nothing inside the preview is interactive (`pointer-events: none`): a click lands on the token and
opens the form, and a link or video in the guidance does nothing inside the editor. The one
exception is the up and down buttons (`amd/src/move.js`), which are in the same shadow root, so
they are never saved either. Their clicks are stopped inside the shadow root, before the token's
own click handler would open the form. A move swaps the token with its neighbouring element,
stepping over TinyMCE's `data-mce-bogus` scaffolding, in one undo step. Filter output
that needs JavaScript, such as MathJax or a media player, does not start in the preview.

### Dismissing

The interface calls this *Mark as read*, or *Mark as complete* for a task or an optional task. The
code, the web service (`local_edguidance_set_dismissed`), the favourites item type and `dismissed.php`
keep the name *dismissed*, so renaming the label changed no stored data and no API, and a task marked
as complete is a dismissal like any other: it lists on the same page, and restores the same way.

Per user, per block, in `core_favourites` (component `local_edguidance`, item type `dismissed`,
item id = the row id) in the user's own context - the same store `mod_ednote` used, for the same
reasons: it is core's general "this user has flagged this item" store, with a privacy story and a
bulk read. Keyed on the row rather than the text, so editing guidance, or an administrator rewording
a preset, does not quietly un-dismiss it.

`dismissed::set()` checks before writing: core's `create_favourite()` inserts into a unique index and
`delete_favourite()` throws when there is nothing to delete, so a double click would otherwise be a
500.

A dismissed block renders nothing. `block::render_row()` returns `''` for it - before resolving, so
it costs no formatting - and every path to the page goes through that: the filter and
`card_injector` alike. The filter's empty-text rule then drops a description that was nothing but
that guidance, as it does for a student, and `card_injector` adds no afterlink at all. The editor is
the exception, because a teacher can ask to see there what they have dismissed:
`block::render_preview()` renders a dismissed block in full, `get_previews` flags it, and
`tiny_edguidance` hides it unless asked (see *The preview in the editor*).

So only a block that was not dismissed when the page loaded is ever rendered, and its template
(`templates/block.mustache`) carries both states: the guidance, and the confirmation that replaces
it once *Mark as read* is clicked, hidden until then. `amd/src/guidance.js` only toggles between them, and
*Undo* is the same web service the other way. The block is not removed on the spot, because the
server has already recorded the choice and an accidental click should be easy to take back; it is
gone on the next load.

With nothing left on the page, `dismissed.php` is the way back. `output\dismissed_page` lists the
course's blocks the user has dismissed, sections' before their activities', in course order - only
those they could see if restored (section and activity visible, `view` in the activity), because
each row shows an excerpt of the guidance. `local_edguidance_extend_navigation_course()` links it
only when that list is not empty, asking the cheap questions first (anything dismissed at all,
anything dismissed in this course), since it runs on every course page. *Restore* is a plain link
carrying a sesskey, so the page needs no JavaScript, and it checks nothing about the block: it only
ever deletes the user's own flag.

The guidance form (`form\embed_form`) also carries a notice, with the block's id, when the teacher has
dismissed the block being edited; `tiny_edguidance` shows *Restore* in the form's footer only while
that notice is there. Editing does not undismiss. The footer's *Delete* (after
`core/notification`'s delete confirmation) removes the token from the text in one undo step, exactly
as deleting it by hand does, so nothing is lost until the text is saved.

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
  removes modules without the per-module callbacks;
* the `course_section_deleted` event deletes a section's blocks, and `course_content_deleted` every
  section's in a course, because `remove_course_contents()` (a restore that deletes the existing
  content first, say) deletes sections straight from the table.

### Backup and restore

`backup_local_edguidance_plugin` adds an activity's blocks to its `module.xml`, which covers course
backup and restore, import, course copy, *Duplicate*, the recycle bin and `mod_edpreset`'s copies,
and a section's blocks to its `section.xml`, which covers the same for sections (duplicating a
section is not a backup; see *Guidance in section summaries*). `embedkey` and `presetslot` are
carried verbatim - the token in the restored text matches the same key - and so are `category` and
`heading`. A backup from before those existed restores as a note with no heading, which is what the
table's defaults make it. A checklist's ticks are in the text, so they travel with it: a course
copied for its next run starts with this run's ticks.

A section may be merged into one that already exists, so its restored summary decides what is
restored: a block whose key is not in the summary (the existing section kept its own) is skipped; a
key already used by that section's own block is skipped, and the token resolves to it; a key used
anywhere else in the course - restoring a section back into the course it came from - gets a new
key, and the summary follows it.

`restore_local_edguidance_plugin` maps the blocks and restores their files in
`after_restore_module()`, not as each block is processed. The plugin hangs off the module step, which
runs before the activity's own step has told the task the module's old context id, and a file mapping
made then records 0 and matches no file.

Dismissals are a preference of each user, not course content, and are not backed up.

### Privacy

Blocks are course content and name nobody - a tick records nothing of who made it. The only personal
data this plugin stores is which blocks a teacher has dismissed, in `core_favourites` in their own
context; `privacy\provider` declares that link and exports and deletes only there. Who ticked what,
and who wrote or edited guidance, is in the site's logs (see *Logging*), which the log stores' own
privacy providers cover.

## Testing

```
vendor/bin/phpunit --testsuite local_edguidance_testsuite
vendor/bin/phpunit --testsuite filter_edguidance_testsuite
vendor/bin/phpunit --testsuite tiny_edguidance_testsuite
php admin/tool/behat/cli/run.php --tags=@local_edguidance
```

Behat covers card guidance with the description shown and hidden, in Boost and in Snap; categories
and headings, and *Mark as complete* for a task; setting and changing them in the editor; dismiss,
undo, and restore from the dismissed guidance page; per-teacher dismissal; a preset updating live;
book chapters and lesson pages; section summaries, in Boost and in Snap; adding guidance with the
editor button, in a chapter and a section summary; the editor's preview, as it is added and edited,
with a check each time that the guidance is not in the text the editor would save; dismissed
guidance hidden in the editor, shown hatched on request, and restored from its form; deleting
guidance from its form; moving guidance up and down in the editor; and checklists - ticks shared
between teachers and kept over a reload, none of it shown to students, and a preset's disabled.

Four things to know when adding Behat coverage:

* `mod_book`'s generator makes chapters in `FORMAT_MOODLE`, which TinyMCE does not edit - Moodle
  quietly picks another editor and there is no button. Give chapters `contentformat` 1.
* The `Insert > ...` menu step cannot reach a third menu level. Click the toolbar button and pick
  menu items by `[role^='menuitem'][aria-label='...']` instead, as `editor.feature` does.
* The editor's preview and its move buttons are in a shadow root, which XPath cannot see into.
  Use `tiny_edguidance`'s steps: *the "Content" TinyMCE editor should preview guidance "..."* (and
  *should not preview*, and *should preview dismissed guidance* for the hatched kind), *... should
  not save "..."* and *I move teacher guidance "up" in the "Content" TinyMCE editor*.
* Open the dismissed guidance page with *I am on the "Course 1" "local_edguidance > dismissed
  guidance" page*, not through the navigation, whose overflow into *More* depends on window size and
  theme. On that page an activity's name is also in the course index, so a `"list_item"` scope finds
  the index first; click *Restore* by its label, `a[aria-label='Restore teacher guidance in ...']`.

Static caches (`guidance`, `dismissed`, `card_injector`) are keyed on ids PHPUnit reuses between
tests; reset them in `setUp()`.

## License

GNU GPL v3 or later - see [LICENSE](LICENSE).

## Author

Andrew Rowatt &lt;A.J.Rowatt@massey.ac.nz&gt;, Massey University.
