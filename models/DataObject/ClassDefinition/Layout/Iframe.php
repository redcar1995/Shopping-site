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

namespace Pimcore\Model\DataObject\ClassDefinition\Layout;

use Pimcore\Model;
use Pimcore\Model\DataObject\ClassDefinition\Data\LayoutDefinitionEnrichmentInterface;
use Pimcore\Model\DataObject\Concrete;

class Iframe extends Model\DataObject\ClassDefinition\Layout implements LayoutDefinitionEnrichmentInterface
{
    /**
     * Static type of this element
     *
     * @internal
     *
     * @var string
     */
    public $fieldtype = 'iframe';

    /**
     * @internal
     *
     * @var string
     */
    public $iframeUrl;

    /**
     * @internal
     *
     * @var string
     */
    public $renderingData;

    /**
     * @return string
     */
    public function getIframeUrl(): string
    {
        return $this->iframeUrl;
    }

    /**
     * @param string $iframeUrl
     */
    public function setIframeUrl(string $iframeUrl): void
    {
        $this->iframeUrl = $iframeUrl;
    }

    /**
     * @return string
     */
    public function getRenderingData(): string
    {
        return $this->renderingData;
    }

    /**
     * @param string $renderingData
     */
    public function setRenderingData(string $renderingData): void
    {
        $this->renderingData = $renderingData;
    }

    /**
     * {@inheritdoc}
     */
    public function enrichLayoutDefinition(/* ?Concrete */ $object, /* array */ $context = []) // : static
    {
        $this->width = $this->getWidth() ? $this->getWidth() : 500;
        $this->height = $this->getHeight() ? $this->getHeight() : 500;

        return $this;
    }
}
