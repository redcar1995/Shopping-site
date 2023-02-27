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

namespace Pimcore\Web2Print;

use Pimcore\Config\LocationAwareConfigRepository;
use Pimcore\Model\Exception\ConfigWriteException;

/**
 * @internal
 */
final class Config
{
    /**
     * @deprecated Will be removed in Pimcore 11
     */
    private const WRITE_TARGET = 'PIMCORE_WRITE_TARGET_WEB_TO_PRINT';

    private const CONFIG_ID = 'web_to_print';

    /**
     * @var LocationAwareConfigRepository|null
     */
    private static ?LocationAwareConfigRepository $locationAwareConfigRepository = null;

    /**
     * @deprecated Will be removed in Pimcore 11
     */
    private const LEGACY_FILE = 'web2print.php';

    /**
     * @return LocationAwareConfigRepository
     */
    private static function getRepository()
    {
        if (!self::$locationAwareConfigRepository) {
            $config = [];
            $containerConfig = \Pimcore::getContainer()->getParameter('pimcore.config');
            if ($containerConfig['documents']['web_to_print']['generalTool']) {
                $config = [
                    self::CONFIG_ID => $containerConfig['documents']['web_to_print'],
                ];
            }

            // @deprecated legacy will be removed in Pimcore 11
            $loadLegacyConfigCallback = function ($legacyRepo, &$dataSource) {
                $file = \Pimcore\Config::locateConfigFile(self::LEGACY_FILE);
                if (is_file($file)) {
                    $content = include($file);
                    if (is_array($content)) {
                        $dataSource = LocationAwareConfigRepository::LOCATION_LEGACY;

                        return $content;
                    }
                }

                return null;
            };

            $storageDirectory = LocationAwareConfigRepository::getStorageDirectoryFromSymfonyConfig($containerConfig, self::CONFIG_ID, 'PIMCORE_CONFIG_STORAGE_DIR_WEB_TO_PRINT');
            $writeTarget = LocationAwareConfigRepository::getWriteTargetFromSymfonyConfig($containerConfig, self::CONFIG_ID, 'PIMCORE_WRITE_TARGET_WEB_TO_PRINT');

            self::$locationAwareConfigRepository = new LocationAwareConfigRepository(
                $config,
                'pimcore_web_to_print',
                $storageDirectory,
                self::WRITE_TARGET,
                null,
                self::LEGACY_FILE,
                $loadLegacyConfigCallback
            );

            self::$locationAwareConfigRepository->setWriteTarget($writeTarget);
            self::$locationAwareConfigRepository->setOptions($containerConfig['storage'][self::CONFIG_ID]['options']);
        }

        return self::$locationAwareConfigRepository;
    }

    /**
     * @return bool
     *
     * @throws \Exception
     */
    public static function isWriteable(): bool
    {
        return self::getRepository()->isWriteable();
    }

    /**
     * @return \Pimcore\Config\Config
     */
    public static function get(): \Pimcore\Config\Config
    {
        $repository = self::getRepository();

        list($config) = $repository->loadConfigByKey(self::CONFIG_ID);

        return new \Pimcore\Config\Config($config ?? []);
    }

    /**
     * @param array $data
     *
     * @throws \Exception
     */
    public static function save(array $data)
    {
        $repository = self::getRepository();

        unset($data['pdf_creation_php_memory_limit']);
        unset($data['default_controller_print_page']);
        unset($data['default_controller_print_container']);

        if (!$repository->isWriteable()) {
            throw new ConfigWriteException();
        }

        $repository->saveConfig(self::CONFIG_ID, $data, function ($key, $data) {
            return [
                'pimcore' => [
                    'documents' => [
                        'web_to_print' => $data,
                    ],
                ],
            ];
        });
    }
}
