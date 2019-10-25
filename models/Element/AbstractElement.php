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
 * @package    Element
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Element;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\Element\Dao getDao()
 */
abstract class AbstractElement extends Model\AbstractModel implements ElementInterface, ElementDumpStateInterface
{
    use ElementDumpStateTrait;

    /**
     * @var int
     */
    protected $__dataVersionTimestamp = null;

    protected function updateModificationInfos()
    {
        $this->setVersionCount($this->getDao()->getVersionCountForUpdate() + 1);

        if ($this->getVersionCount() > 4200000000) {
            $this->setVersionCount(1);
        }

        $updateTime = time();
        $this->setModificationDate($updateTime);

        if (!$this->getCreationDate()) {
            $this->setCreationDate($updateTime);
        }

        // auto assign user if possible, if no user present, use ID=0 which represents the "system" user
        $userId = 0;
        $user = \Pimcore\Tool\Admin::getCurrentUser();
        if ($user instanceof Model\User) {
            $userId = $user->getId();
        }
        $this->setUserModification($userId);

        if ($this->getUserOwner() === null) {
            $this->setUserOwner($userId);
        }
    }

    /**
     * Get specific property data or the property object itself ($asContainer=true) by its name, if the
     * property doesn't exists return null
     *
     * @param string $name
     * @param bool $asContainer
     *
     * @return mixed
     */
    public function getProperty($name, $asContainer = false)
    {
        $properties = $this->getProperties();
        if ($this->hasProperty($name)) {
            if ($asContainer) {
                return $properties[$name];
            } else {
                return $properties[$name]->getData();
            }
        }

        return null;
    }

    /**
     * @param  $name
     *
     * @return bool
     */
    public function hasProperty($name)
    {
        $properties = $this->getProperties();

        return array_key_exists($name, $properties);
    }

    /**
     * @param  $name
     */
    public function removeProperty($name)
    {
        $properties = $this->getProperties();
        unset($properties[$name]);
        $this->setProperties($properties);
    }

    /**
     * get the cache tag for the element
     *
     * @return string
     */
    public function getCacheTag()
    {
        $elementType = Service::getElementType($this);

        return $elementType . '_' . $this->getId();
    }

    /**
     * Get the cache tags for the element, resolve all dependencies to tag the cache entries
     * This is necessary to update the cache if there is a change in an depended object
     *
     * @param array $tags
     *
     * @return array
     */
    public function getCacheTags($tags = [])
    {
        $tags = is_array($tags) ? $tags : [];

        $tags[$this->getCacheTag()] = $this->getCacheTag();

        return $tags;
    }

    /**
     * Resolves the dependencies of the element and returns an array of them - Used by update()
     *
     * @return array
     */
    public function resolveDependencies()
    {
        $dependencies = [[]];

        // check for properties
        if (method_exists($this, 'getProperties')) {
            $properties = $this->getProperties();
            foreach ($properties as $property) {
                $dependencies[] = $property->resolveDependencies();
            }
        }

        $dependencies = array_merge(...$dependencies);

        return $dependencies;
    }

    /**
     * Returns true if the element is locked
     *
     * @return bool
     */
    public function isLocked()
    {
        if ($this->getLocked()) {
            return true;
        }

        // check for inherited
        return $this->getDao()->isLocked();
    }

    /**
     * @return array
     */
    public function getUserPermissions()
    {
        $workspaceClass = Service::getBaseClassNameForElement($this);
        $vars = get_class_vars('\\Pimcore\\Model\\User\\Workspace\\' . $workspaceClass);
        $ignored = ['userId', 'cid', 'cpath', 'dao'];
        $permissions = [];

        foreach ($vars as $name => $defaultValue) {
            if (!in_array($name, $ignored)) {
                $permissions[$name] = $this->isAllowed($name);
            }
        }

        return $permissions;
    }

    /**
     * This is used for user-permissions, pass a permission type (eg. list, view, save) an you know if the current user is allowed to perform the requested action
     *
     * @param string $type
     *
     * @return bool
     */
    public function isAllowed($type)
    {
        $currentUser = \Pimcore\Tool\Admin::getCurrentUser();
        //everything is allowed for admin
        if ($currentUser->isAdmin()) {
            return true;
        }

        return $this->getDao()->isAllowed($type, $currentUser);
    }

    public function unlockPropagate()
    {
        $type = Service::getType($this);
        $ids = $this->getDao()->unlockPropagate();

        // invalidate cache items
        foreach ($ids as $id) {
            $element = Service::getElementById($type, $id);
            if ($element) {
                $element->clearDependentCache();
            }
        }
    }

    protected function validatePathLength()
    {
        if (mb_strlen($this->getRealFullPath()) > 765) {
            throw new \Exception("Full path is limited to 765 characters, reduce the length of your parent's path");
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getFullPath();
    }

    /**
     * @return int
     */
    public function __getDataVersionTimestamp()
    {
        return $this->__dataVersionTimestamp;
    }

    /**
     * @param int $_dataVersionTimestamp
     */
    public function __setDataVersionTimestamp($_dataVersionTimestamp)
    {
        $this->__dataVersionTimestamp = $_dataVersionTimestamp;
    }

    /**
     * @return bool
     */
    public function __isBasedOnLatestData()
    {
        return $this->getDao()->__isBasedOnLatestData();
    }

    /**
     * @param null $versionNote
     * @param bool $saveOnlyVersion
     *
     * @return Model\Version
     *
     * @throws \Exception
     */
    protected function doSaveVersion($versionNote = null, $saveOnlyVersion = true)
    {
        /**
         * @var Model\Version $version
         */
        $version = self::getModelFactory()->build(Model\Version::class);
        $version->setCid($this->getId());
        $version->setCtype(Service::getElementType($this));
        $version->setDate($this->getModificationDate());
        $version->setUserId($this->getUserModification());
        $version->setData($this);
        $version->setNote($versionNote);

        if ($saveOnlyVersion) {
            $versionCount = $this->getDao()->getVersionCountForUpdate();
            $versionCount++;
        } else {
            $versionCount = $this->getVersionCount();
        }

        $version->setVersionCount($versionCount);
        $version->save();

        return $version;
    }
}
