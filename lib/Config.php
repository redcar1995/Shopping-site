<?php
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

namespace Pimcore;

use Pimcore\Config\EnvironmentConfig;
use Pimcore\Config\EnvironmentConfigInterface;
use Pimcore\FeatureToggles\Features\DebugMode;
use Pimcore\Model\WebsiteSetting;
use Symfony\Cmf\Bundle\RoutingBundle\Routing\DynamicRouter;

class Config
{
    /**
     * @deprecated Default environment is now determined by EnvironmentConfig
     */
    const DEFAULT_ENVIRONMENT = 'prod';

    /**
     * @var array
     */
    protected static $configFileCache = [];

    /**
     * @var string
     */
    protected static $environment = null;

    /**
     * @var EnvironmentConfigInterface
     */
    private static $environmentConfig;

    /**
     * @param $name - name of configuration file. slash is allowed for subdirectories.
     *
     * @return mixed
     */
    public static function locateConfigFile($name)
    {
        if (!isset(self::$configFileCache[$name])) {
            $pathsToCheck = [
                PIMCORE_CUSTOM_CONFIGURATION_DIRECTORY,
                PIMCORE_CONFIGURATION_DIRECTORY,
            ];
            $file = null;

            // check for environment configuration
            $env = self::getEnvironment();
            if ($env) {
                $fileExt = File::getFileExtension($name);
                $pureName = str_replace('.' . $fileExt, '', $name);
                foreach ($pathsToCheck as $path) {
                    $tmpFile = $path . '/' . $pureName . '_' . $env . '.' . $fileExt;
                    if (file_exists($tmpFile)) {
                        $file = $tmpFile;
                        break;
                    }
                }
            }

            //check for config file without environment configuration
            if (!$file) {
                foreach ($pathsToCheck as $path) {
                    $tmpFile = $path . '/' . $name;
                    if (file_exists($tmpFile)) {
                        $file = $tmpFile;
                        break;
                    }
                }
            }

            //get default path in pimcore configuration directory
            if (!$file) {
                $file = PIMCORE_CONFIGURATION_DIRECTORY . '/' . $name;
            }

            self::$configFileCache[$name] = $file;
        }

        return self::$configFileCache[$name];
    }

    /**
     * @param bool $forceReload
     *
     * @return mixed|null|\Pimcore\Config\Config
     *
     * @throws \Exception
     */
    public static function getSystemConfig($forceReload = false)
    {
        $config = null;

        if (\Pimcore\Cache\Runtime::isRegistered('pimcore_config_system') && !$forceReload) {
            $config = \Pimcore\Cache\Runtime::get('pimcore_config_system');
        } else {
            $file = self::locateConfigFile('system.php');

            try {
                // this is just for compatibility reasons, pimcore itself doesn't use this constant anymore
                if (!defined('PIMCORE_CONFIGURATION_SYSTEM')) {
                    define('PIMCORE_CONFIGURATION_SYSTEM', $file);
                }

                $config = static::getConfigInstance($file);

                self::setSystemConfig($config);
            } catch (\Exception $e) {
                Logger::emergency('Cannot find system configuration, should be located at: ' . $file);

                if (is_file($file)) {
                    $m = 'Your system.php located at ' . $file . ' is invalid, please check and correct it manually!';
                    Tool::exitWithError($m);
                }
            }
        }

        return $config;
    }

    /**
     * @static
     *
     * @param \Pimcore\Config\Config $config
     */
    public static function setSystemConfig(\Pimcore\Config\Config $config)
    {
        \Pimcore\Cache\Runtime::set('pimcore_config_system', $config);
    }

    /**
     * @param null $languange
     *
     * @return string
     */
    public static function getWebsiteConfigRuntimeCacheKey($languange = null)
    {
        $cacheKey = 'pimcore_config_website';
        if ($languange) {
            $cacheKey .= '_' . $languange;
        }

        return $cacheKey;
    }

    /**
     * @static
     *
     * @return mixed|\Pimcore\Config\Config
     */
    public static function getWebsiteConfig($language = null)
    {
        if (\Pimcore\Cache\Runtime::isRegistered(self::getWebsiteConfigRuntimeCacheKey($language))) {
            $config = \Pimcore\Cache\Runtime::get(self::getWebsiteConfigRuntimeCacheKey($language));
        } else {
            $cacheKey = 'website_config';
            if ($language) {
                $cacheKey .= '_' . $language;
            }

            $siteId = null;
            if (Model\Site::isSiteRequest()) {
                $siteId = Model\Site::getCurrentSite()->getId();
            } elseif (Tool::isFrontentRequestByAdmin()) {
                // this is necessary to set the correct settings in editmode/preview (using the main domain)
                // we cannot use the document resolver service here, because we need the document on the master request
                $originDocument = \Pimcore::getContainer()->get('request_stack')->getMasterRequest()->get(DynamicRouter::CONTENT_KEY);
                if ($originDocument) {
                    $site = Tool\Frontend::getSiteForDocument($originDocument);
                    if ($site) {
                        $siteId = $site->getId();
                    }
                }
            }

            if ($siteId) {
                $cacheKey = $cacheKey . '_site_' . $siteId;
            }

            if (!$config = Cache::load($cacheKey)) {
                $settingsArray = [];
                $cacheTags = ['website_config', 'system', 'config', 'output'];

                $list = new Model\WebsiteSetting\Listing();
                $list = $list->load();

                /** @var $item WebsiteSetting */
                foreach ($list as $item) {
                    $key = $item->getName();
                    $itemSiteId = $item->getSiteId();

                    if ($itemSiteId != 0 && $itemSiteId != $siteId) {
                        continue;
                    }

                    $itemLanguage = $item->getLanguage();

                    if ($itemLanguage && $language != $itemLanguage) {
                        continue;
                    }

                    if (isset($settingsArray[$key])) {
                        if (!$itemLanguage) {
                            continue;
                        }
                    }

                    if ($settingsArray[$key] && !$itemLanguage) {
                        continue;
                    }

                    $s = null;

                    switch ($item->getType()) {
                        case 'document':
                        case 'asset':
                        case 'object':
                            $s = Model\Element\Service::getElementById($item->getType(), $item->getData());
                            break;
                        case 'bool':
                            $s = (bool) $item->getData();
                            break;
                        case 'text':
                            $s = (string) $item->getData();
                            break;

                    }

                    if ($s instanceof Model\Element\ElementInterface) {
                        $cacheTags = $s->getCacheTags($cacheTags);
                    }

                    if (isset($s)) {
                        $settingsArray[$key] = $s;
                    }
                }

                //TODO resolve for all langs, current lang first, then no lang
                $config = new \Pimcore\Config\Config($settingsArray, true);

                Cache::save($config, $cacheKey, $cacheTags, null, 998);
            }

            self::setWebsiteConfig($config, $language);
        }

        return $config;
    }

    /**
     * @param Config\Config $config
     * @param null $language
     */
    public static function setWebsiteConfig(\Pimcore\Config\Config $config, $language = null)
    {
        \Pimcore\Cache\Runtime::set(self::getWebsiteConfigRuntimeCacheKey($language), $config);
    }

    /**
     * Returns whole website config or only a given setting for the current site
     *
     * @param null|mixed $key       Config key to directly load. If null, the whole config will be returned
     * @param null|mixed $default   Default value to use if the key is not set
     * @param null|string $language   Language
     *
     * @return Config\Config|mixed
     */
    public static function getWebsiteConfigValue($key = null, $default = null, $language = null)
    {
        $config = self::getWebsiteConfig($language);
        if (null !== $key) {
            return $config->get($key, $default);
        }

        return $config;
    }

    /**
     * @static
     *
     * @return \Pimcore\Config\Config
     */
    public static function getReportConfig()
    {
        if (\Pimcore\Cache\Runtime::isRegistered('pimcore_config_report')) {
            $config = \Pimcore\Cache\Runtime::get('pimcore_config_report');
        } else {
            try {
                $file = self::locateConfigFile('reports.php');
                $config = static::getConfigInstance($file);
            } catch (\Exception $e) {
                $config = new \Pimcore\Config\Config([]);
            }

            self::setReportConfig($config);
        }

        return $config;
    }

    /**
     * @static
     *
     * @param \Pimcore\Config\Config $config
     */
    public static function setReportConfig(\Pimcore\Config\Config $config)
    {
        \Pimcore\Cache\Runtime::set('pimcore_config_report', $config);
    }

    /**
     * @static
     *
     * @return \Pimcore\Config\Config
     */
    public static function getRobotsConfig()
    {
        if (\Pimcore\Cache\Runtime::isRegistered('pimcore_config_robots')) {
            $config = \Pimcore\Cache\Runtime::get('pimcore_config_robots');
        } else {
            try {
                $file = self::locateConfigFile('robots.php');
                $config = static::getConfigInstance($file);
            } catch (\Exception $e) {
                $config = new \Pimcore\Config\Config([]);
            }

            self::setRobotsConfig($config);
        }

        return $config;
    }

    /**
     * @static
     *
     * @param \Pimcore\Config\Config $config
     */
    public static function setRobotsConfig(\Pimcore\Config\Config $config)
    {
        \Pimcore\Cache\Runtime::set('pimcore_config_robots', $config);
    }

    /**
     * @static
     *
     * @return \Pimcore\Config\Config
     */
    public static function getWeb2PrintConfig()
    {
        if (\Pimcore\Cache\Runtime::isRegistered('pimcore_config_web2print')) {
            $config = \Pimcore\Cache\Runtime::get('pimcore_config_web2print');
        } else {
            try {
                $file = self::locateConfigFile('web2print.php');
                $config = static::getConfigInstance($file);
            } catch (\Exception $e) {
                $config = new \Pimcore\Config\Config([]);
            }

            self::setWeb2PrintConfig($config);
        }

        return $config;
    }

    /**
     * @static
     *
     * @param \Pimcore\Config\Config $config
     */
    public static function setWeb2PrintConfig(\Pimcore\Config\Config $config)
    {
        \Pimcore\Cache\Runtime::set('pimcore_config_web2print', $config);
    }

    /**
     * @static
     *
     * @param \Pimcore\Config\Config $config
     */
    public static function setModelClassMappingConfig($config)
    {
        \Pimcore\Cache\Runtime::set('pimcore_config_model_classmapping', $config);
    }

    /**
     * @static
     *
     * @return mixed|\Pimcore\Config\Config
     */
    public static function getPerspectivesConfig()
    {
        if (\Pimcore\Cache\Runtime::isRegistered('pimcore_config_perspectives')) {
            $config = \Pimcore\Cache\Runtime::get('pimcore_config_perspectives');
        } else {
            try {
                $file = self::locateConfigFile('perspectives.php');
                $config = static::getConfigInstance($file);
                self::setPerspectivesConfig($config);
            } catch (\Exception $e) {
                Logger::info('Cannot find perspectives configuration, should be located at: ' . $file);
                if (is_file($file)) {
                    $m = 'Your perspectives.php located at ' . $file . ' is invalid, please check and correct it manually!';
                    Tool::exitWithError($m);
                }
                $config = new \Pimcore\Config\Config(self::getStandardPerspective());
                self::setPerspectivesConfig($config);
            }
        }

        return $config;
    }

    /** Returns the standard perspective settings
     * @return array
     */
    public static function getStandardPerspective()
    {
        $elementTree = [
            [
                'type' => 'documents',
                'position' => 'left',
                'expanded' => false,
                'hidden' => false,
                'sort' => -3,
                'treeContextMenu' => [
                    'document' => [
                        'items' => [
                            'addPrintPage' => self::getWeb2PrintConfig()->enableInDefaultView ? true : false // hide add print documents by default
                        ]
                    ]
                ]
            ],
            [
                'type' => 'assets',
                'position' => 'left',
                'expanded' => false,
                'hidden' => false,
                'sort' => -2
            ],
            [
                'type' => 'objects',
                'position' => 'left',
                'expanded' => false,
                'hidden' => false,
                'sort' => -1
            ],
        ];

        $cvConfigs = Tool::getCustomViewConfig();
        if ($cvConfigs) {
            foreach ($cvConfigs as $cvConfig) {
                $cvConfig['type'] = 'customview';
                $elementTree[] = $cvConfig;
            }
        }

        return [
            'default' => [
                'iconCls' => 'pimcore_icon_perspective',
                'elementTree' => $elementTree,
                'dashboards' => [
                    'predefined' => [
                        'welcome' => [
                            'positions' => [
                                [
                                    [
                                        'id' => 1,
                                        'type' => 'pimcore.layout.portlets.modificationStatistic',
                                        'config' => null
                                    ],
                                    [
                                        'id' => 2,
                                        'type' => 'pimcore.layout.portlets.modifiedAssets',
                                        'config' => null
                                    ]
                                ],
                                [
                                    [
                                        'id' => 3,
                                        'type' => 'pimcore.layout.portlets.modifiedObjects',
                                        'config' => null
                                    ],
                                    [
                                        'id' => 4,
                                        'type' => 'pimcore.layout.portlets.modifiedDocuments',
                                        'config' => null
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /** Gets the active perspective for the current user
     * @param Model\User $currentUser
     *
     * @return array
     */
    public static function getRuntimePerspective(Model\User $currentUser = null)
    {
        if (null === $currentUser) {
            $currentUser = Tool\Admin::getCurrentUser();
        }

        $currentConfigName = $currentUser->getActivePerspective() ? $currentUser->getActivePerspective() : $currentUser->getFirstAllowedPerspective();

        $config = self::getPerspectivesConfig()->toArray();
        $result = [];

        if ($config[$currentConfigName]) {
            $result = $config[$currentConfigName];
        } else {
            $availablePerspectives = self::getAvailablePerspectives($currentUser);
            if ($availablePerspectives) {
                $currentPerspective = reset($availablePerspectives);
                $currentConfigName = $currentPerspective['name'];
                if ($currentConfigName && $config[$currentConfigName]) {
                    $result = $config[$currentConfigName];
                }
            }
        }

        if ($result && $currentConfigName != $currentUser->getActivePerspective()) {
            $currentUser->setActivePerspective($currentConfigName);
            $currentUser->save();
        }

        $result['elementTree'] = self::getRuntimeElementTreeConfig($currentConfigName);

        return $result;
    }

    /** Returns the element tree config for the given config name
     * @param $name
     *
     * @return array
     */
    protected static function getRuntimeElementTreeConfig($name)
    {
        $masterConfig = self::getPerspectivesConfig()->toArray();

        $config = $masterConfig[$name];
        if (!$config) {
            $config = [];
        }

        $tmpResult = $config['elementTree'];
        if (is_null($tmpResult)) {
            $tmpResult = [];
        }
        $result = [];

        $cfConfigMapping = [];

        $cvConfigs = Tool::getCustomViewConfig();

        if ($cvConfigs) {
            foreach ($cvConfigs as $node) {
                $tmpData = $node;
                if (!isset($tmpData['id'])) {
                    Logger::error('custom view ID is missing ' . var_export($tmpData, true));
                    continue;
                }

                if ($tmpData['hidden']) {
                    continue;
                }

                // backwards compatibility
                $treeType = $tmpData['treetype'] ? $tmpData['treetype'] : 'object';
                $rootNode = Model\Element\Service::getElementByPath($treeType, $tmpData['rootfolder']);

                if ($rootNode) {
                    $tmpData['type'] = 'customview';
                    $tmpData['rootId'] = $rootNode->getId();
                    $tmpData['allowedClasses'] = $tmpData['classes'] ? explode(',', $tmpData['classes']) : null;
                    $tmpData['showroot'] = (bool)$tmpData['showroot'];
                    $customViewId = $tmpData['id'];
                    $cfConfigMapping[$customViewId] = $tmpData;
                }
            }
        }

        foreach ($tmpResult as $resultItem) {
            if ($resultItem['hidden']) {
                continue;
            }

            if ($resultItem['type'] == 'customview') {
                $customViewId = $resultItem['id'];
                if (!$customViewId) {
                    Logger::error('custom view id missing ' . var_export($resultItem, true));
                    continue;
                }
                $customViewCfg = $cfConfigMapping[$customViewId];
                if (!$customViewCfg) {
                    Logger::error('no custom view config for id  ' . $customViewId);
                    continue;
                }

                foreach ($resultItem as $specificConfigKey => $specificConfigValue) {
                    $customViewCfg[$specificConfigKey] = $specificConfigValue;
                }
                $result[] = $customViewCfg;
            } else {
                $result[] = $resultItem;
            }
        }

        usort($result, function ($treeA, $treeB) {
            $a = $treeA['sort'] ? $treeA['sort'] : 0;
            $b = $treeB['sort'] ? $treeB['sort'] : 0;

            if ($a > $b) {
                return 1;
            } elseif ($a < $b) {
                return -1;
            } else {
                return 0;
            }
        });

        return $result;
    }

    /**
     * @static
     *
     * @param \Pimcore\Config\Config $config
     */
    public static function setPerspectivesConfig(\Pimcore\Config\Config $config)
    {
        \Pimcore\Cache\Runtime::set('pimcore_config_perspectives', $config);
    }

    /** Returns a list of available perspectives for the given user
     * @param Model\User $user
     *
     * @return array
     */
    public static function getAvailablePerspectives($user)
    {
        $currentConfigName = null;
        $masterConfig = self::getPerspectivesConfig()->toArray();

        if ($user instanceof  Model\User) {
            if ($user->isAdmin()) {
                $config = self::getPerspectivesConfig()->toArray();
            } else {
                $config = [];
                $roleIds = $user->getRoles();
                $userIds = [$user->getId()];
                $userIds = array_merge($userIds, $roleIds);

                foreach ($userIds as $userId) {
                    if (in_array($userId, $roleIds)) {
                        $userOrRoleToCheck = Model\User\Role::getById($userId);
                    } else {
                        $userOrRoleToCheck = Model\User::getById($userId);
                    }
                    $perspectives = $userOrRoleToCheck ? $userOrRoleToCheck->getPerspectives() : null;
                    if ($perspectives) {
                        foreach ($perspectives as $perspectiveName) {
                            $masterDef = $masterConfig[$perspectiveName];
                            if ($masterDef) {
                                $config[$perspectiveName] = $masterDef;
                            }
                        }
                    }
                }
                if (!$config) {
                    $config = self::getPerspectivesConfig()->toArray();
                }
            }

            if ($config) {
                $tmpConfig = [];
                $validPerspectiveNames = array_keys($config);

                // sort the stuff
                foreach ($masterConfig as $masterConfigName => $masterConfiguration) {
                    if (in_array($masterConfigName, $validPerspectiveNames)) {
                        $tmpConfig[$masterConfigName] = $masterConfiguration;
                    }
                }
                $config = $tmpConfig;
            }

            $currentConfigName = $user->getActivePerspective();
            if ($config && !in_array($currentConfigName, array_keys($config))) {
                $currentConfigName = reset(array_keys($config));
            }
        } else {
            $config = self::getPerspectivesConfig()->toArray();
        }

        $result = [];

        foreach ($config as $configName => $configItem) {
            $item = [
                'name' => $configName,
                'icon' => isset($configItem['icon']) ? $configItem['icon'] : null,
                'iconCls' => isset($configItem['iconCls']) ? $configItem['iconCls'] : null
            ];
            if ($user) {
                $item['active'] = $configName == $currentConfigName;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param $runtimeConfig
     * @param $key
     *
     * @return bool
     */
    public static function inPerspective($runtimeConfig, $key)
    {
        if (!isset($runtimeConfig['toolbar']) || !$runtimeConfig['toolbar']) {
            return true;
        }
        $parts = explode('.', $key);
        $menuItems = $runtimeConfig['toolbar'];

        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];

            if (!isset($menuItems[$part])) {
                break;
            }

            $menuItem = $menuItems[$part];

            if (is_array($menuItem)) {
                if ($menuItem['hidden']) {
                    return false;
                }

                if (!$menuItem['items']) {
                    break;
                }
                $menuItems = $menuItem['items'];
            } else {
                return $menuItem;
            }
        }

        return true;
    }

    /**
     * @param bool $reset
     * @param string|null $default
     *
     * @return string
     */
    public static function getEnvironment(bool $reset = false, string $default = null)
    {
        if (null === static::$environment || $reset) {
            $environment = false;

            // check env vars - fall back to default (prod)
            if (!$environment) {
                $environment = getenv('PIMCORE_ENVIRONMENT')
                    ?: (getenv('REDIRECT_PIMCORE_ENVIRONMENT'))
                        ?: false;
            }

            if (!$environment) {
                $environment = getenv('SYMFONY_ENV')
                    ?: (getenv('REDIRECT_SYMFONY_ENV'))
                        ?: false;
            }

            if (!$environment && php_sapi_name() === 'cli') {
                // check CLI option: [-e|--env ENV]
                foreach ($_SERVER['argv'] as $argument) {
                    if (preg_match("@\-\-env=(.*)@", $argument, $matches)) {
                        $environment = $matches[1];
                        break;
                    }
                }
            }

            if (!$environment) {
                if (null !== $default) {
                    $environment = $default;
                } else {
                    $environmentConfig = static::getEnvironmentConfig();

                    if (\Pimcore::inDebugMode(DebugMode::SYMFONY_ENVIRONMENT)) {
                        $environment = $environmentConfig->getDefaultDebugModeEnvironment();
                    } else {
                        $environment = $environmentConfig->getDefaultEnvironment();
                    }
                }
            }

            static::$environment = $environment;
        }

        return static::$environment;
    }

    /**
     * @param string $environment
     */
    public static function setEnvironment($environment)
    {
        static::$environment = $environment;
    }

    public static function getEnvironmentConfig(): EnvironmentConfigInterface
    {
        if (null === static::$environmentConfig) {
            static::$environmentConfig = new EnvironmentConfig();
        }

        return static::$environmentConfig;
    }

    public static function setEnvironmentConfig(EnvironmentConfigInterface $environmentConfig)
    {
        self::$environmentConfig = $environmentConfig;
    }

    /**
     * @return mixed
     */
    public static function getFlag($key)
    {
        $config = \Pimcore::getContainer()->getParameter('pimcore.config');
        if (isset($config['flags'])) {
            if (isset($config['flags'][$key])) {
                return $config['flags'][$key];
            }
        }

        return null;
    }

    /**
     * @param $file
     *
     * @return null|Config\Config
     *
     * @throws \Exception
     */
    protected static function getConfigInstance($file)
    {
        if (file_exists($file)) {
            $content = include($file);

            if (is_array($content)) {
                return new \Pimcore\Config\Config($content);
            }
        } else {
            throw new \Exception($file . " doesn't exist");
        }

        throw new \Exception($file . ' is invalid');
    }
}
