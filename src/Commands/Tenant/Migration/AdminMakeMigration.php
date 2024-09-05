<?php

namespace Shekel\ShekelLib\Commands\Tenant\Migration;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Arr;
use Shekel\ShekelLib\Commands\Traits\TenantConnectionTrait;

class AdminMakeMigration extends Command implements PromptsForMissingInput
{
    use TenantConnectionTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:migration:admin {name : The name of the migration}
        {--database= : The database connection to use}
        {--create= : The table to be created}
        {--table= : The table to migrate}
        {--path= : The location where the migration file should be created}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        {--fullpath : Output the full path of the migration (Deprecated)}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make migration in admin folder';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->setTenantConnection();
        $this->call('make:migration', array_merge(
            Arr::prependKeysWith(Arr::except($this->options(), ['database', 'name']), '--'),
            $this->arguments(),
            [
                '--path' => '/database/migrations/admin',
            ]
        ));
        return Command::SUCCESS;
    }
}
