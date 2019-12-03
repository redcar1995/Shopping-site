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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\Tracker;

use AppBundle\Ecommerce\CartManager\Cart;
use AppBundle\Ecommerce\Checkout\B2B\Step\Billing;
use AppBundle\Ecommerce\Checkout\B2B\Step\Payment;
use AppBundle\Ecommerce\Checkout\B2B\Step\Shipping;
use AppBundle\Ecommerce\Checkout\B2B\TrackableStep;
use AppBundle\Ecommerce\Tracking\TrackingItemBuilder;
use AppBundle\Model\DataObject\ShopCategory;
use AppBundle\Service\Formatter\PriceFormatter;
use AppBundle\Traits\EnvironmentAware;
use Pimcore\Bundle\EcommerceFrameworkBundle\CartManager\CartInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\CheckoutStepInterface as CheckoutManagerCheckoutStepInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\ProductInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\CartProductActionAddInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\CartProductActionRemoveInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\CheckoutCompleteInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\CheckoutInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\CheckoutStepInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\ProductAction;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\ProductImpression;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\ProductImpressionInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\ProductViewInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\Tracker;
use Pimcore\Bundle\EcommerceFrameworkBundle\Tracking\Transaction;
use Pimcore\Bundle\EcommerceFrameworkBundle\Type\Decimal;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoogleTagManager extends Tracker implements
    ProductViewInterface,
    ProductImpressionInterface,
    CartProductActionAddInterface,
    CartProductActionRemoveInterface,
    CheckoutInterface,
    CheckoutStepInterface,
    CheckoutCompleteInterface
{

    const DEFERRED_DIMENSION_IMPRESSIONS = 'impressions';

    const DEFERRED_DIMENSIONS = [
        self::DEFERRED_DIMENSION_IMPRESSIONS
    ];

    /** @var string[] */
    protected $trackedCodes = [];

    /** @var [] */
    protected $deferred = [];

    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'template_prefix' => 'PimcoreEcommerceFrameworkBundle:Tracking/analytics/tagManager',
        ]);
    }

    public function trackProductImpression(ProductInterface $product)
    {
        $item = $this->trackingItemBuilder->buildProductImpressionItem($product);

        $this->addDeferredItem(self::DEFERRED_DIMENSION_IMPRESSIONS, $this->transformProductImpression($item));
    }

    public function trackProductView(ProductInterface $product)
    {
        $item = $this->trackingItemBuilder->buildProductViewItem($product);

        $call = [
            "ecommerce" => [
                "detail" => [
                    "products" => [
                        $this->transformProductAction($item),
                    ],
                ],
            ],
        ];

        $result = $this->renderCall($call);

        $this->trackCode($result);
    }

    public function trackCartProductActionAdd(CartInterface $cart, ProductInterface $product, $quantity = 1)
    {
        $item = $this->trackingItemBuilder->buildProductActionItem($product, $quantity = 1);

        $productArray = $this->transformProductAction($item);

        $call = [
            "ecommerce" => [
                "add" => [
                    "products" => [
                        $productArray,
                    ],
                ],
            ],
        ];

        $result = $this->renderCall($call);

        $this->trackCode($result);
    }

    public function trackCartProductActionRemove(CartInterface $cart, ProductInterface $product, $quantity = 1)
    {
        $item = $this->trackingItemBuilder->buildProductActionItem($product, $quantity);

        $productArray = $this->transformProductAction($item);

        $call = [
            "ecommerce" => [
                "remove" => [
                    "products" => [
                        $productArray,
                    ],
                ],
            ],
        ];

        $result = $this->renderCall($call);

        $this->trackCode($result);
    }

     public function trackCheckout(CartInterface $cart)
    {
        $items = $this->trackingItemBuilder->buildCheckoutItemsByCart($cart);

        $products = $this->transformCheckoutItems($items);

        $call = [
            "ecommerce" => [
                "checkout" => [
                    "actionField" => [
                        "step" => 1,
                    ],
                    "products" => $products,
                ],
            ],
        ];

        $result = $this->renderCall($call);

        $this->trackCode($result);
    }

    public function trackCheckoutStep(CheckoutManagerCheckoutStepInterface $step, CartInterface $cart, $stepNumber = null, $checkoutOption = null)
    {
        $items = $this->trackingItemBuilder->buildCheckoutItemsByCart($cart);

        $products = $this->transformCheckoutItems($items);

        $call = [
            "ecommerce" => [
                "checkout" => [
                    "actionField" => [
                        "step" => $stepNumber,
                        "option" => $checkoutOption,
                    ],
                    "products" => $products,
                ],
            ],
        ];

        $result = $this->renderCall($call);

        $this->trackCode($result);
    }

    public function trackCheckoutComplete(AbstractOrder $order)
    {
        $transaction = $this->trackingItemBuilder->buildCheckoutTransaction($order);
        $items = $this->trackingItemBuilder->buildCheckoutItems($order);

        $call = [
            "ecommerce" => [
                "currencyCode" => $order->getCurrency(),
                "purchase" => [
                    "actionField" => $this->transformTransaction($transaction),
                    "products" => $this->transformCheckoutItems($items),
                ],
            ],
        ];

        $result = $this->renderCall($call);

        $this->trackCode($result);
    }

    /**
     * Transform product action into data array
     *
     * @param ProductAction $item
     *
     * @return array
     */
    protected function transformProductAction(ProductAction $item)
    {
        return $this->filterNullValues(
            array_merge([
                "name" => $item->getName(),
                "id" => $item->getId(),
                "price" => $this->formatPrice($item->getPrice()),
                'brand' => $item->getBrand(),
                "category" => $item->getCategory(),
                "variant" => $item->getVariant(),
                "quantity" => $item->getQuantity(),
                "position" => $item->getPosition(),
                'coupon' => $item->getCoupon(),
            ],
                $item->getAdditionalAttributes())
        );
    }

    /**
     * Transform product action into data array
     *
     * @param ProductImpression $item
     *
     * @return array
     */
    protected function transformProductImpression(ProductImpression $item)
    {
        $data = $this->filterNullValues(
            array_merge([
                'id' => $item->getId(),
                'name' => $item->getName(),
                'category' => $item->getCategory(),
                'brand' => $item->getBrand(),
                'variant' => $item->getVariant(),
                'price' => $this->formatPrice($item->getPrice()),
                'list' => $item->getList(),
                'position' => $item->getPosition(),
            ],
                $item->getAdditionalAttributes())
        );

        return $data;
    }

    /**
     * Transform transaction into data array
     *
     * @param Transaction $transaction
     *
     * @return array
     */
    protected function transformTransaction(Transaction $transaction)
    {
        return $this->filterNullValues(
            array_merge([
                "id" => $transaction->getId(),
                "affiliation" => $transaction->getAffiliation(),
                "revenue" => $this->formatPrice($transaction->getTotal()),
                "tax" => $this->formatPrice($transaction->getTax()),
                "coupon" => $transaction->getCoupon(),
                "shipping" => $this->formatPrice($transaction->getShipping()),
            ],
                $transaction->getAdditionalAttributes())
        );
    }

    /**
     * @param array $items
     * @return array
     */
    protected function transformCheckoutItems(array $items)
    {
        return array_map(function (ProductAction $item) {
            return $this->transformProductAction($item);
        }, $items);
    }


    /**
     * @param $price
     * @return mixed
     */
    private function formatPrice($price = null)
    {
        return Decimal::fromNumeric($price)->asString();
    }


    /**
     * @param array $call
     * @return string
     */
    private function renderCall(?array $call): string
    {
        return $this->renderTemplate('call', [
            'call' => $call,
        ]);
    }

    protected function addDeferredItem(string $dimension, array $item)
    {
        $this->deferred[$dimension][] = $item;
    }

    protected function getDeferredItems(string $dimension)
    {
        return $this->deferred[$dimension];
    }

    protected function consolidateDeferredDimensions()
    {
        foreach (self::DEFERRED_DIMENSIONS as $dimension) {
            if ($items = $this->getDeferredItems($dimension)) {
                $call = [
                    "ecommerce" => [
                        $dimension => $items,
                    ],
                ];

                $result = $this->renderCall($call);

                $this->trackCode($result);
            }
        }
    }

    public function getTrackedCodes(): array
    {
        $this->consolidateDeferredDimensions();

        return $this->trackedCodes;
    }

    public function trackCode(string $code)
    {
        $this->trackedCodes[] = $code;
    }
}
