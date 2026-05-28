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

rm -rf "${ROOT}/build"

echo "Created ${ZIP_FILE}"
