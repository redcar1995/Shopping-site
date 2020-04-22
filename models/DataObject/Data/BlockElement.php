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
 * @category   Pimcore
 * @package    Object
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\Data;

use DeepCopy\DeepCopy;
use Pimcore\Cache\Runtime;
use Pimcore\Model\AbstractModel;
use Pimcore\Model\DataObject\OwnerAwareFieldInterface;
use Pimcore\Model\DataObject\Traits\OwnerAwareFieldTrait;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service;
use Pimcore\Model\Version\ElementDescriptor;
use Pimcore\Model\Version\MarshalMatcher;
use Pimcore\Model\Version\UnmarshalMatcher;

class BlockElement extends AbstractModel implements OwnerAwareFieldInterface
{
    use OwnerAwareFieldTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var bool
     */
    protected $needsRenewReferences = false;

    /**
     * BlockElement constructor.
     *
     * @param string $name
     * @param string $type
     * @param mixed $data
     */
    public function __construct($name, $type, $data)
    {
        $this->name = $name;
        $this->type = $type;
        $this->data = $data;
        $this->markMeDirty();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        if ($name != $this->name) {
            $this->name = $name;
            $this->markMeDirty();
        }
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        if ($type != $this->type) {
            $this->type = $type;
            $this->markMeDirty();
        }
    }

    /**
     * @return mixed
     */
    public function getData()
    {

        if ($this->needsRenewReferences) {
            $container = null;
            $this->needsRenewReferences = false;
            $this->renewReferences();
        }

        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
        $this->markMeDirty();
    }

    protected function renewReferences()
    {
        $copier = new DeepCopy();
        $copier->skipUncloneable(true);
        $copier->addTypeFilter(
            new \DeepCopy\TypeFilter\ReplaceFilter(
                function ($currentValue) {
                    if ($currentValue instanceof ElementDescriptor) {
                        $cacheKey = $currentValue->getCacheKey();
                        if (Runtime::isRegistered($cacheKey)) {
                            // we don't want the copy from the runtime but cache is fine
                            Runtime::getInstance()->offsetUnset($cacheKey);
                        }

                        $renewedElement = Service::getElementById($currentValue->getType(), $currentValue->getId());

                        return $renewedElement;
                    }

                    return $currentValue;
                }
            ),
            new UnmarshalMatcher()
        );
        $this->data = $copier->copy($this->data);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->name . '; ' . $this->type;
    }

    public function __wakeup()
    {
        $this->needsRenewReferences = true;
    }

    /**
     * @return array
     */
    public function __sleep()
    {

        $copier = new DeepCopy();
        $copier->skipUncloneable(true);
        $copier->addTypeFilter(
            new \DeepCopy\TypeFilter\ReplaceFilter(
                function ($currentValue) {
                    if ($currentValue instanceof ElementInterface) {
                        $elementType = Service::getType($currentValue);
                        $descriptor = new ElementDescriptor($elementType, $currentValue->getId());

                        return $descriptor;
                    }

                    return $currentValue;
                }
            ),
            new MarshalMatcher(null, null)
        );

        $this->needsRenewReferences = true;
        $this->data = $copier->copy($this->data);

        return parent::__sleep();
    }

    /**
     * @return bool
     */
    public function getNeedsRenewReferences(): bool
    {
        return $this->needsRenewReferences;
    }

    /**
     * @param bool $needsRenewReferences
     */
    public function setNeedsRenewReferences(bool $needsRenewReferences)
    {
        $this->needsRenewReferences = (bool) $needsRenewReferences;
    }

}
