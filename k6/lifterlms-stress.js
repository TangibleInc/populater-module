/**
 * LifterLMS student journey stress test.
 *
 * Based on the Grafana k6 Studio recording in lifter-1.js. Each VU logs in as a
 * different seeded student (lifterstudent1, lifterstudent2, …), enrolls in a course when
 * needed, completes lessons, and takes the section quiz.
 *
 * Logins match Populater LifterLMS seeded users (SeededUsername: lifterstudent{N}).
 * Content slugs follow DeterministicTitle (e.g. lifterlms-course-1, lifterlms-lesson-c1-l1).
 *
 * Usage:
 *   cp .env.example .env   # set K6_BASE_URL, K6_USER_PASSWORD, pacing (K6_ACTION_DELAY, K6_THINK_TIME)
 *   composer k6:lifter:smoke
 *   composer k6:lifter
 *
 * Live dashboard: http://127.0.0.1:5665 (enabled by default via scripts/k6-run.sh).
 * Autosave: k6/reports/k6-report-<timestamp>.html and k6-results-<timestamp>.json.
 */

import { check, group, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import http from 'k6/http';
import execution from 'k6/execution';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8888').replace(/\/$/, '');
const USER_PASSWORD = __ENV.LIFTER_USER_PASSWORD || __ENV.USER_PASSWORD || 'StressTest#2026';
/** Must match SeededUsername::prefix('lifterlms', 'student') in the Populater plugin. */
const LIFTER_STUDENT_PREFIX = 'lifterstudent';
const LIFTER_USERNAME = __ENV.LIFTER_USERNAME || '';
const MAX_USERS = intEnv('MAX_USERS', 20);
const THINK_TIME = floatEnv('THINK_TIME', 1);
const ACTION_DELAY = floatEnv('ACTION_DELAY', 0);
const LESSON_COUNT = intEnv('LESSON_COUNT', 10);
const SECTIONS_PER_COURSE = intEnv('SECTIONS_PER_COURSE', 5);
const QUIZZES_PER_SECTION = intEnv('QUIZZES_PER_SECTION', 1);
const CF_BYPASS_ENABLED = __ENV.CF_BYPASS !== '0';
const CF_USER_AGENT = __ENV.CF_USER_AGENT ?? 'bench2.com PopulaterK6/1.0';
const CF_BYPASS_HEADER = (__ENV.CF_BYPASS_HEADER || 'x-reviewsignal').toLowerCase();
const CF_BYPASS_VALUE = __ENV.CF_BYPASS_VALUE ?? '1';

const enrollSkippedNoForm = new Counter('enroll_skipped_no_form');
const enrollAttempted = new Counter('enroll_attempted');

export const options = {
  stages: [
    { target: intEnv('VUS', 20), duration: __ENV.RAMP_UP || '1m' },
    { target: intEnv('VUS', 20), duration: __ENV.HOLD || '3m30s' },
    { target: 0, duration: __ENV.RAMP_DOWN || '1m' },
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<8000'],
    checks: ['rate>0.90'],
  },
};

function intEnv(name, fallback) {
  const value = __ENV[name];
  if (value === undefined || value === '') {
    return fallback;
  }

  const parsed = parseInt(value, 10);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function floatEnv(name, fallback) {
  const value = __ENV[name];
  if (value === undefined || value === '') {
    return fallback;
  }

  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function pauseBetweenActions() {
  if (ACTION_DELAY > 0) {
    sleep(ACTION_DELAY);
  }
}

function coursePath(courseIndex) {
  return `/course/lifterlms-course-${courseIndex}/`;
}

function lessonPath(courseIndex, lessonIndex) {
  return `/lesson/lifterlms-lesson-c${courseIndex}-l${lessonIndex}/`;
}

function quizPath(courseIndex, sectionIndex, quizIndex) {
  return `/quiz/lifterlms-quiz-c${courseIndex}-s${sectionIndex}-q${quizIndex}/`;
}

// Fetch the course page once and parse the embedded structure metadata.
// Returns { lessons, sections_per_course, lessons_per_section, quizzes_per_lesson } or null.
function fetchCourseStructure(courseIndex, jar) {
  const path = coursePath(courseIndex);
  const res = http.get(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET course structure' },
  });

  check(res, { 'course page loads': (r) => r.status === 200 });

  if (res.status !== 200) {
    return null;
  }

  const body = String(res.body);
  const match = body.match(/<!--\s*populater:structure\s+(\{[^>]*\})\s*-->/);

  if (!match) {
    return fallbackCourseStructure();
  }

  try {
    return JSON.parse(match[1]);
  } catch (_error) {
    return fallbackCourseStructure();
  }
}

function fallbackCourseStructure() {
  const lessons = Math.max(1, LESSON_COUNT);
  const sectionsPerCourse = Math.max(1, SECTIONS_PER_COURSE);

  return {
    lessons,
    sections_per_course: sectionsPerCourse,
    lessons_per_section: Math.max(1, Math.ceil(lessons / sectionsPerCourse)),
    quizzes_per_lesson: Math.max(1, QUIZZES_PER_SECTION),
  };
}

function range(n) {
  return Array.from({ length: n }, (_, i) => i + 1);
}

function vuUser() {
  if (LIFTER_USERNAME !== '') {
    return {
      username: LIFTER_USERNAME,
      index: 1,
      courseIndex: 1,
    };
  }

  const vu = execution.vu.idInTest;
  const index = ((vu - 1) % MAX_USERS) + 1;

  return {
    username: `${LIFTER_STUDENT_PREFIX}${index}`,
    index,
    courseIndex: 1,
  };
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

function login(user, jar) {
  group('login', () => {
    const loginUrl = `${BASE_URL}/wp-login.php`;
    const loginPage = http.get(loginUrl, {
      jar,
      headers: requestHeaders(),
      tags: { name: 'GET /wp-login.php' },
    });

    check(loginPage, {
      'login page loads': (r) => r.status === 200,
    });
    pauseBetweenActions();

    const response = http.post(
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
        tags: { name: 'POST /wp-login.php' },
      },
    );

    check(response, {
      'login succeeded': (r) => {
        if (r.status === 429 || r.status === 503) {
          return false;
        }

        if (r.status === 302) {
          return true;
        }

        const body = String(r.body);
        return r.status === 200 && !body.includes('login_error') && !body.includes('Error 429');
      },
    });
    pauseBetweenActions();
  });
}

function maybeEnroll(user, courseIndex, jar) {
  group('enroll', () => {
    const path = coursePath(courseIndex);
    const coursePage = http.get(`${BASE_URL}${path}`, {
      jar,
      headers: htmlHeaders(path),
      tags: { name: 'GET course' },
    });

    check(coursePage, {
      'course page loads': (r) => r.status === 200,
    });
    pauseBetweenActions();

    const body = String(coursePage.body);
    const hasEnrollForm =
      body.includes('free_enroll') || body.includes('llms-free-enroll-form');

    if (!hasEnrollForm) {
      enrollSkippedNoForm.add(1);
      return;
    }

    enrollAttempted.add(1);

    const checkoutNonce = extractInput(body, '_llms_checkout_nonce');
    const planId = extractInput(body, 'llms_plan_id');

    check(null, {
      'checkout nonce present': () => checkoutNonce !== null,
      'plan id present': () => planId !== null,
    });

    const enrollResponse = http.post(
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

    check(enrollResponse, {
      'enrollment submitted': (r) => r.status === 200,
    });
    pauseBetweenActions();
  });
}

function completeLessons(courseIndex, structure, jar) {
  group('lessons', () => {
    for (const lessonIndex of range(structure.lessons)) {
      const path = lessonPath(courseIndex, lessonIndex);
      const lessonPage = http.get(`${BASE_URL}${path}`, {
        jar,
        headers: htmlHeaders(path),
        tags: { name: 'GET lesson' },
      });

      if (lessonPage.status !== 200) {
        continue;
      }

      check(lessonPage, {
        [`lesson ${lessonIndex} loads`]: (r) => r.status === 200,
      });
      pauseBetweenActions();

      const body = String(lessonPage.body);
      if (!body.includes('name="mark-complete"')) {
        continue;
      }

      const lessonId = extractInput(body, 'mark-complete');
      const nonce = extractInput(body, '_wpnonce');

      check(null, {
        [`lesson ${lessonIndex} mark-complete nonce`]: () => nonce !== null,
      });

      const completeResponse = http.post(
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

      check(completeResponse, {
        [`lesson ${lessonIndex} marked complete`]: (r) => r.status === 200,
      });
      pauseBetweenActions();
    }
  });
}

function takeQuiz(courseIndex, sectionIndex, quizIndex, jar) {
  group('quiz', () => {
    const path = quizPath(courseIndex, sectionIndex, quizIndex);
    const quizPage = http.get(`${BASE_URL}${path}`, {
      jar,
      headers: htmlHeaders(path),
      tags: { name: 'GET quiz' },
    });

    check(quizPage, {
      'quiz page loads': (r) => r.status === 200,
    });
    pauseBetweenActions();

    if (quizPage.status !== 200) {
      return;
    }

    const body = String(quizPage.body);
    const ajaxNonce = extractAjaxNonce(body);
    const lessonId = extractInput(body, 'llms_lesson_id');
    const quizId = extractInput(body, 'llms_quiz_id');

    check(null, {
      'quiz ajax nonce present': () => ajaxNonce !== null,
      'quiz id present': () => quizId !== null,
      'lesson id present': () => lessonId !== null,
    });

    if (!ajaxNonce || !quizId || !lessonId) {
      return;
    }

    const startResponse = http.post(
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

    check(startResponse, {
      'quiz started': (r) => r.status === 200 && r.json('success') === true,
    });
    pauseBetweenActions();

    if (startResponse.status !== 200 || startResponse.json('success') !== true) {
      return;
    }

    const startData = startResponse.json('data');
    let html = startData && startData.html ? startData.html : null;
    const attemptKey = startData ? startData.attempt_key : null;
    let answered = 0;

    while (html) {
      const questionIdMatch = html.match(/data-id="(\d+)"/);
      const questionTypeMatch = html.match(/data-type="([^"]+)"/);
      const answerId = pickCorrectAnswer(html);

      check(null, {
        [`question ${answered + 1} has correct answer`]: () => answerId !== null,
      });

      if (!questionIdMatch || !questionTypeMatch || !answerId || !attemptKey) {
        break;
      }
      pauseBetweenActions();

      const answerResponse = http.post(
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

      check(answerResponse, {
        [`question ${answered + 1} answered`]: (r) =>
          r.status === 200 && r.json('success') === true,
      });

      const answerData = answerResponse.json('data');
      html = answerData && answerData.html ? answerData.html : null;
      answered++;
      pauseBetweenActions();
    }

    check(null, {
      'answered at least one quiz question': () => answered > 0,
    });
  });
}

export default function () {
  const user = vuUser();
  const jar = http.cookieJar();

  login(user, jar);

  const structure = fetchCourseStructure(user.courseIndex, jar);

  check(null, {
    'course structure present': () => structure !== null,
  });

  if (!structure) {
    return;
  }

  maybeEnroll(user, user.courseIndex, jar);
  completeLessons(user.courseIndex, structure, jar);

  group('quizzes', () => {
    for (const sectionIndex of range(structure.sections_per_course)) {
      for (const quizIndex of range(structure.quizzes_per_lesson)) {
        takeQuiz(user.courseIndex, sectionIndex, quizIndex, jar);
        pauseBetweenActions();
      }
    }
  });

  sleep(THINK_TIME);
}
