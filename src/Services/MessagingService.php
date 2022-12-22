<?php

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class MessagingService extends ShekelBaseService {
    
    public function __construct($token){
        parent::__construct($token, "messaging");
    }

    public function sendEmailVerification($data)
    {
        $url = "/send/verification-email";
        return $this->client->post($url, $data);
    }

    public function sendInviteEmail($data)
    {
        $url = "/send/invite-email";
        return $this->client->post($url, $data);
    }

    public function sendOTP($data) {
        $url = "/send/otp-email";
        return $this->client->post($url, $data);
    }

    public function sendLoanOfferEmail($data) {
        $url = "/send/loan-offer-email";
        return $this->client->post($url, $data);
    }

    public function sendLoanRequestEmail($data) {
        $url = "/send/loan-request-email";
        return $this->client->post($url, $data);
    }

    public function sendAdminVerificationMail($data) {
        $url = "/send/kyc-verified-email";
        return $this->client->post($url, $data);
    }
}