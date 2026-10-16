# Example components

Copyable starting points for `<:Button>`, `<:Heading>` and `<:Card>`. They are **not**
loaded automatically (`_manifest_exclude`): copy what you need into your project and adapt
it to your design.

| File | Copy to |
|---|---|
| `src/ButtonComponent.php`, `src/CardComponent.php`, `src/HeadingComponent.php` | `app/src/Components/` |
| `templates/Components/*.ss` | `themes/<your-theme>/templates/Components/` |

The classes use the `App\Components` namespace and the colours `brand-50`, `brand-600` and
`brand-700`. Define those in your Tailwind `@theme`, or replace them with your own tokens.
`Card` works without its class too (template-only), but then every attribute also lands in
`$Attributes`, so `title` would render as a tooltip on the root element. `CardComponent`
declares `title` as a prop to prevent that.
