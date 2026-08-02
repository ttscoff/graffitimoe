## Ruby, Python, or Node

When executing code in Terminal or the Sandbox, use mise for any command that requires Ruby, Python, or Node.

e.g.

```
mise x ruby@3.4.4 -- [COMMAND]
mise x node@23.5.0 -- [COMMAND]
mise x python@3.13.1 -- [COMMAND]
```

Detect the target version by checking the mise.toml file in the current project or ~/.config/mise/mise.toml.

This will allow access to my environment and any gems/libraries needed.

## Unix commands

Any time you run a unix command like `ls` or `cat` in the terminal, prefix the command with `command`, e.g. `command cat`. This will avoid running my own aliases and give you more predictable results.
