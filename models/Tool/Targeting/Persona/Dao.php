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
 * @package    Tool
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Tool\Targeting\Persona;

use Pimcore\Model;
use Pimcore\Tool\Serialize;

/**
 * @deprecated Use TargetGroup\Dao instead. Will be removed in Pimcore 6.
 *
 * @property \Pimcore\Model\Tool\Targeting\Persona $model
 */
class Dao extends Model\Dao\AbstractDao
{
    /**
     * @param null $id
     *
     * @throws \Exception
     */
    public function getById($id = null)
    {
        if ($id != null) {
            $this->model->setId($id);
        }

        $data = $this->db->fetchRow('SELECT * FROM targeting_target_groups WHERE id = ?', $this->model->getId());

        if ($data['id']) {
            $data['actions'] = (isset($data['actions']) ? Serialize::unserialize($data['actions']) : []);

            $this->assignVariablesToModel($data);
        } else {
            throw new \Exception('persona with id ' . $this->model->getId() . " doesn't exist");
        }
    }

    /**
     * Save object to database
     *
     * @return bool
     *
     * @todo: update and create don't return anything
     */
    public function save()
    {
        if ($this->model->getId()) {
            return $this->model->update();
        }

        return $this->create();
    }

    /**
     * Deletes object from database
     */
    public function delete()
    {
        $this->db->delete('targeting_target_groups', ['id' => $this->model->getId()]);
    }

    /**
     * @throws \Exception
     */
    public function update()
    {
        try {
            $type = $this->model->getObjectVars();
            $data = [];

            foreach ($type as $key => $value) {
                if (in_array($key, $this->getValidTableColumns('targeting_target_groups'))) {
                    if (is_array($value) || is_object($value)) {
                        $value = Serialize::serialize($value);
                    }
                    if (is_bool($value)) {
                        $value = (int) $value;
                    }
                    $data[$key] = $value;
                }
            }

            $this->db->update('targeting_target_groups', $data, ['id' => $this->model->getId()]);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Create a new record for the object in database
     *
     * @return bool
     */
    public function create()
    {
        $this->db->insert('targeting_target_groups', []);

        $this->model->setId($this->db->lastInsertId());

        return $this->save();
    }
}
