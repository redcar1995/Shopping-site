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
 * @package    Document
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Document\Tag;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\Document\Tag\Dao getDao()
 */
class Numeric extends Model\Document\Tag
{
    /**
     * Contains the current number, or an empty string if not set
     *
     * @var string
     */
    public $number = '';

    /**
     * @see TagInterface::getType
     *
     * @return string
     */
    public function getType()
    {
        return 'numeric';
    }

    /**
     * @see TagInterface::getData
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->number;
    }

    /**
     * @see TagInterface::frontend
     *
     * @return string
     */
    public function frontend()
    {
        return $this->number;
    }

    /**
     * @see TagInterface::setDataFromResource
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function setDataFromResource($data)
    {
        $this->number = $data;

        return $this;
    }

    /**
     * @see TagInterface::setDataFromEditmode
     *
     * @param mixed $data
     *
     * @return $this
     */
    public function setDataFromEditmode($data)
    {
        $this->number = $data;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        if (is_numeric($this->number)) {
            return false;
        }

        return empty($this->number);
    }

    /**
     * @param Model\Webservice\Data\Document\Element $wsElement
     * @param $document
     * @param mixed $params
     * @param null $idMapper
     *
     * @throws \Exception
     */
    public function getFromWebserviceImport($wsElement, $document = null, $params = [], $idMapper = null)
    {
        $data = $wsElement->value;
        if (empty($data->number) or is_numeric($data->number)) {
            $this->number = $data->number;
        } else {
            throw new \Exception('cannot get values from web service import - invalid data');
        }
    }
}
