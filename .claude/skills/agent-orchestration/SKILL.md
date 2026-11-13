---
name: agent-orchestration
description: Use when the user asks to delegate, orchestrate, or bake off work across Codex, OpenCode, or Antigravity (agy), or to have Claude supervise other coding agents.
---

# Agent Orchestration

Claude supervises. Codex, OpenCode and Antigravity do the coding, each on a chosen model and in its own git worktree. `run-agent.ps1` handles the mechanics; this file covers the judgment.

## When not to orchestrate

If a task is under ~30 lines or touches a single file, do it directly. Splitting it, writing prompts and reviewing would cost more than the work itself.

## Workflow

1. **Split** the task into independent slugs (`fix-cart-total`, `seller-badge-ui`). Two slugs must not edit the same files.
2. **Route** each slug to an agent and model using the table below.
3. **Write a prompt file** per slug from the template, in the session scratchpad (never inside the repo).
4. **Launch** each runner with the shell tool and `run_in_background: true`, all in one message:
   ```
   powershell -NoProfile -File .claude/skills/agent-orchestration/run-agent.ps1 -Agent codex -Model gpt-6.1-sol -Task fix-cart-total -PromptFile <scratchpad>\fix-cart-total.md
   ```
   Options: `-TimeoutMinutes 30` (default), `-RepoRoot <path>` (default: the current git toplevel), `-DryRun` (prints the command without running it).
   Codex reasoning effort is set in `~/.codex/config.toml` (`model_reasoning_effort`). Change it there before a hard run if needed.
5. **Wait.** You're notified when each runner exits. Don't poll.
6. **Read `<worktree>\.orch\RESULT.md` only.** The runner prints its path, and the worktree is `<repo-parent>\<repo>-orch\<slug>-<agent>`.
7. **Review** with the checklist below.
8. **Fix round**, if needed: run the agent again in its worktree with a short follow-up:
   - Codex: `codex exec resume --last -C <worktree> "<follow-up>"`
   - OpenCode: `opencode run -c "<follow-up>"` (working directory = the worktree)
   - Antigravity: `agy -c -p "<follow-up>" --mode auto` (working directory = the worktree)

   Make small fixes (a few lines) yourself rather than spending a round on them.
9. **Report** to the user: one short block per slug (status, what changed, tests, your verdict). **Merge only after they approve:** `git merge --no-ff orch/<slug>-<agent>`.
10. **Clean up** every run, kept or not: `git worktree remove --force <worktree>` and `git branch -D orch/<slug>-<agent>`.
11. **Deploy.** If backend code was merged, follow `.ai/rules/general.md` (deploy to Render). Agents never deploy.

## Routing table

| Task type | First choice | Cheap choice | Reviewer |
|---|---|---|---|
| Money, checkout, stock, tenant isolation, auth, migrations, hard bugs | `codex` `gpt-6.1-sol` (effort high) | `agy` `claude-opus-5-5-high` | Claude (you), with full diff |
| Feature work across several files | `codex` `gpt-6.1-sol` | `agy` `gemini-4-argon-high` | A model from a different family than the author |
| UI, Blade, Tailwind, copy, Flutter widgets | `agy` `gemini-3.8-flash-medium` | `opencode` `opencode/deepseek-v4.1-flash-free` | Claude, stat + spot check |
| Boilerplate tests, renames, docs | `opencode` `deepseek/deepseek-flash` | an `opencode/*-free` model | Claude, stat + test run |
| Search or exploration only | `agy` `gemini-3.8-flash-low` | an `opencode/*-free` model | none |

Rules:
- Never send money, tenant, auth or migration work to a free model.
- The reviewer is always a different model family from the author.
- Model lists change. Check them with `agy models`, `opencode models` and `~/.codex/config.toml`.
- This table is a starting guess. Update it when the ledger (below) shows a pattern.

## Prompt template

Copy exactly and fill in the angle brackets:

```
# Task: <one sentence>

## Goal
<what done looks like, from the user's point of view>

## Files in scope
<paths the agent may edit>

## Out of scope
<paths and behavior it must not touch>

## Project rules — read before editing
- CLAUDE.md
- .ai/rules/index.md and every rule file it maps to these paths
- <matching .claude/skills/jmmart-*/SKILL.md, e.g. jmmart-money-and-orders for checkout>

## Acceptance check
Run: <exact command, e.g. php artisan test --compact tests/Feature/CartTest.php>
It must pass. Run vendor/bin/pint --dirty --format agent if you changed PHP.

## Rules
Do not commit, push, or deploy. When done, write at most 15 lines to .orch/SUMMARY.md:
what changed, the tests you ran with their results, and open questions.
```

## Review checklist

1. Read `RESULT.md`. Any `WARNING:` line is a blocker:
   - `commits on branch`: the agent ignored the rules. Check the commits.
   - `main tree changed`: inspect `git status` in the main repo right away. The user's uncommitted work may have been touched.
2. Status:
   - `OK`: continue.
   - `FAILED`: read the log lines in RESULT.md. A login or quota error goes to the user (`! codex login` etc.); don't retry blindly.
   - `NO_SUMMARY` or `TIMEOUT`: check the Diff section. Partial work may still be usable.
3. **Run the acceptance command yourself** in the worktree. Never trust the summary's claim that tests pass.
4. Read the full diff (`git -C <worktree> diff <base>`) only for money, tenant, auth or migration files. For other files, the stat plus a spot check of the largest file is enough.
5. Check against the project skills that apply (tenant isolation, money and orders, writing actions).
6. `vendor/bin/pint --dirty --format agent` in the worktree before merging.

## Token rules

- Never read `.orch\agent.log` unless the status is `FAILED` or `NO_SUMMARY`, and then only its tail.
- Never paste whole diffs or logs into the conversation. Quote the lines that matter.
- One fix round is normal. After two failed rounds, do the work yourself or re-route to a stronger model.

## Bake-off and ledger

For important or uncertain tasks, run the same slug on 2–3 agents (`-Task cart-fix` with `-Agent codex`, then `-Agent agy`, and so on). Compare in this order: acceptance test passes, no rule violations, smallest diff. Keep one and clean up the rest.

After every orchestrated task, append one line to the memory file `agent-routing-ledger` (create it if missing):
`YYYY-MM-DD | <task type> | <agent> <model> | <OK/FAILED/fixed in N rounds> | <one-line note>`
Once three or more lines agree, update the routing table above.

## Testing this skill

`powershell -NoProfile -File .claude/skills/agent-orchestration/run-agent.Tests.ps1` runs the runner against `fake-agent.ps1` in a temp repo. It covers argument quoting, every status, both warnings, timeout kill, and an existing-branch error.
