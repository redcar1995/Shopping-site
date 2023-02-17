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

namespace Pimcore\Bundle\PersonalizationBundle\Model\Document\Page;

use Pimcore\Bundle\PersonalizationBundle\Model\Document\Targeting\TargetingDocumentDaoInterface;
use Pimcore\Bundle\PersonalizationBundle\Model\Document\Targeting\TargetingDocumentDaoTrait;
use Pimcore\Model;

/**
 * @internal
 *
 * @property \Pimcore\Bundle\PersonalizationBundle\Model\Document\Page $model
 */
class Dao extends Model\Document\PageSnippet\Dao implements TargetingDocumentDaoInterface
{
    use TargetingDocumentDaoTrait;

    /**
     * Get the data for the object by the given id, or by the id which is set in the object
     *
     * @param int|null $id
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getById(int $id = null): void
    {
        if ($id != null) {
            $this->model->setId($id);
        }

        $data = $this->db->fetchAssociative("SELECT documents.*, documents_page.*, tree_locks.locked FROM documents
            LEFT JOIN documents_page ON documents.id = documents_page.id
            LEFT JOIN tree_locks ON documents.id = tree_locks.id AND tree_locks.type = 'document'
                WHERE documents.id = ?", [$this->model->getId()]);

        if (!empty($data['id'])) {
            if (is_string($data['metaData'])) {
                $data['metaData'] = @unserialize($data['metaData']);
            }
            if (!is_array($data['metaData'])) {
                $data['metaData'] = [];
            }
            $this->assignVariablesToModel($data);
        } else {
            throw new Model\Exception\NotFoundException('Page with the ID ' . $this->model->getId() . " doesn't exists");
        }
    }

    public function create(): void
    {
        parent::create();

        $this->db->insert('documents_page', [
            'id' => $this->model->getId(),
        ]);
    }

    /**
     * @throws \Exception
     */
    public function delete(): void
    {
        $this->deleteAllProperties();

        parent::delete();
    }
}
