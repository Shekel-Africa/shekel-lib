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
        $pdf = Pdf::loadView('vendor.shekel-lib.pdf_view', $data);
        return $pdf->download($this->generateFileName($title))->setPaper($this->getSize(), 'landscape');
    }

    private function getSize() {
        $length = count($this->collection);
        switch ($length) {
            case $length < 7:
                return 'a4';
                break;
            case $length < 13 :
                return 'a3';
                break;
            default:
                return 'a2';
                break;
        }
    }
}