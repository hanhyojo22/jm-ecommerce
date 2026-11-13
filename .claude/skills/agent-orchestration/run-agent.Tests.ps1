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
    Set-Content -Path (Join-Path $repo '.gitignore') -Value "vendor/`r`nnode_modules/`r`npublic/build/`r`n.env" -Encoding ascii
    git -C $repo add .
    git -C $repo commit -qm init
    New-Item -ItemType Directory -Force (Join-Path $repo 'vendor') | Out-Null
    Set-Content -Path (Join-Path $repo 'vendor\autoload.php') -Value '<?php' -Encoding ascii
    New-Item -ItemType Directory -Force (Join-Path $repo 'vendor\composer') | Out-Null
    Set-Content -Path (Join-Path $repo 'vendor\composer\installed.json') -Value '{}' -Encoding ascii
    Set-Content -Path (Join-Path $repo '.env') -Value 'APP_KEY=x' -Encoding ascii
    New-Item -ItemType Directory -Force (Join-Path $repo 'public\build') | Out-Null
    Set-Content -Path (Join-Path $repo 'public\build\manifest.json') -Value '{}' -Encoding ascii
    return $repo
}

function Remove-TempRepo([string]$repo) {
    # rmdir /s does not follow junctions, unlike Remove-Item -Recurse in PS 5.1
    cmd /c rmdir /s /q "`"$(Split-Path $repo -Parent)`"" 2>$null
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
    Assert ($a -contains (Join-Path $expectedWt '.orch\last-message.md')) 'codex dry run: -o writes last-message.md, not SUMMARY.md'
    Assert ($a[-1] -eq $msg) 'codex dry run: ends with fixed message'

    Assert ($r.worktree -eq $expectedWt) 'worktree path'
    Assert ($r.branch -eq 'orch/fix-cart-codex') 'branch name'

    $r = & $runner -Agent opencode -Model deepseek/deepseek-v4-pro -Task t -PromptFile $prompt -RepoRoot $repo -DryRun | ConvertFrom-Json
    Assert ($r.exe -like '*opencode.cmd') 'opencode dry run: exe'
    Assert ((@($r.args) -join '|') -eq "run|--auto|-m|deepseek/deepseek-v4-pro|$msg") 'opencode dry run: args'
    Assert ($r.workingDir -eq $r.worktree) 'opencode dry run: workingDir'

    $r = & $runner -Agent agy -Model gemini-4-argon-high -Task t -PromptFile $prompt -RepoRoot $repo -DryRun | ConvertFrom-Json
    Assert ((@($r.args) -join '|') -eq "-p|$msg|--mode|auto|--sandbox|--model|gemini-4-argon-high") 'agy dry run: args include --sandbox'
    Assert ($r.workingDir -eq $r.worktree) 'agy dry run: workingDir'
    Assert (-not (Test-Path (Split-Path $expectedWt -Parent))) 'dry run creates nothing'

    $wt = & $runner -Agent codex -Model m -Task space -PromptFile $prompt -RepoRoot $repo -SetupOnly
    $copied = Join-Path $wt '.orch\PROMPT.md'
    Assert ((Test-Path $copied) -and ((Get-FileHash $copied).Hash -eq (Get-FileHash $prompt).Hash)) 'spaces in repo path: prompt copied byte for byte'
    Assert ((git -C $repo branch --list 'orch/space-codex') -ne $null) 'spaces in repo path: branch created'
    Assert ([string]::IsNullOrEmpty((git -C $wt status --porcelain))) '.orch is excluded from git'
    Assert (Test-Path (Join-Path $wt 'vendor\autoload.php')) 'setup: vendor linked into worktree'
    $buildCopy = Get-Item (Join-Path $wt 'public\build')
    Assert ((Test-Path (Join-Path $wt 'public\build\manifest.json')) -and -not ($buildCopy.Attributes -band [IO.FileAttributes]::ReparsePoint)) 'setup: public/build copied, not linked'
    Assert (-not (Test-Path (Join-Path $wt '.env'))) 'setup: .env not copied by default'
    $envWt = & $runner -Agent codex -Model m -Task withenv -PromptFile $prompt -RepoRoot $repo -SetupOnly -WithEnv
    Assert ((Get-Content (Join-Path $envWt '.env')) -eq 'APP_KEY=x') 'setup: -WithEnv copies .env'

    Set-Content -Path (Join-Path $wt 'keep.txt') -Value 'mine' -Encoding ascii
    $threw = $null
    try { & $runner -Agent codex -Model m -Task space -PromptFile $prompt -RepoRoot $repo -SetupOnly | Out-Null } catch { $threw = $_.Exception.Message }
    Assert ($threw -like '*branch orch/space-codex already exists*') 'existing branch: throws'
    Assert ((Get-Content (Join-Path $wt 'keep.txt')) -eq 'mine') 'existing branch: worktree untouched'

    $threw = $null
    try { & $runner -Agent foo -Model m -Task t -PromptFile $prompt -RepoRoot $repo -DryRun | Out-Null } catch { $threw = $_ }
    Assert ($threw -ne $null) 'bad agent rejected'

    function Invoke-Fake([string]$mode, [double]$timeout = 1) {
        $env:FAKE_MODE = $mode
        $resultPath = & $runner -Agent fake -Model fake-model -Task "mode-$mode" -PromptFile $prompt -RepoRoot $repo -TimeoutMinutes $timeout
        return Get-Content -Raw $resultPath
    }

    $res = Invoke-Fake 'ok'
    Assert ($res.StartsWith('Status: OK')) 'ok: status'
    Assert ($res -match '(?s)## Diff.*hello\.txt') 'ok: diff lists hello.txt'
    Assert ($res -match '(?s)## Summary\s+Created hello\.txt') 'ok: summary included'
    Assert ($res -notmatch 'WARNING') 'ok: no warnings'

    $res = Invoke-Fake 'nosummary'
    Assert ($res.StartsWith('Status: NO_SUMMARY')) 'nosummary: status'

    $res = Invoke-Fake 'fail'
    Assert ($res.StartsWith('Status: FAILED')) 'fail: status'
    Assert ($res -match 'Exit: 3') 'fail: exit code'
    Assert ($res -match '(?s)## Summary.*not logged in') 'fail: log tail in summary'

    $sw = [Diagnostics.Stopwatch]::StartNew()
    $res = Invoke-Fake 'hang' 0.1
    $sw.Stop()
    Assert ($res.StartsWith('Status: TIMEOUT')) 'hang: status'
    Assert ($sw.Elapsed.TotalSeconds -lt 30) 'hang: returns promptly'
    $orphans = @(Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" | Where-Object { $_.CommandLine -like '*fake-agent.ps1*' })
    Assert ($orphans.Count -eq 0) 'hang: no orphan process'

    $res = Invoke-Fake 'commit'
    Assert ($res -match 'WARNING: 1 commits on branch') 'commit: warning'
    Assert ($res -match '(?s)## Diff.*hello\.txt') 'commit: diff includes committed file'

    $env:FAKE_MAIN_TREE = $repo
    $res = Invoke-Fake 'maintree'
    Assert ($res -match 'WARNING: main tree changed') 'maintree: warning'
    Remove-Item (Join-Path $repo 'stray.txt')

    $env:FAKE_MAIN_TREE = $repo
    $res = Invoke-Fake 'vendoredit'
    Assert ($res -match 'WARNING: dependencies changed') 'vendoredit: write through vendor link is flagged'
    Set-Content -Path (Join-Path $repo 'vendor\composer\installed.json') -Value '{}' -Encoding ascii

    $res = Invoke-Fake 'lastmsg'
    Assert ($res.StartsWith('Status: OK') -and ($res -match 'From codex -o')) 'lastmsg: last-message.md used when SUMMARY.md missing'

    $res = Invoke-Fake 'embedded'
    Assert ($res.StartsWith('Status: OK')) 'embedded: git stderr warning does not kill the runner'

    Add-Content (Join-Path $repo 'README.md') 'user edit' -Encoding ascii
    $res = Invoke-Fake 'dirtyedit'
    Assert ($res -match 'WARNING: main tree changed') 'dirtyedit: agent edit to an already-dirty file is caught'
    git -C $repo checkout -q -- README.md

    $env:FAKE_MODE = 'ok'
    $res = Get-Content -Raw (& $runner -Agent fake -Model fake-model -Task 'round' -PromptFile $prompt -RepoRoot $repo)
    Assert ($res -match 'Base: [0-9a-f]{40}') 'result has Base line'
    $env:FAKE_MODE = 'ok2'
    $res = Get-Content -Raw (& $runner -Agent fake -Model fake-model -Task 'round' -PromptFile $prompt -RepoRoot $repo -Continue)
    Assert ($res.StartsWith('Status: OK')) 'continue: status'
    Assert (($res -match 'hello\.txt') -and ($res -match 'hello2\.txt')) 'continue: diff against original base'
    Assert (($res -match 'second round') -and ($res -notmatch 'Created hello\.txt')) 'continue: stale summary cleared'

    $threw = $null
    try { & $runner -Agent fake -Model m -Task 'nope' -PromptFile $prompt -RepoRoot $repo -Continue | Out-Null } catch { $threw = $_.Exception.Message }
    Assert ($threw -like '*no worktree*') 'continue: missing worktree throws'

    $roundWt = Join-Path (Split-Path $repo -Parent) 'orch test repo-orch\round-fake'
    & $runner -Agent fake -Model m -Task 'round' -RepoRoot $repo -Cleanup | Out-Null
    Assert (-not (Test-Path $roundWt)) 'cleanup: worktree removed'
    Assert ((git -C $repo branch --list 'orch/round-fake') -eq $null) 'cleanup: branch deleted'
    Assert (Test-Path (Join-Path $repo 'vendor\autoload.php')) 'cleanup: main vendor untouched'
}
finally {
    Remove-TempRepo $repo
}

Write-Host "`n$script:passes passed, $script:failures failed"
if ($script:failures -gt 0) { exit 1 }
