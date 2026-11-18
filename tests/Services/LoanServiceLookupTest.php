<?php

namespace Shekel\ShekelLib\Tests\Services;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Shekel\ShekelLib\Services\v3\LoanService;

/**
 * LoanService::lookupLoans, loan-api's vendor loan lookup: the ids alone, as every caller sends
 * today, or with the vehicles' photos and video when asked (S20-4847).
 */
class LoanServiceLookupTest extends TestCase
{
    private const LOAN_API = 'https://loan-api.example.test/api';
    private const SECRET = 'loan-test-secret-value';
    private const IDS = ['9b1c3f0e-6a2d-4c1e-9f7a-2b8d5e4c1a00', '9b1c3f0e-6a2d-4c1e-9f7a-2b8d5e4c1a01'];

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('shekel.loan', self::LOAN_API);
        $app['config']->set('shekel.loan-secret', self::SECRET);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake([self::LOAN_API.'/v3/vendor/loans/lookup' => Http::response(['data' => []])]);
    }

    public function test_it_sends_only_the_ids_by_default(): void
    {
        (new LoanService())->lookupLoans(self::IDS);

        Http::assertSent(fn (Request $request) => $request->url() === self::LOAN_API.'/v3/vendor/loans/lookup'
            && $request->method() === 'POST'
            && $request->data() === ['ids' => self::IDS]);
    }

    public function test_it_sends_only_the_ids_when_uploads_are_not_asked_for(): void
    {
        (new LoanService())->lookupLoans(self::IDS, false);

        Http::assertSent(fn (Request $request) => $request->data() === ['ids' => self::IDS]);
    }

    public function test_it_asks_for_the_vehicles_uploads_when_asked(): void
    {
        (new LoanService())->lookupLoans(self::IDS, true);

        Http::assertSent(fn (Request $request) => $request->url() === self::LOAN_API.'/v3/vendor/loans/lookup'
            && $request->data() === ['ids' => self::IDS, 'include_uploads' => true]);
    }

    public function test_the_service_secret_is_sent_either_way(): void
    {
        $service = new LoanService();
        $service->lookupLoans(self::IDS);
        $service->lookupLoans(self::IDS, true);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->hasHeader('s2s', self::SECRET)
            && $request->hasHeader('s2sName', 'loan'));
        Http::assertNotSent(fn (Request $request) => !$request->hasHeader('s2s', self::SECRET));
    }
}
