<?php 

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class TransactionService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'transaction');
    }

    public function createWallet($currency='NGN') {
        $url ="/wallet";
        return $this->client->post($url, [
            'currency' => $currency
        ]);
    }
}