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

namespace Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing;

use Pimcore\Model;
use Pimcore\Model\DataObject;

/**
 * @internal
 *
 * @property \Pimcore\Model\DataObject\Classificationstore\KeyGroupRelation\Listing $model
 */
class Dao extends Model\Listing\Dao\AbstractDao
{
    /**
     * Loads a list of Classificationstore group configs for the specified parameters, returns an array of config elements
     *
     * @return array
     */
    public function load()
    {
        $sql = 'SELECT ' . DataObject\Classificationstore\KeyGroupRelation\Dao::TABLE_NAME_RELATIONS . '.*,'
            . DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS . '.*';

        $resourceGroupName = $this->model->getResolveGroupName();

        if ($resourceGroupName) {
            $sql .= ', ' . DataObject\Classificationstore\GroupConfig\Dao::TABLE_NAME_GROUPS . '.name as groupName';
        }

        $sql .= $this->getFrom() . $this->getWhere() . $this->getOrder() . $this->getOffsetLimit();
        $data = $this->db->fetchAllAssociative($sql, $this->model->getConditionVariables());

        $configData = [];
        foreach ($data as $dataItem) {
            $entry = new DataObject\Classificationstore\KeyGroupRelation();
            $resource = $entry->getDao();
            $resource->assignVariablesToModel($dataItem);

            $configData[] = $entry;
        }

        $this->model->setList($configData);

        return $configData;
    }

    /**
     * @return array
     */
    public function getDataArray()
    {
        return $this->db->fetchAllAssociative('SELECT *' . $this->getFrom() . $this->getWhere() . $this->getOrder() . $this->getOffsetLimit(), $this->model->getConditionVariables());
    }

    /**
     * @return int
     */
    public function getTotalCount()
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*)' . $this->getFrom() . $this->getWhere(), $this->model->getConditionVariables());
    }

    private function getWhere(): string
    {
        $where = parent::getCondition();
        if ($where) {
            $where .= ' AND ';
        } else {
            $where = ' WHERE ';
        }
        $where .= DataObject\Classificationstore\KeyGroupRelation\Dao::TABLE_NAME_RELATIONS
            . '.keyId = ' . DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS . '.id';

        $resourceGroupName = $this->model->getResolveGroupName();

        if ($resourceGroupName) {
            $where .= ' and ' . DataObject\Classificationstore\GroupConfig\Dao::TABLE_NAME_GROUPS . '.id = '
                . DataObject\Classificationstore\KeyGroupRelation\Dao::TABLE_NAME_RELATIONS . '.groupId';
        }

        return $where;
    }

    private function getFrom(): string
    {
        $from = ' FROM ' . DataObject\Classificationstore\KeyGroupRelation\Dao::TABLE_NAME_RELATIONS
            . ',' . DataObject\Classificationstore\KeyConfig\Dao::TABLE_NAME_KEYS;
        $resourceGroupName = $this->model->getResolveGroupName();

        if ($resourceGroupName) {
            $from .= ', ' . DataObject\Classificationstore\GroupConfig\Dao::TABLE_NAME_GROUPS;
        }

        return $from;
    }
}
