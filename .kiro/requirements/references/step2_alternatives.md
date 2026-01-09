# Step 2: Alternatives

**Goal**: Validate necessity of new development

**Completion**: 3+ alternatives proposed with recommendation

**Output**: `2_alternatives.md`

## Pre-Step: Automatic Codebase Analysis

Perform automatically (no user confirmation needed):

1. **Identify Related Code**
   - Search files by screen/component/feature names
   - Use `find_file`, `get_symbols_overview`, `find_symbol`

2. **Analyze Existing Patterns**
   - Search for similar features via `search_for_pattern`
   - Check UI components, APIs, state management
   - Look for toggle/visibility/layout change features

3. **Document Current State**
   - Understand behavior, data structures, UI from code
   - Identify constraints (scroll settings, columns, state)
   - Review tests if available

4. **Include in Output**
   - Current implementation summary
   - Reusable components
   - Technical constraints

## Alternative Template

**Option 1: Modify Existing Feature**
- Pros: ...
- Cons: ...

**Option 2: Build New Feature**
- Pros: ...
- Cons: ...

**Option 3: Workaround / Simple Solution**
- Pros: ...
- Cons: ...

**Recommendation**: [Selected option with justification]
