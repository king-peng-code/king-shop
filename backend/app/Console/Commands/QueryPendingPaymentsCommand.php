<?php

namespace App\Console\Commands;

use App\Application\Payment\QueryPendingPayments\QueryPendingPaymentsHandler;
use Illuminate\Console\Command;

class QueryPendingPaymentsCommand extends Command
{
    protected $signature = 'payments:query-pending {--limit=100 : Max payments to query}';

    protected $description = 'Query pending payments from payment providers and confirm successful ones';

    public function handle(QueryPendingPaymentsHandler $handler): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $count = $handler->handle($limit);
        $this->info("Confirmed {$count} payment(s) via active query.");

        return self::SUCCESS;
    }
}
