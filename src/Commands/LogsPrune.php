<?php

namespace Shekel\ShekelLib\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;


class LogsPrune extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean Logs, remove all old logs';



    public function handle()
    {
        $logPath = storage_path('logs/');
        $files = File::glob($logPath . '*.log');

        foreach ($files as $file) {
            $lastModified = File::lastModified($file);
            $daysOld = floor((time() - $lastModified) / (60 * 60 * 24));

            if ($daysOld > 7) {
                File::delete($file);
            }
        }
    }

}
