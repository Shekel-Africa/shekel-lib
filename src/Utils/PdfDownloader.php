<?php

namespace Shekel\ShekelLib\Utils;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfDownloader extends Downloader {
    public function generateReport($title)
    {
        $heading = array_keys($this->collection[0]);
        $data = [
            'heading' => $heading,
            'body' => $this->collection
        ];
        $pdf = Pdf::loadView('vendor.shekel-lib.pdf_view', $data)->setPaper($this->getSize(), 'landscape');
        return $pdf->download($this->generateFileName($title));
    }

    private function getSize() {
        $length = count($this->collection);
        switch ($length) {
            case $length < 7:
                return 'a4';
                break;
            case $length < 10 :
                return 'a3';
                break;
            case $length < 13:
                return 'a2';
                break;
            default:
                return 'a1';
                break;
        }
    }
}
