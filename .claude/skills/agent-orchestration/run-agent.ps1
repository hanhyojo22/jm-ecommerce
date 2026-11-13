<#
.SYNOPSIS
Runs one coding agent (Codex, OpenCode, Antigravity) on a task in its own git worktree and writes .orch\RESULT.md.

.EXAMPLE
powershell -NoProfile -File run-agent.ps1 -Agent codex -Model gpt-6.1-sol -Task fix-cart -PromptFile C:\tmp\fix-cart.md
Fix round on the same worktree (fresh agent session, diff still against the original base):
powershell -NoProfile -File run-agent.ps1 -Agent codex -Model gpt-6.1-sol -Task fix-cart -PromptFile C:\tmp\fix-cart-round2.md -Continue
Remove the worktree and branch (keeps the main repo's vendor/node_modules intact):
powershell -NoProfile -File run-agent.ps1 -Agent codex -Model x -Task fix-cart -Cleanup
#>
param(
    [Parameter(Mandatory)][ValidateSet('codex', 'opencode', 'agy', 'fake')][string]$Agent,
    [Parameter(Mandatory)][string]$Model,
    [Parameter(Mandatory)][ValidatePattern('^[a-z0-9][a-z0-9-]*$')][string]$Task,
    [string]$PromptFile,
    [double]$TimeoutMinutes = 30,
    [string]$RepoRoot,
    [switch]$DryRun,
    [switch]$SetupOnly,
    [switch]$Continue,
    [switch]$Cleanup
)

$ErrorActionPreference = 'Stop'
$message = 'Read .orch/PROMPT.md and complete the task. Write your summary to .orch/SUMMARY.md.'
$linkedDirs = @('vendor', 'node_modules')

# Under EAP=Stop, PS 5.1 turns redirected native stderr into a terminating error; git warnings must not kill the run.
function Invoke-GitQuiet {
    $ErrorActionPreference = 'Continue'
    & git @args 2>$null
}

if (-not $RepoRoot) { $RepoRoot = git rev-parse --show-toplevel }
$RepoRoot = (Resolve-Path $RepoRoot).Path

$repoName = Split-Path $RepoRoot -Leaf
$worktree = Join-Path (Split-Path $RepoRoot -Parent) "$repoName-orch\$Task-$Agent"
$branch = "orch/$Task-$Agent"
$orchDir = Join-Path $worktree '.orch'

if ($Cleanup) {
    # Remove junctions first: git can't delete a worktree holding them, and recursive deletes would follow them into the main repo.
    foreach ($dir in $linkedDirs) {
        $link = Join-Path $worktree $dir
        if (Test-Path $link) { cmd /c rmdir "`"$link`"" | Out-Null }
    }
    if (Test-Path $worktree) { Invoke-GitQuiet -C $RepoRoot worktree remove --force $worktree }
    Invoke-GitQuiet -C $RepoRoot worktree prune
    Invoke-GitQuiet -C $RepoRoot branch -D $branch | Out-Null
    if (Test-Path $worktree) { throw "could not remove $worktree (a process may hold it open); run git worktree prune later" }
    return
}

if (-not $PromptFile) { throw '-PromptFile is required' }
$PromptFile = (Resolve-Path $PromptFile).Path

switch ($Agent) {
    'codex' {
        $exe = 'codex'
        # -o gets codex's final message only; the agent's own SUMMARY.md stays authoritative.
        $agentArgs = @('exec', '-C', $worktree, '-m', $Model, '-s', 'workspace-write', '-o', (Join-Path $orchDir 'last-message.md'), $message)
    }
    'opencode' {
        # opencode resolves to a .ps1 shim; Start-Process needs the .cmd beside it.
        $exe = Join-Path (Split-Path (Get-Command opencode).Source -Parent) 'opencode.cmd'
        $agentArgs = @('run', '--auto', '-m', $Model, $message)
    }
    'agy' {
        $exe = 'agy'
        $agentArgs = @('-p', $message, '--mode', 'auto', '--model', $Model)
    }
    'fake' {
        $exe = 'powershell'
        $agentArgs = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', (Join-Path $PSScriptRoot 'fake-agent.ps1'), $message)
    }
}

if ($DryRun) {
    [pscustomobject]@{ exe = $exe; args = $agentArgs; workingDir = $worktree; worktree = $worktree; branch = $branch } | ConvertTo-Json -Compress
    return
}

if ($Continue) {
    if (-not (Test-Path (Join-Path $orchDir 'BASE'))) { throw "no worktree from a previous run at $worktree" }
    Remove-Item -Force -ErrorAction SilentlyContinue (Join-Path $orchDir 'SUMMARY.md'), (Join-Path $orchDir 'last-message.md'), (Join-Path $orchDir 'RESULT.md')
}
else {
    git -C $RepoRoot rev-parse --verify --quiet "refs/heads/$branch" | Out-Null
    if ($LASTEXITCODE -eq 0) { throw "branch $branch already exists; remove it or pick another -Task" }
    if (Test-Path $worktree) { throw "worktree path $worktree already exists" }

    Invoke-GitQuiet -C $RepoRoot worktree add --quiet -b $branch $worktree
    if ($LASTEXITCODE -ne 0) { throw "git worktree add failed for $worktree" }

    $exclude = Join-Path (git -C $RepoRoot rev-parse --path-format=absolute --git-common-dir) 'info\exclude'
    if (-not ((Test-Path $exclude) -and (Select-String -Path $exclude -SimpleMatch -Pattern '.orch/' -Quiet))) {
        Add-Content -Path $exclude -Value '.orch/' -Encoding ascii
    }

    # Fresh worktrees lack gitignored dependencies, so tests and pint could not run there.
    foreach ($dir in $linkedDirs) {
        if (Test-Path (Join-Path $RepoRoot $dir)) {
            New-Item -ItemType Junction -Path (Join-Path $worktree $dir) -Target (Join-Path $RepoRoot $dir) | Out-Null
        }
    }
    if (Test-Path (Join-Path $RepoRoot '.env')) { Copy-Item (Join-Path $RepoRoot '.env') (Join-Path $worktree '.env') }

    New-Item -ItemType Directory -Force $orchDir | Out-Null
    Set-Content -Path (Join-Path $orchDir 'BASE') -Value (git -C $worktree rev-parse HEAD) -Encoding ascii
}

Copy-Item -LiteralPath $PromptFile -Destination (Join-Path $orchDir 'PROMPT.md') -Force

if ($SetupOnly) {
    return $worktree
}

# Windows PowerShell 5.1 joins -ArgumentList without quoting, so quote each argument ourselves.
function ConvertTo-ArgumentString([string[]]$Arguments) {
    ($Arguments | ForEach-Object {
        if ($_ -match '[\s"]') { '"' + ($_ -replace '(\\*)"', '$1$1\"' -replace '(\\+)$', '$1$1') + '"' } else { $_ }
    }) -join ' '
}

function Get-LogTail {
    @('agent.log', 'agent.err.log') | ForEach-Object { Join-Path $orchDir $_ } |
        Where-Object { Test-Path $_ } | ForEach-Object { Get-Content $_ -Tail 40 -Encoding UTF8 } |
        ForEach-Object { $_ -replace "\x1b\[[0-9;]*m", '' }
}

# Content snapshot of the main tree (tracked + untracked) via a throwaway index, so edits to already-dirty files are caught.
function Get-MainTreeSnapshot {
    $index = Join-Path $env:TEMP ("orch-index-" + [guid]::NewGuid().ToString('N'))
    $previous = $env:GIT_INDEX_FILE
    $env:GIT_INDEX_FILE = $index
    try {
        Invoke-GitQuiet -C $RepoRoot read-tree HEAD
        Invoke-GitQuiet -C $RepoRoot add -A
        Invoke-GitQuiet -C $RepoRoot write-tree
    }
    finally {
        $env:GIT_INDEX_FILE = $previous
        Remove-Item -Force -ErrorAction SilentlyContinue $index
    }
}

$baseCommit = (Get-Content (Join-Path $orchDir 'BASE')).Trim()
$headBefore = git -C $worktree rev-parse HEAD
$mainTreeBefore = Get-MainTreeSnapshot
$stopwatch = [Diagnostics.Stopwatch]::StartNew()

$process = Start-Process -FilePath $exe -ArgumentList (ConvertTo-ArgumentString $agentArgs) -WorkingDirectory $worktree `
    -NoNewWindow -PassThru `
    -RedirectStandardOutput (Join-Path $orchDir 'agent.log') -RedirectStandardError (Join-Path $orchDir 'agent.err.log')
$null = $process.Handle # caches the handle so ExitCode is readable after exit

$warnings = @()
$timedOut = -not $process.WaitForExit([int]($TimeoutMinutes * 60000))
if ($timedOut) {
    taskkill /T /F /PID $process.Id 2>&1 | Out-Null
    if (-not $process.WaitForExit(30000)) { $warnings += "WARNING: agent process $($process.Id) did not exit after kill" }
}
$stopwatch.Stop()
$exitCode = if ($timedOut) { 'killed' } else { $process.ExitCode }

$summary = ''
foreach ($name in 'SUMMARY.md', 'last-message.md') {
    $path = Join-Path $orchDir $name
    if (-not $summary -and (Test-Path $path)) { $summary = (Get-Content -Raw -Encoding UTF8 $path).Trim() }
}

$status = if ($timedOut) { 'TIMEOUT' } elseif ($exitCode -ne 0) { 'FAILED' } elseif (-not $summary) { 'NO_SUMMARY' } else { 'OK' }

$commitCount = [int](git -C $worktree rev-list --count "$headBefore..HEAD")
if ($commitCount -gt 0) { $warnings += "WARNING: $commitCount commits on branch" }
if ((Get-MainTreeSnapshot) -ne $mainTreeBefore) { $warnings += 'WARNING: main tree changed (the agent, the user, or a parallel run touched it)' }

Invoke-GitQuiet -C $worktree add -N .
$diffStat = (Invoke-GitQuiet -C $worktree diff --stat $baseCommit) -join "`n"

$summarySection = @()
if ($summary) { $summarySection += $summary }
if ($status -ne 'OK') { $summarySection += '--- last log lines ---'; $summarySection += Get-LogTail }

$result = @(
    "Status: $status"
    "Agent: $Agent  Model: $Model  Exit: $exitCode  Duration: $($stopwatch.Elapsed.ToString('mm\:ss'))"
    "Worktree: $worktree  Branch: $branch"
    "Base: $baseCommit"
) + $warnings + @('## Summary') + $summarySection + @('## Diff', $(if ($diffStat) { $diffStat } else { '(no changes)' }))

$resultPath = Join-Path $orchDir 'RESULT.md'
Set-Content -Path $resultPath -Value $result -Encoding utf8
$resultPath
