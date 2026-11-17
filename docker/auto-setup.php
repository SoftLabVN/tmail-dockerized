<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Domain;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

echo "==> Configuring TMail...\n";

// Domain setup
$domainName = env('DEFAULT_DOMAIN', 'imail.edu.vn');
if (!empty($domainName)) {
    $domain = Domain::firstOrCreate(['domain' => $domainName]);
    echo "==> Default domain ready: " . $domain->domain . "\n";
}

// App Settings
$appName = env('APP_NAME', 'TMail');
Setting::put('name', $appName);
Setting::put('license_key', 'babiato-vilaseca-license-bypass');

$theme = env('DEFAULT_THEME', 'default');
if (!empty($theme)) {
    Setting::put('theme', $theme);
    echo "==> Theme set to: $theme\n";
}

// Engine setup (imap or delivery)
$engine = env('TMAIL_ENGINE', 'imap');
Setting::put('engine', $engine);
echo "==> Mail engine set to: $engine\n";

// Admin user setup
$adminEmail = env('ADMIN_EMAIL', 'anhptt@softlab.vn');
$adminPassword = env('ADMIN_PASSWORD', 'Admin@123456');
$adminName = env('ADMIN_NAME', 'Peter Tuan Anh');

$admin = User::where('email', $adminEmail)->first();
if (!$admin) {
    User::create([
        'name' => $adminName,
        'email' => $adminEmail,
        'password' => Hash::make($adminPassword),
        'role' => 7,
    ]);
    echo "==> Created admin user: $adminEmail\n";
} else {
    $admin->update([
        'name' => $adminName,
        'password' => Hash::make($adminPassword),
        'role' => 7,
    ]);
    echo "==> Updated admin user: $adminEmail\n";
}

// Mark installed
$installedFile = storage_path('installed');
file_put_contents($installedFile, 'TMail successfully installed on ' . date('Y/m/d h:i:sa'));
echo "==> TMail initialization completed successfully!\n";
