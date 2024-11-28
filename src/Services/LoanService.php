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

    public function makeRepayment($loan_id, $amount, $isPartial=false, $adminFee=null) {
        $url = "/repayment";
        $data = [
            'loan_id' => $loan_id,
            'amount' => $amount,
            'is_partial' => $isPartial
        ];
        if (isset($adminFee)) {
            $data['adminFee'] = $adminFee;
        }
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function updateRepayment($id, $data) {
        $url = "/repayment/$id";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function getRepayment($id) {
        $url = "/repayment/$id";
        return $this->handleRequest($this->client->get($url));
    }

    public function editLoan($id, $data) {
        $url = "/$id";
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
