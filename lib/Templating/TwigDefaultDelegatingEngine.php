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

namespace Pimcore\Templating;

use Pimcore\Config;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\DelegatingEngine as BaseDelegatingEngine;
use Symfony\Component\Templating\EngineInterface;
use Twig\Environment;
use Twig\Extension\SandboxExtension;

/**
 * @internal
 */
class TwigDefaultDelegatingEngine extends BaseDelegatingEngine
{
    /**
     * @var bool
     */
    protected $delegate = false;

    /**
     * @param EngineInterface[] $engines
     */
    public function __construct(protected Environment $twig, protected Config $config, array $engines = [])
    {
        parent::__construct($engines);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name): bool
    {
        if (!$this->delegate) {
            return $this->twig->getLoader()->exists($name);
        } else {
            return parent::exists($name);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function render($name, array $parameters = []): string
    {
        if (!$this->delegate) {
            return $this->twig->render($name, $parameters);
        } else {
            return parent::render($name, $parameters);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($name): bool
    {
        if (!$this->delegate) {
            return true;
        } else {
            return parent::supports($name);
        }
    }

    /**
     * @param bool $delegate
     */
    public function setDelegate(bool $delegate)
    {
        $this->delegate = $delegate;
    }

    /**
     * @return bool $delegate
     */
    public function isDelegate()
    {
        return $this->delegate;
    }

    public function getTwigEnvironment(bool $sandboxed = false): Environment
    {
        if ($sandboxed) {
            /** @var SandboxExtension $sandboxExtension */
            $sandboxExtension = $this->twig->getExtension(SandboxExtension::class);
            $sandboxExtension->enableSandbox();
        }

        return $this->twig;
    }

    public function disableSandboxExtensionFromTwigEnvironment(): void
    {
        /** @var SandboxExtension $sandboxExtension */
        $sandboxExtension = $this->twig->getExtension(SandboxExtension::class);
        $sandboxExtension->disableSandbox();
    }

    /**
     * @param string $view
     * @param array $parameters
     * @param Response|null $response
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function renderResponse($view, array $parameters = [], Response $response = null)
    {
        if (null === $response) {
            $response = new Response();
        }

        $response->setContent($this->render($view, $parameters));

        return $response;
    }
}
