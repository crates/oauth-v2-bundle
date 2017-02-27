<?php

namespace Keboola\OAuthV2Bundle\Tests;

use Keboola\Syrup\Test\WebTestCase;
use Keboola\Temp\Temp;

class ManageControllerTest extends WebTestCase
{
    public function setUp() {
        self::bootKernel([
            'debug' => true
        ]);
        $connection = static::$kernel->getContainer()->get('doctrine')->getConnection('oauth_providers');
        $connection->exec(
            "TRUNCATE `consumers`"
        );
    }

    public function testListEmptyAPIsList() {

        $client = static::createClient();
        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN
        ];

        $client->request('GET', '/oauth-v2/manage', [], [], $server);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals([], $response);
    }

    public function testAddAPI() {
        $client = static::createClient();
        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN,
            'HTTP_X-StorageApi-Token' => STORAGE_API_TOKEN,
            'CONTENT_TYPE' => 'application/json'
        ];
        $body = '{
              "component_id": "ex-generic-v2",
              "friendly_name": "Testing keboola.oauth-v2",
              "app_key": "123456",
              "app_secret": "654321",
              "auth_url": "https://oauth.example.com",
              "token_url": "https://oauth.example.com/token",
              "oauth_version": "2.0"
            }';

        $client->request('POST', '/oauth-v2/manage', [], [], $server, $body);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('created', $response['status']);
    }


}
