import { test, expect } from '@playwright/test';
import { env, loginAsAdmin } from '../support/env';

/**
 * Smoke coverage for the ADELE enrolment management page.
 *
 * Deliberately small. Its job is to prove that the page renders against a
 * real Moodle at all — the part PHPUnit cannot reach, because manage.php is a
 * page script and not a class. The query layer behind it has its own unit
 * tests in tests/manage_test.php; this suite only checks that the page, the
 * filter form and the pager are wired up.
 */
test.describe('Learning path enrolment management', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('lists the seeded learning path with its course and type', async ({ page }) => {
    await page.goto('/enrol/adele/manage.php');

    await expect(page.getByRole('heading', { name: /Learning path enrolment management/i })).toBeVisible();

    // Scoped to the table row, not to the page: the learning path name also
    // appears as an option in the filter select, so an unscoped text match
    // resolves to two elements and fails on strict mode — while proving
    // nothing about the listing itself.
    const row = page.locator('#enroladelemanagetable tbody tr').filter({ hasText: env.learningPathName });
    await expect(row).toHaveCount(1);
    await expect(row.getByRole('link', { name: env.courseShortname })).toBeVisible();
    await expect(row).toContainText('Target course');
  });

  test('reports that the scheduled reconciliation has not run yet', async ({ page }) => {
    await page.goto('/enrol/adele/manage.php');

    await expect(page.getByRole('heading', { name: /Last full reconciliation/i })).toBeVisible();
    await expect(page.getByText(/has not run yet/i)).toBeVisible();
  });

  test('filtering by host course empties the list', async ({ page }) => {
    await page.goto('/enrol/adele/manage.php');

    await page.selectOption('#adelefilterkind', { label: 'Host course' });
    await page.getByRole('button', { name: 'Apply filter' }).click();

    // The seed creates a target-course instance only, so the host filter must
    // find nothing — and say so rather than showing an empty table.
    await expect(page.getByText(/No learning path currently owns/i)).toBeVisible();
  });

  test('hard delete appears only once a single learning path is selected', async ({ page }) => {
    await page.goto('/enrol/adele/manage.php');
    await expect(page.getByRole('button', { name: 'Hard delete' })).toHaveCount(0);

    // Selected by id, not by label: the label carries the generated name and
    // would have to be reconstructed exactly, id is what the form submits.
    await page.selectOption('#adelefilterlp', env.learningPathId);
    await page.getByRole('button', { name: 'Apply filter' }).click();

    await expect(page.getByRole('button', { name: 'Hard delete' })).toBeVisible();
  });
});
