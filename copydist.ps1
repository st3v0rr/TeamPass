#Directory where sources are
$from = $PSScriptRoot
$to = $PSScriptRoot + "\dist"

if(Test-Path -Path $to)
{
    Remove-Item -Recurse -Force $to
}
New-Item -ItemType directory -Path $to

$rootExcludes = @(
    "dist",
    "install", 
    ".github", 
    ".gitignore", 
    "changelog.txt", 
    "composer.json", 
    "copydist.ps1", 
    "ftpupload.ps1", 
    "license.md",
    "readme.md",
    "deployTest.ps1",
    "deployProd.ps1")

Get-ChildItem $from | 
    Where-Object { $_.Name -notin $rootExcludes } | 
    Copy-Item -Destination $to -Recurse -Force

$specialExcludes = @(
    "\files\ldap.debug.txt",
    "\includes\sk.php",
    "\includes\teampass-seckey.txt",
    "\includes\config\settings.php",
    "\includes\config\tp.config.php",
    "\includes\libraries\csrfp\libs\csrfp.config.php"
)

foreach($exclude in $specialExcludes) {
  $path = $to + $exclude
  Remove-Item -Path $path -Force
}