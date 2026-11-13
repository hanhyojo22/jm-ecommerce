# Agent Orchestration Skill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Claude Code skill that lets Claude (the supervisor) send coding tasks to Codex, OpenCode and Antigravity, each on a chosen model and each in its own git worktree, then review their results cheaply and merge only what passes.

**Architecture:** One PowerShell runner script (`run-agent.ps1`) does the mechanical part: it creates the worktree, writes the prompt to a file, starts the chosen CLI with a timeout, and writes a short `RESULT.md` (exit status, the agent's summary, `git diff --stat`). `SKILL.md` covers the judgment part: splitting the task, picking the agent and model, reviewing, sending fix rounds, merging, and recording which model did well. Claude starts the runner with `run_in_background` and reads only `RESULT.md` plus the diffs it needs.

**Tech Stack:** Windows PowerShell 5.1, git worktrees, `codex exec`, `opencode run`, `agy -p`.

**Spec:** No separate spec doc. The requirements come from the 2026-11-13 conversation and are copied into Global Constraints below.

## Global Constraints

- Skill location: `.claude/skills/agent-orchestration/` in this repo, so it's versioned (it can be copied to `~/.claude/skills/` later to use it in every project).
- Agent commands, verified from each CLI's `--help` on 2026-11-13:
  - Codex: `codex exec -C <worktree> -m <model> -s workspace-write -o <worktree>\.orch\SUMMARY.md "<msg>"`
  - OpenCode: `opencode.cmd run --auto -m <provider/model> "<msg>"`, run with the worktree as the working directory (`opencode` is a `.ps1` shim, and `Start-Process` needs the `.cmd`).
  - Antigravity: `agy -p "<msg>" --mode auto --model <id>`, run with the worktree as the working directory.
- The message passed on the command line is always the fixed string `Read .orch/PROMPT.md and complete the task. Write your summary to .orch/SUMMARY.md.` The real prompt goes in a file, so quoting, newlines and the space in `D:\JMSOLUTION PROJECT` never reach the argument parser.
- Worktrees go in `<repo-parent>\<repo-name>-orch\<task>-<agent>` on branch `orch/<task>-<agent>`. They are never created inside the repo.
- `.orch/` is added to `.git/info/exclude` (shared by all worktrees), so prompts and logs never get committed.
- Agents must not commit, push or deploy. Only the supervisor merges, and only after the user approves. The Render deploy rule in `.ai/rules/general.md` applies after the merge, never inside an agent run.
- The default timeout is 30 minutes (`-TimeoutMinutes 30`). When it runs out, the process tree is killed and the result is marked `TIMEOUT`.
- Token rule: the supervisor reads `RESULT.md` and the diffs of high-risk files. It reads `.orch/agent.log` only when `RESULT.md` says `FAILED` or `NO_SUMMARY`.

## Review Focus

1. **The CLI is logged out or out of quota:** the run exits non-zero within seconds. Expected: `RESULT.md` shows `Status: FAILED`, the exit code, and the last 40 lines of the log. The run must not count as a success just because the diff is empty. Covered by a Task 2 test.
2. **The agent writes no summary:** expected `Status: NO_SUMMARY` with the last 40 log lines as a fallback. Covered by a Task 2 test.
3. **The agent hangs:** expected kill at the timeout, `Status: TIMEOUT`, and no orphaned process. Covered by a Task 2 test using `-TimeoutMinutes` with a fake agent that sleeps.
4. **The agent touches the main working tree or commits:** expected `RESULT.md` to flag `WARNING: main tree changed` or `WARNING: N commits on branch`. Covered by a Task 2 test.
5. **The branch or worktree already exists:** expected the runner to stop before starting an agent, with a clear error and no change to the existing worktree. Covered by a Task 1 test.

---

## File Structure

| File | Responsibility |
|---|---|
| `.claude/skills/agent-orchestration/run-agent.ps1` | Worktree setup, building the agent command, run with timeout, writing `RESULT.md` |
| `.claude/skills/agent-orchestration/run-agent.Tests.ps1` | A plain assert script (no Pester dependency) that uses `-DryRun` and a fake agent |
| `.claude/skills/agent-orchestration/fake-agent.ps1` | A test double that acts like a CLI: writes a file or summary, sleeps, or fails, depending on its arguments |
| `.claude/skills/agent-orchestration/SKILL.md` | Workflow, routing table, prompt template, review checklist, token rules |

---

### Task 1: Runner builds the worktree and agent command

**Files:**
- Create: `.claude/skills/agent-orchestration/run-agent.ps1`
- Create: `.claude/skills/agent-orchestration/run-agent.Tests.ps1`

**Interfaces:**
- Produces: `run-agent.ps1 -Agent <codex|opencode|agy|fake> -Model <string> -Task <slug> -PromptFile <path> [-TimeoutMinutes <double>=30] [-RepoRoot <path>=git toplevel] [-DryRun]`
- With `-DryRun`: creates nothing and writes one JSON object to stdout: `{ "exe": string, "args": string[], "workingDir": string, "worktree": string, "branch": string }`
- Without `-DryRun`: creates the worktree and branch, copies the prompt to `<worktree>\.orch\PROMPT.md`, and returns the worktree path (Task 2 adds the run).

- [ ] **Step 1: Write the failing tests** in `run-agent.Tests.ps1` (an `Assert($cond, $msg)` helper that throws, a counter, exit code 1 on any failure):
  - `codex dry run`: `exe -eq 'codex'`; `args` contains `exec`, `-C`, the worktree path, `-m`, `gpt-6.1-sol`, `-s`, `workspace-write`, `-o`, and ends with the fixed message.
  - `opencode dry run`: `exe` ends with `opencode.cmd`; `args` is `run --auto -m deepseek/deepseek-v4-pro <msg>`; `workingDir -eq worktree`.
  - `agy dry run`: `args` is `-p <msg> --mode auto --model gemini-4-argon-high`; `workingDir -eq worktree`.
  - `worktree path`: for task `fix-cart`, agent `codex`, the path is `<parent>\<repo>-orch\fix-cart-codex` and the branch is `orch/fix-cart-codex`.
  - `spaces in repo path`: run against a temp repo created in `$env:TEMP\orch test repo\` (with spaces). The worktree is created and `PROMPT.md` matches the source byte for byte.
  - `existing branch` (Review Focus 5): creating the same task and agent twice throws `branch orch/<task>-<agent> already exists`, and the first worktree's files are unchanged.
  - `bad agent`: `-Agent foo` fails parameter validation.
- [ ] **Step 2: Run them:** `powershell -NoProfile -File .claude/skills/agent-orchestration/run-agent.Tests.ps1`. Expected: FAIL, script not found.
- [ ] **Step 3: Implement** the parameters with `[ValidateSet('codex','opencode','agy','fake')]`, then the path and branch rules from Global Constraints, `git worktree add -b`, the `.git/info/exclude` append (only if `.orch/` isn't already in it), and a switch statement that builds `exe` and `args` for each agent. `fake` maps to `powershell -NoProfile -File fake-agent.ps1 <msg>`.
- [ ] **Step 4: Run the tests.** Expected: all PASS. The test script removes its temp repos and worktrees in `finally`.
- [ ] **Step 5: Commit**
  ```
  git add .claude/skills/agent-orchestration/run-agent.ps1 .claude/skills/agent-orchestration/run-agent.Tests.ps1
  git commit -m "Add agent orchestration runner: worktree setup and per-agent commands"
  ```

### Task 2: Run with timeout and write RESULT.md

**Files:**
- Modify: `.claude/skills/agent-orchestration/run-agent.ps1`
- Create: `.claude/skills/agent-orchestration/fake-agent.ps1`
- Modify: `.claude/skills/agent-orchestration/run-agent.Tests.ps1`

**Interfaces:**
- Consumes: the Task 1 runner and its `-Agent fake` path.
- Produces: `<worktree>\.orch\RESULT.md` in this exact shape, which SKILL.md relies on:
  ```
  Status: OK | FAILED | NO_SUMMARY | TIMEOUT
  Agent: <agent>  Model: <model>  Exit: <code>  Duration: <mm:ss>
  Worktree: <path>  Branch: <branch>
  <zero or more WARNING: lines>
  ## Summary
  <SUMMARY.md contents, or the last 40 log lines when it's missing>
  ## Diff
  <git diff --stat output, untracked files included>
  ```
- `fake-agent.ps1` behavior is controlled by the env var `FAKE_MODE`: `ok` (writes `hello.txt` and `SUMMARY.md`), `nosummary` (writes `hello.txt` only), `fail` (prints `not logged in` and exits 3), `hang` (sleeps 600 s), `commit` (writes and commits `hello.txt`), `maintree` (writes `stray.txt` into `$env:FAKE_MAIN_TREE`).

- [ ] **Step 1: Write the failing tests**, one per mode, each asserting the first line of `RESULT.md`:
  - `ok` → `Status: OK`, and the Diff section lists `hello.txt`.
  - `nosummary` → `Status: NO_SUMMARY` (Review Focus 2).
  - `fail` → `Status: FAILED`, `Exit: 3`, and the Summary section contains `not logged in` (Review Focus 1).
  - `hang` with `-TimeoutMinutes 0.1` → `Status: TIMEOUT`, returns in under 30 s, and no `powershell` process is still running the fake script afterwards (Review Focus 3).
  - `commit` → contains `WARNING: 1 commits on branch` (Review Focus 4).
  - `maintree` → contains `WARNING: main tree changed` (Review Focus 4).
- [ ] **Step 2: Run them.** Expected: FAIL (no RESULT.md).
- [ ] **Step 3: Implement** the run: `Start-Process -PassThru -NoNewWindow -WorkingDirectory <workingDir> -RedirectStandardOutput .orch\agent.log -RedirectStandardError .orch\agent.err.log`, then `WaitForExit(ms)`, and on timeout `taskkill /T /F /PID`. Before starting, record the main tree's `git status --porcelain` and the branch's base commit, then compare both afterwards for the warnings. Status precedence: TIMEOUT > FAILED (exit ≠ 0) > NO_SUMMARY > OK. Run `git add -N .` before `git diff --stat` so new files appear.
- [ ] **Step 4: Run all tests.** Expected: PASS.
- [ ] **Step 5: Commit**
  ```
  git add .claude/skills/agent-orchestration/
  git commit -m "Run agents with timeout and write RESULT.md with status and warnings"
  ```

### Task 3: SKILL.md (supervisor workflow)

**Files:**
- Create: `.claude/skills/agent-orchestration/SKILL.md`

**Interfaces:**
- Consumes: the runner's parameters (Task 1) and the `RESULT.md` shape (Task 2).

Frontmatter: `name: agent-orchestration`; description: "Use when the user asks to delegate, orchestrate, or bake off work across Codex, OpenCode, or Antigravity (agy), or to have Claude supervise other coding agents." Body sections, in order. Keep the whole file under 150 lines.

- [ ] **Step 1: When not to orchestrate.** A task under ~30 lines or touching one file: do it directly, because orchestration overhead costs more.
- [ ] **Step 2: Workflow.** (1) Split the task into independent slugs. (2) Pick the agent and model from the routing table. (3) Write `.orch` prompt files from the template. (4) Start each runner through the shell tool with `run_in_background: true`, one call per agent, all in one message. (5) When notified, read `RESULT.md` only. (6) Review. (7) Fix round: `codex exec resume --last -C <wt>`, `opencode run -c` or `agy -c` in the worktree, or make small fixes directly. (8) Show the user a short combined report and merge only after they approve (`git merge --no-ff orch/<slug>-<agent>`). (9) `git worktree remove` and `git branch -d` for every run. (10) If backend code changed, follow the Render deploy rule.
- [ ] **Step 3: Routing table.** Columns: task type → first choice / cheap choice / reviewer.
  - Money, checkout, tenant isolation, auth, hard bugs → `codex -m gpt-6.1-sol` with `-c model_reasoning_effort="high"`, or `agy --model claude-opus-5-5-high`. Never route these to a free model.
  - UI, copy, Blade or Tailwind tweaks, boilerplate tests → `agy --model gemini-3.8-flash-medium` or `opencode -m opencode/<free model>`.
  - Search or exploration → a Flash or low-effort model.
  - Reviewer: always a different model family from the author.
  - Note: this table is a starting guess. Update it from the ledger (Step 6).
- [ ] **Step 4: Prompt template**, copied exactly into SKILL.md: Goal / Files in scope / Out of scope / Project rules to read (`CLAUDE.md`, `.ai/rules/`, any matching `.claude/skills/jmmart-*`) / Acceptance check (an exact test command) / "Do not commit, push, or deploy. When done, write at most 15 lines to .orch/SUMMARY.md: what changed, tests run with results, open questions."
- [ ] **Step 5: Review checklist and token rules.** Read RESULT.md first. Treat any WARNING as a blocker. Run the acceptance test command in the worktree yourself rather than trusting the summary. Read full diffs only for money, tenant, auth or migration files; for other files, `--stat` plus a spot check is enough. Run `vendor/bin/pint --dirty --format agent` in the worktree before merging. Open `agent.log` only for FAILED or NO_SUMMARY. Never paste whole logs into the conversation.
- [ ] **Step 6: Bake-off and ledger.** For important tasks, run the same slug on 2–3 agents (`<slug>-codex`, `<slug>-agy` and so on), compare tests passed, diff size and rule violations, and keep one. Afterwards, append one line to the memory file `agent-routing-ledger` (date, task type, agent and model, outcome) and update the routing table once the ledger shows a pattern.
- [ ] **Step 7: Verify that the skill loads:** start a fresh session, ask "orchestrate a bake-off for X", and check that `agent-orchestration` is invoked.
- [ ] **Step 8: Commit**
  ```
  git add .claude/skills/agent-orchestration/SKILL.md
  git commit -m "Add agent orchestration skill workflow, routing table and review rules"
  ```

### Task 4: Real smoke run on all three CLIs

**Files:** none created. This checks the real CLIs, which the fake agent can't.

- [ ] **Step 1:** Write `$env:TEMP\orch-smoke.md` containing: "Create `ORCH_SMOKE.txt` containing the word ok. Do not commit."
- [ ] **Step 2:** Run the runner once per agent with task `smoke` on its cheap model: `codex` (`gpt-6.1-sol`), `opencode` (`opencode/deepseek-v4.1-flash-free`) and `agy` (`gemini-3.8-flash-low`).
- [ ] **Step 3:** Expected for each: `Status: OK`, a Diff section listing `ORCH_SMOKE.txt`, and no WARNING lines. Any `FAILED` line names a login or quota problem for the user to fix (`! codex login`, etc.).
- [ ] **Step 4:** Remove the three worktrees and `orch/smoke-*` branches, then confirm with `git worktree list` that only the main tree remains.
- [ ] **Step 5:** Fix any command-line flag that turns out wrong in real use, in both the runner and the Global Constraints, then rerun Task 1's tests and commit.
