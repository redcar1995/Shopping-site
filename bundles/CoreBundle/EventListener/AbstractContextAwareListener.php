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

namespace Pimcore\Bundle\CoreBundle\EventListener;

use Pimcore\Bundle\CoreBundle\EventListener\Traits\PimcoreContextAwareTrait;
use Pimcore\Http\Context\PimcoreContextResolverAwareInterface;

/**
 * @deprecated Just use the PimcoreContextAwareTrait if you need to check against pimcore context
 */
abstract class AbstractContextAwareListener implements PimcoreContextResolverAwareInterface
{
    use PimcoreContextAwareTrait;
}
