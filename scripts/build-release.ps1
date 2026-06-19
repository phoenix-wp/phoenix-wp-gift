# Build a distributable ZIP for GitHub Release / Freemius / wordpress.org submit.
# Usage: .\scripts\build-release.ps1 [-Version 1.0.0]

param(
	[string]$Version = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$pluginSlug = 'phoenix-gift-for-woocommerce'
$coreHelpers = Join-Path (Split-Path -Parent $root) 'phoenix-wp-core\scripts\wp-org-release-helpers.ps1'
if (-not (Test-Path $coreHelpers)) {
	throw "Missing shared helpers: $coreHelpers"
}
. $coreHelpers

if ($Version -eq '') {
	$mainFile = Join-Path $root "$pluginSlug.php"
	$content = Get-Content $mainFile -Raw
	if ($content -match "Version:\s*([0-9.]+)") {
		$Version = $Matches[1]
	} else {
		throw "Could not detect plugin version in $mainFile"
	}
}

$distDir = Join-Path $root 'dist'
$stageDir = Join-Path $env:TEMP $pluginSlug
$zipPath = Join-Path $distDir "$pluginSlug-$Version.zip"

if (-not (Test-Path $distDir)) {
	New-Item -ItemType Directory -Path $distDir -Force | Out-Null
}

Copy-PhoenixPluginToStage -Root $root -StageDir $stageDir -ExcludeNames (Get-PhoenixWpOrgStageExcludeNames)
Remove-PhoenixFreemiusDevPaths -StageDir $stageDir
Test-PhoenixFreemiusVendorLayout -StageDir $stageDir
New-PhoenixPluginReleaseZip -StageDir $stageDir -PluginSlug $pluginSlug -ZipPath $zipPath
Test-PhoenixPluginReleaseZip -ZipPath $zipPath -PluginSlug $pluginSlug -RequireDistinctUris

Write-Host "Built $zipPath (tar, forward-slash paths for Freemius/wp.org)"
