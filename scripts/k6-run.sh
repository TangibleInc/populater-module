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

# Derive a short test name from the .js script argument for filenames / report titles.
# lifterlms-stress.js → lifterlms   learndash-stress.js → learndash
K6_TEST_NAME="k6"
for _k6_arg in "$@"; do
  if [[ "$_k6_arg" == *.js ]]; then
    _k6_base="$(basename "$_k6_arg" .js)"
    K6_TEST_NAME="${_k6_base%%-stress}"
    break
  fi
done
export K6_TEST_NAME

if [[ -n "${K6_EFFECTIVE_VUS:-}" && "${K6_MAX_USERS:-20}" -lt "$K6_EFFECTIVE_VUS" ]]; then
  echo "Warning: K6_MAX_USERS (${K6_MAX_USERS:-20}) < profile VUs (${K6_EFFECTIVE_VUS}) — some VUs will idle with no journey." >&2
fi

k6_preflight_cloud

if [[ "$K6_EXECUTION" == "cloud" ]]; then
  K6_REPORT_HTML_PATH=""
  K6_REPORT_JSON_PATH=""
  K6_REPORT_STAMP=""
else
  k6_resolve_report_paths "$ROOT" "$K6_TEST_NAME"
  mkdir -p "${ROOT}/k6/reports"
fi

k6_export_env_args
k6_export_dashboard_env
k6_prepare_k6_args "$@"

echo "k6 execution: ${K6_EXECUTION}; profile: ${K6_PROFILE} (${K6_EFFECTIVE_PROFILE_FILE})"

if [[ "$K6_EXECUTION" != "cloud" ]] && k6_report_autosave_enabled; then
  if k6_json_stream_enabled; then
    echo "k6 autosave: ${K6_REPORT_HTML_PATH} (HTML), ${K6_REPORT_JSON_PATH} (full JSON stream — large on long runs)"
  else
    echo "k6 autosave: ${K6_REPORT_HTML_PATH} (HTML), ${K6_REPORT_JSON_PATH} (summary JSON)"
  fi
fi

if [[ "$K6_EXECUTION" != "cloud" ]] && k6_dashboard_enabled; then
  echo "k6 web dashboard: ${K6_WEB_DASHBOARD_URL}"
  # In WSL2, localhost forwarding can be blocked by Windows Firewall.
  # Print the WSL2 VM IP as a reliable fallback URL.
  if [[ -n "${WSL_DISTRO_NAME:-}" ]]; then
    _wsl_ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    if [[ -n "$_wsl_ip" ]]; then
      echo "k6 web dashboard (WSL2 fallback): http://${_wsl_ip}:${K6_WEB_DASHBOARD_PORT:-5665}"
      echo "  (use this URL if localhost is blocked by Windows Firewall)"
    fi
  fi
fi

cd "${ROOT}/k6"
k6_exit=0
k6 "${K6_FINAL_ARGS[@]}" || k6_exit=$?

# Annotate the HTML report with test metadata (title + banner).
if [[ "$K6_EXECUTION" != "cloud" ]] && [[ -f "${K6_REPORT_HTML_PATH:-}" ]]; then
  k6_annotate_report "${K6_REPORT_HTML_PATH}" "${K6_TEST_NAME}" "${K6_PROFILE}" "${K6_REPORT_STAMP}"
fi

exit "$k6_exit"
