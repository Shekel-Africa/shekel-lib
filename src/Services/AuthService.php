<?php

namespace Shekel\ShekelLib\Services;

use Carbon\Carbon;
use Shekel\ShekelLib\Models\AccessToken;
use Shekel\ShekelLib\Services\ShekelBaseService;
use Shekel\ShekelLib\Utils\PassportToken;

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
     * Get Authenticated User Object
     * @return mixed
     */
    public function getAuthenticated(array $query=[])
    {
        return $this->handleRequest($this->client->get('/authenticated', $query));
    }

    /**
     * Validate Token and permissions
     * @param array $scopes
     * @return bool
     */
    public function verifyToken(array $scopes = []): bool
    {
        if (empty($this->token)) {
            abort(401, "unauthenticated");
        }
        $user = PassportToken::getUserFromToken($this->token);
        if (now()->gt($user['expiry'])) {
            abort(401, "token expired");
        }
        if (!AccessToken::isValid($user['token_id'])) {
            abort(401, "Token Revoked");
        }
        if (!empty($scopes)) {
            $scopes = collect($scopes);
            if (($scopes->diff($user['scopes']))->count() > 0) {
                abort(403, "User does not have required permission");
            }
        }
        return true;
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
