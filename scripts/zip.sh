#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="tangible-populater"
ZIP_FILE="${ROOT}/${SLUG}.zip"
BUILD_DIR="${ROOT}/build/${SLUG}"

rm -rf "${ROOT}/build" "${ZIP_FILE}"
mkdir -p "${BUILD_DIR}"

cp "${ROOT}/tangible-populater.php" "${BUILD_DIR}/"
cp -r "${ROOT}/src" "${ROOT}/assets" "${BUILD_DIR}/"
cp "${ROOT}/composer.json" "${ROOT}/composer.lock" "${BUILD_DIR}/"

(
  cd "${BUILD_DIR}"
  composer install --no-dev --optimize-autoloader --no-interaction --quiet
  rm -f composer.json composer.lock
)

(
  cd "${ROOT}/build"
  zip -rq "${ZIP_FILE}" "${SLUG}"
)

# Guard against accidentally shipping dev/repo files in the release artifact.
FORBIDDEN_PATHS=(
  ".git/"
  ".gitignore"
  "node_modules/"
  "tests/"
  "docker-compose.yml"
  ".playwright-mcp/"
  "composer.json"
  "composer.lock"
)
for path in "${FORBIDDEN_PATHS[@]}"; do
  if unzip -l "${ZIP_FILE}" | grep -q "${SLUG}/${path}"; then
    echo "Release zip validation failed: forbidden path ${path}" >&2
    rm -f "${ZIP_FILE}"
    rm -rf "${ROOT}/build"
    exit 1
  fi
done

rm -rf "${ROOT}/build"

echo "Created ${ZIP_FILE}"
