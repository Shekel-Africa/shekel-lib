<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Shekel\ShekelLib\Models\AccessToken;

class ShekelAuth
{
    const authKey = 'ShekelAuthToken';
    const xTokenKey = 'ShekelXToken';

    private string|null $token;
    private string|null $clientId;
    private string|null $clientConnection;
    private string|null $xToken;

    public function __construct() {
        $this->token = self::getAuthToken();
        $this->clientId = TenantClient::getClientId();
        $this->xToken = self::getAuthXToken();
        $this->clientConnection = Config::get('database.connection.tenant');
    }
    /**
     * @return string|null
     */
    public static function getAuthToken(): string|null
    {
        return Session::get(self::authKey);
    }

    /**
     * @param string|null $token
     */
    public static function setAuthToken(string|null $token): void
    {
        Session::put(self::authKey, $token);
    }
    /**
     * @return string|null
     */
    public static function getAuthXToken(): string|null
    {
        return Session::get(self::xTokenKey);
    }

    /**
     * @param string|null $token
     */
    public static function setAuthXToken(string|null $token): void
    {
        Session::put(self::xTokenKey, $token);
    }

    /**
     * @param string $token
     * @param array $scopes
     * @return bool
     * @throws ShekelInvalidArgumentException
     */
    public static function verifyToken(string $token, array $scopes =[]): bool
    {
        if (empty($token)) {
            throw new ShekelInvalidArgumentException('Unauthenticated', 401);
        }
        $user = PassportToken::getUserFromToken($token);
        if (Carbon::now()->gt($user['expiry'])) {
            throw new ShekelInvalidArgumentException('Login Session Expired', 401);
        }
//        if (!AccessToken::isValid($user['token_id'])) {
//            throw new ShekelInvalidArgumentException('Login Session Token Revoked', 401);
//        }
        if (!empty($scopes)) {
            $scopes = collect($scopes);
            if (($scopes->diff($user['scopes']))->count() > 0) {
                throw new ShekelInvalidArgumentException('User does not have required permission', 403);
            }
        }
        return true;
    }

    /**
     * @return ShekelAuth
     */
    public static function getAuthObject(): ShekelAuth
    {
        return new ShekelAuth();
    }

    /**
     * Set session from auth object
     * @return void
     */
    public function fromAuthObject():void {
        TenantClient::setClientId($this->clientId);
        ShekelAuth::setAuthXToken($this->xToken);
        ShekelAuth::setAuthToken($this->token);
    }

    /**
     * @return string|null
     */
    public function getToken(): string|null
    {
        return $this->token;
    }

    /**
     * @return string|null
     */
    public function getClientId(): string|null
    {
        return $this->clientId;
    }

    /**
     * @return string|null
     */
    public function getXToken(): string|null
    {
        return $this->xToken;
    }
    /**
     * @return string|null
     */
    public function getClientConnection(): string|null
    {
        return $this->clientConnection;
    }


}