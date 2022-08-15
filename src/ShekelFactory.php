<?php

namespace Shekel\ShekelLib;

use Shekel\ShekelLib\Services\CarService;
use Shekel\ShekelLib\Services\AuthService;
use Shekel\ShekelLib\Services\LoanService;
use Shekel\ShekelLib\Services\TransactionService;
use Shekel\ShekelLib\Services\UploadService;

class ShekelFactory {
    private $token; 

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function getService($name) {
        switch ($name) {
            case 'auth':
                return new AuthService($this->token);
                break;

            case 'cars':
                return new CarService($this->token);
                break;

            case 'loans':
                return new LoanService($this->token);
                break;

            case 'uploads':
                return new UploadService($this->token);
                break;

            case 'transactions':
                return new TransactionService($this->token);
                break;
            
            default:
                # code...
                break;
        }
    }
}