<?php 

namespace Shekel\ShekelLib;

use Shekel\ShekelLib\ShekelBaseService;

class UploadService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'upload');
    }
}