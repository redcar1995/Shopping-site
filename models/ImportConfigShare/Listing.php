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

namespace Pimcore\Model\ImportConfigShare;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\ImportConfigShare\Listing\Dao getDao()
 * @method Model\ImportConfigShare[] load()
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * Contains the results of the list. They are all an instance of ImportConfigShare
     *
     * @var array
     */
    public $importConfigShares = [];

    /**
     * @return array
     */
    public function getImportConfigShares(): array
    {
        return $this->importConfigShares;
    }

    /**
     * @param array $importConfigShares
     */
    public function setImportConfigShares(array $importConfigShares)
    {
        $this->importConfigShares = $importConfigShares;
    }
}
