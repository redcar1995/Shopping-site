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

use DeepCopy\Filter\SetNullFilter;
use DeepCopy\Matcher\PropertyNameMatcher;
use Doctrine\Common\Collections\Collection;
use Pimcore\Db;
use Pimcore\Db\ZendCompatibility\QueryBuilder;
use Pimcore\Event\SystemEvents;
use Pimcore\File;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\Dependency;
use Pimcore\Model\Document;
use Pimcore\Tool;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @method \Pimcore\Model\Element\Dao getDao()
 */
class Service extends Model\AbstractModel
{
    /**
     * @static
     *
     * @param ElementInterface $element
     *
     * @return string
     */
    public static function getIdPath($element)
    {
        $path = '';
        $parentElement = null;

        if ($element instanceof ElementInterface) {
            $elementType = self::getElementType($element);
            $parentId = $element->getParentId();
            $parentElement = self::getElementById($elementType, $parentId);
        }

        if ($parentElement) {
            $path = self::getIdPath($parentElement);
        }

        if ($element) {
            $path .= '/' . $element->getId();
        }

        return $path;
    }

    /**
     * @static
     *
     * @param ElementInterface $element
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function getTypePath($element)
    {
        $path = '';
        $parentElement = null;

        if ($element instanceof ElementInterface) {
            $elementType = self::getElementType($element);
            $parentId = $element->getParentId();
            $parentElement = self::getElementById($elementType, $parentId);
        }

        if ($parentElement) {
            $path = self::getTypePath($parentElement);
        }

        if ($element) {
            $type = $element->getType();
            if ($type !== 'folder') {
                if ($element instanceof Document) {
                    $type = 'document';
                } elseif ($element instanceof DataObject\AbstractObject) {
                    $type = 'object';
                } elseif ($element instanceof Asset) {
                    $type = 'asset';
                } else {
                    throw new \Exception('unknown type');
                }
            }
            $path .= '/' . $type;
        }

        return $path;
    }

    /**
     * @static
     *
     * @param $list array | \Pimcore\Model\Listing\AbstractListing
     * @param string $idGetter
     *
     * @return int[]
     */
    public static function getIdList($list, $idGetter = 'getId')
    {
        $ids = [];
        if (is_array($list)) {
            foreach ($list as $entry) {
                if (is_object($entry) && method_exists($entry, $idGetter)) {
                    $ids[] = $entry->$idGetter();
                } elseif (is_scalar($entry)) {
                    $ids[] = $entry;
                }
            }
        }

        if ($list instanceof \Pimcore\Model\Listing\AbstractListing) {
            $ids = $list->loadIdList();
        }
        $ids = array_unique($ids);

        return $ids;
    }

    /**
     * @param Dependency $d
     *
     * @return array
     */
    public static function getRequiredByDependenciesForFrontend(Dependency $d, $offset, $limit)
    {
        $dependencies['hasHidden'] = false;
        $dependencies['requiredBy'] = [];

        // requiredBy
        foreach ($d->getRequiredBy($offset, $limit) as $r) {
            if ($e = self::getDependedElement($r)) {
                if ($e->isAllowed('list')) {
                    $dependencies['requiredBy'][] = self::getDependencyForFrontend($e);
                } else {
                    $dependencies['hasHidden'] = true;
                }
            }
        }

        return $dependencies;
    }

    /**
     * @param Dependency $d
     *
     * @return array
     */
    public static function getRequiresDependenciesForFrontend(Dependency $d, $offset, $limit)
    {
        $dependencies['hasHidden'] = false;
        $dependencies['requires'] = [];

        // requires
        foreach ($d->getRequires($offset, $limit) as $r) {
            if ($e = self::getDependedElement($r)) {
                if ($e->isAllowed('list')) {
                    $dependencies['requires'][] = self::getDependencyForFrontend($e);
                } else {
                    $dependencies['hasHidden'] = true;
                }
            }
        }

        return $dependencies;
    }

    /**
     * @param Document|Asset|DataObject\AbstractObject $element
     *
     * @return array
     */
    public static function getDependencyForFrontend($element)
    {
        if ($element instanceof ElementInterface) {
            return [
                'id' => $element->getId(),
                'path' => $element->getRealFullPath(),
                'type' => self::getElementType($element),
                'subtype' => $element->getType()
            ];
        }
    }

    /**
     * @param array $config
     *
     * @return DataObject\AbstractObject|Document|Asset
     */
    public static function getDependedElement($config)
    {
        if ($config['type'] == 'object') {
            return DataObject::getById($config['id']);
        } elseif ($config['type'] == 'asset') {
            return Asset::getById($config['id']);
        } elseif ($config['type'] == 'document') {
            return Document::getById($config['id']);
        }

        return false;
    }

    /**
     * determines whether an element is published
     *
     * @static
     *
     * @param  ElementInterface $element
     *
     * @return bool
     */
    public static function isPublished($element = null)
    {
        if ($element instanceof ElementInterface) {
            if (method_exists($element, 'isPublished')) {
                return $element->isPublished();
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $data
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function filterUnpublishedAdvancedElements($data)
    {
        if (DataObject\AbstractObject::doHideUnpublished() and is_array($data)) {
            $publishedList = [];
            $mapping = [];
            foreach ($data as $advancedElement) {
                if (!$advancedElement instanceof DataObject\Data\ObjectMetadata
                    && !$advancedElement instanceof DataObject\Data\ElementMetadata) {
                    throw new \Exception('only supported for advanced many-to-many (+object) relations');
                }

                $elementId = null;
                if ($advancedElement instanceof DataObject\Data\ObjectMetadata) {
                    $elementId = $advancedElement->getObjectId();
                    $elementType = 'object';
                } else {
                    $elementId = $advancedElement->getElementId();
                    $elementType = $advancedElement->getElementType();
                }

                if (!$elementId) {
                    continue;
                }
                if ($elementType == 'asset') {
                    // there is no published flag for assets
                    continue;
                }
                $mapping[$elementType][$elementId] = true;
            }

            $db = Db::get();
            $publishedMapping = [];

            // now do the query;
            foreach ($mapping as $elementType => $idList) {
                $idList = array_keys($mapping[$elementType]);
                switch ($elementType) {
                    case 'document':
                        $idColumn = 'id';
                        $publishedColumn = 'published';
                        break;
                    case 'object':
                        $idColumn = 'o_id';
                        $publishedColumn = 'o_published';
                        break;
                    default:
                        throw new \Exception('unknown type');
                }
                $query = 'SELECT ' . $idColumn . ' FROM ' . $elementType . 's WHERE ' . $publishedColumn . '=1 AND ' . $idColumn . ' IN (' . implode(',', $idList) . ');';
                $publishedIds = $db->fetchCol($query);
                $publishedMapping[$elementType] = $publishedIds;
            }

            foreach ($data as $advancedElement) {
                $elementId = null;
                if ($advancedElement instanceof DataObject\Data\ObjectMetadata) {
                    $elementId = $advancedElement->getObjectId();
                    $elementType = 'object';
                } else {
                    $elementId = $advancedElement->getElementId();
                    $elementType = $advancedElement->getElementType();
                }

                if ($elementType == 'asset') {
                    $publishedList[] = $advancedElement;
                }

                if (isset($publishedMapping[$elementType]) && in_array($elementId, $publishedMapping[$elementType])) {
                    $publishedList[] = $advancedElement;
                }
            }

            return $publishedList;
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @static
     *
     * @param  string $type
     * @param  string $path
     *
     * @return ElementInterface|null
     */
    public static function getElementByPath($type, $path)
    {
        $element = null;

        if ($type == 'asset') {
            $element = Asset::getByPath($path);
        } elseif ($type == 'object') {
            $element = DataObject::getByPath($path);
        } elseif ($type == 'document') {
            $element = Document::getByPath($path);
        }

        return $element;
    }

    /**
     * @param string|ElementInterface $element
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function getBaseClassNameForElement($element)
    {
        if ($element instanceof ElementInterface) {
            $elementType = self::getElementType($element);
        } elseif (is_string($element)) {
            $elementType = $element;
        } else {
            throw new \Exception('Wrong type given for getBaseClassNameForElement(), ElementInterface and string are allowed');
        }

        $baseClass = ucfirst($elementType);
        if ($elementType == 'object') {
            $baseClass = 'DataObject';
        }

        return $baseClass;
    }

    /**
     * Returns a uniqe key for the element in the $target-Path (recursive)
     *
     * @static
     *
     * @return ElementInterface|string
     *
     * @param string $type
     * @param string $sourceKey
     * @param ElementInterface $target
     */
    public static function getSaveCopyName($type, $sourceKey, $target)
    {
        if (self::pathExists($target->getRealFullPath() . '/' . $sourceKey, $type)) {
            // only for assets: add the prefix _copy before the file extension (if exist) not after to that source.jpg will be source_copy.jpg and not source.jpg_copy
            if ($type == 'asset' && $fileExtension = File::getFileExtension($sourceKey)) {
                $sourceKey = str_replace('.' . $fileExtension, '_copy.' . $fileExtension, $sourceKey);
            } else {
                $sourceKey .= '_copy';
            }

            return self::getSaveCopyName($type, $sourceKey, $target);
        }

        return $sourceKey;
    }

    /**
     * @static
     *
     * @param $type
     * @param $path
     *
     * @return bool
     */
    public static function pathExists($path, $type = null)
    {
        if ($type == 'asset') {
            return Asset\Service::pathExists($path);
        } elseif ($type == 'document') {
            return Document\Service::pathExists($path);
        } elseif ($type == 'object') {
            return DataObject\Service::pathExists($path);
        }

        return;
    }

    /**
     * @static
     *
     * @param  string $type
     * @param  int $id
     * @param  bool $force
     *
     * @return ElementInterface
     */
    public static function getElementById($type, $id, $force = false)
    {
        $element = null;
        if ($type === 'asset') {
            $element = Asset::getById($id, $force);
        } elseif ($type === 'object') {
            $element = DataObject::getById($id, $force);
        } elseif ($type === 'document') {
            $element = Document::getById($id, $force);
        }

        return $element;
    }

    /**
     * @static
     *
     * @param  ElementInterface $element $element
     *
     * @return string
     */
    public static function getElementType($element)
    {
        if ($element instanceof DataObject\AbstractObject) {
            return 'object';
        }

        if ($element instanceof Document) {
            return 'document';
        }

        if ($element instanceof Asset) {
            return 'asset';
        }

        return null;
    }

    /**
     * @param ElementInterface $element
     *
     * @return string
     */
    public static function getElementHash(ElementInterface $element): string
    {
        return self::getElementType($element) . '-' . $element->getId();
    }

    /**
     * determines the type of an element (object,asset,document)
     *
     * @static
     *
     * @param  ElementInterface $element
     *
     * @return string
     */
    public static function getType($element)
    {
        return self::getElementType($element);
    }

    /**
     * @static
     *
     * @param  $props
     *
     * @return array
     */
    public static function minimizePropertiesForEditmode($props)
    {
        $properties = [];
        foreach ($props as $key => $p) {

            //$p = object2array($p);
            $allowedProperties = [
                'key',
                'o_key',
                'filename',
                'path',
                'o_path',
                'id',
                'o_id',
                'o_type',
                'type'
            ];

            if ($p->getData() instanceof Document || $p->getData() instanceof Asset || $p->getData() instanceof DataObject\AbstractObject) {
                $pa = [];

                $vars = $p->getData()->getObjectVars();

                foreach ($vars as $k => $value) {
                    if (in_array($k, $allowedProperties)) {
                        $pa[$k] = $value;
                    }
                }

                // clone it because of caching
                $tmp = clone $p;
                $tmp->setData($pa);
                $properties[$key] = $tmp->getObjectVars();
            } else {
                $properties[$key] = $p->getObjectVars();
            }

            // add config from predefined properties
            if ($p->getName() && $p->getType()) {
                $predefined = Model\Property\Predefined::getByKey($p->getName());

                if ($predefined && $predefined->getType() == $p->getType()) {
                    $properties[$key]['config'] = $predefined->getConfig();
                    $properties[$key]['description'] = $predefined->getDescription();
                }
            }
        }

        return $properties;
    }

    /**
     * @param DataObject\AbstractObject|Document|Asset\Folder $target the parent element
     * @param ElementInterface $new the newly inserted child
     */
    protected function updateChildren($target, $new)
    {
        if (is_array($target->getChildren())) {
            //check in case of recursion
            $found = false;
            foreach ($target->getChildren() as $child) {
                /**
                 * @var ElementInterface $child
                 */
                if ($child->getId() == $new->getId()) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $target->setChildren(array_merge($target->getChildren(), [$new]));
            }
        } else {
            $target->setChildren([$new]);
        }
    }

    /**
     * @param  ElementInterface $element
     *
     * @return array
     */
    public static function gridElementData(ElementInterface $element)
    {
        $data = [
            'id' => $element->getId(),
            'fullpath' => $element->getRealFullPath(),
            'type' => self::getType($element),
            'subtype' => $element->getType(),
            'filename' => self::getFilename($element),
            'creationDate' => $element->getCreationDate(),
            'modificationDate' => $element->getModificationDate()
        ];

        if (method_exists($element, 'isPublished')) {
            $data['published'] = $element->isPublished();
        } else {
            $data['published'] = true;
        }

        return $data;
    }

    /**
     * @param ElementInterface $element
     *
     * @return string
     */
    public static function getFilename(ElementInterface $element)
    {
        if ($element instanceof Document || $element instanceof DataObject\AbstractObject) {
            return $element->getKey();
        } elseif ($element instanceof Asset) {
            return $element->getFilename();
        }
    }

    /**
     * find all elements which the user may not list and therefore may never be shown to the user
     *
     * @param string $type asset|object|document
     * @param $user
     *
     * @return array
     */
    public static function findForbiddenPaths($type, $user)
    {
        if ($user->isAdmin()) {
            return [];
        }

        // get workspaces
        $workspaces = $user->{'getWorkspaces' . ucfirst($type)}();
        foreach ($user->getRoles() as $roleId) {
            $role = Model\User\Role::getById($roleId);
            $workspaces = array_merge($workspaces, $role->{'getWorkspaces' . ucfirst($type)}());
        }

        $forbidden = [];
        if (count($workspaces) > 0) {
            foreach ($workspaces as $workspace) {
                if (!$workspace->getList()) {
                    $forbidden[] = $workspace->getCpath();
                }
            }
        } else {
            $forbidden[] = '/';
        }

        return $forbidden;
    }

    /**
     * renews all references, for example after unserializing an ElementInterface
     *
     * @param Document|Asset|DataObject\AbstractObject $data
     * @param bool $initial
     * @param string $key
     *
     * @return mixed
     */
    public static function renewReferences($data, $initial = true, $key = null)
    {
        if ($data instanceof \__PHP_Incomplete_Class) {
            Logger::err(sprintf('Renew References: Cannot read data (%s) of incomplete class.', is_null($key) ? 'not available' : $key));

            return null;
        }

        if (is_array($data)) {
            foreach ($data as $dataKey => &$value) {
                $value = self::renewReferences($value, false, $dataKey);
            }

            return $data;
        } elseif (is_object($data)) {
            if ($data instanceof ElementInterface && !$initial) {
                return self::getElementById(self::getElementType($data), $data->getId());
            } else {

                // if this is the initial element set the correct path and key
                if ($data instanceof ElementInterface && $initial) {
                    $originalElement = self::getElementById(self::getElementType($data), $data->getId());

                    if ($originalElement) {
                        if ($data instanceof Asset) {
                            $data->setFilename($originalElement->getFilename());
                        } elseif ($data instanceof Document) {
                            $data->setKey($originalElement->getKey());
                        } elseif ($data instanceof DataObject\AbstractObject) {
                            $data->setKey($originalElement->getKey());
                        }

                        if (!DataObject\AbstractObject::doNotRestoreKeyAndPath()) {
                            $data->setPath($originalElement->getRealPath());
                        }
                    }
                }

                if ($data instanceof Model\AbstractModel) {
                    $properties = $data->getObjectVars();
                    foreach ($properties as $name => $value) {
                        $data->setObjectVar($name, self::renewReferences($value, false, $name), true);
                    }
                } else {
                    $properties = method_exists($data, 'getObjectVars') ? $data->getObjectVars() : get_object_vars($data);
                    foreach ($properties as $name => $value) {
                        if (method_exists($data, 'setObjectVar')) {
                            $data->setObjectVar($name, self::renewReferences($value, false, $name), true);
                        } else {
                            $data->$name = self::renewReferences($value, false, $name);
                        }
                    }
                }

                return $data;
            }
        }

        return $data;
    }

    /**
     * @static
     *
     * @param string $path
     *
     * @return string
     */
    public static function correctPath($path)
    {
        // remove trailing slash
        if ($path != '/') {
            $path = rtrim($path, '/ ');
        }

        // correct wrong path (root-node problem)
        $path = str_replace('//', '/', $path);

        if (strpos($path, '%') !== false) {
            $path = rawurldecode($path);
        }

        return $path;
    }

    /**
     * @static
     *
     * @param ElementInterface $element
     *
     * @return ElementInterface
     */
    public static function loadAllFields(ElementInterface $element)
    {
        if ($element instanceof Document) {
            Document\Service::loadAllDocumentFields($element);
        } elseif ($element instanceof DataObject\Concrete) {
            DataObject\Service::loadAllObjectFields($element);
        } elseif ($element instanceof Asset) {
            Asset\Service::loadAllFields($element);
        }

        return $element;
    }

    /**
     * clean up broken views which were generated by localized fields, ....
     * when removing a language the view isn't valid anymore
     */
    public function cleanupBrokenViews()
    {
        $this->getDao()->cleanupBrokenViews();
    }

    /** Callback for array_filter function.
     * @param string $var value
     *
     * @return bool true if value is accepted
     */
    private static function filterNullValues($var)
    {
        return strlen($var) > 0;
    }

    /**
     * @param $path
     * @param array $options
     *
     * @return Asset\Folder|Document\Folder|DataObject\Folder
     *
     * @throws \Exception
     */
    public static function createFolderByPath($path, $options = [])
    {
        $calledClass = get_called_class();
        if ($calledClass == __CLASS__) {
            throw new \Exception('This method must be called from a extended class. e.g Asset\\Service, DataObject\\Service, Document\\Service');
        }

        $type = str_replace('\Service', '', $calledClass);
        $type = '\\' . ltrim($type, '\\');
        $folderType = $type . '\Folder';

        $lastFolder = null;
        $pathsArray = [];
        $parts = explode('/', $path);
        $parts = array_filter($parts, '\\Pimcore\\Model\\Element\\Service::filterNullValues');

        $sanitizedPath = '/';

        $itemType = self::getElementType(new $type);

        foreach ($parts as $part) {
            $sanitizedPath = $sanitizedPath . self::getValidKey($part, $itemType) . '/';
        }

        if (self::pathExists($sanitizedPath, $itemType)) {
            return $type::getByPath($sanitizedPath);
        }

        foreach ($parts as $part) {
            $pathPart = $pathsArray[count($pathsArray) - 1] ?? '';
            $pathsArray[] = $pathPart . '/' . self::getValidKey($part, $itemType);
        }

        for ($i = 0; $i < count($pathsArray); $i++) {
            $currentPath = $pathsArray[$i];
            if (!self::pathExists($currentPath, $itemType)) {
                $parentFolderPath = ($i == 0) ? '/' : $pathsArray[$i - 1];

                $parentFolder = $type::getByPath($parentFolderPath);

                $folder = new $folderType();
                $folder->setParent($parentFolder);
                if ($parentFolder) {
                    $folder->setParentId($parentFolder->getId());
                } else {
                    $folder->setParentId(1);
                }

                $key = substr($currentPath, strrpos($currentPath, '/') + 1, strlen($currentPath));

                if (method_exists($folder, 'setKey')) {
                    $folder->setKey($key);
                }

                if (method_exists($folder, 'setFilename')) {
                    $folder->setFilename($key);
                }

                if (method_exists($folder, 'setType')) {
                    $folder->setType('folder');
                }

                $folder->setPath($currentPath);
                $folder->setUserModification(0);
                $folder->setUserOwner(1);
                $folder->setCreationDate(time());
                $folder->setModificationDate(time());
                $folder->setValues($options);
                $folder->save();
                $lastFolder = $folder;
            }
        }

        return $lastFolder;
    }

    /** Changes the query according to the custom view config
     * @param $cv array
     * @param $childsList
     */
    public static function addTreeFilterJoins($cv, $childsList)
    {
        if ($cv) {
            $childsList->onCreateQuery(static function (QueryBuilder $select) use ($cv) {
                $where = $cv['where'] ?? null;
                if ($where) {
                    $select->where($where);
                }

                $customViewJoins = $cv['joins'] ?? null;
                if ($customViewJoins) {
                    foreach ($customViewJoins as $joinConfig) {
                        $type = $joinConfig['type'];
                        $method = $type == 'left' || $type == 'right' ? $method = 'join' . ucfirst($type) : 'join';
                        $name = $joinConfig['name'];
                        $condition = $joinConfig['condition'];
                        $columns = $joinConfig['columns'];
                        $select->$method($name, $condition, $columns);
                    }
                }

                if (!empty($cv['having'])) {
                    $select->having($cv['having']);
                }
            });
        }
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public static function getCustomViewById($id)
    {
        $customViews = Tool::getCustomViewConfig();
        if ($customViews) {
            foreach ($customViews as $customView) {
                if ($customView['id'] == $id) {
                    return $customView;
                }
            }
        }
    }

    /**
     * @param string $key
     * @param null $type
     *
     * @return mixed|string
     */
    public static function getValidKey($key, $type)
    {
        $event = new GenericEvent(null, [
            'key' => $key,
            'type' => $type
        ]);
        \Pimcore::getEventDispatcher()->dispatch(SystemEvents::SERVICE_PRE_GET_VALID_KEY, $event);
        $key = $event->getArgument('key');
        $key = trim($key);

        // replace all 4 byte unicode characters
        $key = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '-', $key);
        // replace slashes with a hyphen
        $key = str_replace('/', '-', $key);

        if ($type === 'object') {
            $key = preg_replace('/[<>]/', '-', $key);
        } elseif ($type === 'document') {
            // replace URL reserved characters with a hyphen
            $key = preg_replace('/[#\?\*\:\\\\<\>\|"%&@=;]/', '-', $key);
        } elseif ($type === 'asset') {
            // keys shouldn't start with a "." (=hidden file) *nix operating systems
            // keys shouldn't end with a "." - Windows issue: filesystem API trims automatically . at the end of a folder name (no warning ... et al)
            $key = trim($key, '. ');

            // windows forbidden filenames + URL reserved characters (at least the ones which are problematic)
            $key = preg_replace('/[#\?\*\:\\\\<\>\|"%]/', '-', $key);
        } else {
            $key = ltrim($key, '. ');
        }

        $key = mb_substr($key, 0, 255);

        return $key;
    }

    /**
     * @param string $key
     * @param string $type
     *
     * @return bool
     */
    public static function isValidKey($key, $type)
    {
        return self::getValidKey($key, $type) == $key;
    }

    /**
     * @param string $path
     * @param string $type
     *
     * @return bool
     */
    public static function isValidPath($path, $type)
    {
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if (!self::isValidKey($part, $type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * returns a unique key for an element
     *
     * @param $element
     *
     * @return string
     */
    public static function getUniqueKey($element)
    {
        if ($element instanceof DataObject\AbstractObject) {
            return DataObject\Service::getUniqueKey($element);
        }

        if ($element instanceof Document) {
            return Document\Service::getUniqueKey($element);
        }

        if ($element instanceof Asset) {
            return Asset\Service::getUniqueKey($element);
        }
    }

    /**
     * @param $data
     * @param $type
     *
     * @return array|string
     */
    public static function fixAllowedTypes($data, $type)
    {
        // this is the new method with Ext.form.MultiSelect
        if (is_array($data) && count($data)) {
            $first = reset($data);
            if (!is_array($first)) {
                $parts = $data;
                $data = [];
                foreach ($parts as $elementType) {
                    $data[] = [$type => $elementType];
                }
            } else {
                $newList = [];
                foreach ($data as $key => $item) {
                    if ($item) {
                        if (is_array($item)) {
                            foreach ($item as $itemKey => $itemValue) {
                                if ($itemValue) {
                                    $newList[$key][$itemKey] = $itemValue;
                                }
                            }
                        } elseif ($item) {
                            $newList[$key] = $item;
                        }
                    }
                }

                $data = $newList;
            }
        }

        return $data ? $data : [];
    }

    /**
     * @param $versions
     *
     * @return array
     */
    public static function getSafeVersionInfo($versions)
    {
        $indexMap = [];

        if (is_array($versions)) {
            $versions = json_decode(json_encode($versions), true);
            $result = [];
            /** @var $version Model\Version */
            foreach ($versions as $version) {
                $name = $version['user']['name'];
                $id = $version['user']['id'];
                unset($version['user']);
                $version['user']['name'] = $name;
                $version['user']['id'] = $id;
                $versionKey = $version['date'] . '-' . $version['versionCount'];
                if (!isset($indexMap[$versionKey])) {
                    $indexMap[$versionKey] = 0;
                }
                $version['index'] = $indexMap[$versionKey];
                $indexMap[$versionKey] = $indexMap[$versionKey] + 1;

                $result[] = $version;
            }

            return $result;
        }
    }

    /**
     * @see
     *
     * @param ElementInterface $element
     *
     * @return ElementInterface
     */
    public static function cloneMe(ElementInterface $element)
    {
        $deepCopy = new \DeepCopy\DeepCopy();
        $deepCopy->addFilter(new \DeepCopy\Filter\KeepFilter(), new class($element) implements \DeepCopy\Matcher\Matcher {
            /**
             * The element to be cloned
             *
             * @var  ElementInterface
             */
            private $element;

            /**
             * @param ElementInterface $element
             */
            public function __construct($element)
            {
                $this->element = $element;
            }

            /**
             * {@inheritdoc}
             */
            public function matches($object, $property)
            {
                try {
                    $reflectionProperty = new \ReflectionProperty($object, $property);
                } catch (\Exception $e) {
                    return false;
                }
                $reflectionProperty->setAccessible(true);
                $myValue = $reflectionProperty->getValue($object);

                return $myValue instanceof ElementInterface;
            }
        });

        $deepCopy->addFilter(new SetNullFilter(), new PropertyNameMatcher('dao'));
        $deepCopy->addFilter(new SetNullFilter(), new PropertyNameMatcher('resource'));
        $deepCopy->addFilter(new SetNullFilter(), new PropertyNameMatcher('writeResource'));
        $deepCopy->addFilter(new \DeepCopy\Filter\Doctrine\DoctrineCollectionFilter(), new \DeepCopy\Matcher\PropertyTypeMatcher(
            Collection::class
        ));

        if ($element instanceof DataObject\Concrete) {
            DataObject\Service::loadAllObjectFields($element);
        }

        $theCopy = $deepCopy->copy($element);
        $theCopy->setId(null);
        $theCopy->setParent(null);

        return $theCopy;
    }

    /**
     * @param Note $note
     *
     * @return array
     */
    public static function getNoteData(Note $note)
    {
        $cpath = '';
        if ($note->getCid() && $note->getCtype()) {
            if ($element = Service::getElementById($note->getCtype(), $note->getCid())) {
                $cpath = $element->getRealFullPath();
            }
        }

        $e = [
            'id' => $note->getId(),
            'type' => $note->getType(),
            'cid' => $note->getCid(),
            'ctype' => $note->getCtype(),
            'cpath' => $cpath,
            'date' => $note->getDate(),
            'title' => $note->getTitle(),
            'description' => $note->getDescription()
        ];

        // prepare key-values
        $keyValues = [];
        if (is_array($note->getData())) {
            foreach ($note->getData() as $name => $d) {
                $type = $d['type'];
                $data = $d['data'];

                if ($type == 'document' || $type == 'object' || $type == 'asset') {
                    if ($d['data'] instanceof ElementInterface) {
                        $data = [
                            'id' => $d['data']->getId(),
                            'path' => $d['data']->getRealFullPath(),
                            'type' => $d['data']->getType()
                        ];
                    }
                } elseif ($type == 'date') {
                    if (is_object($d['data'])) {
                        $data = $d['data']->getTimestamp();
                    }
                }

                $keyValue = [
                    'type' => $type,
                    'name' => $name,
                    'data' => $data
                ];

                $keyValues[] = $keyValue;
            }
        }

        $e['data'] = $keyValues;

        // prepare user data
        if ($note->getUser()) {
            $user = Model\User::getById($note->getUser());
            if ($user) {
                $e['user'] = [
                    'id' => $user->getId(),
                    'name' => $user->getName()
                ];
            } else {
                $e['user'] = '';
            }
        }

        return $e;
    }
}
