# Step 4: Requirements

**Goal**: Create user-focused requirements document

**Completion**: User stories and acceptance criteria defined (no technical details)

**Output**: `4_requirements.md`

## Pre-Step: Code Investigation

Before writing requirements:
1. Understand current user workflows
2. Check existing validation rules and constraints
3. Review similar features for reference
4. Define realistic, implementable requirements

## Note

Do NOT include technical details (APIs, DB schema). Focus on user behavior.

## Requirements Template

### Overview
Brief feature description

### User Stories
- As `<user>`, I want to `<action>` so that `<benefit>`.

### Acceptance Criteria
- **Scenario**: Normal case - ...
- **Scenario**: Edge case - ...
- **Scenario**: Error case - ...

## Good Example (User-Focused)

```markdown
### US-1: Search Tasks by Title
As a heavy user managing 50+ tasks
I want to search tasks by keyword in the title
So that I can quickly find target tasks without scrolling

**AC-1.1: Partial match search works**
Given user opens task board
When user types "meeting" in search bar
Then all tasks with "meeting" in title are displayed

### Non-Functional Requirements
#### Responsiveness
- Results displayed within 0.5 seconds after input
- Results update in real-time during typing

#### Usability
- Search bar focusable with Tab key
- Search clearable with Escape key
- Search bar usable on mobile devices
```

## Bad Example (Contains Technical Details)

Do NOT include technical implementation details:
- API specifications (endpoints, request/response formats)
- Database schema (table definitions, indexes)
- Security implementation details (SQL injection prevention)
- Implementation priorities or phasing
- Specific tech stack (library names, frameworks)
