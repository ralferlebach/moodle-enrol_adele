Adaptive e-Learning Paths enrolments (moodle-enrol_adele)
==================

[![Moodle Plugin CI](https://github.com/ralferlebach/moodle-enrol_adele/actions/workflows/moodle-plugin-ci.yml/badge.svg?branch=development)](https://github.com/ralferlebach/moodle-enrol_adele/actions?query=workflow%3A%22Moodle+Plugin+CI%22+branch%3Adevelopment)

AdeLe enrolment owns every course enrolment a learning path causes: it derives who belongs in which course from the learning path state, keeps that derivation correct when events are lost, and cleans up when an entitlement ends.

AdeLe is not a single plugin but a set of three that work as one system. They are developed together and declare each other as dependencies, so they can only be installed and updated as a set.

* **local_adele** is the learning path itself: the graphical editor, the node structure, the completion and restriction logic, and the Vue 3 frontend.
* **mod_adele** is the in-course entry point: it embeds a learning path in an ordinary course and decides which of that course's participants the path applies to.
* **enrol_adele** is the enrolment layer: it turns the learning path state into actual course enrolments and role assignments, and reconciles them.

This README documents **enrol_adele** - the third bullet point above. The other two plugins are documented in their own repositories.

Because the responsibilities are split this way, no rule exists twice: the learning path is defined in one place, embedded in another, and every enrolment AdeLe causes is created and removed by this plugin alone. That is also why a partial installation does not work - a missing sibling means a missing part of the mechanism.


Requirements
------------

This plugin requires Moodle 4.5+

It also requires the other AdeLe plugins. All three are developed together and must be installed in matching versions:

* **local_adele (AdeLe learning paths)** - required dependency, declared in version.php\
  https://github.com/Wunderbyte-GmbH/moodle_local_adele
* **mod_adele (AdeLe activity)** - part of the same set, required by local_adele\
  https://github.com/Wunderbyte-GmbH/moodle-mod_adele

The three plugins declare each other as dependencies, and that graph is deliberately circular. Moodle installs them together without complaint; installing only one is not possible.


Motivation for this plugin
--------------------------

A learning path spans several courses, and who may enter which of them changes as a learner progresses. Doing that by hand does not scale, and doing it purely with events does not survive contact with reality: a bulk operation with events suppressed, a restore, a cohort resynchronisation or a direct database change leaves the enrolments and the learning path disagreeing, with nothing to notice.

This plugin therefore treats enrolments as **derived state**. The learning path is the source of truth and the enrolments are recomputed from it. Events make that fast, but nothing depends on their delivery: a full reconciliation reproduces the same result from scratch, in both directions - creating what is missing and withdrawing what is no longer justified.


Installation
------------

Install the plugin like any other plugin to folder
/enrol/adele

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins

The plugin enables itself on installation. Enrolment plugins are inactive by default in Moodle, and an inactive one fails silently here - every reconciliation would report success without having done anything.


Usage & Settings
----------------

After installing the plugin, it is ready to use. There is nothing to configure per course: enrolment instances are created and removed by the plugin itself and cannot be added by hand.

To configure the plugin and its behaviour, please visit:
Site administration -> Plugins -> Enrolments -> Learning path enrolment

There, you find settings for:

* **Default role** - the role assigned through a learning path. Changing it migrates every existing AdeLe role assignment in the background; roles assigned by hand are never touched.
* **Remove suspended enrolments after** - how many days an enrolment may stay suspended before it is removed altogether. Default 90 days; `0` keeps suspended enrolments indefinitely.

A management page under Site administration -> Plugins -> Enrolments -> Manage learning path enrolments lists every AdeLe enrolment instance with server-side paging and filters by learning path, course, type and enrolment status. It also shows the outcome of the last full reconciliation, the repairs currently queued in the background, and what became of the ones that already ran.


Capabilities
------------

This plugin introduces these additional capabilities:

* **enrol/adele:config** - configure AdeLe enrolment instances and use the management page.
* **enrol/adele:unenrol** - unenrol users from an AdeLe enrolment instance.

Both are deliberately restrictive. This plugin owns its enrolments: manual enrolment and manual unenrolment through the participants page are refused, because an enrolment removed by hand would be recreated by the next reconciliation.


Scheduled Tasks
---------------

This plugin also introduces these additional scheduled tasks:

* **\enrol_adele\task\reconcile_task** - Runs the full reconciliation as a safety net: orphaned instances, duplicates, roles, target courses in both directions, host courses, subscriptions no embedding carries any more, and enrolments past their retention.\ By default, the task is enabled and runs nightly at 03:20.

Repairs started from the management page run as ad-hoc tasks once more than 200 users are affected; below that they run immediately, so the administrator sees the result without waiting for cron.


How this plugin works / Pitfalls
--------------------------------

Two kinds of enrolment exist, and they are decided differently.

A **target course** belongs to a node of the learning path. Entitlement follows the node's state for that user - accessible or completed - and is derived by local_adele.

A **host course** contains a mod_adele activity. Entitlement follows the activity's subscription options: course membership, membership of a starting node course, or membership of any node course. Several activities may embed the same learning path in the same course; the most generous setting wins.

Losing an entitlement suspends the enrolment rather than removing it, so that reports, certificates and grades keep working. Removal happens after the configured retention, or immediately when the user genuinely leaves the learning path.

**Pitfall:** the learning path subscription record is the only copy of a learner's progress through the path. Losing the last carrying enrolment therefore does **not** delete it straight away - a deferred task re-checks a few minutes later whether the removal proved durable. A cohort resynchronisation that removes and re-adds a user within that window leaves the record, and the progress, untouched.

**Pitfall:** an enrolment instance belongs to a (course, learning path, kind) triple, not to a mod_adele activity. Removing one of several activities that embed the same path in the same course therefore does not remove the instance - the remaining activity still justifies it.


Theme support
-------------

This plugin is developed and tested on Moodle Core's Boost theme.
It should also work with Boost child themes, including Moodle Core's Classic theme. However, we can't support any other theme than Boost.


Plugin repositories
-------------------

This plugin is not published in the Moodle plugins repository.

The latest development version can be found on Github:
https://github.com/ralferlebach/moodle-enrol_adele


Bug and problem reports / Support requests
------------------------------------------

This plugin is carefully developed and thoroughly tested, but bugs and problems can always appear.

Please report bugs and problems on Github:
https://github.com/Wunderbyte-GmbH/moodle-enrol_adele/issues

We will do our best to solve your problems, but please note that due to limited resources we can't always provide per-case support.


Feature proposals
-----------------

Due to limited resources, the functionality of this plugin is primarily implemented for our own local needs and published as-is to the community. We are aware that members of the community will have other needs and would love to see them solved by this plugin.

Please issue feature proposals on Github:
https://github.com/Wunderbyte-GmbH/moodle-enrol_adele/issues

Please create pull requests on Github:
https://github.com/Wunderbyte-GmbH/moodle-enrol_adele/pulls

We are always interested to read about your feature proposals or even get a pull request from you, but please accept that we can handle your issues only as feature _proposals_ and not as feature _requests_.


Moodle release support
----------------------

Due to limited resources, this plugin is only maintained for the most recent major release of Moodle as well as the most recent LTS release of Moodle. Bugfixes are backported to the LTS release. However, new features and improvements are not necessarily backported to the LTS release.

Apart from these maintained releases, previous versions of this plugin which work in legacy major releases of Moodle are still available as-is without any further updates in the Moodle Plugins repository.

There may be several weeks after a new major release of Moodle has been published until we can do a compatibility check and fix problems if necessary. If you encounter problems with a new major release of Moodle - or can confirm that this plugin still works with a new major release - please let us know on Github.

This plugin is designed to be compatible with all currently supported versions of Moodle, leveraging its latest APIs. However, if you are using a legacy version of Moodle, we kindly advise against installing or using this plugin. Instead, we strongly recommend updating your Moodle instance to a supported version to ensure security and compliance with current technological standards. Thank you for your understanding.


Translating this plugin
-----------------------

This Moodle plugin is provided with English and German language packs only. Translations into other languages must be managed through AMOS (https://lang.moodle.org), where they will become part of Moodle's official language pack.

As the plugin creator, we continue to maintain the German translation. For all other languages, we kindly ask you to contribute your translations directly in AMOS. These contributions will be reviewed by Moodle's official language pack maintainers before being included in the official repository.

Thank you for supporting the global Moodle community!


Right-to-left support
---------------------

This plugin has not been tested with Moodle's support for right-to-left (RTL) languages.
If you want to use this plugin with a RTL language and it doesn't work as-is, you are free to send us a pull request on Github with modifications.


Maintainers
-----------

The plugin is maintained by\
Wunderbyte GmbH

Copyright
---------

The copyright of this plugin is held by\
Wunderbyte GmbH\
Ralf Erlebach (as independent main-contributor)

Individual copyrights of individual developers are tracked in PHPDoc comments and Git commits.
