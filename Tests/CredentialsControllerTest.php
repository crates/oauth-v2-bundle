<?php
/**
 * Author: miro@keboola.com
 * Date: 21/09/2017
 */

namespace Keboola\OAuthV2Bundle\Tests;

use Doctrine\DBAL\Connection;
use Keboola\OAuthV2Bundle\Encryption\ByAppEncryption;
use Keboola\Syrup\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class CredentialsControllerTest extends WebTestCase
{
    /** @var Connection */
    protected $connection;

    private $testComponentId = 'leochan.ex-quickbooks';

    /** @var Client */
    protected $client;

    private $credentials;

    public function setUp()
    {
        self::bootKernel(['debug' => true]);
        $container = static::$kernel->getContainer();
        $this->connection = $container->get('doctrine')->getConnection();
        $this->connection->exec(
            "TRUNCATE `consumers`"
        );
        $this->connection->exec(
            "TRUNCATE `credentials`"
        );
        $this->connection->exec(
            "TRUNCATE `sessions`"
        );

        $this->client = static::createClient();

        // register API
        $api = [
            'component_id' => $this->testComponentId,
            'friendly_name' => 'Quickbooks Report',
            'app_key' => 'app_key',
            'auth_url' => 'https://appcenter.intuit.com/connect/oauth2?response_type=code&client_id=%%client_id%%&scope=com.intuit.quickbooks.accounting com.intuit.quickbooks.payment&redirect_uri=%%redirect_uri%%&state=security_token12345',
            'token_url' => 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer',
            'oauth_version' => 'quickbooks'
        ];

        $api['app_secret_docker'] = 'not needed';
        $api['app_secret'] = 'empty';

        $this->connection->insert('consumers', (array) $api);

        $storageApiTokenArr = explode('-', STORAGE_API_TOKEN);

        $this->credentials = [
            "id" => "123456",
            "component_id" => "leochan.ex-quickbooks",
            "project_id" => $storageApiTokenArr[0],
            "creator" => "{\"id\":\"1\", \"description\":\"test\"}",
            "data" => "KBC::ProjectSecure::12345==",
            "authorized_for" => "me",
            "created" => "2017-09-21 03:41:23",
            "auth_url" => "https://anothersubdomain.intuit.com/connect/oauth2?response_type=code&client_id=%%client_id%%&scope=com.intuit.quickbooks.accounting com.intuit.quickbooks.payment&redirect_uri=%%redirect_uri%%&state=security_token12345",
            "token_url"=> null,
            "request_token_url" => null,
            "app_key" => "123456",
            "app_secret"=> "DhnxbCLaQdk9/o7kMYZP9bYQWGa8ELfF/Z17Qlw1FEzUYQC2O/1UDAgrJmh+Az1KnjENwiRBe8/jhIOMILlYew==",
            "app_secret_docker" => "KBC::ProjectSecure::sSfe6fxE651Gjav5oYnXtCR45wJXUN5GUlrSmJbdXAzQg66P/qVBusYgZYjLHtH4amc3hXbk/sj2+vxw2uTbow=="
        ];
        $this->connection->insert('credentials', $this->credentials);
    }

    public function testGetAction()
    {
        $server = [
            'HTTP_X-StorageApi-Token' => STORAGE_API_TOKEN
        ];

        $this->client->request(
            'GET', '/oauth-v2/credentials/' . $this->testComponentId . '/123456',
            [],
            [],
            $server
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = json_decode($response->getContent(), true);

        $credentials = $this->credentials;
        $this->assertEquals($credentials['id'], $responseBody['id']);
        $this->assertEquals($credentials['authorized_for'], $responseBody['authorizedFor']);
        $this->assertEquals($credentials['created'], $responseBody['created']);
        $this->assertEquals($credentials['data'], $responseBody['#data']);
        $this->assertEquals('quickbooks', $responseBody['oauthVersion']);
        $this->assertEquals($credentials['app_key'], $responseBody['appKey']);
        $this->assertEquals($credentials['app_secret_docker'], $responseBody['#appSecret']);
    }

    public function testEncryption()
    {
        $client = new \Keboola\StorageApi\Client(
            [
                'token' => STORAGE_API_TOKEN,
                'url' => STORAGE_API_URL,
            ]
        );
        $encryption = ByAppEncryption::factory($client);
        $response = $encryption->encrypt('secret', 'docker-config-encrypt-verify', false);
        self::assertStringStartsWith('KBC::ComponentSecure::', $response);
        $response = $encryption->encrypt('secret', 'docker-config-encrypt-verify', true);
        self::assertStringStartsWith('KBC::ProjectSecure::', $response);
    }

    public function testGetRawAction()
    {
        $server = [
            'HTTP_X-StorageApi-Token' => STORAGE_API_TOKEN
        ];

        $this->client->request(
            'GET', '/oauth-v2/credentials/' . $this->testComponentId . '/123456/raw',
            [],
            [],
            $server
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = json_decode($response->getContent(), true);

        $credentials = $this->credentials;
        $this->assertEquals($credentials['id'], $responseBody['id']);
        $this->assertEquals($credentials['component_id'], $responseBody['component_id']);
        $this->assertEquals($credentials['project_id'], $responseBody['project_id']);
        $this->assertEquals($credentials['data'], $responseBody['data']);
        $this->assertEquals($credentials['authorized_for'], $responseBody['authorized_for']);
        $this->assertEquals($credentials['creator'], $responseBody['creator']);
        $this->assertEquals($credentials['created'], $responseBody['created']);
        $this->assertEquals($credentials['auth_url'], $responseBody['auth_url']);
        $this->assertEquals($credentials['app_key'], $responseBody['app_key']);
        $this->assertEquals($credentials['app_secret_docker'], $responseBody['app_secret_docker']);
    }

    public function testAddActionCustom()
    {
        $server = [
            'HTTP_X-StorageApi-Token' => STORAGE_API_TOKEN
        ];

        $id = uniqid('oauth-test');

        $body = sprintf('{
              "id": "%s",
              "data": {
                "access_token": "1234"
              },
              "authorizedFor": "test",
              "authUrl": "https://oauth.example.com",
              "appKey": "12345",
              "appSecret": "5678",
              "appSecretDocker": "KBC::ComponentSecure::5678"
            }', $id);

        $this->client->request(
            'POST', '/oauth-v2/credentials/' . $this->testComponentId,
            [],
            [],
            $server,
            $body
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

        $responseBody = json_decode($response->getContent(), true);

        $this->assertEquals('KBC::ComponentSecure::5678', $responseBody['#appSecret']);
        $this->assertEquals('12345', $responseBody['appKey']);
        $this->assertEquals($id, $responseBody['id']);
        $this->assertEquals('test', $responseBody['authorizedFor']);

        // get raw credentials
        $this->client->request(
            'GET',
            sprintf('/oauth-v2/credentials/%s/%s/raw', $this->testComponentId, $id),
            [],
            [],
            $server
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = json_decode($response->getContent(), true);

        $this->assertEquals('KBC::ComponentSecure::5678', $responseBody['app_secret_docker']);
        $this->assertEquals('12345', $responseBody['app_key']);
        $this->assertEquals($id, $responseBody['id']);
        $this->assertEquals('test', $responseBody['authorized_for']);
    }

    public function testAddAction()
    {
        $server = [
            'HTTP_X-StorageApi-Token' => STORAGE_API_TOKEN
        ];

        $id = uniqid('oauth-test');

        $body = sprintf('{
              "id": "%s",
              "data": {
                "access_token": "1234"
              },
              "authorizedFor": "test"
            }', $id);

        $this->client->request(
            'POST', '/oauth-v2/credentials/' . $this->testComponentId,
            [],
            [],
            $server,
            $body
        );

        /** @var Response $response */
        $response = $this->client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

        $responseBody = json_decode($response->getContent(), true);

        $this->assertEquals($id, $responseBody['id']);
        $this->assertEquals('test', $responseBody['authorizedFor']);
    }
}
