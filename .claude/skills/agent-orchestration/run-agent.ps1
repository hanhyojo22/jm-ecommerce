<#
.SYNOPSIS
Runs one coding agent (Codex, OpenCode, Antigravity) on a task in its own git worktree and writes .orch\RESULT.md.

.EXAMPLE
powershell -NoProfile -File run-agent.ps1 -Agent codex -Model gpt-6.1-sol -Task fix-cart -PromptFile C:\tmp\fix-cart.md
#>
param(
    [Parameter(Mandatory)][ValidateSet('codex', 'opencode', 'agy', 'fake')][string]$Agent,
    [Parameter(Mandatory)][string]$Model,
    [Parameter(Mandatory)][ValidatePattern('^[a-z0-9][a-z0-9-]*$')][string]$Task,
    [Parameter(Mandatory)][string]$PromptFile,
    [double]$TimeoutMinutes = 30,
    [string]$RepoRoot,
    [switch]$DryRun,
    [switch]$SetupOnly
)

$ErrorActionPreference = 'Stop'
$message = 'Read .orch/PROMPT.md and complete the task. Write your summary to .orch/SUMMARY.md.'

if (-not $RepoRoot) { $RepoRoot = git rev-parse --show-toplevel }
$RepoRoot = (Resolve-Path $RepoRoot).Path
$PromptFile = (Resolve-Path $PromptFile).Path

$repoName = Split-Path $RepoRoot -Leaf
$worktree = Join-Path (Split-Path $RepoRoot -Parent) "$repoName-orch\$Task-$Agent"
$branch = "orch/$Task-$Agent"
$orchDir = Join-Path $worktree '.orch'

switch ($Agent) {
    'codex' {
        $exe = 'codex'
        $agentArgs = @('exec', '-C', $worktree, '-m', $Model, '-s', 'workspace-write', '-o', (Join-Path $orchDir 'SUMMARY.md'), $message)
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

git -C $RepoRoot rev-parse --verify --quiet "refs/heads/$branch" | Out-Null
if ($LASTEXITCODE -eq 0) { throw "branch $branch already exists; remove it or pick another -Task" }
if (Test-Path $worktree) { throw "worktree path $worktree already exists" }

git -C $RepoRoot worktree add --quiet -b $branch $worktree
if ($LASTEXITCODE -ne 0) { throw "git worktree add failed for $worktree" }

$exclude = Join-Path (git -C $RepoRoot rev-parse --path-format=absolute --git-common-dir) 'info\exclude'
if (-not ((Test-Path $exclude) -and (Select-String -Path $exclude -SimpleMatch -Pattern '.orch/' -Quiet))) {
    Add-Content -Path $exclude -Value '.orch/' -Encoding ascii
}

New-Item -ItemType Directory -Force $orchDir | Out-Null
Copy-Item -LiteralPath $PromptFile -Destination (Join-Path $orchDir 'PROMPT.md')

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
        Where-Object { Test-Path $_ } | ForEach-Object { Get-Content $_ -Tail 40 }
}

$mainTreeBefore = (git -C $RepoRoot status --porcelain) -join "`n"
$baseCommit = git -C $worktree rev-parse HEAD
$stopwatch = [Diagnostics.Stopwatch]::StartNew()

$process = Start-Process -FilePath $exe -ArgumentList (ConvertTo-ArgumentString $agentArgs) -WorkingDirectory $worktree `
    -NoNewWindow -PassThru `
    -RedirectStandardOutput (Join-Path $orchDir 'agent.log') -RedirectStandardError (Join-Path $orchDir 'agent.err.log')
$null = $process.Handle # caches the handle so ExitCode is readable after exit

$timedOut = -not $process.WaitForExit([int]($TimeoutMinutes * 60000))
if ($timedOut) {
    taskkill /T /F /PID $process.Id | Out-Null
    $process.WaitForExit()
}
$stopwatch.Stop()
$exitCode = if ($timedOut) { 'killed' } else { $process.ExitCode }

$summaryPath = Join-Path $orchDir 'SUMMARY.md'
$summary = if (Test-Path $summaryPath) { (Get-Content -Raw $summaryPath).Trim() } else { '' }

$status = if ($timedOut) { 'TIMEOUT' } elseif ($exitCode -ne 0) { 'FAILED' } elseif (-not $summary) { 'NO_SUMMARY' } else { 'OK' }

$warnings = @()
$commitCount = [int](git -C $worktree rev-list --count "$baseCommit..HEAD")
if ($commitCount -gt 0) { $warnings += "WARNING: $commitCount commits on branch" }
if (((git -C $RepoRoot status --porcelain) -join "`n") -ne $mainTreeBefore) { $warnings += 'WARNING: main tree changed' }

git -C $worktree add -N . 2>$null
$diffStat = (git -C $worktree diff --stat $baseCommit) -join "`n"

$summarySection = @()
if ($summary) { $summarySection += $summary }
if ($status -ne 'OK') { $summarySection += "--- last log lines ---"; $summarySection += Get-LogTail }

$result = @(
    "Status: $status"
    "Agent: $Agent  Model: $Model  Exit: $exitCode  Duration: $($stopwatch.Elapsed.ToString('mm\:ss'))"
    "Worktree: $worktree  Branch: $branch"
) + $warnings + @('## Summary') + $summarySection + @('## Diff', $(if ($diffStat) { $diffStat } else { '(no changes)' }))

$resultPath = Join-Path $orchDir 'RESULT.md'
Set-Content -Path $resultPath -Value $result -Encoding utf8
$resultPath
