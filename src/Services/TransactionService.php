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

    public function createProvidusAccount($email, $bvn, $user_id=null) {
        $url = "/wallet/link/bank";
        $data = [   
            'email' => $email,
            'bvn' => $bvn
        ];
        if (isset($user_id)) {
            $data['user_id'] = $user_id;
        }
        return $this->client->post($url, $data);
    }

    public function updateWalletType($currency='NGN'){
        $url ="/wallet/convert";
        return $this->client->post($url,[
            'currency' => $currency
        ]);
    }

    public function verifyAccount($bank, $account) {
        $url ="/bank/verify";
        return $this->client->post($url, [
            'bank_name' => $bank,
            'account' => $account
        ]);
    }
}