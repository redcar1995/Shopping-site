<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Condition;

use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\ConditionInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\EnvironmentInterface;

/**
 * @deprecated will be removed in Pimcore 11
 */
class Token implements ConditionInterface
{
    /**
     * @var string
     */
    protected $token;

    /**
     * @param EnvironmentInterface $environment
     *
     * @return bool
     */
    public function check(EnvironmentInterface $environment)
    {
        trigger_deprecation(
            'pimcore/pimcore',
            '10.5',
            sprintf('%s is deprecated. It will be removed in Pimcore 11.', __CLASS__)
        );

        $token = $environment->getSession()->get('token');

        return $token === $this->getToken();
    }

    /**
     * @return string
     */
    public function toJSON()
    {
        trigger_deprecation(
            'pimcore/pimcore',
            '10.5',
            sprintf('%s is deprecated. It will be removed in Pimcore 11.', __CLASS__)
        );

        // basic
        $json = [
            'type' => 'Token', 'token' => $this->getToken(),
        ];

        return json_encode($json);
    }

    /**
     * @param string $string
     *
     * @return ConditionInterface
     */
    public function fromJSON($string)
    {
        trigger_deprecation(
            'pimcore/pimcore',
            '10.5',
            sprintf('%s is deprecated. It will be removed in Pimcore 11.', __CLASS__)
        );

        $json = json_decode($string);
        $this->setToken($json->token);

        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }
}
