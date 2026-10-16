# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0-beta.1] - 2026-10-16

### Added

- `<:Component>` tags in `.ss` templates: self-closing or with content, dotted names for subfolders (`<:Form.Input>`).
- Props from attributes with `<% include %>` semantics: quoted strings (with `$Var` interpolation, escaped once), unquoted `$Var` objects, numbers, booleans and bare boolean attributes.
- Default slot (`$Children`) and named slots (`<:slot name="…">`, `$Slot()`, `$HasSlot()`), rendered in the caller's scope; empty slots count as absent.
- Optional component classes with `$props` (defaults, `required`, `options`), `$base`, `$variants`, computed props via getters and a `resolveProps()` hook. Template-only components work without a class.
- `$Classes` with swappable class merging: `TailwindClassMerger` (default, resolves Tailwind conflicts) or `SimpleClassMerger`; `$Attributes` passthrough.
- Parse errors with template name and absolute line numbers; validation errors in dev, logged fallback in live.
- Works inside `<% loop %>`, `<% with %>`, `<% cached %>`; existing templates compile unchanged.
- Copyable example components (`examples/`), a Claude Code skill (`skills/`) and a VS Code syntax extension (`editor/vscode/`).
