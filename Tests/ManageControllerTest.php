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

    public function setUp()
    {
        self::bootKernel(
            [
                'debug' => true
            ]
        );
        $this->connection = static::$kernel->getContainer()->get('doctrine')->getConnection('oauth_providers');
        $this->connection->exec(
            "TRUNCATE `consumers`"
        );
    }

    public function testListEmptyAPIsList()
    {

        $client = static::createClient();
        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN
        ];

        $client->request('GET', '/oauth-v2/manage', [], [], $server);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals([], $response);
    }

    public function testAddAPI()
    {
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

    public function testListAPIs()
    {
        $this->connection->query(
            '
            INSERT INTO `consumers` VALUES (
                \'ex-generic-v2\',
                \'https://oauth.example.com\',
                \'https://oauth.example.com/token\',
                \'\',
                \'123456\',
                \'\',
                \'\',
                \'Testing keboola.oauth-v2\',
                \'2.0\'
            )
        '
        );
        $client = static::createClient();
        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN
        ];

        $client->request('GET', '/oauth-v2/manage', [], [], $server);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $response);
        $this->assertEquals('ex-generic-v2', $response[0]['component_id']);
        $this->assertEquals('123456', $response[0]['app_key']);
        $this->assertEquals('Testing keboola.oauth-v2', $response[0]['friendly_name']);
        $this->assertEquals('2.0', $response[0]['oauth_version']);
    }

    public function testGetAPI()
    {
        $this->connection->query(
            '
            INSERT INTO `consumers` VALUES (
                \'ex-generic-v2\',
                \'https://oauth.example.com\',
                \'https://oauth.example.com/token\',
                \'\',
                \'123456\',
                \'\',
                \'\',
                \'Testing keboola.oauth-v2\',
                \'2.0\'
            )
        '
        );
        $client = static::createClient();
        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN
        ];
        $client->request('GET', '/oauth-v2/manage/ex-generic-v2', [], [], $server);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ex-generic-v2', $response['component_id']);
        $this->assertEquals('123456', $response['app_key']);
        $this->assertEquals('Testing keboola.oauth-v2', $response['friendly_name']);
        $this->assertEquals('2.0', $response['oauth_version']);
    }

    public function testUpdateAPI()
    {
        $this->connection->query(
            '
            INSERT INTO `consumers` VALUES (
                \'ex-generic-v2\',
                \'https://oauth.example.com\',
                \'https://oauth.example.com/token\',
                \'\',
                \'123456\',
                \'\',
                \'\',
                \'Testing keboola.oauth-v2\',
                \'2.0\'
            )
        '
        );
        $client = static::createClient();
        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN,
            'HTTP_X-StorageApi-Token' => STORAGE_API_TOKEN
        ];
        $body = '{
              "friendly_name": "Testing keboola.oauth-v2 XXX",
              "app_key": "123456 XXX",
              "app_secret": "654321 XXX",
              "auth_url": "https://oauth.example.com XXX",
              "token_url": "https://oauth.example.com/token XXX",
              "oauth_version": "2.0"
            }';

        $client->request('PATCH', '/oauth-v2/manage/ex-generic-v2', [], [], $server, $body);

        $dbRecord = $this->connection->fetchAll('SELECT * FROM `consumers`')[0];
        $this->assertEquals('ex-generic-v2', $dbRecord['component_id']);
        $this->assertEquals('https://oauth.example.com XXX', $dbRecord['auth_url']);
        $this->assertEquals('https://oauth.example.com/token XXX', $dbRecord['token_url']);
        $this->assertEquals('Testing keboola.oauth-v2 XXX', $dbRecord['friendly_name']);
        $this->assertEquals('', $dbRecord['request_token_url']);
        $this->assertEquals('2.0', $dbRecord['oauth_version']);
        $this->assertArrayHasKey('app_secret', $dbRecord);
        $this->assertArrayHasKey('app_secret_docker', $dbRecord);
        $this->assertNotEmpty($dbRecord['app_secret']);
        $this->assertNotEmpty($dbRecord['app_secret_docker']);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ex-generic-v2', $response['component_id']);
        $this->assertEquals('123456 XXX', $response['app_key']);
        $this->assertEquals('Testing keboola.oauth-v2 XXX', $response['friendly_name']);
        $this->assertEquals('2.0', $response['oauth_version']);


    }

}
