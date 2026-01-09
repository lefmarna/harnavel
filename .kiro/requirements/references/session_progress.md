# Session Progress Management

Use `session.json` to track progress through the 5 steps.

## Initial Structure

Created at session start:

```json
{
  "sessionId": "YYYY-MM-DD-feature-name",
  "status": "in_progress",
  "currentStep": 1,
  "steps": {
    "1_purpose": false,
    "2_alternatives": false,
    "3_scope": false,
    "4_requirements": false,
    "5_ui_prompt": false
  },
  "createdAt": "2025-01-15T09:00:00+09:00",
  "updatedAt": "2025-01-15T09:00:00+09:00"
}
```

Note: All timestamps must be in ISO8601 format with timezone (e.g., `2025-01-15T09:00:00+09:00`).

## Update on Step Completion

When a step is completed:
1. Set step flag: `false` → `true`
2. Increment `currentStep` to next step number
3. Update `updatedAt` to current ISO8601 timestamp

Example after Step 1 completion:
```json
{
  "currentStep": 2,
  "steps": {
    "1_purpose": true,
    "2_alternatives": false,
    ...
  },
  "updatedAt": "2025-01-15T10:30:00Z"
}
```

## Session Completion

When all steps are `true`:

```json
{
  "status": "completed",
  "completedAt": "2025-01-15T15:00:00Z",
  "currentStep": 5,
  "steps": {
    "1_purpose": true,
    "2_alternatives": true,
    "3_scope": true,
    "4_requirements": true,
    "5_ui_prompt": true
  }
}
```

## Progress Display

Show progress to user at each step start:

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Requirements Session: {feature-name}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Current: Step 2/5 - Alternatives
Progress: [████░░░░░░] 40%

[Continue with step content]
```
