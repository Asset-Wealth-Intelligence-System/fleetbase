<#
.SYNOPSIS
  Provisions ONE low-cost Azure VM running the whole Fleetbase stack (API, console,
  socket server, MySQL, Redis, queue, scheduler, Caddy TLS) with Docker Compose.

.DESCRIPTION
  Creates: resource group, VM (Ubuntu 24.04, Standard_B2s_v2: 2 vCPU / 8 GiB, ~US$28/mo),
  64 GiB Standard SSD OS disk (~US$5/mo), Standard static public IP with a DNS label
  (~US$4/mo), NSG rules for 22/80/443/8000/38000. Fleetbase installs itself via cloud-init.

  Requires: Azure CLI logged in (az login). SSH key generated automatically if missing.
  The VM clones -RepoUrl (your fork, which carries deploy/azure) and runs deploy/azure/fleetbase-azure-setup.sh.

.EXAMPLE
  .\provision-azure.ps1 -DnsLabel fleetbase-unitedops -Location eastus
#>
param(
  [string]$ResourceGroup = "rg-fleetbase-united-ops",
  [string]$Location      = "eastus",
  [string]$VmName        = "vm-fleetbase",
  [string]$VmSize        = "Standard_B2s_v2",
  [Parameter(Mandatory = $true)][string]$DnsLabel,
  [string]$AdminUser     = "azureuser",
  [string]$SshKeyPath    = "$env:USERPROFILE\.ssh\fleetbase_azure",
  [string]$AcmeEmail     = "",
  [string]$AppName       = "Fleetbase",
  [int]$OsDiskGb         = 64,
  [string]$RepoUrl       = "https://github.com/Asset-Wealth-Intelligence-System/fleetbase.git",
  [string]$RepoBranch    = "main"
)
$ErrorActionPreference = "Stop"
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$fqdn = "$DnsLabel.$Location.cloudapp.azure.com"
if (-not $AcmeEmail) { $AcmeEmail = "ops@$fqdn" }

Write-Host "== Fleetbase on Azure ==" -ForegroundColor Cyan
Write-Host "   RG=$ResourceGroup  Location=$Location  Size=$VmSize  FQDN=$fqdn"

# 1. SSH key
if (-not (Test-Path "$SshKeyPath.pub")) {
  New-Item -ItemType Directory -Force (Split-Path $SshKeyPath) | Out-Null
  ssh-keygen -t ed25519 -N '""' -f $SshKeyPath -C "fleetbase-azure" | Out-Null
  Write-Host "   generated SSH key $SshKeyPath"
}

# 2. cloud-init with placeholders filled (scripts embedded base64 to avoid YAML escaping)
$b64 = { param($p) [Convert]::ToBase64String([IO.File]::ReadAllBytes($p)) }
$cloudInit = Get-Content "$here\cloud-init.yaml" -Raw
$cloudInit = $cloudInit.Replace("__CADDYFILE_B64__", (& $b64 "$here\Caddyfile.template"))
$cloudInit = $cloudInit.Replace("__SETUP_B64__",     (& $b64 "$here\fleetbase-azure-setup.sh"))
$cloudInit = $cloudInit.Replace("__FQDN__", $fqdn).Replace("__ACME_EMAIL__", $AcmeEmail).Replace("__APP_NAME__", $AppName).Replace("__ADMIN_USER__", $AdminUser).Replace("__REPO_URL__", $RepoUrl).Replace("__REPO_BRANCH__", $RepoBranch)
$cloudInitPath = Join-Path $env:TEMP "fleetbase-cloud-init-$DnsLabel.yaml"
[IO.File]::WriteAllText($cloudInitPath, $cloudInit.Replace("`r`n", "`n"), (New-Object Text.UTF8Encoding $false))

# 3. Resource group + VM
az group create --name $ResourceGroup --location $Location --output none
Write-Host "   resource group ready"

$existing = az vm show -g $ResourceGroup -n $VmName --query name -o tsv 2>$null
if ($existing) {
  Write-Host "   VM $VmName already exists, skipping create" -ForegroundColor Yellow
} else {
  az vm create `
    --resource-group $ResourceGroup `
    --name $VmName `
    --image "Canonical:ubuntu-24_04-lts:server:latest" `
    --size $VmSize `
    --admin-username $AdminUser `
    --ssh-key-values "$SshKeyPath.pub" `
    --public-ip-sku Standard `
    --public-ip-address-allocation static `
    --public-ip-address-dns-name $DnsLabel `
    --os-disk-size-gb $OsDiskGb `
    --storage-sku StandardSSD_LRS `
    --custom-data $cloudInitPath `
    --nsg-rule SSH `
    --output none
  Write-Host "   VM created"
  az vm open-port -g $ResourceGroup -n $VmName --port "80,443,8000,38000" --priority 1010 --output none
  Write-Host "   ports 80/443/8000/38000 opened"
}

$ip = az vm show -d -g $ResourceGroup -n $VmName --query publicIps -o tsv
Write-Host ""
Write-Host "VM is up. cloud-init is now installing Docker + Fleetbase (5-10 min)." -ForegroundColor Green
Write-Host "  IP      : $ip"
Write-Host "  FQDN    : $fqdn"
Write-Host "  SSH     : ssh -i $SshKeyPath $AdminUser@$fqdn"
Write-Host "  Progress: ssh ... 'sudo tail -f /var/log/fleetbase-setup.log'"
Write-Host "  Console : https://$fqdn"
Write-Host "  API     : https://$fqdn:8000"
