<?php

namespace Shekel\ShekelLib\Commands\Tenant\Passport;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Shekel\ShekelLib\Commands\Traits\TenantConnectionTrait;

class PassportClientTenantCommand extends Command
{
    use TenantConnectionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'passport:tenant:client
            {--database= : Database connection to use}
            {--personal : Create a personal access token client}
            {--password : Create a password grant client}
            {--client : Create a client credentials grant client}
            {--name= : The name of the client}
            {--provider= : The name of the user provider}
            {--redirect_uri= : The URI to redirect to after authorization }
            {--user_id= : The user ID the client should be assigned to }
            {--public : Create a public client (Auth code grant type only) }';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Passport create client in tenant db';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->setTenantConnection();
        $this->call('passport:client', array_merge(
                Arr::prependKeysWith(Arr::except($this->options(), 'database'), '--'))
        );
        return Command::SUCCESS;
    }
}
