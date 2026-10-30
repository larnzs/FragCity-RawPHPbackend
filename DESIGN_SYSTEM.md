# FragCity Design System

The shared design system is a site-wide foundation for new and existing pages. Its motion controller automatically enhances existing page components without requiring their markup to be rewritten. It does not merge page CSS/JS into one bundle and does not overwrite existing page-specific styles.

## Global asset order

Loaded by `index.php` on every route:

1. `css/design-tokens.css` — brand, color, typography, spacing, radius, elevation, motion and z-index tokens.
2. Existing global styles — `style.css`, `header.css`, and `footer.css`.
3. `css/components.css` — reusable surfaces, cards, buttons, fields, badges and layout helpers.
4. `css/motion.css` — reusable animation classes and reveal transitions.
5. Route-specific CSS — loaded after the shared foundation and kept in each page's own file.

The global JavaScript controller is `js/motion.js`. It applies page entrance animation, viewport reveals to known page cards and sections, shared hover motion to interactive cards, and watches AJAX-inserted content. Standard links and form controls receive a consistent transition contract. Page-specific JavaScript continues to load through the existing `$pageAssets` router.

## Design tokens

Use the `--fc-*` namespace so the system does not collide with legacy variables:

- Brand: `--fc-color-accent`, `--fc-color-accent-hover`, `--fc-color-accent-warm`.
- Surfaces: `--fc-color-canvas`, `--fc-color-surface-1` through `--fc-color-surface-4`.
- Content: `--fc-color-text`, `--fc-color-text-secondary`, `--fc-color-text-muted`.
- States: `--fc-color-success`, `--fc-color-warning`, `--fc-color-danger`, `--fc-color-info`.
- Shape and spacing: `--fc-radius-*` and `--fc-space-*`.
- Motion: `--fc-duration-*` and `--fc-ease-*`.

## Reusable classes

```html
<section class="fc-card">
    <span class="fc-badge fc-badge--accent">FragCity</span>
    <h2>Card title</h2>
    <p class="fc-muted">Supporting text</p>
    <button class="fc-button fc-button--primary">Continue</button>
</section>
```

Available primitives:

- Surfaces: `fc-surface`, `fc-card`, `fc-card--compact`, `fc-card--interactive`.
- Buttons: `fc-button--primary`, `fc-button--secondary`, `fc-button--ghost`, `fc-button--danger`, `fc-button--sm`, `fc-button--lg`, `fc-icon-button`.
- Fields: `fc-field`, `fc-label`, `fc-input`, `fc-select`, `fc-textarea`.
- Status: `fc-badge`, `fc-badge--accent`, `fc-badge--success`, `fc-badge--warning`, `fc-badge--danger`.
- Layout: `fc-stack`, `fc-cluster`, `fc-divider`, `fc-muted`, `fc-visually-hidden`.

## Motion

Simple entrance animations:

- `fc-motion-fade-in`
- `fc-motion-rise-in`
- `fc-motion-scale-in`
- `fc-motion-hover-lift`

For scroll-triggered reveals, add a direction to the element:

```html
<div data-fc-reveal="up" data-fc-reveal-delay="100">
    Content appears when it enters the viewport.
</div>
```

Supported directions are `up`, `down`, `left`, `right`, and `fade`. Delay is optional and capped at 1200ms.

The controller automatically initializes every route, listens for the existing `content-loaded` event after AJAX navigation, and observes content inserted into `#contentFader`. For content inserted elsewhere, call `FragCityMotion.refresh(container)`. Existing components are detected by their page classes; no class-by-class migration is required.

All reveal and hover effects respect `prefers-reduced-motion`; without IntersectionObserver, content is shown immediately rather than hidden. Reveal delays are capped and staggered in small groups to avoid slowing long grids.

## Adoption rule

New or refactored components should use these tokens and primitives. Existing page styles should only be migrated deliberately, one page at a time, after visual review. Do not import heavy page-specific CSS or JavaScript globally.
