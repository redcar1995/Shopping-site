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
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\ClassDefinition\Data;

use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;

interface DataContainerAwareInterface
{
    /**
     * @param mixed $containerDefinition
     * @param array $params
     *
     * @return mixed
     */
    public function preSave($containerDefinition, $params = []);

    /**
     * @param mixed $containerDefinition
     * @param array $params
     *
     * @return mixed
     */
    public function postSave($containerDefinition, $params = []);

}
