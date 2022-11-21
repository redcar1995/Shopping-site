<?php
declare(strict_types=1);

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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\Order\Listing\Filter;

use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\OrderListFilterInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\OrderListInterface;
use Pimcore\Model\Element\ElementInterface;

class CustomerObject implements OrderListFilterInterface
{
    protected ElementInterface $customer;

    public function __construct(ElementInterface $customer)
    {
        $this->customer = $customer;
    }

    public function apply(OrderListInterface $orderList): OrderListFilterInterface
    {
        $orderList->addCondition('order.customer__id = ?', $this->customer->getId());

        return $this;
    }
}
