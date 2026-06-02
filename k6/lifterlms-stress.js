/**
 * LifterLMS student journey stress test.
 *
 * Based on the Grafana k6 Studio recording in lifter-1.js. Each VU logs in as a
 * different seeded student (student1, student2, …), enrolls in a course when
 * needed, completes lessons, and takes the section quiz.
 *
 * Seeded content slugs follow DeterministicTitle conventions from the Populater
 * plugin (e.g. lifterlms-course-1, lifterlms-lesson-c1-l1).
 *
 * Usage:
 *   cp .env.example .env   # set K6_BASE_URL and K6_USER_PASSWORD
 *   composer k6:lifter:smoke
 *   composer k6:lifter
 */

import { check, group, sleep } from 'k6';
import http from 'k6/http';
import execution from 'k6/execution';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8888').replace(/\/$/, '');
const USER_PASSWORD = __ENV.USER_PASSWORD || 'StressTest#2026';
const USER_PREFIX = __ENV.USER_PREFIX || 'student';
const COURSE_INDEX = intEnv('COURSE_INDEX', 1);
const LESSON_COUNT = intEnv('LESSON_COUNT', 10);
const SECTION_INDEX = intEnv('SECTION_INDEX', 1);
const QUIZ_INDEX = intEnv('QUIZ_INDEX', 1);
const MAX_USERS = intEnv('MAX_USERS', 20);
const THINK_TIME = floatEnv('THINK_TIME', 1);

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

function coursePath(courseIndex) {
  return `/course/lifterlms-course-${courseIndex}/`;
}

function lessonPath(courseIndex, lessonIndex) {
  return `/lesson/lifterlms-lesson-c${courseIndex}-l${lessonIndex}/`;
}

function quizPath(courseIndex, sectionIndex, quizIndex) {
  return `/quiz/lifterlms-quiz-c${courseIndex}-s${sectionIndex}-q${quizIndex}/`;
}

function vuUser() {
  const vu = execution.vu.idInTest;
  const index = ((vu - 1) % MAX_USERS) + 1;

  return {
    username: `${USER_PREFIX}${index}`,
    index,
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

function htmlHeaders(refererPath) {
  return {
    accept:
      'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    referer: `${BASE_URL}${refererPath}`,
  };
}

function ajaxHeaders(refererPath) {
  return {
    accept: 'application/json, text/javascript, */*; q=0.01',
    'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
    'x-requested-with': 'XMLHttpRequest',
    origin: BASE_URL,
    referer: `${BASE_URL}${refererPath}`,
  };
}

function login(user, jar) {
  group('login', () => {
    const loginUrl = `${BASE_URL}/wp-login.php`;
    const loginPage = http.get(loginUrl, {
      jar,
      tags: { name: 'GET /wp-login.php' },
    });

    check(loginPage, {
      'login page loads': (r) => r.status === 200,
    });

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

    const body = String(coursePage.body);
    if (!body.includes('free_enroll') && !body.includes('llms-free-enroll-form')) {
      return;
    }

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
  });
}

function completeLessons(courseIndex, jar) {
  group('lessons', () => {
    for (let lessonIndex = 1; lessonIndex <= LESSON_COUNT; lessonIndex++) {
      const path = lessonPath(courseIndex, lessonIndex);
      const lessonPage = http.get(`${BASE_URL}${path}`, {
        jar,
        headers: htmlHeaders(path),
        tags: { name: 'GET lesson' },
      });

      check(lessonPage, {
        [`lesson ${lessonIndex} loads`]: (r) => r.status === 200,
      });

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
  maybeEnroll(user, COURSE_INDEX, jar);
  completeLessons(COURSE_INDEX, jar);
  takeQuiz(COURSE_INDEX, SECTION_INDEX, QUIZ_INDEX, jar);

  sleep(THINK_TIME);
}
