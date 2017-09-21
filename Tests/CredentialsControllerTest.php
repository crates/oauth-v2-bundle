<?php
/**
 * Author: miro@keboola.com
 * Date: 21/09/2017
 */

namespace Keboola\OAuthV2Bundle\Tests;

use Doctrine\DBAL\Connection;
use Keboola\Syrup\Encryption\BaseWrapper;
use Keboola\Syrup\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CredentialsControllerTest extends WebTestCase
{
    /** @var Connection */
    protected $connection;

    private $testComponentId = 'leochan.ex-quickbooks';

    /** @var Client */
    protected $client;

    public function setUp()
    {
        self::bootKernel(['debug' => true]);
        $container = static::$kernel->getContainer();
        $this->connection = $container->get('doctrine')->getConnection('oauth_providers');
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
        $api['app_secret'] = $container
            ->get('syrup.encryption.base_wrapper')
            ->encrypt('app_secret');

        $this->connection->insert('consumers', (array) $api);
    }

    public function testGetAction()
    {
        $credentials = [
            "id" => "123456",
            "component_id" => "leochan.ex-quickbooks",
            "project_id" => "219",
            "creator" => "{\"id\":\"1\", \"description\":\"test\"}",
            "data" => "KBC::ComponentProjectEncrypted==12345==",
            "authorized_for" => "me",
            "created" => "2017-09-21 03:41:23",
            "auth_url" => "https://anothersubdomain.intuit.com/connect/oauth2?response_type=code&client_id=%%client_id%%&scope=com.intuit.quickbooks.accounting com.intuit.quickbooks.payment&redirect_uri=%%redirect_uri%%&state=security_token12345",
            "token_url"=> null,
            "request_token_url" => null,
            "app_key" => "123456",
            "app_secret"=> "DhnxbCLaQdk9/o7kMYZP9bYQWGa8ELfF/Z17Qlw1FEzUYQC2O/1UDAgrJmh+Az1KnjENwiRBe8/jhIOMILlYew==",
            "app_secret_docker" => "KBC::ComponentProjectEncrypted==sSfe6fxE651Gjav5oYnXtCR45wJXUN5GUlrSmJbdXAzQg66P/qVBusYgZYjLHtH4amc3hXbk/sj2+vxw2uTbow=="
        ];
        $this->connection->insert('credentials', $credentials);

        $server = [
            'HTTP_X-StorageApi-Token' => STORAGE_API_TOKEN
        ];

        $this->client->request(
            'GET', '/oauth-v2/credentials/' . $this->testComponentId . '/123456',
            [],
            [],
            $server
        );

        /** @var RedirectResponse $response */
        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

        $responseBody = json_decode($response->getContent(), true);

        $this->assertEquals($credentials['id'], $responseBody['id']);
        $this->assertEquals($credentials['authorized_for'], $responseBody['authorizedFor']);
        $this->assertEquals($credentials['created'], $responseBody['created']);
        $this->assertEquals($credentials['data'], $responseBody['#data']);
        $this->assertEquals('quickbooks', $responseBody['oauthVersion']);
        $this->assertEquals($credentials['app_key'], $responseBody['appKey']);
        $this->assertEquals($credentials['app_secret_docker'], $responseBody['#appSecret']);
    }
}