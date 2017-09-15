<?php

namespace Keboola\OAuthV2Bundle\Controller;

use Keboola\Syrup\Controller\BaseController,
    Keboola\Syrup\Encryption\BaseWrapper;
use Symfony\Component\HttpFoundation\Request;
use Keboola\OAuthV2Bundle\Storage\Session;

/**
 * @todo Use 1 controller and initialize with 1.0 or 2.0 class, that'll take care
 * of all differences in each call (init and callback)
 */
class SessionController extends BaseController
{
    /**
     * Initialize session and check/set mandatory fields.
     * @param Request $request
     * @return Session
     */
    protected function initSession(Request $request)
    {
        $session = $this->container->get('oauth.session');
        $session->init($request);
        return $session;
    }

    /**
     * @fixme should be in a parent
     * @return BaseWrapper
     */
    protected function getEncryptor()
    {
        return $this->container->get('syrup.encryption.base_wrapper');
    }
}
