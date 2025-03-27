<?php

namespace Shekel\ShekelLib\Utils;

use Barryvdh\DomPDF\Facade\Pdf;

class PdfDownloader extends Downloader {
    public function generateReport($title, $save=false)
    {
        $heading = array_keys($this->collection[0]);
        $data = [
            'heading' => $heading,
            'body' => $this->collection
        ];
        $pdf = Pdf::loadView('vendor.shekel-lib.pdf_view', $data)->setPaper($this->getSize($heading), 'landscape');
        if ($save) {
            $fileName = $this->generateFileName($title);
            $pdf->save('./public/'.$fileName);
            return response()->download(public_path($fileName), $this->generateFileName($title));
        }
        return $pdf->download($this->generateFileName($title));
    }

    private function getSize($heading) {
        $length = count($heading);
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
