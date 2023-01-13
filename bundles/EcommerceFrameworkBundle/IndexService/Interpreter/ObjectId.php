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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Interpreter;

use Pimcore\Model\DataObject\AbstractObject;

class ObjectId implements InterpreterInterface
{
    /**
     * @param mixed $value
     * @param array|null $config
     * @return int|null
     */
    public function interpret($value, $config = null)
    {
        if (!empty($value) && $value instanceof AbstractObject) {
            return $value->getId();
        }

        return null;
    }
}
