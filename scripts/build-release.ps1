# Build a distributable ZIP for GitHub Release / Freemius upload.
# Usage: .\scripts\build-release.ps1 [-Version 1.0.0]

param(
	[string]$Version = ''
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$pluginSlug = 'phoenix-wp-gift'

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
$stageDir = Join-Path $distDir $pluginSlug
$zipPath = Join-Path $distDir "$pluginSlug-$Version.zip"

if (Test-Path $distDir) {
	Remove-Item -Recurse -Force $distDir
}
New-Item -ItemType Directory -Path $stageDir -Force | Out-Null

$excludePattern = '(\\\.git\\|\\\.github\\|\\dist\\|\\scripts\\|composer\.lock$|composer\.phar$|wp-cli\.phar$|\.DS_Store$)'

Get-ChildItem -Path $root -Force | Where-Object {
	$name = $_.Name
	$name -notin @('.git', '.github', 'dist', 'scripts', 'composer.lock', 'composer.phar', 'wp-cli.phar', '.DS_Store')
} | ForEach-Object {
	Copy-Item -Path $_.FullName -Destination $stageDir -Recurse -Force
}

# Drop Freemius SDK dev-only folders from the distribution ZIP.
$freemiusDevPaths = @(
	(Join-Path $stageDir 'includes\freemius\.github'),
	(Join-Path $stageDir 'includes\freemius\gulptasks'),
	(Join-Path $stageDir 'includes\freemius\.phpstan')
)
foreach ($path in $freemiusDevPaths) {
	if (Test-Path $path) {
		Remove-Item -Recurse -Force $path
	}
}

if (Test-Path $zipPath) {
	Remove-Item -Force $zipPath
}

# Freemius rejects PowerShell Compress-Archive ZIPs (backslash paths). Use tar.
Push-Location $distDir
try {
	tar -a -c -f "$pluginSlug-$Version.zip" $pluginSlug
} finally {
	Pop-Location
}

Write-Host "Built $zipPath (tar — forward-slash paths for Freemius)"
