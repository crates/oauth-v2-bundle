<?php

namespace Keboola\OAuthV2Bundle\Controller;

use	Keboola\Syrup\Controller\ApiController,
	Keboola\Syrup\Exception\UserException;
use	Symfony\Component\HttpFoundation\Response,
	Symfony\Component\HttpFoundation\JsonResponse,
	Symfony\Component\HttpFoundation\Request;
use	Keboola\Utils\Utils;
use Keboola\ManageApi\Client,
    Keboola\ManageApi\ClientException;
use GuzzleHttp\Client as Guzzle;

class ManageController extends ApiController
{
	public function getAction($api, $id, Request $request)
	{
		$token = $this->storageApi->verifyToken();

		/**
		 * @var \Doctrine\DBAL\Connection
		 */
		$conn = $this->getConnection();

		$creds = $conn->fetchAssoc("SELECT `data`, `description`, `consumer_key`, `oauth_version`, `creator` FROM `credentials` WHERE `project` = '{$token['owner']['id']}' AND `id` = '{$id}' AND `api` = :api", ['api' => $api]);

		if (empty($creds['data'])) {
			throw new UserException("No data found for api: {$api} with id: {$id} in project {$token['owner']['name']}");
		}

		// TODO makes consumer_secret available to anyone with a token, is that quite desirable?
		// It's needed for OAuth 1.0 clients :-/
// 		$content = Utils::json_decode($request->getContent());
// 		if (!empty($content->includeApiDetail)) {
// 			$consumerTable = $creds['oauth_version'] == '2.0' ? 'consumers_v2' : 'consumers_v1';
// 			$consumerColumn = $creds['oauth_version'] == '2.0' ? 'client_id' : 'consumer_key';
// 			$apiDetail = $conn->fetchAssoc("SELECT * FROM `{$consumerTable}` WHERE `id` = '{$api}' AND `{$consumerColumn}` = '{$creds['consumer_key']}'");
//
// 			$data = Utils::json_decode($creds['data']);
// 			$data->apiDetail = $apiDetail;
// 			$creds['data'] = json_encode($data);
// 		}
		return new JsonResponse($creds, 200, [
			"Content-Type" => "application/json",
			"Access-Control-Allow-Origin" => "*",
			"Connection" => "close"
		]);
	}

	public function deleteAction($api, $id)
	{
		$token = $this->storageApi->verifyToken();

		if (empty($token['admin'])) {
			throw new UserException("Forbidden: Only project admin can delete existing credentials.");
		}

		$conn = $this->getConnection();

		// A check for delete rights would come here..IF WE HAD ONE!

		$result = $conn->delete('credentials', [
			'project' => $token['owner']['id'],
			'id' => $id,
			'api' => $api
		]);

		if ($result == 1) {
			return new Response(null, 204, [
				"Content-Type" => "application/json",
				"Access-Control-Allow-Origin" => "*",
				"Connection" => "close"
			]);
		} else {
			throw new UserException("Error deleting credentials for api: {$api} with id: {$id} in project {$token['owner']['name']}");
		}
	}

	/**
	 *
	 */
	public function listAction()
	{
		$token = $this->storageApi->verifyToken();

		$conn = $this->getConnection();

		$consumers = $this->getConsumers();

		return new JsonResponse($consumers, 200, [
			"Content-Type" => "application/json",
			"Access-Control-Allow-Origin" => "*",
			"Connection" => "close"
		]);
	}

	public function addAction(Request $request)
	{
        if (!$this->checkScope('oauth:manage', $request)) {
            throw new UserException("Insufficient permissions to add API");
        }

        $sapiToken = $this->storageApi->verifyToken();

        $conn = $this->getConnection();

        $api = $this->validateApiConfig(Utils::json_decode($request->getContent()));
        $api->app_secret_docker = $this->encryptByApp($api->app_secret, $api->component_id, $sapiToken['token']);
        $api->app_secret = $this->encryptBySelf($api->app_secret);

        try {
            $conn->insert('consumers', (array) $api);
        } catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            throw new UserException("Consumer '{$api->component_id}' already exists!");
        }

        return new JsonResponse(
            [
                'status' => 'created',
                'component_id' => $api->component_id
            ],
            201,
            [
                "Content-Type" => "application/json",
                "Access-Control-Allow-Origin" => "*",
                "Connection" => "close"
            ]
        );
	}

	/**
	 * @param string $secret String to encrypt
	 * @param string $componentId
     * @param string $token SAPI token
	 * @return string Encrypted $secret by application $componentId
	 */
    protected function encryptByApp($secret, $componentId, $token)
    {
        $url = "https://syrup.keboola.com/docker/{$componentId}/encrypt";

        $client = new Guzzle();

        try {
            $result = $client->post(
                $url,
                [
                    'headers' => [
                        'X-StorageApi-Token' => $token,
                        'Content-Type' => 'text/plain'
                    ]
                ]
            );
        } catch(\GuzzleHttp\Exception\ClientException $e) {
            throw new UserException("Component based encryption of the app secret failed: " . $e->getMessage());
        }

        return (string) $result->getBody();
    }

    /**
     * @todo
     */
    protected function encryptBySelf($secret)
    {
        return $secret;
    }

    /**
     * @param object $api
     * @return object
     */
	protected function validateApiConfig(\stdClass $api)
	{
        if (empty($api->oauth_version) || !in_array($api->oauth_version, ['1.0', '2.0'])) {
            throw new UserException("'oauth_version' must be either '1.0' or '2.0'");
        }

        if ($api->oauth_version == '1.0' && empty($api->request_token_url)) {
            throw new UserException("'request_token_url' is required for OAuth 1.0 APIs");
        }

        /**
         * 0 = optional
         * 1 = required for 1.0
         * 2 = required for 2.0
         * 3 = required for 1.0 & 2.0
         */
        $cols = [
            'component_id'=> 3,
            'auth_url'=> 3,
            'token_url'=> 3,
            'request_token_url'=> 1,
            'app_key'=> 3,
            'app_secret'=> 3,
//             'app_secret_docker'=> 3, // created in the ctrlr
            'friendly_name'=> 3,
            'oauth_version'=> 3
        ];

        $validated = new \stdClass;
        foreach($cols as $col => $flag) {
            $validated->{$col} = '';

            if (
                ($flag == 3)
                || ($flag == 1 && $api->oauth_version == '1.0')
                || ($flag == 2 && $api->oauth_version == '2.0')
            ) {
                if (empty($api->{$col})) {
                    throw new UserException("Missing parameter '{$col}'.");
                }

                $validated->{$col} = $api->{$col};
            }
        }

        return $validated;
	}

	/**
	 * @param string $scope
	 * @param Request $request
     * @return bool
	 */
	protected function checkScope($scope, Request $request)
	{
        if (!$request->headers->get("X-KBC-ManageApiToken")) {
            throw new UserException("Manage API Token not set.");
        }
        $client = new Client([
            "token" => $request->headers->get("X-KBC-ManageApiToken")
        ]);

        try {
            $token = $client->verifyToken();
        } catch(ClientException $e) {
            throw new UserException("Error validating Manage token: " . $e->getMessage());
        }

        return is_array($token['scopes']) && in_array($scope, $token['scopes']);
	}

	protected function getConsumers()
	{
		return $this->getConnection()->fetchAll("SELECT `component_id`, `app_key`, `friendly_name`, `oauth_version` FROM `consumers`");
	}

	/**
	 * @return \Doctrine\DBAL\Connection
	 */
	protected function getConnection()
	{
		return $this->getDoctrine()->getConnection('oauth_providers');
	}
}
