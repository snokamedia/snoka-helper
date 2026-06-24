# SVG Icons in WordPress Admin

WordPress admin CSS includes rules like `.components-button svg { fill: currentColor; }` that force `currentColor` onto **all** descendant SVG elements. This breaks Lucide icons and any SVG that relies on explicit fills, strokes, or multi-color paths.

## Core rule

**Two categories, never mix them:**

| Category | Where used | Strategy |
|---|---|---|
| Monochrome UI icons | Buttons, toolbars, menu items, compact controls | `fill="none"` + `stroke="currentColor"` (stroke icons) or `fill="currentColor"` (filled icons) |
| Multi-color / illustrative SVGs | Logos, empty states, hero art, diagrams | Preserve colors — never place inside `components-button` |

## Icon patterns

### Lucide-style stroke icon (safest for buttons)

```tsx
export function MyIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      {...props}
    >
      <path d="..." />
    </svg>
  );
}
```

### Monochrome filled icon

```tsx
export function MyFilledIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="currentColor"
      aria-hidden="true"
      focusable="false"
      {...props}
    >
      <path d="..." />
    </svg>
  );
}
```

### Multi-color / preserve-colors icon

```tsx
export function MyLogo(props: SVGProps<SVGSVGElement>) {
  return (
    <svg
      viewBox="0 0 24 24"
      className="wp-agent-icon--preserve"
      aria-hidden="true"
      focusable="false"
      {...props}
    >
      <path fill="#0ea5e9" d="..." />
      <path fill="#22c55e" d="..." />
    </svg>
  );
}
```

Protect it with scoped CSS:

```css
.wp-agent-icon--preserve {
  fill: initial;
}

/* Override WordPress button fill cascade */
.components-button .wp-agent-icon--preserve,
.components-button .wp-agent-icon--preserve * {
  fill: revert;
  stroke: revert;
}
```

## How `fill: currentColor` breaks SVGs

| SVG type | Effect of `.components-button svg { fill: currentColor; }` |
|---|---|
| Stroke-only (Lucide, Heroicons outline) | **Safe** — `fill="none"` prevents color bleed; visible geometry comes from `stroke="currentColor"` |
| Filled monochrome | **Safe** — intended to inherit color anyway |
| Multi-color with explicit fills | **Broken** — all child paths get forced to `currentColor`, losing palette |
| Transparent/empty regions | **Broken** — unfilled shapes fill with button color |
| Mixed fill+stroke | **Broken** — stroke may be overridden unexpectedly |

## Accessibility

| Scenario | Attributes |
|---|---|
| Decorative icon inside a labeled button | `aria-hidden="true"` `focusable="false"` — label lives on the `<Button>` |
| Icon-only button | `aria-hidden="true"` `focusable="false"` — `<Button aria-label="...">` provides the name |
| Standalone informative icon | `role="img"` `aria-label="..."` `focusable="false"` |

**Never** put `role="img"` on decorative icons inside controls — it creates redundant announcements.

## CSS defense strategies

Apply in component-level CSS (not global `svg` resets):

```css
/* Default for all UI icons in this plugin */
.wp-agent-icon {
  fill: none;
  stroke: currentColor;
  flex: none;
}

/* Filled variant */
.wp-agent-icon--filled {
  fill: currentColor;
  stroke: none;
}

/* Multi-color — undo WordPress cascade */
.components-button .wp-agent-icon--preserve,
.components-button .wp-agent-icon--preserve * {
  fill: revert;
  stroke: revert;
}
```

## Summary checklist

Before adding an SVG to a WordPress admin component:

1. Is it a UI icon in a button/toolbar? → Use `fill="none"` + `stroke="currentColor"` (Lucide-style)
2. Is it a filled monochrome icon? → Use `fill="currentColor"`
3. Is it multi-color or a logo? → Never place inside `components-button`; if unavoidable, add `.wp-agent-icon--preserve` class + CSS override
4. Does the SVG have `aria-hidden="true"` and `focusable="false"`? → Yes, unless it's a standalone informative graphic
5. Is the accessible name on the **control**, not the SVG? → Yes
