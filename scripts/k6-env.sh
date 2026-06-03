#!/usr/bin/env bash
# Shared k6 environment resolution. Source from k6-run.sh.

k6_load_dotenv() {
  local root="${1:-.}"
  local env_file="${root}/.env"

  if [[ -f "$env_file" ]]; then
    set -a
    # shellcheck disable=SC1090
    source "$env_file"
    set +a
  fi
}

k6_resolve_env() {
  K6_RESOLVED_BASE_URL="${K6_BASE_URL:-http://host.docker.internal:${PORT:-8888}}"
  K6_RESOLVED_USER_PASSWORD="${K6_USER_PASSWORD:-StressTest#2026}"

  export K6_RESOLVED_BASE_URL K6_RESOLVED_USER_PASSWORD
}

k6_export_env_args() {
  K6_ENV_ARGS=(
    -e "BASE_URL=${K6_RESOLVED_BASE_URL}"
    -e "USER_PASSWORD=${K6_RESOLVED_USER_PASSWORD}"
    -e "USER_PREFIX=${K6_USER_PREFIX:-student}"
    -e "VUS=${K6_VUS:-20}"
    -e "MAX_USERS=${K6_MAX_USERS:-20}"
    -e "RAMP_UP=${K6_RAMP_UP:-1m}"
    -e "HOLD=${K6_HOLD:-3m30s}"
    -e "RAMP_DOWN=${K6_RAMP_DOWN:-1m}"
    -e "THINK_TIME=${K6_THINK_TIME:-1}"
    -e "COURSE_INDEX=${K6_COURSE_INDEX:-1}"
    -e "COURSE_PER_USER=${K6_COURSE_PER_USER:-0}"
    -e "LESSON_COUNT=${K6_LESSON_COUNT:-10}"
    -e "SECTION_INDEX=${K6_SECTION_INDEX:-1}"
    -e "QUIZ_INDEX=${K6_QUIZ_INDEX:-1}"
    -e "CF_BYPASS=${K6_CF_BYPASS:-1}"
    -e "CF_USER_AGENT=${K6_CF_USER_AGENT:-bench2.com PopulaterK6/1.0}"
    -e "CF_BYPASS_HEADER=${K6_CF_BYPASS_HEADER:-x-reviewsignal}"
    -e "CF_BYPASS_VALUE=${K6_CF_BYPASS_VALUE:-1}"
  )

  export K6_ENV_ARGS
}
