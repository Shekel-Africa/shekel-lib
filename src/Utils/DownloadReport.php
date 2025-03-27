<?php

namespace Shekel\ShekelLib\Utils;

class DownloadReport {

    public static function download(array $collection, $title, $type, $save=false) {
        $extension = strtolower($type);
        switch ($extension) {
            case 'csv':
                $downloader = new CsvDownloader($collection, $extension);
                break;
            case 'xls':
                $downloader = new XlsDownloader($collection, $extension);
                break;
            case 'pdf':
                $downloader = new PdfDownloader($collection, $extension);
                break;
            default:
                abort(400, "Type not currently supported");
                break;
        }

        return $downloader->generateReport($title, $save);
    }

}