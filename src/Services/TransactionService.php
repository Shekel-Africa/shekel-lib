<?php 

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class TransactionService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'transaction');
    }
}