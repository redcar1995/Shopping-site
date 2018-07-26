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
 * @package    Asset
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Asset;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\Asset\Dao getDao()
 */
class Folder extends Model\Asset
{
    use Model\Element\ChildsCompatibilityTrait;

    /**
     * @var string
     */
    public $type = 'folder';

    /**
     * set the children of the document
     *
     * @param $children
     *
     * @return Folder
     */
    public function setChildren($children)
    {
        $this->childs = $children;
        if (is_array($children) and count($children) > 0) {
            $this->hasChilds = true;
        } else {
            $this->hasChilds = false;
        }

        return $this;
    }
}
