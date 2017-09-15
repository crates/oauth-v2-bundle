<?php

namespace Keboola\OAuthV2Bundle\Storage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Keboola\Syrup\Encryption\BaseWrapper;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;

/**
 * Wrapper for encrypted session storage
 */
class Session
{
    const BAG_NAME = 'oauth_v2';

    /** @var AttributeBag */
    protected $bag;

    /** @var BaseWrapper */
    protected $encryptor;

    protected $host;

    protected $user;

    protected $password;

    protected $db;

    public function __construct(BaseWrapper $encryptor, $host, $user, $password, $db)
    {
        $this->encryptor = $encryptor;
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->db = $db;

        $this->initSessionBag();
    }

    /**
     * Init OAuth session bag
     *
     * @return AttributeBag
     */
    protected function initSessionBag()
    {
        if (!$this->bag) {
            $name = str_replace("-", "", self::BAG_NAME);

            /** @var SymfonySession $session */
            $session = $this->getSession();
            $bag = new AttributeBag('_' . str_replace("-", "_", self::BAG_NAME));
            $bag->setName($name);
            $session->registerBag($bag);

            $this->bag = $session->getBag($name);
        }

        return $this->bag;
    }

    public function init(Request $request)
    {
        $this->bag->clear();

        foreach($request->request->all() as $key => $value) {
            $this->set($key, $value);
        }

        $this->set(
            "returnUrl",
            $request->request->has('returnUrl')
                ? $request->request->get('returnUrl')
                : $request->server->get("HTTP_REFERER")
        );

        if ($request->request->has('token')) {
            $this->setEncrypted('token', $request->request->get('token'));
        }

        return $this;
    }

    protected function getSession() {
        $dsn = "mysql:host=". $this->host . ";dbname=" . $this->db;
        $pdo = new \PDO($dsn, $this->user, $this->password);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $storage = new NativeSessionStorage(array(), new PdoSessionHandler($pdo));
        $session = new SymfonySession($storage);
        return $session;
    }

    public function set($key, $data)
    {
        $this->bag->set($key, $data);
    }

    public function get($key)
    {
        return $this->bag->get($key);
    }

    public function setEncrypted($key, $data)
    {
        $encryptedData = $this->encryptor->encrypt($data);
        $this->bag->set($key, $encryptedData);
    }

    public function getEncrypted($key)
    {
        $encryptedData = $this->bag->get($key);
        return $this->encryptor->decrypt($encryptedData);
    }

    /**
     * @return AttributeBag
     */
    public function getBag()
    {
        return $this->bag;
    }

    /**
     * @return BaseWrapper
     */
    public function getEncryptor()
    {
        return $this->encryptor;
    }
}
