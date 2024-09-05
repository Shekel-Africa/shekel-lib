<?php

namespace Shekel\ShekelLib\Commands\Tenant\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Shekel\ShekelLib\Commands\Traits\TenantConnectionTrait;

class TenantMigration extends Command
{
    use TenantConnectionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:tenant
                {--database= : The database connection to use}
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
    protected $description = 'Migrate Tenant database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->setTenantConnection();
        $this->call('migrate', Arr::prependKeysWith($this->options(), '--'));
        return Command::SUCCESS;
    }
}
