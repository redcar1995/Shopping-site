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

use Pimcore\Bundle\EcommerceFrameworkBundle\CartManager\ICart;
use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\ICheckoutStep as CheckoutManagerICheckoutStep;
use Pimcore\Bundle\EcommerceFrameworkBundle\IEnvironment;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\IProduct;

class TrackingManager implements ITrackingManager
{
    /**
     * @var ITracker[]
     */
    protected $trackers = [];

    /**
     * @var ITracker[]
     */
    protected $activeTrackerCache = [];

    /**
     * @var string
     */
    protected $cachedAssortmentTenant = null;

    /**
     * @var string
     */
    protected $cachedCheckoutTenant = null;

    /**
     * @var null|IEnvironment
     */
    protected $enviroment = null;

    /**
     * @param ITracker[] $trackers
     * @param IEnvironment $environment
     */
    public function __construct(array $trackers = [], IEnvironment $environment)
    {
        foreach ($trackers as $tracker) {
            $this->registerTracker($tracker);
        }

        $this->enviroment = $environment;
    }

    /**
     * Register a tracker
     *
     * @param ITracker $tracker
     */
    public function registerTracker(ITracker $tracker)
    {
        $this->trackers[] = $tracker;
    }

    /**
     * Get all registered trackers
     *
     * @return ITracker[]
     */
    public function getTrackers(): array
    {
        return $this->trackers;
    }

    /**
     * Get all for current tenants active trackers
     *
     * @return ITracker[]
     */
    public function getActiveTrackers(): array
    {
        $currentAssortmentTenant = $this->enviroment->getCurrentAssortmentTenant() ?: 'default';
        $currentCheckoutTenant = $this->enviroment->getCurrentCheckoutTenant() ?: 'default';

        if ($currentAssortmentTenant !== $this->cachedAssortmentTenant || $currentCheckoutTenant !== $this->cachedCheckoutTenant) {
            $this->cachedCheckoutTenant = $currentCheckoutTenant;
            $this->cachedAssortmentTenant = $currentAssortmentTenant;

            $this->activeTrackerCache = [];
            foreach ($this->trackers as $tracker) {
                $active = false;
                if (empty($tracker->getAssortmentTenants()) || in_array($currentAssortmentTenant, $tracker->getAssortmentTenants())) {
                    $active = true;
                }
                if (empty($tracker->getCheckoutTenants()) || in_array($currentCheckoutTenant, $tracker->getCheckoutTenants())) {
                    $active = true;
                }

                if ($active) {
                    $this->activeTrackerCache[] = $tracker;
                }
            }
        }

        return $this->activeTrackerCache;
    }

    /**
     * Tracks a category page view
     *
     * @param array|string $category One or more categories matching the page
     * @param mixed $page            Any kind of page information you can use to track your page
     */
    public function trackCategoryPageView($category, $page = null)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof ICategoryPageView) {
                $tracker->trackCategoryPageView($category, $page);
            }
        }
    }

    /**
     * Track product impression
     *
     * @implements IProductImpression
     *
     * @param IProduct $product
     */
    public function trackProductImpression(IProduct $product)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof IProductImpression) {
                $tracker->trackProductImpression($product);
            }
        }
    }

    /**
     * Track product view
     *
     * @param IProduct $product
     *
     * @implements IProductView
     */
    public function trackProductView(IProduct $product)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof IProductView) {
                $tracker->trackProductView($product);
            }
        }
    }

    /**
     * Track a cart update
     *
     * @param ICart $cart
     */
    public function trackCartUpdate(ICart $cart)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof ICartUpdate) {
                $tracker->trackCartUpdate($cart);
            }
        }
    }

    /**
     * Track product add to cart
     *
     * @param ICart $cart
     * @param IProduct $product
     * @param int|float $quantity
     */
    public function trackCartProductActionAdd(ICart $cart, IProduct $product, $quantity = 1)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof ICartProductActionAdd) {
                $tracker->trackCartProductActionAdd($cart, $product, $quantity);
            }
        }
    }

    /**
     * Track product add to cart
     *
     * @deprecated Use ICartProductActionAdd::trackCartProductActionAdd instead
     *
     * @param IProduct $product
     * @param int|float $quantity
     */
    public function trackProductActionAdd(IProduct $product, $quantity = 1)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof IProductActionAdd) {
                $tracker->trackProductActionAdd($product, $quantity);
            }
        }
    }

    /**
     * Track product remove from cart
     *
     * @param ICart $cart
     * @param IProduct $product
     * @param int|float $quantity
     */
    public function trackCartProductActionRemove(ICart $cart, IProduct $product, $quantity = 1)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof ICartProductActionRemove) {
                $tracker->trackCartProductActionRemove($cart, $product, $quantity);
            }
        }
    }

    /**
     * Track product remove from cart
     *
     * @deprecated Use ICartProductActionRemove::trackCartProductActionRemove instead
     *
     * @param IProduct $product
     * @param int|float $quantity
     */
    public function trackProductActionRemove(IProduct $product, $quantity = 1)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof IProductActionRemove) {
                $tracker->trackProductActionRemove($product, $quantity);
            }
        }
    }

    /**
     * Track start checkout with first step
     *
     * @implements ICheckoutComplete
     *
     * @param ICart $cart
     */
    public function trackCheckout(ICart $cart)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof ICheckout) {
                $tracker->trackCheckout($cart);
            }
        }
    }

    /**
     * Track checkout complete
     *
     * @implements ICheckoutComplete
     *
     * @param AbstractOrder $order
     */
    public function trackCheckoutComplete(AbstractOrder $order)
    {
        if ($order->getProperty('os_tracked')) {
            return;
        }

        // add property to order object in order to prevent multiple checkout complete tracking
        $order->setProperty('os_tracked', 'bool', true);
        $order->save();

        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof ICheckoutComplete) {
                $tracker->trackCheckoutComplete($order);
            }
        }
    }

    /**
     * Track checkout step
     *
     * @implements ICheckoutStep
     *
     * @param CheckoutManagerICheckoutStep $step
     * @param ICart $cart
     * @param null $stepNumber
     * @param null $checkoutOption
     */
    public function trackCheckoutStep(CheckoutManagerICheckoutStep $step, ICart $cart, $stepNumber = null, $checkoutOption = null)
    {
        foreach ($this->getActiveTrackers() as $tracker) {
            if ($tracker instanceof ICheckoutStep) {
                $tracker->trackCheckoutStep($step, $cart, $stepNumber, $checkoutOption);
            }
        }
    }
}
