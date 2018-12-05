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

namespace Pimcore\Model\DataObject\Classificationstore\KeyConfig;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\DataObject\Classificationstore\KeyConfig\Listing\Dao getDao()
 * @method Model\DataObject\Classificationstore\KeyConfig[] load()
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * Contains the results of the list. They are all an instance of Classificationstore_KeyConfig
     *
     * @var array
     */
    public $list = [];

    /** @var bool */
    public $includeDisabled;

    /**
     * @return array
     */
    public function getList()
    {
        return $this->list;
    }

    /**
     * @param array
     *
     * @return $this
     */
    public function setList($theList)
    {
        $this->list = $theList;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIncludeDisabled()
    {
        return $this->includeDisabled;
    }

    /**
     * @param bool $includeDisabled
     */
    public function setIncludeDisabled($includeDisabled)
    {
        $this->includeDisabled = $includeDisabled;
    }
}
