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

namespace Pimcore\Bundle\AdminBundle\Session\Handler;

use Pimcore\Bundle\CoreBundle\EventListener\Traits\PimcoreContextAwareTrait;
use Pimcore\Http\Request\Resolver\PimcoreContextResolver;
use Pimcore\Http\RequestHelper;
use Pimcore\Session\Attribute\LockableAttributeBagInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
class AdminSessionHandler implements LoggerAwareInterface, AdminSessionHandlerInterface
{
    use LoggerAwareTrait;
    use PimcoreContextAwareTrait;

    /**
     * Contains how many sessions are currently open, this is important, because writeClose() must not be called if
     * there is still an open session, this is especially important if something doesn't use the method use() but get()
     * so the session isn't closed automatically after the action is done
     */
    private $openedSessions = 0;

    /**
     * @deprecated
     *
     * @var SessionInterface
     */
    protected $session;

    protected $readOnlySessionBagsCache = [];

    /**
     * @var bool|null
     */
    private $canWriteAndClose;

    /**
     * @var RequestHelper
     */
    protected $requestHelper;

    public function __construct(RequestHelper $requestHelper)
    {
        $this->requestHelper = $requestHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getSessionId()
    {
        if (!$this->getSession()->isStarted()) {
            // this is just to initialize the session :)
            $this->useSession(static function (SessionInterface $session) {
                return $session->getId();
            });
        }

        return $this->getSession()->getId();
    }

    /**
     * @return SessionInterface
     */
    private function getSession()
    {
        try {
            return $this->requestHelper->getSession();
        } catch (\LogicException $e) {
            $this->logger->debug('Error while getting the admin session: {exception}', ['exception' => $e->getMessage()]);
        }

        trigger_deprecation('pimcore/pimcore', '10.5',
            sprintf('Session used with non existing request stack in %s, that will not be possible in Pimcore 11.', __CLASS__));

        return \Pimcore::getContainer()->get('session');
    }

    /**
     * {@inheritdoc}
     */
    public function getSessionName()
    {
        return $this->getSession()->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function useSession(callable $callable)
    {
        $session = $this->loadSession();

        $result = call_user_func_array($callable, [$session]);

        $this->writeClose();

        $this->readOnlySessionBagsCache = []; // clear cache if session was modified manually

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function useSessionAttributeBag(callable $callable, string $name = 'pimcore_admin')
    {
        $session = $this->loadSession();
        $attributeBag = $this->loadAttributeBag($name, $session);

        $result = call_user_func_array($callable, [$attributeBag, $session]);
        $this->writeClose();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getReadOnlyAttributeBag(string $name = 'pimcore_admin'): AttributeBagInterface
    {
        if (isset($this->readOnlySessionBagsCache[$name])) {
            $bag = $this->readOnlySessionBagsCache[$name];
        } else {
            $bag = $this->useSessionAttributeBag(function (AttributeBagInterface $bag) {
                return $bag;
            }, $name);
        }

        if ($bag instanceof LockableAttributeBagInterface) {
            $bag->lock();
        }

        return $bag;
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate(int $lifetime = null): bool
    {
        return $this->getSession()->invalidate($lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function regenerateId(): bool
    {
        return $this->useSession(static function (SessionInterface $session) {
            return $session->migrate(true);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function loadAttributeBag(string $name, SessionInterface $session = null): SessionBagInterface
    {
        if (null === $session) {
            $session = $this->loadSession();
        }

        $attributeBag = $session->getBag($name);
        if ($attributeBag instanceof LockableAttributeBagInterface) {
            $attributeBag->unlock();
        }

        $this->readOnlySessionBagsCache[$name] = $attributeBag;

        return $attributeBag;
    }

    /**
     * {@inheritdoc}
     */
    public function requestHasSessionId(Request $request, bool $checkRequestParams = false): bool
    {
        $sessionName = $this->getSessionName();
        if (empty($sessionName)) {
            return false;
        }

        $properties = ['cookies'];

        if ($checkRequestParams) {
            $properties[] = 'request';
            $properties[] = 'query';
        }

        foreach ($properties as $property) {
            if ($request->$property->has($sessionName) && !empty($request->$property->get($sessionName))) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSessionIdFromRequest(Request $request, bool $checkRequestParams = false): string
    {
        if ($this->requestHasSessionId($request, $checkRequestParams)) {
            $sessionName = $this->getSessionName();

            if ($sessionId = $request->cookies->get($sessionName)) {
                return $sessionId;
            }

            if ($checkRequestParams) {
                if ($sessionId = $request->request->get($sessionName)) {
                    return $sessionId;
                }

                if ($sessionId = $request->query->get($sessionName)) {
                    return $sessionId;
                }
            }
        }

        throw new \RuntimeException('Failed to get session ID from request');
    }

    /**
     * {@inheritdoc}
     */
    public function loadSession(): SessionInterface
    {
        $sessionName = $this->getSessionName();

        $this->logger->debug('Opening admin session {name}', ['name' => $sessionName]);

        if (!$this->getSession()->isStarted()) {
            $this->getSession()->start();
        }

        $this->openedSessions++;

        $this->logger->debug('Admin session {name} was successfully opened. Open admin sessions: {count}', [
            'name' => $sessionName,
            'count' => $this->openedSessions,
        ]);

        return $this->getSession();
    }

    /**
     * {@inheritdoc}
     */
    public function writeClose()
    {
        if (!$this->shouldWriteAndClose()) {
            return;
        }

        $this->openedSessions--;

        if (0 === $this->openedSessions) {
            $this->getSession()->save();

            $this->logger->debug('Admin session {name} was written and closed', [
                'name' => $this->getSessionName(),
            ]);
        } else {
            $this->logger->debug('Not writing/closing session admin session {name} as there are still {count} open sessions', [
                'name' => $this->getSessionName(),
                'count' => $this->openedSessions,
            ]);
        }
    }

    /**
     * @return bool
     */
    private function shouldWriteAndClose(): bool
    {
        // main request is not available in CLI, so session should be written
        // otherwise session should be written & closed in Admin context only.
        return $this->canWriteAndClose ??= !$this->requestHelper->hasMainRequest()
            || $this->isAdminRequest($this->requestHelper->getMainRequest());
    }

    /**
     * @return bool
     */
    private function isAdminRequest(Request $request): bool
    {
        return $this->matchesPimcoreContext($request, PimcoreContextResolver::CONTEXT_ADMIN)
            || $this->requestHelper->isFrontendRequestByAdmin($request);
    }
}
