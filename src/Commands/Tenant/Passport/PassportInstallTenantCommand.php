<?php

namespace Shekel\ShekelLib\Commands\Tenant\Passport;


use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Shekel\ShekelLib\Commands\Traits\TenantConnectionTrait;

class PassportInstallTenantCommand extends Command
{
    use TenantConnectionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passport:tenant:install
                            {--database= : Database connection to use}
                            {--uuids : Use UUIDs for all client IDs}
                            {--force : Overwrite keys they already exist}
                            {--length=4096 : The length of the private key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Passport install in tenant db';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->setTenantConnection();
        $this->call('passport:install', array_merge(
                Arr::prependKeysWith(Arr::except($this->options(), 'database'), '--'))
        );
        return Command::SUCCESS;
    }
}
