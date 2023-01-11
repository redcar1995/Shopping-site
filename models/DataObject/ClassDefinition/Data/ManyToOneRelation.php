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

namespace Pimcore\Model\DataObject\ClassDefinition\Data;

use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData;
use Pimcore\Model\DataObject\Localizedfield;
use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Normalizer\NormalizerInterface;

class ManyToOneRelation extends AbstractRelations implements QueryResourcePersistenceAwareInterface, VarExporterInterface, NormalizerInterface, PreGetDataInterface, PreSetDataInterface
{
    use Extension\QueryColumnType;
    use DataObject\ClassDefinition\Data\Relations\AllowObjectRelationTrait;
    use DataObject\ClassDefinition\Data\Relations\AllowAssetRelationTrait;
    use DataObject\ClassDefinition\Data\Relations\AllowDocumentRelationTrait;
    use DataObject\ClassDefinition\Data\Extension\RelationFilterConditionParser;
    use DataObject\Traits\DataWidthTrait;

    /**
     * Static type of this element
     *
     * @internal
     *
     * @var string
     */
    public string $fieldtype = 'manyToOneRelation';

    /**
     * @internal
     */
    public bool $assetInlineDownloadAllowed = false;

    /**
     * @internal
     *
     * @var string
     */
    public string $assetUploadPath;

    /**
     * @internal
     */
    public bool $allowToClearRelation = true;

    /**
     * @internal
     */
    public bool $relationType = true;

    /**
     * Type for the column to query
     *
     * @internal
     *
     * @var array
     */
    public $queryColumnType = [
        'id' => 'int(11)',
        'type' => "enum('document','asset','object')",
    ];

    /**
     * @internal
     */
    public bool $objectsAllowed = false;

    /**
     * @internal
     */
    public bool $assetsAllowed = false;

    /**
     * Allowed asset types
     *
     * @internal
     *
     * @var array
     */
    public array $assetTypes = [];

    /**
     * @internal
     */
    public bool $documentsAllowed = false;

    /**
     * Allowed document types
     *
     * @internal
     *
     * @var array
     */
    public array $documentTypes = [];

    public function getObjectsAllowed(): bool
    {
        return $this->objectsAllowed;
    }

    public function setObjectsAllowed(bool $objectsAllowed): static
    {
        $this->objectsAllowed = $objectsAllowed;

        return $this;
    }

    public function getDocumentsAllowed(): bool
    {
        return $this->documentsAllowed;
    }

    public function setDocumentsAllowed(bool $documentsAllowed): static
    {
        $this->documentsAllowed = $documentsAllowed;

        return $this;
    }

    public function getDocumentTypes(): array
    {
        return $this->documentTypes ?: [];
    }

    public function setDocumentTypes(array $documentTypes): static
    {
        $this->documentTypes = Element\Service::fixAllowedTypes($documentTypes, 'documentTypes');

        return $this;
    }

    public function getAssetsAllowed(): bool
    {
        return $this->assetsAllowed;
    }

    public function setAssetsAllowed(bool $assetsAllowed): static
    {
        $this->assetsAllowed = $assetsAllowed;

        return $this;
    }

    public function getAssetTypes(): array
    {
        return $this->assetTypes ?: [];
    }

    public function setAssetTypes(array $assetTypes): static
    {
        $this->assetTypes = Element\Service::fixAllowedTypes($assetTypes, 'assetTypes');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareDataForPersistence(array|Element\ElementInterface $data, Localizedfield|AbstractData|\Pimcore\Model\DataObject\Objectbrick\Data\AbstractData|Concrete $object = null, array $params = []): mixed
    {
        if ($data instanceof Element\ElementInterface) {
            $type = Element\Service::getElementType($data);
            $id = $data->getId();

            return [[
                'dest_id' => $id,
                'type' => $type,
                'fieldname' => $this->getName(),
            ]];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function loadData(array $data, Localizedfield|AbstractData|\Pimcore\Model\DataObject\Objectbrick\Data\AbstractData|Concrete $object = null, array $params = []): mixed
    {
        // data from relation table
        $data = current($data);

        $result = [
            'dirty' => false,
            'data' => null,
        ];

        if (!empty($data['dest_id']) && !empty($data['type'])) {
            $element = Element\Service::getElementById($data['type'], $data['dest_id']);
            if ($element instanceof Element\ElementInterface) {
                $result['data'] = $element;
            } else {
                $result['dirty'] = true;
            }
        }

        return $result;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     *
     * @see QueryResourcePersistenceAwareInterface::getDataForQueryResource
     */
    public function getDataForQueryResource(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        $return = [];

        if ($data != null) {
            $rData = $this->prepareDataForPersistence($data, $object, $params);

            $return[$this->getName() . '__id'] = isset($rData[0]['dest_id']) ? $rData[0]['dest_id'] : null;
            $return[$this->getName() . '__type'] = isset($rData[0]['type']) ? $rData[0]['type'] : null;
        }

        return $return;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array|null
     *
     * @see Data::getDataForEditmode
     *
     */
    public function getDataForEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): ?array
    {
        if ($data instanceof Element\ElementInterface) {
            $r = [
                'id' => $data->getId(),
                'path' => $data->getRealFullPath(),
                'subtype' => $data->getType(),
                'type' => Element\Service::getElementType($data),
                'published' => Element\Service::isPublished($data),
            ];

            return $r;
        }

        return null;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return Asset|Document|DataObject\AbstractObject|null
     *
     * @see Data::getDataFromEditmode
     */
    public function getDataFromEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): Asset|Document|DataObject\AbstractObject|null
    {
        if (!empty($data['id']) && !empty($data['type'])) {
            return Element\Service::getElementById($data['type'], $data['id']);
        }

        return null;
    }

    /**
     * @param array $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return Asset|Document|DataObject\AbstractObject|null
     */
    public function getDataFromGridEditor(array $data, Concrete $object = null, array $params = []): Asset|Document|DataObject\AbstractObject|null
    {
        return $this->getDataFromEditmode($data, $object, $params);
    }

    /**
     * @param Element\ElementInterface|null $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return array|null
     */
    public function getDataForGrid(?Element\ElementInterface $data, Concrete $object = null, array $params = []): ?array
    {
        return $this->getDataForEditmode($data, $object, $params);
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return string
     *
     * @see Data::getVersionPreview
     *
     */
    public function getVersionPreview(mixed $data, DataObject\Concrete $object = null, array $params = []): string
    {
        if ($data instanceof Element\ElementInterface) {
            return Element\Service::getElementType($data).' '.$data->getRealFullPath();
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function checkValidity(mixed $data, bool $omitMandatoryCheck = false, array $params = [])
    {
        if (!$omitMandatoryCheck && $this->getMandatory() && empty($data)) {
            throw new Element\ValidationException('Empty mandatory field [ '.$this->getName().' ]');
        }

        if ($data instanceof Document) {
            $allow = $this->allowDocumentRelation($data);
        } elseif ($data instanceof Asset) {
            $allow = $this->allowAssetRelation($data);
        } elseif ($data instanceof DataObject\AbstractObject) {
            $allow = $this->allowObjectRelation($data);
        } elseif (empty($data)) {
            $allow = true;
        } else {
            Logger::error(sprintf('Invalid data in field `%s` [type: %s]', $this->getName(), $this->getFieldtype()));
            $allow = false;
        }

        if (!$allow) {
            throw new Element\ValidationException(sprintf('Invalid data in field `%s` [type: %s]', $this->getName(), $this->getFieldtype()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getForCsvExport(DataObject\Localizedfield|DataObject\Fieldcollection\Data\AbstractData|DataObject\Objectbrick\Data\AbstractData|DataObject\Concrete $object, array $params = []): string
    {
        $data = $this->getDataFromObjectParam($object, $params);
        if ($data instanceof Element\ElementInterface) {
            return Element\Service::getElementType($data).':'.$data->getRealFullPath();
        }

        return '';
    }

    public function resolveDependencies(mixed $data): array
    {
        $dependencies = [];

        if ($data instanceof Element\ElementInterface) {
            $elementType = Element\Service::getElementType($data);
            $dependencies[$elementType . '_' . $data->getId()] = [
                'id' => $data->getId(),
                'type' => $elementType,
            ];
        }

        return $dependencies;
    }

    public function preGetData(mixed $container, array $params = []): ?Element\ElementInterface
    {
        $data = null;
        if ($container instanceof DataObject\Concrete) {
            $data = $container->getObjectVar($this->getName());

            if (!$container->isLazyKeyLoaded($this->getName())) {
                $data = $this->load($container);

                $container->setObjectVar($this->getName(), $data);
                $this->markLazyloadedFieldAsLoaded($container);
            }
        } elseif ($container instanceof DataObject\Localizedfield) {
            $data = $params['data'];
        } elseif ($container instanceof DataObject\Fieldcollection\Data\AbstractData) {
            parent::loadLazyFieldcollectionField($container);
            $data = $container->getObjectVar($this->getName());
        } elseif ($container instanceof DataObject\Objectbrick\Data\AbstractData) {
            parent::loadLazyBrickField($container);
            $data = $container->getObjectVar($this->getName());
        }

        if (DataObject::doHideUnpublished() && ($data instanceof Element\ElementInterface)) {
            if (!Element\Service::isPublished($data)) {
                return null;
            }
        }

        return $data;
    }

    /**
     * { @inheritdoc }
     */
    public function preSetData(mixed $container, mixed $data, array $params = []): mixed
    {
        $this->markLazyloadedFieldAsLoaded($container);

        return $data;
    }

    /**
     * @return $this
     */
    public function setAssetInlineDownloadAllowed(bool $assetInlineDownloadAllowed): static
    {
        $this->assetInlineDownloadAllowed = $assetInlineDownloadAllowed;

        return $this;
    }

    public function getAssetInlineDownloadAllowed(): bool
    {
        return $this->assetInlineDownloadAllowed;
    }

    public function setAssetUploadPath(string $assetUploadPath): static
    {
        $this->assetUploadPath = $assetUploadPath;

        return $this;
    }

    public function getAssetUploadPath(): string
    {
        return $this->assetUploadPath;
    }

    public function isAllowedToClearRelation(): bool
    {
        return $this->allowToClearRelation;
    }

    public function setAllowToClearRelation(bool $allowToClearRelation): void
    {
        $this->allowToClearRelation = $allowToClearRelation;
    }

    /**
     * {@inheritdoc}
     */
    public function isDiffChangeAllowed(Concrete $object, array $params = []): bool
    {
        return true;
    }

    /**
     * { @inheritdoc }
     */
    public function rewriteIds(mixed $container, array $idMapping, array $params = []): mixed
    {
        $data = $this->getDataFromObjectParam($container, $params);
        if ($data) {
            $data = $this->rewriteIdsService([$data], $idMapping);
            $data = $data[0]; //get the first element
        }

        return $data;
    }

    /**
     * @param DataObject\ClassDefinition\Data\ManyToOneRelation $masterDefinition
     */
    public function synchronizeWithMasterDefinition(DataObject\ClassDefinition\Data $masterDefinition)
    {
        $this->assetUploadPath = $masterDefinition->assetUploadPath;
        $this->relationType = $masterDefinition->relationType;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPhpdocType(): string
    {
        return $this->getPhpDocClassString(false);
    }

    public function normalize(mixed $value, array $params = []): ?array
    {
        if ($value) {
            $type = Element\Service::getElementType($value);
            $id = $value->getId();

            return [
                'type' => $type,
                'id' => $id,
            ];
        }

        return null;
    }

    public function denormalize(mixed $value, array $params = []): null|Model\Asset|Model\DataObject\AbstractObject|Model\Document
    {
        if (is_array($value)) {
            $type = $value['type'];
            $id = $value['id'];

            return Element\Service::getElementById($type, $id);
        }

        return null;
    }

    public function isEqual(mixed $oldValue, mixed $newValue): bool
    {
        $oldValue = $oldValue ? $oldValue->getType() . $oldValue->getId() : null;
        $newValue = $newValue ? $newValue->getType() . $newValue->getId() : null;

        return $oldValue === $newValue;
    }

    /**
     * {@inheritdoc}
     */
    public function isFilterable(): bool
    {
        return true;
    }

    public function getParameterTypeDeclaration(): ?string
    {
        return '?\\' . Element\AbstractElement::class;
    }

    public function getReturnTypeDeclaration(): ?string
    {
        return '?\\' . Element\AbstractElement::class;
    }

    /**
     * {@inheritdoc}
     */
    public function addListingFilter(DataObject\Listing $listing, float|array|int|string|Model\Element\ElementInterface $data, string $operator = '='): DataObject\Listing
    {
        if ($data instanceof Element\ElementInterface) {
            $data = [
                'id' => $data->getId(),
                'type' => Element\Service::getElementType($data),
            ];
        }

        if (!isset($data['id'], $data['type'])) {
            throw new \InvalidArgumentException('Please provide an array with keys "id" and "type" or an object which implements '.Element\ElementInterface::class);
        }

        if ($operator === '=') {
            $listing->addConditionParam('`'.$this->getName().'__id` = ? AND `'.$this->getName().'__type` = ?', [$data['id'], $data['type']]);

            return $listing;
        }

        throw new \InvalidArgumentException('Filtering '.__CLASS__.' does only support "=" operator');
    }

    public function getPhpdocInputType(): ?string
    {
        if ($phpdocType = $this->getPhpdocType()) {
            return $phpdocType . '|null';
        }

        return null;
    }

    public function getPhpdocReturnType(): ?string
    {
        if ($phpdocType = $this->getPhpdocType()) {
            return $phpdocType . '|null';
        }

        return null;
    }

    /**
     * Filter by relation feature
     *
     * @param mixed $value
     * @param string $operator
     * @param array $params
     *
     * @return string
     */
    public function getFilterConditionExt(mixed $value, string $operator, array $params = []): string
    {
        $name = $params['name'] . '__id';
        if (preg_match('/^(asset|object|document)\|(\d+)/', $value, $matches)) {
            $typeField = $params['name'] . '__type';
            $typeCondition = '`' . $typeField . '` = ' . "'" . $matches[1] . "'";
            $value = $matches[2];

            return '(' . $typeCondition . ' AND ' . $this->getRelationFilterCondition($value, $operator, $name) . ')';
        }

        return $this->getRelationFilterCondition($value, $operator, $name);
    }

    public function getVisibleFields(): ?string
    {
        return 'fullpath';
    }
}
