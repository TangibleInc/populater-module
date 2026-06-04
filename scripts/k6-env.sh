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
    -e "VUS=${K6_VUS:-20}"
    -e "MAX_USERS=${K6_MAX_USERS:-20}"
    -e "RAMP_UP=${K6_RAMP_UP:-1m}"
    -e "HOLD=${K6_HOLD:-3m30s}"
    -e "RAMP_DOWN=${K6_RAMP_DOWN:-1m}"
    -e "THINK_TIME=${K6_THINK_TIME:-1}"
    -e "ACTION_DELAY=${K6_ACTION_DELAY:-0}"
    -e "COURSE_INDEX=${K6_COURSE_INDEX:-1}"
    -e "COURSE_PER_USER=${K6_COURSE_PER_USER:-0}"
    -e "LESSON_COUNT=${K6_LESSON_COUNT:-10}"
    -e "TOPIC_COUNT=${K6_TOPIC_COUNT:-10}"
    -e "QUIZ_TOPIC_INDEX=${K6_QUIZ_TOPIC_INDEX:-${K6_TOPIC_COUNT:-10}}"
    -e "SECTION_INDEX=${K6_SECTION_INDEX:-1}"
    -e "QUIZ_INDEX=${K6_QUIZ_INDEX:-1}"
    -e "CF_BYPASS=${K6_CF_BYPASS:-1}"
    -e "CF_USER_AGENT=${K6_CF_USER_AGENT:-bench2.com PopulaterK6/1.0}"
    -e "CF_BYPASS_HEADER=${K6_CF_BYPASS_HEADER:-x-reviewsignal}"
    -e "CF_BYPASS_VALUE=${K6_CF_BYPASS_VALUE:-1}"
  )

  export K6_ENV_ARGS
}

# k6 built-in web dashboard (https://grafana.com/docs/k6/latest/results-output/web-dashboard/)
k6_dashboard_enabled() {
  [[ "${K6_WEB_DASHBOARD:-1}" != "0" ]]
}

k6_report_autosave_enabled() {
  [[ "${K6_REPORT_AUTOSAVE:-1}" != "0" ]]
}

k6_resolve_report_paths() {
  K6_REPORT_HTML_PATH=""
  K6_REPORT_JSON_PATH=""
  K6_REPORT_STAMP=""

  if ! k6_report_autosave_enabled; then
    export K6_REPORT_HTML_PATH K6_REPORT_JSON_PATH K6_REPORT_STAMP
    return
  fi

  K6_REPORT_STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

  if [[ -n "${K6_WEB_DASHBOARD_EXPORT:-}" ]]; then
    K6_REPORT_HTML_PATH="${K6_WEB_DASHBOARD_EXPORT}"
  else
    K6_REPORT_HTML_PATH="/scripts/reports/k6-report-${K6_REPORT_STAMP}.html"
  fi

  if [[ -n "${K6_JSON_OUTPUT:-}" ]]; then
    K6_REPORT_JSON_PATH="${K6_JSON_OUTPUT}"
  else
    K6_REPORT_JSON_PATH="/scripts/reports/k6-results-${K6_REPORT_STAMP}.json"
  fi

  export K6_REPORT_HTML_PATH K6_REPORT_JSON_PATH K6_REPORT_STAMP
}

k6_export_dashboard_docker_args() {
  K6_DASHBOARD_DOCKER_ARGS=()

  if ! k6_dashboard_enabled; then
    export K6_DASHBOARD_DOCKER_ARGS
    return
  fi

  local port="${K6_WEB_DASHBOARD_PORT:-5665}"

  K6_DASHBOARD_DOCKER_ARGS=(
    -p "${port}:5665"
    -e K6_WEB_DASHBOARD=true
    -e K6_WEB_DASHBOARD_HOST=0.0.0.0
    -e K6_WEB_DASHBOARD_PORT=5665
  )

  if k6_report_autosave_enabled && [[ -n "${K6_REPORT_HTML_PATH:-}" ]]; then
    K6_DASHBOARD_DOCKER_ARGS+=(-e "K6_WEB_DASHBOARD_EXPORT=${K6_REPORT_HTML_PATH}")
  elif [[ -n "${K6_WEB_DASHBOARD_EXPORT:-}" ]]; then
    K6_DASHBOARD_DOCKER_ARGS+=(-e "K6_WEB_DASHBOARD_EXPORT=${K6_WEB_DASHBOARD_EXPORT}")
  fi

  export K6_DASHBOARD_DOCKER_ARGS K6_WEB_DASHBOARD_URL="http://127.0.0.1:${port}"
}

k6_prepare_k6_args() {
  if [[ "${1:-}" != "run" ]] || ! k6_report_autosave_enabled || [[ -z "${K6_REPORT_JSON_PATH:-}" ]]; then
    K6_FINAL_ARGS=("$@")
    export K6_FINAL_ARGS
    return
  fi

  K6_FINAL_ARGS=(run --out "json=${K6_REPORT_JSON_PATH}" "${@:2}")
  export K6_FINAL_ARGS
}
