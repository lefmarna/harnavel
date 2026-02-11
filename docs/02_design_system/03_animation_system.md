# Animation System

This document defines the animation guidelines and system.
It ensures smooth and meaningful transitions.

## 1. Principles

- **Subtle**: Avoid excessive motion. Use for focus, transitions, and feedback.
- **Purposeful**: Animate to guide user attention.
- **Consistent**: Use standard duration and easing functions.

## 2. Timing & Duration

- **Instant**: 0ms (No animation).
- **Fast**: 100ms (Hover, Click).
- **Normal**: 200ms (Page Transition, Modal Open).
- **Slow**: 300ms (Complex Expansion, Drawer).
- **Very Slow**: 500ms (Background Animation, Loading).

## 3. Easing (Bezier Curves)

- **Standard**: `ease` (Natural).
- **Entrance**: `ease-out` (Decelerate).
- **Exit**: `ease-in` (Accelerate).
- **Attention**: `ease-in-out` (Bounce, Pulse).

## 4. Implementation

- **CSS Transitions**: Simple hover/focus effects (`transition-all duration-200`).
- **Framer Motion**: Complex animations (page transitions, modals, keyframes).
- **Reduced Motion**: Respect user preferences (`@media (prefers-reduced-motion)`).

## Verification Checklist

- [ ] **Performance**: Is `will-change` used for complex animations (transform, opacity)?
- [ ] **Accessibility**: Does the animation stop if user prefers reduced motion?
- [ ] **Clarity**: Does the animation improve understanding (not distract)?
- [ ] **Duration**: Is the animation too slow (> 300ms) or too fast (< 100ms)?
- [ ] **Jank**: Does the animation run at 60fps (no layout thrashing)?