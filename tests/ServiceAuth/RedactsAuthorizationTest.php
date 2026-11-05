<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use PHPUnit\Framework\TestCase;
use Shekel\ShekelLib\ServiceAuth\Client\RedactsAuthorization;

class RedactsAuthorizationTest extends TestCase
{
    public function test_headers_are_redacted_case_insensitively(): void
    {
        $headers = RedactsAuthorization::headers([
            'Authorization' => ['Bearer eyJhbGciOi.eyJzdWIiOi.c2lnbmF0dXJl'],
            'authorization' => 'Bearer abc.def.ghi',
            's2s' => 'legacy',
            'Accept' => 'application/json',
        ]);

        $this->assertSame(['[redacted]'], $headers['Authorization']);
        $this->assertSame('[redacted]', $headers['authorization']);
        $this->assertSame('[redacted]', $headers['s2s']);
        $this->assertSame('application/json', $headers['Accept']);
    }

    public function test_strings_lose_bearer_tokens_and_jwts(): void
    {
        $text = RedactsAuthorization::string('call failed: Authorization: Bearer abc123.def-456_ghi= and eyJhbGciOiJSUzI1NiJ9.eyJpc3MiOiJ4In0.sig-nature');

        $this->assertStringNotContainsString('abc123', $text);
        $this->assertStringNotContainsString('eyJhbGciOiJSUzI1NiJ9', $text);
        $this->assertStringContainsString('call failed', $text);
    }

    public function test_monolog_array_records_are_scrubbed(): void
    {
        $record = (new RedactsAuthorization())([
            'message' => 'token Bearer secret-token',
            'context' => ['headers' => ['Authorization' => 'Bearer x'], 'client_secret' => 's'],
            'extra' => [],
        ]);

        $this->assertSame('token Bearer [redacted]', $record['message']);
        $this->assertSame('[redacted]', $record['context']['headers']['Authorization']);
        $this->assertSame('[redacted]', $record['context']['client_secret']);
    }

    public function test_exceptions_and_raw_bodies_are_scrubbed(): void
    {
        $record = (new RedactsAuthorization())([
            'message' => 'grant_type=client_credentials&client_id=x&client_secret=fake-secret&scope=a',
            'context' => ['exception' => new \RuntimeException('failed with Bearer fake-token'), 'body' => '{"access_token":"fake-token","expires_in":600}'],
            'extra' => [],
        ]);

        $this->assertStringNotContainsString('fake-secret', $record['message']);
        $this->assertStringNotContainsString('fake-token', $record['context']['exception']);
        $this->assertStringContainsString('RuntimeException', $record['context']['exception']);
        $this->assertStringNotContainsString('fake-token', $record['context']['body']);
    }

    public function test_whole_values_are_redacted_whatever_they_contain(): void
    {
        $json = RedactsAuthorization::string('{"client_secret":"alpha beta \\"gamma\\" delta","scope":"a"}');
        $this->assertSame('{"client_secret":"[redacted]","scope":"a"}', $json);

        $form = RedactsAuthorization::string('grant_type=client_credentials&client_secret=alpha,beta%20gamma&scope=a');
        $this->assertSame('grant_type=client_credentials&client_secret=[redacted]&scope=a', $form);
    }

    public function test_sentry_events_are_scrubbed(): void
    {
        $event = new class {
            public array $request = ['headers' => ['Authorization' => 'Bearer x'], 'url' => '/v1/internal'];

            public function getRequest(): array
            {
                return $this->request;
            }

            public function setRequest(array $request): void
            {
                $this->request = $request;
            }
        };

        RedactsAuthorization::sentryBeforeSend($event);
        $this->assertSame('[redacted]', $event->request['headers']['Authorization']);
        $this->assertSame('/v1/internal', $event->request['url']);
    }
}
