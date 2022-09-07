<?php 

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class LoanService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'loan');
    }

    public function getApprovedLoanOffer($loan_id) {
        $url = "/$loan_id/offer";
        return $this->client->get($url);
    }
}