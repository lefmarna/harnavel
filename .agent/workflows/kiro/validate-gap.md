---
description: Analyze implementation gap between requirements and existing codebase
---

# Implementation Gap Validation

> **Usage**: `/validate-gap <feature-name>`
>
> **Arguments**:
> - `<feature-name>`: The name of the feature specification to analyze

<background_information>
- **Mission**: Analyze the gap between requirements and existing codebase to inform implementation strategy
- **Success Criteria**:
  - Comprehensive understanding of existing codebase patterns and components
  - Clear identification of missing capabilities and integration challenges
  - Multiple viable implementation approaches evaluated
  - Technical research needs identified for design phase
</background_information>

<instructions>
## Core Task
Analyze implementation gap for the specified feature based on approved requirements and existing codebase.

## Execution Steps

1. **Load Context**:
   - Read `.kiro/specs/<feature-name>/spec.json` for language and metadata
   - Read `.kiro/specs/<feature-name>/requirements.md` for requirements
   - **Load ALL steering context**: Read entire `.kiro/steering/` directory including:
     - Default files: `structure.md`, `tech.md`, `product.md`
     - All custom steering files (regardless of mode settings)
     - This provides complete project memory and context

2. **Read Analysis Guidelines**:
   - Read `.kiro/settings/rules/gap-analysis.md` for comprehensive analysis framework

3. **Execute Gap Analysis**:
   - Follow gap-analysis.md framework for thorough investigation
   - Analyze existing codebase using grep and file reading
   - Use web search for external dependency research if needed
   - Evaluate multiple implementation approaches (extend/new/hybrid)
   - Use language specified in spec.json for output

4. **Generate Analysis Document**:
   - Create comprehensive gap analysis following the output guidelines in gap-analysis.md
   - Present multiple viable options with trade-offs
   - Flag areas requiring further research

## Important Constraints
- **Information over Decisions**: Provide analysis and options, not final implementation choices
- **Multiple Options**: Present viable alternatives when applicable
- **Thorough Investigation**: Use tools to deeply understand existing codebase
- **Explicit Gaps**: Clearly flag areas needing research or investigation
</instructions>

## Tool Guidance
- **Read first**: Load all context (spec, steering, rules) before analysis
- **Grep extensively**: Search codebase for patterns, conventions, and integration points
- **Web search**: Research external dependencies and best practices when needed
- **Write last**: Generate analysis only after complete investigation

## Output Description
Provide output in the language specified in spec.json with:

1. **Analysis Summary**: Brief overview (3-5 bullets) of scope, challenges, and recommendations
2. **Document Status**: Confirm analysis approach used
3. **Next Steps**: Guide user on proceeding to design phase

**Format Requirements**:
- Use Markdown headings for clarity
- Keep summary concise (under 300 words)
- Detailed analysis follows gap-analysis.md output guidelines

## Safety & Fallback

### Error Scenarios
- **Missing Requirements**: If requirements.md doesn't exist, stop with message: "Run `/spec-requirements <feature-name>` first to generate requirements"
- **Requirements Not Approved**: If requirements not approved, warn user but proceed (gap analysis can inform requirement revisions)
- **Empty Steering Directory**: Warn user that project context is missing and may affect analysis quality
- **Complex Integration Unclear**: Flag for comprehensive research in design phase rather than blocking
- **Language Undefined**: Default to English (`en`) if spec.json doesn't specify language

### Next Phase: Design Generation

**If Gap Analysis Complete**:
- Review gap analysis insights
- Run `/spec-design <feature-name>` to create technical design document
- Or `/spec-design <feature-name> -y` to auto-approve requirements and proceed directly

**Note**: Gap analysis is optional but recommended for brownfield projects to inform design decisions.
