<?php

namespace App\Console\Commands;

use App\Settings\GoLiveCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shop:check')]
#[Description('List what is still missing before the shop may sell (seller details, legal texts, payment, mail)')]
class ShopCheck extends Command
{
    public function handle(): int
    {
        $problems = GoLiveCheck::problems();

        if ($problems === []) {
            $this->components->info('Nothing missing.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        return self::FAILURE;
    }
}
