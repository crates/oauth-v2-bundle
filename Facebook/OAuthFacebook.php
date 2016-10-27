<?php
namespace Keboola\OAuthV2Bundle\Facebook;

use GuzzleHttp\Client,
    GuzzleHttp\Exception\ClientException;
use Keboola\Utils\Utils;
use Keboola\Syrup\Exception\UserException;
use Keboola\Syrup\Exception\ApplicationException;
use Keboola\OAuth\AbstractOAuth;

use Facebook\Facebook;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Exceptions\FacebookResponseException;

class OAuthFacebook extends AbstractOAuth
{
    const GRANT_TYPE = 'authorization_code';

    /**
     * @todo NEEDS app_key/secret, auth_url, request_token_url (1.0)
     * 2.0 will need redir_url along with auth_url, app_key
     */
    public function createRedirectData($callbackUrl)
    {
        $provider = $this->getProvider();
        $helper = $provider->getRedirectLoginHelper();
        $permissions = explode(',', $this->authUrl); //stored in db under auth_url column
        $loginUrl = $helper->getLoginUrl($callbackUrl, $permissions);
        return ['url' => $loginUrl];
    }

    public function createToken($callbackUrl, array $sessionData, array $query)
    {
        if (empty($query['code'])) {
            throw new UserException("'code' not returned in query from the auth API!");
        }
        $provider = $this->getProvider();
        $helper = $provider->getRedirectLoginHelper();
        // CSRF
        $_SESSION['FBRLH_state'] = $_GET['state'];

        // Try to get an access token (using the authorization code grant)
        try {
            $accessToken = $helper->getAccessToken();
        } catch(FacebookResponseException $e) {
            // When Graph returns an error
            throw new ApplicationException('Graph returned an error: ' . $e->getMessage(), $e);
        } catch(FacebookSDKException $e) {
            // When validation fails or other local issues
            throw new ApplicationException('Facebook SDK returned an error: ' . $e->getMessage(), $e);
        }
        catch (Exception $e) {
            throw $e;
        }

        if (!isset($accessToken)) {
            if ($helper->getError()) {
                $msg = 'Unauthorized: Error:' . $helper->getError() . "\n";
                $msg .= 'Error Code:' . $helper->getErrorCode() . "\n";
                $msg .= 'Error Reason:' . $helper->getErrorReason() . "\n";
                $msg .= 'Error Description:' . $helper->getErrorDescription();
                throw new UserException($msg);
            } else {
                throw new UserException('No accesss token retrieved. Bad Request');
            }
        }
        // The OAuth 2.0 client handler helps us manage access tokens
        $oAuth2Client = $provider->getOAuth2Client();

        if (! $accessToken->isLongLived()) {
          // Exchanges a short-lived access token for a long-lived one
          try {
            $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
          } catch (FacebookSDKException $e) {
              throw new ApplicationException('Error getting long-lived access token: ' . $helper->getMessage());
          }
        }
        $result = [
            "token" => $accessToken->getValue(),
            "expires" => $accessToken->getExpiresAt(),
            "isLongLived" => $accessToken->isLongLived()
        ];
        return $result;
    }

    private function getProvider() {
        $provider = new Facebook([
            'app_id'          => $this->appKey,
            'app_secret'      => $this->appSecret,
            // 'redirectUri'       => $callbackUrl,
            'default_graph_version' => 'v2.7',
            'persistent_data_handler'=>'session'
        ]);
        return $provider;
    }



}
