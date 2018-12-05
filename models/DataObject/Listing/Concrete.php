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

namespace Pimcore\Model\DataObject\Listing;

use Pimcore\Model;
use Pimcore\Model\DataObject;

/**
 * @method \Pimcore\Model\DataObject\Listing\Concrete\Dao getDao()
 * @method Model\Webservice\Data\DataObject\Concrete[] load()
 */
abstract class Concrete extends Model\DataObject\Listing
{
    /**
     * @var int
     */
    protected $classId;

    /**
     * @var string
     */
    protected $className;

    /**
     * @var string|
     */
    protected $locale;

    /**
     * do not use the localized views for this list (in the case the class contains localized fields),
     * conditions on localized fields are not possible
     *
     * @var bool
     */
    public $ignoreLocalizedFields = false;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->objectTypeObject = true;
        $this->initDao('\\Pimcore\\Model\\DataObject\\Listing\\Concrete');
    }

    /**
     * @return string
     */
    public function getClassId()
    {
        return $this->classId;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param $classId
     *
     * @return $this
     */
    public function setClassId($classId)
    {
        $this->classId = $classId;

        return $this;
    }

    /**
     * @param $className
     *
     * @return $this
     */
    public function setClassName($className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @return DataObject\ClassDefinition
     */
    public function getClass()
    {
        $class = DataObject\ClassDefinition::getById($this->getClassId());

        return $class;
    }

    /**
     * @param mixed $locale
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param bool $ignoreLocalizedFields
     *
     * @return $this
     */
    public function setIgnoreLocalizedFields($ignoreLocalizedFields)
    {
        $this->ignoreLocalizedFields = $ignoreLocalizedFields;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIgnoreLocalizedFields()
    {
        return $this->ignoreLocalizedFields;
    }

    /**
     * field collection queries
     *
     * @var array
     */
    private $fieldCollectionConfigs = [];

    /**
     * @param $type
     * @param null $fieldname
     *
     * @throws \Exception
     */
    public function addFieldCollection($type, $fieldname = null)
    {
        if (empty($type)) {
            throw new \Exception('No fieldcollectiontype given');
        }

        DataObject\Fieldcollection\Definition::getByKey($type);
        $this->fieldCollectionConfigs[] = ['type' => $type, 'fieldname' => $fieldname];
    }

    /**
     * @param $fieldCollections
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setFieldCollections($fieldCollections)
    {
        foreach ($fieldCollections as $fc) {
            $this->addFieldCollection($fc['type'], $fc['fieldname']);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getFieldCollections()
    {
        return $this->fieldCollectionConfigs;
    }

    /**
     * object brick queries
     *
     * @var array
     */
    private $objectBrickConfigs = [];

    /**
     * @param $type
     *
     * @throws \Exception
     */
    public function addObjectbrick($type)
    {
        if (empty($type)) {
            throw new \Exception('No objectbrick given');
        }

        DataObject\Objectbrick\Definition::getByKey($type);
        if (!in_array($type, $this->objectBrickConfigs)) {
            $this->objectBrickConfigs[] = $type;
        }
    }

    /**
     * @param $objectbricks
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setObjectbricks($objectbricks)
    {
        foreach ($objectbricks as $ob) {
            if (!in_array($ob, $this->objectBrickConfigs)) {
                $this->addObjectbrick($ob);
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getObjectbricks()
    {
        return $this->objectBrickConfigs;
    }

    /**
     * @return bool
     */
    public function addDistinct()
    {
        $fieldCollections = $this->getFieldCollections();
        if (!empty($fieldCollections)) {
            return true;
        }
    }
}
