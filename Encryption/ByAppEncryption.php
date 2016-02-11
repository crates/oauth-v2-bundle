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
            'runId' => 'CURRENT_RUNID', // TODO
            'super' => 'docker'
        ]);

        try {
            return $client->encryptString($componentId, $secret, $toConfig ? ["path" => "configs"] : []);
        } catch(ClientException $e) {
            throw new UserException("Component based encryption of the app secret failed: " . $e->getMessage());
        }


//         $url = $toConfig
//             ? "https://syrup.keboola.com/docker/{$componentId}/configs/encrypt"
//             : "https://syrup.keboola.com/docker/{$componentId}/encrypt";
//
//         $client = new Guzzle();
//
//         try {
//             $result = $client->post(
//                 $url,
//                 [
//                     'headers' => [
//                         'X-StorageApi-Token' => $token,
//                         'Content-Type' => 'text/plain'
//                     ],
//                     'body' => $secret
//                 ]
//             );
//         } catch(ClientException $e) {
//             throw new UserException("Component based encryption of the app secret failed: " . $e->getMessage());
//         }
//
//         return (string) $result->getBody();
    }
}
