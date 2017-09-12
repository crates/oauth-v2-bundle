<?php
namespace Keboola\OAuthV2Bundle\Quickbooks;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Keboola\OAuth\OAuth20;
use Keboola\Syrup\Exception\UserException;
use function Keboola\Utils\jsonDecode;

class OAuthQuickbooks extends OAuth20
{
    public function createToken($callbackUrl, array $sessionData, array $query)
    {
        if (empty($query['code'])) {
            throw new UserException("'code' not returned in query from the auth API!");
        }

        $guzzle = new Client();
        try {
            $response = $guzzle->post(
                $this->tokenUrl,
                [
                    'form_params' => [
                        'client_id' => $this->appKey,
                        'client_secret' => $this->appSecret,
                        'grant_type' => self::GRANT_TYPE,
                        'redirect_uri' => $callbackUrl,
                        'code' => $query['code']
                    ],
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => sprintf('Basic %s', base64_encode($this->appKey . ':' . $this->appSecret))
                    ],
                ]
            );
        } catch (ClientException $e) {
            $errCode = $e->getResponse()->getStatusCode();
            if ($errCode == 400) {
                $desc = jsonDecode($e->getResponse()->getBody(true), true);
                $code = empty($desc["code"]) ? 0 : $desc["code"];
                $message = empty($desc["error_message"]) ? "Unknown error from API." : $desc["error_message"];

                throw new UserException(
                    "OAuth authentication failed[{$code}]: {$message}",
                    null,
                    [
                        'response' => $e->getResponse()->getBody()
                    ]
                );
            } else {
                throw $e;
            }
        }

        return jsonDecode($response->getBody(true));
    }
}

