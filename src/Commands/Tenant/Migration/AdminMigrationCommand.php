<?php

namespace Shekel\ShekelLib\Commands\Tenant\Migration;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Arr;
use Shekel\ShekelLib\Commands\Traits\TenantConnectionTrait;

class AdminMigrationCommand extends Command
{
    use ConfirmableTrait, TenantConnectionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:admin {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--pretend : Dump the SQL queries that would be run}
                {--seed : Indicates if the seed task should be re-run}
                {--seeder= : The class name of the root seeder}
                {--step : Force the migrations to be run so they can be rolled back individually}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Admin database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('migrate', array_merge(Arr::prependKeysWith($this->options(), '--'),
            [
                '--path' => '/database/migrations/admin',
                '--database' => 'admin',
            ]));
        return Command::SUCCESS;
    }
}
