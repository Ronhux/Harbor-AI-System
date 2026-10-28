$ErrorActionPreference = 'Stop'
$root = 'C:\HarborAI_QA_PHASE5\app'
if (!(Test-Path -LiteralPath $root -PathType Container)) { throw "BLOCKED: QA application copy absent: $root" }
$cursor = $root
while ($cursor) {
    if ((Get-Item -LiteralPath $cursor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "BLOCKED: linked ancestor $cursor" }
    $parent = Split-Path -Parent $cursor; if ($parent -eq $cursor) { break }; $cursor = $parent
}
# Do not recurse through reparse points; inspect each directory before descending.
function Check-Tree([string]$path) {
    foreach ($e in Get-ChildItem -LiteralPath $path -Force) {
        if ($e.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "BLOCKED: linked QA entry $($e.FullName)" }
        if ($e.PSIsContainer) { Check-Tree $e.FullName }
        else {
            # NTFS hard links bypass symlink detection. File-specific hardlink inventory is read-only.
            $links = @(& fsutil hardlink list $e.FullName 2>$null)
            if ($LASTEXITCODE -ne 0) { throw 'BLOCKED: unable to verify NTFS hard links' }
            if ($links.Count -ne 1) { throw "BLOCKED: hard-linked QA file $($e.FullName)" }
        }
    }
}
foreach ($p in @('storage','public\storage','bootstrap\cache','config')) {
    $full = Join-Path $root $p
    if (!(Test-Path -LiteralPath $full -PathType Container)) { throw "BLOCKED: missing $p" }
    if ((Get-Item -LiteralPath $full).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "BLOCKED: linked $p" }
    Check-Tree $full
}
foreach ($name in @('.env','bootstrap\app.php')) {
    $file=Join-Path $root $name
    if (!(Test-Path -LiteralPath $file -PathType Leaf)) { throw "BLOCKED: missing $name" }
    if ((Get-Item -LiteralPath $file).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "BLOCKED: linked $name" }
    $links=@(& fsutil hardlink list $file 2>$null)
    if ($LASTEXITCODE -ne 0 -or $links.Count -ne 1) { throw "BLOCKED: unverifiable or hard-linked $name" }
}
Write-Output 'WINDOWS RUNTIME PATH CHECK PASSED. Follow with PHP resolved-configuration/database preflight. This is not write authorization.'
