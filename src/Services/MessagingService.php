<?php

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class MessagingService extends ShekelBaseService {

    public function __construct(){
        parent::__construct("messaging");
    }

    public function sendEmailVerification($data)
    {
        $url = "/send/verification-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendInviteEmail($data)
    {
        $url = "/send/invite-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendOTP($data) {
        $url = "/send/otp-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendLoanOfferEmail($data) {
        $url = "/send/loan-offer-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendLoanRequestEmail($data) {
        $url = "/send/loan-request-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendAdminVerificationMail($data) {
        $url = "/send/kyc-verified-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendForgotPasswordMail($data) {
        $url = "/send/forgot-password";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function onNewLoanComment($data) {
        $url = "/send/on-new-loan-comment";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendDisbursementEmail($data) {
        $url = "/send/disbursement-email";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendKYCNotification($data) {
        $url = "/send/kyc-status-email";
        return $this->handleRequest($this->client->post($url, $data));
    }
    
    public function sendTransactionNotification($data) {
        $url = "/send/transaction-notification";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function sendDueLoanNotification($data) {
        $url = "/send/due-loan-notification";
        return $this->handleRequest($this->client->post($url, $data));
    }

}