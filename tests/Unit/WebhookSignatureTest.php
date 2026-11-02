<?php

namespace MainaSuperintelligence\Whatsapp\Tests\Unit;

use MainaSuperintelligence\Whatsapp\Services\WebhookProcessor;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class WebhookSignatureTest extends TestCase
{
    public function test_valid_signature(): void
    {
        $body = '{"object":"whatsapp_business_account","entry":[]}';
        $header = 'sha256=' . hash_hmac('sha256', $body, 'secret');

        $this->assertTrue(WebhookProcessor::signatureIsValid($body, $header, 'secret'));
    }

    public function test_rejects_tampered_body_wrong_secret_and_missing_header(): void
    {
        $body = '{"a":1}';
        $header = 'sha256=' . hash_hmac('sha256', $body, 'secret');

        $this->assertFalse(WebhookProcessor::signatureIsValid('{"a":2}', $header, 'secret'));
        $this->assertFalse(WebhookProcessor::signatureIsValid($body, $header, 'other'));
        $this->assertFalse(WebhookProcessor::signatureIsValid($body, null, 'secret'));
        $this->assertFalse(WebhookProcessor::signatureIsValid($body, $header, null));
        $this->assertFalse(WebhookProcessor::signatureIsValid($body, hash_hmac('sha256', $body, 'secret'), 'secret'));
    }
}
