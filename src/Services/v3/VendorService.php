<?php

namespace Shekel\ShekelLib\Services\v3;

class VendorService extends ShekelBaseService
{
    public function __construct()
    {
        parent::__construct('vendor');
    }

    /**
     * vendor-api's /v3/vendor/loan-statuses: each loan's assignment status. Up to 1,000 ids per call.
     *
     * @param list<string> $loanIds
     */
    public function loanStatuses(array $loanIds)
    {
        return $this->handleRequest($this->client->post('/vendor/loan-statuses', [
            'loan_ids' => $loanIds,
        ]));
    }

    /**
     * vendor-api's /v3/vendor/assigned-loan-ids: every loan in the tenant whose vehicle is with a
     * shipping partner.
     */
    public function assignedLoanIds()
    {
        return $this->handleRequest($this->client->get('/vendor/assigned-loan-ids'));
    }
}
