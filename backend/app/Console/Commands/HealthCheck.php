<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheck extends Command
{
    protected $signature = 'hustle:health {--worker} {--scheduler}';

    protected $description = 'Check database and Redis connectivity';

    public function handle(): int
    {
        try {
            DB::select('SELECT 1');
            Redis::ping();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
