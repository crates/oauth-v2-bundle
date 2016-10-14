<?php
namespace Keboola\OAuthV2Bundle\Facebook;

use GuzzleHttp\Client,
    GuzzleHttp\Exception\ClientException;
use Keboola\Utils\Utils;
use Keboola\Syrup\Exception\UserException;
use Keboola\OAuth\AbstractOAuth;

use League\OAuth2\Client\Provider\Facebook;

class OAuthFacebook extends AbstractOAuth
{
    const GRANT_TYPE = 'authorization_code';

    /**
     * @todo NEEDS app_key/secret, auth_url, request_token_url (1.0)
     * 2.0 will need redir_url along with auth_url, app_key
     */
    public function createRedirectData($callbackUrl)
    {
        $provider = $this->getProvider($callbackUrl);
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['email'],
        ]);
        return ['url' => $authUrl];
    }


    public function createToken($callbackUrl, array $sessionData, array $query)
    {
        if (empty($query['code'])) {
            throw new UserException("'code' not returned in query from the auth API!");
        }
        $provider = $this->getProvider($callbackUrl);
        // Try to get an access token (using the authorization code grant)

        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $query['code']
            ]);
            $token = $provider->getLongLivedAccessToken($token);
        } catch (Exception $e) {
            throw $e;
        }

        return $token;
        // return Utils::json_decode($response->getBody(true));
    }

    private function getProvider($callbackUrl) {
        $provider = new Facebook([
            'clientId'          => $this->appKey,
            'clientSecret'      => $this->appSecret,
            'redirectUri'       => $callbackUrl,
            'graphApiVersion'   => 'v2.7',
        ]);
        return $provider;
    }



}
