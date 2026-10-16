# AGENTS.md

## Architecture

| Path | Contents |
|---|---|
| `src/Parser/Tokenizer.php` | Finds top-level `<:Name>` calls (skips `<%-- --%>` comments) → `ComponentNode` (attributes, default body, named slots, offsets) |
| `src/Parser/ComponentParser.php` | `SSTemplateParser` subclass (Injector swap). Fast path for sources without `<:`; replaces calls with `<% component "__cN" %>` open-block placeholders |
| `src/Parser/CallCompiler.php` | Compiles a node to `ComponentRenderer::render(name, props, slots, $scope)`; slots are closures capturing the caller's `$scope` |
| `src/ComponentRenderer.php` | Runtime: resolves class via `ComponentRegistry`, renders `Components/<Name>.ss` with the component as model in a fresh scope, trims output, fires `updateRenderedHTML` |
| `src/Component.php` | Base model: `$props` (defaults/required/options), `$base`, `$variants`, `Classes()`, `Attributes()`, slots, `resolveProps()` hook |
| `src/ComponentRegistry.php` | Name ↔ class (`FooComponent` → `<:Foo>`, `$component_name`); subclass with same name wins |
| `src/ClassMerger.php` | Interface; `TailwindClassMerger` (default, needs tailwind-merge-php) and `SimpleClassMerger` |
| `src/HtmlAttributes.php`, `src/TemplateValue.php` | Attribute rendering; unwrapping template values to raw scalars |
| `examples/` | Copyable Button/Card/Heading (not loaded: `_manifest_exclude`) |
| `skills/using-silverstripe-components/` | Claude Code skill for projects that use the module |
| `editor/vscode/` | VS Code grammar injection for `<:Component>` tags |
| `tests/` | PHPUnit (SapphireTest, SQLite in-memory); fixtures in `tests/Fixtures`, `tests/templates` |

## Rules

- Only public parser API: `compileString`, open-block handlers, `match_NamedArgument`. Never fork the PEG grammar.
- Templates without `<:` must compile byte-identically to the core parser (`ParserRegressionTest`).
- Escaping happens exactly once: props/attributes are raw values, escaped on output; slots are `DBHTMLText`.
- Never build Tailwind classes dynamically; variants are complete strings.
- Code, comments and docs in English. Keep `README.md` and `CHANGELOG.md` (Keep a Changelog, `[Unreleased]`) in sync with behaviour changes.

## Commands

All PHP runs in Docker (`.docker/compose.yml`); use Task:

- `task install` — Composer dependencies
- `task test` — PHPUnit (pass filters after `--`, e.g. `task test -- --filter TokenizerTest`)
- `task test-flush` — same, with a fresh test manifest (needed after adding test classes)
- `task sh` — shell in the container
