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

namespace Pimcore\Document\Renderer;

use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Http\RequestHelper;
use Pimcore\Localization\LocaleService;
use Pimcore\Model\Document;
use Pimcore\Routing\Dynamic\DocumentRouteHandler;
use Pimcore\Templating\Renderer\ActionRenderer;
use Pimcore\Twig\Extension\Templating\Placeholder\ContainerService;
use Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

class DocumentRenderer implements DocumentRendererInterface
{
    private RequestHelper $requestHelper;

    private ActionRenderer $actionRenderer;

    private FragmentRendererInterface $fragmentRenderer;

    private DocumentRouteHandler $documentRouteHandler;

    private EventDispatcherInterface $eventDispatcher;

    private LocaleService $localeService;

    public function __construct(
        RequestHelper $requestHelper,
        ActionRenderer $actionRenderer,
        FragmentRendererInterface $fragmentRenderer,
        DocumentRouteHandler $documentRouteHandler,
        EventDispatcherInterface $eventDispatcher,
        LocaleService $localeService
    ) {
        $this->requestHelper = $requestHelper;
        $this->actionRenderer = $actionRenderer;
        $this->fragmentRenderer = $fragmentRenderer;
        $this->documentRouteHandler = $documentRouteHandler;
        $this->eventDispatcher = $eventDispatcher;
        $this->localeService = $localeService;
    }

    #[Required]
    public function setContainerService(ContainerService $containerService): void
    {
        // we have to ensure that the ContainerService was initialized at the time this service is created
        // this is necessary, since the ContainerService registers a listener for DocumentEvents::RENDERER_PRE_RENDER
        // which wouldn't be called if the ContainerService would be lazy initialized when the first
        // placeholder service/templating helper is used during the rendering process
    }

    /**
     * {@inheritdoc}
     */
    public function render(Document\PageSnippet $document, array $attributes = [], array $query = [], array $options = []): string
    {
        $this->eventDispatcher->dispatch(
            new DocumentEvent($document, $attributes),
            DocumentEvents::RENDERER_PRE_RENDER
        );

        // add document route to request if no route is set
        // this is needed for logic relying on the current route (e.g. pimcoreUrl helper)
        if (!isset($attributes['_route'])) {
            $route = $this->documentRouteHandler->buildRouteForDocument($document);
            if (null !== $route) {
                $attributes['_route'] = $route->getRouteKey();
            }
        }

        try {
            $request = $this->requestHelper->getCurrentRequest();
        } catch (\Exception $e) {
            $request = $this->requestHelper->createRequestWithContext();
        }

        $documentLocale = $document->getProperty('language');
        $tempLocale = $this->localeService->getLocale();
        if ($documentLocale) {
            $this->localeService->setLocale($documentLocale);
            $request->setLocale($documentLocale);
        }

        $uri = $this->actionRenderer->createDocumentReference($document, $attributes, $query);
        $response = $this->fragmentRenderer->render($uri, $request, $options);

        $this->localeService->setLocale($tempLocale);

        $this->eventDispatcher->dispatch(
            new DocumentEvent($document, $attributes),
            DocumentEvents::RENDERER_POST_RENDER
        );

        return $response->getContent();
    }
}
