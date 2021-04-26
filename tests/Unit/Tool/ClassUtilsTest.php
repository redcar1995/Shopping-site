<?php

declare(strict_types=1);

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

namespace Pimcore\Tests\Unit\Tool;

use Pimcore\Tests\Test\TestCase;
use Pimcore\Tool\ClassUtils;

class ClassUtilsTest extends TestCase
{
    public function testFindClassName()
    {
        $file = new \SplFileInfo(__FILE__);
        $className = ClassUtils::findClassName($file);

        $this->assertEquals($className, self::class);
    }
}
