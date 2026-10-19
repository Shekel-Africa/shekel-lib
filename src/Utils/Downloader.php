<?php

namespace Shekel\ShekelLib\Utils;

use RuntimeException;

abstract class Downloader {
    public $collection, $extension;
    public function __construct(array $collection, $extension)
    {
        $this->collection = $collection;
        $this->extension = $extension;
    }

    abstract public function generateReport($title, $save=false);

    public function generateFileName($title) {
        return $title."_".date('d-m-Y').'.'.$this->extension;
    }

    /**
     * Create a private, uniquely named file in the system temp directory to
     * build an export in. Exports must never be written relative to the
     * process working directory: under PHP-FPM that is the service's public/
     * web root, which made every export downloadable by anyone.
     */
    protected function createTempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'shekel-export-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file for the export');
        }
        @chmod($path, 0600);

        return $path;
    }
}
