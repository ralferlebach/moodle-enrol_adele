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
 * entitlement-to-course-access - the release half of the central E2E
 * contract (plan section 27).
 *
 *     condition met
 *     -> node accessible
 *     -> enrolment active
 *     -> the learner can open the course
 *
 * And its counterpart, which is what actually makes the chain a test: a
 * locked node must NOT produce access, and a person who is on no path must
 * not receive the effect at all (plan section 2 G).
 *
 * Runs on the reference path "Linear A1" (T01 -> T02 -> T03) from the shipped
 * fixtures. On that path only T01 is reachable at the start, which is exactly
 * the boundary this spec needs: one course that must open and one that must
 * not, for the same learner at the same moment.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { test, expect } from '@playwright/test';
import { loginAs } from '../support/env';
import {
  controlUser,
  expectCourseClosed,
  expectCourseOpen,
  fixtureCourse,
  fixturePassword,
  referencePath,
} from '../support/fixtures';

test.describe('ADELE-E2E-V1 — entitlement reaches the real course', () => {
  test('the learner can open the reachable course of their path', async ({ page }) => {
    await loginAs(page, referencePath.learner, fixturePassword());

    await expectCourseOpen(
      page,
      fixtureCourse('T01'),
      'T01 is the entry node of Linear A1 and is accessible, so its learner must be able to open it'
    );
  });

  test('a locked node grants no access to its course', async ({ page }) => {
    await loginAs(page, referencePath.learner, fixturePassword());

    // T02 sits behind T01 and nothing has been completed yet. An enrolment
    // here would mean the learner reaches content the path has not released.
    await expectCourseClosed(
      page,
      fixtureCourse('T02'),
      'T02 is locked behind T01, so no enrolment may carry its learner into it'
    );
  });

  test('a learner on no path receives nothing', async ({ page }) => {
    await loginAs(page, controlUser(), fixturePassword());

    await expectCourseClosed(
      page,
      fixtureCourse('T01'),
      'the control learner is on no learning path and must not be enrolled anywhere by ADELE'
    );
  });

  test('the enrolment survives a fresh session', async ({ page }) => {
    // Persistence (plan section 2 F): the state must not depend on the
    // session that produced it.
    await loginAs(page, referencePath.learner, fixturePassword());
    await expectCourseOpen(page, fixtureCourse('T01'), 'precondition: access exists in the first session');

    await page.context().clearCookies();
    await loginAs(page, referencePath.learner, fixturePassword());

    await expectCourseOpen(
      page,
      fixtureCourse('T01'),
      'the entitlement must still hold after logging out and back in'
    );
  });

  test('the course appears on the learner overview, and only the released one', async ({ page }) => {
    await loginAs(page, referencePath.learner, fixturePassword());
    await page.goto('/my/courses.php');

    // Named by their real titles: the fixture courses are "Testkurs 01" and
    // "Testkurs 02", so this also catches an enrolment into the wrong course.
    await expect(page.getByRole('link', { name: /Testkurs 01/ }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: /Testkurs 02/ })).toHaveCount(0);
  });
});
