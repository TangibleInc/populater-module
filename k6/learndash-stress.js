/**
 * LearnDash student journey stress test.
 *
 * Each VU logs in as a seeded student (ldstudent1, ldstudent2, …), enrolls in a
 * course when needed, marks topics complete in order, and submits the lesson quiz
 * (wpProQuiz via admin-ajax).
 *
 * Logins match Populater LearnDash seeded users (SeededUsername: ldstudent{N}).
 * Content slugs follow DeterministicTitle (e.g. learndash-course-1,
 * learndash-topic-c1-l1-t1, learndash-quiz-c1-l1-q1).
 *
 * Usage:
 *   cp .env.example .env   # set K6_BASE_URL, K6_USER_PASSWORD, pacing (K6_ACTION_DELAY, K6_THINK_TIME)
 *   composer k6:learndash:smoke
 *   composer k6:learndash
 *
 * Live dashboard: http://127.0.0.1:5665 (enabled by default via scripts/k6-run.sh).
 * Autosave: k6/reports/k6-report-<timestamp>.html and k6-results-<timestamp>.json.
 */

import { check, group, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import http from 'k6/http';
import execution from 'k6/execution';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8888').replace(/\/$/, '');
const USER_PASSWORD = __ENV.USER_PASSWORD || 'StressTest#2026';
/** Must match SeededUsername::prefix('learndash', 'student') in the Populater plugin. */
const LD_STUDENT_PREFIX = 'ldstudent';
const COURSE_INDEX = intEnv('COURSE_INDEX', 1);
const LESSON_COUNT = intEnv('LESSON_COUNT', 10);
const TOPIC_COUNT = intEnv('TOPIC_COUNT', 10);
/** Topic that hosts the lesson quiz (Populater attaches it to the last topic in the lesson). */
const QUIZ_TOPIC_INDEX = intEnv('QUIZ_TOPIC_INDEX', TOPIC_COUNT);
const QUIZ_INDEX = intEnv('QUIZ_INDEX', 1);
const MAX_USERS = intEnv('MAX_USERS', 20);
const COURSE_PER_USER = intEnv('COURSE_PER_USER', 0) === 1;
const THINK_TIME = floatEnv('THINK_TIME', 1);
const ACTION_DELAY = floatEnv('ACTION_DELAY', 0);
const CF_BYPASS_ENABLED = __ENV.CF_BYPASS !== '0';
const CF_USER_AGENT = __ENV.CF_USER_AGENT ?? 'bench2.com PopulaterK6/1.0';
const CF_BYPASS_HEADER = (__ENV.CF_BYPASS_HEADER || 'x-reviewsignal').toLowerCase();
const CF_BYPASS_VALUE = __ENV.CF_BYPASS_VALUE ?? '1';

const enrollSkippedNoForm = new Counter('enroll_skipped_no_form');
const enrollAttempted = new Counter('enroll_attempted');
const topicSkippedNoForm = new Counter('topic_skipped_no_form');
const topicMarkedComplete = new Counter('topic_marked_complete');

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
  return `/courses/learndash-course-${courseIndex}/`;
}

function lessonPath(courseIndex, lessonIndex) {
  return `/courses/learndash-course-${courseIndex}/lessons/learndash-lesson-c${courseIndex}-l${lessonIndex}/`;
}

function topicPath(courseIndex, lessonIndex, topicIndex) {
  return `${lessonPath(courseIndex, lessonIndex)}topics/learndash-topic-c${courseIndex}-l${lessonIndex}-t${topicIndex}/`;
}

function quizPath(courseIndex, lessonIndex, topicIndex, quizIndex) {
  return `${topicPath(courseIndex, lessonIndex, topicIndex)}quizzes/learndash-quiz-c${courseIndex}-l${lessonIndex}-q${quizIndex}/`;
}

function vuUser() {
  const vu = execution.vu.idInTest;
  const index = ((vu - 1) % MAX_USERS) + 1;

  return {
    username: `${LD_STUDENT_PREFIX}${index}`,
    index,
    courseIndex: COURSE_PER_USER ? index : COURSE_INDEX,
  };
}

function extractInput(html, name) {
  const forward = new RegExp(`name=["']${name}["'][^>]*value=["']([^"']*)["']`, 'i');
  const backward = new RegExp(`value=["']([^"']*)["'][^>]*name=["']${name}["']`, 'i');

  const match = html.match(forward) || html.match(backward);
  return match ? match[1] : null;
}

function extractScriptNumber(html, key) {
  const match = html.match(new RegExp(`${key}:\\s*(\\d+)`, 'i'));
  return match ? match[1] : null;
}

function extractScriptString(html, key) {
  const match = html.match(new RegExp(`${key}:\\s*'([^']+)'`, 'i'));
  return match ? match[1] : null;
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
        redirects: 0,
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
    const hasAccess =
      body.includes('user_has_access') || body.includes('ld-course-status-enrolled');
    const joinNonce = extractInput(body, 'course_join');
    const courseId = extractInput(body, 'course_id');

    if (hasAccess || !joinNonce || !courseId) {
      enrollSkippedNoForm.add(1);
      return;
    }

    enrollAttempted.add(1);

    const enrollResponse = http.post(
      `${BASE_URL}${path}`,
      {
        course_id: courseId,
        course_join: joinNonce,
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

function markTopicComplete(courseIndex, lessonIndex, topicIndex, jar) {
  const path = topicPath(courseIndex, lessonIndex, topicIndex);
  const topicPage = http.get(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET topic' },
  });

  check(topicPage, {
    [`topic L${lessonIndex} T${topicIndex} loads`]: (r) => r.status === 200,
  });
  pauseBetweenActions();

  const body = String(topicPage.body);
  if (!body.includes('sfwd-mark-complete')) {
    topicSkippedNoForm.add(1);
    return;
  }

  const postId = extractInput(body, 'post');
  const courseId = extractInput(body, 'course_id');
  const nonce = extractInput(body, 'sfwd_mark_complete');

  check(null, {
    [`topic L${lessonIndex} T${topicIndex} mark-complete nonce`]: () => nonce !== null,
  });

  if (!nonce || !postId) {
    return;
  }

  const payload = {
    post: postId,
    sfwd_mark_complete: nonce,
  };

  if (courseId) {
    payload.course_id = courseId;
  }

  const completeResponse = http.post(`${BASE_URL}${path}`, payload, {
    jar,
    headers: {
      ...htmlHeaders(path),
      'content-type': 'application/x-www-form-urlencoded',
      origin: BASE_URL,
    },
    tags: { name: 'POST mark topic complete' },
  });

  check(completeResponse, {
    [`topic L${lessonIndex} T${topicIndex} marked complete`]: (r) => r.status === 200,
  });

  if (completeResponse.status === 200) {
    topicMarkedComplete.add(1);
  }

  pauseBetweenActions();
}

function completeLessons(courseIndex, jar) {
  group('lessons', () => {
    for (let lessonIndex = 1; lessonIndex <= LESSON_COUNT; lessonIndex++) {
      for (let topicIndex = 1; topicIndex <= TOPIC_COUNT; topicIndex++) {
        markTopicComplete(courseIndex, lessonIndex, topicIndex, jar);
      }
    }
  });
}

function buildCheckResponses(questionIds) {
  const responses = {};

  for (const questionId of questionIds) {
    responses[String(questionId)] = {
      response: { 0: true, 1: false, 2: false },
      question_pro_id: questionId,
      question_post_id: 0,
    };
  }

  return responses;
}

function buildQuizResults(checked, globalPoints) {
  const now = Math.floor(Date.now() / 1000);
  const results = {
    comp: {
      points: 0,
      correctQuestions: 0,
      result: 0,
      quizTime: 5,
      quizEndTimestamp: now,
      quizStartTimestamp: now - 5,
      cats: {},
    },
  };

  let points = 0;
  let correctCount = 0;

  for (const [questionId, result] of Object.entries(checked)) {
    const questionPoints = result.p ?? 0;
    const isCorrect = Number(result.c) === 1;

    points += questionPoints;
    if (isCorrect) {
      correctCount += 1;
    }

    results[questionId] = {
      time: 1,
      points: questionPoints,
      correct: isCorrect ? 1 : 0,
      data: result.s ?? {},
      p_nonce: result.p_nonce ?? '',
      a_nonce: result.a_nonce ?? '',
      possiblePoints: result.e?.possiblePoints ?? 1,
    };
  }

  results.comp.points = points;
  results.comp.correctQuestions = correctCount;
  results.comp.result =
    globalPoints > 0 ? Math.round((points / globalPoints) * 100 * 100) / 100 : 0;

  return results;
}

function takeQuiz(courseIndex, lessonIndex, jar) {
  group('quiz', () => {
    const path = quizPath(courseIndex, lessonIndex, QUIZ_TOPIC_INDEX, QUIZ_INDEX);
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
    if (body.includes('complete the previous topic')) {
      return;
    }

    const quizNonce = extractScriptString(body, 'quiz_nonce');
    const quizProId = extractScriptNumber(body, 'quizId');
    const quizPostId = extractScriptNumber(body, 'quiz');
    const courseId = extractScriptNumber(body, 'course_id');
    const lessonId = extractScriptNumber(body, 'lesson_id');
    const topicId = extractScriptNumber(body, 'topic_id');
    const globalPoints = parseInt(extractScriptNumber(body, 'globalPoints') || '0', 10);

    const jsonMatch = body.match(/json:\s*(\{[^}]+\})/);
    let questionIds = [];

    if (jsonMatch) {
      try {
        const questionMap = JSON.parse(jsonMatch[1]);
        questionIds = Object.keys(questionMap).map((id) => parseInt(id, 10));
      } catch (_error) {
        questionIds = [];
      }
    }

    check(null, {
      'quiz nonce present': () => quizNonce !== null,
      'quiz pro id present': () => quizProId !== null,
      'quiz post id present': () => quizPostId !== null,
      'quiz has questions': () => questionIds.length > 0,
    });

    if (!quizNonce || !quizProId || !quizPostId || questionIds.length === 0) {
      return;
    }

    const responses = buildCheckResponses(questionIds);
    const quizStarted = Date.now();

    const checkPayload = {
      action: 'ld_adv_quiz_pro_ajax',
      func: 'checkAnswers',
      'data[course_id]': courseId || '',
      'data[quiz_nonce]': quizNonce,
      'data[quiz_started]': String(quizStarted),
      'data[quiz]': quizPostId,
      'data[quizId]': quizProId,
      'data[responses]': JSON.stringify(responses),
      'data[quiz_resume_data]': '[]',
    };

    const checkResponse = http.post(`${BASE_URL}/wp-admin/admin-ajax.php`, checkPayload, {
      jar,
      headers: ajaxHeaders(path),
      tags: { name: 'POST quiz checkAnswers' },
    });

    let checked = null;

    check(checkResponse, {
      'quiz answers checked': (r) => {
        if (r.status !== 200) {
          return false;
        }

        try {
          checked = r.json();
          return checked !== null && typeof checked === 'object';
        } catch (_error) {
          return false;
        }
      },
    });
    pauseBetweenActions();

    if (!checked || checkResponse.status !== 200) {
      return;
    }

    const results = buildQuizResults(checked, globalPoints || questionIds.length);
    const completePayload = {
      action: 'wp_pro_quiz_completed_quiz',
      course_id: courseId || '',
      lesson_id: lessonId || '',
      topic_id: topicId || '',
      quiz: quizPostId,
      quizId: quizProId,
      results: JSON.stringify(results),
      timespent: '5',
      forms: '[]',
      quiz_nonce: quizNonce,
    };

    const completeResponse = http.post(
      `${BASE_URL}/wp-admin/admin-ajax.php`,
      completePayload,
      {
        jar,
        headers: ajaxHeaders(path),
        tags: { name: 'POST quiz completed' },
      },
    );

    check(completeResponse, {
      'quiz completed': (r) => r.status === 200 && String(r.body).length > 2,
    });
    pauseBetweenActions();
  });
}

function takeLessonQuizzes(courseIndex, jar) {
  group('quizzes', () => {
    for (let lessonIndex = 1; lessonIndex <= LESSON_COUNT; lessonIndex++) {
      takeQuiz(courseIndex, lessonIndex, jar);
    }
  });
}

export default function () {
  const user = vuUser();
  const jar = http.cookieJar();

  login(user, jar);
  maybeEnroll(user, user.courseIndex, jar);
  completeLessons(user.courseIndex, jar);
  takeLessonQuizzes(user.courseIndex, jar);

  sleep(THINK_TIME);
}
