<?php

namespace Keboola\OAuthV2Bundle\Tests;

use Doctrine\DBAL\Connection;
use Keboola\Syrup\Encryption\BaseWrapper;
use Keboola\Syrup\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

class OAuth10RsaTest extends WebTestCase
{
    /** @var Connection */
    protected $connection;

    private $testComponentId = 'apac.ex-xero';

    /** @var Client */
    protected $client;

    public function setUp()
    {
        self::bootKernel(['debug' => true]);
        $container = static::$kernel->getContainer();
        $this->connection = $container->get('doctrine')->getConnection();
        $this->connection->exec("TRUNCATE `consumers`");
        $this->connection->exec("TRUNCATE `credentials`");
        $this->connection->exec("TRUNCATE `sessions`");

        $this->client = static::createClient();
        /** @var BaseWrapper $encryptor */
        $encryptor = $container->get('syrup.encryption.base_wrapper');

        // register API
        $api = [
            'component_id' => $this->testComponentId,
            'friendly_name' => 'Xero',
            'app_key' => 'abcd56789',
            'request_token_url' => 'https://api.xero.com/oauth/RequestToken',
            'auth_url' => 'https://api.xero.com/oauth/Authorize?oauth_token=%%oauth_token%%',
	        'token_url' => 'https://api.xero.com/oauth/AccessToken',
            'oauth_version' => '1.0-rsa',
            'app_secret_docker' => 'not needed',
            'app_secret' => $encryptor->encrypt('abcd12345'),
            'rsa_private_key' => file_get_contents(__DIR__ . '/privatekey.pem'),
        ];

        var_dump($api);

        $this->connection->insert('consumers', (array) $api);
    }

    public function testInitAction()
    {
        $params = [
            'id' => '123456',
            'token' => '123456',
            'appKey' => '123456',
            'appSecret' => '123456',
            'authorizedFor' => 'test',
            'authUrl' => 'https://api.xero.com/oauth/Authorize?oauth_token=%%oauth_token%%',
            'returnUrl' => 'callback'
        ];

        $sessionMock = $this->getMockBuilder('Keboola\OAuthV2Bundle\Storage\Session')
            ->disableOriginalConstructor()
            ->getMock();

        $bagMock = new AttributeBag();
        $bagMock->initialize($params);
        $sessionMock->method('getBag')
            ->willReturn($bagMock);

        $this->client->getContainer()->set('oauth.session', $sessionMock);

        $container = static::$kernel->getContainer();
        $container->set('oauth.session', $sessionMock);

        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN
        ];

        /** @var Crawler $crawler */
        $crawler = $this->client->request(
            'POST', '/oauth-v2/authorize/' . $this->testComponentId,
            $params,
            [],
            $server
        );

        /** @var RedirectResponse $response */
        $response = $this->client->getResponse();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertContains(
            'Redirecting to https://anothersubdomain.intuit.com/connect/oauth2',
            $crawler->getNode('html')->nodeValue
        );
        $this->assertContains(
            'client_id=123456',
            $crawler->getNode('html')->nodeValue
        );
    }

    public function testCallbackActionSimple()
    {
        $this->client->restart();
        $this->client->followRedirects(false);
        $container = $this->client->getContainer();
        /** @var BaseWrapper $encryptor */
        $encryptor = $container->get('syrup.encryption.base_wrapper');

        $params = [
            'id' => '123456',
            'token' => $encryptor->encrypt(STORAGE_API_TOKEN),
            'authorizedFor' => 'test',
            'returnUrl' => 'callback'
        ];

        $oauthMock = $this->createMock('Keboola\OAuth\OAuth10');
        $oauthMock->method('createToken')
            ->willReturn([
                'access_token' => 'asdfghjkl',
                'refresh_token' => 'zxcvbnm'
            ]);

        $oauthFactoryMock = $this->createMock('Keboola\OAuthV2Bundle\Service\OAuthFactory');
        $oauthFactoryMock->method('create')
            ->willReturn($oauthMock);

        $sessionMock = $this->getMockBuilder('Keboola\OAuthV2Bundle\Storage\Session')
            ->disableOriginalConstructor()
            ->getMock();

        $bagMock = new AttributeBag();
        $bagMock->initialize($params);
        $sessionMock->method('getBag')
            ->willReturn($bagMock);
        $sessionMock->method('getEncrypted')
            ->willReturn(STORAGE_API_TOKEN);

        $container->set('oauth.session', $sessionMock);
        $container->set('oauth.factory', $oauthFactoryMock);

        $server = [
            'HTTP_X-KBC-ManageApiToken' => MANAGE_API_TOKEN
        ];

        $this->client->request(
            'GET', '/oauth-v2/authorize/' . $this->testComponentId . '/callback',
            ['code' => 'code123456789'],
            [],
            $server
        );

        /** @var RedirectResponse $response */
        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode());

        $consumers = $this->connection->query("SELECT * FROM consumers")->fetchAll();
        $credentials = $this->connection->query("SELECT * FROM credentials")->fetchAll();

        $this->assertEquals('abcd56789', $consumers[0]['app_key']);
        $this->assertEmpty($credentials[0]['app_key']);
        $this->assertEquals('abcd12345', $encryptor->decrypt($consumers[0]['app_secret']));
        $this->assertEmpty($credentials[0]['app_secret']);
    }
}

