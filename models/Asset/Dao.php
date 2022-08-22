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

namespace Pimcore\Model\Asset;

use Pimcore\Db\Helper;
use Pimcore\Loader\ImplementationLoader\Exception\UnsupportedException;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Asset\MetaData\ClassDefinition\Data\Data;
use Pimcore\Model\User;
use Pimcore\Tool\Serialize;

/**
 * @internal
 *
 * @property \Pimcore\Model\Asset $model
 */
class Dao extends Model\Element\Dao
{
    use Model\Element\Traits\ScheduledTasksDaoTrait;
    use Model\Element\Traits\VersionDaoTrait;

    /**
     * @internal
     *
     * @var array
     */
    public static array $thumbnailStatusCache = [];

    /**
     * Get the data for the object by id from database and assign it to the object (model)
     *
     * @param int $id
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getById($id)
    {
        $data = $this->db->fetchAssociative("SELECT assets.*, tree_locks.locked FROM assets
            LEFT JOIN tree_locks ON assets.id = tree_locks.id AND tree_locks.type = 'asset'
                WHERE assets.id = ?", [$id]);

        if (!empty($data['id'])) {
            $this->assignVariablesToModel($data);

            if ($data['hasMetaData']) {
                $metadataRaw = $this->db->fetchAllAssociative('SELECT * FROM assets_metadata WHERE cid = ?', [$data['id']]);
                $metadata = [];
                foreach ($metadataRaw as $md) {
                    $loader = \Pimcore::getContainer()->get('pimcore.implementation_loader.asset.metadata.data');

                    $transformedData = $md['data'];

                    $md['type'] = $md['type'] ?? 'input';

                    try {
                        /** @var Data $instance */
                        $instance = $loader->build($md['type']);
                        $transformedData = $instance->getDataFromResource($md['data'], $md);
                    } catch (UnsupportedException $e) {
                    }

                    $md['data'] = $transformedData;
                    unset($md['cid']);
                    $metadata[] = $md;
                }
                $this->model->setMetadataRaw($metadata);
            }
        } else {
            throw new Model\Exception\NotFoundException('Asset with ID ' . $id . " doesn't exists");
        }
    }

    /**
     * Get the data for the asset from database for the given path
     *
     * @param string $path
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getByPath($path)
    {
        $params = $this->extractKeyAndPath($path);
        $data = $this->db->fetchAssociative('SELECT id FROM assets WHERE path = :path AND `filename` = :key', $params);

        if (!empty($data['id'])) {
            $this->assignVariablesToModel($data);
        } else {
            throw new Model\Exception\NotFoundException('asset with path: ' . $path . " doesn't exist");
        }
    }

    public function create()
    {
        $this->db->insert('assets', [
            'filename' => $this->model->getFilename(),
            'path' => $this->model->getRealPath(),
            'parentId' => $this->model->getParentId(),
        ]);

        $this->model->setId((int) $this->db->lastInsertId());
    }

    public function update()
    {
        $asset = $this->model->getObjectVars();

        foreach ($asset as $key => $value) {
            if (in_array($key, $this->getValidTableColumns('assets'))) {
                if (is_array($value)) {
                    $value = Serialize::serialize($value);
                }
                $data[$key] = $value;
            }
        }

        // metadata
        $this->db->delete('assets_metadata', ['cid' => $this->model->getId()]);
        /** @var array $metadata */
        $metadata = $this->model->getMetadata(null, null, false, true);

        $data['hasMetaData'] = 0;
        $metadataItems = [];
        if (!empty($metadata)) {
            foreach ($metadata as $metadataItem) {
                $metadataItem['cid'] = $this->model->getId();
                unset($metadataItem['config']);

                $loader = \Pimcore::getContainer()->get('pimcore.implementation_loader.asset.metadata.data');

                $dataForResource = $metadataItem['data'];

                try {
                    /** @var Data $instance */
                    $instance = $loader->build($metadataItem['type']);
                    $dataForResource = $instance->getDataForResource($metadataItem['data'], $metadataItem);
                } catch (UnsupportedException $e) {
                }

                $metadataItem['data'] = $dataForResource;

                $metadataItem['language'] = (string) $metadataItem['language']; // language column cannot be NULL -> see SQL schema

                if (is_scalar($metadataItem['data'])) {
                    $data['hasMetaData'] = 1;
                    $metadataItems[] = $metadataItem;
                }
            }
        }

        Helper::insertOrUpdate($this->db, 'assets', $data);
        if ($data['hasMetaData'] && count($metadataItems)) {
            foreach ($metadataItems as $metadataItem) {
                $this->db->insert('assets_metadata', $metadataItem);
            }
        }

        // tree_locks
        $this->db->delete('tree_locks', ['id' => $this->model->getId(), 'type' => 'asset']);
        if ($this->model->getLocked()) {
            $this->db->insert('tree_locks', [
                'id' => $this->model->getId(),
                'type' => 'asset',
                'locked' => $this->model->getLocked(),
            ]);
        }
    }

    public function delete()
    {
        $this->db->delete('assets', ['id' => $this->model->getId()]);
    }

    public function updateWorkspaces()
    {
        $this->db->update('users_workspaces_asset', [
            'cpath' => $this->model->getRealFullPath(),
        ], [
            'cid' => $this->model->getId(),
        ]);
    }

    /**
     * @internal
     *
     * @param string $oldPath
     *
     * @return array
     */
    public function updateChildPaths($oldPath)
    {
        //get assets to empty their cache
        $assets = $this->db->fetchFirstColumn('SELECT id FROM assets WHERE path LIKE ' . $this->db->quote(Helper::escapeLike($oldPath) . '%'));

        $userId = '0';
        if ($user = \Pimcore\Tool\Admin::getCurrentUser()) {
            $userId = $user->getId();
        }

        //update assets child paths
        // we don't update the modification date here, as this can have side-effects when there's an unpublished version for an element
        $this->db->executeQuery('update assets set path = replace(path,' . $this->db->quote($oldPath . '/') . ',' . $this->db->quote($this->model->getRealFullPath() . '/') . "), userModification = '" . $userId . "' where path like " . $this->db->quote(Helper::escapeLike($oldPath) . '/%') . ';');

        //update assets child permission paths
        $this->db->executeQuery('update users_workspaces_asset set cpath = replace(cpath,' . $this->db->quote($oldPath . '/') . ',' . $this->db->quote($this->model->getRealFullPath() . '/') . ') where cpath like ' . $this->db->quote(Helper::escapeLike($oldPath) . '/%') . ';');

        //update assets child properties paths
        $this->db->executeQuery('update properties set cpath = replace(cpath,' . $this->db->quote($oldPath . '/') . ',' . $this->db->quote($this->model->getRealFullPath() . '/') . ') where cpath like ' . $this->db->quote(Helper::escapeLike($oldPath) . '/%') . ';');

        return $assets;
    }

    /**
     * Get the properties for the object from database and assign it
     *
     * @param bool $onlyInherited
     *
     * @return array
     */
    public function getProperties($onlyInherited = false)
    {
        $properties = [];

        // collect properties via parent - ids
        $parentIds = $this->getParentIds();
        $propertiesRaw = $this->db->fetchAllAssociative('SELECT * FROM properties WHERE ((cid IN (' . implode(',', $parentIds) . ") AND inheritable = 1) OR cid = ? )  AND ctype='asset'", [$this->model->getId()]);

        // because this should be faster than mysql
        usort($propertiesRaw, function ($left, $right) {
            return strcmp($left['cpath'], $right['cpath']);
        });

        foreach ($propertiesRaw as $propertyRaw) {
            try {
                $property = new Model\Property();
                $property->setType($propertyRaw['type']);
                $property->setCid($this->model->getId());
                $property->setName($propertyRaw['name']);
                $property->setCtype('asset');
                $property->setDataFromResource($propertyRaw['data']);
                $property->setInherited(true);
                if ($propertyRaw['cid'] == $this->model->getId()) {
                    $property->setInherited(false);
                }
                $property->setInheritable(false);
                if ($propertyRaw['inheritable']) {
                    $property->setInheritable(true);
                }

                if ($onlyInherited && !$property->getInherited()) {
                    continue;
                }

                $properties[$propertyRaw['name']] = $property;
            } catch (\Exception $e) {
                Logger::error("can't add property " . $propertyRaw['name'] . ' to asset ' . $this->model->getRealFullPath());
            }
        }

        // if only inherited then only return it and dont call the setter in the model
        if ($onlyInherited) {
            return $properties;
        }

        $this->model->setProperties($properties);

        return $properties;
    }

    /**
     * deletes all properties for the object from database
     */
    public function deleteAllProperties()
    {
        $this->db->delete('properties', ['cid' => $this->model->getId(), 'ctype' => 'asset']);
    }

    /**
     * @return string|null retrieves the current full set path from DB
     */
    public function getCurrentFullPath()
    {
        $path = null;

        try {
            $path = $this->db->fetchOne('SELECT CONCAT(path,filename) as path FROM assets WHERE id = ?', [$this->model->getId()]);
        } catch (\Exception $e) {
            Logger::error('could not get  current asset path from DB');
        }

        return $path;
    }

    /**
     * @return int
     */
    public function getVersionCountForUpdate(): int
    {
        if (!$this->model->getId()) {
            return 0;
        }

        $versionCount = (int) $this->db->fetchOne('SELECT versionCount FROM assets WHERE id = ? FOR UPDATE', [$this->model->getId()]);

        if (!$this->model instanceof Folder) {
            $versionCount2 = (int) $this->db->fetchOne("SELECT MAX(versionCount) FROM versions WHERE cid = ? AND ctype = 'asset'", [$this->model->getId()]);
            $versionCount = max($versionCount, $versionCount2);
        }

        return (int) $versionCount;
    }

    /**
     * quick test if there are children
     *
     * @param Model\User $user
     *
     * @return bool
     */
    public function hasChildren($user = null)
    {
        if (!$this->model->getId()) {
            return false;
        }

        $query = 'SELECT `a`.`id` FROM `assets` a  WHERE parentId = ? ';

        if ($user && !$user->isAdmin()) {
            $userIds = $user->getRoles();
            $currentUserId = $user->getId();
            $userIds[] = $currentUserId;

            $inheritedPermission = $this->isInheritingPermission('list', $userIds);

            $anyAllowedRowOrChildren = 'EXISTS(SELECT list FROM users_workspaces_asset uwa WHERE userId IN (' . implode(',', $userIds) . ') AND list=1 AND LOCATE(CONCAT(path,filename),cpath)=1 AND
                NOT EXISTS(SELECT list FROM users_workspaces_asset WHERE userId =' . $currentUserId . '  AND list=0 AND cpath = uwa.cpath))';
            $isDisallowedCurrentRow = 'EXISTS(SELECT list FROM users_workspaces_asset WHERE userId IN (' . implode(',', $userIds) . ')  AND cid = id AND list=0)';

            $query .= ' AND IF(' . $anyAllowedRowOrChildren . ',1,IF(' . $inheritedPermission . ', ' . $isDisallowedCurrentRow . ' = 0, 0)) = 1';
        }

        $query .= ' LIMIT 1;';
        $c = $this->db->fetchOne($query, [$this->model->getId()]);

        return (bool)$c;
    }

    /**
     * Quick test if there are siblings
     *
     * @return bool
     */
    public function hasSiblings()
    {
        if (!$this->model->getParentId()) {
            return false;
        }

        $sql = 'SELECT 1 FROM assets WHERE parentId = ?';
        $params = [$this->model->getParentId()];

        if ($this->model->getId()) {
            $sql .= ' AND id != ?';
            $params[] = $this->model->getId();
        }

        $sql .= ' LIMIT 1';

        $c = $this->db->fetchOne($sql, $params);

        return (bool)$c;
    }

    /**
     * returns the amount of directly children (not recursivly)
     *
     * @param Model\User $user
     *
     * @return int
     */
    public function getChildAmount($user = null)
    {
        if (!$this->model->getId()) {
            return 0;
        }

        $query = 'SELECT COUNT(*) AS count FROM assets WHERE parentId = ?';

        if ($user && !$user->isAdmin()) {
            $userIds = $user->getRoles();
            $currentUserId = $user->getId();
            $userIds[] = $currentUserId;

            $inheritedPermission = $this->isInheritingPermission('list', $userIds);

            $anyAllowedRowOrChildren = 'EXISTS(SELECT list FROM users_workspaces_asset uwa WHERE userId IN (' . implode(',', $userIds) . ') AND list=1 AND LOCATE(CONCAT(path,filename),cpath)=1 AND
                NOT EXISTS(SELECT list FROM users_workspaces_asset WHERE userId =' . $currentUserId . '  AND list=0 AND cpath = uwa.cpath))';
            $isDisallowedCurrentRow = 'EXISTS(SELECT list FROM users_workspaces_asset WHERE userId IN (' . implode(',', $userIds) . ')  AND cid = id AND list=0)';

            $query .= ' AND IF(' . $anyAllowedRowOrChildren . ',1,IF(' . $inheritedPermission . ', ' . $isDisallowedCurrentRow . ' = 0, 0)) = 1';
        }

        return (int) $this->db->fetchOne($query, [$this->model->getId()]);
    }

    /**
     * @return bool
     */
    public function isLocked()
    {
        // check for an locked element below this element
        $belowLocks = $this->db->fetchOne("SELECT tree_locks.id FROM tree_locks INNER JOIN assets ON tree_locks.id = assets.id WHERE assets.path LIKE ? AND tree_locks.type = 'asset' AND tree_locks.locked IS NOT NULL AND tree_locks.locked != '' LIMIT 1", [Helper::escapeLike($this->model->getRealFullPath()) . '/%']);

        if ($belowLocks > 0) {
            return true;
        }

        $parentIds = $this->getParentIds();
        $inhertitedLocks = $this->db->fetchOne('SELECT id FROM tree_locks WHERE id IN (' . implode(',', $parentIds) . ") AND type='asset' AND locked = 'propagate' LIMIT 1");

        if ($inhertitedLocks > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function unlockPropagate()
    {
        $lockIds = $this->db->fetchFirstColumn('SELECT id from assets WHERE path LIKE ' . $this->db->quote(Helper::escapeLike($this->model->getRealFullPath()) . '/%') . ' OR id = ' . $this->model->getId());
        $this->db->executeQuery("DELETE FROM tree_locks WHERE type = 'asset' AND id IN (" . implode(',', $lockIds) . ')');

        return $lockIds;
    }

    /**
     * @param string $type
     * @param array $userIds
     *
     * @return int
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function isInheritingPermission(string $type, array $userIds): int
    {
        return $this->InheritingPermission($type, $userIds, 'asset');
    }

    /**
     * @param string $type
     * @param Model\User $user
     *
     * @return bool
     */
    public function isAllowed($type, $user)
    {
        // collect properties via parent - ids
        $parentIds = [1];

        $obj = $this->model->getParent();
        if ($obj) {
            while ($obj) {
                $parentIds[] = $obj->getId();
                $obj = $obj->getParent();
            }
        }
        if ($id = $this->model->getId()) {
            $parentIds[] = $id;
        }

        $userIds = $user->getRoles();
        $userIds[] = $user->getId();

        try {
            $permissionsParent = $this->db->fetchOne('SELECT ' . $this->db->quoteIdentifier($type) . ' FROM users_workspaces_asset WHERE cid IN (' . implode(',', $parentIds) . ') AND userId IN (' . implode(',', $userIds) . ') ORDER BY LENGTH(cpath) DESC, FIELD(userId, ' . $user->getId() . ') DESC, ' . $this->db->quoteIdentifier($type) . ' DESC  LIMIT 1');

            if ($permissionsParent) {
                return true;
            }

            // exception for list permission
            if (empty($permissionsParent) && $type == 'list') {
                // check for children with permissions
                $path = $this->model->getRealFullPath() . '/';
                if ($this->model->getId() == 1) {
                    $path = '/';
                }

                $permissionsChildren = $this->db->fetchOne('SELECT list FROM users_workspaces_asset WHERE cpath LIKE ? AND userId IN (' . implode(',', $userIds) . ') AND list = 1 LIMIT 1', [Helper::escapeLike($path) . '%']);
                if ($permissionsChildren) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            Logger::warn('Unable to get permission ' . $type . ' for asset ' . $this->model->getId());
        }

        return false;
    }

    /**
     * @param array $columns
     * @param User $user
     *
     * @return array<string, int>
     *
     */
    public function areAllowed(array $columns, User $user)
    {
        return $this->permissionByTypes($columns, $user, 'asset');
    }

    public function updateCustomSettings()
    {
        $customSettingsData = Serialize::serialize($this->model->getCustomSettings());
        $this->db->update('assets', ['customSettings' => $customSettingsData], ['id' => $this->model->getId()]);
    }

    /**
     * @return bool
     */
    public function __isBasedOnLatestData()
    {
        $data = $this->db->fetchAssociative('SELECT modificationDate, versionCount from assets WHERE id = ?', [$this->model->getId()]);
        if ($data['modificationDate'] == $this->model->__getDataVersionTimestamp() && $data['versionCount'] == $this->model->getVersionCount()) {
            return true;
        }

        return false;
    }

    public function addToThumbnailCache(string $name, string $filename, int $filesize, int $width, int $height): void
    {
        $assetId = $this->model->getId();
        $thumb = [
            'cid' => $assetId,
            'name' => $name,
            'filename' => $filename,
            'modificationDate' => time(),
            'filesize' => $filesize,
            'width' => $width,
            'height' => $height,
        ];
        Helper::insertOrUpdate($this->db, 'assets_image_thumbnail_cache', $thumb);

        if (isset(self::$thumbnailStatusCache[$assetId])) {
            $hash = $name . $filename;
            self::$thumbnailStatusCache[$assetId][$hash] = $thumb;
        }
    }

    public function getCachedThumbnailModificationDate(string $name, string $filename): ?int
    {
        return $this->getCachedThumbnail($name, $filename)['modificationDate'] ?? null;
    }

    public function getCachedThumbnail(string $name, string $filename): ?array
    {
        $assetId = $this->model->getId();

        // we use a static var here, because it could be that an asset is serialized in the cache,
        // so this runtime cache wouldn't be as efficient
        if (!isset(self::$thumbnailStatusCache[$assetId])) {
            self::$thumbnailStatusCache[$assetId] = [];
            $thumbs = $this->db->fetchAllAssociative('SELECT * FROM assets_image_thumbnail_cache WHERE cid = :cid', [
                'cid' => $this->model->getId(),
            ]);

            foreach ($thumbs as $thumb) {
                $hash = $thumb['name'] . $thumb['filename'];
                self::$thumbnailStatusCache[$assetId][$hash] = $thumb;
            }
        }

        $hash = $name . $filename;

        return self::$thumbnailStatusCache[$assetId][$hash] ?? null;
    }

    public function deleteFromThumbnailCache(?string $name = null, ?string $filename = null): void
    {
        $assetId = $this->model->getId();
        $where = [
            'cid' => $assetId,
        ];

        if ($name) {
            $where['name'] = $name;
        }

        if ($filename) {
            $where['filename'] = $filename;
        }

        $this->db->delete('assets_image_thumbnail_cache', $where);
        unset(self::$thumbnailStatusCache[$assetId]);
    }
}
