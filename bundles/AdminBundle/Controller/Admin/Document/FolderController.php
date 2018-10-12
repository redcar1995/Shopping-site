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
 * @Route("/folder")
 */
class FolderController extends DocumentControllerBase
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

        $folder = Document\Folder::getById($request->get('id'));
        $folder = clone $folder;

        $folder->idPath = Element\Service::getIdPath($folder);
        $folder->setUserPermissions($folder->getUserPermissions());
        $folder->setLocked($folder->isLocked());
        $folder->setParent(null);

        $this->addTranslationsData($folder);
        $this->minimizeProperties($folder);

        //Hook for modifying return value - e.g. for changing permissions based on object data
        //data need to wrapped into a container in order to pass parameter to event listeners by reference so that they can change the values
        $data = $folder->getObjectVars();
        $event = new GenericEvent($this, [
            'data' => $data,
            'document' => $folder
        ]);
        \Pimcore::getEventDispatcher()->dispatch(AdminEvents::DOCUMENT_GET_PRE_SEND_DATA, $event);
        $data = $event->getArgument('data');

        if ($folder->isAllowed('view')) {
            return $this->adminJson($data);
        }

        return $this->adminJson(false);
    }

    /**
     * @Route("/save", methods={"PUT", "POST"})
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
                $folder = Document\Folder::getById($request->get('id'));
                $folder->setModificationDate(time());
                $folder->setUserModification($this->getAdminUser()->getId());

                if ($folder->isAllowed('publish')) {
                    $this->setValuesToDocument($request, $folder);
                    $folder->save();

                    return $this->adminJson(['success' => true]);
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
     * @param Document $folder
     */
    protected function setValuesToDocument(Request $request, Document $folder)
    {
        $this->addPropertiesToDocument($request, $folder);
    }
}
