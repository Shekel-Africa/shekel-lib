<?php

namespace Shekel\ShekelLib\Utils;

abstract class Downloader {
    public $collection;
    public function __construct(array $collection)
    {
        $this->collection = $collection;
    }

    abstract public function generateReport($title);
}