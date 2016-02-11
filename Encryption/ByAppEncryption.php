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
   public static function encrypt($secret, $componentId, $token, $toConfig = false)
    {
        $client = Client::factory([
            'token' => $token,
            'super' => 'docker'
        ]);

        try {
            return $client->encryptString($componentId, $secret, $toConfig ? ["path" => "configs"] : []);
        } catch(ClientException $e) {
            throw new UserException("Component based encryption of the app secret failed: " . $e->getMessage());
        }
    }
}
