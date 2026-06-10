#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=scripts/k6-env.sh
source "${ROOT}/scripts/k6-env.sh"

k6_load_dotenv "$ROOT"
k6_resolve_env
k6_resolve_profile "$ROOT"
k6_generate_effective_profile "$ROOT"

if [[ "${1:-}" == "cloud" && "${2:-}" == "run" ]]; then
  K6_EXECUTION="cloud"
fi

if [[ -n "${K6_EFFECTIVE_VUS:-}" && "${K6_MAX_USERS:-20}" -lt "$K6_EFFECTIVE_VUS" ]]; then
  echo "Warning: K6_MAX_USERS (${K6_MAX_USERS:-20}) < profile VUs (${K6_EFFECTIVE_VUS}) — some VUs will idle with no journey." >&2
fi

k6_preflight_cloud

if [[ "$K6_EXECUTION" == "cloud" ]]; then
  K6_REPORT_HTML_PATH=""
  K6_REPORT_JSON_PATH=""
  K6_REPORT_STAMP=""
else
  k6_resolve_report_paths
  mkdir -p "${ROOT}/k6/reports"
  chmod a+rwx "${ROOT}/k6/reports"
fi

k6_export_env_args
k6_export_cloud_docker_args

if [[ "$K6_EXECUTION" == "cloud" ]]; then
  K6_DASHBOARD_DOCKER_ARGS=()
  K6_DOCKER_SCRIPT_ENV_ARGS=()
else
  k6_export_dashboard_docker_args
  K6_DOCKER_SCRIPT_ENV_ARGS=("${K6_ENV_ARGS[@]}")
fi

k6_prepare_k6_args "$@"

cd "$ROOT"

echo "k6 execution: ${K6_EXECUTION}; profile: ${K6_PROFILE} (${K6_CONTAINER_PROFILE_FILE})"

if [[ "$K6_EXECUTION" != "cloud" ]] && k6_report_autosave_enabled; then
  if k6_json_stream_enabled; then
    echo "k6 autosave: ${K6_REPORT_HTML_PATH} (HTML), ${K6_REPORT_JSON_PATH} (full JSON stream — large on long runs)"
  else
    echo "k6 autosave: ${K6_REPORT_HTML_PATH} (HTML), ${K6_REPORT_JSON_PATH} (summary JSON)"
  fi
fi

if [[ "$K6_EXECUTION" != "cloud" ]] && k6_dashboard_enabled; then
  echo "k6 web dashboard: ${K6_WEB_DASHBOARD_URL}"
fi

exec docker compose --profile k6 run --rm \
  "${K6_CLOUD_DOCKER_ARGS[@]}" \
  "${K6_DASHBOARD_DOCKER_ARGS[@]}" \
  "${K6_DOCKER_SCRIPT_ENV_ARGS[@]}" \
  k6 "${K6_FINAL_ARGS[@]}"
