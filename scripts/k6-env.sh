#!/usr/bin/env bash
# Shared k6 environment resolution. Source from k6-run.sh.

k6_load_dotenv() {
  local root="${1:-.}"
  local env_file="${root}/.env"

  if [[ -f "$env_file" ]]; then
    local key value
    while IFS='=' read -r key value; do
      [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
      if [[ -z "${!key+x}" ]]; then
        export "$key=$value"
      fi
    done < <(env -i bash -c 'set -a; source "$1"; env' _ "$env_file")
  fi
}

k6_resolve_env() {
  K6_RESOLVED_BASE_URL="${K6_BASE_URL:-http://localhost:${PORT:-8888}}"
  K6_RESOLVED_USER_PASSWORD="${K6_USER_PASSWORD:-StressTest#2026}"
  K6_EXECUTION="${K6_EXECUTION:-local}"

  if [[ "$K6_EXECUTION" != "local" && "$K6_EXECUTION" != "cloud" ]]; then
    echo "K6_EXECUTION must be 'local' or 'cloud' (got '${K6_EXECUTION}')." >&2
    exit 1
  fi

  K6_PROFILE="${K6_PROFILE:-default}"
  K6_PROFILE_FILE="${K6_PROFILE_FILE:-}"
  K6_HOST_CONFIG_DIR="${K6_HOST_CONFIG_DIR:-${HOME:-}/.config/k6}"

  export K6_RESOLVED_BASE_URL K6_RESOLVED_USER_PASSWORD K6_EXECUTION K6_PROFILE K6_PROFILE_FILE K6_HOST_CONFIG_DIR
}

k6_resolve_profile() {
  local root="${1:-.}"
  local profile_file="$K6_PROFILE_FILE"

  if [[ -z "$profile_file" ]]; then
    profile_file="${root}/k6/profiles/${K6_PROFILE}.json"
  elif [[ "$profile_file" != /* ]]; then
    profile_file="${root}/${profile_file}"
  fi

  if [[ ! -f "$profile_file" ]]; then
    echo "k6 profile not found: ${profile_file}" >&2
    echo "Set K6_PROFILE to a file under k6/profiles without the .json extension, or set K6_PROFILE_FILE." >&2
    exit 1
  fi

  case "$profile_file" in
    "${root}/k6/"*)
      K6_RESOLVED_PROFILE_FILE="$profile_file"
      K6_CONTAINER_PROFILE_FILE="/scripts/${profile_file#"${root}/k6/"}"
      ;;
    *)
      echo "K6_PROFILE_FILE must point inside ${root}/k6 so it is visible in the Docker k6 container." >&2
      exit 1
      ;;
  esac

  export K6_RESOLVED_PROFILE_FILE K6_CONTAINER_PROFILE_FILE
}

k6_generate_effective_profile() {
  local root="${1:-.}"
  local runtime_dir="${root}/k6/.runtime"
  local profile_slug="${K6_PROFILE//[^A-Za-z0-9_.-]/_}"
  local output_file="${runtime_dir}/${profile_slug}-effective.json"

  mkdir -p "$runtime_dir"
  php "${root}/scripts/k6-profile.php" "$K6_RESOLVED_PROFILE_FILE" "$output_file"

  K6_EFFECTIVE_PROFILE_FILE="$output_file"
  K6_EFFECTIVE_PROFILE_ENV_FILE="${output_file}.env"
  K6_CONTAINER_PROFILE_FILE="/scripts/.runtime/${profile_slug}-effective.json"
  K6_EFFECTIVE_VUS="$(php -r '
    $profile = json_decode(file_get_contents($argv[1]), true);
    $scenario = reset($profile["scenarios"]);
    if (isset($scenario["vus"])) {
      echo $scenario["vus"];
      exit;
    }
    if (isset($scenario["maxVUs"])) {
      echo $scenario["maxVUs"];
      exit;
    }
    if (isset($scenario["stages"])) {
      echo max(array_map(static fn($stage) => $stage["target"] ?? 0, $scenario["stages"]));
    }
  ' "$output_file")"

  export K6_EFFECTIVE_PROFILE_FILE K6_EFFECTIVE_PROFILE_ENV_FILE K6_CONTAINER_PROFILE_FILE K6_EFFECTIVE_VUS
}

k6_preflight_cloud() {
  if [[ "$K6_EXECUTION" != "cloud" ]]; then
    return
  fi

  if [[ "$K6_RESOLVED_BASE_URL" =~ ^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0)(:|/|$) ]]; then
    echo "K6_EXECUTION=cloud cannot target localhost (${K6_RESOLVED_BASE_URL}); use a publicly reachable bench URL." >&2
    exit 1
  fi

  if [[ -n "${K6_CLOUD_TOKEN:-}" ]]; then
    return
  fi

  if [[ -r "${K6_HOST_CONFIG_DIR}/config.json" ]]; then
    return
  fi

  echo "Grafana Cloud auth not found for Docker k6." >&2
  echo "Set K6_CLOUD_TOKEN, or run 'k6 cloud login' on the host so ${K6_HOST_CONFIG_DIR}/config.json exists." >&2
  exit 1
}

k6_export_env_args() {
  K6_PROFILE_ENV_ARGS=()
  if [[ -f "${K6_EFFECTIVE_PROFILE_ENV_FILE:-}" ]]; then
    local key value
    while IFS='=' read -r key value; do
      [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
      if [[ -n "${!key+x}" ]]; then
        K6_PROFILE_ENV_ARGS+=(-e "${key}=${!key}")
      else
        K6_PROFILE_ENV_ARGS+=(-e "${key}=${value}")
      fi
    done < "$K6_EFFECTIVE_PROFILE_ENV_FILE"
  fi

  K6_SCRIPT_ENV_ARGS=(
    -e "K6_PROFILE=${K6_PROFILE}"
    -e "BASE_URL=${K6_RESOLVED_BASE_URL}"
    -e "USER_PASSWORD=${K6_RESOLVED_USER_PASSWORD}"
    -e "MAX_USERS=${K6_MAX_USERS:-20}"
    -e "PROFILE_VUS=${K6_EFFECTIVE_VUS:-}"
    -e "THINK_TIME=${K6_THINK_TIME:-1}"
    -e "STEP_THINK_TIME=${K6_STEP_THINK_TIME:-${K6_ACTION_DELAY:-1}}"
    -e "ACTION_DELAY=${K6_ACTION_DELAY:-${K6_STEP_THINK_TIME:-1}}"
    -e "SECTIONS_PER_COURSE=${K6_SECTIONS_PER_COURSE:-5}"
    -e "QUIZZES_PER_SECTION=${K6_QUIZZES_PER_SECTION:-1}"
    -e "COURSE_INDEX=${K6_COURSE_INDEX:-1}"
    -e "COURSE_COUNT=${K6_COURSE_COUNT:-5}"
    -e "COURSE_PER_USER=${K6_COURSE_PER_USER:-0}"
    -e "LESSON_COUNT=${K6_LESSON_COUNT:-10}"
    -e "TOPIC_COUNT=${K6_TOPIC_COUNT:-10}"
    -e "QUESTIONS_PER_QUIZ=${K6_QUESTIONS_PER_QUIZ:-10}"
    -e "QUIZ_TOPIC_INDEX=${K6_QUIZ_TOPIC_INDEX:-${K6_TOPIC_COUNT:-10}}"
    -e "SECTION_INDEX=${K6_SECTION_INDEX:-1}"
    -e "QUIZ_INDEX=${K6_QUIZ_INDEX:-1}"
    -e "LIFTER_USERNAME=${K6_LIFTER_USERNAME:-}"
    -e "LIFTER_USER_PASSWORD=${K6_LIFTER_USER_PASSWORD:-}"
    -e "CF_BYPASS=${K6_CF_BYPASS:-1}"
    -e "CF_USER_AGENT=${K6_CF_USER_AGENT:-bench2.com PopulaterK6/1.0}"
    -e "CF_BYPASS_HEADER=${K6_CF_BYPASS_HEADER:-x-reviewsignal}"
    -e "CF_BYPASS_VALUE=${K6_CF_BYPASS_VALUE:-1}"
  )
  K6_SCRIPT_ENV_ARGS+=("${K6_PROFILE_ENV_ARGS[@]}")

  K6_ENV_ARGS=("${K6_SCRIPT_ENV_ARGS[@]}")

  if [[ -n "${K6_REPORT_STAMP:-}" ]]; then
    K6_ENV_ARGS+=(-e "K6_REPORT_STAMP=${K6_REPORT_STAMP}")
  fi

  export K6_PROFILE_ENV_ARGS K6_SCRIPT_ENV_ARGS K6_ENV_ARGS
}

k6_export_cloud_docker_args() {
  K6_CLOUD_DOCKER_ARGS=()

  local name
  while IFS= read -r name; do
    if [[ -n "${!name:-}" ]]; then
      K6_CLOUD_DOCKER_ARGS+=(-e "${name}=${!name}")
    fi
  done < <(compgen -A variable K6_CLOUD_)

  export K6_CLOUD_DOCKER_ARGS
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
    K6_REPORT_JSON_PATH="/scripts/reports/k6-summary-${K6_REPORT_STAMP}.json"
  fi

  export K6_REPORT_HTML_PATH K6_REPORT_JSON_PATH K6_REPORT_STAMP
}

k6_json_stream_enabled() {
  [[ "${K6_JSON_STREAM:-0}" == "1" ]]
}

k6_export_dashboard_docker_args() {
  K6_DASHBOARD_DOCKER_ARGS=()

  if ! k6_dashboard_enabled; then
    export K6_DASHBOARD_DOCKER_ARGS
    return
  fi

  local port="${K6_WEB_DASHBOARD_PORT:-5665}"

  K6_DASHBOARD_DOCKER_ARGS=(
    -e K6_WEB_DASHBOARD=true
    -e K6_WEB_DASHBOARD_HOST=0.0.0.0
    -e "K6_WEB_DASHBOARD_PORT=${port}"
  )

  if k6_report_autosave_enabled && [[ -n "${K6_REPORT_HTML_PATH:-}" ]]; then
    K6_DASHBOARD_DOCKER_ARGS+=(-e "K6_WEB_DASHBOARD_EXPORT=${K6_REPORT_HTML_PATH}")
  elif [[ -n "${K6_WEB_DASHBOARD_EXPORT:-}" ]]; then
    K6_DASHBOARD_DOCKER_ARGS+=(-e "K6_WEB_DASHBOARD_EXPORT=${K6_WEB_DASHBOARD_EXPORT}")
  fi

  export K6_DASHBOARD_DOCKER_ARGS K6_WEB_DASHBOARD_URL="http://127.0.0.1:${port}"
}

k6_prepare_k6_args() {
  local subcommand="${1:-run}"
  shift || true

  if [[ "$subcommand" == "cloud" && "${1:-}" == "run" ]]; then
    K6_EXECUTION="cloud"
    shift
  elif [[ "$subcommand" != "run" ]]; then
    K6_FINAL_ARGS=("$subcommand" "$@")
    export K6_FINAL_ARGS
    return
  fi

  local profile_args=(--config "$K6_CONTAINER_PROFILE_FILE")

  if [[ "$K6_EXECUTION" == "cloud" ]]; then
    K6_FINAL_ARGS=(cloud run "${profile_args[@]}" "${K6_SCRIPT_ENV_ARGS[@]}" "$@")
    export K6_FINAL_ARGS
    return
  fi

  local run_args=(run "${profile_args[@]}" --address "${K6_API_ADDRESS:-127.0.0.1:6566}")

  if k6_report_autosave_enabled && [[ -n "${K6_REPORT_JSON_PATH:-}" ]]; then
    if k6_json_stream_enabled; then
      # Full NDJSON sample stream — one line per metric point (multi-GB on long runs).
      run_args+=(--out "json=${K6_REPORT_JSON_PATH}")
    else
      # End-of-test aggregates only (typically tens of KB).
      run_args+=(--summary-export="${K6_REPORT_JSON_PATH}")
    fi
  fi

  K6_FINAL_ARGS=("${run_args[@]}" "$@")
  export K6_FINAL_ARGS
}
