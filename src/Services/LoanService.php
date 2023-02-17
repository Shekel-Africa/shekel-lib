<?php

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class LoanService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('loan');
    }

    public function getApprovedLoanOffer($loan_id) {
        $url = "/$loan_id/offer";
        return $this->handleRequest($this->client->get($url));
    }

    public function makeRepayment($loan_id, $amount) {
        $url = "/repayment";
        return $this->handleRequest($this->client->post($url, [
            'loan_id' => $loan_id,
            'amount' => $amount
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

    public function getUsersActiveLoan(array $userIds) {
        $url = "/list/active";
        return $this->handleRequest($this->client->post($url, [
            'user_ids' => $userIds
        ]));
    }
}
