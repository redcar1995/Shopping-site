<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\Tracking;

interface ITracker
{
    /**
     * Returns assortment tenants the tracker should be activated for.
     *
     * @return array
     */
    public function getAssortmentTenants(): array;

    /**
     * Returns checkout tenants the tracker should be activated for.
     *
     * @return array
     */
    public function getCheckoutTenants(): array;

}
