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

namespace Pimcore\Model\DataObject\QuantityValue\Unit;

use Pimcore\Db\Helper;
use Pimcore\Model;

/**
 * @internal
 *
 * @property \Pimcore\Model\DataObject\QuantityValue\Unit $model
 */
class Dao extends Model\Dao\AbstractDao
{
    const TABLE_NAME = 'quantityvalue_units';

    /**
     * Contains all valid columns in the database table
     *
     * @var array
     */
    protected $validColumns = [];

    /**
     * Get the valid columns from the database
     *
     */
    public function init()
    {
        $this->validColumns = $this->getValidTableColumns(self::TABLE_NAME);
    }

    /**
     * @param string $abbreviation
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getByAbbreviation($abbreviation)
    {
        $classRaw = $this->db->fetchAssociative('SELECT * FROM ' . self::TABLE_NAME . ' WHERE abbreviation=' . $this->db->quote($abbreviation));
        if (empty($classRaw)) {
            throw new Model\Exception\NotFoundException('Unit ' . $abbreviation . ' not found.');
        }
        $this->assignVariablesToModel($classRaw);
    }

    /**
     * @param string $reference
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getByReference($reference)
    {
        $classRaw = $this->db->fetchAssociative('SELECT * FROM ' . self::TABLE_NAME . ' WHERE reference=' . $this->db->quote($reference));
        if (empty($classRaw)) {
            throw new Model\Exception\NotFoundException('Unit ' . $reference . ' not found.');
        }
        $this->assignVariablesToModel($classRaw);
    }

    /**
     * @param int $id
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getById($id)
    {
        $classRaw = $this->db->fetchAssociative('SELECT * FROM ' . self::TABLE_NAME . ' WHERE id=' . $this->db->quote($id));
        if (empty($classRaw)) {
            throw new Model\Exception\NotFoundException('Unit ' . $id . ' not found.');
        }
        $this->assignVariablesToModel($classRaw);
    }

    /**
     * Create a new record for the object in database
     */
    public function create()
    {
        $this->update();
    }

    /**
     * Save object to database
     */
    public function save()
    {
        $this->update();
    }

    public function update()
    {
        if (!$this->model->getId()) {
            // mimic autoincrement
            $id = $this->db->fetchOne('SELECT CONVERT(SUBSTRING_INDEX(id,\'-\',-1),UNSIGNED INTEGER) AS num FROM quantityvalue_units ORDER BY num DESC LIMIT 1');
            $id = $id > 0 ? ($id + 1) : 1;
            $this->model->setId((string) $id);
        }

        $class = $this->model->getObjectVars();
        $data = [];

        foreach ($class as $key => $value) {
            if (in_array($key, $this->validColumns)) {
                if (is_array($value) || is_object($value)) {
                    $value = serialize($value);
                } elseif (is_bool($value)) {
                    $value = (int)$value;
                }
                $data[$key] = $value;
            }
        }

        Helper::insertOrUpdate($this->db, self::TABLE_NAME, $data);
    }

    /**
     * Deletes object from database
     */
    public function delete()
    {
        $this->db->delete(self::TABLE_NAME, ['id' => $this->model->getId()]);
    }
}
