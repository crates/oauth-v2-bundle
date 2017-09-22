<?php

namespace Keboola\OAuthV2Bundle\Controller;

use Keboola\Syrup\Controller\ApiController,
    Keboola\Syrup\Exception\UserException;
use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\JsonResponse,
    Symfony\Component\HttpFoundation\Request;
use function Keboola\Utils\jsonDecode;

class CredentialsController extends ApiController
{
    public function getAction($componentId, $id, Request $request)
    {
        $token = $this->storageApi->verifyToken();

        /**
         * @var \Doctrine\DBAL\Connection
         */
        $conn = $this->getConnection();

        $creds = $conn->fetchAssoc("
            SELECT `data`, `authorized_for`, `creator`, `created`, `app_key`, `app_secret_docker` 
            FROM `credentials` 
            WHERE `project_id` = '{$token['owner']['id']}' AND `id` = :id AND `component_id` = :componentId
        ",
            ['id' => $id, 'componentId' => $componentId]
        );

        if (empty($creds['data'])) {
            throw new UserException("No data found for api: {$componentId} with id: {$id} in project {$token['owner']['name']}");
        }

        $consumer = $conn->fetchAssoc("
            SELECT `app_key`, `app_secret_docker`, `oauth_version` 
            FROM `consumers` 
            WHERE `component_id` = :componentId
        ",
            ['componentId' => $componentId]
        );

        if (empty($consumer)) {
            throw new UserException("Component '{$componentId}' not found!");
        }

        return new JsonResponse(
            [
                'id' => $id,
                'authorizedFor' => $creds['authorized_for'],
                'creator' => jsonDecode($creds['creator']),
                'created' => $creds['created'],
                '#data' => $creds['data'],
                'oauthVersion' => $consumer['oauth_version'],
                'appKey' => empty($creds['app_key']) ? $consumer['app_key'] : $creds['app_key'],
                '#appSecret' => empty($creds['app_secret_docker']) ? $consumer['app_secret_docker'] : $creds['app_secret_docker']
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
                `authorized_for` AS `authorizedFor`,
                `id`,
                `creator`,
                `created`
            FROM `credentials`
            WHERE `project_id` = '{$token['owner']['id']}'
                AND `component_id` = :componentId",
            ['componentId' => $componentId]
        );

        foreach($result as &$record) {
            $record['creator'] = jsonDecode($record['creator']);
        }

        return new JsonResponse($result, 200, [
            "Content-Type" => "application/json",
            "Access-Control-Allow-Origin" => "*",
            "Connection" => "close"
        ]);
    }

    public function addAction($componentId, Request $request)
    {
        $credentials = $this->validateCredentials(jsonDecode($request->getContent()));
        $conn = $this->getConnection();

        $consumer = $conn->fetchAssoc(
            "SELECT `app_key`, `app_secret_docker`, `oauth_version` FROM `consumers` WHERE `component_id` = :componentId",
            ['componentId' => $componentId]
        );

        if (empty($consumer)) {
            throw new UserException("Component '{$componentId}' not found!");
        }

        $token = $this->storageApi->verifyToken();
        $creator = [
            'id' => $token['id'],
            'description' => $token['description']
        ];

        $data = json_encode($credentials->data);
        $encryptor = $this->container->get('oauth.docker_encryptor')->getEncryptor();
        $dataEncrypted = $encryptor->encrypt($data, $componentId, true);
        $created = date("Y-m-d H:i:s");

        try {
            $conn->insert('credentials', [
                'id' => $credentials->id,
                'component_id' => $componentId,
                'project_id' => $token['owner']['id'],
                'creator' => json_encode($creator),
                'data' => $dataEncrypted,
                'authorized_for' => $credentials->authorizedFor,
                'created' => $created
            ]);
        } catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            throw new UserException("Credentials '{$credentials->id}' for component '{$componentId}' already exist!");
        }

        return new JsonResponse(
            [
                'id' => $credentials->id,
                'authorizedFor' => $credentials->authorizedFor,
                'creator' => $creator,
                'created' => $created,
                '#data' => $dataEncrypted,
                'oauthVersion' => $consumer['oauth_version'],
                'appKey' => $consumer['app_key'],
                '#appSecret' => $consumer['app_secret_docker']
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
     * @return \Doctrine\DBAL\Connection
     */
    protected function getConnection()
    {
        return $this->getDoctrine()->getConnection('oauth_providers');
    }

    private function validateCredentials(\stdClass $credentials)
    {
        $cols = ['id', 'data', 'authorizedFor'];

        $validated = new \stdClass();
        foreach ($cols as $col) {
            if (empty($credentials->{$col})) {
                throw new UserException("Missing parameter '{$col}'.");
            }
            $validated->{$col} = $credentials->{$col};
        }

        return $validated;
    }
}
