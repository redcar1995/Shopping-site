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

namespace Pimcore\Model\Metadata\Predefined;

use Pimcore\Model;
use Symfony\Component\Uid\Uuid as Uid;

/**
 * @internal
 *
 * @property \Pimcore\Model\Metadata\Predefined $model
 */
class Dao extends Model\Dao\PimcoreLocationAwareConfigDao
{
    public function configure()
    {
        $config = \Pimcore::getContainer()->getParameter('pimcore.config');

        parent::configure([
            'containerConfig' => $config['assets']['metadata']['predefined']['definitions'],
            'settingsStoreScope' => 'pimcore_predefined_asset_metadata',
            'storageDirectory' => $_SERVER['PIMCORE_CONFIG_STORAGE_DIR_PREDEFINED_ASSET_METADATA'] ?? PIMCORE_CONFIGURATION_DIRECTORY  . '/predefined-asset-metadata',
            'legacyConfigFile' => 'predefined-asset-metadata.php',
            'writeTargetEnvVariableName' => 'PIMCORE_WRITE_TARGET_PREDEFINED_ASSET_METADATA',
        ]);
    }

    /**
     * @param string|null $id
     *
     * @throws Model\Exception\NotFoundException
     */
    public function getById($id = null)
    {
        if ($id != null) {
            $this->model->setId($id);
        }

        $data = $this->getDataByName($this->model->getId());

        if ($data && $id != null) {
            $data['id'] = $id;
        }

        if ($data) {
            $this->assignVariablesToModel($data);
        } else {
            throw new Model\Exception\NotFoundException('Predefined asset metadata with id: ' . $this->model->getId() . ' does not exist');
        }
    }

    /**
     * @param string|null $name
     * @param string|null $language
     *
     * @throws \Exception
     */
    public function getByNameAndLanguage($name = null, $language = null)
    {
        $list = new Listing();
        /** @var Model\Metadata\Predefined[] $definitions */
        $definitions = array_values(array_filter($list->getDefinitions(), function ($item) use ($name, $language) {
            $return = true;
            if ($name && $item->getName() != $name) {
                $return = false;
            }
            if ($language && $item->getLanguage() != $language) {
                $return = false;
            }

            return $return;
        }));

        if (count($definitions) && $definitions[0]->getId()) {
            $this->assignVariablesToModel($definitions[0]->getObjectVars());
        } else {
            throw new Model\Exception\NotFoundException(sprintf('Predefined metadata config with name "%s" and language %s does not exist.', $name, $language));
        }
    }

    /**
     * @throws \Exception
     */
    public function save()
    {
        if (!$this->model->getId()) {
            $this->model->setId(Uid::v4());
        }
        $ts = time();
        if (!$this->model->getCreationDate()) {
            $this->model->setCreationDate($ts);
        }
        $this->model->setModificationDate($ts);

        $dataRaw = $this->model->getObjectVars();
        $data = [];
        $allowedProperties = ['name', 'description', 'group', 'language', 'type', 'data',
            'targetSubtype', 'config', 'creationDate', 'modificationDate', ];

        foreach ($dataRaw as $key => $value) {
            if (in_array($key, $allowedProperties)) {
                $data[$key] = $value;
            }
        }
        $this->saveData($this->model->getId(), $data);
    }

    /**
     * Deletes object from database
     */
    public function delete()
    {
        $this->deleteData($this->model->getId());
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareDataStructureForYaml(string $id, $data)
    {
        return [
            'pimcore' => [
                'assets' => [
                    'metadata' => [
                        'predefined' => [
                            'definitions' => [
                                $id => $data,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
