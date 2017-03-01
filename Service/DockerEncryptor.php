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

    public function __construct(StorageApiService $storageApiService)
    {
        $this->setStorageApiService($storageApiService);
    }

    /**
     * @param StorageApiService $service
     */
    public function setStorageApiService(StorageApiService $service)
    {
        $this->storageApiService = $service;
    }

    /**
     * @return StorageApiService
     */
    public function getStorageApiService()
    {
        return $this->storageApiService;
    }

    /**
     * @return ByAppEncryption
     */
    public function getEncryptor()
    {
        return ByAppEncryption::factory($this->getStorageApiService()->getClient());
    }

}
