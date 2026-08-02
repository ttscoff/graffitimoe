document the project structure and commands for building and running in a file called buildnotes.md. Use Markdown formatting with level 2 headers for each topic. Only use 1 level 1 title, and never go deeper than level 2 in the hierarchy (no h3s, just use bold text and lists for subtopics).

## Runnable topics

If a command can be run from the command line, surround it with @run(COMMAND) on one line:

```
@run(rake install)
```

If a command requires multiple lines, surround with backticks with a language specifier of `run`:

```run
#!/bin/bash

COMMAND 1
COMMAND 2
```

Use `run` as the code block language. All multi-line fenced `run` blocks should include a hashbang, even if it's just a bash script.

Headline based sections are used as "runnable Topics" by Howzit, so every command should be part of its own topic. Don't create multiple options for a command within a headline if they should be distinct topics, create new headlines for each logical runnable topic.

Give headline as unique a name as possible, but if topics are subtopics of the same type, e.g. `build all` or `build cli`, then the headlines should have a command starting word (e.g. "Build") so that Howzit can offer a menu --- e.g. when I run `howzit -r build` it should list all available build topics (starting with "build").

Don't include @run directives or `run` blocks for commands that wouldn't be commonly run by the user, e.g they're just examples. Just make those code spans and blocks with an appropriate language specifier (not `run`). Only create run blocks for topics like `Build` or `Deploy` or `Test`, etc.

## Includes

Howzit has an @include directive that can include steps from other topics. So if you have a basic topic like

```markdown
## Git commit

@run(changelog | git commit -a -F -)
```

Then, if you wanted to run that at the beginning of other topics, you could @include it

```markdown
## Deploy

@include(Git commit)
@run(other command)
```

You don't have to use this @include pattern, but if it makes sense when repeating steps in topics, please do. @include directives can not be inside of fenced code blocks, they must be run before or after.

## User confirmation

You can make blocks optional with ?, so @run?(command) lets the user decide if a command should be run. The default response is yes, so hitting return runs the command. If the default should be no, use ?!. This can apply to both @run and @include directives. If it makes sense that a command would require approval, use ? or ?!.

## Variables

A build file can have MultiMarkdown metadata defined at the top of it, with "key: value" pairs. The keys from these can be used as `[%key]`. Feel free to add reusable values in scripts to the header of the build notes, and reference them in scripts and topics.

## Arguments

Topics can also take arguments. If the topic has a parenthetical after it, the variables named in the parenthetical can be used in the run commands with `${varname}`, and use `${varname:default value}` to include a default:

```markdown
## Bump version (increment)

@run(rake bump[${increment:patch}])
```

Use this pattern any time a command can take options. You can use it in bash commands like `if -n "${increment}"` to fork on the argument.


