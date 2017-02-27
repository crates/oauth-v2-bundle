<?php
namespace Keboola\OAuthV2Bundle\Encryption;

use Keboola\OAuth\Exception\UserException;
use Keboola\Syrup\Client,
    Keboola\Syrup\ClientException;
use Keboola\Syrup\Exception\ApplicationException;
use Keboola\StorageApi\Client as StorageApi;

class ByAppEncryption
{
    /**
     * @var Client
     */
    protected $syrupClient;

    /**
     * ByAppEncryption constructor.
     *
     * @param Client $syrupClient
     */
    public function __construct(Client $syrupClient)
    {
        $this->syrupClient = $syrupClient;
    }

    /**
     * @param $secret
     * @param $componentId
     * @param bool $toConfig
     * @return string
     * @throws UserException
     */
    public function encrypt($secret, $componentId, $toConfig = false) {
        try {
            return $this->syrupClient->encryptString($componentId, $secret, $toConfig ? ["path" => "configs"] : []);
        } catch(ClientException $e) {
            throw new UserException("Component based encryption of the app secret failed: " . $e->getMessage());
        }
    }

    /**
     * @param $token
     * @param $sapiUrl
     * @return ByAppEncryption
     */
    public static function factory($token, $sapiUrl)
    {
        if(empty($sapiUrl)) {
            throw new ApplicationException("StorageApi url is empty and must be set");
        }
        $storageApiClient = new StorageApi([
            "token" => $token,
            "userAgent" => 'oauth-v2',
            "url" => $sapiUrl
        ]);
        $services = $storageApiClient->indexAction()["services"];
        $syrupApiUrl = null;
        foreach ($services as $service) {
            if ($service["id"] == 'syrup') {
                $syrupApiUrl = $service["url"];
                break;
            }
        }
        if(empty($syrupApiUrl)) {
            throw new ApplicationException("SyrupApi url is empty");
        }

        $config = [
            'super' => 'docker',
            'url' => $syrupApiUrl
        ];
        if (!is_null($token)) {
            $config['token'] = $token;
        }

        $client = Client::factory($config);
        return new self($client);
    }
}
