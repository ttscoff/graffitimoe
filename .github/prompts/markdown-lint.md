Parse all Markdown files in the project and ensure:

1. Wrap text at 60 characters, but don't break links or code spans. Don't wrap inside of fenced code blocks. If you wrap a list item, make sure it's indented properly. If you wrap a block quote line, ensure that the new line starts with >.
2. Ensure a blank newline before and after code blocks
3. Ensure a blank line before and after lists, except in the case of nested lists. If a list item contains a paragraph, make sure that wrapped lines include proper indentation.

Report all changes made.