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

namespace Pimcore\Routing;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Data\UrlSlug;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Route;

/**
 * @internal
 */
final class DataObjectRoute extends Route implements RouteObjectInterface
{
    /**
     * @var Concrete|null
     */
    protected ?Concrete $object;

    /**
     * @var UrlSlug|null
     */
    protected ?UrlSlug $slug;

    /**
     * @return Concrete|null
     */
    public function getObject(): ?Concrete
    {
        return $this->object;
    }

    /**
     * @param Concrete $object
     *
     * @return $this
     */
    public function setObject(Concrete $object): self
    {
        $this->object = $object;

        return $this;
    }

    /**
     * @return UrlSlug|null
     */
    public function getSlug(): ?UrlSlug
    {
        return $this->slug;
    }

    /**
     * @param UrlSlug $slug
     *
     * @return $this
     */
    public function setSlug(UrlSlug $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?object
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteKey(): ?string
    {
        if ($this->object) {
            return sprintf('data_object_%d_%s_%s', $this->object->getId(), $this->getDefault('site'), $this->getPath());
        }

        return null;
    }
}
