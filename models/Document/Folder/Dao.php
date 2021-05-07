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

namespace Pimcore\Model\Document\Folder;

use Pimcore\Model;

/**
 * @property \Pimcore\Model\Document\Folder $model
 */
class Dao extends Model\Document\Dao
{
    /**
     * Deletes the folder
     */
    public function delete()
    {
        parent::delete();
    }
}
