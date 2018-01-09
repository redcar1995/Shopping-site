<?php

declare(strict_types=1);

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

namespace Pimcore\Install;

use Pimcore\Bundle\InstallBundle\Controller\InstallController;
use Pimcore\Bundle\InstallBundle\PimcoreInstallBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollectionBuilder;

class InstallerKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @var string
     */
    private $projectRoot;

    public function __construct(string $projectRoot, string $environment, bool $debug)
    {
        $this->projectRoot = $projectRoot;

        parent::__construct($environment, $debug);
    }

    /**
     * @inheritDoc
     */
    public function getProjectDir()
    {
        return $this->projectRoot;
    }

    /**
     * @inheritDoc
     */
    public function getRootDir()
    {
        return $this->projectRoot . '/var/installer';
    }

    /**
     * @inheritDoc
     */
    public function registerBundles()
    {
        $bundles = [
            new FrameworkBundle(),
            new MonologBundle(),
            new TwigBundle(),
            new PimcoreInstallBundle()
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'])) {
            $bundles[] = new DebugBundle();
        }

        return $bundles;
    }

    /**
     * @inheritDoc
     */
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $c->setParameter('secret', uniqid('installer-', true));

        $c->loadFromExtension('twig', [
            'paths' => [
                __DIR__ . '/../Bundle/AdminBundle/Resources/views' => 'PimcoreAdminBundle'
            ]
        ]);

        $loader->load('@PimcoreInstallBundle/Resources/config/config.yml');

        // load installer config files if available
        foreach (['php', 'yaml', 'yml', 'xml'] as $extension) {
            $file = sprintf('%s/app/config/installer.%s', $this->getProjectDir(), $extension);

            if (file_exists($file)) {
                $loader->load($file);
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->addRoute($this->buildRoute('/install/', 'index', ['GET']));
        $routes->addRoute($this->buildRoute('/install/', 'install', ['POST']));
        $routes->addRoute($this->buildRoute('/install/check', 'check', ['POST']));
    }

    private function buildRoute(string $path, string $action, array $methods = []): Route
    {
        $route = new Route($path);
        $route->setDefault('_controller', sprintf('%s:%sAction', InstallController::class, $action));

        if (!empty($methods)) {
            $route->setMethods($methods);
        }

        return $route;
    }
}
