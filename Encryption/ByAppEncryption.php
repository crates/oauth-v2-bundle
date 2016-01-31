<?php
namespace Keboola\OAuthV2Bundle\Encryption;

use GuzzleHttp\Client as Guzzle,
    GuzzleHttp\Exception\ClientException;

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
        $url = $toConfig
            ? "https://syrup.keboola.com/docker/{$componentId}/configs/encrypt"
            : "https://syrup.keboola.com/docker/{$componentId}/encrypt";

        $client = new Guzzle();

        try {
            $result = $client->post(
                $url,
                [
                    'headers' => [
                        'X-StorageApi-Token' => $token,
                        'Content-Type' => 'text/plain'
                    ],
                    'body' => $secret
                ]
            );
        } catch(ClientException $e) {
            throw new UserException("Component based encryption of the app secret failed: " . $e->getMessage());
        }

        return (string) $result->getBody();
    }
}
