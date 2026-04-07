# Erzeugt git-overview.json im selben Ordner – danach git-dashboard.html im Browser öffnen (am besten über lokalen Server).
$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repoRoot

if (-not (Test-Path (Join-Path $repoRoot '.git'))) {
  Write-Error "Kein Git-Repository gefunden in: $repoRoot"
  exit 1
}

function Git-Out {
  param([string[]]$GitArgs)
  $oldEap = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  $out = & git @GitArgs 2>&1
  $code = $LASTEXITCODE
  $ErrorActionPreference = $oldEap
  if ($code -ne 0) {
    throw "git $($GitArgs -join ' ') beendete sich mit $code`: $out"
  }
  return $out
}

$branch = (Git-Out @('rev-parse', '--abbrev-ref', 'HEAD')).Trim()
$headFull = (Git-Out @('rev-parse', 'HEAD')).Trim()
$headShort = (Git-Out @('rev-parse', '--short', 'HEAD')).Trim()

$statusLines = @(Git-Out @('status', '--porcelain=v1', '-b'))
$branchLine = $statusLines | Where-Object { $_ -match '^##' } | Select-Object -First 1
$porcelainFiles = $statusLines | Where-Object { $_ -notmatch '^##' }

$upstream = $null
$ahead = 0
$behind = 0
if ($branchLine -match '^## (\S+?)(?:\.\.\.(\S+))?(?:\s+\[([^\]]+)\])?') {
  $parsedBranch = $Matches[1]
  if ($Matches.Count -gt 2 -and $Matches[2]) { $upstream = $Matches[2].Trim() }
  $trackInfo = $Matches[3]
  if ($trackInfo) {
    if ($trackInfo -match 'ahead (\d+)') { $ahead = [int]$Matches[1] }
    if ($trackInfo -match 'behind (\d+)') { $behind = [int]$Matches[1] }
  }
}

$statusEntries = @()
foreach ($line in $porcelainFiles) {
  if ([string]::IsNullOrWhiteSpace($line)) { continue }
  $xy = $line.Substring(0, [Math]::Min(2, $line.Length))
  $pathBit = if ($line.Length -gt 3) { $line.Substring(3) } else { '' }
  $statusEntries += @{
    code = $xy
    path = $pathBit
  }
}

$remotesRaw = Git-Out @('remote', '-v')
$remotes = @{}
foreach ($r in $remotesRaw) {
  if ($r -match '^(\S+)\s+(\S+)\s+\((fetch|push)\)$') {
    $name = $Matches[1]
    $url = $Matches[2]
    $kind = $Matches[3]
    if (-not $remotes.ContainsKey($name)) {
      $remotes[$name] = @{ fetch = $null; push = $null }
    }
    $remotes[$name][$kind] = $url
  }
}

$logFormat = '%H%x09%h%x09%s%x09%an%x09%ai'
$logRaw = Git-Out @('log', '-25', "--pretty=format:$logFormat")
$commits = @()
foreach ($row in $logRaw) {
  if ([string]::IsNullOrWhiteSpace($row)) { continue }
  $p = $row -split "`t", 5
  if ($p.Count -ge 5) {
    $commits += @{
      hash      = $p[0]
      shortHash = $p[1]
      subject   = $p[2]
      author    = $p[3]
      date      = $p[4]
    }
  }
}

$stashList = @(Git-Out @('stash', 'list'))

$originUrl = $null
try { $originUrl = (Git-Out @('config', '--get', 'remote.origin.url')).Trim() } catch { }

$obj = [ordered]@{
  generatedAt   = (Get-Date).ToString('o')
  repository    = $repoRoot
  branch        = $branch
  head          = $headFull
  headShort     = $headShort
  upstream      = $upstream
  ahead         = $ahead
  behind        = $behind
  isClean       = ($porcelainFiles.Count -eq 0)
  statusEntries = $statusEntries
  remotes       = $remotes
  originUrl     = $originUrl
  recentCommits = $commits
  stashCount    = $stashList.Count
  stashes       = $stashList
}

$jsonPath = Join-Path $repoRoot 'git-overview.json'
$jsonText = $obj | ConvertTo-Json -Depth 8
$utf8 = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($jsonPath, $jsonText, $utf8)
Write-Host "Geschrieben: $jsonPath" -ForegroundColor Green
