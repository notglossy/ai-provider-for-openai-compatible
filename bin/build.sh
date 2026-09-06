#!/usr/bin/env bash
#
# Build the WordPress plugin zip for distribution.
#
# Produces build/ai-provider-for-openai-compatible.zip with vendor/ excluded
# (WordPress 7.0 ships the PHP AI Client SDK in core, so the plugin doesn't
# need to bundle it). Repo metadata and dev tooling are stripped.
#
# Usage:
#   bin/build.sh
#   composer build

set -euo pipefail

SLUG="ai-provider-for-openai-compatible"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
BUILD_DIR="$ROOT/build"
STAGE="$BUILD_DIR/$SLUG"
ZIP="$BUILD_DIR/$SLUG.zip"

cd "$ROOT"

echo "Building $SLUG..."

# Stage a clean copy with dist exclusions.
echo "→ Staging files"
rm -rf "$BUILD_DIR"
mkdir -p "$STAGE"
# .distignore is the single source of truth for what ships (CI verifies it);
# the extra excludes below are local-only files that never belong in a zip.
rsync -a \
  --exclude-from="$ROOT/.distignore" \
  --exclude='/.claude' \
  --exclude='/.phpactor.json' \
  --exclude='/bin' \
  --exclude='/build' \
  --exclude='.DS_Store' \
  "$ROOT/" "$STAGE/"

# Zip from inside build/ so the archive's top-level folder is the plugin slug.
echo "→ Creating zip"
cd "$BUILD_DIR"
zip -r -q "$SLUG.zip" "$SLUG"
cd "$ROOT"

SIZE=$(du -sh "$ZIP" | cut -f1)
FILES=$(unzip -l "$ZIP" | tail -1 | awk '{print $2}')
echo
echo "Built: $ZIP"
echo "Size:  $SIZE"
echo "Files: $FILES"
