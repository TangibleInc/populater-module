#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=scripts/k6-env.sh
source "${ROOT}/scripts/k6-env.sh"

k6_load_dotenv "$ROOT"
k6_resolve_env
k6_export_env_args

cd "$ROOT"
exec docker compose --profile k6 run --rm \
  "${K6_ENV_ARGS[@]}" \
  k6 "$@"
