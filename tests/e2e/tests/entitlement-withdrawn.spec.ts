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
 * entitlement-withdrawn - the withdrawal half of the central E2E contract
 * (plan section 27).
 *
 *     the course leaves the learning path
 *     -> the entitlement it carried ends
 *     -> the enrolment goes
 *     -> the learner can no longer open the course
 *
 * The chain follows what an author actually has to do, including the step
 * that fails: removing the entry node leaves its successor pointing at a
 * predecessor that no longer exists, and the editor REFUSES to save. That
 * refusal is part of the test - a silent save there would be the defect.
 * Only after the orphaned access condition is removed does the save go
 * through, and only then may the entitlement change.
 *
 * Afterwards the path itself is deleted, and that too must take its
 * entitlements with it.
 *
 * The negative control is unusually strong here, because all four fixture
 * paths start with the same course: changing ONE path must move the access
 * of that path's learner and of nobody else. A sweep that clears the course
 * for everybody would pass a test that only looked at the first learner.
 * */

import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';
import {
  drainTaskQueue,
  expectCourseClosed,
  expectCourseOpenAfterTasks,
  fixture,
  fixtureCourse,
  fixturePassword,
  referencePath,
} from '../support/fixtures';

/** The learner of the second path, which keeps the course. */
const otherLearner = () => fixture('ADELE_FIXTURE_LEARNER_LINEAR_A2');

/** The Vue application's mount point. */
const app = (page: Page) => page.locator('[id^="local-adele-app"]');

test.describe('ADELE-E2E-C2 — a course that leaves the path takes its access with it', () => {
  test('refused save, removed condition, moved entitlement, deleted path', async ({ page }) => {
    const entry = fixtureCourse('T01');
    const second = fixtureCourse('T02');

    const dialogs: string[] = [];
    // The editor asks through the browser's own confirm(); Playwright
    // dismisses such a dialog unless something accepts it, and a dismissed
    // confirm silently cancels whatever it was guarding.
    page.on('dialog', async (dialog) => {
      dialogs.push(dialog.message());
      await dialog.accept();
    });

    await test.step('precondition: both learners can open the entry course', async () => {
      for (const learner of [referencePath.learner, otherLearner()]) {
        await loginAs(page, learner, fixturePassword());
        await expectCourseOpenAfterTasks(
          page,
          entry,
          `${learner} is on a path whose entry node is this course`
        );
      }
    });

    await test.step('a manager removes the entry node from the reference path', async () => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');

      const row = app(page).locator(`[data-testid="learningpath-row-${referencePath.id}"]`);
      await expect(row).toBeVisible({ timeout: 30_000 });
      await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();

      const node = app(page).locator('[data-testid="learningpath-node-dndnode_1"]');
      await expect(node).toBeVisible({ timeout: 30_000 });

      // By keyboard: Vue Flow puts every node in the tab order and the editor
      // deletes the focused one (#575 B5) - the only route that does not
      // depend on dragging. The business id sits on the wrapper, which is
      // what carries the tab stop.
      await page.locator('.vue-flow__node[data-id="dndnode_1"]').focus();
      await page.keyboard.press('Delete');
      await expect(node).toHaveCount(0);
      expect(dialogs.join(' '), 'the editor must ask before discarding a node')
        .toMatch(/Testkurs 01/);
    });

    await test.step('saving is refused while the successor still requires the removed node', async () => {
      await app(page).locator('[data-testid="learningpath-save"]').click();

      // The refusal itself is the assertion: the successor's access condition
      // now points at a node that is gone.
      const refusal = page.getByRole('dialog').filter({ hasText: /Incomplete criterion|Unvollständig/i });
      await expect(refusal).toBeVisible({ timeout: 30_000 });
      await refusal.locator('[data-action="cancel"], [data-action="hide"]').first().click();
      await expect(refusal).toBeHidden();

      // Still in the editor, and nothing has been written: the learner must
      // keep the access the unsaved change would have taken away.
      await expect(app(page).locator('[data-testid="learningpath-save"]')).toBeVisible();
      await loginAs(page, referencePath.learner, fixturePassword());
      await expectCourseOpenAfterTasks(
        page,
        entry,
        'a refused save must not change anything, least of all an enrolment'
      );
    });

    await test.step('the manager removes the orphaned access condition and saves', async () => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      const row = app(page).locator(`[data-testid="learningpath-row-${referencePath.id}"]`);
      await expect(row).toBeVisible({ timeout: 30_000 });
      await row.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();

      // Redo the removal: the refused save was not written, so the editor
      // starts from the unchanged path again.
      const node = app(page).locator('[data-testid="learningpath-node-dndnode_1"]');
      await expect(node).toBeVisible({ timeout: 30_000 });
      await page.locator('.vue-flow__node[data-id="dndnode_1"]').focus();
      await page.keyboard.press('Delete');
      await expect(node).toHaveCount(0);

      // The ACCESS criteria, not the completion criteria. A node card carries
      // two controls that open two near-identical canvases - the padlock for
      // access, the checklist for completion - and the card body itself opens
      // the completion one. Only the access canvas holds the criterion the
      // refusal is about.
      const node2 = app(page).locator('[data-testid="learningpath-node-dndnode_2"]');
      // By its name, not by its icon: the card's controls carry an aria-label
      // since #575 B2, which also distinguishes them from the identical
      // controls of every other card.
      await node2.getByRole('button', { name: /^(Edit access criteria|Zugangskriterien bearbeiten):/ })
        .click();

      const criterion = page.locator('.vue-flow__node').filter({ hasText: /Vorgänger|predecessor/i });
      await expect(criterion.first()).toBeVisible({ timeout: 30_000 });
      await criterion.first().locator('button:has(.fa-trash)').click();
      await expect(criterion).toHaveCount(0);

      // The conditions have their own canvas with its own save. Going "back"
      // from here discards them - the change has to be saved twice, once for
      // the node's conditions and once for the path.
      // The criteria canvas saves the whole path itself and returns to the
      // graph, so this is already the save that must now go through.
      await page.getByRole('button', { name: /^(Save|Speichern)$/ }).click();
      await expect(app(page).locator('[data-testid="learningpath-node-dndnode_2"]'))
        .toBeVisible({ timeout: 30_000 });

      // Verify the removal where it counts: reopen the access criteria.
      await node2.getByRole('button', { name: /^(Edit access criteria|Zugangskriterien bearbeiten):/ })
        .click();
      await expect(page.locator('.vue-flow__node').filter({ hasText: /Vorgänger|predecessor/i }),
        'the removed access criterion must stay removed after reopening').toHaveCount(0);
      await page.getByRole('button', { name: /^(Save|Speichern)$/ }).click();

      await app(page).locator('[data-testid="learningpath-save"]').click();

      // Back on the overview: the editor only returns there after the save
      // went through.
      await expect(app(page).locator('[data-testid="learningpath-create"]'), 'dialogs seen: ' + dialogs.join(' | '))
        .toBeVisible({ timeout: 30_000 });
    });

    await test.step('the entitlement ends where the course left the path', async () => {
      await loginAs(page, referencePath.learner, fixturePassword());
      await expectCourseClosed(
        page,
        entry,
        'the course is no longer part of the path, so the enrolment it caused must end'
      );
      // The successor does NOT open: only the predecessor criterion was
      // removed, and the node still carries a timed criterion that governs
      // on its own. Asserted rather than assumed - a release here would mean
      // a removed criterion took an unrelated one with it.
      await expectCourseClosed(
        page,
        second,
        'the successor still has a timed access criterion and must stay closed'
      );
    });

    await test.step('the learner of the other path is untouched', async () => {
      await loginAs(page, otherLearner(), fixturePassword());
      await expectCourseOpenAfterTasks(
        page,
        entry,
        'the second path still contains this course; its learner must not be swept up'
      );
    });

    await test.step('deleting the path takes the remaining entitlement with it', async () => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      const row = app(page).locator(`[data-testid="learningpath-row-${referencePath.id}"]`);
      await expect(row).toBeVisible({ timeout: 30_000 });
      await row.getByRole('button', { name: /^(Delete|Löschen):/ }).click();
      // The confirmation sits inside the card, so it can only be this path's.
      await row.locator('.deletealert .btn-danger').click();
      await expect(row, 'the deletion must be visible in the overview').toHaveCount(0, { timeout: 30_000 });

      drainTaskQueue();

      await loginAs(page, referencePath.learner, fixturePassword());
      await expectCourseClosed(
        page,
        second,
        'with the path gone, the enrolment it caused must go too'
      );

      await loginAs(page, otherLearner(), fixturePassword());
      await expectCourseOpenAfterTasks(
        page,
        entry,
        'deleting one path must not touch the learners of another'
      );
    });
  });
});
