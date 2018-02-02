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
use Pimcore\Config;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Symfony\Component\Console\Input\ArgvInput;

// ensure the cli arguments are set
if (!isset($_SERVER['argv'])) {
    $_SERVER['argv'] = [];
}

// although this was already defined in console, we re-check here as simple CLI scripts could
// just include this file to get started
if (!defined('PIMCORE_PROJECT_ROOT')) {
    define(
        'PIMCORE_PROJECT_ROOT',
        getenv('PIMCORE_PROJECT_ROOT')
            ?: getenv('REDIRECT_PIMCORE_PROJECT_ROOT')
            ?: realpath(__DIR__ . '/../..')
    );
}

// determines if we're in Pimcore\Console mode
$pimcoreConsole = (defined('PIMCORE_CONSOLE') && true === PIMCORE_CONSOLE);

require_once PIMCORE_PROJECT_ROOT . '/pimcore/config/bootstrap.php';

$workingDirectory = getcwd();
chdir(__DIR__);

// init shell verbosity as 0 - this would normally be handled by the console application,
// but as we boot the kernel early the kernel initializes this to 3 (verbose) by default
putenv('SHELL_VERBOSITY=0');
$_ENV['SHELL_VERBOSITY'] = 0;
$_SERVER['SHELL_VERBOSITY'] = 0;

if ($pimcoreConsole) {
    $input = new ArgvInput();
    $env   = $input->getParameterOption(['--env', '-e'], Config::getEnvironment());
    $debug = Pimcore::inDebugMode() && !$input->hasParameterOption(['--no-debug', '']);

    Config::setEnvironment($env);
    if (!defined('PIMCORE_DEBUG')) {
        define('PIMCORE_DEBUG', $debug);
    }
}

/** @var \Pimcore\Kernel $kernel */
$kernel = include_once __DIR__ . '/../config/kernel.php';

chdir($workingDirectory);

//Activate Inheritance for cli-scripts
\Pimcore::unsetAdminMode();
Document::setHideUnpublished(true);
DataObject\AbstractObject::setHideUnpublished(true);
DataObject\AbstractObject::setGetInheritedValues(true);
DataObject\Localizedfield::setGetFallbackValues(true);

// CLI has no memory/time limits
@ini_set('memory_limit', -1);
@ini_set('max_execution_time', -1);
@ini_set('max_input_time', -1);

// Error reporting is enabled in CLI
@ini_set('display_errors', 'On');
@ini_set('display_startup_errors', 'On');
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

// Pimcore\Console handles maintenance mode through the AbstractCommand
if (!$pimcoreConsole) {
    // skip if maintenance mode is on and the flag is not set
    if (\Pimcore\Tool\Admin::isInMaintenanceMode() && !in_array('--ignore-maintenance-mode', $_SERVER['argv'])) {
        die("in maintenance mode -> skip\nset the flag --ignore-maintenance-mode to force execution \n");
    }
}

return $kernel;
