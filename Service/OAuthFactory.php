<?php
/**
 * Author: miro@keboola.com
 * Date: 21/09/2017
 */

namespace Keboola\OAuthV2Bundle\Service;

use Keboola\Syrup\Exception\UserException;

class OAuthFactory
{
    public function create($params)
    {
        $oauthVersion = $params['oauth_version'];
        $namespace = 'Keboola\\OAuthV2Bundle\\';
        $oauthName = ucfirst(str_replace('.', '', $oauthVersion));
        if (!in_array($oauthName, ['10', '20'])) {
            $namespace .= $oauthName;
        }
        $className = $namespace . '\\OAuth' . $oauthName;

        if (class_exists($className)) {
            return new $className($params);
        }

        throw new UserException(sprintf("Unknown oauth version: '%s'", $oauthVersion));
    }
}