# Discussion Log Format

Format template for `discussion_log.md`.

**Language Policy**: Section names are in Japanese (exception to docs English policy) because logs are read by developers.

## Standard Entry

```markdown
## [step_name] - YYYY-MM-DD HH:MM
### ユーザー発言
> [user_input]

### AI提案/質問
[agent_response]

### 決定事項
- [decision_1]
- [decision_2]

### 理由/背景
[rationale]

---
```

## Change Log Entry

```markdown
## [step_name修正] [change_summary] - YYYY-MM-DD HH:MM
### ユーザー発言
> [user_input]

[if needed]
### AI質問
[agent_question]

### ユーザー回答
> [user_response]

### 変更内容
- ❌ 却下: [rejected_item]
- ✅ 採用: [adopted_item]

### 理由/背景
[rationale]

### 影響を受けるステップ
- [affected_step]

---
```
