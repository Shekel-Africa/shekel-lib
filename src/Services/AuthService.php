<?php 

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class AuthService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'auth');
    }

    /**
     * Undocumented function
     *
     * @return Response
     */
    public function verifyAuthentication($scopes = ['*'])
    {
        return $this->client->post('/token/verify', ['scope' => json_encode($scopes)]);
    }

    public function getSuperAdmin() {
        return $this->client->get('/admin/super');
    }
}