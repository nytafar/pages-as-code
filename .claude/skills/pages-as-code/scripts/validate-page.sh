#!/usr/bin/env bash
# validate-page.sh — Basic validation for Pages as Code .html files
# Usage: bash validate-page.sh <path-to-file>
set -euo pipefail

FILE="${1:?Usage: validate-page.sh <file>}"

if [ ! -f "$FILE" ]; then
  echo "ERROR: File not found: $FILE" >&2
  exit 1
fi

ERRORS=0

# Check front matter delimiters
if ! head -1 "$FILE" | grep -q '^---$'; then
  echo "ERROR: File must start with --- (front matter delimiter)" >&2
  ERRORS=$((ERRORS + 1))
fi

# Check closing front matter delimiter
if ! awk 'NR>1 && /^---$/{found=1; exit} END{exit !found}' "$FILE"; then
  echo "ERROR: Missing closing --- front matter delimiter" >&2
  ERRORS=$((ERRORS + 1))
fi

# Check title field
if ! grep -q '^title:' "$FILE"; then
  echo "ERROR: Missing required 'title' field in front matter" >&2
  ERRORS=$((ERRORS + 1))
fi

# Check block comment pairing
OPENS=$(grep -co '<!-- wp:[a-z]' "$FILE" | tail -1 || true)
CLOSES=$(grep -co '<!-- /wp:[a-z]' "$FILE" | tail -1 || true)
SELFCLOSE=$(grep -co ' /-->' "$FILE" | tail -1 || true)
OPENS=${OPENS:-0}; CLOSES=${CLOSES:-0}; SELFCLOSE=${SELFCLOSE:-0}

if [ "$OPENS" -gt 0 ] && [ "$OPENS" -ne "$((CLOSES + SELFCLOSE))" ]; then
  echo "WARNING: Block comment mismatch — $OPENS opening, $CLOSES closing, $SELFCLOSE self-closing" >&2
fi

if [ "$ERRORS" -eq 0 ]; then
  echo "OK: $FILE passes basic validation"
else
  echo "FAILED: $FILE has $ERRORS error(s)" >&2
  exit 1
fi
