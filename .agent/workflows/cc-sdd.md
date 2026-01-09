---
description: cc-sddを用いた要件定義セッションを開始する
---

Start cc-sdd requirements definition process (steps 1-5).

1. Determine session name: `YYYY-MM-DD-feature-name`
   - Example: `2024-01-09-add-login-feature`

<!-- turbo -->
2. Create session directory
   - `mkdir -p .kiro/requirements/sessions/{session_name}`

3. Initialize session.json
   - Create `.kiro/requirements/sessions/{session_name}/session.json`
   - Content:
     ```json
     {
       "sessionId": "{session_name}",
       "status": "in_progress",
       "currentStep": 1,
       "steps": {
         "1_purpose": false,
         "2_alternatives": false,
         "3_scope": false,
         "4_requirements": false,
         "5_ui_prompt": false
       },
       "createdAt": "{current_timestamp_iso8601}",
       "updatedAt": "{current_timestamp_iso8601}"
     }
     ```

4. Initialize discussion log
   - Create `.kiro/requirements/sessions/{session_name}/discussion_log.md`
   - Content:
     ```markdown
     # Requirements Session: {session_name}
     Status: In Progress
     Current Step: 1
     
     ```

5. Begin Step 1
   - Read `.kiro/requirements/references/step1_purpose.md`
   - Perform preliminary codebase investigation
   - Start dialogue with user

Next steps:
- Complete each step per completion criteria
- Output deliverables: `1_purpose.md`, `2_alternatives.md`, etc.
- Log all discussions to `discussion_log.md` per `discussion_logger.md`
- Update `session.json` on each step completion (see `session_progress.md`)
- Show progress display at each step start
- Proceed to next step after completion
