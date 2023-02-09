<?php

namespace Shekel\ShekelLib\Utils;

use Barryvdh\DomPDF\PDF;

class PdfDownloader extends Downloader {
    use PDF;
    public function generateReport($title)
    {
        $heading = array_keys($this->collection[0]);
        $data = [
            'heading' => $heading,
            'body' => $this->collection
        ];
        $pdfFile = realpath(__DIR__.'/../../resources/views/pdf_view.blade.php');
        $pdf = PDF::loadView($pdfFile, $data);
        return $pdf->download("$title.pdf");
    }
}