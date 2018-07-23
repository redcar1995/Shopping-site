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
$workingDirectory = getcwd();
chdir(__DIR__);
include_once('../../../../../config/startup_cli.php');
chdir($workingDirectory);

\Pimcore\Bundle\EcommerceFrameworkBundle\Legacy\LegacyClassMappingTool::createNamespaceCompatibilityFile();

//\OnlineShop\LegacyClassMappingTool::generateMarkdownTable();

die("done.\n\n");
