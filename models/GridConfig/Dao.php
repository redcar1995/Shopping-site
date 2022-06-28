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

namespace Pimcore\Model\GridConfig;

use Pimcore\Db\Helper;
use Pimcore\Model;
use Pimcore\Model\Exception\NotFoundException;

/**
 * @internal
 *
 * @property \Pimcore\Model\GridConfig $model
 */
class Dao extends Model\Dao\AbstractDao
{
    /**
     * @param int $id
     *
     * @throws NotFoundException
     */
    public function getById($id)
    {
        $data = $this->db->fetchAssociative('SELECT * FROM gridconfigs WHERE id = ?', [$id]);

        if (!$data) {
            throw new NotFoundException('gridconfig with id ' . $id . ' not found');
        }

        $this->assignVariablesToModel($data);
    }

    /**
     * Save object to database
     *
     * @return int
     */
    public function save()
    {
        $gridconfigs = $this->model->getObjectVars();
        $data = [];

        foreach ($gridconfigs as $key => $value) {
            if (in_array($key, $this->getValidTableColumns('gridconfigs'))) {
                if (is_bool($value)) {
                    $value = (int) $value;
                }

                $data[$key] = $value;
            }
        }

        Helper::insertOrUpdate($this->db, 'gridconfigs', $data);

        $lastInsertId = $this->db->lastInsertId();
        if (!$this->model->getId() && $lastInsertId) {
            $this->model->setId((int) $lastInsertId);
        }

        return $this->model->getId();
    }

    /**
     * Deletes object from database
     */
    public function delete()
    {
        $this->db->delete('gridconfigs', ['id' => $this->model->getId()]);
    }
}
