<?php

namespace Shekel\ShekelLib\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;


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
        $this->putPermanentEnv('AUTH_SERVICE_SECRET', base64_encode(Str::random(32)));
        $this->putPermanentEnv('CAR_SERVICE_SECRET', base64_encode(Str::random(32)));
        $this->putPermanentEnv('MESSAGING_SERVICE_SECRET', base64_encode(Str::random(32)));
        $this->putPermanentEnv('UPLOAD_SERVICE_SECRET', base64_encode(Str::random(32)));
        $this->putPermanentEnv('TRANSACTION_SERVICE_SECRET', base64_encode(Str::random(32)));
        $this->putPermanentEnv('LOAN_SERVICE_SECRET', base64_encode(Str::random(32)));
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