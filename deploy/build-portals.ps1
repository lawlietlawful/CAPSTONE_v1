# Builds the Teacher and Student portals for the server, on your Windows PC.
#
#   .\deploy\build-portals.ps1 -Domain portal.your-school.edu.ph
#
# Output: deploy\out\teacher and deploy\out\student. Upload both folders to
# /var/www/portals/ on the server (see DEPLOYMENT.md, step 7).
param(
    [Parameter(Mandatory = $true)]
    [string]$Domain
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$out = Join-Path $PSScriptRoot 'out'
$api = "https://$Domain/api"

foreach ($app in @(@{ Dir = 'TeacherPortal'; Path = 'teacher' }, @{ Dir = 'StudentPortal'; Path = 'student' })) {
    Write-Host "Building $($app.Dir) for https://$Domain/$($app.Path)/ ..."
    Push-Location (Join-Path $root $app.Dir)
    try {
        flutter build web --release --no-web-resources-cdn `
            --base-href "/$($app.Path)/" `
            --dart-define="API_BASE_URL=$api"
        if ($LASTEXITCODE -ne 0) { throw "flutter build failed for $($app.Dir)" }

        $dest = Join-Path $out $app.Path
        if (Test-Path $dest) { Remove-Item -Recurse -Force $dest }
        New-Item -ItemType Directory -Force $dest | Out-Null
        Copy-Item -Recurse (Join-Path 'build\web' '*') $dest
    }
    finally {
        Pop-Location
    }
}

Write-Host ""
Write-Host "Done. Upload these to /var/www/portals/ on the server:"
Write-Host "  $out\teacher"
Write-Host "  $out\student"
