<?php
namespace Keboola\OAuthV2Bundle\Encryption;

use Crypto;

class SelfEncryption
{
    /**
     * @var string Binary
     */
    protected $key;

    /**
     * @param string $key Base64 encoded key
     */
    public function __construct($key)
    {
        $this->key = base64_decode($key);
    }

    public function encrypt($string)
    {
        return Crypto::encrypt($string, $this->key);
    }

    public function decrypt($string)
    {
        return Crypto::decrypt($string, $this->key);
    }
}
