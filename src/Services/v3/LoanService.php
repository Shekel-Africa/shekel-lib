<?php

namespace Shekel\ShekelLib\Services\v3;


class LoanService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('loan');
    }

    public function getApprovedLoanOffer($loan_id) {
        $url = "/loans/$loan_id/offer";
        return $this->handleRequest($this->client->get($url));
    }
    public function getItem($id, $extra=[]) {
        $url = "/loans/$id";
        return $this->handleRequest($this->client->get($url));
    }

    public function makeRepayment($loan_id, $amount, $isPartial=false) {
        $url = "/repayment";
        return $this->handleRequest($this->client->post($url, [
            'loan_id' => $loan_id,
            'amount' => $amount,
            'is_partial' => $isPartial
        ]));
    }

    public function updateRepayment($id, $data) {
        $url = "/repayment/$id";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function getRepayment($id) {
        $url = "/repayment/$id";
        return $this->handleRequest($this->client->get($url));
    }

    public function editItem($id, $data) {
        $url = "/loans/$id";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function getUsersActiveLoan(array $userIds) {
        $url = "/list/active";
        return $this->handleRequest($this->client->post($url, [
            'user_ids' => $userIds
        ]));
    }

    public function editCreditLimit($user_id, array $data) {
        $url = '/limit';
        $data['user_id'] = $user_id;
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function getCreditLimit($user_id) {
        $url ="/limit/$user_id";
        return $this->handleRequest($this->client->get($url));
    }
}
