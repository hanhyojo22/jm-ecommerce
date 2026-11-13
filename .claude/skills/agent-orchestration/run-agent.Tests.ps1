# Plain assert tests for run-agent.ps1 (no Pester). Run: powershell -NoProfile -File run-agent.Tests.ps1
$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'run-agent.ps1'
$msg = 'Read .orch/PROMPT.md and complete the task. Write your summary to .orch/SUMMARY.md.'
$script:failures = 0
$script:passes = 0

function Assert([bool]$cond, [string]$name) {
    if ($cond) { $script:passes++; Write-Host "PASS $name" }
    else { $script:failures++; Write-Host "FAIL $name" -ForegroundColor Red }
}

function New-TempRepo {
    $root = Join-Path $env:TEMP ("orch test " + [guid]::NewGuid().ToString('N').Substring(0, 6))
    $repo = Join-Path $root 'orch test repo'
    New-Item -ItemType Directory -Force $repo | Out-Null
    git -C $repo init -q
    git -C $repo config user.email t@t
    git -C $repo config user.name t
    Set-Content -Path (Join-Path $repo 'README.md') -Value 'x' -Encoding ascii
    git -C $repo add .
    git -C $repo commit -qm init
    return $repo
}

function Remove-TempRepo([string]$repo) {
    Remove-Item -Recurse -Force (Split-Path $repo -Parent) -ErrorAction SilentlyContinue
}

$prompt = Join-Path $env:TEMP 'orch-test-prompt.md'
Set-Content -Path $prompt -Value "Line one`r`nLine `"two`" with 'quotes' & spaces" -Encoding utf8

$repo = New-TempRepo
try {
    $expectedWt = Join-Path (Split-Path $repo -Parent) 'orch test repo-orch\fix-cart-codex'

    $r = & $runner -Agent codex -Model gpt-6.1-sol -Task fix-cart -PromptFile $prompt -RepoRoot $repo -DryRun | ConvertFrom-Json
    Assert ($r.exe -eq 'codex') 'codex dry run: exe'
    $a = @($r.args)
    Assert (($a[0] -eq 'exec') -and ($a -contains '-C') -and ($a -contains $expectedWt) -and ($a -contains '-m') -and ($a -contains 'gpt-6.1-sol') -and ($a -contains '-s') -and ($a -contains 'workspace-write') -and ($a -contains '-o')) 'codex dry run: args'
    Assert ($a[-1] -eq $msg) 'codex dry run: ends with fixed message'

    Assert ($r.worktree -eq $expectedWt) 'worktree path'
    Assert ($r.branch -eq 'orch/fix-cart-codex') 'branch name'

    $r = & $runner -Agent opencode -Model deepseek/deepseek-v4-pro -Task t -PromptFile $prompt -RepoRoot $repo -DryRun | ConvertFrom-Json
    Assert ($r.exe -like '*opencode.cmd') 'opencode dry run: exe'
    Assert ((@($r.args) -join '|') -eq "run|--auto|-m|deepseek/deepseek-v4-pro|$msg") 'opencode dry run: args'
    Assert ($r.workingDir -eq $r.worktree) 'opencode dry run: workingDir'

    $r = & $runner -Agent agy -Model gemini-4-argon-high -Task t -PromptFile $prompt -RepoRoot $repo -DryRun | ConvertFrom-Json
    Assert ((@($r.args) -join '|') -eq "-p|$msg|--mode|auto|--model|gemini-4-argon-high") 'agy dry run: args'
    Assert ($r.workingDir -eq $r.worktree) 'agy dry run: workingDir'
    Assert (-not (Test-Path (Split-Path $expectedWt -Parent))) 'dry run creates nothing'

    $wt = & $runner -Agent codex -Model m -Task space -PromptFile $prompt -RepoRoot $repo -SetupOnly
    $copied = Join-Path $wt '.orch\PROMPT.md'
    Assert ((Test-Path $copied) -and ((Get-FileHash $copied).Hash -eq (Get-FileHash $prompt).Hash)) 'spaces in repo path: prompt copied byte for byte'
    Assert ((git -C $repo branch --list 'orch/space-codex') -ne $null) 'spaces in repo path: branch created'
    Assert ([string]::IsNullOrEmpty((git -C $wt status --porcelain))) '.orch is excluded from git'

    Set-Content -Path (Join-Path $wt 'keep.txt') -Value 'mine' -Encoding ascii
    $threw = $null
    try { & $runner -Agent codex -Model m -Task space -PromptFile $prompt -RepoRoot $repo -SetupOnly | Out-Null } catch { $threw = $_.Exception.Message }
    Assert ($threw -like '*branch orch/space-codex already exists*') 'existing branch: throws'
    Assert ((Get-Content (Join-Path $wt 'keep.txt')) -eq 'mine') 'existing branch: worktree untouched'

    $threw = $null
    try { & $runner -Agent foo -Model m -Task t -PromptFile $prompt -RepoRoot $repo -DryRun | Out-Null } catch { $threw = $_ }
    Assert ($threw -ne $null) 'bad agent rejected'
}
finally {
    Remove-TempRepo $repo
}

Write-Host "`n$script:passes passed, $script:failures failed"
if ($script:failures -gt 0) { exit 1 }
