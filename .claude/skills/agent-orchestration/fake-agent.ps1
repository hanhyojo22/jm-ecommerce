# Test double for run-agent.ps1. Behaves like an agent CLI; $env:FAKE_MODE picks the behavior.
$expected = 'Read .orch/PROMPT.md and complete the task. Write your summary to .orch/SUMMARY.md.'
if ($args.Count -ne 1 -or $args[0] -ne $expected) {
    Write-Output "fake-agent: bad argv ($($args.Count)): $($args -join ' | ')"
    exit 9
}

switch ($env:FAKE_MODE) {
    'ok' {
        Set-Content hello.txt 'hi' -Encoding ascii
        Set-Content .orch\SUMMARY.md 'Created hello.txt' -Encoding ascii
    }
    'nosummary' { Set-Content hello.txt 'hi' -Encoding ascii }
    'fail' { Write-Output 'not logged in'; exit 3 }
    'hang' { Start-Sleep -Seconds 600 }
    'commit' {
        Set-Content hello.txt 'hi' -Encoding ascii
        git add hello.txt
        git commit -qm 'agent commit'
        Set-Content .orch\SUMMARY.md 'Committed hello.txt' -Encoding ascii
    }
    'maintree' {
        Set-Content (Join-Path $env:FAKE_MAIN_TREE 'stray.txt') 'oops' -Encoding ascii
        Set-Content .orch\SUMMARY.md 'Wrote outside worktree' -Encoding ascii
    }
    default { Write-Output "fake-agent: unknown FAKE_MODE '$env:FAKE_MODE'"; exit 8 }
}
exit 0
