<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$users = App\Models\User::where('whatsapp_number', 'like', '%85717481973%')->get();
echo "Users with phone 85717481973: " . $users->count() . "\n";
foreach ($users as $u) {
    echo "ID: {$u->id} | Name: {$u->username} | Email: {$u->email} | WA: {$u->whatsapp_number} | Enabled: " . ($u->whatsapp_notifications_enabled ? 'YES' : 'NO') . "\n";
}
