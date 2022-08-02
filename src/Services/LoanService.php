<?php 

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class LoanService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'loan');
    }
}