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

use Pimcore\Controller\Traits\ElementEditLockHelperTrait;
use Pimcore\Event\Admin\ElementAdminStyleEvent;
use Pimcore\Event\AdminEvents;
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
    use ElementEditLockHelperTrait;

    /**
     * @Route("/get-data-by-id", methods={"GET"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getDataByIdAction(Request $request)
    {
        $folder = Document\Folder::getById($request->get('id'));

        if (!$folder) {
            throw $this->createNotFoundException('Folder not found');
        }

        // check for lock
        if ($folder->isAllowed('save') || $folder->isAllowed('publish') || $folder->isAllowed('unpublish') || $folder->isAllowed('delete')) {
            if (Element\Editlock::isLocked($request->get('id'), 'document')) {
                return $this->getEditLockResponse($request->get('id'), 'document');
            }
            Element\Editlock::lock($request->get('id'), 'document');
        }

        $folder = clone $folder;
        $folder->setLocked($folder->isLocked());
        $folder->setParent(null);

        $this->addTranslationsData($folder);
        $this->minimizeProperties($folder);

        $data = $folder->getObjectVars();

        $this->preSendDataActions($data, $folder);

        if ($folder->isAllowed('view')) {
            return $this->adminJson($data);
        }

        throw $this->createAccessDeniedHttpException();
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
        $folder = Document\Folder::getById($request->get('id'));

        if (!$folder) {
            throw $this->createNotFoundException('Folder not found');
        }

        $folder->setModificationDate(time());
        $folder->setUserModification($this->getAdminUser()->getId());

        if ($folder->isAllowed('publish')) {
            $this->setValuesToDocument($request, $folder);
            $folder->save();

            $this->addAdminStyle($folder, ElementAdminStyleEvent::CONTEXT_EDITOR, $treeData);

            return $this->adminJson(['success' => true, 'treeData' => $treeData]);
        } else {
            throw $this->createAccessDeniedHttpException();
        }
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
