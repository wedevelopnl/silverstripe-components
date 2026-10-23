# wedevelopnl/silverstripe-components

Reusable components for Silverstripe 6 `.ss` templates: props, default and named slots,
optional component classes, variants and class merging (Tailwind-aware by default, but
usable with any CSS framework or none at all).

> **Beta.** The API may still change before 1.0.0. The package is not on Packagist yet, so
> add the repository first:
>
> ```bash
> composer config repositories.silverstripe-components vcs https://github.com/wedevelopnl/silverstripe-components
> ```

## Installation

```bash
composer require wedevelopnl/silverstripe-components:^1.0@beta gehrisandro/tailwind-merge-php
vendor/bin/sake flush
```

`gehrisandro/tailwind-merge-php` is optional: it is only needed for the default Tailwind
merger. Not using Tailwind? Leave it out and see [Without Tailwind](#without-tailwind).

The module replaces `SSTemplateParser` with `ComponentParser` via the Injector. Templates
without `<:` are handled by the standard parser, unchanged.

## Using a component

```html
<:Button label="Save" variant="primary" class="px-8" />

<:Card title="$Title">
    $Summary
    <:slot name="footer"><a href="$Link">Read more</a></:slot>
</:Card>
```

- `<:Name … />` or `<:Name …>…</:Name>`; subfolders use a dot: `<:Form.Input>`.
- Content outside `<:slot>` is the default slot (`$Children`).
- Slots render in the caller's scope: `$Link` above comes from the page.
- `<:…>` tags inside `<%-- … --%>` template comments are ignored.
- `<:` directly followed by an uppercase letter is always parsed as a component, also inside
  `<script>` or `<pre>`. In JavaScript strings write e.g. `'<\:X'`, or move the script to
  its own file.
- A `<:slot>` cannot sit inside `<% if %>`, `<% loop %>` etc. in the component body (parse
  error). Put the condition inside the slot instead:
  `<:slot name="footer"><% if $Link %>…<% end_if %></:slot>`; a slot that renders empty
  counts as absent.

### Attributes

| Written as | Value in the component |
|---|---|
| `title="Hello"` | string |
| `title="Hello $Name"` / `"{$Name}"` | string with variables filled in (escaped once on output) |
| `item=$Page` | the object itself (for `$Item.Title`, `<% loop $Item.Children %>`) |
| `data-id=$ID`, `class=$Extra` | in `$Attributes` / `$Classes`: the raw value, escaped once (lists and records are left out) |
| `max=3`, `open=true` | number / boolean |
| `disabled` | `true` |

The parent scope is not passed on: only what you pass as attributes is available.

## Creating a component

Template: `templates/Components/<Name>.ss` in your theme, `app/` or a module. A theme can
override templates from modules.

| In the template | Contents |
|---|---|
| `$Label`, `$Variant` | props (attribute name, capitalised) |
| `$Children` / `$HasChildren` | default slot; `$HasChildren` only if it renders non-empty |
| `$Slot('footer')` / `$HasSlot('footer')` | named slot; `$HasSlot` only if it was passed and renders non-empty |
| `$Classes` / `$Classes('extra classes')` | merged classes; the caller's `class` wins |
| `$Attributes` | remaining attributes (`id`, `aria-*`, `data-*`), starts with a space |

```html
<article class="$Classes('rounded-xl bg-white p-6')"$Attributes>
    <h3>$Title</h3>
    $Children
    <% if $HasSlot('footer') %><footer>$Slot('footer')</footer><% end_if %>
</article>
```

A component's rendered output is trimmed: whitespace at the start and end of the component
template (such as the trailing newline) is removed. Whitespace around the call in the calling
template is kept.

### Without a class

Every attribute is readable as a variable *and* is included in `$Attributes`.

Note: an attribute you use as a variable therefore also ends up in `$Attributes`.
`<:Card title="…">` also renders `title="…"` on the root element (browser tooltip). To avoid
that, declare the attribute as a prop in a component class with `'title' => null` in
`$props`; it is then only a variable.

Note: attribute names equal to a `ModelData` method (such as `count`, `first`, `last` or
`debug`) are shadowed by that method in the template: `$Count` will not return your
attribute. Pick another name or declare it as a prop in a class.

### With a class

A subclass of `WeDevelop\Components\Component` is registered automatically:
`ButtonComponent` → `<:Button>` (or set `private static string $component_name`).

Simplified example (the full version, which also has `type` and `size` and an optional
`label` because children take precedence, is in `examples/src/ButtonComponent.php`):

```php
use WeDevelop\Components\Component;

class ButtonComponent extends Component
{
    private static array $props = [
        'label'   => ['required' => true],
        'href'    => null,
        'variant' => ['default' => 'primary', 'options' => ['primary', 'secondary']],
    ];

    private static string $base = 'inline-flex items-center rounded-lg font-medium';

    private static array $variants = [
        'variant' => [
            'primary'   => 'bg-brand-600 text-white hover:bg-brand-700',
            'secondary' => 'bg-white text-gray-900 ring-1 ring-gray-300',
        ],
    ];

    public function getTag(): string // $Tag in the template
    {
        return $this->prop('href') ? 'a' : 'button';
    }
}
```

- Only declared props are variables; other attributes go to `$Attributes`.
- A missing required prop or a value outside `options`: an exception in dev/test, a warning
  in the log plus the default in live.
- Prop values from unquoted attributes (such as `level=2`) are compared to `options` as
  strings, so `options => ['1','2']` accepts both `level="2"` and `level=2`.
- Override `resolveProps(array $props): array` to derive props (see
  `examples/src/HeadingComponent.php`).
- A subclass with the same component name replaces the older class.
- To change variants without editing the class (for example for a component from a shared
  module), use YAML:

  ```yaml
  App\Components\ButtonComponent:
    variants:
      variant:
        primary: 'bg-emerald-600 text-white hover:bg-emerald-700'
  ```

**Always write classes out in full.** Tailwind cannot find `bg-$Color-500`.

## Tailwind v4

```css
@import "tailwindcss";
@plugin "@tailwindcss/typography";

@source "../templates";  /* your theme */
@source "../../../app";  /* app/src (component classes), app/templates, app/_config (YAML variants) */

@theme {
  --color-brand-600: oklch(0.52 0.17 258);
}
```

The module itself contains no templates or classes, so it does not need to be in `@source`.
If your components come from another module or from `vendor/`, add that path explicitly:
Tailwind v4 skips gitignored paths such as `vendor/` by default.

Design tokens belong in `@theme`. Style WYSIWYG content (`$Content`) with `prose`.

## Without Tailwind

The engine (props, slots, `$base`, `$variants`, `$Classes`, `$Attributes`) works with any CSS
framework or plain CSS. Only the way `$Classes` merges classes is swappable:

| Merger | Behaviour |
|---|---|
| `TailwindClassMerger` (default) | resolves Tailwind conflicts: `px-4` + `px-8` → `px-8`. Requires `gehrisandro/tailwind-merge-php`. |
| `SimpleClassMerger` | joins and de-duplicates, nothing else: `btn btn-primary` + `btn-lg` → `btn btn-primary btn-lg`. |

Choose `SimpleClassMerger` for Bootstrap, Bulma, BEM or plain CSS:

```yaml
# app/_config/components.yml
---
Name: app-components
After: '#wedevelop-components'
---
SilverStripe\Core\Injector\Injector:
  WeDevelop\Components\ClassMerger:
    class: WeDevelop\Components\SimpleClassMerger
```

In those projects, switch appearance through variant props rather than an extra `class`
(which is then only added, not replaced). A Bootstrap button:

```php
class ButtonComponent extends Component
{
    private static array $props = [
        'variant' => ['default' => 'primary', 'options' => ['primary', 'secondary', 'danger']],
        'size' => ['default' => 'md', 'options' => ['sm', 'md', 'lg']],
    ];

    private static string $base = 'btn';

    private static array $variants = [
        'variant' => ['primary' => 'btn-primary', 'secondary' => 'btn-secondary', 'danger' => 'btn-danger'],
        'size' => ['sm' => 'btn-sm', 'md' => '', 'lg' => 'btn-lg'],
    ];
}
```

If the default merger is active but `gehrisandro/tailwind-merge-php` is missing, you get an
error message describing both solutions.

## Editor support (VS Code)

- **Syntax highlighting** for `<:Component>` and `<:slot>` tags: the extension in
  `editor/vscode/` (requires the "SilverStripe" extension, `adrianhumphreys.silverstripe`).
  Build and install:

  ```bash
  cd editor/vscode
  npx @vscode/vsce@3 package --allow-missing-repository --skip-license -o ss-components-syntax.vsix
  code --install-extension ss-components-syntax.vsix
  ```

- **Tailwind IntelliSense** in `.ss` files, `$Classes('…')` and the `$base`/`$variants` of
  component classes, via `.vscode/settings.json` in your project:

  ```json
  {
      "tailwindCSS.experimental.configFile": "themes/app/css/app.css",
      "tailwindCSS.includeLanguages": { "silverstripe": "html" },
      "tailwindCSS.experimental.classRegex": [
          "\\$Classes\\(\\s*'([^']*)'",
          "\\$Classes\\(\\s*\"([^\"]*)\"",
          ["\\$(?:base|variants)\\s*=\\s*([\\s\\S]*?);", "'([^']*)'"]
      ]
  }
  ```

## AI agent skill (Claude Code)

`skills/using-silverstripe-components/` is a Claude Code skill that teaches coding agents to
inventory and reuse your components, add variants instead of one-off styling, avoid the
pitfalls above and verify their changes. Make it available in a project by linking or copying
it into `.claude/skills/`:

```bash
mkdir -p .claude/skills
ln -s ../../vendor/wedevelopnl/silverstripe-components/skills/using-silverstripe-components .claude/skills/
```

## Example components

The module only provides the engine and registers no components itself, so every project
starts clean and puts its own design in its components. `examples/` contains copyable
starting points (not loaded automatically): `<:Button>` (label or children, variant, size,
href, type), `<:Heading>` (text or children, level 1–6, size) and `<:Card>` (title, named
slot `footer`; class declares `title` as a prop). See `examples/README.md`.

## Known limitations

- `<% component %>` is reserved.

## Extending

- Extension hook `updateRenderedHTML(string &$html)` on `Component`, for example to add
  islands later (`data-component`, `getClientProps()`).

## Development

PHP runs in Docker; [Task](https://taskfile.dev) wraps the commands:

```bash
task install   # composer install in the container
task test      # PHPUnit on SQLite in-memory, no database server needed
task test -- --filter TokenizerTest
task test-flush   # after adding test classes (fresh test manifest)
task sh        # shell in the container
```

See `AGENTS.md` for the architecture and rules, and `CHANGELOG.md` for releases.
