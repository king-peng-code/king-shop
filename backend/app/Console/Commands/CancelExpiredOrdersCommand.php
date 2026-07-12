<?php

namespace App\Console\Commands;

use App\Application\Order\CancelExpiredOrders\CancelExpiredOrdersHandler;
use Illuminate\Console\Command;

class CancelExpiredOrdersCommand extends Command
{
    protected $signature = 'orders:cancel-expired';

    protected $description = 'Cancel pending payment orders that exceeded auto cancel timeout';

    public function handle(CancelExpiredOrdersHandler $handler): int
    {
        $count = $handler->handle();
        $this->info("Cancelled {$count} expired order(s).");

        return self::SUCCESS;
    }
}
