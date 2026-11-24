<?php

namespace Shekel\ShekelLib\Tests\Services;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Shekel\ShekelLib\Services\v3\VendorService;

/**
 * VendorService::documentReminders, vendor-api's shipping document reminders: the shipments loan-api
 * found at a reminder point before arrival, sent with the service secret (S20-4922).
 */
class VendorServiceDocumentRemindersTest extends TestCase
{
    private const VENDOR_API = 'https://vendor-api.example.test/api';
    private const SECRET = 'vendor-test-secret-value';
    private const URL = self::VENDOR_API.'/v3/vendor/shippings/document-reminders';
    private const SHIPMENTS = [
        ['shipping_id' => '9b1c3f0e-6a2d-4c1e-9f7a-2b8d5e4c1a00', 'origin' => 'US', 'point' => 'one_week', 'estimated_arrival' => '2026-12-20'],
        ['shipping_id' => '9b1c3f0e-6a2d-4c1e-9f7a-2b8d5e4c1a01', 'origin' => 'KR', 'point' => 'four_weeks', 'estimated_arrival' => '2027-01-10'],
    ];

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('shekel.vendor', self::VENDOR_API);
        $app['config']->set('shekel.vendor-secret', self::SECRET);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::response(['data' => ['reminded' => 1, 'skipped' => 1]])]);
    }

    public function test_it_posts_the_shipments_to_vendor_api(): void
    {
        $response = (new VendorService())->documentReminders(self::SHIPMENTS);

        Http::assertSent(fn (Request $request) => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->data() === ['shipments' => self::SHIPMENTS]);
        $this->assertSame(['reminded' => 1, 'skipped' => 1], $response->json('data'));
    }

    public function test_it_sends_the_service_secret(): void
    {
        (new VendorService())->documentReminders(self::SHIPMENTS);

        Http::assertSent(fn (Request $request) => $request->hasHeader('s2s', self::SECRET)
            && $request->hasHeader('s2sName', 'vendor'));
    }
}
