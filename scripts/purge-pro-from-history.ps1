# Purge Pro PHP from entire git history (public phoenix-wp-gift).
# 1) Remove dedicated Pro paths from every commit
# 2) Replace mixed free/pro src files with current free-tier versions (anchor: HEAD)
#
# Usage:
#   .\scripts\purge-pro-from-history.ps1
#   .\scripts\purge-pro-from-history.ps1 -ForcePush
#
# Creates backup branch backup/pre-pro-history-purge. Requires Git Bash.

param(
	[switch]$ForcePush
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

if (git status --porcelain) {
	throw 'Working tree must be clean before history rewrite.'
}

$gitBash = 'C:\Program Files\Git\bin\bash.exe'
if (-not (Test-Path $gitBash)) {
	throw "Git Bash not found at $gitBash"
}

$anchor = (git rev-parse HEAD).Trim()
Write-Host "Anchor commit (free-tier source): $anchor"

$backupBranch = 'backup/pre-pro-history-purge'
git branch -f $backupBranch HEAD
Write-Host "Backup branch: $backupBranch"

$proPaths = @(
	'src/Admin/Rules_Admin.php',
	'src/Admin/Stats_Admin.php',
	'src/Admin/Tools_Admin.php',
	'src/Frontend/Gift_Choice.php',
	'src/Frontend/Gift_Choice_Rest.php',
	'src/Frontend/Progress_Calculator.php',
	'src/Frontend/Progress_Rest.php',
	'src/Frontend/Progress_Shortcode.php',
	'src/Rules/Audience_Evaluator.php',
	'src/Rules/Cart_Content_Evaluator.php',
	'src/Rules/Condition_Evaluator.php',
	'src/Rules/Gift_Options_Helper.php',
	'src/Rules/Rule_Resolver.php',
	'src/Rules/Rules_Exporter.php',
	'src/Rules/Rules_Importer.php',
	'src/Rules/Schedule_Evaluator.php',
	'src/Rules/Upgrade_Group_Helper.php',
	'src/Stats/Gift_Stats.php'
)

$freeSyncPaths = @(
	'src/Plugin.php',
	'src/Cart/Gift_Handler.php',
	'src/Admin/Menu.php',
	'src/functions.php',
	'src/Rules/Rules_Repository.php',
	'src/Rules/Free_Rule_Evaluator.php'
)

$rmArgs = ($proPaths | ForEach-Object { "git rm --cached --ignore-unmatch `"$_`"" }) -join '; '
$indexFilter = "$rmArgs; true"

$syncLines = ($freeSyncPaths | ForEach-Object {
	$p = $_ -replace '\\', '/'
	"git show $anchor`:$p > `"$p`" 2>/dev/null || rm -f `"$p`""
}) -join '; '
$treeFilter = "$syncLines; true"

$rootUnix = ($root -replace '\\', '/')

Write-Host 'Step 1/2: Removing dedicated Pro paths from all commits...'
$env:FILTER_BRANCH_SQUELCH_WARNING = '1'
& $gitBash -lc "cd '$rootUnix' && git filter-branch --force --index-filter '$indexFilter' --prune-empty --tag-name-filter cat -- --all"

Write-Host 'Step 2/2: Syncing free-tier mixed files from anchor commit...'
& $gitBash -lc "cd '$rootUnix' && git filter-branch --force --tree-filter '$treeFilter' --tag-name-filter cat -- --all"

& $gitBash -lc "cd '$rootUnix' && git for-each-ref --format='delete %(refname)' refs/original | git update-ref --stdin"
git reflog expire --expire=now --all
git gc --prune=now --aggressive

Write-Host ''
Write-Host 'Verification:'
$check = git log --all --name-only --pretty=format: -- src/Admin/Rules_Admin.php
if ($check) {
	throw 'FAIL: Rules_Admin.php still appears in history.'
}
Write-Host 'OK: Rules_Admin.php absent from history.'

if ($ForcePush) {
	Write-Host 'Force-pushing main and tags...'
	git push origin --force --all
	git push origin --force --tags
	Write-Host 'Published. Optional: git branch -D backup/pre-pro-history-purge'
} else {
	Write-Host 'Local rewrite done. Publish with:'
	Write-Host '  git push origin --force --all'
	Write-Host '  git push origin --force --tags'
}
