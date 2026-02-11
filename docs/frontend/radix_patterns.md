# Radix UI Patterns

This document defines implementation patterns and best practices for Radix UI.
It ensures accessible and robust component usage.

## 1. Principles

- **Accessibility**: Always use semantic roles (`dialog`, `checkbox`, `switch`).
- **Styling**: Wrap Radix components with Tailwind utility classes (`className`).
- **Composition**: Use slot components to inject behavior (`asChild`).

## 2. Common Components (Examples)

### A. Dialog (Modal)

- **Structure**: `Dialog` > `DialogTrigger` > `DialogContent` > `DialogHeader` > `DialogTitle` > `DialogDescription`.
- **Close**: `DialogClose` or `X` icon.
- **Overlay**: `DialogOverlay` (Backdrop).

### B. Dropdown Menu

- **Structure**: `DropdownMenu` > `DropdownMenuTrigger` > `DropdownMenuContent` > `DropdownMenuItem`.
- **Keyboard**: Arrow keys navigation (Up/Down).
- **Escape**: Close on Esc.

### C. Popover

- **Structure**: `Popover` > `PopoverTrigger` > `PopoverContent`.
- **Focus**: Trap focus inside content when open.

## 3. Verification Checklist

- [ ] **Import**: Use named exports (`import * as Dialog from '@radix-ui/react-dialog'`).
- [ ] **Aria Attributes**: Check if `aria-label`, `aria-describedby` are set.
- [ ] **Keyboard**: Verify Tab/Arrow/Esc behavior.
- [ ] **Screen Reader**: Test with VoiceOver/NVDA (if possible).
- [ ] **Z-Index**: Verify overlay stacking context (`z-50`).

## 4. Troubleshooting

- **Focus Trap**: If focus is lost, check `Portal` usage.
- **Scrolling**: Prevent body scroll when modal is open (`data-state="open"`).
- **Rendering**: Check if `asChild` is correctly passing refs.