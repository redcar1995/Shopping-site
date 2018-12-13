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
 * @package    Schedule
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\ImportConfig;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\ImportConfig\Listing\Dao getDao()
 * @method Model\ImportConfig[] load()
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * Contains the results of the list. They are all an instance of ImportConfig
     *
     * @var array
     */
    public $importconfigs = [];

    /**
     * @return array
     */
    public function getImportconfigs(): array
    {
        return $this->importconfigs;
    }

    /**
     * @param array $importconfigs
     */
    public function setImportconfigs(array $importconfigs)
    {
        $this->importconfigs = $importconfigs;
    }
}
