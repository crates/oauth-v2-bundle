<?php

namespace Keboola\OAuthV2Bundle\Controller;

use	Keboola\Syrup\Controller\ApiController,
	Keboola\Syrup\Exception\UserException;
use	Symfony\Component\HttpFoundation\Response,
	Symfony\Component\HttpFoundation\JsonResponse,
	Symfony\Component\HttpFoundation\Request;
use	Keboola\Utils\Utils;

class CredentialsController extends ApiController
{
	public function getAction($componentId, $id, Request $request)
	{
		$token = $this->storageApi->verifyToken();

		/**
		 * @var \Doctrine\DBAL\Connection
		 */
		$conn = $this->getConnection();

		$creds = $conn->fetchAssoc(
            "SELECT `data`, `authorized_for`, `creator`, `created` FROM `credentials` WHERE `project_id` = '{$token['owner']['id']}' AND `id` = :id AND `component_id` = :componentId",
            ['id' => $id, 'componentId' => $componentId]
        );

		if (empty($creds['data'])) {
			throw new UserException("No data found for api: {$componentId} with id: {$id} in project {$token['owner']['name']}");
		}

        $consumer = $conn->fetchAssoc(
            "SELECT `app_key`, `app_secret_docker`, `oauth_version` FROM `consumers` WHERE `component_id` = :componentId",
            ['componentId' => $componentId]
        );

        if (empty($consumer)) {
            throw new UserException("Component '{$componentId}' not found!");
        }


		return new JsonResponse(
            [
                'id' => $id,
                'authorized_for' => $creds['authorized_for'],
                'creator' => Utils::json_decode($creds['creator']),
                'created' => $creds['created'],
                '#data' => $creds['data'],
                'oauthVersion' => $consumer['oauth_version'],
                'appKey' => $consumer['app_key'],
                'appSecret' => $consumer['app_secret_docker']
            ],
            200,
            [
                "Content-Type" => "application/json",
                "Access-Control-Allow-Origin" => "*",
                "Connection" => "close"
            ]
        );
	}

	public function deleteAction($componentId, $id)
	{
		$token = $this->storageApi->verifyToken();

		if (empty($token['admin'])) {
			throw new UserException("Forbidden: Only project admin can delete existing credentials.");
		}

		$conn = $this->getConnection();

		// A check for delete rights would come here..IF WE HAD ONE!

		$result = $conn->delete(
            'credentials',
            [
                'project_id' => $token['owner']['id'],
                'id' => $id,
                'component_id' => $componentId
            ]
        );

		if ($result == 1) {
			return new Response(null, 204, [
				"Content-Type" => "application/json",
				"Access-Control-Allow-Origin" => "*",
				"Connection" => "close"
			]);
		} else {
			throw new UserException("Error deleting credentials for api: {$componentId} with id: {$id} in project: {$token['owner']['name']}");
		}
	}

	public function listAction($componentId)
	{
		$token = $this->storageApi->verifyToken();

		$conn = $this->getConnection();

		$result = $conn->fetchAll(
			"SELECT
				`authorized_for`,
				`id`,
				`creator`,
				`created`
			FROM `credentials`
			WHERE `project_id` = '{$token['owner']['id']}'
				AND `component_id` = :componentId",
			['componentId' => $componentId]
		);

		foreach($result as &$record) {
            $record['creator'] = Utils::json_decode($record['creator']);
        }

		return new JsonResponse($result, 200, [
			"Content-Type" => "application/json",
			"Access-Control-Allow-Origin" => "*",
			"Connection" => "close"
		]);
	}

	/**
	 * @return \Doctrine\DBAL\Connection
	 */
	protected function getConnection()
	{
		return $this->getDoctrine()->getConnection('oauth_providers');
	}
}
