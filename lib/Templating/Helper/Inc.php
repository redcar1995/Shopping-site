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

namespace Pimcore\Templating\Helper;

@trigger_error(
    'Pimcore\Templating\Helper\Inc is deprecated since version 6.8.0 and will be removed in Pimcore 10. ' .
    ' Use ' . \Pimcore\Twig\Extension\Templating\Inc::class . ' instead.',
    E_USER_DEPRECATED
);

class_exists(\Pimcore\Twig\Extension\Templating\Inc::class);

if (false) {
    /**
     * @deprecated since Pimcore 6.8, use Pimcore\Twig\Extension\Templating\Inc
     */
    class Inc extends \Pimcore\Twig\Extension\Templating\Inc
    {
    }
}
