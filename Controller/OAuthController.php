<?php

namespace Keboola\OAuthV2Bundle\Controller;

use Keboola\Syrup\Exception\SyrupComponentException,
    Keboola\Syrup\Exception\UserException,
    Keboola\Syrup\Encryption\BaseWrapper;
use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Doctrine\DBAL\Connection;
use Keboola\OAuth\OAuth10,
    Keboola\OAuth\OAuth20;
use Keboola\OAuthV2Bundle\Storage\Session;

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
     */
    public function initAction($componentId, Request $request)
    {
        $session = $this->initSession($request);

        $api = $this->connection->fetchAssoc("SELECT `app_key`, `app_secret`, `auth_url`, `token_url`, `request_token_url`, `oauth_version` FROM `consumers` WHERE `component_id` = :componentId", ['componentId' => $componentId]);
        if (empty($api)) {
            throw new UserException("Api '{$componentId}' details not found in the OAuth consumer database");
        }

        $api['app_secret'] = $this->getEncryptor()->decrypt($api['app_secret']);

        $oAuth = $api['oauth_version'] == '1.0' ? new OAuth10($api) : new OAuth20($api);

        $result = $oAuth->createRedirectData($this->getCallbackUrl($request));

        if (!empty($result['sessionData'])) {
            foreach($result['sessionData'] as $key => $val) {
                if (!is_scalar($val)) {
                    throw new SyrupComponentException("Session value must be scalar");
                }

                $session->setEncrypted($key, $val);
            }
        }

        return $this->redirect($result['url']);
    }

    public function callbackAction($componentId, Request $request)
    {
//         $session = $this->createSession();

//         var_dump($session, $request);
// $bag = new AttributeBag('_' . parent::BAG_NAME);
// $bag->setName(parent::BAG_NAME);
// $this->getSymfonySession()->registerBag($bag);
// if ($reset || !$this->sessionBag) {
//     $name = str_replace("-", "", parent::BAG_NAME);
//     /** @var Session $session */
//     $session = $this->container->get('session');
//     $bag = new AttributeBag('_' . parent::BAG_NAME);
//     $bag->setName($name);
//     $session->registerBag($bag);
//
//     $this->sessionBag = $session->getBag($name);
// // }
// var_dump($this->sessionBag);

var_dump($this->createSession()->getBag()->all());
// var_dump($this->getSymfonySession()->);
// $this->createSession()->set('cccc', 'ddddd');
// var_dump($this->createSession()->getBag());
die();

    }

    public function testInitAction($componentId, Request $request)
    {
        $redirect = $this->initAction($componentId, $request);

        $session = $this->createSession();
        $session->set('returnData', true);
        $session->set('returnUrl', null);

        return $redirect;
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
        $this->connection = $this->getConnection();
        $this->encryptor = $this->getEncryptor();
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
