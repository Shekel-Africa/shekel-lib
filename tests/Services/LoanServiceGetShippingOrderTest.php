<?php

namespace Shekel\ShekelLib\Tests\Services;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Shekel\ShekelLib\Services\v3\LoanService;

class LoanServiceGetShippingOrderTest extends TestCase
{
    private const LOAN_API  = 'https://loan-api.example.test/api';
    private const SECRET    = 'loan-test-secret-value';
    private const ORDER_ID  = '9b1c3f0e-6a2d-4c1e-9f7a-2b8d5e4c1a00';

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
        Http::fake([
            self::LOAN_API.'/v3/shipping/orders/'.self::ORDER_ID => Http::response([
                'data' => ['id' => self::ORDER_ID, 'status' => 'shipped'],
            ]),
        ]);
    }

    public function test_it_gets_the_correct_endpoint(): void
    {
        (new LoanService())->getShippingOrder(self::ORDER_ID);

        Http::assertSent(fn (Request $req) =>
            $req->url()    === self::LOAN_API.'/v3/shipping/orders/'.self::ORDER_ID
            && $req->method() === 'GET'
        );
    }

    public function test_it_sends_the_service_secret(): void
    {
        (new LoanService())->getShippingOrder(self::ORDER_ID);

        Http::assertSent(fn (Request $req) =>
            $req->hasHeader('s2s', self::SECRET)
            && $req->hasHeader('s2sName', 'loan')
        );
    }

    public function test_it_returns_the_response(): void
    {
        $response = (new LoanService())->getShippingOrder(self::ORDER_ID);

        $this->assertTrue($response->successful());
        $this->assertEquals(self::ORDER_ID, $response->json('data.id'));
    }
}
