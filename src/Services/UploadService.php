<?php 

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class UploadService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'upload');
    }
}