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

    public function getTradeLimit() {
        $url = "/trade/subscription/limit";
        return $this->handleRequest($this->client->get($url));
    }

    public function completeEscrow(string $offer_id) {
        $url = "/trade/dispute/$offer_id/complete";
        return $this->handleRequest($this->client->post($url));
    }

    public function cancelEscrow(string $offer_id) {
        $url = "/trade/dispute/$offer_id/cancel";
        return $this->handleRequest($this->client->post($url));
    }

    public function createLendingPartner(array $data) {
        $url = "/lending-partner/create";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function generateWalletForUser($id, $type, $currency='NGN') {
        $url = "/wallet/create";
        return $this->handleRequest($this->client->post($url, [
            'owner_id' => $id,
            'owner_type' => $type,
            'currency' => $currency
        ]));
    }
}
