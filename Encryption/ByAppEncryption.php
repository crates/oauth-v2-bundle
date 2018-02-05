<?php
namespace Keboola\OAuthV2Bundle\Encryption;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Keboola\OAuth\Exception\UserException;
use Keboola\StorageApi\HandlerStack;
use Keboola\Syrup\Exception\ApplicationException;
use Keboola\StorageApi\Client as StorageApi;

class ByAppEncryption
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    private $projectId;

    /**
     * ByAppEncryption constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param $secret
     * @param $componentId
     * @param bool $toConfig
     * @return string
     * @throws UserException
     */
    public function encrypt($secret, $componentId, $toConfig = false)
    {
        try {
            if ($toConfig) {
                $request = new Request(
                    'POST',
                    "/docker/encrypt?componentId=$componentId&projectId=" . $this->projectId,
                    ["Content-Type" => "text/plain"],
                    $secret
                );
            } else {
                $request = new Request(
                    'POST',
                    "/docker/encrypt?componentId=$componentId",
                    ["Content-Type" => "text/plain"],
                    $secret
                );
            }
            $response = $this->client->send($request);
            return (string)$response->getBody();
        } catch (GuzzleException $e) {
            throw new UserException("Encryption of the app secret failed: " . $e->getMessage());
        }
    }

    /**
     * @param StorageApi $storageApiClient
     * @return ByAppEncryption
     */
    public static function factory(StorageApi $storageApiClient)
    {
        $services = $storageApiClient->indexAction()["services"];
        $dockerRunner = null;
        foreach ($services as $service) {
            if ($service["id"] == 'docker-runner') {
                $dockerRunner = $service["url"];
                break;
            }
        }
        if (empty($dockerRunner)) {
            throw new ApplicationException("Docker Runner URL is empty");
        }

        $handlerStack = HandlerStack::create([
            'backoffMaxTries' => 10,
        ]);
        $client = new Client([
            'base_uri' => $dockerRunner,
            'handler' => $handlerStack,
        ]);
        $self = new self($client);
        $projectId = $storageApiClient->verifyToken()['owner']['id'];
        $self->setProjectId($projectId);

        return $self;
    }

    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }
}
