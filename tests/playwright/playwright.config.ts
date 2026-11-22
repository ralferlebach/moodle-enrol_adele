import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for enrol_adele.
 *
 * The suite talks to a live Moodle site, so the base URL is supplied from
 * outside rather than guessed. seed.php prints it along with the credentials
 * and the fixture data; support/env.ts fails loudly when a variable is
 * missing, because a browser test silently pointed at the wrong site is worse
 * than one that does not start.
 */
export default defineConfig({
  testDir: './tests',
  // A browser run is slower than a unit test but must not hang a CI job.
  timeout: 60_000,
  expect: { timeout: 10_000 },
  // Serial by default: the suite shares one seeded site, and parallel workers
  // logging in and out of the same account fight over the session.
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  // results.json tells the workflow which tests failed: a red run delivers
  // only their videos (decision 2026-11-22).
  reporter: process.env.CI
    ? [['list'], ['html', { open: 'never' }], ['json', { outputFile: 'results.json' }]]
    : [['list']],
  use: {
    baseURL: process.env.ADELE_BASE_URL,
    // Evidence is recorded for GREEN runs too, not only for failures.
    // These suites are not just a regression net: a passing run documents the
    // intended journey through the interface, and its video and screenshots
    // are reused as illustrations for the manual. A run that proves the
    // feature works but leaves nothing to look at would have to be repeated
    // by hand to produce the same pictures.
    //
    // A green run uploads every video; a red run only those of the failed
    // tests (decision 2026-11-22, .github/workflows/playwright.yml).
    trace: 'retain-on-failure',
    screenshot: 'on',
    video: 'on',
    ignoreHTTPSErrors: true,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});
