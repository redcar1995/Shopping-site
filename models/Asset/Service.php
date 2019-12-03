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
 * @package    Asset
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Asset;

use Pimcore\Event\AssetEvents;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\Element;

/**
 * @method \Pimcore\Model\Asset\Dao getDao()
 */
class Service extends Model\Element\Service
{
    /**
     * @var array
     */
    public static $gridSystemColumns = ['preview', 'id', 'type', 'fullpath', 'filename', 'creationDate', 'modificationDate', 'size'];

    /**
     * @var Model\User|null
     */
    protected $_user;
    /**
     * @var array
     */
    protected $_copyRecursiveIds;

    /**
     * @param Model\User $user
     */
    public function __construct($user = null)
    {
        $this->_user = $user;
    }

    /**
     * @param  Asset|Asset\Folder $target
     * @param  Asset|Asset\Folder $source
     *
     * @return Asset copied asset
     */
    public function copyRecursive($target, $source)
    {

        // avoid recursion
        if (!$this->_copyRecursiveIds) {
            $this->_copyRecursiveIds = [];
        }
        if (in_array($source->getId(), $this->_copyRecursiveIds)) {
            return;
        }

        $source->getProperties();

        $new = Element\Service::cloneMe($source);
        $new->id = null;
        if ($new instanceof Asset\Folder) {
            $new->setChildren(null);
        }

        $new->setFilename(Element\Service::getSaveCopyName('asset', $new->getFilename(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user ? $this->_user->getId() : 0);
        $new->setUserModification($this->_user ? $this->_user->getId() : 0);
        $new->setDao(null);
        $new->setLocked(false);
        $new->setCreationDate(time());
        $new->setStream($source->getStream());
        $new->save();

        // add to store
        $this->_copyRecursiveIds[] = $new->getId();

        foreach ($source->getChildren() as $child) {
            $this->copyRecursive($new, $child);
        }

        if ($target instanceof Asset\Folder) {
            $this->updateChildren($target, $new);
        }

        // triggers actions after the complete asset cloning
        \Pimcore::getEventDispatcher()->dispatch(AssetEvents::POST_COPY, new AssetEvent($new, [
            'base_element' => $source // the element used to make a copy
        ]));

        return $new;
    }

    /**
     * @param  Asset $target
     * @param  Asset $source
     *
     * @return Asset copied asset
     */
    public function copyAsChild($target, $source)
    {
        $source->getProperties();

        $new = Element\Service::cloneMe($source);
        $new->setId(null);

        if ($new instanceof Asset\Folder) {
            $new->setChildren(null);
        }
        $new->setFilename(Element\Service::getSaveCopyName('asset', $new->getFilename(), $target));
        $new->setParentId($target->getId());
        $new->setUserOwner($this->_user ? $this->_user->getId() : 0);
        $new->setUserModification($this->_user ? $this->_user->getId() : 0);
        $new->setDao(null);
        $new->setLocked(false);
        $new->setCreationDate(time());
        $new->setStream($source->getStream());
        $new->save();

        if ($target instanceof Asset\Folder) {
            $this->updateChildren($target, $new);
        }

        // triggers actions after the complete asset cloning
        \Pimcore::getEventDispatcher()->dispatch(AssetEvents::POST_COPY, new AssetEvent($new, [
            'base_element' => $source // the element used to make a copy
        ]));

        return $new;
    }

    /**
     * @param $target
     * @param $source
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function copyContents($target, $source)
    {

        // check if the type is the same
        if (get_class($source) != get_class($target)) {
            throw new \Exception('Source and target have to be the same type');
        }

        if (!$source instanceof Asset\Folder) {
            $target->setStream($source->getStream());
            $target->setCustomSettings($source->getCustomSettings());
        }

        $target->setUserModification($this->_user ? $this->_user->getId() : 0);
        $newProps = Element\Service::cloneMe($source->getProperties());
        $target->setProperties($newProps);
        $target->save();

        return $target;
    }

    /**
     * @param $asset
     * @param null $fields
     * @param null $requestedLanguage
     * @param array $params
     *
     * @return array
     */
    public static function gridAssetData($asset, $fields = null, $requestedLanguage = null, $params = [])
    {
        $data = Element\Service::gridElementData($asset);

        if ($asset instanceof Asset && !empty($fields)) {
            $data = [
                'id' => $asset->getId(),
                'id~system' => $asset->getId(),
                'type~system' => $asset->getType(),
                'fullpath~system' => $asset->getRealFullPath(),
                'filename~system' => $asset->getKey(),
                'creationDate~system' => $asset->getCreationDate(),
                'modificationDate~system' => $asset->getModificationDate(),
                'idPath~system' => Element\Service::getIdPath($asset),
            ];

            $requestedLanguage = str_replace('default', '', $requestedLanguage);

            foreach ($fields as $field) {
                $fieldDef = explode('~', $field);
                if (isset($fieldDef[1]) && $fieldDef[1] === 'system') {
                    if ($fieldDef[0] === 'preview') {
                        $data[$field] = self::getPreviewThumbnail($asset, ['width' => 108, 'height' => 70, 'frame' => true]);
                    } elseif ($fieldDef[0] === 'size') {
                        /** @var $asset Asset */
                        $filename = PIMCORE_ASSET_DIRECTORY . '/' . $asset->getRealFullPath();
                        $size = @filesize($filename);
                        $data[$field] = formatBytes($size);
                    }
                } else {
                    if (isset($fieldDef[1])) {
                        $language = ($fieldDef[1] === 'none' ? '' : $fieldDef[1]);
                        $metaData = $asset->getMetadata($fieldDef[0], $language, true);
                    } else {
                        $metaData = $asset->getMetadata($field, $requestedLanguage, true);
                    }

                    if ($metaData instanceof Model\Element\AbstractElement) {
                        $metaData = $metaData->getFullPath();
                    }

                    $data[$field] = $metaData;
                }
            }
        }

        return $data;
    }

    /**
     * @param $asset
     * @param array $params
     * @param bool $onlyMethod
     *
     * @return string|null
     */
    public static function getPreviewThumbnail($asset, $params = [], $onlyMethod = false)
    {
        $thumbnailMethod = '';
        $thumbnailUrl = null;

        if ($asset instanceof Asset\Image) {
            $thumbnailMethod = 'getThumbnail';
        } elseif ($asset instanceof Asset\Video && \Pimcore\Video::isAvailable()) {
            $thumbnailMethod = 'getImageThumbnail';
        } elseif ($asset instanceof Asset\Document && \Pimcore\Document::isAvailable()) {
            $thumbnailMethod = 'getImageThumbnail';
        }

        if ($onlyMethod) {
            return $thumbnailMethod;
        }

        if (!empty($thumbnailMethod)) {
            $thumbnailUrl = '/admin/asset/get-' . $asset->getType() . '-thumbnail?id=' . $asset->getId();
            if (count($params) > 0) {
                $thumbnailUrl .= '&' . http_build_query($params);
            }
        }

        return $thumbnailUrl;
    }

    /**
     * @static
     *
     * @param $path
     * @param null $type
     *
     * @return bool
     */
    public static function pathExists($path, $type = null)
    {
        $path = Element\Service::correctPath($path);

        try {
            $asset = new Asset();

            if (self::isValidPath($path, 'asset')) {
                $asset->getDao()->getByPath($path);

                return true;
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @static
     *
     * @param Element\ElementInterface $element
     *
     * @return Element\ElementInterface
     */
    public static function loadAllFields(Element\ElementInterface $element)
    {
        $element->getProperties();

        return $element;
    }

    /**
     * Rewrites id from source to target, $rewriteConfig contains
     * array(
     *  "document" => array(
     *      SOURCE_ID => TARGET_ID,
     *      SOURCE_ID => TARGET_ID
     *  ),
     *  "object" => array(...),
     *  "asset" => array(...)
     * )
     *
     * @param $asset
     * @param $rewriteConfig
     *
     * @return Asset
     */
    public static function rewriteIds($asset, $rewriteConfig)
    {

        // rewriting properties
        $properties = $asset->getProperties();
        foreach ($properties as &$property) {
            $property->rewriteIds($rewriteConfig);
        }
        $asset->setProperties($properties);

        return $asset;
    }

    /**
     * @param $metadata
     *
     * @return array
     */
    public static function minimizeMetadata($metadata)
    {
        if (!is_array($metadata)) {
            return $metadata;
        }

        $result = [];
        foreach ($metadata as $item) {
            $type = $item['type'];
            switch ($type) {
                case 'document':
                case 'asset':
                case 'object':
                    {
                        $element = Element\Service::getElementByPath($type, $item['data']);
                        if ($element) {
                            $item['data'] = $element->getId();
                        } else {
                            $item['data'] = '';
                        }
                    }

                    break;
                default:
                    //nothing to do
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param $metadata
     *
     * @return array
     */
    public static function expandMetadataForEditmode($metadata)
    {
        if (!is_array($metadata)) {
            return $metadata;
        }

        $result = [];
        foreach ($metadata as $item) {
            $type = $item['type'];
            switch ($type) {
                case 'document':
                case 'asset':
                case 'object':
                {
                    $element = $item['data'];
                    if (is_numeric($item['data'])) {
                        $element = Element\Service::getElementById($type, $item['data']);
                    }
                    if ($element instanceof Element\ElementInterface) {
                        $item['data'] = $element->getRealFullPath();
                    } else {
                        $item['data'] = '';
                    }
                }

                    break;
                default:
                    //nothing to do
            }

            //get the config from an predefined property-set (eg. select)
            $predefined = Model\Metadata\Predefined::getByName($item['name']);
            if ($predefined && $predefined->getType() == $item['type'] && $predefined->getConfig()) {
                $item['config'] = $predefined->getConfig();
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param $item \Pimcore\Model\Asset
     * @param int $nr
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function getUniqueKey($item, $nr = 0)
    {
        $list = new Listing();
        $key = Element\Service::getValidKey($item->getKey(), 'asset');
        if (!$key) {
            throw new \Exception('No item key set.');
        }
        if ($nr) {
            if ($item->getType() == 'folder') {
                $key = $key . '_' . $nr;
            } else {
                $keypart = substr($key, 0, strrpos($key, '.'));
                $extension = str_replace($keypart, '', $key);
                $key = $keypart . '_' . $nr . $extension;
            }
        }

        $parent = $item->getParent();
        if (!$parent) {
            throw new \Exception('You have to set a parent folder to determine a unique Key');
        }

        if (!$item->getId()) {
            $list->setCondition('parentId = ? AND `filename` = ? ', [$parent->getId(), $key]);
        } else {
            $list->setCondition('parentId = ? AND `filename` = ? AND id != ? ', [$parent->getId(), $key, $item->getId()]);
        }
        $check = $list->loadIdList();
        if (!empty($check)) {
            $nr++;
            $key = self::getUniqueKey($item, $nr);
        }

        return $key;
    }
}
