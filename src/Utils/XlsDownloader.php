<?php

namespace Shekel\ShekelLib\Utils;

class XlsDownloader extends Downloader {

    public function generateReport($title)
    {
        $filename = "$title.xls";
        $out = fopen($filename, 'w');
        fputcsv($out, array_keys($this->collection[0]));
        foreach($this->collection as $line)
        {
            fputcsv($out, $line, "\t");
        }
        fclose($out);
        $newFileName = $this->generateFileName($title);
        $headers = [
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',   
            'Content-type'        => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=$newFileName",
            'Expires'             => '0',
            'Pragma'              => 'no-cache',
        ];
        return response()->download($filename, $newFileName, $headers);
    }
}