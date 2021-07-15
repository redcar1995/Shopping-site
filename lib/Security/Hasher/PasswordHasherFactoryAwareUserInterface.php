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

namespace Pimcore\Security\Hasher;

/**
 * @internal
 */
interface PasswordHasherFactoryAwareUserInterface
{
    /**
     * Gets the name of the encoder factory used to hash the password.
     *
     * If the method returns null, the standard way to retrieve the hasher
     * will be used instead.
     *
     * @return string
     */
    public function getHasherFactoryName();
}
