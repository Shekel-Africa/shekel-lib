<?php

namespace Shekel\ShekelLib\Services\v3;

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

    public function getTransaction($id, $query=[]){
        $url = "/transaction/$id";
        return $this->handleRequest($this->client->get($url, $query));
    }

    public function createProvidusAccount($email, $bvn, $user_id=null, $details=[], $provider='flw') {
        $url = match($provider) {
            'lenco' => "/wallet/link/lenco",
            default => "/wallet/link/bank"
        };

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

    public function listAdminWallets() {
        $url = '/admin/wallet';
        return $this->handleRequest($this->client->get($url));
    }

    public function requiresConsent($id, $type=null) {
        $url = "/requires-consent/$id";
        if (isset($type)) {
            $url = "$type/requires-consent/$id";
        }
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
        $url = "/admin/lending-partner/create";
        return $this->handleRequest($this->client->post($url, $data));
    }
    public function editLendingPartner(string $id, array $data) {
        $url = "/admin/lending-partner/$id/edit";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function generateWalletForUser($id, $type, $currency='NGN', $userType=null) {
        $url = "/wallet/create";
        $data = [
            'owner_id' => $id,
            'owner_type' => $type,
            'currency' => $currency
        ];
        if (isset($userType)) {
            $data['type'] = $userType;
        }
        return $this->handleRequest($this->client->post($url, $data));
    }

    /**
     * @param array{id:string,type:string,user_id:string,bank:string,account_number:string,account_name:string}$data
     * @param array $additional_info
     * @return mixed
     */
    public function addDefaultPayoutToWallet(array $data, array $additional_info = [], bool $isInternational=false) {
        $url = "/partner/bank/default";
        return $this->handleRequest($this->client->post($url, [
            'owner_id' => $data['id'],
            'owner_type' => $data['type'],
            'user_id' => $data['user_id'],
            'bank' => $data['bank'],
            'account_number' => $data['account_number'],
            'account_name' => $data['account_name'],
            'is_international' => $isInternational,
            'additional_info' => $additional_info
        ]));
    }

    public function creditWalletBonus($user_id, int $amount) {
        $url = "/wallet/bonus/credit";
        return $this->handleRequest($this->client->post($url, [
            'user_id' => $user_id,
            'amount' => $amount
        ]));
    }

    public function listPartnerSlugs($currency=null) {
        $url = "/admin/lending-partner/slugs";
        return $this->handleRequest($this->client->get($url, [
            'currency' => $currency
        ]));
    }

    public function initiateVfdAccountCreation($userId) {
        $url = '/admin/lending-partner/vfd/account/initiate';
        return $this->handleRequest($this->client->post($url, [
            'user_id' => $userId
        ]));
    }
}
