<?php

use PHPUnit\Framework\TestCase;
use ThiagoMarini\AmazonMwsClient;

/**
 * @group unit
 */
final class AmazonMwsTest extends TestCase
{
    public function test_base_url_needs_to_be_a_valid_mws_endpoint()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Base URL must be a valid MWS endpoint, received "https://weengs.com"');

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

    public function test_it_accepts_valid_USA_mws_endpoint()
    {
        $client = new AmazonMwsClient(
            'foo',
            'bar',
            'baz',
            ['mkt-place-id'],
            'fake-token',
            'https://mws.amazonservices.com',
            'name',
            'version'
        );

        $this->assertInstanceOf(AmazonMwsClient::class, $client);
    }

    public function test_it_accepts_valid_EU_mws_endpoint()
    {
        $client = new AmazonMwsClient(
            'foo',
            'bar',
            'baz',
            ['mkt-place-id'],
            'fake-token',
            'https://mws-eu.amazonservices.com',
            'name',
            'version'
        );

        $this->assertInstanceOf(AmazonMwsClient::class, $client);
    }
}
