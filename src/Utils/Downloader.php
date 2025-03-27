<?php

namespace Shekel\ShekelLib\Utils;

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
}