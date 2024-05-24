<?php

namespace Shekel\ShekelLib\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class GenerateServiceSecret extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shekel:generate-secret';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Shekel Client Secrets';



    public function handle()
    {
        $this->putPermanentEnv('AUTH_SERVICE_SECRET', Hash::make(str_random(8)));
        $this->putPermanentEnv('CAR_SERVICE_SECRET', Hash::make(str_random(8)));
        $this->putPermanentEnv('MESSAGING_SERVICE_SECRET', Hash::make(str_random(8)));
        $this->putPermanentEnv('UPLOAD_SERVICE_SECRET', Hash::make(str_random(8)));
        $this->putPermanentEnv('TRANSACTION_SERVICE_SECRET', Hash::make(str_random(8)));
        $this->putPermanentEnv('LOAN_SERVICE_SECRET', Hash::make(str_random(8)));
    }

    public function putPermanentEnv($key, $value)
    {
        $path = app()->environmentFilePath();

        $escaped = preg_quote('='.env($key), '/');

        file_put_contents($path, preg_replace(
            "/^{$key}{$escaped}/m",
            "{$key}={$value}",
            file_get_contents($path)
        ));
    }
}