# Learning path enrolment (`enrol_adele`)

Moodle enrolment plugin for the ADELE learning path ecosystem.

> **Status: 0.1.3 — critical activation fix.**
> The stateless reconciliation engine, the host-course removal rules
> (subscription options 1/2/3), the nightly safety-net task and the
> host-course reconciliation (0.1.2) are all implemented — but were
> effectively inert until 0.1.3, because the plugin was never enabled by
> default (see CHANGELOG). Requires local_adele 0.4.6; companion releases:
> local_adele 0.4.6, mod_adele 0.1.6 (unchanged this round). The admin manage
> page and backup/restore hooks follow in 0.1.4 — see
> [`docs/arbeitsplan.md`](docs/arbeitsplan.md).

---

## Why this plugin exists

`local_adele` models learning paths whose nodes point at Moodle courses. When a
node becomes accessible, learners are supposed to reach those courses; when it
stops being accessible, they are supposed to lose them again.

Today `local_adele` enrols users through **`enrol_manual`**
(`relation_update.php:1104`, `node_completion.php:70`, and
`mod_adele/classes/observer.php:144`). That makes an ADELE-created enrolment
indistinguishable from one a teacher created by hand — same method, same record,
no marker. Which is exactly why un-enrolment does not exist: a search for
`unenrol` across `local_adele` returns nothing. ADELE cannot withdraw what it
cannot recognise as its own, and deleting a learning path leaves its enrolments
behind forever.

`enrol_adele` gives ADELE its own enrolment method, and therefore its own data
trail. Everything ADELE creates lives on an instance that ADELE owns, so it can
be withdrawn completely and without ever touching `manual`, `self` or `cohort`
enrolments.

## The core idea

One enrolment instance is scoped to exactly one pair of learning path and target
course:

```
enrol      = 'adele'
courseid   = target course id
customint1 = local_adele_learning_paths.id
```

Neither the `mod_adele` activity nor the host course that embeds the learning
path is part of that identity. A learning path shown in five activities still
produces one instance per target course — and two learning paths that share a
target course deliberately get two separate instances, because they are two
separate access sources with separate lifecycles.

The plugin keeps no state of its own (no tables — decided in Session 002, Teil 1).
Instead, a stateless, idempotent reconciliation compares the intended state
from `local_adele`'s user paths — the set of courses whose nodes are currently
accessible or completed — against Moodle's `user_enrolments`, and enrols,
reactivates or suspends accordingly. A course stays active as long as *any*
node of the learning path still grants it. Hard removal (learning path deleted,
or access through the embedding course lost) deletes the enrolments entirely.

## Requirements

* Moodle 4.1 – 5.1
* PHP 8.1+
* [`local_adele`](https://github.com/ralferlebach/moodle_local_adele) (hard
  dependency), which in turn requires
  [`mod_adele`](https://github.com/ralferlebach/moodle-mod_adele)

`local_adele` does **not** depend on `enrol_adele`. Without this plugin installed
it behaves exactly as before.

## Installation

From a ZIP: *Site administration → Plugins → Install plugins*, plugin type
*Enrolment method*.

Manually:

```bash
git clone https://github.com/ralferlebach/moodle-enrol_adele.git /path/to/moodle/enrol/adele
```

Then visit the notifications page to complete the install.

For development, `make link MOODLE_DIR=/path/to/moodle` symlinks the checkout
into place.

## Development

```bash
make help     # list all targets
make checks   # phplint, phpcs, phpdoc, phpmd
make test     # PHPUnit (needs MOODLE_DIR)
make zip      # build an installable ZIP in build/
```

The code checker runs at **zero tolerance**: `codechecker_max_warnings: 0` in CI.

## Documentation

| Document | Content |
|---|---|
| [`docs/lastenheft.md`](docs/lastenheft.md) | Requirements — what is asked for, and the findings that motivate it |
| [`docs/pflichtenheft.md`](docs/pflichtenheft.md) | Specification — reconciliation architecture, event matrix, lifecycle, open questions |
| [`docs/arbeitsplan.md`](docs/arbeitsplan.md) | Work plan — phases A–D, work packages, order of delivery |
| [`docs/sessions/`](docs/sessions/) | Session logs, decisions and their rationale |

## Repositories

| Repository | Working branch |
|---|---|
| [`moodle-enrol_adele`](https://github.com/ralferlebach/moodle-enrol_adele) | `development` |
| [`moodle_local_adele`](https://github.com/ralferlebach/moodle_local_adele) | `development` |
| [`moodle-mod_adele`](https://github.com/ralferlebach/moodle-mod_adele) | `development` |

ADELE is developed by [Wunderbyte GmbH](https://www.wunderbyte.at/). This is a
fork-side extension and is not affiliated with upstream.

## License

GNU GPL v3 or later — see [LICENSE.md](LICENSE.md).
