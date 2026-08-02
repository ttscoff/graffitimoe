#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
port="${PORT:-8080}"
config="$root/config/config.php"
config_backup="$(mktemp)"
db_dir="$(mktemp -d)"
server_log="$(mktemp)"
pid=""

cleanup() {
    if [[ -n "$pid" ]]; then
        kill "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
    fi
    cp "$config_backup" "$config"
    rm -rf "$db_dir" "$config_backup" "$server_log"
}

trap cleanup EXIT

if [[ ! -f "$config" ]]; then
    echo "Missing config/config.php; copy config/config.example.php first." >&2
    exit 1
fi

cp "$config" "$config_backup"
php -r '$config = require $argv[1]; $config["db_path"] = $argv[2]; file_put_contents($argv[1], "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n");' \
    "$config" "$db_dir/graffiti.sqlite"

cd "$root"
php -S "127.0.0.1:$port" -t public public/router.php >"$server_log" 2>&1 &
pid="$!"

for _ in {1..20}; do
    if curl --silent --fail "http://127.0.0.1:$port/random" >/dev/null; then
        break
    fi
    sleep 0.1
done

export GRAFFITI_URL="http://127.0.0.1:$port"

sprayed="$(./cli/graffiti spraypaint --color cyan --bold $'hi\nart')"
[[ "$sprayed" == "Sprayed." ]]

random="$(./cli/graffiti --color=never)"
[[ "$random" == $'hi\nart' ]]

echo "CLI smoke test passed."
