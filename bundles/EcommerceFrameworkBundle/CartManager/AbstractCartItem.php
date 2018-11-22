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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\CartManager;

use Pimcore\Bundle\EcommerceFrameworkBundle\AvailabilitySystem\IAvailability;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractSetProduct;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractSetProductEntry;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\ICheckoutable;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\IPrice;
use Pimcore\Bundle\EcommerceFrameworkBundle\PriceSystem\IPriceInfo;
use Pimcore\Model\DataObject\AbstractObject;

abstract class AbstractCartItem extends \Pimcore\Model\AbstractModel implements ICartItem
{
    /**
     * flag needed for preventing call modified on cart when loading cart from storage
     *
     * @var bool
     */
    protected $isLoading = false;

    /**
     * @var ICheckoutable
     */
    protected $product;
    protected $productId;
    protected $itemKey;
    protected $count;
    protected $comment;
    protected $parentItemKey = '';

    protected $subItems = null;

    /**
     * @var ICart
     */
    protected $cart;
    protected $cartId;

    /**
     * @var int unix timestamp
     */
    protected $addedDateTimestamp;

    public function __construct()
    {
        $this->setAddedDate(new \DateTime());
    }

    public function setCount($count, bool $fireModified = true)
    {
        if ($this->count != $count && $this->getCart() && !$this->isLoading && $fireModified) {
            $this->getCart()->modified();
        }
        $this->count = $count;
    }

    public function getCount()
    {
        return $this->count;
    }

    /**
     * @param ICheckoutable $product
     * @param bool $fireModified
     */
    public function setProduct(ICheckoutable $product, bool $fireModified = true)
    {
        if ((empty($product) || $this->productId != $product->getId()) && $this->getCart() && !$this->isLoading && $fireModified) {
            $this->getCart()->modified();
        }
        $this->product = $product;
        $this->productId = $product->getId();
    }

    /**
     * @return ICheckoutable
     */
    public function getProduct()
    {
        if ($this->product) {
            return $this->product;
        }
        $this->product = AbstractObject::getById($this->productId);

        return $this->product;
    }

    /**
     * @param ICart $cart
     */
    public function setCart(ICart $cart)
    {
        $this->cart = $cart;
        $this->cartId = $cart->getId();
    }

    /**
     * @return ICart
     */
    abstract public function getCart();

    /**
     * @return mixed
     */
    public function getCartId()
    {
        return $this->cartId;
    }

    /**
     * @param $cartId
     */
    public function setCartId($cartId)
    {
        $this->cartId = $cartId;
    }

    /**
     * @return int
     */
    public function getProductId()
    {
        if ($this->productId) {
            return $this->productId;
        }

        return $this->getProduct()->getId();
    }

    /**
     * @param $productId
     */
    public function setProductId($productId)
    {
        if ($this->productId != $productId && $this->getCart() && !$this->isLoading) {
            $this->getCart()->modified();
        }
        $this->productId = $productId;
        $this->product = null;
    }

    /**
     * @param $parentItemKey
     */
    public function setParentItemKey($parentItemKey)
    {
        $this->parentItemKey = $parentItemKey;
    }

    /**
     * @return string
     */
    public function getParentItemKey()
    {
        return $this->parentItemKey;
    }

    /**
     * @param $itemKey
     */
    public function setItemKey($itemKey)
    {
        $this->itemKey = $itemKey;
    }

    /**
     * @return mixed
     */
    public function getItemKey()
    {
        return $this->itemKey;
    }

    /**
     * @param  \Pimcore\Bundle\EcommerceFrameworkBundle\CartManager\ICartItem[] $subItems
     *
     * @return void
     */
    public function setSubItems($subItems)
    {
        if ($this->getCart() && !$this->isLoading) {
            $this->getCart()->modified();
        }

        foreach ($subItems as $item) {
            $item->setParentItemKey($this->getItemKey());
        }
        $this->subItems = $subItems;
    }

    /**
     * @return IPrice
     */
    public function getPrice(): IPrice
    {
        return $this->getPriceInfo()->getPrice();
    }

    /**
     * @return IPriceInfo
     */
    public function getPriceInfo(): IPriceInfo
    {
        if ($this->getProduct() instanceof AbstractSetProduct) {
            $priceInfo = $this->getProduct()->getOSPriceInfo($this->getCount(), $this->getSetEntries());
        } else {
            $priceInfo = $this->getProduct()->getOSPriceInfo($this->getCount());
        }

        if ($priceInfo instanceof \Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\IPriceInfo) {
            $priceInfo->getEnvironment()->setCart($this->getCart());
            $priceInfo->getEnvironment()->setCartItem($this);
        }

        return $priceInfo;
    }

    /**
     * @return IAvailability
     */
    public function getAvailabilityInfo()
    {
        if ($this->getProduct() instanceof AbstractSetProduct) {
            return $this->getProduct()->getOSAvailabilityInfo($this->getCount(), $this->getSetEntries());
        } else {
            return $this->getProduct()->getOSAvailabilityInfo($this->getCount());
        }
    }

    /**
     * @return \Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractSetProductEntry[]
     */
    public function getSetEntries()
    {
        $products = [];
        if ($this->getSubItems()) {
            foreach ($this->getSubItems() as $item) {
                $products[] = new AbstractSetProductEntry($item->getProduct(), $item->getCount());
            }
        }

        return $products;
    }

    /**
     * @param string $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @return IPrice
     */
    public function getTotalPrice(): IPrice
    {
        return $this->getPriceInfo()->getTotalPrice();
    }

    /**
     * @param \DateTime|null $date
     */
    public function setAddedDate(\DateTime $date = null)
    {
        if ($date) {
            $this->addedDateTimestamp = $date->getTimestamp();
        } else {
            $this->addedDateTimestamp = null;
        }
    }

    /**
     * @return \DateTime|null
     */
    public function getAddedDate()
    {
        $datetime = null;
        if ($this->addedDateTimestamp) {
            $datetime = new \DateTime();
            $datetime->setTimestamp($this->addedDateTimestamp);
        }

        return $datetime;
    }

    /**
     * @return int
     */
    public function getAddedDateTimestamp()
    {
        return $this->addedDateTimestamp;
    }

    /**
     * @param int $time
     */
    public function setAddedDateTimestamp($time)
    {
        $this->addedDateTimestamp = $time;
    }

    /**
     * get item name
     *
     * @return string
     */
    public function getName()
    {
        return $this->getProduct()->getOSName();
    }

    /**
     * Flag needed for preventing call modified on cart when loading cart from storage
     * only for internal usage
     *
     * @param bool $isLoading
     *
     * @internal
     */
    public function setIsLoading(bool $isLoading)
    {
        $this->isLoading = $isLoading;
    }
}
