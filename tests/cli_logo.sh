#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cli="$root/cli/graffiti"

plain="$("$cli" --color=never --version)"
[[ "$plain" == *$'\n'* ]] || { echo "expected multiline version with logo"; exit 1; }
[[ "$plain" == *"@@@@@@@"* ]] || { echo "missing can body in logo"; exit 1; }
[[ "$plain" == *"graffiti 0.1.2"* ]] || { echo "missing version line"; exit 1; }
[[ "$plain" != *$'\033'* ]] || { echo "plain version should have no ANSI"; exit 1; }

help_plain="$("$cli" --color=never --help)"
[[ "$help_plain" == *"@@@@@@@"* ]] || { echo "missing logo on help"; exit 1; }
[[ "$help_plain" == *"Usage:"* ]] || { echo "missing usage"; exit 1; }

spray_help="$("$cli" spraypaint --help)"
[[ "$spray_help" != *"@@@@@@@"* ]] || { echo "spraypaint help should not show logo"; exit 1; }

colored="$("$cli" --color=always --version)"
[[ "$colored" == *$'\033[31m'* ]] || { echo "expected red ANSI for %"; exit 1; }
[[ "$colored" == *$'\033[36m'* ]] || { echo "expected cyan ANSI for *"; exit 1; }
[[ "$colored" == *$'\033[35m'* ]] || { echo "expected magenta ANSI for #"; exit 1; }
[[ "$colored" == *$'\033[33m'* ]] || { echo "expected yellow ANSI for ="; exit 1; }

echo "CLI logo smoke test passed."
