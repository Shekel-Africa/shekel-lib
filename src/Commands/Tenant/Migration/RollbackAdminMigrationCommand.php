<?php

namespace Shekel\ShekelLib\Commands\Tenant\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class RollbackAdminMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:admin:rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback admin migration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('migrate:rollback', array_merge(Arr::prependKeysWith($this->options(), '--'), [
            '--path' => '/database/migrations/admin',
            '--database' => 'admin'
        ]));
        return Command::SUCCESS;
    }
}
