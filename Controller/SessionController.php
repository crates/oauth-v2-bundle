<?php

namespace Keboola\OAuthV2Bundle\Controller;

use Keboola\Syrup\Exception\SyrupComponentException,
    Keboola\Syrup\Exception\UserException,
    Keboola\Syrup\Controller\BaseController,
    Keboola\Syrup\Encryption\BaseWrapper;
use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag,
    Symfony\Component\HttpFoundation\Session\Session as SymfonySession,
    Symfony\Component\HttpFoundation\ParameterBag;
use Doctrine\DBAL\Connection;
use Keboola\OAuthV2Bundle\Storage\Session;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * @todo Use 1 controller and initialize with 1.0 or 2.0 class, that'll take care
 * of all differences in each call (init and callback)
 */
class SessionController extends BaseController
{
    const BAG_NAME = 'oauth_v2';

    /**
     * @var SymfonySession
     */
    protected $symfonySession;

    /**
     * @var AttributeBag
     */
    protected $sessionBag;

    /**
     * Init OAuth session bag
     *
     * @return Session
     */
    protected function createSession()
    {
        $bag = $this->initSessionBag();

        return new Session(
            $bag,
            $this->getEncryptor()
        );
    }

    /**
     * Init OAuth session bag
     *
     * @param bool $reset
     * @return AttributeBag
     */
    protected function initSessionBag()
    {
        if (!$this->sessionBag) {
            $name = str_replace("-", "", self::BAG_NAME);
            /** @var Session $session */
            $session = $this->getSession();
            $bag = new AttributeBag('_' . str_replace("-", "_", self::BAG_NAME));
            $bag->setName($name);
            $session->registerBag($bag);

            $this->sessionBag = $session->getBag($name);
        }

        return $this->sessionBag;
    }


    protected function registerBag(SymfonySession $session)
    {
        $bag = new AttributeBag('_' . self::BAG_NAME);
        $bag->setName(self::BAG_NAME);
        $session->registerBag($bag);
        $this->sessionBag = $session->getBag(self::BAG_NAME);
    }

    /**
     * Initialize session and check/set mandatory fields.
     * @param Request $request
     * @return Session
     */
    protected function initSession(Request $request)
    {
//         $this->checkParams($request->request);
        $session = $this->createSession();
        $session->getBag()->clear();

        foreach($request->request->all() as $key => $value) {
            $session->set($key, $value);
        }

        $session->set(
            "returnUrl",
            $request->request->has('returnUrl')
                ? $request->request->get('returnUrl')
                : $request->server->get("HTTP_REFERER")
        );

        if ($request->request->has('token')) {
            $session->setEncrypted('token', $request->request->get('token'));
        }

        return $session;
    }

    /**
     * @return SymfonySession
     */
    protected function getSymfonySession()
    {
        if (empty($this->symfonySession)) {
            $this->symfonySession = $this->getSession();
            $this->registerBag($this->symfonySession);
        }

        return $this->symfonySession;
    }

    protected function getSession() {
        $host = $this->container->getParameter('oauth.providers_db.endpoint');
        $user = $this->container->getParameter('oauth.providers_db.user');
        $password = $this->container->getParameter('oauth.providers_db.password');
        $dbname = $this->container->getParameter('oauth.providers_db.db');

        $dsn = "mysql:host=". $host . ";dbname=" . $dbname;
        $pdo = new \PDO($dsn, $user, $password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $storage = new NativeSessionStorage(array(), new PdoSessionHandler($pdo));
        $session = new SymfonySession($storage);
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
