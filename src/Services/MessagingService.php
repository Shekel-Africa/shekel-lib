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

    public function sendOTP($data) {
        $url = "/send/otp-email";
        return $this->client->post($url, $data);
    }
}