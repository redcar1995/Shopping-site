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

namespace Pimcore\Bundle\AdminBundle\Controller\Admin\Document;

use Pimcore\Event\AdminEvents;
use Pimcore\Logger;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/hardlink")
 */
class HardlinkController extends DocumentControllerBase
{
    /**
     * @Route("/get-data-by-id", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getDataByIdAction(Request $request)
    {
        // check for lock
        if (Element\Editlock::isLocked($request->get('id'), 'document')) {
            return $this->adminJson([
                'editlock' => Element\Editlock::getByElement($request->get('id'), 'document')
            ]);
        }
        Element\Editlock::lock($request->get('id'), 'document');

        $link = Document\Hardlink::getById($request->get('id'));
        $link = clone $link;

        $link->idPath = Element\Service::getIdPath($link);
        $link->setUserPermissions($link->getUserPermissions());
        $link->setLocked($link->isLocked());
        $link->setParent(null);

        if ($link->getSourceDocument()) {
            $link->sourcePath = $link->getSourceDocument()->getRealFullPath();
        }

        $this->addTranslationsData($link);
        $this->minimizeProperties($link);
        $link->getScheduledTasks();

        //Hook for modifying return value - e.g. for changing permissions based on object data
        //data need to wrapped into a container in order to pass parameter to event listeners by reference so that they can change the values
        $data = $link->getObjectVars();
        $event = new GenericEvent($this, [
            'data' => $data,
            'document' => $link
        ]);
        \Pimcore::getEventDispatcher()->dispatch(AdminEvents::DOCUMENT_GET_PRE_SEND_DATA, $event);
        $data = $event->getArgument('data');
        $data['versionDate'] = $link->getModificationDate();

        if ($link->isAllowed('view')) {
            return $this->adminJson($data);
        }

        return $this->adminJson(false);
    }

    /**
     * @Route("/save", methods={"POST", "PUT"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function saveAction(Request $request)
    {
        try {
            if ($request->get('id')) {
                $link = Document\Hardlink::getById($request->get('id'));
                $this->setValuesToDocument($request, $link);

                $link->setModificationDate(time());
                $link->setUserModification($this->getAdminUser()->getId());

                if ($request->get('task') == 'unpublish') {
                    $link->setPublished(false);
                }
                if ($request->get('task') == 'publish') {
                    $link->setPublished(true);
                }

                // only save when publish or unpublish
                if (($request->get('task') == 'publish' && $link->isAllowed('publish')) || ($request->get('task') == 'unpublish' && $link->isAllowed('unpublish'))) {
                    $link->save();

                    return $this->adminJson(['success' => true,
                                             'data' => ['versionDate' => $link->getModificationDate(),
                                                        'versionCount' => $link->getVersionCount()]]);
                }
            }
        } catch (\Exception $e) {
            Logger::log($e);
            if ($e instanceof Element\ValidationException) {
                return $this->adminJson(['success' => false, 'type' => 'ValidationException', 'message' => $e->getMessage(), 'stack' => $e->getTraceAsString(), 'code' => $e->getCode()]);
            }
            throw $e;
        }

        return $this->adminJson(false);
    }

    /**
     * @param Request $request
     * @param Document\Hardlink $link
     */
    protected function setValuesToDocument(Request $request, Document $link)
    {

        // data
        if ($request->get('data')) {
            $data = $this->decodeJson($request->get('data'));

            $sourceId = null;
            if ($sourceDocument = Document::getByPath($data['sourcePath'])) {
                $sourceId = $sourceDocument->getId();
            }
            $link->setSourceId($sourceId);
            $link->setValues($data);
        }

        $this->addPropertiesToDocument($request, $link);
        $this->addSchedulerToDocument($request, $link);
    }
}
