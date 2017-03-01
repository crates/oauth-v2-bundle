<?php
/**
 * Created by PhpStorm.
 * User: ondra
 * Date: 28/02/17
 * Time: 19:13
 */
namespace Keboola\OAuthV2Bundle\Service;

use Keboola\OAuthV2Bundle\Encryption\ByAppEncryption;
use Keboola\Syrup\Service\StorageApi\StorageApiService;

class DockerEncryptor
{
    /**
     * @var StorageApiService
     */
    protected $storageApiService;

    /**
     * @var ByAppEncryption
     */
    protected $encryptor;

    public function __construct(StorageApiService $storageApiService)
    {
        $this->storageApiService = $storageApiService;
    }

    /**
     * @param ByAppEncryption $encryptor
     * @return $this
     */
    public function setEncryptor($encryptor)
    {
        $this->encryptor = $encryptor;
        return $this;
    }

    /**
     * @return ByAppEncryption
     */
    public function getEncryptor()
    {
        if (!$this->encryptor) {
            $this->encryptor = ByAppEncryption::factory($this->storageApiService->getClient());
        }
        return $this->encryptor;
    }

}
