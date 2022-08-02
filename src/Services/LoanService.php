<?php 

namespace Shekel\ShekelLib;

use Shekel\ShekelLib\ShekelBaseService;

class LoanService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'loan');
    }
}