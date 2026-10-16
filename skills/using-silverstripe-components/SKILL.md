---
name: using-silverstripe-components
description: Use when adding or changing markup, layouts or UI elements (buttons, cards, headings, banners, sections, badges) in Silverstripe .ss templates of a project that uses wedevelopnl/silverstripe-components, contains <:Component> tags, or has templates/Components/ folders.
---

# Using Silverstripe components

## Overview

UI in these projects is built from `<:Name>` components. The Tailwind classes that define
the design live **inside** components (templates in `templates/Components/`, variant maps
in `*Component` classes). Page templates compose components and only add layout.

## 1. Inventory before writing markup

```bash
find themes app -path '*templates/Components/*' -name '*.ss'
grep -rl "extends Component" app/src
```

For every component you might use, read its template (which `$Vars`, slots, `$Classes`)
and its class if any (`$props` with `options`, `$variants`, getters). Use only props and
variant values that exist.

## 2. Decide

| Situation | Do |
|---|---|
| A component exists for the element | Use it. A `class` attribute is for layout only (margins, width, grid placement), not to restyle. |
| Need another look of an existing component (e.g. "outline button") | Add a variant: value in `$props[...]['options']` + complete class string in `$variants`. |
| Markup repeats, or will be reused ("later on other pages") | New component in `themes/<theme>/templates/Components/<Name>.ss`. |
| The new component shows an attribute as text (`title`, `text`, `label`, `heading`) | Give it a class that declares those as props (`'title' => null`). See pitfall 1. |
| One-off layout (section wrapper, grid) | Plain HTML + Tailwind in the page template is fine. |

## 3. Syntax

| Write | Effect |
|---|---|
| `<:Button label="Save" />` / `<:Card>…</:Card>` | self-closing / with default slot (`$Children`) |
| `<:slot name="footer">…</:slot>` | named slot (`$Slot('footer')`, `$HasSlot('footer')`) |
| `title="Hi $Name"` | string; variables interpolated, escaped once |
| `items=$Page.Children` | the object/list itself (for loops in the component) |
| `disabled` | `true` |
| `<:Form.Input>` | `Components/Form/Input.ss` |

Slots render in the caller's scope: inside a caller loop, `$Title`/`$Link` in slot content are
the loop item's. The parent scope is NOT passed into the component itself; pass what it needs.

Component template: `class="$Classes('internal classes')"$Attributes` on the root element
(`$Attributes` starts with its own space).

## 4. Pitfalls

1. **Class-less components put every attribute in `$Attributes`.** `<:Card title="News">`
   on a template-only Card also renders `title="News"` on the root element (tooltip). Fix:
   class with `private static array $props = ['title' => null];`.
2. **`<:slot>` inside `<% if %>`/`<% loop %>` in a component body is a parse error.** Put the
   condition inside the slot; a slot that renders empty counts as absent.
3. **Never compose classes dynamically** (`bg-$Color-500`); Tailwind won't generate them.
   Map values to full strings in `$variants`.
4. **Attribute names that are ModelData methods** (`count`, `first`, `last`, `debug`) are
   shadowed on class-less components. Pick another name or declare a prop.
5. **`<:` + uppercase letter is always a component**, even in inline `<script>`.
6. **Invalid prop values throw in dev** (with allowed values) — read the message, don't remove the prop.

## 5. Verify (required before reporting done)

1. Flush (`vendor/bin/sake flush`, via the project's usual way of running PHP) after adding
   a component template or class; rebuild the Tailwind CSS after adding classes.
2. Render the page: `curl -s <url> | grep -c '<:'` must print `0`; check the HTML of what
   you added (classes, attributes, no stray `title=` on wrappers).
3. New component or variant: add a test that renders it, e.g.
   `SSTemplateEngine::create()->renderString('<:Button variant="outline" label="x" />', ViewLayerData::create(ArrayData::create([])), [], false)`
   and assert on the output.

If you could not render or test, say so explicitly instead of assuming it works.
