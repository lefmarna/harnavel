# Creating Toast Notifications

Toast notifications are transient status messages. Unlike menus, they should not close when a user interacts with other parts of the page. The popover="manual" state is ideal because it lacks "light-dismiss" behavior and allows multiple notifications to coexist.

### Implementation Guidelines

* **MANDATORY:** Use one popover="manual" container so the notification stack stays visible until explicitly closed or timed out by a script.
* **DO** stack `.toast` elements in normal flow inside that container.
* **DO** provide an explicit "Close" button that removes its toast.
* **DO** use the same removal behavior for auto-dismissal timers, and hide the container after either path when it becomes empty.
* **DO** utilize transition-behavior: allow-discrete to animate the container's entry and exit from the Top Layer.

### Fallback Strategies

### Fallbacks & browser support for Popover

Baseline status for Popover: Newly available. It's been Baseline since 2025-01-27.
Supported by: Chrome 116 (Aug 2023), Edge 116 (Aug 2023), Firefox 125 (Apr 2024), Safari 17 (Sep 2023), and Safari iOS 18.3 (Jan 2025).

The Popover API is mostly **progressive enhancement**, but its defining behaviors — top-layer promotion, light-dismiss, and `popovertarget` invocation — have no CSS-only equivalent. Older browsers need a polyfill, or a manual fallback if you would rather not ship one.

**Polyfill:** To support the `popover` attribute in older browsers, conditionally load [`@oddbird/popover-polyfill`](https://github.com/oddbird/popover-polyfill). **MANDATORY:** Feature detect by checking for the `popover` property on `HTMLElement.prototype`, and load the polyfill **only** when native support is missing — do NOT load it unconditionally.

With a bundler or import map:

```js
// MANDATORY: Feature detect 'popover' on HTMLElement.prototype.
if (!("popover" in HTMLElement.prototype)) {
  import("@oddbird/popover-polyfill");
}
```

Without a bundler, import from a CDN inside a `<script type="module">`:

```html
<script type="module">
  if (!("popover" in HTMLElement.prototype)) {
    import("https://unpkg.com/@oddbird/popover-polyfill@latest/dist/popover.min.js");
  }
</script>
```

**Styling caveat:** The polyfill cannot define the real `:popover-open` pseudo-class, so it applies a `.\:popover-open` class instead. **MANDATORY:** Combine the two with `:is()` or `:where()`, otherwise browsers that lack `:popover-open` discard the entire rule:

```css
[popover]:is(:popover-open, .\:popover-open) {
  display: block;
}
```

Alternatively, for a legacy fallback without a polyfill, use `position: fixed` and manually calculate coordinates via `getBoundingClientRect()` or rely on default positioning with `inset: auto` if that's acceptable for the use case.

#### sibling-index()

* **Guidance:** If sibling-index() is not supported, space the normal-flow items with `.toast + .toast { margin-block-start: 1rem }`. The selector `popover` would target a nonexistent `<popover>` element rather than an element with the `popover` attribute.

#### anchor-positioning

* **Guidance:** Use the [CSS Anchor Positioning Polyfill](https://github.com/oddbird/css-anchor-positioning). For a non-polyfill fallback, default the tooltip to a fixed position at the bottom of the viewport using `@supports not (anchor-name: --foo)`.

#### transition-behavior

* **Guidance:** If transition-behavior is not supported, use JavaScript classes as the container transitions in and out.
