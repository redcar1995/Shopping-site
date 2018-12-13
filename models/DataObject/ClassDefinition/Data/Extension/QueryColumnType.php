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
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\ClassDefinition\Data\Extension;

trait QueryColumnType
{
    /**
     * @var string | array
     */
    public $queryColumnType;

    /**
     * @return string | array
     */
    public function getQueryColumnType()
    {
        return $this->queryColumnType;
    }

    /**
     * @deprecated
     * @param string | array $queryColumnType
     * @return $this
     */
    public function setQueryColumnType($queryColumnType)
    {
        $this->queryColumnType = $queryColumnType;

        return $this;
    }
}
