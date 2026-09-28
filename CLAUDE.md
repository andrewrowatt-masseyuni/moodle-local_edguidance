# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Where this plugin sits

This directory is the `local_edguidance` plugin and **its own git repository** (remote
`git@github.com:andrewrowatt-masseyuni/moodle-local_edguidance.git`, branch `main`). Its two
companions are their own repositories too:

| Plugin | Path | Remote |
| --- | --- | --- |
| `filter_edguidance` | `filter/edguidance` | `andrewrowatt-masseyuni/moodle-filter_edguidance` |
| `tiny_edguidance` | `lib/editor/tiny/plugins/edguidance` | `andrewrowatt-masseyuni/moodle-tiny_edguidance` |

Both depend on this one, and changes to guidance usually touch more than one of the three. Each
repository's CI fetches the others with `moodle-plugin-ci add-plugin`, so a change that needs all
three must be pushed to all three before CI can pass.

All three are checked out inside a **Moodle 4.5 core checkout** (branch `MOODLE_405_STABLE`) at
`/home/arowatt/moodle405_local_edguidance`, the development host and the session's working directory.
Commit plugin work from inside each plugin's own directory; never commit into the outer core repo.
Editing core, or a theme, is not the answer here - the design exists precisely to need neither.

[README.md](README.md) is the design record for all three plugins: *why* each non-obvious decision
was made, with the failure it prevents. **Read the relevant section before changing that area, and
update it in the same change.** Notes below are operating context only.

This project neither depends on nor relates to
`mod_edpreset`, which is developed separately in its own checkout (`moodle405_mod_edpreset`); do not
look there for context or make changes there.

## Commands

From the Moodle root, inside the container (`docker exec moodle405_local_edguidance-webserver-1 ...`,
cwd `/var/www/html`):

```bash
vendor/bin/phpunit --testsuite local_edguidance_testsuite
vendor/bin/phpunit --testsuite filter_edguidance_testsuite
vendor/bin/phpunit --testsuite tiny_edguidance_testsuite
vendor/bin/phpunit privacy/tests/privacy/provider_test.php
php admin/tool/behat/cli/run.php --tags=@local_edguidance --format progress   # as -u www-data
php admin/tool/phpunit/cli/init.php && php admin/tool/behat/cli/init.php       # after db/ or version changes
php admin/cli/upgrade.php --non-interactive && php admin/cli/purge_caches.php
```

On the host, from the Moodle root: `../moodle-plugin-ci/bin/moodle-plugin-ci phpcs --max-warnings 0
./local/edguidance` (and phplint, phpmd, mustache); PHPDoc via
`php local/moodlecheck/cli/moodlecheck.php -p=local/edguidance -f=text` in the container. On the
host, **from the plugin directory**: `grunt --max-lint-warnings=0 amd` (built files are committed),
`stylelint`, `gherkinlint`.

## Traps

* **Students are kept out by `local/edguidance:view` alone.** Every render path checks it: the
  filter, `card_injector`, `local_edguidance_pluginfile()`, the web services. There is no second
  line of defence.
* **Guidance text must never go into host text.** Only the token does. Anything that writes guidance
  HTML into a description, chapter or page reopens the leaks the README lists.
* **The rendered block must never contain `data-edguidance=`** - lesson filters some text twice.
* **`card_injector` relies on core internals** (per-request modinfo, afterlink). Keep
  `tests/card_injector_test.php` rendering real cards and fragments; do not reduce it to checking
  `$cm->afterlink`.
* **Test artefacts that look like bugs:** the generator's course objects carry a stale `cacherev`
  (pass course ids to `get_fast_modinfo()`); `course_get_format()` caches modinfo per course, so
  switching users mid-test renders the first user's cards; Moodle sets `arg_separator.output` to
  `&amp;`, so build query strings with `http_build_query($data, '', '&')`.
* **`cmid = 0` is not always a draft.** A section's block has `cmid = 0` and `sectionid > 0`; any
  query meant for drafts must also say `sectionid = 0`, or the daily purge deletes section guidance.
* **Section keys are scoped to the course, not the section**, because a filter only knows the
  context. `api::claim_section_summary()` (on `course_section_updated`) is what stops two sections
  sharing a block; restore holds it off while a section step runs.
* **`SITEID` is a string.** Compare it as `(int)SITEID`; a strict comparison with an int never
  matches.
* Reset `guidance`, `dismissed` and `card_injector` static caches in `setUp()`.
* Bump `version.php` with every `db/` change, paired with a savepoint in `db/upgrade.php`.
* Lang keys are kept in alphabetical order.
