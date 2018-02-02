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

namespace Pimcore\Document\Renderer;

use Pimcore\Http\RequestHelper;
use Pimcore\Model\Document;
use Pimcore\Routing\Dynamic\DocumentRouteHandler;
use Pimcore\Targeting\Document\DocumentTargetingConfigurator;
use Pimcore\Templating\Renderer\ActionRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface;

class DocumentRenderer implements DocumentRendererInterface
{
    /**
     * @var RequestHelper
     */
    private $requestHelper;

    /**
     * @var ActionRenderer
     */
    private $actionRenderer;

    /**
     * @var FragmentRendererInterface
     */
    private $fragmentRenderer;

    /**
     * @var DocumentRouteHandler
     */
    private $documentRouteHandler;

    /**
     * @var DocumentTargetingConfigurator
     */
    private $targetingConfigurator;

    public function __construct(
        RequestHelper $requestHelper,
        ActionRenderer $actionRenderer,
        FragmentRendererInterface $fragmentRenderer,
        DocumentRouteHandler $documentRouteHandler,
        DocumentTargetingConfigurator $targetingConfigurator
    ) {
        $this->requestHelper         = $requestHelper;
        $this->actionRenderer        = $actionRenderer;
        $this->fragmentRenderer      = $fragmentRenderer;
        $this->documentRouteHandler  = $documentRouteHandler;
        $this->targetingConfigurator = $targetingConfigurator;
    }

    /**
     * @inheritdoc
     */
    public function render(Document\PageSnippet $document, array $attributes = [], array $query = [], array $options = []): string
    {
        // apply best matching target group (if any)
        $this->targetingConfigurator->configureTargetGroup($document);

        // add document route to request if no route is set
        // this is needed for logic relying on the current route (e.g. pimcoreUrl helper)
        if (!isset($attributes['_route'])) {
            $route = $this->documentRouteHandler->buildRouteForDocument($document);
            $attributes['_route'] = $route->getRouteKey();
        }

        try {
            $request = $this->requestHelper->getCurrentRequest();
        } catch (\Exception $e) {
            $request = new Request();
        }

        $uri      = $this->actionRenderer->createDocumentReference($document, $attributes, $query);
        $response = $this->fragmentRenderer->render($uri, $request, $options);

        return $response->getContent();
    }
}
