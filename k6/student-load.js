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

export const STEP_THINK_TIME = Math.max(
  0,
  floatEnv('STEP_THINK_TIME', floatEnv('ACTION_DELAY', 1)),
);
export const COURSE_THINK_TIME = Math.max(0, floatEnv('THINK_TIME', 0));

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
  execution.test.abort(
    `Seeded user pool exhausted at ${user.username}; increase K6_MAX_USERS or shorten the run.`,
  );
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
  const match =
    body.match(/<!--\s*populater:structure\s+(\{[^>]*\})\s*-->/) ||
    body.match(/<script[^>]+id=["']populater-structure["'][^>]*>([\s\S]*?)<\/script>/) ||
    body.match(/\[populater:structure\s+(\{[^]*?})]/);

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
