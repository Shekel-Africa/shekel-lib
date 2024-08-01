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

    public function getAdminIds() {
        return $this->handleRequest($this->client->get('/admin/getIds'));
    }

    /**
     * Get Authenticated User Object
     * @return mixed
     */
    public function getAuthenticated(array $query=[], $type=null)
    {
        $url = '/authenticated';
        if (isset($type)) {
            $url = "/$type/authenticated";
        }
        return $this->handleRequest($this->client->get($url, $query));
    }

    /**
     * Validate Token and permissions
     * @param array $scopes
     * @return bool
     */
    public function verifyToken(array $scopes = []): bool
    {
        if (empty($this->token)) {
            abort(401, "Unauthenticated");
        }
        $user = PassportToken::getUserFromToken($this->token);
        if (now()->gt($user['expiry'])) {
            abort(401, "Login Session Expired");
        }
        if (!AccessToken::isValid($user['token_id'])) {
            abort(401, "Login Session Token Revoked");
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

    public function listUsersDetail(array $ids, array $fields = []) {
        return $this->handleRequest($this->client->post("/user/list?showDeleted=true", [
            'ids' => $ids,
            'fields' => $fields
        ]));
    }

    public function partnerListUsersDetail(array $ids) {
        return $this->handleRequest($this->client->post("/partner/user/list", [
            'ids' => $ids
        ]));
    }

    public function listUsersDetailWithAvatar(array $ids) {
        return $this->handleRequest($this->client->post("/user/list?withAvatar=true&showDeleted=true", [
            'ids' => $ids
        ]));
    }

    public function getUserId(string $id) {
        return $this->handleRequest($this->client->get("/kyc/$id/id"));
    }

    public function getPartnerSecrets(string $id) {
        return $this->handleRequest($this->client->get("/partner/kyc/$id/details"));
    }

    public function getPartner(string $id) {
        return $this->handleRequest($this->client->get("/partner/user/$id"));
    }

    public function userListPartnerDetail(array $ids) {
        return $this->handleRequest($this->client->post("/partner/user/partner/list?showDeleted=true", [
            'ids' => $ids
        ]));
    }

    public function getCompanyDetailsWithSecrets(string $companyId) {
        return $this->handleRequest($this->client->get("/company/$companyId/secrets"));
    }
    
    public function activateReferral(string $id) {
        return $this->handleRequest($this->client->post("/referral/activate", [
            'user_id' => $id
        ]));
    }

    public function deactivateLending(string $lendingId, bool $status) {
        return $this->handleRequest($this->client->post("/partner/lending/$lendingId/deactivate", [
            'deactivate' => $status
        ]));
    }

    public function getSubdealerIds($id) {
        return $this->handleRequest($this->client->get("/admin/superdealer/$id/ids"));
    }

    public function userSearch($search, array $fields=[]) {
        return $this->handleRequest($this->client->post("/user/search?search=$search", [
            'fields' => $fields
        ]));
    }

    public function editUser($id, array $data) {
        return $this->handleRequest($this->client->post("/user/$id", $data));
    }

    public function getUserFeaturesFlag(string $userId, string $flag) {
        return $this->handleRequest($this->client->post("/features/$userId", [
            'flag' => $flag
        ]));
    }
}
