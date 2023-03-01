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
     * @param $token
     * @param $userId
     * @return
     */
    public function s2sLogin($token, $userId=null) {
        $data = [
            'token' => $token,
            'userId' => $userId
        ];
        return $this->handleRequest($this->client->post('login/s2s', $data));
    }
}
