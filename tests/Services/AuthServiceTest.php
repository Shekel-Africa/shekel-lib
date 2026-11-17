<?php

namespace Shekel\ShekelLib\Tests\Services;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Shekel\ShekelLib\Services\v3\AuthService;

class AuthServiceTest extends TestCase
{
    private const AUTH_URL = 'https://auth-api.test';

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('shekel.auth', self::AUTH_URL);
        $app['config']->set('shekel.auth-secret', 'test-s2s-secret');
    }

    private function fakeAuth(int $status = 201): void
    {
        Http::fake([self::AUTH_URL.'/*' => Http::response(['data' => []], $status)]);
    }

    private function service(): AuthService
    {
        $service = new AuthService();
        $service->setToken('test-admin-token');
        return $service;
    }

    public function test_creates_required_action_with_required_fields_only(): void
    {
        $this->fakeAuth();

        $this->service()->createRequiredAction('user-uuid', 'complete_kyc');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/user/required-actions')
                && $request->method() === 'POST'
                && $request['user_id'] === 'user-uuid'
                && $request['action'] === 'complete_kyc';
        });
    }

    public function test_creates_required_action_with_all_options(): void
    {
        $this->fakeAuth();

        $this->service()->createRequiredAction('user-uuid', 'accept_loan_terms', [
            'subject_type' => 'loan',
            'subject_id'   => 'loan-uuid',
            'company_id'   => 'company-uuid',
            'metadata'     => ['loan_amount' => 500000],
            'expires_at'   => '2026-12-01',
        ]);

        Http::assertSent(function ($request) {
            return $request['subject_type'] === 'loan'
                && $request['subject_id'] === 'loan-uuid'
                && $request['company_id'] === 'company-uuid'
                && $request['metadata']['loan_amount'] === 500000
                && $request['expires_at'] === '2026-12-01';
        });
    }

    public function test_options_do_not_overwrite_user_id_or_action(): void
    {
        $this->fakeAuth();

        $this->service()->createRequiredAction('user-uuid', 'complete_kyc', [
            'user_id' => 'other-uuid',
            'action'  => 'other_action',
        ]);

        Http::assertSent(function ($request) {
            return $request['user_id'] === 'other-uuid'
                && $request['action'] === 'other_action';
        });
    }

    public function test_admin_token_is_sent_as_bearer(): void
    {
        $this->fakeAuth();

        $this->service()->createRequiredAction('user-uuid', 'complete_kyc');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-admin-token');
        });
    }
}
