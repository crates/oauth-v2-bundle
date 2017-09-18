<?php

namespace Keboola\OAuthV2Bundle\Controller;

use Keboola\OAuthV2Bundle\Quickbooks\OAuthQuickbooks;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\Syrup\Exception\UserException;
use Keboola\Syrup\Encryption\BaseWrapper;
use Keboola\StorageApi\Client as StorageApi;
use Symfony\Component\HttpFoundation\JsonResponse,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Doctrine\DBAL\Connection;
use Keboola\OAuth\OAuth10,
    Keboola\OAuth\OAuth20,
    Keboola\OAuth\AbstractOAuth;
use Keboola\OAuthV2Bundle\Storage\Session,
    Keboola\OAuthV2Bundle\Encryption\ByAppEncryption;
use function Keboola\Utils\jsonDecode;
use Keboola\OAuthV2Bundle\Facebook\OAuthFacebook;

/**
 * @todo Use 1 controller and initialize with 1.0 or 2.0 class, that'll take care
 * of all differences in each call (init and callback)
 */
class OAuthController extends SessionController
{
    /**
     * @var BaseWrapper
     */
    protected $encryptor;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * Initialize the call and redirect to authorization website
     * @param $componentId
     * @param Request $request
     * @param bool $validateRequest
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function initAction($componentId, Request $request, $validateRequest = true)
    {
        $session = $this->initSession($request);

        if ($validateRequest) {
            $this->checkParams($session->getBag());
        }

        $oAuth = $this->getOAuth($componentId, $session);

        $result = $oAuth->createRedirectData($this->getCallbackUrl($request));

        if (!empty($result['sessionData'])) {
            $session->setEncrypted('oauth_data', serialize($result['sessionData']));
        }

        return $this->redirect($result['url']);
    }

    public function callbackAction($componentId, Request $request)
    {
        $session = $this->container->get('oauth.session');

        $oAuth = $this->getOAuth($componentId, $session);

        $sessionOAuthData = $session->getBag()->has('oauth_data')
            ? unserialize($session->getEncrypted('oauth_data'))
            : [];

        $result = $oAuth->createToken(
            $this->getCallbackUrl($request),
            $sessionOAuthData,
            $request->query->all()
        );

        if ($session->getBag()->has('returnData') && $session->get('returnData')) {
            return new JsonResponse($result, 200, [
                "Content-Type" => "application/json",
                "Access-Control-Allow-Origin" => "*",
                "Connection" => "close"
            ]);
        }

        $this->storeResult($result, $componentId, $session);

        if (!$session->getBag()->has('returnUrl') || empty($session->get('returnUrl'))) {
            throw new UserException("Cannot redirect; return URL not found");
        }

        return $this->redirect($session->get('returnUrl'));
    }

    public function testInitAction($componentId, Request $request)
    {
        $redirect = $this->initAction($componentId, $request, false);

        $session = $this->container->get('oauth.session');
        $session->set('returnData', true);
        $session->set('returnUrl', null);

        return $redirect;
    }

    /**
     * @param mixed $result
     * @param string $componentId
     * @param Session $session
     */
    protected function storeResult($result, $componentId, Session $session)
    {
        $authorizedFor = $session->getBag()->has('authorizedFor') ? $session->get('authorizedFor') : '';
        $token = $session->getEncrypted('token');

        $tokenDetail = $this->getStorageApiToken($token);
        $creator = [
            'id' => $tokenDetail['id'],
            'description' => $tokenDetail['description']
        ];

        $data = json_encode($result);
        try {
            $client = new Client([
                "token" => $token,
                "url" => $this->container->getParameter('storage_api.url')
            ]);
            $encryptor = ByAppEncryption::factory($client);
            $this->connection->insert('credentials', [
                'id' => $session->get('id'),
                'component_id' => $componentId,
                'project_id' => $tokenDetail['owner']['id'],
                'creator' => json_encode($creator),
                'data' => $encryptor->encrypt($data, $componentId, true),
                'authorized_for' => $authorizedFor,
                'created' => date("Y-m-d H:i:s")
            ]);
        } catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $id = $session->get('id');
            throw new UserException("Credentials '{$id}' for component '{$componentId}' already exist!");
        }
    }

    /**
     * @param string $componentId
     * @param Session $session
     * @return AbstractOAuth
     */
    protected function getOAuth($componentId, Session $session)
    {
        $api = $this->connection->fetchAssoc("SELECT `app_key`, `app_secret`, `auth_url`, `token_url`, `request_token_url`, `oauth_version` FROM `consumers` WHERE `component_id` = :componentId", ['componentId' => $componentId]);
        if (empty($api)) {
            throw new UserException("Api '{$componentId}' details not found in the OAuth consumer database");
        }

        $api['app_secret'] = $this->getEncryptor()->decrypt($api['app_secret']);

        if ($session->getBag()->has('appKey') && $session->getBag()->has('appSecret')) {
            $api['app_key'] = $session->getBag()->get('appKey');
            $api['app_secret'] = $session->getBag()->get('appSecret');
        }
        if ($session->getBag()->has('authUrl')) {
            $api['auth_url'] = $session->getBag()->get('authUrl');
        }
        if ($session->getBag()->has('tokenUrl')) {
            $api['token_url'] = $session->getBag()->get('tokenUrl');
        }
        if ($session->getBag()->has('requestTokenUrl')) {
            $api['request_token_url'] = $session->getBag()->get('requestTokenUrl');
        }

        $api = $this->buildAuthUrls($api, $session);

        switch ($api['oauth_version']) {
        case '1.0':
            return new OAuth10($api);
        case '2.0':
            return new OAuth20($api);
        case 'facebook':
            return new OAuthFacebook($api);
        case 'quickbooks':
            return new OAuthQuickbooks($api);
        default:
            throw new UserException("Unknown oauth version: '{$api['oauth_version']}' ");
        }
    }

    /**
     * Replace placeholders from config data (eg 3rd level domain)
     *
     * auth_url: https://%%domain%%.zendesk.com/...; token_url: https://%%something%%.zendesk.com/..
     * in session:
     * {'domain': 'keboola', 'something': 'maybe-something-else'}
     *
     * @param array $api
     * @return array
     */
    protected function buildAuthUrls(array $api, Session $session)
    {
        $userDataJson = $session->get('userData');

        $userData = empty($userDataJson) ? [] : jsonDecode($userDataJson, true);

        array_walk($api, function(&$url, $apiKey) use($userData) {
            if (substr($apiKey, -4) != '_url') {
                return;
            }

            preg_match_all('/%%(.*?)%%/', $url, $matches);

            foreach($matches[1] as $match) {
                if (in_array($match, ['app_key', 'client_id', 'redirect_uri', 'oauth_token'])) {
                    continue;
                }

                if (!isset($userData[$match])) {
                    throw new UserException("The URL requires 'userData' to contain '{$match}'");
                }

                $url = str_replace('%%' . $match . '%%', $userData[$match], $url);
            }
        });

        return $api;
    }

    /**
     * Get the current URL (used for redirect URL generation)
     *
     * @param Request $request
     * @return string
     */
    protected function getSelfUrl(Request $request)
    {
        return $request->getSchemeAndHttpHost()
            . $request->getBaseUrl()
            . $request->getPathInfo();
    }

    /**
     * Get the callback URL
     *
     * @param Request $request
     * @return string
     */
    protected function getCallbackUrl(Request $request)
    {
        $selfUrl = $this->getSelfUrl($request);
        if (substr($selfUrl, -9) == "/callback") {
            return $selfUrl;
        } else {
            return $selfUrl . "/callback";
        }
    }

    public function preExecute(Request $request)
    {
        parent::preExecute($request);
        $this->connection = $this->getConnection();
        $this->encryptor = $this->getEncryptor();
    }

    /**
     * @param string $token
     * @return StorageApi
     */
    protected function getStorageApiToken($token)
    {
        $sapi = new StorageApi([
            "token" => $token,
            "userAgent" => 'oauth-v2',
            "url" => $this->container->getParameter('storage_api.url'),
        ]);

        try {
            $tokenInfo = $sapi->verifyToken();
        } catch(ClientException $e) {
            throw new UserException($e->getMessage());
        }

        return $tokenInfo;
    }

    /**
     * @param AttributeBag $params
     * @throws UserException
     */
    protected function checkParams(AttributeBag $params)
    {
        foreach(['token', 'id'] as $name) {
            if (!$params->has($name) || empty($params->get($name))) {
                throw new UserException("Missing parameter '{$name}'");
            }
        }
    }

    /**
     * @fixme Code below is duplicated from ManageController
     */

    /**
     * @return Connection
     */
    protected function getConnection()
    {
        return $this->getDoctrine()->getConnection('oauth_providers');
    }
}
