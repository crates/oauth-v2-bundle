<?php

namespace Keboola\OAuthV2Bundle\Storage;

use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Keboola\Syrup\Encryption\BaseWrapper;

/**
 * Wrapper for encrypted session storage
 */
class Session
{
    /**
     * @var AttributeBag
     */
    protected $bag;

    /**
     * @var BaseWrapper
     */
    protected $encryptor;

    public function __construct(AttributeBag $bag, BaseWrapper $encryptor)
    {
        $this->bag = $bag;
        $this->encryptor = $encryptor;
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
