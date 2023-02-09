<?php

namespace Shekel\ShekelLib\Utils;

class DownloadReport {

    public static function download(array $collection, $title, $type) {
        switch ($type) {
            case 'csv':
                $downloader = new CsvDownloader($collection);
                break;
            case 'xls':
                $downloader = new XlsDownloader($collection);
                break;
            case 'pdf':
                $downloader = new PdfDownloader($collection);
                break;
            default:
                abort(400, "Type not currently supported");
                break;
        }

        return $downloader->generateReport($title);
    }

}