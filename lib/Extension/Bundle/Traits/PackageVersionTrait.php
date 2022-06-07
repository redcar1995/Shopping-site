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

namespace Pimcore\Extension\Bundle\Traits;

use Composer\InstalledVersions;
use PackageVersions\Versions;
use Pimcore\Composer\PackageInfo;

/**
 * Exposes a simple getVersion() and getComposerPackageName() implementation by looking up the installed versions 
 * via ocramius/package-versions which is generated on composer install.
 */
trait PackageVersionTrait
{

    /**
     * Returns the composer package name used to resolve the version
     *
     * @return string
     */
    public function getComposerPackageName(): string
    {
        foreach (InstalledVersions::getAllRawData() as $installed) {
            foreach ($installed['versions'] as $packageName => $packageInfo) {
                if (!isset($packageInfo['install_path'])) {
                    // It's a replaced or provided (virtual) package
                    continue;
                }

                if (str_starts_with(__DIR__, realpath($packageInfo['install_path']))) {
                    return $packageName;
                }
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion()
    {
        $version = Versions::getVersion($this->getComposerPackageName());

        // normalizes v2.3.0@9e016f4898c464f5c895c17993416c551f1697d3 to 2.3.0
        $version = preg_replace('/^v/', '', $version);
        $version = preg_replace('/@(.+)$/', '', $version);

        return $version;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $packageInfo = new PackageInfo();

        foreach ($packageInfo->getInstalledPackages('pimcore-bundle') as $bundle) {
            if ($bundle['name'] === $this->getComposerPackageName()) {
                return $bundle['description'] ?? '';
            }
        }

        return '';
    }
}
