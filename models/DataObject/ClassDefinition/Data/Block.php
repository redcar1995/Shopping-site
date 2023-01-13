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

use Pimcore\Db;
use Pimcore\Element\MarshallerService;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Layout;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Fieldcollection\Data\AbstractData;
use Pimcore\Model\DataObject\Localizedfield;
use Pimcore\Model\Element;
use Pimcore\Normalizer\NormalizerInterface;
use Pimcore\Tool\Serialize;

class Block extends Data implements CustomResourcePersistingInterface, ResourcePersistenceAwareInterface, LazyLoadingSupportInterface, TypeDeclarationSupportInterface, VarExporterInterface, NormalizerInterface, DataContainerAwareInterface, PreGetDataInterface, PreSetDataInterface
{
    use Extension\ColumnType;
    use DataObject\Traits\ClassSavedTrait;

    /**
     * Static type of this element
     *
     * @internal
     *
     * @var string
     */
    public string $fieldtype = 'block';

    /**
     * @internal
     */
    public bool $lazyLoading = false;

    /**
     * @internal
     */
    public bool $disallowAddRemove = false;

    /**
     * @internal
     */
    public bool $disallowReorder = false;

    /**
     * @internal
     */
    public bool $collapsible = false;

    /**
     * @internal
     */
    public bool $collapsed = false;

    /**
     * @internal
     *
     * @var int|null
     */
    public ?int $maxItems = null;

    /**
     * Type for the column
     *
     * @internal
     *
     * @var string
     */
    public $columnType = 'longtext';

    /**
     * @internal
     *
     * @var string
     */
    public string $styleElement = '';

    /**
     * @internal
     *
     * @var array
     */
    public array $children = [];

    /**
     * @internal
     *
     * @var array|null
     */
    public ?array $layout = null;

    /**
     * contains further child field definitions if there are more than one localized fields in on class
     *
     * @internal
     *
     * @var array
     */
    protected array $referencedFields = [];

    /**
     * @internal
     *
     * @var array|null
     */
    public ?array $fieldDefinitionsCache = null;

    /**
     * @see ResourcePersistenceAwareInterface::getDataForResource
     *
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return string
     */
    public function getDataForResource(mixed $data, DataObject\Concrete $object = null, array $params = []): string
    {
        $result = [];

        if (is_array($data)) {
            foreach ($data as $blockElements) {
                $resultElement = [];

                /** @var DataObject\Data\BlockElement $blockElement */
                foreach ($blockElements as $elementName => $blockElement) {
                    $this->setBlockElementOwner($blockElement, $params);

                    $fd = $this->getFieldDefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);

                        continue;
                    }
                    $elementData = $blockElement->getData();

                    // $encodedDataBC = $fd->marshal($elementData, $object, ['raw' => true, 'blockmode' => true]);

                    if ($fd instanceof NormalizerInterface) {
                        $normalizedData = $fd->normalize($elementData, [
                            'object' => $object,
                            'fieldDefinition' => $fd,
                        ]);
                        $encodedData = $normalizedData;

                        /** @var MarshallerService $marshallerService */
                        $marshallerService = \Pimcore::getContainer()->get(MarshallerService::class);

                        if ($marshallerService->supportsFielddefinition('block', $fd->getFieldtype())) {
                            $marshaller = $marshallerService->buildFieldefinitionMarshaller('block', $fd->getFieldtype());
                            // TODO format only passed in for BC reasons (localizedfields). remove it as soon as marshal is gone
                            $encodedData = $marshaller->marshal($normalizedData, ['object' => $object, 'fieldDefinition' => $fd, 'format' => 'block']);
                        }

                        // do not serialize the block element itself
                        $resultElement[$elementName] = [
                            'name' => $blockElement->getName(),
                            'type' => $blockElement->getType(),
                            'data' => $encodedData,
                        ];
                    }
                }
                $result[] = $resultElement;
            }
        }
        $result = Serialize::serialize($result);

        return $result;
    }

    /**
     * @see ResourcePersistenceAwareInterface::getDataFromResource
     *
     * @param mixed $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return array|null
     */
    public function getDataFromResource(mixed $data, DataObject\Concrete $object = null, array $params = []): ?array
    {
        if ($data) {
            $count = 0;

            //Fix old serialized data protected properties with \0*\0 prefix
            //https://github.com/pimcore/pimcore/issues/9973
            if (str_contains($data, ':" * ')) {
                $data = preg_replace_callback('!s:(\d+):" \* (.*?)";!', function ($match) {
                    return ($match[1] == strlen($match[2])) ? $match[0] : 's:' . strlen($match[2]) .   ':"' . $match[2] . '";';
                }, $data);
            }

            $unserializedData = Serialize::unserialize($data);
            $result = [];

            foreach ($unserializedData as $blockElements) {
                $items = [];
                foreach ($blockElements as $elementName => $blockElementRaw) {
                    $fd = $this->getFieldDefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);

                        continue;
                    }

                    // do not serialize the block element itself

                    $elementData = $blockElementRaw['data'];

                    if ($fd instanceof NormalizerInterface) {
                        /** @var MarshallerService $marshallerService */
                        $marshallerService = \Pimcore::getContainer()->get(MarshallerService::class);

                        if ($marshallerService->supportsFielddefinition('block', $fd->getFieldtype())) {
                            $unmarshaller = $marshallerService->buildFieldefinitionMarshaller('block', $fd->getFieldtype());
                            // TODO format only passed in for BC reasons (localizedfields). remove it as soon as marshal is gone
                            $elementData = $unmarshaller->unmarshal($elementData, ['object' => $object, 'fieldDefinition' => $fd, 'format' => 'block']);
                        }

                        $dataFromResource = $fd->denormalize($elementData, [
                            'object' => $object,
                            'fieldDefinition' => $fd,
                        ]);

                        $blockElementRaw['data'] = $dataFromResource;
                    }

                    $blockElement = new DataObject\Data\BlockElement($blockElementRaw['name'], $blockElementRaw['type'], $blockElementRaw['data']);

                    if ($blockElementRaw['type'] == 'localizedfields') {
                        /** @var DataObject\Localizedfield|null $data */
                        $data = $blockElementRaw['data'];
                        if ($data) {
                            $data->setObject($object);
                            $data->_setOwner($blockElement);
                            $data->_setOwnerFieldname('localizedfields');

                            $data->setContext(['containerType' => 'block',
                                'fieldname' => $this->getName(),
                                'index' => $count,
                                'containerKey' => $this->getName(),
                                'classId' => $object ? $object->getClassId() : null, ]);
                            $blockElementRaw['data'] = $data;
                        }
                    }

                    $blockElement->setNeedsRenewReferences(true);

                    $this->setBlockElementOwner($blockElement, $params);

                    $items[$elementName] = $blockElement;
                }
                $result[] = $items;
                $count++;
            }

            return $result;
        }

        return null;
    }

    /**
     * @see Data::getDataForEditmode
     *
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     */
    public function getDataForEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        $params = (array)$params;
        $result = [];
        $idx = -1;

        if (is_array($data)) {
            foreach ($data as $blockElements) {
                $resultElement = [];
                $idx++;

                /** @var DataObject\Data\BlockElement $blockElement */
                foreach ($blockElements as $elementName => $blockElement) {
                    $fd = $this->getFieldDefinition($elementName);
                    if (!$fd) {
                        // class definition seems to have changed
                        Logger::warn('class definition seems to have changed, element name: ' . $elementName);

                        continue;
                    }
                    $elementData = $blockElement->getData();
                    $params['context']['containerType'] = 'block';
                    $dataForEditMode = $fd->getDataForEditmode($elementData, $object, $params);
                    $resultElement[$elementName] = $dataForEditMode;

                    if (isset($params['owner'])) {
                        $this->setBlockElementOwner($blockElement, $params);
                    }
                }
                $result[] = [
                    'oIndex' => $idx,
                    'data' => $resultElement,
                ];
            }
        }

        return $result;
    }

    /**
     * @see Data::getDataFromEditmode
     *
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     */
    public function getDataFromEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        $result = [];
        $count = 0;

        foreach ($data as $rawBlockElement) {
            $resultElement = [];

            $oIndex = $rawBlockElement['oIndex'] ?? null;
            $blockElement = $rawBlockElement['data'] ?? null;
            $blockElementDefinition = $this->getFieldDefinitions();

            foreach ($blockElementDefinition as $elementName => $fd) {
                $elementType = $fd->getFieldtype();
                $invisible = $fd->getInvisible();
                if ($invisible && !is_null($oIndex)) {
                    $blockGetter = 'get' . ucfirst($this->getname());
                    if (method_exists($object, $blockGetter)) {
                        $language = $params['language'] ?? null;
                        $items = $object->$blockGetter($language);
                        if (isset($items[$oIndex])) {
                            $item = $items[$oIndex][$elementName];
                            $blockData = $blockElement[$elementName] ?: $item->getData();
                            $resultElement[$elementName] = new DataObject\Data\BlockElement($elementName, $elementType, $blockData);
                        }
                    } else {
                        $params['blockGetter'] = $blockGetter;
                        $blockData = $this->getBlockDataFromContainer($object, $params);
                        if ($blockData) {
                            $resultElement = $blockData[$oIndex];
                        }
                    }
                } else {
                    $elementData = $blockElement[$elementName] ?? null;
                    $blockData = $fd->getDataFromEditmode(
                        $elementData,
                        $object,
                        [
                            'context' => [
                                'containerType' => 'block',
                                'fieldname' => $this->getName(),
                                'index' => $count,
                                'oIndex' => $oIndex,
                                'classId' => $object->getClassId(),
                            ],
                        ]
                    );

                    $resultElement[$elementName] = new DataObject\Data\BlockElement($elementName, $elementType, $blockData);
                }
            }

            $result[] = $resultElement;
            $count++;
        }

        return $result;
    }

    /**
     * @param DataObject\Concrete $object
     * @param array $params
     *
     * @return mixed
     *
     * @throws \Exception
     */
    protected function getBlockDataFromContainer(Concrete $object, array $params = []): mixed
    {
        $data = null;

        $context = $params['context'] ?? null;

        if (isset($context['containerType'])) {
            if ($context['containerType'] === 'fieldcollection') {
                $fieldname = $context['fieldname'];

                if ($object instanceof DataObject\Concrete) {
                    $containerGetter = 'get' . ucfirst($fieldname);
                    $container = $object->$containerGetter();
                    if ($container) {
                        $originalIndex = $context['oIndex'];

                        // field collection or block items
                        if (!is_null($originalIndex)) {
                            $items = $container->getItems();

                            if ($items && count($items) > $originalIndex) {
                                $item = $items[$originalIndex];

                                $getter = 'get' . ucfirst($this->getName());
                                $data = $item->$getter();

                                return $data;
                            }
                        } else {
                            return null;
                        }
                    } else {
                        return null;
                    }
                }
            } elseif ($context['containerType'] === 'objectbrick') {
                $fieldname = $context['fieldname'];

                if ($object instanceof DataObject\Concrete) {
                    $containerGetter = 'get' . ucfirst($fieldname);
                    $container = $object->$containerGetter();
                    if ($container) {
                        $brickGetter = 'get' . ucfirst($context['containerKey']);
                        /** @var DataObject\Objectbrick\Data\AbstractData|null $brickData */
                        $brickData = $container->$brickGetter();

                        if ($brickData) {
                            $blockGetter = $params['blockGetter'];
                            $data = $brickData->$blockGetter();

                            return $data;
                        }
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @see Data::getVersionPreview
     *
     * @param mixed $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return string
     */
    public function getVersionPreview(mixed $data, DataObject\Concrete $object = null, array $params = []): string
    {
        return $this->getDiffVersionPreview($data, $object, $params)['html'];
    }

    /**
     * {@inheritdoc}
     */
    public function getForCsvExport(DataObject\Localizedfield|DataObject\Fieldcollection\Data\AbstractData|DataObject\Objectbrick\Data\AbstractData|DataObject\Concrete $object, array $params = []): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function isDiffChangeAllowed(Concrete $object, array $params = []): bool
    {
        return true;
    }

    /** Generates a pretty version preview (similar to getVersionPreview) can be either HTML or
     * a image URL. See the https://github.com/pimcore/object-merger bundle documentation for details
     *
     * @param array|null $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return array
     */
    public function getDiffVersionPreview(?array $data, Concrete $object = null, array $params = []): array
    {
        $html = '';
        if (is_array($data)) {
            $html = '<table>';

            foreach ($data as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $html .= '<tr><th><b>'.$index.'</b></th><th>&nbsp;</th><th>&nbsp;</th></tr>';

                foreach ($this->getFieldDefinitions() as $fieldDefinition) {
                    $title = !empty($fieldDefinition->title) ? $fieldDefinition->title : $fieldDefinition->getName();
                    $html .= '<tr><td>&nbsp;</td><td>'.$title.'</td><td>';

                    $blockElement = $item[$fieldDefinition->getName()];
                    if ($blockElement instanceof DataObject\Data\BlockElement) {
                        $html .= $fieldDefinition->getVersionPreview($blockElement->getData(), $object, $params);
                    } else {
                        $html .= 'invalid data';
                    }
                    $html .= '</td></tr>';
                }
            }

            $html .= '</table>';
        }

        $value = [];
        $value['html'] = $html;
        $value['type'] = 'html';

        return $value;
    }

    /**
     * @param Model\DataObject\ClassDefinition\Data\Block $masterDefinition
     */
    public function synchronizeWithMasterDefinition(Model\DataObject\ClassDefinition\Data $masterDefinition)
    {
        $this->disallowAddRemove = $masterDefinition->disallowAddRemove;
        $this->disallowReorder = $masterDefinition->disallowReorder;
        $this->collapsible = $masterDefinition->collapsible;
        $this->collapsed = $masterDefinition->collapsed;
    }

    /**
     * @param mixed $data
     *
     * @return bool
     */
    public function isEmpty(mixed $data): bool
    {
        return is_null($data) || count($data) === 0;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): static
    {
        $this->children = $children;
        $this->fieldDefinitionsCache = null;

        return $this;
    }

    public function hasChildren(): bool
    {
        if (is_array($this->children) && count($this->children) > 0) {
            return true;
        }

        return false;
    }

    /**
     * typehint "mixed" is required for asset-metadata-definitions bundle
     * since it doesn't extend Core Data Types
     *
     * @param Data|Layout $child
     */
    public function addChild(mixed $child)
    {
        $this->children[] = $child;
        $this->fieldDefinitionsCache = null;
    }

    public function setLayout(?array $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function getLayout(): ?array
    {
        return $this->layout;
    }

    /**
     * @param mixed $def
     * @param array $fields
     *
     * @return array
     */
    public function doGetFieldDefinitions(mixed $def = null, array $fields = []): array
    {
        if ($def === null) {
            $def = $this->getChildren();
        }

        if (is_array($def)) {
            foreach ($def as $child) {
                $fields = array_merge($fields, $this->doGetFieldDefinitions($child, $fields));
            }
        }

        if ($def instanceof DataObject\ClassDefinition\Layout) {
            if ($def->hasChildren()) {
                foreach ($def->getChildren() as $child) {
                    $fields = array_merge($fields, $this->doGetFieldDefinitions($child, $fields));
                }
            }
        }

        if ($def instanceof DataObject\ClassDefinition\Data) {
            $existing = $fields[$def->getName()] ?? false;
            if ($existing && method_exists($existing, 'addReferencedField')) {
                // this is especially for localized fields which get aggregated here into one field definition
                // in the case that there are more than one localized fields in the class definition
                // see also pimcore.object.edit.addToDataFields();
                $existing->addReferencedField($def);
            } else {
                $fields[$def->getName()] = $def;
            }
        }

        return $fields;
    }

    /**
     * @param array $context additional contextual data
     *
     * @return DataObject\ClassDefinition\Data[]|null
     */
    public function getFieldDefinitions(array $context = []): ?array
    {
        if (empty($this->fieldDefinitionsCache)) {
            $definitions = $this->doGetFieldDefinitions();
            foreach ($this->getReferencedFields() as $rf) {
                if ($rf instanceof DataObject\ClassDefinition\Data\Localizedfields) {
                    $definitions = array_merge($definitions, $this->doGetFieldDefinitions($rf->getChildren()));
                }
            }

            $this->fieldDefinitionsCache = $definitions;
        }

        if (!\Pimcore::inAdmin() || (isset($context['suppressEnrichment']) && $context['suppressEnrichment'])) {
            return $this->fieldDefinitionsCache;
        }

        $enrichedFieldDefinitions = [];
        if (is_array($this->fieldDefinitionsCache)) {
            foreach ($this->fieldDefinitionsCache as $key => $fieldDefinition) {
                $fieldDefinition = $this->doEnrichFieldDefinition($fieldDefinition, $context);
                $enrichedFieldDefinitions[$key] = $fieldDefinition;
            }
        }

        return $enrichedFieldDefinitions;
    }

    /**
     * @param string $name
     * @param array $context additional contextual data
     *
     * @return DataObject\ClassDefinition\Data|null
     */
    public function getFieldDefinition(string $name, array $context = []): ?Data
    {
        $fds = $this->getFieldDefinitions();
        if (isset($fds[$name])) {
            if (!\Pimcore::inAdmin() || (isset($context['suppressEnrichment']) && $context['suppressEnrichment'])) {
                return $fds[$name];
            }
            $fieldDefinition = $this->doEnrichFieldDefinition($fds[$name], $context);

            return $fieldDefinition;
        }

        return null;
    }

    protected function doEnrichFieldDefinition(Data $fieldDefinition, array $context = []): Data
    {
        if ($fieldDefinition instanceof FieldDefinitionEnrichmentInterface) {
            $context['containerType'] = 'block';
            $context['containerKey'] = $this->getName();
            $fieldDefinition = $fieldDefinition->enrichFieldDefinition($context);
        }

        return $fieldDefinition;
    }

    public function setReferencedFields(array $referencedFields)
    {
        $this->referencedFields = $referencedFields;
        $this->fieldDefinitionsCache = null;
    }

    /**
     * @return Data[]
     */
    public function getReferencedFields(): array
    {
        return $this->referencedFields;
    }

    public function addReferencedField(Data $field)
    {
        $this->referencedFields[] = $field;
        $this->fieldDefinitionsCache = null;
    }

    public function getBlockedVarsForExport(): array
    {
        return [
            'fieldDefinitionsCache',
            'referencedFields',
            'blockedVarsForExport',
            'childs',         //TODO remove in Pimcore 12
        ];
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        $vars = get_object_vars($this);
        $blockedVars = $this->getBlockedVarsForExport();

        foreach ($blockedVars as $blockedVar) {
            unset($vars[$blockedVar]);
        }

        return array_keys($vars);
    }

    public function resolveDependencies(mixed $data): array
    {
        $dependencies = [];

        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $blockElements) {
            foreach ($blockElements as $elementName => $blockElement) {
                $fd = $this->getFieldDefinition($elementName);
                if (!$fd) {
                    // class definition seems to have changed
                    Logger::warn('class definition seems to have changed, element name: ' . $elementName);

                    continue;
                }
                $elementData = $blockElement->getData();

                $dependencies = array_merge($dependencies, $fd->resolveDependencies($elementData));
            }
        }

        return $dependencies;
    }

    public function getCacheTags(mixed $data, array $tags = []): array
    {
        if ($this->getLazyLoading()) {
            return $tags;
        }

        if (!is_array($data)) {
            return $tags;
        }

        foreach ($data as $blockElements) {
            foreach ($blockElements as $elementName => $blockElement) {
                $fd = $this->getFieldDefinition($elementName);
                if (!$fd) {
                    // class definition seems to have changed
                    Logger::warn('class definition seems to have changed, element name: ' . $elementName);

                    continue;
                }
                $data = $blockElement->getData();

                $tags = $fd->getCacheTags($data, $tags);
            }
        }

        return $tags;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    public function setCollapsed(bool $collapsed)
    {
        $this->collapsed = (bool) $collapsed;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function setCollapsible(bool $collapsible)
    {
        $this->collapsible = (bool) $collapsible;
    }

    public function getStyleElement(): string
    {
        return $this->styleElement;
    }

    /**
     * @return $this
     */
    public function setStyleElement(string $styleElement): static
    {
        $this->styleElement = $styleElement;

        return $this;
    }

    public function getLazyLoading(): bool
    {
        return $this->lazyLoading;
    }

    /**
     * @return $this
     */
    public function setLazyLoading(bool $lazyLoading): static
    {
        $this->lazyLoading = (bool) $lazyLoading;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function preSetData(mixed $container, mixed $data, array $params = []): mixed
    {
        $this->markLazyloadedFieldAsLoaded($container);

        $lf = $this->getFieldDefinition('localizedfields');
        if ($lf && is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    foreach ($item as $itemElement) {
                        if ($itemElement->getType() === 'localizedfields') {
                            /** @var DataObject\Localizedfield $itemElementData */
                            $itemElementData = $itemElement->getData();
                            $itemElementData->setObject($container);

                            // the localized field needs at least the containerType as this is important
                            // for lazy loading
                            $context = $itemElementData->getContext() ? $itemElementData->getContext() : [];
                            $context['containerType'] = 'block';
                            $context['containerKey'] = $this->getName();
                            $itemElementData->setContext($context);
                        }
                    }
                }
            }
        }

        return $data;
    }

    public function save(Localizedfield|AbstractData|\Pimcore\Model\DataObject\Objectbrick\Data\AbstractData|Concrete $object, array $params = []): void
    {
    }

    public function load(Localizedfield|AbstractData|\Pimcore\Model\DataObject\Objectbrick\Data\AbstractData|Concrete $object, array $params = []): mixed
    {
        $field = $this->getName();
        $db = Db::get();
        $data = null;

        if ($object instanceof DataObject\Concrete) {
            $query = 'select ' . $db->quoteIdentifier($field) . ' from object_store_' . $object->getClassId() . ' where oo_id  = ' . $object->getId();
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $object, $params);
        } elseif ($object instanceof DataObject\Localizedfield) {
            $context = $params['context'];
            $object = $context['object'];
            $containerType = $context['containerType'] ?? null;

            if ($containerType === 'fieldcollection') {
                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_collection_' . $context['containerKey'] . '_localized_' . $object->getClassId() . ' where language = ' . $db->quote($params['language']) . ' and  ooo_id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($context['fieldname']) . ' and `index` =  ' . $context['index'];
            } elseif ($containerType === 'objectbrick') {
                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_brick_localized_' . $context['containerKey'] . '_' . $object->getClassId() . ' where language = ' . $db->quote($params['language']) . ' and  ooo_id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($context['fieldname']);
            } else {
                $query = 'select ' . $db->quoteIdentifier($field) . ' from object_localized_data_' . $object->getClassId() . ' where language = ' . $db->quote($params['language']) . ' and  ooo_id  = ' . $object->getId();
            }
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $object, $params);
        } elseif ($object instanceof DataObject\Objectbrick\Data\AbstractData) {
            $context = $params['context'];

            $object = $context['object'];
            $brickType = $context['containerKey'];
            $brickField = $context['brickField'];
            $fieldname = $context['fieldname'];
            $query = 'select ' . $db->quoteIdentifier($brickField) . ' from object_brick_store_' . $brickType . '_' . $object->getClassId()
                . ' where  id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($fieldname);
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $object, $params);
        } elseif ($object instanceof DataObject\Fieldcollection\Data\AbstractData) {
            $context = $params['context'];
            $collectionType = $context['containerKey'];
            $object = $context['object'];
            $fcField = $context['fieldname'];

            //TODO index!!!!!!!!!!!!!!

            $query = 'select ' . $db->quoteIdentifier($field) . ' from object_collection_' . $collectionType . '_' . $object->getClassId()
                . ' where  id  = ' . $object->getId() . ' and fieldname = ' . $db->quote($fcField) . ' and `index` = ' . $context['index'];
            $data = $db->fetchOne($query);
            $data = $this->getDataFromResource($data, $object, $params);
        }

        return $data;
    }

    public function delete(Localizedfield|AbstractData|\Pimcore\Model\DataObject\Objectbrick\Data\AbstractData|Concrete $object, array $params = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    public function preGetData(mixed $container, array $params = []): mixed
    {
        $data = null;
        $params['owner'] = $container;
        $params['fieldname'] = $this->getName();
        if ($container instanceof DataObject\Concrete) {
            $data = $container->getObjectVar($this->getName());
            if ($this->getLazyLoading() && !$container->isLazyKeyLoaded($this->getName())) {
                $data = $this->load($container, $params);

                $setter = 'set' . ucfirst($this->getName());
                if (method_exists($container, $setter)) {
                    $container->$setter($data);
                    $this->markLazyloadedFieldAsLoaded($container);
                }
            }
        } elseif ($container instanceof DataObject\Localizedfield) {
            $data = $params['data'];
        } elseif ($container instanceof DataObject\Fieldcollection\Data\AbstractData) {
            $data = $container->getObjectVar($this->getName());
        } elseif ($container instanceof DataObject\Objectbrick\Data\AbstractData) {
            $data = $container->getObjectVar($this->getName());
        }

        return is_array($data) ? $data : [];
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function setMaxItems(?int $maxItems)
    {
        $this->maxItems = $this->getAsIntegerCast($maxItems);
    }

    public function isDisallowAddRemove(): bool
    {
        return $this->disallowAddRemove;
    }

    public function setDisallowAddRemove(bool $disallowAddRemove)
    {
        $this->disallowAddRemove = (bool) $disallowAddRemove;
    }

    public function isDisallowReorder(): bool
    {
        return $this->disallowReorder;
    }

    public function setDisallowReorder(bool $disallowReorder)
    {
        $this->disallowReorder = (bool) $disallowReorder;
    }

    /**
     * {@inheritdoc}
     */
    public function checkValidity(mixed $data, bool $omitMandatoryCheck = false, array $params = [])
    {
        if (!$omitMandatoryCheck) {
            if (is_array($data)) {
                $blockDefinitions = $this->getFieldDefinitions();

                $validationExceptions = [];

                $idx = -1;
                foreach ($data as $item) {
                    $idx++;
                    if (!is_array($item)) {
                        continue;
                    }

                    foreach ($blockDefinitions as $fd) {
                        try {
                            $blockElement = $item[$fd->getName()] ?? null;
                            if (!$blockElement) {
                                if ($fd->getMandatory()) {
                                    throw new Element\ValidationException('Block element empty [ ' . $fd->getName() . ' ]');
                                } else {
                                    continue;
                                }
                            }

                            $data = $blockElement->getData();

                            if ($data instanceof DataObject\Localizedfield && $fd instanceof Localizedfields) {
                                foreach ($data->getInternalData() as $language => $fields) {
                                    foreach ($fields as $fieldName => $values) {
                                        $lfd = $fd->getFieldDefinition($fieldName);
                                        if ($lfd instanceof ManyToManyRelation || $lfd instanceof ManyToManyObjectRelation) {
                                            if (!method_exists($lfd, 'getAllowMultipleAssignments') || !$lfd->getAllowMultipleAssignments()) {
                                                $lfd->performMultipleAssignmentCheck($values);
                                            }
                                        }
                                    }
                                }
                            } elseif ($fd instanceof ManyToManyRelation || $fd instanceof ManyToManyObjectRelation) {
                                $fd->performMultipleAssignmentCheck($data);
                            }

                            $fd->checkValidity($data, false, $params);
                        } catch (Model\Element\ValidationException $ve) {
                            $ve->addContext($this->getName() . '-' . $idx);
                            $validationExceptions[] = $ve;
                        }
                    }
                }

                if ($validationExceptions) {
                    $errors = [];
                    /** @var Element\ValidationException $e */
                    foreach ($validationExceptions as $e) {
                        $errors[] = $e->getAggregatedMessage();
                    }
                    $message = implode(' / ', $errors);

                    throw new Model\Element\ValidationException($message);
                }
            }
        }
    }

    /**
     * This method is called in DataObject\ClassDefinition::save()
     *
     * @param DataObject\ClassDefinition $class
     * @param array $params
     */
    public function classSaved(DataObject\ClassDefinition $class, array $params = [])
    {
        $blockDefinitions = $this->getFieldDefinitions();

        if (is_array($blockDefinitions)) {
            foreach ($blockDefinitions as $field) {
                if ($field instanceof LazyLoadingSupportInterface && $field->getLazyLoading()) {
                    // Lazy loading inside blocks isn't supported, turn it off if possible
                    if (method_exists($field, 'setLazyLoading')) {
                        $field->setLazyLoading(false);
                    }
                }
            }
        }
    }

    public function getParameterTypeDeclaration(): ?string
    {
        return '?array';
    }

    public function getReturnTypeDeclaration(): ?string
    {
        return '?array';
    }

    private function setBlockElementOwner(DataObject\Data\BlockElement $blockElement, $params = []): void
    {
        if (!isset($params['owner'])) {
            throw new \Error('owner missing');
        } else {
            // addition check. if owner is passed but no fieldname then there is something wrong with the params.
            if (!array_key_exists('fieldname', $params)) {
                // do not throw an exception because it is silently swallowed by the caller
                throw new \Error('params contains owner but no fieldname');
            }

            if ($params['owner'] instanceof DataObject\Localizedfield) {
                //make sure that for a localized field parent the language param is set and not empty
                if (($params['language'] ?? null) === null) {
                    throw new \Error('language param missing');
                }
            }
            $blockElement->_setOwner($params['owner']);
            $blockElement->_setOwnerFieldname($params['fieldname']);
            $blockElement->_setOwnerLanguage($params['language'] ?? null);
        }
    }

    public function getPhpdocInputType(): ?string
    {
        return '\\' . DataObject\Data\BlockElement::class . '[][]';
    }

    public function getPhpdocReturnType(): ?string
    {
        return '\\' .DataObject\Data\BlockElement::class . '[][]';
    }

    public function normalize(mixed $value, array $params = []): ?array
    {
        $result = null;
        if ($value) {
            $result = [];
            $fieldDefinitions = $this->getFieldDefinitions();
            foreach ($value as $block) {
                $resultItem = [];
                /**
                 * @var  string $key
                 * @var  DataObject\Data\BlockElement $fieldValue
                 */
                foreach ($block as $key => $fieldValue) {
                    $fd = $fieldDefinitions[$key];

                    if ($fd instanceof NormalizerInterface) {
                        $normalizedData = $fd->normalize($fieldValue->getData(), [
                            'object' => $params['object'] ?? null,
                            'fieldDefinition' => $fd,
                        ]);
                        $resultItem[$key] = $normalizedData;
                    } else {
                        throw new \Exception('data type ' . $fd->getFieldtype() . ' does not implement normalizer interface');
                    }
                }
                $result[] = $resultItem;
            }
        }

        return $result;
    }

    public function denormalize(mixed $value, array $params = []): ?array
    {
        if (is_array($value)) {
            $result = [];
            $fieldDefinitions = $this->getFieldDefinitions();

            foreach ($value as $idx => $blockItem) {
                $resultItem = [];
                /**
                 * @var  string $key
                 * @var  DataObject\Data\BlockElement $fieldValue
                 */
                foreach ($blockItem as $key => $fieldValue) {
                    $fd = $fieldDefinitions[$key];

                    if ($fd instanceof NormalizerInterface) {
                        $denormalizedData = $fd->denormalize($fieldValue, [
                            'object' => $params['object'],
                            'fieldDefinition' => $fd,
                        ]);
                        $resultItem[$key] = $denormalizedData;
                    } else {
                        throw new \Exception('data type does not implement normalizer interface');
                    }
                }
                $result[] = $resultItem;
            }

            return $result;
        }

        return null;
    }

    /**
     * @param array $data
     *
     * @return static
     */
    public static function __set_state($data)
    {
        $obj = new static();
        $obj->setValues($data);

        return $obj;
    }
}
