/**
 * LifterLMS student journey stress test.
 *
 * Based on the Grafana k6 Studio recording in lifter-1.js. Each VU logs in as a
 * Each VU iteration logs in as the next seeded student (`lifterstudent1`, `lifterstudent2`, ...),
 * walks every course once (default 5), then never reuses that user on a later iteration.
 *
 * Logins match Populater LifterLMS seeded users (SeededUsername: lifterstudent{N}).
 * Content slugs follow DeterministicTitle (e.g. lifterlms-course-1, lifterlms-lesson-c1-l1).
 *
 * Usage:
 *   cp .env.example .env   # set K6_BASE_URL, K6_USER_PASSWORD, pacing (K6_STEP_THINK_TIME, K6_THINK_TIME)
 *   composer k6:lifter:smoke
 *   composer k6:lifter
 *
 * Live dashboard: http://127.0.0.1:5665 (enabled by default via scripts/k6-run.sh).
 * Autosave: k6/reports/k6-report-<timestamp>.html and k6-summary-<timestamp>.json.
 */

import { group } from 'k6';
import http from 'k6/http';
import {
  intEnv,
  logLoadProfile,
  must,
  parseCourseStructure,
  pacedGet,
  pacedPost,
  seededUser,
  stopIfUserPoolExhausted,
  studentLoadOptions,
  tagged,
  thinkBetweenCourses,
} from './student-load.js';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8888').replace(/\/$/, '');
const USER_PASSWORD = __ENV.LIFTER_USER_PASSWORD || __ENV.USER_PASSWORD || 'StressTest#2026';
/** Must match SeededUsername::prefix('lifterlms', 'student') in the Populater plugin. */
const LIFTER_STUDENT_PREFIX = tagged('lifterstudent');
const LIFTER_USERNAME = __ENV.LIFTER_USERNAME || '';
const MAX_USERS = intEnv('MAX_USERS', 20);
const COURSE_INDEX = intEnv('COURSE_INDEX', 1);
const COURSE_COUNT = intEnv('COURSE_COUNT', 5);
const COURSE_PER_USER = intEnv('COURSE_PER_USER', 0) === 1;
const CF_BYPASS_ENABLED = __ENV.CF_BYPASS !== '0';
const CF_USER_AGENT = __ENV.CF_USER_AGENT ?? 'bench2.com PopulaterK6/1.0';
const CF_BYPASS_HEADER = (__ENV.CF_BYPASS_HEADER || 'x-reviewsignal').toLowerCase();
const CF_BYPASS_VALUE = __ENV.CF_BYPASS_VALUE ?? '1';

export const options = studentLoadOptions('lifterlms');

function coursePath(courseIndex) {
  return `/course/${tagged(`lifterlms-course-${courseIndex}`)}/`;
}

function lessonPath(courseIndex, lessonIndex) {
  return `/lesson/${tagged(`lifterlms-lesson-c${courseIndex}-l${lessonIndex}`)}/`;
}

function quizPath(courseIndex, sectionIndex, quizIndex) {
  return `/quiz/${tagged(`lifterlms-quiz-c${courseIndex}-s${sectionIndex}-q${quizIndex}`)}/`;
}

function fetchCourseStructure(courseIndex, jar) {
  const path = coursePath(courseIndex);
  const res = pacedGet(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET course structure' },
  });

  must(res.status === 200, 'course page loads');

  return parseCourseStructure(String(res.body));
}

function range(n) {
  return Array.from({ length: n }, (_, i) => i + 1);
}

function courseIndices() {
  return range(COURSE_COUNT).map((offset) => COURSE_INDEX + offset - 1);
}

function vuUser() {
  return seededUser(LIFTER_STUDENT_PREFIX, LIFTER_USERNAME);
}

function lessonsPerSection(structure) {
  must(structure.lessons_per_section > 0, 'lessons per section present');
  return structure.lessons_per_section;
}

function lessonIndicesForSection(structure, sectionIndex) {
  const perSection = lessonsPerSection(structure);
  const start = (sectionIndex - 1) * perSection + 1;
  const end = Math.min(sectionIndex * perSection, structure.lessons);
  const indices = [];

  for (let lessonIndex = start; lessonIndex <= end; lessonIndex++) {
    indices.push(lessonIndex);
  }

  return indices;
}

function hasLifterQuizEmbed(body) {
  return body.includes('llms_quiz_id') || body.includes('llms-lesson-quiz') || body.includes('llms_quiz');
}

export function setup() {
  return logLoadProfile('LifterLMS', MAX_USERS);
}

function extractInput(html, name) {
  const forward = new RegExp(`name=["']${name}["'][^>]*value=["']([^"']*)["']`, 'i');
  const backward = new RegExp(`value=["']([^"']*)["'][^>]*name=["']${name}["']`, 'i');

  const match = html.match(forward) || html.match(backward);
  return match ? match[1] : null;
}

function extractAjaxNonce(html) {
  const inline = html.match(/window\.llms\.ajax_nonce = "([^"]+)"/);
  return inline ? inline[1] : extractInput(html, '_ajax_nonce');
}

function pickCorrectAnswer(html) {
  const blocks = html.match(/<div class="llms-choice[\s\S]*?<\/div>\s*/g) || [];

  for (const block of blocks) {
    if (!block.includes('correct answer')) {
      continue;
    }

    const match = block.match(/value="([^"]+)"/);
    if (match) {
      return match[1];
    }
  }

  return null;
}

function requestHeaders(extra = {}) {
  const headers = { ...extra };

  if (!CF_BYPASS_ENABLED) {
    return headers;
  }

  if (CF_USER_AGENT !== '') {
    headers['User-Agent'] = CF_USER_AGENT;
  }

  if (CF_BYPASS_VALUE !== '') {
    headers[CF_BYPASS_HEADER] = CF_BYPASS_VALUE;
  }

  return headers;
}

function htmlHeaders(refererPath) {
  return requestHeaders({
    accept:
      'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    referer: `${BASE_URL}${refererPath}`,
  });
}

function ajaxHeaders(refererPath) {
  return requestHeaders({
    accept: 'application/json, text/javascript, */*; q=0.01',
    'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
    'x-requested-with': 'XMLHttpRequest',
    origin: BASE_URL,
    referer: `${BASE_URL}${refererPath}`,
  });
}

function loginResponseOk(response) {
  if (response.status === 429 || response.status === 503) {
    return false;
  }

  if (response.status === 302) {
    return true;
  }

  const body = String(response.body);
  return response.status === 200 && !body.includes('login_error') && !body.includes('Error 429');
}

function login(user, jar) {
  const loginUrl = `${BASE_URL}/wp-login.php`;

  const loginPage = pacedGet(loginUrl, {
    jar,
    headers: requestHeaders(),
    tags: { name: 'GET /wp-login.php' },
  });

  must(loginPage.status === 200, 'login page loads');

  const response = pacedPost(
    loginUrl,
    {
      log: user.username,
      pwd: USER_PASSWORD,
      'wp-submit': 'Log In',
      redirect_to: `${BASE_URL}/`,
      testcookie: '1',
    },
    {
      jar,
      headers: requestHeaders(),
      redirects: 0,
      tags: { name: 'POST /wp-login.php' },
    },
  );

  must(loginResponseOk(response), 'login succeeded');
}

function enroll(user, courseIndex, jar) {
  const path = coursePath(courseIndex);
  const coursePage = pacedGet(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET course' },
  });

  must(coursePage.status === 200, 'course page loads');

  const body = String(coursePage.body);
  const hasEnrollForm =
    body.includes('free_enroll') || body.includes('llms-free-enroll-form');

  must(hasEnrollForm, 'course enrollment form available for fresh un-enrolled student');

  const checkoutNonce = extractInput(body, '_llms_checkout_nonce');
  const planId = extractInput(body, 'llms_plan_id');

  must(checkoutNonce !== null, 'checkout nonce present');
  must(planId !== null, 'plan id present');

  const enrollResponse = pacedPost(
    `${BASE_URL}${path}`,
    {
      first_name: 'Student',
      last_name: String(user.index),
      llms_billing_address_1: `${user.index} Populater Lane`,
      llms_billing_address_2: '',
      llms_billing_city: 'Testville',
      llms_billing_country: 'US',
      llms_billing_state: 'CA',
      llms_billing_zip: '90210',
      llms_phone: '',
      free_checkout_redirect: '',
      llms_plan_id: planId,
      _llms_checkout_nonce: checkoutNonce,
      _wp_http_referer: path,
      action: 'create_pending_order',
      form: 'free_enroll',
      llms_agree_to_terms: 'yes',
    },
    {
      jar,
      headers: {
        ...htmlHeaders(path),
        'content-type': 'application/x-www-form-urlencoded',
        origin: BASE_URL,
      },
      tags: { name: 'POST course enroll' },
    },
  );

  must(enrollResponse.status === 200, 'enrollment submitted');
}

function completeLesson(courseIndex, lessonIndex, jar) {
  const path = lessonPath(courseIndex, lessonIndex);
  const lessonPage = pacedGet(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET lesson' },
  });

  must(lessonPage.status === 200, `lesson ${lessonIndex} loads`);

  const body = String(lessonPage.body);
  must(body.includes('name="mark-complete"'), `lesson ${lessonIndex} is incomplete`);

  const lessonId = extractInput(body, 'mark-complete');
  const nonce = extractInput(body, '_wpnonce');

  must(nonce !== null, `lesson ${lessonIndex} mark-complete nonce present`);
  must(lessonId !== null, `lesson ${lessonIndex} id present`);

  const completeResponse = pacedPost(
    `${BASE_URL}${path}`,
    {
      'mark-complete': lessonId,
      action: 'mark_complete',
      _wpnonce: nonce,
      _wp_http_referer: path,
      mark_complete: 'Mark Complete',
    },
    {
      jar,
      headers: {
        ...htmlHeaders(path),
        'content-type': 'application/x-www-form-urlencoded',
        origin: BASE_URL,
      },
      tags: { name: 'POST mark complete' },
    },
  );

  must(completeResponse.status === 200, `lesson ${lessonIndex} marked complete`);
}

function takeQuiz(courseIndex, sectionIndex, quizIndex, jar) {
  const path = quizPath(courseIndex, sectionIndex, quizIndex);
  const quizPage = pacedGet(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET quiz' },
  });

  must(quizPage.status === 200, 'quiz page loads');

  const body = String(quizPage.body);
  must(hasLifterQuizEmbed(body), 'quiz embed present');

  const ajaxNonce = extractAjaxNonce(body);
  const lessonId = extractInput(body, 'llms_lesson_id');
  const quizId = extractInput(body, 'llms_quiz_id');

  must(ajaxNonce !== null, 'quiz ajax nonce present');
  must(quizId !== null, 'quiz id present');
  must(lessonId !== null, 'lesson id present');

  const startResponse = pacedPost(
    `${BASE_URL}/wp-admin/admin-ajax.php`,
    {
      action: 'quiz_start',
      lesson_id: lessonId,
      quiz_id: quizId,
      _ajax_nonce: ajaxNonce,
      post_id: '',
    },
    {
      jar,
      headers: ajaxHeaders(path),
      tags: { name: 'POST quiz_start' },
    },
  );

  must(startResponse.status === 200 && startResponse.json('success') === true, 'quiz started');

  const startData = startResponse.json('data');
  must(startData !== null && typeof startData === 'object', 'quiz start data present');
  let html = startData.html;
  const attemptKey = startData.attempt_key;
  must(typeof html === 'string' && html !== '', 'first quiz question present');
  must(typeof attemptKey === 'string' && attemptKey !== '', 'quiz attempt key present');
  let answered = 0;

  while (html) {
    const questionIdMatch = html.match(/data-id="(\d+)"/);
    const questionTypeMatch = html.match(/data-type="([^"]+)"/);
    const answerId = pickCorrectAnswer(html);

    must(questionIdMatch !== null, `question ${answered + 1} id present`);
    must(questionTypeMatch !== null, `question ${answered + 1} type present`);
    must(answerId !== null, `question ${answered + 1} has correct answer`);

    const answerResponse = pacedPost(
      `${BASE_URL}/wp-admin/admin-ajax.php`,
      {
        action: 'quiz_answer_question',
        'answer[]': answerId,
        attempt_key: attemptKey,
        question_id: questionIdMatch[1],
        question_type: questionTypeMatch[1],
        _ajax_nonce: ajaxNonce,
        post_id: '',
      },
      {
        jar,
        headers: ajaxHeaders(path),
        tags: { name: 'POST quiz_answer' },
      },
    );

    must(
      answerResponse.status === 200 && answerResponse.json('success') === true,
      `question ${answered + 1} answered`,
    );

    const answerData = answerResponse.json('data');
    html = answerData && answerData.html ? answerData.html : null;
    answered++;
  }

  must(answered > 0, 'answered at least one quiz question');
}

function completeCourse(user, courseIndex, jar) {
  group('course', () => {
    const structure = fetchCourseStructure(courseIndex, jar);

    enroll(user, courseIndex, jar);

    for (const sectionIndex of range(structure.sections_per_course)) {
      const perSection = lessonsPerSection(structure);
      const quizHostStart = sectionIndex * perSection - structure.quizzes_per_lesson + 1;

      for (const lessonIndex of lessonIndicesForSection(structure, sectionIndex)) {
        if (lessonIndex >= quizHostStart) {
          continue;
        }

        completeLesson(courseIndex, lessonIndex, jar);
      }

      for (const quizIndex of range(structure.quizzes_per_lesson)) {
        takeQuiz(courseIndex, sectionIndex, quizIndex, jar);
      }
    }
  });
}

export default function () {
  const user = vuUser();

  if (stopIfUserPoolExhausted(user, MAX_USERS)) {
    return;
  }

  const jar = http.cookieJar();

  login(user, jar);

  const courses = courseIndices().filter(
    (courseIndex) => !COURSE_PER_USER || courseIndex === user.index,
  );

  for (let i = 0; i < courses.length; i++) {
    completeCourse(user, courses[i], jar);
    if (i < courses.length - 1) {
      thinkBetweenCourses();
    }
  }
}
