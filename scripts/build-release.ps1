# Build distributable ZIPs for Freemius (full) and wordpress.org (free only).
# Usage:
#   .\scripts\build-release.ps1 -Channel Freemius
#   .\scripts\build-release.ps1 -Channel WpOrg

param(
	[string]$Version = '',
	[ValidateSet('Freemius', 'WpOrg')]
	[string]$Channel = 'Freemius'
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
$zipSuffix = if ($Channel -eq 'WpOrg') { '-wporg' } else { '' }
$zipPath = Join-Path $distDir "$pluginSlug-$Version$zipSuffix.zip"

if (-not (Test-Path $distDir)) {
	New-Item -ItemType Directory -Path $distDir -Force | Out-Null
}

$excludeNames = Get-PhoenixWpOrgStageExcludeNames
if ($Channel -eq 'WpOrg') {
	$excludeNames += 'premium'
}

Copy-PhoenixPluginToStage -Root $root -StageDir $stageDir -ExcludeNames $excludeNames

	if ($Channel -eq 'Freemius') {
	Copy-PhoenixPremiumOverlay -Root $root -StageDir $stageDir
	$premiumSrcInStage = Join-Path $stageDir 'premium\src'
	if (Test-Path $premiumSrcInStage) {
		Remove-Item -Recurse -Force $premiumSrcInStage
	}
}

Remove-PhoenixFreemiusDevPaths -StageDir $stageDir
Test-PhoenixFreemiusVendorLayout -StageDir $stageDir
New-PhoenixPluginReleaseZip -StageDir $stageDir -PluginSlug $pluginSlug -ZipPath $zipPath
Test-PhoenixPluginReleaseZip -ZipPath $zipPath -PluginSlug $pluginSlug -RequireDistinctUris:($Channel -eq 'WpOrg')

if ($Channel -eq 'WpOrg') {
	Test-PhoenixWpOrgZipNoPremiumPaths -ZipPath $zipPath -PluginSlug $pluginSlug
}

Write-Host "Built $zipPath (Channel=$Channel, tar forward-slash paths)"
