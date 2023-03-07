<?php

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class AuthService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('auth');
    }

    /**
     * Undocumented function
     *
     * @return Response
     */
    public function verifyAuthentication($scopes = ['*'])
    {
        return $this->handleRequest($this->client->post('/token/verify', ['scope' => json_encode($scopes)]));
    }

    public function getSuperAdmin() {
        return $this->handleRequest($this->client->get('/admin/super'));
    }

    /**
     * Handles Background Processes that needs s2stoken
     * Returns auth Token for user |super admin if user Id is not provided
     * @param $hashedToken
     * @param null $userId
     * @return mixed
     */
    public function s2sLogin($hashedToken, $userId=null) {
        $data = [
            'token' => $hashedToken,
            'userId' => $userId
        ];
        return $this->handleRequest($this->client->post('login/service', $data));
    }

    public function getUserDetail(string $userId) {
        return $this->handleRequest($this->client->get("/user/$userId"));
    }

    public function getUserDetailsWithSecrets(string $userId) {
        return $this->handleRequest($this->client->get("/user/$userId/secrets"));
    }

    public function getBusinessName(string $userId) {
        return $this->handleRequest($this->client->get("/user/$userId/business"));
    }
}
