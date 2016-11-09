<?php
namespace Keboola\OAuthV2Bundle\Encryption;

use Keboola\Syrup\Client,
    Keboola\Syrup\ClientException;
use Keboola\Syrup\Exception\UserException;

class ByAppEncryption
{
    /**
     * @param string $secret String to encrypt
     * @param string $componentId
     * @param string $token SAPI token
     * @return string Encrypted $secret by application $componentId
     */
    public static function encrypt($secret, $componentId, $token = null, $toConfig = false, $storageApiClient)
    {
        $components = $storageApiClient->indexAction()["components"];
        $syrupApiUrl = null;
        foreach ($components as $component) {
            if ($component["id"] == 'docker') {
                // strip the component uri to syrup api uri
                // eg https://syrup.keboola.com/docker/docker-demo => https://syrup.keboola.com
                $syrupApiUrl = substr($component["uri"], 0, strpos($component["uri"], "/", 8));
                break;
            }
        }
        $config = [
            'super' => 'docker',
            "url" => $syrupApiUrl
        ];
        if (!is_null($token)) {
            $config['token'] = $token;
        }

        $client = Client::factory($config);

        try {
            return $client->encryptString($componentId, $secret, $toConfig ? ["path" => "configs"] : []);
        } catch(ClientException $e) {
            throw new UserException("Component based encryption of the app secret failed: " . $e->getMessage());
        }
    }
}
