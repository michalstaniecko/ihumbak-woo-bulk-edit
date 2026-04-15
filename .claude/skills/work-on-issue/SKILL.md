---
name: work-on-issue
description: Start work on a GitHub issue (by ID) or a free-form task prompt — run analyze (Opus), code (Sonnet), test (Sonnet) agents sequentially.
argument-hint: [issue-id | task description]
disable-model-invocation: true
---

# Work on GitHub Issue or Task

Orchestrate implementation of a GitHub issue or a free-form task using three specialized agents run sequentially.

## Input

Arguments: `$ARGUMENTS`

### Determine input mode

Check if `$ARGUMENTS` is a number (issue ID) or a text description (free-form task):

- **Issue ID mode** — `$ARGUMENTS` is a positive integer (e.g. `42`): fetch issue details from GitHub.
- **Free-form task mode** — `$ARGUMENTS` is any non-numeric text: treat it as the full task description; skip the GitHub fetch step.

## Workflow

### Step 1: Fetch the issue (Issue ID mode only)

If input mode is **Issue ID**, run:

```bash
gh issue view $ARGUMENTS --json number,title,body,labels,assignees,milestone,comments
```

Display a brief summary to the user:
- Issue title and number
- Labels
- First 3 lines of the body

If input mode is **Free-form task**, display:
- "Task: $ARGUMENTS"
- Skip to Step 2 immediately — use `$ARGUMENTS` as the issue content wherever the workflow refers to "full issue content".

### Step 2: Agent 1 — Analysis & Planning (Opus)

Launch a **Plan** agent with `model: opus`. This agent runs in **foreground** — wait for it to complete before proceeding.

Agent prompt must include:
- Full issue content (number, title, body, labels, comments)
- This project context:
  - Stack: PHP 8.1+ WooCommerce plugin + React 18 + TypeScript strict
  - REST namespace: `ihumbak-woo-bulk-edit/v1`
  - Key directories: `src/` (PHP PSR-4), `assets/js/` (TS/React), `tests/Unit/`, `tests/Integration/`
  - Conventions: `declare(strict_types=1)`, Zod for API validation, React Query for server state, Zustand for local state, TanStack Table v8
  - SQL: only `$wpdb->prepare()`, never string concatenation
  - Tests: PHPUnit (Unit + Integration), Vitest (frontend)
- Task: "Analyze this issue thoroughly. Explore the codebase to understand the existing patterns and relevant files. Produce a detailed, step-by-step implementation plan including: (1) files to create/modify, (2) PHP classes/interfaces to implement, (3) REST endpoint changes if any, (4) frontend components/hooks/stores to add or modify, (5) test cases to write. Be specific about method signatures and data shapes."

Store the full plan output — it will be passed to the coding agent.

### Step 3: Agent 2 — Implementation (Sonnet)

Launch a **fullstack-developer** agent with `model: sonnet`. This agent runs in **foreground** — wait for it to complete before proceeding.

Agent prompt must include:
- Full issue content
- **Complete plan from Agent 1** (verbatim)
- Coding conventions:
  - PHP: `declare(strict_types=1)`, PSR-4 namespace `IhumbakWooBulkEdit`, `$wpdb->prepare()` for SQL, `wc_clean`/`sanitize_text_field`/`floatval` for input sanitization
  - TypeScript: strict mode, no `any`, `@wordpress/i18n` for all strings, no `dangerouslySetInnerHTML` without DOMPurify
  - Register new PHP services in `Container.php`, wire in `Plugin.php`
  - Frontend: React Query for server state, Zustand for local state, Zod schemas for API responses
- Task: "Implement this issue exactly according to the plan above. Follow all existing patterns in the codebase — read similar existing files before writing new ones. Write tests FIRST (TDD): for PHP, write PHPUnit tests in `tests/Unit/` or `tests/Integration/` mirroring the `src/` structure; for TypeScript, write Vitest tests. After tests are written and failing, implement the feature. Then run tests to confirm they pass. Do NOT skip tests."

Store the implementation summary (files created/modified, test results) — it will be passed to the review agent.

### Step 4: Agent 3 — Testing & Review (Sonnet)

Launch a **code-reviewer** agent with `model: sonnet`. This agent runs in **foreground**.

Agent prompt must include:
- Full issue content
- Plan from Agent 1
- Implementation summary from Agent 2
- Task: "Review the implementation of this issue. Check: (1) Run `vendor/bin/phpunit` — all tests must pass with no regressions; (2) Run `npm test` — all Vitest tests must pass; (3) Verify the implementation matches the acceptance criteria from the issue; (4) Check for security issues: SQL injection, XSS, missing nonce/capability checks; (5) Check TypeScript strict compliance (`npm run build` must succeed without errors); (6) Report: test results, any bugs found, any security concerns, what still needs to be done."

### Step 5: Final Report

After all three agents complete, present a structured summary to the user:

```
## Issue #$ARGUMENTS — Implementation Complete
(or "Task: $ARGUMENTS — Implementation Complete" for free-form mode)

### Plan (Agent 1)
[Key points from the plan]

### Implementation (Agent 2)
Files created: ...
Files modified: ...
Tests written: ...

### Test Results (Agent 3)
PHPUnit: X passed, Y failed
Vitest: X passed, Y failed
Issues found: ...

### Next Steps
[Anything remaining]
```

## Important Rules

- Agents run **sequentially**, never in parallel — each depends on the previous agent's output
- Always pass the **full context** from previous agents to the next one
- Do NOT skip any agent even if the issue seems simple
- The coding agent must write tests FIRST (TDD), then implement
- If any agent reports a blocker, stop and ask the user how to proceed
