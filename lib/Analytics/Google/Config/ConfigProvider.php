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

namespace Pimcore\Analytics\Google\Config;

class ConfigProvider
{
    private ?Config $config = null;

    private ?array $configObject = null;

    /**
     * @param array|null $configObject
     */
    public function __construct(array $configObject = null)
    {
        $this->configObject = $configObject;
    }

    public function getConfig(): Config
    {
        if (null === $this->config) {
            $this->config = new Config($this->getConfigObject());
        }

        return $this->config;
    }

    private function getConfigObject(): array
    {
        if (null === $this->configObject) {
            $this->configObject = $this->loadDefaultConfigObject();
        }

        return $this->configObject;
    }

    protected function loadDefaultConfigObject(): array
    {
        $reportConfig = \Pimcore\Config::getReportConfig();

        return $reportConfig['analytics'] ?? [];
    }
}
