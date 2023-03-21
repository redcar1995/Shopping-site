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
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Extension\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

abstract class AbstractPimcoreBundle extends Bundle implements PimcoreBundleInterface
{
    protected static ?PimcoreBundleManager $bundleManager = null;

    /**
     * {@inheritdoc}
     */
    public function getNiceName(): string
    {
        return $this->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getInstaller(): ?Installer\InstallerInterface
    {
        return null;
    }

    public static function isInstalled(): bool
    {
        static::$bundleManager ??= \Pimcore::getContainer()->get(PimcoreBundleManager::class);

        $bundle = static::$bundleManager->getActiveBundle(static::class, false);

        return static::$bundleManager->isInstalled($bundle);
    }
}
