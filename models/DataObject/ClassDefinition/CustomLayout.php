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

namespace Pimcore\Model\DataObject\ClassDefinition;

use Pimcore\Cache;
use Pimcore\Cache\RuntimeCache;
use Pimcore\Event\DataObjectCustomLayoutEvents;
use Pimcore\Event\Model\DataObject\CustomLayoutEvent;
use Pimcore\Event\Traits\RecursionBlockingEventDispatchHelperTrait;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\DataObject;
use Symfony\Component\Uid\UuidV4;

/**
 * @method \Pimcore\Model\DataObject\ClassDefinition\CustomLayout\Dao getDao()
 * @method bool isWriteable()
 * @method string getWriteTarget()
 */
class CustomLayout extends Model\AbstractModel
{
    use DataObject\ClassDefinition\Helper\VarExport;
    use RecursionBlockingEventDispatchHelperTrait;

    /**
     * @var string|null
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var int|null
     */
    protected $creationDate;

    /**
     * @var int|null
     */
    protected $modificationDate;

    /**
     * @var int
     */
    protected $userOwner;

    /**
     * @var int
     */
    protected $userModification;

    /**
     * @var string
     */
    protected $classId;

    /**
     * @var Layout|null
     */
    protected $layoutDefinitions;

    /**
     * @var int
     */
    protected $default = 0;

    /**
     * @param string $id
     *
     * @return null|CustomLayout
     */
    public static function getById($id)
    {
        $cacheKey = 'customlayout_' . $id;

        try {
            $customLayout = RuntimeCache::get($cacheKey);
            if (!$customLayout) {
                throw new \Exception('Custom Layout in registry is null');
            }
        } catch (\Exception $e) {
            try {
                $customLayout = new self();
                $customLayout->getDao()->getById($id);
                RuntimeCache::set($cacheKey, $customLayout);
            } catch (Model\Exception\NotFoundException $e) {
                return null;
            }
        }

        return $customLayout;
    }

    /**
     * @param string $name
     *
     * @return null|CustomLayout
     *
     * @throws \Exception
     */
    public static function getByName(string $name)
    {
        $cacheKey = 'customlayout_' . $name;

        try {
            $customLayout = RuntimeCache::get($cacheKey);
            if (!$customLayout) {
                throw new \Exception('Custom Layout in registry is null');
            }
        } catch (\Exception $e) {
            try {
                $customLayout = new self();
                $customLayout->getDao()->getByName($name);
                RuntimeCache::set($cacheKey, $customLayout);
            } catch (Model\Exception\NotFoundException $e) {
                return null;
            }
        }

        return $customLayout;
    }

    /**
     * @param string $name
     * @param string $classId
     *
     * @return null|CustomLayout
     *
     * @throws \Exception
     */
    public static function getByNameAndClassId(string $name, $classId)
    {
        try {
            $customLayout = new self();
            $customLayout->getDao()->getByName($name);

            if ($customLayout->getClassId() != $classId) {
                throw new Model\Exception\NotFoundException('classId does not match');
            }

            return $customLayout;
        } catch (Model\Exception\NotFoundException $e) {
        }

        return null;
    }

    /**
     * @param string $field
     *
     * @return Data|null
     */
    public function getFieldDefinition($field)
    {
        /**
         * @param string $key
         * @param Data|Layout $definition
         *
         * @return Data|null
         */
        $findElement = static function ($key, $definition) use (&$findElement) {
            if ($definition->getName() === $key) {
                return $definition;
            }
            if (method_exists($definition, 'getChildren')) {
                foreach ($definition->getChildren() as $child) {
                    if ($childDefinition = $findElement($key, $child)) {
                        return $childDefinition;
                    }
                }
            }

            return null;
        };

        return $findElement($field, $this->getLayoutDefinitions());
    }

    /**
     * @param array $values
     *
     * @return CustomLayout
     */
    public static function create($values = [])
    {
        $class = new self();
        $class->setValues($values);

        if (!$class->getId()) {
            $class->getDao()->getNewId();
        }

        return $class;
    }

    /**
     *
     * @throws DataObject\Exception\DefinitionWriteException
     */
    public function save()
    {
        if (!$this->isWriteable()) {
            throw new DataObject\Exception\DefinitionWriteException();
        }

        $isUpdate = $this->exists();

        if ($isUpdate) {
            $this->dispatchEvent(new CustomLayoutEvent($this), DataObjectCustomLayoutEvents::PRE_UPDATE);
        } else {
            $this->dispatchEvent(new CustomLayoutEvent($this), DataObjectCustomLayoutEvents::PRE_ADD);
        }

        $this->setModificationDate(time());

        $this->getDao()->save();

        // empty custom layout cache
        try {
            Cache::clearTag('customlayout_' . $this->getId());
        } catch (\Exception $e) {
        }
    }

    /**
     * @internal
     *
     * @return string
     */
    protected function getInfoDocBlock()
    {
        $cd = '/**' . "\n";

        if ($this->getDescription()) {
            $description = str_replace(['/**', '*/', '//'], '', $this->getDescription());
            $description = str_replace("\n", "\n* ", $description);

            $cd .= '* '.$description."\n";
        }
        $cd .= '*/';

        return $cd;
    }

    /**
     * @internal
     *
     * @param string $classId
     *
     * @return UuidV4|null
     */
    public static function getIdentifier($classId)
    {
        try {
            $customLayout = new self();
            return $customLayout->getDao()->getLatestIdentifier($classId);
        } catch (\Exception $e) {
            Logger::error((string) $e);

            return null;
        }
    }

    public function delete()
    {
        // empty object cache
        try {
            Cache::clearTag('customlayout_' . $this->getId());
        } catch (\Exception $e) {
        }

        // empty output cache
        try {
            Cache::clearTag('output');
        } catch (\Exception $e) {
        }

        $this->getDao()->delete();
    }

    /**
     * @return bool
     */
    public function exists()
    {
        if (is_null($this->getId())) {
            return false;
        }
        $name = $this->getDao()->getNameById($this->getId());

        return is_string($name);
    }

    /**
     * @return string|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int|null
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * @return int|null
     */
    public function getModificationDate()
    {
        return $this->modificationDate;
    }

    /**
     * @return int
     */
    public function getUserOwner()
    {
        return $this->userOwner;
    }

    /**
     * @return int
     */
    public function getUserModification()
    {
        return $this->userModification;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return int
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @param int $default
     *
     * @return $this
     */
    public function setDefault($default)
    {
        $this->default = (int)$default;

        return $this;
    }

    /**
     * @param int $creationDate
     *
     * @return $this
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = (int) $creationDate;

        return $this;
    }

    /**
     * @param int $modificationDate
     *
     * @return $this
     */
    public function setModificationDate($modificationDate)
    {
        $this->modificationDate = (int) $modificationDate;

        return $this;
    }

    /**
     * @param int $userOwner
     *
     * @return $this
     */
    public function setUserOwner($userOwner)
    {
        $this->userOwner = (int) $userOwner;

        return $this;
    }

    /**
     * @param int $userModification
     *
     * @return $this
     */
    public function setUserModification($userModification)
    {
        $this->userModification = (int) $userModification;

        return $this;
    }

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param Layout|null $layoutDefinitions
     */
    public function setLayoutDefinitions($layoutDefinitions)
    {
        $this->layoutDefinitions = $layoutDefinitions;
    }

    /**
     * @return Layout|null
     */
    public function getLayoutDefinitions()
    {
        return $this->layoutDefinitions;
    }

    /**
     * @param string $classId
     */
    public function setClassId($classId)
    {
        $this->classId = $classId;
    }

    /**
     * @return string
     */
    public function getClassId()
    {
        return $this->classId;
    }
}
