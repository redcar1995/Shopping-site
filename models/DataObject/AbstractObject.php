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

namespace Pimcore\Model\DataObject;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pimcore\Cache;
use Pimcore\Event\DataObjectEvents;
use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Element;

/**
 * @method \Pimcore\Model\DataObject\AbstractObject\Dao getDao()
 * @method bool __isBasedOnLatestData()
 */
class AbstractObject extends Model\Element\AbstractElement
{
    use Element\ChildsCompatibilityTrait;

    const OBJECT_TYPE_FOLDER = 'folder';
    const OBJECT_TYPE_OBJECT = 'object';
    const OBJECT_TYPE_VARIANT = 'variant';

    const OBJECT_CHILDREN_SORT_BY_DEFAULT = 'key';
    const OBJECT_CHILDREN_SORT_BY_INDEX = 'index';

    /**
     * @var bool
     */
    public static $doNotRestoreKeyAndPath = false;

    /**
     * possible types of a document
     *
     * @var array
     */
    public static $types = [self::OBJECT_TYPE_FOLDER, self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_VARIANT];

    /**
     * @var bool
     */
    private static $hidePublished = false;

    /**
     * @var bool
     */
    private static $getInheritedValues = false;

    /**
     * @var bool
     */
    protected static $disableDirtyDetection = false;

    /**
     * @static
     *
     * @return bool
     */
    public static function getHideUnpublished()
    {
        return self::$hidePublished;
    }

    /**
     * @static
     *
     * @param  $hidePublished
     */
    public static function setHideUnpublished($hidePublished)
    {
        self::$hidePublished = $hidePublished;
    }

    /**
     * @static
     *
     * @return bool
     */
    public static function doHideUnpublished()
    {
        return self::$hidePublished;
    }

    /**
     * @static
     *
     * @param  $getInheritedValues
     */
    public static function setGetInheritedValues($getInheritedValues)
    {
        self::$getInheritedValues = $getInheritedValues;
    }

    /**
     * @static
     *
     * @return bool
     */
    public static function getGetInheritedValues()
    {
        return self::$getInheritedValues;
    }

    /**
     * @static
     *
     * @param Concrete $object
     *
     * @return bool
     */
    public static function doGetInheritedValues(Concrete $object = null)
    {
        if (self::$getInheritedValues && $object !== null) {
            $class = $object->getClass();

            return $class->getAllowInherit();
        }

        return self::$getInheritedValues;
    }

    /**
     * @var int
     */
    public $o_id = 0;

    /**
     * @var int
     */
    public $o_parentId;

    /**
     * @var self
     */
    public $o_parent;

    /**
     * @var string
     */
    public $o_type = 'object';

    /**
     * @var string
     */
    public $o_key;

    /**
     * @var string
     */
    public $o_path;

    /**
     * @var int
     */
    public $o_index;

    /**
     * @var int
     */
    public $o_creationDate;

    /**
     * @var int
     */
    public $o_modificationDate;

    /**
     * @var int
     */
    public $o_userOwner;

    /**
     * @var int
     */
    public $o_userModification;

    /**
     * @var array
     */
    public $o_properties = null;

    /**
     * @var bool
     */
    public $o_hasChilds;

    /**
     * Contains a list of sibling documents
     *
     * @var array
     */
    public $o_siblings;

    /**
     * Indicator if document has siblings or not
     *
     * @var bool
     */
    public $o_hasSiblings;

    /**
     * @var Model\Dependency[]
     */
    public $o_dependencies;

    /**
     * @var array
     */
    public $o_childs;

    /**
     * @var string
     */
    public $o_locked;

    /**
     * @var Model\Element\AdminStyle
     */
    public $o_elementAdminStyle;

    /**
     * @var string
     */
    public $o_childrenSortBy;

    /**
     * @var array
     */
    private $lastGetChildsObjectTypes = [];

    /**
     * @var array
     */
    private $lastGetSiblingObjectTypes = [];

    /** @var int */
    protected $o_versionCount = 0;

    /**
     * get possible types
     *
     * @return array
     */
    public static function getTypes()
    {
        return self::$types;
    }

    /**
     * Static helper to get an object by the passed ID
     *
     * @param int $id
     * @param bool $force
     *
     * @return static
     */
    public static function getById($id, $force = false)
    {
        $id = intval($id);

        if ($id < 1) {
            return null;
        }

        $cacheKey = 'object_' . $id;

        if (!$force && \Pimcore\Cache\Runtime::isRegistered($cacheKey)) {
            $object = \Pimcore\Cache\Runtime::get($cacheKey);
            if ($object && static::typeMatch($object)) {
                return $object;
            }
        }

        try {
            if ($force || !($object = Cache::load($cacheKey))) {
                $object = new Model\DataObject();
                $typeInfo = $object->getDao()->getTypeById($id);

                if ($typeInfo['o_type'] == 'object' || $typeInfo['o_type'] == 'variant' || $typeInfo['o_type'] == 'folder') {
                    if ($typeInfo['o_type'] == 'folder') {
                        $className = 'Pimcore\\Model\\DataObject\\Folder';
                    } else {
                        $className = 'Pimcore\\Model\\DataObject\\' . ucfirst($typeInfo['o_className']);
                    }

                    $object = self::getModelFactory()->build($className);
                    \Pimcore\Cache\Runtime::set($cacheKey, $object);
                    $object->getDao()->getById($id);

                    Service::recursiveResetDirtyMap($object);

                    $object->__setDataVersionTimestamp($object->getModificationDate());

                    Cache::save($object, $cacheKey);
                } else {
                    throw new \Exception('No entry for object id ' . $id);
                }
            } else {
                \Pimcore\Cache\Runtime::set($cacheKey, $object);
            }
        } catch (\Exception $e) {
            return null;
        }

        if (!$object || !static::typeMatch($object)) {
            return null;
        }

        return $object;
    }

    /**
     * @param string $path
     * @param bool $force
     *
     * @return static
     */
    public static function getByPath($path, $force = false)
    {
        $path = Model\Element\Service::correctPath($path);

        try {
            $object = new self();
            $object->getDao()->getByPath($path);

            return self::getById($object->getId(), $force);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param array $config
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function getList($config = [])
    {
        $className = 'Pimcore\\Model\\DataObject';
        // get classname
        if (get_called_class() != 'Pimcore\\Model\\DataObject\\AbstractObject' && get_called_class() != 'Pimcore\\Model\\DataObject\\Concrete') {
            $tmpObject = new static();
            $className = 'Pimcore\\Model\\DataObject\\' . ucfirst($tmpObject->getClassName());
        }

        if (!empty($config['class'])) {
            $className = ltrim($config['class'], '\\');
        }

        if (is_array($config)) {
            if ($className) {
                $listClass = $className . '\\Listing';
                $list = self::getModelFactory()->build($listClass);
                $list->setValues($config);

                return $list;
            }
        }

        throw new \Exception('Unable to initiate list class - class not found or invalid configuration');
    }

    /**
     * @param array $config
     *
     * @return int total count
     */
    public static function getTotalCount($config = [])
    {
        $className = 'Pimcore\\Model\\DataObject';
        // get classname
        if (get_called_class() != 'Pimcore\\Model\\DataObject\\AbstractObject' && get_called_class() != 'Pimcore\\Model\\DataObject\\Concrete') {
            $tmpObject = new static();
            $className = 'Pimcore\\Model\\DataObject\\' . ucfirst($tmpObject->getClassName());
        }

        if (!empty($config['class'])) {
            $className = ltrim($config['class'], '\\');
        }

        if (is_array($config)) {
            if ($className) {
                $listClass = ucfirst($className) . '\\Listing';
                $list = self::getModelFactory()->build($listClass);
            }

            $list->setValues($config);
            $count = $list->getTotalCount();

            return $count;
        }
    }

    /**
     * @param AbstractObject $object
     *
     * @return bool
     */
    protected static function typeMatch(AbstractObject $object)
    {
        $staticType = get_called_class();
        if ($staticType != 'Pimcore\Model\DataObject\Concrete' && $staticType != 'Pimcore\Model\DataObject\AbstractObject') {
            if (!$object instanceof $staticType) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $objectTypes
     * @param bool $unpublished
     *
     * @return array
     */
    public function getChildren($objectTypes = [self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_FOLDER], $unpublished = false)
    {
        if ($this->o_childs === null || $this->lastGetChildsObjectTypes != $objectTypes) {
            $this->lastGetChildsObjectTypes = $objectTypes;

            $list = new Listing();
            $list->setUnpublished($unpublished);
            $list->setCondition('o_parentId = ?', $this->getId());
            $list->setOrderKey(sprintf('o_%s', $this->getChildrenSortBy()));
            $list->setOrder('asc');
            $list->setObjectTypes($objectTypes);
            $this->o_childs = $list->load();
        }

        return $this->o_childs;
    }

    /**
     * @param array $objectTypes
     *
     * @return bool
     */
    public function hasChildren($objectTypes = [self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_FOLDER])
    {
        if (is_bool($this->o_hasChilds)) {
            if (($this->o_hasChilds and empty($this->o_childs)) or (!$this->o_hasChilds and !empty($this->o_childs))) {
                return $this->getDao()->hasChildren($objectTypes);
            } else {
                return $this->o_hasChilds;
            }
        }

        return $this->getDao()->hasChildren($objectTypes);
    }

    /**
     * Get a list of the sibling documents
     *
     * @param array $objectTypes
     * @param bool $unpublished
     *
     * @return array
     */
    public function getSiblings($objectTypes = [self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_FOLDER], $unpublished = false)
    {
        if ($this->o_siblings === null || $this->lastGetSiblingObjectTypes != $objectTypes) {
            $list = new Listing();
            $list->setUnpublished($unpublished);
            // string conversion because parentId could be 0
            $list->addConditionParam('o_parentId = ?', (string)$this->getParentId());
            $list->addConditionParam('o_id != ?', $this->getId());
            $list->setOrderKey('o_key');
            $list->setObjectTypes($objectTypes);
            $list->setOrder('asc');
            $this->o_siblings = $list->load();
        }

        return $this->o_siblings;
    }

    /**
     * Returns true if the document has at least one sibling
     *
     * @param array $objectTypes
     *
     * @return bool
     */
    public function hasSiblings($objectTypes = [self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_FOLDER])
    {
        if (is_bool($this->o_hasSiblings)) {
            if (($this->o_hasSiblings and empty($this->o_siblings)) or (!$this->o_hasSiblings and !empty($this->o_siblings))) {
                return $this->getDao()->hasSiblings($objectTypes);
            } else {
                return $this->o_hasSiblings;
            }
        }

        return $this->getDao()->hasSiblings($objectTypes);
    }

    /**
     * Returns true if the element is locked
     *
     * @return string
     */
    public function getLocked()
    {
        return $this->o_locked;
    }

    /**
     * @param bool $o_locked
     *
     * @return $this
     */
    public function setLocked($o_locked)
    {
        $this->o_locked = $o_locked;

        return $this;
    }

    public function delete()
    {
        \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::PRE_DELETE, new DataObjectEvent($this));
        try {
            // delete childs
            if ($this->hasChildren([self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_FOLDER, self::OBJECT_TYPE_VARIANT])) {
                // delete also unpublished children
                $unpublishedStatus = self::doHideUnpublished();
                self::setHideUnpublished(false);
                foreach ($this->getChildren([self::OBJECT_TYPE_OBJECT, self::OBJECT_TYPE_FOLDER, self::OBJECT_TYPE_VARIANT], true) as $value) {
                    $value->delete();
                }
                self::setHideUnpublished($unpublishedStatus);
            }

            // remove dependencies
            $d = $this->getDependencies();
            $d->cleanAllForElement($this);

            // remove all properties
            $this->getDao()->deleteAllProperties();

            // remove all permissions
            $this->getDao()->deleteAllPermissions();

            $this->getDao()->delete();

            // empty object cache
            $this->clearDependentCache();

            //clear object from registry
            \Pimcore\Cache\Runtime::set('object_' . $this->getId(), null);
        } catch (\Exception $e) {
            \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_DELETE_FAILURE, new DataObjectEvent($this));
            Logger::crit($e);
            throw $e;
        }

        \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_DELETE, new DataObjectEvent($this));
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function save()
    {
        // additional parameters (e.g. "versionNote" for the version note)
        $params = [];
        if (func_num_args() && is_array(func_get_arg(0))) {
            $params = func_get_arg(0);
        }

        $isUpdate = false;

        $isDirtyDetectionDisabled = self::isDirtyDetectionDisabled();
        $preEvent = new DataObjectEvent($this, $params);
        if ($this->getId()) {
            $isUpdate = true;
            \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::PRE_UPDATE, $preEvent);
        } else {
            self::disableDirtyDetection();
            \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::PRE_ADD, $preEvent);
        }

        $params = $preEvent->getArguments();

        $this->correctPath();

        // we wrap the save actions in a loop here, so that we can restart the database transactions in the case it fails
        // if a transaction fails it gets restarted $maxRetries times, then the exception is thrown out
        // this is especially useful to avoid problems with deadlocks in multi-threaded environments (forked workers, ...)
        $maxRetries = 5;
        for ($retries = 0; $retries < $maxRetries; $retries++) {

            // be sure that unpublished objects in relations are saved also in frontend mode, eg. in importers, ...
            $hideUnpublishedBackup = self::getHideUnpublished();
            self::setHideUnpublished(false);

            $this->beginTransaction();

            try {
                if (!in_array($this->getType(), self::$types)) {
                    throw new \Exception('invalid object type given: [' . $this->getType() . ']');
                }

                if (!$isUpdate) {
                    $this->getDao()->create();
                }

                // get the old path from the database before the update is done
                $oldPath = null;
                if ($isUpdate) {
                    $oldPath = $this->getDao()->getCurrentFullPath();
                }

                // if the old path is different from the new path, update all children
                // we need to do the update of the children's path before $this->update() because the
                // inheritance helper needs the correct paths of the children in InheritanceHelper::buildTree()
                $updatedChildren = [];
                if ($oldPath && $oldPath != $this->getRealFullPath()) {
                    $this->getDao()->updateWorkspaces();
                    $updatedChildren = $this->getDao()->updateChildsPaths($oldPath);
                }

                $this->update($isUpdate, $params);

                self::setHideUnpublished($hideUnpublishedBackup);

                $this->commit();
                break; // transaction was successfully completed, so we cancel the loop here -> no restart required
            } catch (\Exception $e) {
                try {
                    $this->rollBack();
                } catch (\Exception $er) {
                    // PDO adapter throws exceptions if rollback fails
                    Logger::info($er);
                }

                if ($e instanceof Model\Element\ValidationException) {
                    throw $e;
                }

                if ($e instanceof UniqueConstraintViolationException) {
                    throw new Element\ValidationException('unique constraint violation', 0, $e);
                }

                // set "HideUnpublished" back to the value it was originally
                self::setHideUnpublished($hideUnpublishedBackup);

                // we try to start the transaction $maxRetries times again (deadlocks, ...)
                if ($retries < ($maxRetries - 1)) {
                    $run = $retries + 1;
                    $waitTime = rand(1, 5) * 100000; // microseconds
                    Logger::warn('Unable to finish transaction (' . $run . ". run) because of the following reason '" . $e->getMessage() . "'. --> Retrying in " . $waitTime . ' microseconds ... (' . ($run + 1) . ' of ' . $maxRetries . ')');

                    usleep($waitTime); // wait specified time until we restart the transaction
                } else {
                    if ($isUpdate) {
                        \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_UPDATE_FAILURE, new DataObjectEvent($this));
                    } else {
                        \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_ADD_FAILURE, new DataObjectEvent($this));
                    }

                    // if the transaction still fail after $maxRetries retries, we throw out the exception
                    Logger::error('Finally giving up restarting the same transaction again and again, last message: ' . $e->getMessage());
                    throw $e;
                }
            }
        }

        $additionalTags = [];
        if (isset($updatedChildren) && is_array($updatedChildren)) {
            foreach ($updatedChildren as $objectId) {
                $tag = 'object_' . $objectId;
                $additionalTags[] = $tag;

                // remove the child also from registry (internal cache) to avoid path inconsistencies during long running scripts, such as CLI
                \Pimcore\Cache\Runtime::set($tag, null);
            }
        }
        $this->clearDependentCache($additionalTags);

        if ($isUpdate) {
            \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_UPDATE, new DataObjectEvent($this));
        } else {
            self::setDisableDirtyDetection($isDirtyDetectionDisabled);
            \Pimcore::getEventDispatcher()->dispatch(DataObjectEvents::POST_ADD, new DataObjectEvent($this));
        }

        return $this;
    }

    public function correctPath()
    {
        // set path
        if ($this->getId() != 1) { // not for the root node

            if (!Element\Service::isValidKey($this->getKey(), 'object')) {
                throw new \Exception('invalid key for object with id [ '.$this->getId().' ] key is: [' . $this->getKey() . ']');
            }

            if ($this->getParentId() == $this->getId()) {
                throw new \Exception("ParentID and ID is identical, an element can't be the parent of itself.");
            }

            $parent = AbstractObject::getById($this->getParentId());

            if ($parent) {
                // use the parent's path from the database here (getCurrentFullPath), to ensure the path really exists and does not rely on the path
                // that is currently in the parent object (in memory), because this might have changed but wasn't not saved
                $this->setPath(str_replace('//', '/', $parent->getCurrentFullPath().'/'));
            } else {
                // parent document doesn't exist anymore, set the parent to to root
                $this->setParentId(1);
                $this->setPath('/');
            }

            if (strlen($this->getKey()) < 1) {
                throw new \Exception('DataObject requires key');
            }
        } elseif ($this->getId() == 1) {
            // some data in root node should always be the same
            $this->setParentId(0);
            $this->setPath('/');
            $this->setKey('');
            $this->setType('folder');
        }

        if (Service::pathExists($this->getRealFullPath())) {
            $duplicate = AbstractObject::getByPath($this->getRealFullPath());
            if ($duplicate instanceof self and $duplicate->getId() != $this->getId()) {
                throw new \Exception('Duplicate full path [ '.$this->getRealFullPath().' ] - cannot save object');
            }
        }

        $this->validatePathLength();
    }

    /**
     * @param $isUpdate
     * @param $params
     *
     * @throws \Exception
     */
    protected function update($isUpdate = null, $params = [])
    {
        $this->updateModificationInfos();

        // save properties
        $this->getProperties();
        $this->getDao()->deleteAllProperties();

        if (is_array($this->getProperties()) and count($this->getProperties()) > 0) {
            foreach ($this->getProperties() as $property) {
                if (!$property->getInherited()) {
                    $property->setDao(null);
                    $property->setCid($this->getId());
                    $property->setCtype('object');
                    $property->setCpath($this->getRealFullPath());
                    $property->save();
                }
            }
        }

        // save dependencies
        $d = new Model\Dependency();
        $d->setSourceType('object');
        $d->setSourceId($this->getId());

        foreach ($this->resolveDependencies() as $requirement) {
            if ($requirement['id'] == $this->getId() && $requirement['type'] == 'object') {
                // dont't add a reference to yourself
                continue;
            } else {
                $d->addRequirement($requirement['id'], $requirement['type']);
            }
        }

        $d->save();

        //set object to registry
        \Pimcore\Cache\Runtime::set('object_' . $this->getId(), $this);
    }

    /**
     * @param array $additionalTags
     */
    public function clearDependentCache($additionalTags = [])
    {
        try {
            $tags = ['object_' . $this->getId(), 'object_properties', 'output'];
            $tags = array_merge($tags, $additionalTags);

            Cache::clearTags($tags);
        } catch (\Exception $e) {
            Logger::crit($e);
        }
    }

    /**
     * @param int $index
     */
    public function saveIndex($index)
    {
        $this->getDao()->saveIndex($index);
        $this->clearDependentCache();
    }

    /**
     * @return Model\Dependency
     */
    public function getDependencies()
    {
        if (!$this->o_dependencies) {
            $this->o_dependencies = Model\Dependency::getBySourceId($this->getId(), 'object');
        }

        return $this->o_dependencies;
    }

    /**
     * @return string
     */
    public function getFullPath()
    {
        $path = $this->getPath() . $this->getKey();

        return $path;
    }

    /**
     * @return string
     */
    public function getRealPath()
    {
        return $this->getPath();
    }

    /**
     * @return string
     */
    public function getRealFullPath()
    {
        return $this->getFullPath();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->o_id;
    }

    /**
     * @return int
     */
    public function getParentId()
    {
        // fall back to parent if no ID is set but we have a parent object
        if (!$this->o_parentId && $this->o_parent) {
            return $this->o_parent->getId();
        }

        return $this->o_parentId;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->o_type;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->o_key;
    }

    /**
     * @return string path
     */
    public function getPath()
    {
        return $this->o_path;
    }

    /**
     * @return int
     */
    public function getIndex()
    {
        return $this->o_index;
    }

    /**
     * @return int
     */
    public function getCreationDate()
    {
        return $this->o_creationDate;
    }

    /**
     * @return int
     */
    public function getModificationDate()
    {
        return $this->o_modificationDate;
    }

    /**
     * @return int
     */
    public function getUserOwner()
    {
        return $this->o_userOwner;
    }

    /**
     * @return int
     */
    public function getUserModification()
    {
        return $this->o_userModification;
    }

    /**
     * @param int $o_id
     *
     * @return $this
     */
    public function setId($o_id)
    {
        $this->o_id = (int) $o_id;

        return $this;
    }

    /**
     * @param int $o_parentId
     *
     * @return $this
     */
    public function setParentId($o_parentId)
    {
        $o_parentId = (int) $o_parentId;
        if ($o_parentId != $this->o_parentId && $this instanceof DirtyIndicatorInterface) {
            $this->markFieldDirty('o_parentId');
        }
        $this->o_parentId = $o_parentId;
        $this->o_parent = null;

        return $this;
    }

    /**
     * @param string $o_type
     *
     * @return $this
     */
    public function setType($o_type)
    {
        $this->o_type = $o_type;

        return $this;
    }

    /**
     * @param string $o_key
     *
     * @return $this
     */
    public function setKey($o_key)
    {
        $this->o_key = $o_key;

        return $this;
    }

    /**
     * @param string $o_path
     *
     * @return $this
     */
    public function setPath($o_path)
    {
        $this->o_path = $o_path;

        return $this;
    }

    /**
     * @param int $o_index
     *
     * @return $this
     */
    public function setIndex($o_index)
    {
        $this->o_index = (int) $o_index;

        return $this;
    }

    /**
     * @param string|null $childrenSortBy
     */
    public function setChildrenSortBy($childrenSortBy)
    {
        $this->o_childrenSortBy = $childrenSortBy;
    }

    /**
     * @param int $o_creationDate
     *
     * @return $this
     */
    public function setCreationDate($o_creationDate)
    {
        $this->o_creationDate = (int) $o_creationDate;

        return $this;
    }

    /**
     * @param int $o_modificationDate
     *
     * @return $this
     */
    public function setModificationDate($o_modificationDate)
    {
        $this->o_modificationDate = (int) $o_modificationDate;

        return $this;
    }

    /**
     * @param int $o_userOwner
     *
     * @return $this
     */
    public function setUserOwner($o_userOwner)
    {
        $this->o_userOwner = (int) $o_userOwner;

        return $this;
    }

    /**
     * @param int $o_userModification
     *
     * @return $this
     */
    public function setUserModification($o_userModification)
    {
        $this->o_userModification = (int) $o_userModification;

        return $this;
    }

    /**
     * @param array $children
     *
     * @return $this
     */
    public function setChildren($children)
    {
        $this->o_childs = $children;
        if (is_array($children) and count($children) > 0) {
            $this->o_hasChilds = true;
        } else {
            $this->o_hasChilds = false;
        }

        return $this;
    }

    /**
     * @return self
     */
    public function getParent()
    {
        if ($this->o_parent === null) {
            $this->setParent(AbstractObject::getById($this->getParentId()));
        }

        return $this->o_parent;
    }

    /**
     * @param self $o_parent
     *
     * @return $this
     */
    public function setParent($o_parent)
    {
        $newParentId = $o_parent instanceof self ? $o_parent->getId() : 0;
        $this->setParentId($newParentId);
        $this->o_parent = $o_parent;

        return $this;
    }

    /**
     * @return Model\Property[]
     */
    public function getProperties()
    {
        if ($this->o_properties === null) {
            // try to get from cache
            $cacheKey = 'object_properties_' . $this->getId();
            $properties = Cache::load($cacheKey);
            if (!is_array($properties)) {
                $properties = $this->getDao()->getProperties();
                $elementCacheTag = $this->getCacheTag();
                $cacheTags = ['object_properties' => 'object_properties', $elementCacheTag => $elementCacheTag];
                Cache::save($properties, $cacheKey, $cacheTags);
            }

            $this->setProperties($properties);
        }

        return $this->o_properties;
    }

    /**
     * @param array $o_properties
     *
     * @return $this
     */
    public function setProperties($o_properties)
    {
        $this->o_properties = $o_properties;

        return $this;
    }

    /**
     * @param $name
     * @param $type
     * @param $data
     * @param bool $inherited
     * @param bool $inheritable
     *
     * @return $this
     */
    public function setProperty($name, $type, $data, $inherited = false, $inheritable = false)
    {
        $this->getProperties();

        $property = new Model\Property();
        $property->setType($type);
        $property->setCid($this->getId());
        $property->setName($name);
        $property->setCtype('object');
        $property->setData($data);
        $property->setInherited($inherited);
        $property->setInheritable($inheritable);

        $this->o_properties[$name] = $property;

        return $this;
    }

    /**
     * @return Model\Element\AdminStyle
     */
    public function getElementAdminStyle()
    {
        if (empty($this->o_elementAdminStyle)) {
            $this->o_elementAdminStyle = new Model\Element\AdminStyle($this);
        }

        return $this->o_elementAdminStyle;
    }

    /**
     * @return string
     */
    public function getChildrenSortBy()
    {
        return $this->o_childrenSortBy ?? self::OBJECT_CHILDREN_SORT_BY_DEFAULT;
    }

    public function __sleep()
    {
        $finalVars = [];
        $parentVars = parent::__sleep();

        if (isset($this->_fulldump)) {
            // this is if we want to make a full dump of the object (eg. for a new version), including childs for recyclebin
            $blockedVars = ['o_userPermissions', 'o_dependencies', 'o_hasChilds', 'o_versions', 'o_class', 'scheduledTasks', 'o_parent', 'omitMandatoryCheck', 'o_dirtyFields'];
            $finalVars[] = '_fulldump';
            $this->removeInheritedProperties();
        } else {
            // this is if we want to cache the object
            $blockedVars = ['o_userPermissions', 'o_dependencies', 'o_childs', 'o_hasChilds', 'o_versions', 'o_class', 'scheduledTasks', 'o_properties', 'o_parent', 'o___loadedLazyFields', 'omitMandatoryCheck'];
        }

        foreach ($parentVars as $key) {
            if (!in_array($key, $blockedVars)) {
                $finalVars[] = $key;
            }
        }

        return $finalVars;
    }

    public function __wakeup()
    {
        if (isset($this->_fulldump) && !self::$doNotRestoreKeyAndPath) {
            // set current key and path this is necessary because the serialized data can have a different path than the original element ( element was renamed or moved )
            $originalElement = AbstractObject::getById($this->getId());
            if ($originalElement) {
                $this->setKey($originalElement->getKey());
                $this->setPath($originalElement->getRealPath());
            }
        }

        if (isset($this->_fulldump) && $this->o_properties !== null) {
            $this->renewInheritedProperties();
        }

        if (isset($this->_fulldump)) {
            unset($this->_fulldump);
        }
    }

    public function removeInheritedProperties()
    {
        $myProperties = $this->getProperties();

        if ($myProperties) {
            foreach ($this->getProperties() as $name => $property) {
                if ($property->getInherited()) {
                    unset($myProperties[$name]);
                }
            }
        }

        $this->setProperties($myProperties);
    }

    public function renewInheritedProperties()
    {
        $this->removeInheritedProperties();

        // add to registry to avoid infinite regresses in the following $this->getDao()->getProperties()
        $cacheKey = 'object_' . $this->getId();
        if (!\Pimcore\Cache\Runtime::isRegistered($cacheKey)) {
            \Pimcore\Cache\Runtime::set($cacheKey, $this);
        }

        $myProperties = $this->getProperties();
        $inheritedProperties = $this->getDao()->getProperties(true);
        $this->setProperties(array_merge($inheritedProperties, $myProperties));
    }

    /**
     * @param $method
     * @param $args
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function __call($method, $args)
    {

        // compatibility mode (they do not have any set_oXyz() methods anymore)
        if (preg_match('/^(get|set)o_/i', $method)) {
            $newMethod = preg_replace('/^(get|set)o_/i', '$1', $method);
            if (method_exists($this, $newMethod)) {
                $r = call_user_func_array([$this, $newMethod], $args);

                return $r;
            }
        }

        return parent::__call($method, $args);
    }

    /**
     * @return bool
     */
    public static function doNotRestoreKeyAndPath()
    {
        return self::$doNotRestoreKeyAndPath;
    }

    /**
     * @param bool $doNotRestoreKeyAndPath
     */
    public static function setDoNotRestoreKeyAndPath($doNotRestoreKeyAndPath)
    {
        self::$doNotRestoreKeyAndPath = $doNotRestoreKeyAndPath;
    }

    /**
     * @param string $fieldName
     * @param null $language
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function get($fieldName, $language = null)
    {
        if (!$fieldName) {
            throw new \Exception('Field name must not be empty.');
        }

        return $this->{'get'.ucfirst($fieldName)}($language);
    }

    /**
     * @param string $fieldName
     * @param $value
     * @param null $language
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function set($fieldName, $value, $language = null)
    {
        if (!$fieldName) {
            throw new \Exception('Field name must not be empty.');
        }

        return $this->{'set'.ucfirst($fieldName)}($value, $language);
    }

    /**
     * @return bool
     */
    public static function isDirtyDetectionDisabled()
    {
        return self::$disableDirtyDetection;
    }

    /**
     * @param bool $disableDirtyDetection
     */
    public static function setDisableDirtyDetection(bool $disableDirtyDetection)
    {
        self::$disableDirtyDetection = $disableDirtyDetection;
    }

    /**
     * Disables the dirty detection
     */
    public static function disableDirtyDetection()
    {
        self::setDisableDirtyDetection(true);
    }

    /**
     * Enables the dirty detection
     */
    public static function enableDirtyDetection()
    {
        self::setDisableDirtyDetection(false);
    }

    /**
     * @return int
     */
    public function getVersionCount(): int
    {
        return $this->o_versionCount ? $this->o_versionCount : 0;
    }

    /**
     * @param int|null $o_versionCount
     *
     * @return AbstractObject
     */
    public function setVersionCount(?int $o_versionCount): Element\ElementInterface
    {
        $this->o_versionCount = (int) $o_versionCount;

        return $this;
    }
}
