<?php

namespace Shekel\ShekelLib\Utils;

use Shekel\ShekelLib\Utils\Downloader;

class CsvDownloader extends Downloader { 

    public function generateReport($title) {
        $filename = "download.csv";
        $out = fopen($filename, 'w');
        fputcsv($out, array_keys($this->collection[0]));
        foreach($this->collection as $line)
        {
            fputcsv($out, $line);
        }
        fclose($out);
        $newFileName = $title.date('d-m-Y').'.csv';
        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',   
            'Content-type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=$newFileName",
            'Expires'             => '0',
            'Pragma'              => 'public',
        ];
        return response()->download($filename, $newFileName, $headers);
    }
}