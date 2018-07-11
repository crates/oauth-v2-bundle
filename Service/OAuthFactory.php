<?php
/**
 * Author: miro@keboola.com
 * Date: 21/09/2017
 */

namespace Keboola\OAuthV2Bundle\Service;

use Keboola\OAuth\OAuth10;
use Keboola\OAuth\OAuth20;
use Keboola\OAuthV2Bundle\Facebook\OAuthFacebook;
use Keboola\OAuthV2Bundle\Quickbooks\OAuthQuickbooks;
use Keboola\Syrup\Exception\UserException;

class OAuthFactory
{
    public function create($params)
    {
        switch ($params['oauth_version']) {
            case '1.0':
                return new OAuth10($params);
            case '1.0-rsa':
                $params['signature_method'] = OAUTH_SIG_METHOD_RSASHA1;
                return new OAuth10($params);
            case '2.0':
                return new OAuth20($params);
            case 'facebook':
                return new OAuthFacebook($params);
            case 'quickbooks':
                return new OAuthQuickbooks($params);
            default:
                throw new UserException("Unknown oauth version: '{$params['oauth_version']}'");
         }
    }
}
