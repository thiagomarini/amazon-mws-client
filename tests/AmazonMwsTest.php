<?php

use PHPUnit\Framework\TestCase;
use Weengs\AmazonMwsClient;

/**
 * @group unit
 */
final class AmazonMwsTest extends TestCase
{
    public function test_base_url_needs_to_be_a_valid_mws_endpoint()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base URl must contain "https://mws.amazonservices", received "https://weengs.com"');

        new AmazonMwsClient(
            'foo',
            'bar',
            'baz',
            ['mkt-place-id'],
            'fake-token',
            'https://weengs.com',
            'name',
            'version'
        );
    }
}
