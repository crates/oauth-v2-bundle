<?php

namespace Keboola\OAuthV2Bundle\Tests;

use Doctrine\DBAL\Driver\Connection;
use Keboola\Syrup\Test\WebTestCase;
use Keboola\Temp\Temp;

class ManageControllerTest extends WebTestCase
{
    /**
     * @var Connection
     */
    protected $connection;

    public function setUp() {
        self::bootKernel([
            'debug' => true
        ]);
        $this->connection = static::$kernel->getContainer()->get('doctrine')->getConnection('oauth_providers');
        $this->connection->exec(
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

        $dbRecord = $this->connection->fetchAll('SELECT * FROM `consumers`')[0];
        $this->assertEquals('ex-generic-v2', $dbRecord['component_id']);
        $this->assertEquals('https://oauth.example.com', $dbRecord['auth_url']);
        $this->assertEquals('https://oauth.example.com/token', $dbRecord['token_url']);
        $this->assertEquals('Testing keboola.oauth-v2', $dbRecord['friendly_name']);
        $this->assertEquals('', $dbRecord['request_token_url']);
        $this->assertEquals('2.0', $dbRecord['oauth_version']);
        $this->assertArrayHasKey('app_secret', $dbRecord);
        $this->assertArrayHasKey('app_secret_docker', $dbRecord);
    }


}
