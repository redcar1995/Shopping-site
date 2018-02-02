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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\Order\Listing\Filter;

use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\IOrderList;
use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\IOrderListFilter;

class Product implements IOrderListFilter
{
    /**
     * @var \Pimcore\Model\DataObject\Concrete
     */
    protected $product;

    /**
     * @param \Pimcore\Model\DataObject\Concrete $product
     */
    public function __construct(\Pimcore\Model\DataObject\Concrete $product)
    {
        $this->product = $product;
    }

    /**
     * @param IOrderList $orderList
     *
     * @return IOrderListFilter
     */
    public function apply(IOrderList $orderList)
    {
        $ids = [
            $this->product->getId()
        ];

        $variants = $this->product->getChildren([
            \Pimcore\Model\DataObject\Concrete::OBJECT_TYPE_VARIANT
        ]);

        /** @var \Pimcore\Model\DataObject\Concrete $variant */
        foreach ($variants as $variant) {
            $ids[] = $variant->getId();
        }

        $orderList->addCondition('orderItem.product__id IN (?)', $ids);

        return $this;
    }
}
