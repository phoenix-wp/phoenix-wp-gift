# Build release ZIP and publish to GitHub (Freemius upload is separate - dashboard only).
#
# Usage:
#   .\scripts\publish-release.ps1              # detect version, build, upload GitHub release asset
#   .\scripts\publish-release.ps1 -Version 1.0.0
#   .\scripts\publish-release.ps1 -SkipGitHub  # build + verify only

param(
	[string]$Version = '',
	[switch]$SkipGitHub
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$pluginSlug = 'phoenix-gift-for-woocommerce'
$buildScript = Join-Path $PSScriptRoot 'build-release.ps1'

& $buildScript -Channel Freemius -Version $Version

if ($Version -eq '') {
	$mainFile = Join-Path $root "$pluginSlug.php"
	$content = Get-Content $mainFile -Raw
	if ($content -match "Version:\s*([0-9.]+)") {
		$Version = $Matches[1]
	}
}

$zipPath = Join-Path $root "dist/$pluginSlug-$Version.zip"
if (-not (Test-Path $zipPath)) {
	throw "ZIP not found: $zipPath"
}

$firstEntry = (tar -tf $zipPath | Select-Object -First 1).Trim()
$expected = "$pluginSlug/"
if ($firstEntry -ne $expected) {
	throw "Invalid ZIP root folder '$firstEntry' (expected '$expected')"
}

Write-Host "ZIP verified: $zipPath ($([math]::Round((Get-Item $zipPath).Length / 1KB)) KB)"

if ($SkipGitHub) {
	Write-Host "SkipGitHub set - Freemius: upload this ZIP via Developer Dashboard, Deployment."
	exit 0
}

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
	throw "GitHub CLI (gh) not found. Install gh or use -SkipGitHub."
}

gh release view $Version --repo "phoenix-wp/$pluginSlug" 2>$null
if ($LASTEXITCODE -ne 0) {
	throw "GitHub release tag $Version does not exist. Create it first: gh release create $Version"
}

gh release upload $Version $zipPath --repo "phoenix-wp/$pluginSlug" --clobber

Write-Host ""
Write-Host "GitHub release asset updated: https://github.com/phoenix-wp/$pluginSlug/releases/tag/$Version"
Write-Host ""
Write-Host "Freemius (manual dashboard step):"
Write-Host "  1. https://dashboard.freemius.com - PhoenixWP Gift Product - Deployment"
Write-Host "  2. Delete existing $Version if re-deploying, then Add New Version"
Write-Host "  3. Upload: $zipPath"
Write-Host "  4. Set status to Released"
