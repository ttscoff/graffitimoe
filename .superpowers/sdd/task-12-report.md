# Task 12: CLI `graffiti`

Status: complete

Implemented:

- Added executable `cli/graffiti` with random-message retrieval, color modes, help, and `spraypaint`.
- `spraypaint` streams the message with `printf '%s'` or stdin into `curl --data-urlencode "body@-"`, preserving embedded newlines.
- Added a self-contained local-server CLI smoke test and a Homebrew formula example.

Verification:

- `bash -n cli/graffiti tests/cli_smoke.sh`
- `ruby -c brew/graffiti.rb.example`
- `bash tests/cli_smoke.sh` (confirmed a multiline `hi\nart` submission round-trips through the local server)
- `vendor/bin/phpunit` (37 tests, 112 assertions)

Concerns:

- The formula uses the requested placeholder GitHub URL and SHA256; replace both before publishing a tap.
