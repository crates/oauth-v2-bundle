<?php

namespace Keboola\OAuthV2Bundle\Controller;

use Keboola\Syrup\Controller\BaseController,
    Keboola\Syrup\Exception\UserException,
    Keboola\Syrup\Encryption\BaseWrapper;
use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\JsonResponse,
    Symfony\Component\HttpFoundation\Request;
use Keboola\ManageApi\Client,
    Keboola\ManageApi\ClientException;

class ManageController extends BaseController
{
    protected $defaultResponseHeaders = [
        "Content-Type" => "application/json",
        "Access-Control-Allow-Origin" => "*",
        "Connection" => "close"
    ];

    /**
     * List all supported consumers
     */
    public function listAction()
    {
        $conn = $this->getConnection();

        $consumers = $conn->fetchAll(
            "SELECT `component_id`, `app_key`, `friendly_name`, `oauth_version`
            FROM `consumers`"
        );

        return new JsonResponse($consumers, 200, $this->defaultResponseHeaders);
    }

    /**
     * Get detail for 'componentId' consumer
     */
    public function getAction($componentId)
    {
        $conn = $this->getConnection();
        $detail = $this->getConnection()->fetchAssoc("SELECT `component_id`, `friendly_name`, `app_key`, `app_secret_docker`, `oauth_version` FROM `consumers` WHERE `component_id` = :componentId", ['componentId' => $componentId]);

        // TODO exception?
        if (empty($detail)) {
            return new JsonResponse(
                [
                    'error' => "Component '{$componentId}' not found.",
                    'code' => 'notFound'
                ],
                404,
                $this->defaultResponseHeaders
            );
        }

        return new JsonResponse($detail, 200, $this->defaultResponseHeaders);
    }

    /**
     * Add API to `consumers` and encrypt the secret
     */
    public function addAction(Request $request)
    {

        $api = $this->validateApiConfig(\Keboola\Utils\jsonDecode($request->getContent()));

        $encryptor = $this->container->get('oauth.docker_encryptor')->getEncryptor();
        $api->app_secret_docker = $encryptor->encrypt($api->app_secret, $api->component_id, false);
        $api->app_secret = $this->encryptBySelf($api->app_secret);

        try {
            $conn = $this->getConnection();
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
            $this->defaultResponseHeaders
        );
    }

    /**
     * Delete existing component record
     */
    public function deleteAction($componentId, Request $request)
    {
        $conn = $this->getConnection();

        try {
            $result = $conn->delete('consumers', ['component_id' => $componentId]);
        } catch(\Exception $e) {
            throw new UserException("Unknown error deleting consumer '{$componentId}'.", $e);
        }

        if ($result === 0) {
            throw new UserException("Delete of consumer '{$componentId}' failed: ComponentID doesn't exist in OAuth API.");
        } elseif ($result === 1) {
            return new JsonResponse([], 204, $this->defaultResponseHeaders);
        }

        throw new UserException("Unknown error deleting consumer '{$componentId}'.");
    }

    /**
     * Update API to `consumers` and encrypt the secret if present
     */
    public function updateAction($componentId, Request $request)
    {
        $updateData = \Keboola\Utils\jsonDecode($request->getContent(), true);
        if (isset($updateData["component_id"])) {
            throw new UserException("Cannot update component_id.");
        }

        // encrypt, if app_secret is present
        $updateAppSecret = false;
        if (isset($updateData['app_secret'])) {
            $updateAppSecret = true;
        }

        $conn = $this->getConnection();
        $query = "SELECT * FROM `consumers` WHERE `component_id` = :componentId";
        $detail = $this->getConnection()->fetchAssoc($query, ['componentId' => $componentId]);

        foreach($updateData as $key => $val) {
            $detail[$key] = $val;
        }
        $detail = $this->validateApiConfig((object) $detail);

        if ($updateAppSecret) {
            $encryptor = $this->container->get('oauth.docker_encryptor')->getEncryptor();
            $detail->app_secret_docker = $encryptor->encrypt(
                $detail->app_secret,
                $componentId,
                false
            );
            $detail->app_secret = $this->encryptBySelf($detail->app_secret);

        }

        try {
            $conn->update('consumers', (array) $detail, ['component_id' => $componentId]);
        } catch(\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            throw new UserException("Consumer '$componentId' cannot be updated: " . $e->getMessage());
        }

        $detail = $this->getConnection()->fetchAssoc(
            "SELECT `component_id`, `friendly_name`, `app_key`, `app_secret_docker`, `oauth_version` FROM `consumers` WHERE `component_id` = :componentId",
            ['componentId' => $componentId]
        );

        return new JsonResponse($detail, 200, $this->defaultResponseHeaders);
    }


    public function preExecute(Request $request)
    {
        if (!$this->checkScope('oauth:manage', $request)) {
            throw new UserException("Insufficient manage permissions");
        }
    }

    /**
     * @param string $secret
     * @return string binary encrypted string
     */
    protected function encryptBySelf($secret)
    {
        return $this->getEncryptor()->encrypt($secret);
    }

    /**
     * @param \stdClass $api
     * @return \stdClass
     */
    protected function validateApiConfig(\stdClass $api)
    {
        if (empty($api->oauth_version) || !in_array($api->oauth_version, ['1.0', '2.0', 'facebook'])) {
            throw new UserException("'oauth_version' must be either '1.0', '2.0' or 'facebook'");
        }

        if ($api->oauth_version == '1.0' && empty($api->request_token_url)) {
            throw new UserException("'request_token_url' is required for OAuth 1.0 APIs");
        }

        /**
         * 0 = optional
         * 1 = required for 1.0
         * 2 = required for 2.0
         * 3 = required for 1.0 & 2.0 & facebook
         * 4 = required for 1.0 & 2.0 but not for facebook
         */
        $cols = [
            'component_id'=> 3,
            'auth_url'=> 4,
            'token_url'=> 4,
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
                || ($flag == 4 && $api->oauth_version != 'facebook')
            ) {
                if (empty($api->{$col})) {
                    throw new UserException("Missing parameter '{$col}'.");
                }

                $validated->{$col} = $api->{$col};
            }
        }
        // extra check for fb auth - the permissions field that will
        // be stored under auth_url column in db
        if ($api->oauth_version == 'facebook')
        {
            $permissionsDbAlias = 'auth_url';
            $versionDbAlias = 'token_url';
            if (empty($api->permissions)) {
                throw new UserException("Missing parameter 'permissions'.");
            }
            $validated->{$permissionsDbAlias} = $api->permissions;

            if (empty($api->graph_api_version)) {
                $validated->{$versionDbAlias} = '';
            } else {
                $validated->{$versionDbAlias} = $api->graph_api_version;
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
            "token" => $request->headers->get("X-KBC-ManageApiToken"),
            "url" => $this->container->getParameter('storage_api.url')
        ]);

        try {
            $token = $client->verifyToken();
        } catch(ClientException $e) {
            throw new UserException("Error validating Manage token: " . $e->getMessage());
        }

        return is_array($token['scopes']) && in_array($scope, $token['scopes']);
    }

    /**
     * @return BaseWrapper
     */
    protected function getEncryptor()
    {
        return $this->container->get('syrup.encryption.base_wrapper');
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    protected function getConnection()
    {
        return $this->getDoctrine()->getConnection('oauth_providers');
    }

//     protected function getSelfEncryption()
//     {
//         return new SelfEncryption($this->container->getParameter('oauth.defuse_encryption_key'));
//     }
}
