<?php

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class TransactionService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('transaction');
    }

    public function createWallet($currency='NGN') {
        $url ="/wallet";
        return $this->handleRequest($this->client->post($url, [
            'currency' => $currency
        ]));
    }

    public function createProvidusAccount($email, $bvn, $user_id=null, $details=[]) {
        $url = "/wallet/link/bank";
        $data = [
            'email' => $email,
            'bvn' => $bvn
        ];
        if (isset($user_id)) {
            $data['user_id'] = $user_id;
        }
        if(!empty($details)) {
            $data['details'] = $details;
        }
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function updateWalletType($currency='NGN'){
        $url ="/wallet/convert";
        return $this->handleRequest($this->client->post($url,[
            'currency' => $currency
        ]));
    }

    public function verifyAccount($bank, $account) {
        $url ="/bank/verify";
        return $this->handleRequest($this->client->post($url, [
            'bank_name' => $bank,
            'account' => $account
        ]));
    }

    public function listWallets() {
        $url = '/wallet';
        return $this->handleRequest($this->client->get($url));
    }

    public function requiresConsent($id) {
        $url = "/requires-consent/$id";
        return $this->handleRequest($this->client->get($url));
    }

    public function getActiveSubscription() {
        $url = "/trade/subscription/active";
        return $this->handleRequest($this->client->get($url));
    }
}
