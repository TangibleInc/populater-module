import { check, fail, sleep } from 'k6';
import http from 'k6/http';
import execution from 'k6/execution';
import { Counter } from 'k6/metrics';

export const userPoolExhausted = new Counter('user_pool_exhausted');

export function intEnv(name, fallback) {
  const value = __ENV[name];
  if (value === undefined || value === '') {
    return fallback;
  }

  const parsed = parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : fallback;
}

export function floatEnv(name, fallback) {
  const value = __ENV[name];
  if (value === undefined || value === '') {
    return fallback;
  }

  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

export function boolEnv(name, fallback = false) {
  const value = __ENV[name];
  if (value === undefined || value === '') {
    return fallback;
  }

  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

export const STEP_THINK_TIME = Math.max(
  0,
  floatEnv('STEP_THINK_TIME', floatEnv('ACTION_DELAY', 1)),
);
export const COURSE_THINK_TIME = Math.max(0, floatEnv('THINK_TIME', 0));

// Captured HERE at init (module top-level), not read from __ENV inside a
// function at runtime: in k6 Cloud, values injected via Object.assign(__ENV, …)
// are visible during init but NOT from __ENV reads inside functions on the
// load generators. Every other env value in this suite is captured into a
// module var at init for exactly this reason — parseCourseStructure must read
// this const, never __ENV.COURSE_STRUCTURE directly, or it reads back empty.
export const COURSE_STRUCTURE_ENV = __ENV.COURSE_STRUCTURE || '';

// Dataset namespace. Seeders namespace every slug, username, and email with
// the fixture's dataset tag (e.g. fix-4xi8d8c5-lifterlms-course-1) so that
// coexisting fixtures never collide — without it, WordPress dedupes colliding
// slugs with -2/-3 suffixes and a slug-addressed journey silently lands on
// the OLDEST fixture's stale (possibly pre-enrolled) data. Unset = legacy
// un-namespaced names.
export const DATASET_TAG = __ENV.DATASET_TAG || '';

export function tagged(name) {
  return DATASET_TAG !== '' ? `${DATASET_TAG}-${name}` : name;
}

export function studentLoadOptions(lms) {
  return {
    tags: {
      lms,
    },
  };
}

export function logLoadProfile(label, maxUsers) {
  const vus = intEnv('PROFILE_VUS', 0);
  const profile = __ENV.K6_PROFILE || 'default';

  if (vus > 0 && maxUsers < vus) {
    console.warn(
      `MAX_USERS (${maxUsers}) < VUS (${vus}): seed at least one student per active VU.`,
    );
  }

  console.log(
    `${label} load profile: ${profile}; ${STEP_THINK_TIME}s between actions, ${COURSE_THINK_TIME}s between courses.`,
  );

  return {
    vus,
    maxUsers,
    stepThinkTime: STEP_THINK_TIME,
    courseThinkTime: COURSE_THINK_TIME,
  };
}

export function seededUser(prefix, fixedUsername = '') {
  if (fixedUsername !== '') {
    return {
      username: fixedUsername,
      index: 1,
      iteration: execution.scenario.iterationInInstance + 1,
    };
  }

  const index = execution.scenario.iterationInTest + 1;

  return {
    username: `${prefix}${index}`,
    index,
    iteration: execution.vu.iterationInInstance + 1,
  };
}

export function stopIfUserPoolExhausted(user, maxUsers) {
  if (user.index <= maxUsers) {
    return false;
  }

  userPoolExhausted.add(1);
  const message = `Seeded user pool exhausted at ${user.username}; increase K6_MAX_USERS or shorten the run.`;

  if (boolEnv('ABORT_ON_USER_POOL_EXHAUSTED', true)) {
    execution.test.abort(message);
  } else {
    console.warn(message);
  }

  return true;
}

export function studentStep() {
  if (STEP_THINK_TIME > 0) {
    sleep(STEP_THINK_TIME);
  }
}

export function thinkBetweenCourses() {
  if (COURSE_THINK_TIME > 0) {
    sleep(COURSE_THINK_TIME);
  }
}

export function must(condition, message) {
  check(null, {
    [message]: () => condition,
  });

  if (!condition) {
    fail(message);
  }
}

function decodeHtmlEntities(value) {
  return value
    .replace(/&#x([0-9a-f]+);/gi, (_match, code) => String.fromCharCode(parseInt(code, 16)))
    .replace(/&#([0-9]+);/g, (_match, code) => String.fromCharCode(parseInt(code, 10)))
    .replace(/[\u201c\u201d]/g, '"')
    .replace(/[\u2018\u2019]/g, "'")
    .replace(/&quot;/g, '"')
    .replace(/&#034;/g, '"')
    .replace(/&#34;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&#039;/g, "'")
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>');
}

export function parseCourseStructure(body) {
  // The platform seeds the data, so it authoritatively knows the course
  // structure. When it passes COURSE_STRUCTURE in the run env, trust that
  // directly: it's robust to themes that don't render the course post_content
  // (e.g. block/FSE themes that output only the LMS syllabus block), where the
  // in-page structure marker never reaches the HTML. Falls back to scraping
  // the marker for platform-agnostic seeds. Both the tbench and legacy
  // populater marker names are accepted.
  if (COURSE_STRUCTURE_ENV && COURSE_STRUCTURE_ENV !== '') {
    try {
      return JSON.parse(COURSE_STRUCTURE_ENV);
    } catch (error) {
      must(false, `COURSE_STRUCTURE env is valid JSON: ${error.message}`);
    }
  }

  const match =
    body.match(/<!--\s*(?:tbench|populater):structure\s+(\{[^>]*\})\s*-->/) ||
    body.match(/<script[^>]+id=["'](?:tbench|populater)-structure["'][^>]*>([\s\S]*?)<\/script>/) ||
    body.match(/\[(?:tbench|populater):structure\s+(\{[^]*?})]/);

  must(match !== null, 'course structure present');
  const json = decodeHtmlEntities(match[1].trim());

  try {
    return JSON.parse(json);
  } catch (error) {
    must(false, `course structure is valid JSON: ${error.message}`);
  }

  return null;
}

export function pacedGet(url, params) {
  const response = http.get(url, params);
  studentStep();
  return response;
}

export function pacedPost(url, body, params) {
  const response = http.post(url, body, params);
  studentStep();
  return response;
}
