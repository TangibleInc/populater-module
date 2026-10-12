/**
 * LearnDash student journey stress test.
 *
 * Each VU iteration logs in as the next seeded student (`ldstudent1`, `ldstudent2`, ...),
 * walks every course once (default 5), then never reuses that user on a later iteration.
 *
 * Logins match Populater LearnDash seeded users (SeededUsername: ldstudent{N}).
 * Content slugs follow DeterministicTitle (e.g. learndash-course-1,
 * learndash-topic-c1-l1-t1, learndash-quiz-c1-l1-q1).
 *
 * Usage:
 *   cp .env.example .env   # set K6_BASE_URL, K6_USER_PASSWORD, pacing (K6_STEP_THINK_TIME, K6_THINK_TIME)
 *   composer k6:learndash:smoke
 *   composer k6:learndash
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
const USER_PASSWORD = __ENV.USER_PASSWORD || 'StressTest#2026';
/** Must match SeededUsername::prefix('learndash', 'student') in the Populater plugin. */
const LD_STUDENT_PREFIX = tagged('ldstudent');
const MAX_USERS = intEnv('MAX_USERS', 20);
const COURSE_INDEX = intEnv('COURSE_INDEX', 1);
const COURSE_COUNT = intEnv('COURSE_COUNT', 5);
const COURSE_PER_USER = intEnv('COURSE_PER_USER', 0) === 1;
const CF_BYPASS_ENABLED = __ENV.CF_BYPASS !== '0';
const CF_USER_AGENT = __ENV.CF_USER_AGENT ?? 'bench2.com PopulaterK6/1.0';
const CF_BYPASS_HEADER = (__ENV.CF_BYPASS_HEADER || 'x-reviewsignal').toLowerCase();
const CF_BYPASS_VALUE = __ENV.CF_BYPASS_VALUE ?? '1';

const QUIZ_TOPIC_INDEX = intEnv('QUIZ_TOPIC_INDEX', 0);

export const options = studentLoadOptions('learndash');

function coursePath(courseIndex) {
  return `/courses/${tagged(`learndash-course-${courseIndex}`)}/`;
}

function lessonPath(courseIndex, lessonIndex) {
  return `/courses/${tagged(`learndash-course-${courseIndex}`)}/lessons/${tagged(`learndash-lesson-c${courseIndex}-l${lessonIndex}`)}/`;
}

function topicPath(courseIndex, lessonIndex, topicIndex) {
  return `/courses/${tagged(`learndash-course-${courseIndex}`)}/lessons/${tagged(`learndash-lesson-c${courseIndex}-l${lessonIndex}`)}/topics/${tagged(`learndash-topic-c${courseIndex}-l${lessonIndex}-t${topicIndex}`)}/`;
}

function quizPath(courseIndex, lessonIndex, topicIndex, quizIndex) {
  return `/courses/${tagged(`learndash-course-${courseIndex}`)}/lessons/${tagged(`learndash-lesson-c${courseIndex}-l${lessonIndex}`)}/topics/${tagged(`learndash-topic-c${courseIndex}-l${lessonIndex}-t${topicIndex}`)}/quizzes/${tagged(`learndash-quiz-c${courseIndex}-l${lessonIndex}-q${quizIndex}`)}/`;
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
  return seededUser(LD_STUDENT_PREFIX);
}

function quizTopicIndexFor(structure) {
  return QUIZ_TOPIC_INDEX > 0 ? QUIZ_TOPIC_INDEX : structure.topics_per_lesson;
}

function contentTopicIndices(structure) {
  const quizTopic = quizTopicIndexFor(structure);
  if (quizTopic <= 1) {
    return [];
  }

  return range(quizTopic - 1);
}

function hasQuizEmbed(body) {
  return body.includes('quiz_nonce') || body.includes('wpProQuiz') || body.includes('learndash-quiz');
}

export function setup() {
  return logLoadProfile('LearnDash', MAX_USERS);
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

function extractQuizMeta(body) {
  return {
    quizNonce: extractScriptString(body, 'quiz_nonce'),
    quizProId: extractScriptNumber(body, 'quizId'),
    quizPostId: extractScriptNumber(body, 'quiz'),
    courseId: extractScriptNumber(body, 'course_id'),
    lessonId: extractScriptNumber(body, 'lesson_id'),
    topicId: extractScriptNumber(body, 'topic_id'),
    globalPoints: parseInt(extractScriptNumber(body, 'globalPoints') || '0', 10),
  };
}

// Extract a JSON object value by key, handling nested braces.
// Matches both JS object literal style (key:) and JSON style ("key":).
function extractBalancedJson(html, key) {
  const patterns = [`${key}:`, `"${key}":`];
  let keyIdx = -1;
  let keyLen = 0;

  for (const pattern of patterns) {
    const idx = html.indexOf(pattern);
    if (idx !== -1) {
      keyIdx = idx;
      keyLen = pattern.length;
      break;
    }
  }

  if (keyIdx === -1) return null;

  let i = keyIdx + keyLen;
  while (i < html.length && html[i] !== '{') i++;
  if (i >= html.length) return null;

  let depth = 0;
  const start = i;
  for (; i < html.length; i++) {
    if (html[i] === '{') depth++;
    else if (html[i] === '}') {
      depth--;
      if (depth === 0) return html.substring(start, i + 1);
    }
  }
  return null;
}

function extractQuestionIds(body) {
  const jsonStr = extractBalancedJson(body, 'json');
  must(jsonStr !== null, 'quiz question JSON present');

  let questionMap;
  try {
    questionMap = JSON.parse(jsonStr);
  } catch (error) {
    must(false, `quiz question JSON is valid: ${error.message}`);
  }

  const ids = Object.keys(questionMap).map((id) => parseInt(id, 10));
  must(ids.length > 0, 'quiz has questions');
  return ids;
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
  const hasAccess =
    body.includes('user_has_access') || body.includes('ld-course-status-enrolled');
  const joinNonce = extractInput(body, 'course_join');
  const courseId = extractInput(body, 'course_id');

  must(!hasAccess, 'student is not already enrolled');
  must(joinNonce !== null, 'course join nonce present');
  must(courseId !== null, 'course id present');

  const enrollResponse = pacedPost(
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

  must(enrollResponse.status === 200, 'enrollment submitted');
}

function markTopicComplete(courseIndex, lessonIndex, topicIndex, jar) {
  const path = topicPath(courseIndex, lessonIndex, topicIndex);
  const topicPage = pacedGet(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET topic' },
  });

  must(topicPage.status === 200, `topic L${lessonIndex} T${topicIndex} loads`);

  const body = String(topicPage.body);
  const postId = extractInput(body, 'post');
  const courseId = extractInput(body, 'course_id');
  const nonce = extractInput(body, 'sfwd_mark_complete');

  must(nonce !== null, `topic L${lessonIndex} T${topicIndex} is incomplete`);
  must(nonce !== null, `topic L${lessonIndex} T${topicIndex} mark-complete nonce present`);
  must(postId !== null, `topic L${lessonIndex} T${topicIndex} post id present`);
  must(courseId !== null, `topic L${lessonIndex} T${topicIndex} course id present`);

  const payload = {
    post: postId,
    sfwd_mark_complete: nonce,
    course_id: courseId,
  };

  const completeResponse = pacedPost(`${BASE_URL}${path}`, payload, {
    jar,
    headers: {
      ...htmlHeaders(path),
      'content-type': 'application/x-www-form-urlencoded',
      origin: BASE_URL,
    },
    tags: { name: 'POST mark topic complete' },
  });

  must(
    completeResponse.status === 200,
    `topic L${lessonIndex} T${topicIndex} marked complete`,
  );
}

// Populater seeds single-choice questions with index 0 as the correct answer.
function buildQuestionResponse(questionId) {
  return {
    [String(questionId)]: {
      response: { 0: true, 1: false, 2: false },
      question_pro_id: questionId,
      question_post_id: 0,
    },
  };
}

function parseCheckedAnswers(response) {
  must(response.status === 200, 'quiz answer response is 200');
  const checked = response.json();
  must(checked !== null && typeof checked === 'object', 'quiz answer response is JSON');
  return checked;
}

function buildQuizResults(checked, globalPoints, timeSpentSeconds) {
  const now = Math.floor(Date.now() / 1000);
  const results = {
    comp: {
      points: 0,
      correctQuestions: 0,
      result: 0,
      quizTime: timeSpentSeconds,
      quizEndTimestamp: now,
      quizStartTimestamp: now - timeSpentSeconds,
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

function takeQuiz(courseIndex, lessonIndex, topicIndex, quizIndex, jar) {
  const path = quizPath(courseIndex, lessonIndex, topicIndex, quizIndex);
  const quizPage = pacedGet(`${BASE_URL}${path}`, {
    jar,
    headers: htmlHeaders(path),
    tags: { name: 'GET quiz' },
  });

  must(quizPage.status === 200, 'quiz page loads');

  const body = String(quizPage.body);
  must(!body.includes('complete the previous topic'), 'quiz prerequisites are complete');
  must(hasQuizEmbed(body), 'quiz embed present');

  const { quizNonce, quizProId, quizPostId, courseId, lessonId, topicId, globalPoints } =
    extractQuizMeta(body);
  const questionIds = extractQuestionIds(body);

  must(quizNonce !== null, 'quiz nonce present');
  must(quizProId !== null, 'quiz pro id present');
  must(quizPostId !== null, 'quiz post id present');
  must(courseId !== null, 'quiz course id present');
  must(lessonId !== null, 'quiz lesson id present');
  must(topicId !== null, 'quiz topic id present');

  const quizStarted = Date.now();
  const checked = {};

  for (let i = 0; i < questionIds.length; i++) {
    const questionId = questionIds[i];
    const checkResponse = pacedPost(
      `${BASE_URL}/wp-admin/admin-ajax.php`,
      {
        action: 'ld_adv_quiz_pro_ajax',
        func: 'checkAnswers',
        'data[course_id]': courseId,
        'data[quiz_nonce]': quizNonce,
        'data[quiz_started]': String(quizStarted),
        'data[quiz]': quizPostId,
        'data[quizId]': quizProId,
        'data[responses]': JSON.stringify(buildQuestionResponse(questionId)),
        'data[quiz_resume_data]': '[]',
      },
      {
        jar,
        headers: ajaxHeaders(path),
        tags: { name: 'POST quiz_answer' },
      },
    );

    const partial = parseCheckedAnswers(checkResponse);
    must(partial !== null, `question ${i + 1} answered`);

    Object.assign(checked, partial);
  }

  must(Object.keys(checked).length === questionIds.length, 'all quiz questions answered');

  const timeSpentSeconds = Math.max(1, Math.round((Date.now() - quizStarted) / 1000));
  const results = buildQuizResults(checked, globalPoints || questionIds.length, timeSpentSeconds);
  const completeResponse = pacedPost(
    `${BASE_URL}/wp-admin/admin-ajax.php`,
    {
      action: 'wp_pro_quiz_completed_quiz',
      course_id: courseId,
      lesson_id: lessonId,
      topic_id: topicId,
      quiz: quizPostId,
      quizId: quizProId,
      results: JSON.stringify(results),
      timespent: String(timeSpentSeconds),
      forms: '[]',
      quiz_nonce: quizNonce,
    },
    {
      jar,
      headers: ajaxHeaders(path),
      tags: { name: 'POST quiz completed' },
    },
  );

  must(completeResponse.status === 200 && String(completeResponse.body).length > 2, 'quiz completed');
}

function completeLesson(courseIndex, lessonIndex, structure, jar) {
  for (const topicIndex of contentTopicIndices(structure)) {
    markTopicComplete(courseIndex, lessonIndex, topicIndex, jar);
  }

  const quizTopicIndex = quizTopicIndexFor(structure);

  for (const quizIndex of range(structure.quizzes_per_lesson)) {
    takeQuiz(courseIndex, lessonIndex, quizTopicIndex, quizIndex, jar);
  }
}

function completeCourse(user, courseIndex, jar) {
  group('course', () => {
    const structure = fetchCourseStructure(courseIndex, jar);

    enroll(user, courseIndex, jar);

    for (const lessonIndex of range(structure.lessons)) {
      completeLesson(courseIndex, lessonIndex, structure, jar);
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
