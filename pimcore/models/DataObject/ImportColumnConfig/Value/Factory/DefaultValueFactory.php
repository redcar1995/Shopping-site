<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\ImportColumnConfig\Value\Factory;

use Pimcore\Model\DataObject\ImportColumnConfig\Operator\Factory\ValueFactoryInterface;
use Pimcore\Model\DataObject\ImportColumnConfig\ValueInterface;

class DefaultValueFactory implements ValueFactoryInterface
{
    /**
     * @var string
     */
    private $className;

    public function __construct(string $className)
    {
        $this->className = $className;
    }

    public function build(\stdClass $configElement, $context = null): ValueInterface
    {
        return new $this->className($configElement, $context);
    }
}
