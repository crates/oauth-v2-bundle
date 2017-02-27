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
}
