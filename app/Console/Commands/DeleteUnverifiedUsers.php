<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class DeleteUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete-unverified-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete users who haven’t verified their email within 7 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = User::whereNull('email_verified_at')
            ->where('created_at', '<=', now()->subDays(7))
            ->delete();

        $this->info("Total users deleted: {$deleted}");
    }
}
