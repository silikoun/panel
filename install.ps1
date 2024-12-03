# Create directories
New-Item -ItemType Directory -Force -Path "C:\php"
New-Item -ItemType Directory -Force -Path "C:\composer"

# Download PHP
$phpUrl = "https://windows.php.net/downloads/releases/latest/php-8.2-nts-Win32-vs16-x64-latest.zip"
$phpZip = "C:\php\php.zip"
Write-Host "Downloading PHP..."
Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip

# Extract PHP
Write-Host "Extracting PHP..."
Expand-Archive -Path $phpZip -DestinationPath "C:\php" -Force

# Download Composer installer
$composerUrl = "https://getcomposer.org/Composer-Setup.exe"
$composerInstaller = "C:\composer\composer-setup.exe"
Write-Host "Downloading Composer..."
Invoke-WebRequest -Uri $composerUrl -OutFile $composerInstaller

# Add PHP to PATH
$currentPath = [Environment]::GetEnvironmentVariable("Path", "User")
if ($currentPath -notlike "*C:\php*") {
    [Environment]::SetEnvironmentVariable("Path", $currentPath + ";C:\php", "User")
}

Write-Host "Installation files downloaded!"
Write-Host "Please run the Composer installer at: $composerInstaller"
Write-Host "After installation, restart your terminal and run:"
Write-Host "cd C:\Users\asus\CascadeProjects\woo-dashboard-supabase"
Write-Host "composer install"
Write-Host "php -S localhost:7845"
