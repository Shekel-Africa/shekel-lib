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

    /**
     * vendor-api's /v3/vendor/shippings/document-reminders: the shipments whose shipping partner may
     * need reminding to upload their shipping documents (bill of lading, manifest, Telex), each with
     * the country its port of loading is in, the reminder point it has reached before arrival and its
     * estimated arrival. vendor-api decides which documents are due and missing, and sends each
     * reminder at most once per point.
     *
     * @param list<array{shipping_id: string, origin: string, point: string, estimated_arrival: string}> $shipments
     *        origin: US, CA or KR; point: four_weeks, two_weeks, one_week or one_day;
     *        estimated_arrival: a date (Y-m-d)
     */
    public function documentReminders(array $shipments)
    {
        return $this->handleRequest($this->client->post('/vendor/shippings/document-reminders', [
            'shipments' => $shipments,
        ]));
    }
}
