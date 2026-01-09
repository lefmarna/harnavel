# Step 5: UI Design Prompt

**Goal**: Generate UI implementation prompt

**Completion**: Screen-by-screen prompts created

**Output**: `5_ui_prompt.md`

## Pre-Step: Code Investigation

Before generating UI prompt:
1. Review existing UI components (buttons, modals, forms)
2. Understand design system, colors, style guide
3. Identify reusable components
4. Generate prompt aligned with existing UI patterns

## UI Prompt Components

- **Screen Name**: ...
- **Purpose**: ...
- **Layout**: (ASCII art or description)
- **Key Components**: Header, cards, buttons, etc.
- **User Flow**: Actions and transitions
- **Design Tone**: Colors, spacing, consistency with existing screens
- **Implementation Prompt**: Specific component creation instructions

## Layout Diagram Guidelines

### Purpose
Visually share screen structure before implementation

### Basic Symbols
```
┌─┬─┐   Top border, corners
│ │ │   Vertical lines, separators
├─┼─┤   Middle separators
└─┴─┘   Bottom border, corners
[Item]  Component or UI element
[🔍]    Icon (emoji representation)
━━━━━   Emphasized separator
```

### Example: Modal Dialog
```
┌─────────────────────────────────────┐
│ Create Task                    [×]  │
├─────────────────────────────────────┤
│                                     │
│ Task Name                           │
│ ┌─────────────────────────────┐    │
│ │ [Input field]               │    │
│ └─────────────────────────────┘    │
│                                     │
│ Priority                            │
│ ○ Now  ○ Later  ○ Someday          │
│                                     │
│ Category                            │
│ [▼ Select]                          │
│                                     │
├─────────────────────────────────────┤
│              [Cancel]  [Create]     │
└─────────────────────────────────────┘
```

### Example: List Screen
```
┌─────────────────────────────────────┐
│ [🔍] Search...              [+]     │
├─────────────────────────────────────┤
│                                     │
│ ┌───────────────────────────────┐  │
│ │ ☐ Task 1         [Work]  [⋮] │  │
│ │ Description...   2024/01/15   │  │
│ └───────────────────────────────┘  │
│                                     │
│ ┌───────────────────────────────┐  │
│ │ ☑ Task 2      [Personal] [⋮] │  │
│ │ Description...   2024/01/14   │  │
│ └───────────────────────────────┘  │
│                                     │
└─────────────────────────────────────┘
```

## Good Prompt Components

A complete UI prompt should include:

1. **Screen Purpose** (1 sentence)
   - Clear statement of what the screen is for

2. **Layout Diagram** (Text-based)
   - Visualize component placement
   - User can understand structure at a glance

3. **Key Components** (Bulleted list)
   - Header, forms, buttons, lists, etc.
   - Brief role description for each

4. **User Flow** (Step format)
   - How users interact
   - Screen transition flow

5. **Design Tone** (Consistency with existing)
   - Alignment with existing design system
   - Color palette, fonts, spacing

6. **Implementation Prompt** (Detailed spec)
   - For frontend developers or AI tools
   - Specific requirements and design instructions

7. **Visual Image** (Optional)
   - Mockup created with image generation tool
   - More concrete image sharing
