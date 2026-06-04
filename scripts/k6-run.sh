#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=scripts/k6-env.sh
source "${ROOT}/scripts/k6-env.sh"

k6_load_dotenv "$ROOT"
k6_resolve_env
k6_resolve_report_paths
mkdir -p "${ROOT}/k6/reports"
chmod a+rwx "${ROOT}/k6/reports"
k6_export_env_args
k6_export_dashboard_docker_args
k6_prepare_k6_args "$@"

cd "$ROOT"

if k6_report_autosave_enabled; then
  echo "k6 autosave: ${K6_REPORT_HTML_PATH} (HTML), ${K6_REPORT_JSON_PATH} (JSON)"
fi

if k6_dashboard_enabled; then
  echo "k6 web dashboard: ${K6_WEB_DASHBOARD_URL}"
fi

exec docker compose --profile k6 run --rm \
  "${K6_DASHBOARD_DOCKER_ARGS[@]}" \
  "${K6_ENV_ARGS[@]}" \
  k6 "${K6_FINAL_ARGS[@]}"
