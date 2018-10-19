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
use Pimcore\Cache;
use Pimcore\Config;
use Pimcore\FeatureToggles\Feature;
use Pimcore\FeatureToggles\FeatureManager;
use Pimcore\FeatureToggles\FeatureManagerInterface;
use Pimcore\FeatureToggles\Features\DebugMode;
use Pimcore\FeatureToggles\Features\DevMode;
use Pimcore\FeatureToggles\FeatureState;
use Pimcore\File;
use Pimcore\Logger;
use Pimcore\Model;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Pimcore
{
    /**
     * @var bool
     */
    public static $adminMode;

    /**
     * @var FeatureManagerInterface
     */
    private static $featureManager;

    /**
     * @var bool
     */
    private static $inShutdown = false;

    /**
     * @var KernelInterface
     */
    private static $kernel;

    /**
     * @var \Composer\Autoload\ClassLoader
     */
    private static $autoloader;

    /**
     * @static
     *
     * @return \Pimcore\Config\Config|null
     */
    public static function initConfiguration()
    {
        $conf = null;

        // init configuration
        try {
            $conf = Config::getSystemConfig(true);

            // set timezone
            if ($conf instanceof \Pimcore\Config\Config) {
                if ($conf->general->timezone) {
                    date_default_timezone_set($conf->general->timezone);
                }
            }

            if (!defined('PIMCORE_DEVMODE')) {
                define('PIMCORE_DEVMODE', (bool) $conf->general->devmode);
            }
        } catch (\Exception $e) {
            $m = "Couldn't load system configuration";
            Logger::err($m);

            if (!defined('PIMCORE_DEVMODE')) {
                define('PIMCORE_DEVMODE', false);
            }
        }

        $debug = self::inDebugMode();
        if (!defined('PIMCORE_DEBUG')) {
            define('PIMCORE_DEBUG', $debug);
        }

        // custom error logging when debug flag is set
        if (self::inDebugMode(DebugMode::ERROR_REPORTING)) {
            error_reporting(E_ALL & ~E_NOTICE);
        }

        return $conf;
    }

    public static function setFeatureManager(FeatureManagerInterface $featureManager)
    {
        self::$featureManager = $featureManager;
    }

    public static function getFeatureManager(): FeatureManagerInterface
    {
        if (null === static::$featureManager) {
            $featureManager = new FeatureManager(null, [
                DebugMode::getDefaultInitializer(),
                DevMode::getDefaultInitializer()
            ]);

            static::$featureManager = $featureManager;
        }

        return static::$featureManager;
    }

    public static function isFeatureEnabled(Feature $feature): bool
    {
        return static::getFeatureManager()->isEnabled($feature);
    }

    /**
     * @param DebugMode|int|null $flag
     *
     * @return bool
     */
    public static function inDebugMode($flag = null): bool
    {
        if (is_int($flag)) {
            $flag = new DebugMode($flag);
        }

        if (null !== $flag && !$flag instanceof DebugMode) {
            throw new \InvalidArgumentException(sprintf('Flag must be an integer or an instance of %s', DebugMode::class));
        }

        return static::getFeatureManager()->isEnabled($flag ?? DebugMode::ALL());
    }

    /**
     * @param DevMode|int|null $flag
     *
     * @return bool
     */
    public static function inDevMode($flag = null): bool
    {
        if (is_int($flag)) {
            $flag = new DevMode($flag);
        }

        if (null !== $flag && !$flag instanceof DevMode) {
            throw new \InvalidArgumentException(sprintf('Flag must be an integer or an instance of %s', DevMode::class));
        }

        return static::getFeatureManager()->isEnabled($flag ?? DevMode::ALL());
    }

    /**
     * Sets debug mode (overrides the PIMCORE_DEBUG constant and the debug mode from config)
     *
     * @param bool $debugMode
     */
    public static function setDebugMode(bool $debugMode = true)
    {
        self::getFeatureManager()->setState(FeatureState::fromFeature($debugMode ? DebugMode::ALL() : DebugMode::NONE()));
    }

    /**
     * switches pimcore into the admin mode - there you can access also unpublished elements, ....
     *
     * @static
     */
    public static function setAdminMode()
    {
        self::$adminMode = true;
    }

    /**
     * switches back to the non admin mode, where unpublished elements are invisible
     *
     * @static
     */
    public static function unsetAdminMode()
    {
        self::$adminMode = false;
    }

    /**
     * check if the process is currently in admin mode or not
     *
     * @static
     *
     * @return bool
     */
    public static function inAdmin()
    {
        if (self::$adminMode !== null) {
            return self::$adminMode;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function isInstalled()
    {
        try {
            \Pimcore\Db::get();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return object|\Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher
     */
    public static function getEventDispatcher()
    {
        return self::getContainer()->get('event_dispatcher');
    }

    /**
     * @return KernelInterface
     */
    public static function getKernel()
    {
        return static::$kernel;
    }

    /**
     * @return bool
     */
    public static function hasKernel()
    {
        if (static::$kernel) {
            return true;
        }

        return false;
    }

    /**
     * @param KernelInterface $kernel
     */
    public static function setKernel(KernelInterface $kernel)
    {
        static::$kernel = $kernel;
    }

    /**
     * Accessing the container this way is discouraged as dependencies should be wired through the container instead of
     * needing to access the container directly. This exists mainly for compatibility with legacy code.
     *
     * @return ContainerInterface
     */
    public static function getContainer()
    {
        return static::getKernel()->getContainer();
    }

    /**
     * @return bool
     */
    public static function hasContainer()
    {
        if (static::hasKernel()) {
            $container = static::getContainer();
            if ($container) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getAutoloader(): \Composer\Autoload\ClassLoader
    {
        return self::$autoloader;
    }

    /**
     * @param \Composer\Autoload\ClassLoader $autoloader
     */
    public static function setAutoloader(\Composer\Autoload\ClassLoader $autoloader)
    {
        self::$autoloader = $autoloader;
    }

    /** Add $keepItems to the list of items which are protected from garbage collection.
     * @param $keepItems
     *
     * @deprecated
     */
    public static function addToGloballyProtectedItems($keepItems)
    {
        if (is_string($keepItems)) {
            $keepItems = [$keepItems];
        }
        if (is_array($keepItems)) {
            $longRunningHelper = self::getContainer()->get(\Pimcore\Helper\LongRunningHelper::class);
            $longRunningHelper->addPimcoreRuntimeCacheProtectedItems($keepItems);
        } else {
            throw new \InvalidArgumentException('keepItems must be an instance of array');
        }
    }

    /** Items to be deleted.
     * @param $deleteItems
     *
     * @deprecated
     */
    public static function removeFromGloballyProtectedItems($deleteItems)
    {
        if (is_string($deleteItems)) {
            $deleteItems = [$deleteItems];
        }

        if (is_array($deleteItems)) {
            $longRunningHelper = self::getContainer()->get(\Pimcore\Helper\LongRunningHelper::class);
            $longRunningHelper->removePimcoreRuntimeCacheProtectedItems($deleteItems);
        } else {
            throw new \InvalidArgumentException('deleteItems must be an instance of array');
        }
    }

    /**
     * Forces a garbage collection.
     *
     * @static
     *
     * @param array $keepItems
     */
    public static function collectGarbage($keepItems = [])
    {
        $longRunningHelper = self::getContainer()->get(\Pimcore\Helper\LongRunningHelper::class);
        $longRunningHelper->cleanUp([
            'pimcoreRuntimeCache' => [
                'keepItems' => $keepItems
            ]
        ]);
    }

    /**
     * this method is called with register_shutdown_function() and writes all data queued into the cache
     *
     * @static
     */
    public static function shutdown()
    {
        // set inShutdown to true so that the output-buffer knows that he is allowed to send the headers
        self::$inShutdown = true;

        // write and clean up cache
        if (php_sapi_name() != 'cli') {
            Cache::shutdown();
        }

        // release all open locks from this process
        Model\Tool\Lock::releaseAll();
    }

    public static function disableMinifyJs(): bool
    {
        if (self::inDevMode(DevMode::UNMINIFIED_JS)) {
            return true;
        }

        // magic parameter for debugging ExtJS stuff
        if (array_key_exists('unminified_js', $_REQUEST) && self::inDebugMode(DebugMode::MAGIC_PARAMS)) {
            return true;
        }

        return false;
    }

    public static function initLogger()
    {
        // special request log -> if parameter pimcore_log is set
        if (array_key_exists('pimcore_log', $_REQUEST) && self::inDebugMode(DebugMode::MAGIC_PARAMS)) {
            $requestLogName = date('Y-m-d_H-i-s');
            if (!empty($_REQUEST['pimcore_log'])) {
                // slashed are not allowed, replace them with hyphens
                $requestLogName = str_replace('/', '-', $_REQUEST['pimcore_log']);
            }

            $requestLogFile = resolvePath(PIMCORE_LOG_DIRECTORY . '/request-' . $requestLogName . '.log');
            if (strpos($requestLogFile, PIMCORE_LOG_DIRECTORY) !== 0) {
                throw new \Exception('Not allowed');
            }

            if (!file_exists($requestLogFile)) {
                File::put($requestLogFile, '');
            }

            $requestDebugHandler = new \Monolog\Handler\StreamHandler($requestLogFile);

            foreach (self::getContainer()->getServiceIds() as $id) {
                if (strpos($id, 'monolog.logger.') === 0) {
                    $logger = self::getContainer()->get($id);
                    if ($logger->getName() != 'event') {
                        // replace all handlers
                        $logger->setHandlers([$requestDebugHandler]);
                    }
                }
            }
        }
    }

    /**
     * @return bool
     */
    public static function isLegacyModeAvailable()
    {
        return class_exists('Pimcore\\Legacy');
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
     * @throws Exception
     */
    public static function __callStatic($name, $arguments)
    {
        if (self::isLegacyModeAvailable()) {
            return forward_static_call_array('Pimcore\\Legacy::' . $name, $arguments);
        }

        throw new \Exception('Call to undefined static method ' . $name . ' on class Pimcore');
    }
}
