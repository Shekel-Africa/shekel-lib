<?php

namespace Shekel\ShekelLib\Commands\Tenant\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Shekel\ShekelLib\Commands\Traits\TenantConnectionTrait;

class RollbackTenantMigration extends Command
{
    use TenantConnectionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:tenant:rollback
                            {--database= : The database connection to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback tenant migration';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->setTenantConnection();
        $this->call('migrate:rollback', Arr::prependKeysWith($this->options(), '--'));
        return Command::SUCCESS;
    }
}
