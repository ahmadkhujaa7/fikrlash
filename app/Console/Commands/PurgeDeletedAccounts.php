<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\AccountService;
use Illuminate\Console\Command;

class PurgeDeletedAccounts extends Command
{
    protected $signature = 'accounts:purge-deleted';

    protected $description = 'Grace period tugagan o‘chirilgan akkauntlarni butunlay o‘chiradi';

    public function handle(AccountService $accounts): int
    {
        $count = 0;
        User::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays(config('fikrlash.accounts.deletion_grace_days')))
            ->chunkById(100, function ($users) use ($accounts, &$count) {
                foreach ($users as $user) {
                    $accounts->purge($user);
                    $count++;
                }
            });

        $this->info("Butunlay o‘chirildi: {$count} ta akkaunt");

        return self::SUCCESS;
    }
}
