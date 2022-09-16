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

namespace Pimcore\Model\DataObject\ClassDefinition\Helper;

use Pimcore\Logger;

/**
 * @internal
 */
abstract class ClassResolver
{
    private static $cache;

    /**
     * @param string|null $class
     * @param callable|null $validationCallback
     *
     * @return mixed|null
     */
    protected static function resolve($class, callable $validationCallback = null)
    {
        if (!$class) {
            return null;
        }

        return self::$cache[$class] ??= self::returnValidServiceOrNull(
            str_starts_with($class, '@') ? \Pimcore::getContainer()->get(substr($class, 1)) : new $class,
            $validationCallback
        );
    }

    private static function returnValidServiceOrNull($service, callable $validationCallback = null)
    {
        if ($validationCallback && !$validationCallback($service)) {
            return null;
        }

        return $service;
    }
}
