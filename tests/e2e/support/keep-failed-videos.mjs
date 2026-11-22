#!/usr/bin/env node
/**
 * Keeps the videos of the failed tests only (decision 2026-11-22).
 *
 * A green run uploads the video of every test: those recordings document the
 * intended journey. A red run uploads only the videos of the tests that
 * failed - that is where the evidence is, and the rest would only bury it.
 * The workflows call this script on a red run only (`if: failure()`); a
 * cancelled run keeps everything.
 *
 * Usage: node keep-failed-videos.mjs <results.json> <dir> [<dir> ...]
 *
 * "Failed" is every attempt with the status failed, timedOut or interrupted
 * in Playwright's JSON report; with retries, the failed attempts of a test
 * that passed later are kept too. Every other video file (.webm, .mp4) below
 * the given directories is deleted - the copies the HTML report keeps in
 * data/ as well, which it names by the SHA-1 of the content, so files are
 * matched by content, not by path. In the HTML report the tests that passed
 * then show a missing video.
 *
 * Without a readable results.json nothing is deleted: which videos belong to
 * failed tests is then unknown, and losing the evidence is worse than a large
 * artefact.
 *
 * Prints a Markdown note for the job summary; exits 0 except on bad usage.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const [resultsFile, ...roots] = process.argv.slice(2);
if (!resultsFile || roots.length === 0) {
  console.error('Usage: node keep-failed-videos.mjs <results.json> <dir> [<dir> ...]');
  process.exit(2);
}

const FAILED = new Set(['failed', 'timedOut', 'interrupted']);
const VIDEO = new Set(['.webm', '.mp4']);

/** Every attempt of every test in a suite tree of Playwright's JSON report. */
function* attempts(suite, titles = []) {
  const here = suite.title ? [...titles, suite.title] : titles;
  for (const spec of suite.specs ?? []) {
    for (const test of spec.tests ?? []) {
      for (const result of test.results ?? []) {
        yield { title: [...here, spec.title].join(' › '), project: test.projectName, result };
      }
    }
  }
  for (const child of suite.suites ?? []) {
    yield* attempts(child, here);
  }
}

/** SHA-1 of a file's content, as the HTML reporter names its copies. */
function sha1(file) {
  return crypto.createHash('sha1').update(fs.readFileSync(file)).digest('hex');
}

/** Every video file below a directory. */
function* videos(dir) {
  let entries;
  try {
    entries = fs.readdirSync(dir, { withFileTypes: true });
  } catch {
    return;
  }
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      yield* videos(full);
    } else if (entry.isFile() && VIDEO.has(path.extname(entry.name).toLowerCase())) {
      yield full;
    }
  }
}

let report;
try {
  report = JSON.parse(fs.readFileSync(resultsFile, 'utf8'));
} catch (error) {
  console.log(`**Videos:** no readable result file (${resultsFile}: ${error.message}); `
    + 'all videos kept, since which belong to failed tests is unknown.');
  process.exit(0);
}

const keep = new Set();
const failedTests = [];
for (const suite of report.suites ?? []) {
  for (const { title, project, result } of attempts(suite)) {
    if (!FAILED.has(result.status)) {
      continue;
    }
    const recorded = (result.attachments ?? []).filter((attachment) =>
      (attachment.contentType ?? '').startsWith('video/') && attachment.path && fs.existsSync(attachment.path));
    for (const attachment of recorded) {
      keep.add(sha1(attachment.path));
    }
    const attempt = result.retry ? `, attempt ${result.retry + 1}` : '';
    failedTests.push(`${title} [${project}${attempt}]: ${recorded.length ? `${recorded.length} video(s)` : 'no video'}`);
  }
}

let kept = 0;
let removed = 0;
for (const root of roots) {
  for (const file of videos(root)) {
    if (keep.has(sha1(file))) {
      kept++;
    } else {
      fs.rmSync(file, { force: true });
      removed++;
    }
  }
}

console.log(`**Videos (red run):** only those of the failed tests are in the artefact - `
  + `${kept} file(s) kept, ${removed} of passed or skipped tests removed. `
  + 'A green run keeps every video.');
if (failedTests.length === 0) {
  console.log('No test failed; the run is red for another reason (see the steps above), so no video is kept.');
} else {
  console.log('');
  for (const line of failedTests) {
    console.log(`- ${line}`);
  }
}
