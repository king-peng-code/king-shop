<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereIn('status', ['preparing', 'ready', 'completed'])
            ->update(['status' => 'paid']);
    }

    public function down(): void
    {
        // Legacy statuses removed; cannot restore granular fulfillment states.
    }
};
