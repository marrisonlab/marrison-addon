$ErrorActionPreference = 'Stop'
$siteRoot = [System.IO.Path]::GetFullPath('C:\Users\Angelo\Local Sites\tesy\app\public')
$auditRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$fixtures = @(
    @{ name = 'marrison-hs-followup-continuous.html'; source = 'scroll-followup\continuous.html' },
    @{ name = 'marrison-hs-followup-snap.html'; source = 'scroll-followup\snap.html' },
    @{ name = 'marrison-hs-followup-frames.html'; source = 'scroll-followup\frames.html' },
    @{ name = 'marrison-hs-followup-entry-frames.html'; source = 'scroll-followup\entry-frames.html' },
    @{ name = 'marrison-hs-followup-proof.html'; source = 'scroll-followup\proof.html' },
    @{ name = 'marrison-remediation-elementor.html'; source = 'remediation\elementor.html' },
    @{ name = 'marrison-remediation-frames.html'; source = 'remediation\frames.html' },
    @{ name = 'marrison-remediation-cookie.html'; source = 'remediation\cookie.html' },
    @{ name = 'marrison-remediation-youtube.com.html'; source = 'remediation\youtube.com.html' },
    @{ name = 'marrison-remediation-preloader.html'; source = 'remediation\preloader.html' },
    @{ name = 'marrison-remediation-destination.html'; source = 'remediation\destination.html' }
)
$verified = foreach ($fixture in $fixtures) {
    $destination = [System.IO.Path]::GetFullPath((Join-Path $siteRoot $fixture.name))
    $source = [System.IO.Path]::GetFullPath((Join-Path $auditRoot $fixture.source))
    if ([System.IO.Path]::GetDirectoryName($destination) -ne $siteRoot) { throw "Unexpected destination: $destination" }
    $sourceHash = (Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash
    $destinationHash = (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash
    if ($sourceHash -ne $destinationHash) { throw "Fixture differs from retained source: $destination" }
    [PSCustomObject]@{ destination = $destination; source = $source; sha256 = $sourceHash; removed = $false }
}
foreach ($fixture in $verified) {
    Remove-Item -LiteralPath $fixture.destination
    $fixture.removed = -not (Test-Path -LiteralPath $fixture.destination)
    if (-not $fixture.removed) { throw "Fixture remains: $($fixture.destination)" }
}
[PSCustomObject]@{ files = @($verified); removed = @($verified).Count; consent_values_restored_in_browser = $true } |
    ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'cleanup.json') -Encoding UTF8
Write-Output "PASS removed $(@($verified).Count) fixtures after exact-path and SHA256 verification; sources retained in audit"
