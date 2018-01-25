<?php
namespace Keboola\OAuthV2Bundle\Facebook;

use GuzzleHttp\Client,
    GuzzleHttp\Exception\ClientException;
use Keboola\Syrup\Exception\UserException;
use Keboola\Syrup\Exception\ApplicationException;
use Keboola\OAuth\AbstractOAuth;
use function Keboola\Utils\jsonDecode;

class OAuthFacebook extends AbstractOAuth
{
    const GRANT_TYPE = 'authorization_code';
    const DEFAULT_GRAPH_VERSION = 'v2.8';
    const BASE_AUTHORIZATION_URL = 'https://www.facebook.com';
    const BASE_GRAPH_API_URL = 'https://graph.facebook.com';

    /**
     * @todo NEEDS app_key/secret, auth_url, request_token_url (1.0)
     * 2.0 will need redir_url along with auth_url, app_key
     *
     * @param string $callbackUrl
     * @return array
     */
    public function createRedirectData($callbackUrl)
    {
        $state = bin2hex(random_bytes(16));
        $params = [
            'client_id' => $this->appKey,
            'state' => $state,
            'response_type' => 'code',
            'redirect_uri' => $callbackUrl,
            'scope' => $this->authUrl
        ];


        $loginUrl = self::BASE_AUTHORIZATION_URL . '/' . $this->getGraphApiVersion() . '/dialog/oauth?' . http_build_query($params, null, "&");
        $sessionData = ['state' => $state];
        return [
            'url' => $loginUrl,
            'sessionData' => $sessionData
        ];
    }

    private function getAccessToken($params) {
        $basicParams = [
            'client_id'          => $this->appKey,
            'client_secret'      => $this->appSecret
        ];
        $requestParams = array_merge($basicParams, $params);
        $requestUrl = self::BASE_GRAPH_API_URL . '/' . $this->getGraphApiVersion() . '/oauth/access_token?' . http_build_query($requestParams, null, "&");
        $guzzle = new Client();
        try {
            $response = $guzzle->get($requestUrl);
        } catch (ClientException $e) {
            $errCode = $e->getResponse()->getStatusCode();
            if ($errCode == 400) {
                $desc = jsonDecode($e->getResponse()->getBody(true), true);
                $message = empty($desc["error"]) ? "Unknown error from API." : $desc["error"]["message"];

                throw new UserException(
                    "OAuth authentication failed[{$errCode}]: {$message}",
                    null,
                    ['response' => $e->getResponse()->getBody()]
                );
            } else {
                throw $e;
            }
        }
        $token = (array) jsonDecode($response->getBody(true));
        return $token;
    }

    public function createToken($callbackUrl, array $sessionData, array $query)
    {
        if (empty($query['code'])) {
            throw new UserException("'code' not returned in query from the auth API!");
        }

        $queryState = isset($query['state']) ? $query['state'] : null;
        if (empty($queryState)) {
            throw new UserException("'state' not returned in query from the auth API!");
        }
        $sessionState = isset($sessionData['state']) ? $sessionData['state'] : null;
        if (empty($sessionState)) {
            throw new UserException("'state' param not set in session!");
        }

        if ($sessionState != $queryState) {
            throw new UserException("The 'state' param from the URL and session do not match.");
        }

        // Try to get an access token (using the authorization code grant)
        $params = [
            'redirect_uri' => $callbackUrl,
            'code' => $query['code']
        ];
        // will return short-lived access token
        $accessToken = $this->getAccessToken($params)["access_token"];
        $params = [
            "grant_type" => "fb_exchange_token",
            "fb_exchange_token" => $accessToken
        ];
        // will return long lived access token
        return $this->getAccessToken($params);
    }

    private function getGraphApiVersion() {
        $version = $this->tokenUrl;
        return empty($version) ? self::DEFAULT_GRAPH_VERSION : $version;
    }



}
