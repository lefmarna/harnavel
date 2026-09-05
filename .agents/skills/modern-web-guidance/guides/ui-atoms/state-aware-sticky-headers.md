# State-Aware Sticky Headers

Sticky headers are a common UI pattern, but they often need to change their appearance when they become "stuck" to maintain readability or save space. Traditional solutions required JavaScript scroll listeners, which can cause performance issues. Modern CSS allows you to handle this natively using **Scroll State Queries**.

## Implementation Steps

### 1. Create the Sticky Container
You need a container that will act as the sticky element and the container for the scroll state query.

```html
<!-- Wrap in a section to define the containment area for the sticky element -->
<div class="section">
  <div class="sticky-container">
    <div class="sticky-header">
      Section Header
    </div>
  </div>
  <div class="content">
    <!-- Content goes here -->
  </div>
</div>
```

### 2. Apply CSS for Sticky Behavior and Container Type
Set `container-type: scroll-state` on the `position: sticky` element, not on its scrollable ancestor. Always specify a `container-name`, since these queries may be nested and unnamed containers will collide. You can also combine it with size queries, e.g., `container-type: scroll-state inline-size`.

```css
.sticky-container {
  position: sticky;
  top: 0;
  /* MANDATORY: Always specify a container name to avoid collisions if CQs are nested */
  container-type: scroll-state;
  container-name: section-header;
  z-index: 10;
}

.sticky-header {
  /* Base styles for the header */
  background: #f0f0f0;
  padding: 20px;
  transition: background-color 0.3s, box-shadow 0.3s;
}
```

**Important:** The scroll state is queried by **descendants** of the scroll-state container. The styles in the `@container` query will not apply to the `.sticky-container` itself, but to its children (like `.sticky-header`). You cannot style the container element itself with its own scroll-state query.


### 3. Target the Stuck State
Use the `@container scroll-state(...)` query to apply styles when the header is stuck.

```css
/* Apply styles when the named container is stuck at the top */
@container section-header scroll-state(stuck: top) {
  .sticky-header {
    background: #0056b3;
    color: white;
    box-shadow: 0 4px 6px rgb(0 0 0 / 0.15);
  }
}
```

**Tip:** You can also query flow-relative edges with `stuck: block-start` or `stuck: inline-start` (and their `block-end` / `inline-end` counterparts) to better support internationalization (i18n) and right-to-left (RTL) layouts rather than physical ones. These are direction keywords accepted by the `stuck` feature, not property names: `stuck: inset-block-start` is invalid and never matches.

### Notes on Dimension Changes

Without scroll anchoring disabled, changing layout-affecting properties (height, padding, font-size) when stuck can cause visual flickering. Scroll anchoring on the in-flow content below the sticky element adjusts the scroll offset to compensate for the layout change, which pushes the element back out of its stuck position and triggers an oscillation.

Disable scroll anchoring on the parent of the sticky element to avoid this:

```css
.section {
  overflow-anchor: none;
}
```

Apply `overflow-anchor: none` to the direct parent of the `position: sticky` element, not to `:root` or `body`, which would disable scroll anchoring for the entire page. With this in place, you can freely change any property in the stuck state, including box-model properties and transforms.

DO NOT: Rely on `overflow-anchor: none` for elements stuck to the **bottom**. Scroll anchoring only compensates for layout shifts above the current scroll position, so it has no effect on bottom-stuck elements. Avoid changing box-model properties in the stuck state for bottom-stuck headers.

### Fallback strategies
Container scroll-state queries has limited availability.
Supported by: Chrome 133 (Feb 2025) and Edge 133 (Feb 2025).
Unsupported in: Firefox and Safari.

Scroll state queries are a progressive enhancement. In browsers that do not support `container-type: scroll-state`, the header will still stick to the top (due to `position: sticky`), but it will not visually transform. For most use cases, this is the recommended approach.

If stickiness is not essential, but `position: sticky` without different styling would be a worse experience than no stickiness at all, you can wrap the `position: sticky` and related declaration in an `@supports` query as well:

```css
.sticky-container {
  @supports (container-type: scroll-state) {
	  position: sticky;
	  top: 0;
	  container-type: scroll-state;
	  container-name: section-header;
	  z-index: 10;
	}
}
```
**Tip:** If your "stuck" styling requires a different background color for readability, consider setting your layout up so that the default styling works everywhere (Progressive Enhancement). If you must use a fallback, you can gate the `position: sticky` behaviour itself inside an `@supports (container-type: scroll-state)` query.

If the visual transformation is absolutely critical to the design (e.g., the stuck state introduces a background or compaction without which the content is unreadable), you can implement a robust JavaScript fallback using `IntersectionObserver`. You must duplicate your CSS styles under an `.is-stuck` class. The following generic polyfill checks `getComputedStyle` to traverse up and find the correct scroll container (defaulting to the viewport), matching the behavior of `@container scroll-state(stuck: top)`:

```javascript
function getScrollParent(node) {
  if (node == null || node === document.body || node === document.documentElement) {
    return null; // default to viewport
  }
  // An ancestor with a scrolling overflow is the sticky containing block even
  // before its content overflows, so test computed overflow, not dimensions.
  const { overflowX, overflowY } = getComputedStyle(node);
  if (/^(auto|scroll|hidden)$/.test(overflowY) || /^(auto|scroll|hidden)$/.test(overflowX)) {
    return node;
  }
  return getScrollParent(node.parentNode);
}

document.querySelectorAll('.sticky-container').forEach(container => {
  // Start at the parent: a scrolling overflow on the sticky element itself is
  // not its scrollport, and an IntersectionObserver root must be an ancestor
  // of the observed element.
  const root = getScrollParent(container.parentElement);
  
  const topOffset = parseFloat(getComputedStyle(container).top) || 0;
  
  const observer = new IntersectionObserver(
    ([e]) => {
      // intersectionRatio alone also drops below 1 while the element is still
      // below the fold or clipped at the bottom, so require that it has reached
      // the root's top edge before calling it stuck to the top.
      const stuckToTop =
        e.intersectionRatio < 1 &&
        e.rootBounds != null &&
        e.boundingClientRect.top <= e.rootBounds.top;
      e.target.classList.toggle('is-stuck', stuckToTop);
    },
    { 
      root: root,
      threshold: [1],
      rootMargin: `-${topOffset + 1}px 0px 0px 0px` 
    }
  );
  
  observer.observe(container);
});
```

*Note: This generic IntersectionObserver pattern can also be used as a polyfill for the `scroll-state(scrollable)` query.*
