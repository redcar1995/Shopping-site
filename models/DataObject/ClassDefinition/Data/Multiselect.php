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

use Pimcore\Db\Helper;
use Pimcore\Model;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\ClassDefinition\Service;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Normalizer\NormalizerInterface;

class Multiselect extends Data implements
    ResourcePersistenceAwareInterface,
    QueryResourcePersistenceAwareInterface,
    TypeDeclarationSupportInterface,
    EqualComparisonInterface,
    VarExporterInterface,
    \JsonSerializable,
    NormalizerInterface,
    LayoutDefinitionEnrichmentInterface,
    FieldDefinitionEnrichmentInterface,
    DataContainerAwareInterface
{
    use DataObject\Traits\SimpleComparisonTrait;
    use Extension\ColumnType;
    use Extension\QueryColumnType;
    use DataObject\Traits\SimpleNormalizerTrait;
    use DataObject\ClassDefinition\DynamicOptionsProvider\SelectionProviderTrait;
    use DataObject\Traits\DataHeightTrait;
    use DataObject\Traits\DataWidthTrait;

    /**
     * Static type of this element
     *
     * @internal
     *
     * @var string
     */
    public string $fieldtype = 'multiselect';

    /**
     * Available options to select
     *
     * @internal
     *
     * @var array|null
     */
    public ?array $options = null;

    /**
     * @internal
     *
     * @var int|null
     */
    public ?int $maxItems = null;

    /**
     * @internal
     *
     * @var string|null
     */
    public ?string $renderType = null;

    /**
     * Options provider class
     *
     * @internal
     *
     * @var string|null
     */
    public ?string $optionsProviderClass = null;

    /**
     * Options provider data
     *
     * @internal
     *
     * @var string|null
     */
    public ?string $optionsProviderData = null;

    /**
     * Type for the column to query
     *
     * @internal
     *
     * @var string
     */
    public $queryColumnType = 'text';

    /**
     * Type for the column
     *
     * @internal
     *
     * @var string
     */
    public $columnType = 'text';

    /**
     * @internal
     */
    public bool $dynamicOptions = false;

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function setMaxItems(?int $maxItems): static
    {
        $this->maxItems = $this->getAsIntegerCast($maxItems);

        return $this;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function setRenderType(?string $renderType): static
    {
        $this->renderType = $renderType;

        return $this;
    }

    public function getRenderType(): ?string
    {
        return $this->renderType;
    }

    /**
     * @see ResourcePersistenceAwareInterface::getDataForResource
     *
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return string|null
     */
    public function getDataForResource(mixed $data, DataObject\Concrete $object = null, array $params = []): ?string
    {
        if (is_array($data)) {
            return implode(',', $data);
        }

        return null;
    }

    /**
     * @see ResourcePersistenceAwareInterface::getDataFromResource
     *
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array|null
     */
    public function getDataFromResource(mixed $data, DataObject\Concrete $object = null, array $params = []): ?array
    {
        if (strlen((string) $data)) {
            return explode(',', $data);
        }

        return null;
    }

    /**
     * @see QueryResourcePersistenceAwareInterface::getDataForQueryResource
     *
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return string|null
     */
    public function getDataForQueryResource(mixed $data, DataObject\Concrete $object = null, array $params = []): ?string
    {
        if (!empty($data) && is_array($data)) {
            return ','.implode(',', $data).',';
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
     * @return string|null
     */
    public function getDataForEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): ?string
    {
        if (is_array($data)) {
            return implode(',', $data);
        }

        return null;
    }

    /**
     * @param array $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array|string|null
     */
    public function getDataForGrid(?array $data, Concrete $object = null, array $params = []): array|string|null
    {
        $optionsProvider = DataObject\ClassDefinition\Helper\OptionsProviderResolver::resolveProvider(
            $this->getOptionsProviderClass(),
            DataObject\ClassDefinition\Helper\OptionsProviderResolver::MODE_MULTISELECT
        );

        if ($optionsProvider === null) {
            return $this->getDataForEditmode($data, $object, $params);
        }

        $context = $params['context'] ?? [];
        $context['object'] = $object;
        if ($object) {
            $context['class'] = $object->getClass();
        }

        $context['fieldname'] = $this->getName();
        $options = $optionsProvider->{'getOptions'}($context, $this);
        $this->setOptions($options);

        if (isset($params['purpose']) && $params['purpose'] === 'editmode') {
            $result = $data;
        } else {
            $result = ['value' => $data, 'options' => $this->getOptions()];
        }

        return $result;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return mixed
     *
     * @see Data::getDataFromEditmode
     *
     */
    public function getDataFromEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): mixed
    {
        return $data;
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
        if (is_array($data)) {
            return implode(',', array_map(function ($v) {
                return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
            }, $data));
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function checkValidity(mixed $data, bool $omitMandatoryCheck = false, array $params = [])
    {
        if (!$omitMandatoryCheck && $this->getMandatory() && empty($data)) {
            throw new Model\Element\ValidationException('Empty mandatory field [ '.$this->getName().' ]');
        }

        if (!is_array($data) && !empty($data)) {
            throw new Model\Element\ValidationException('Invalid multiselect data');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getForCsvExport(DataObject\Localizedfield|DataObject\Fieldcollection\Data\AbstractData|DataObject\Objectbrick\Data\AbstractData|DataObject\Concrete $object, array $params = []): string
    {
        $data = $this->getDataFromObjectParam($object, $params);
        if (is_array($data)) {
            return implode(',', $data);
        }

        return '';
    }

    public function getDataForSearchIndex(DataObject\Localizedfield|DataObject\Fieldcollection\Data\AbstractData|DataObject\Objectbrick\Data\AbstractData|DataObject\Concrete $object, array $params = []): string
    {
        $data = $this->getDataFromObjectParam($object, $params);
        if (is_array($data)) {
            return implode(' ', $data);
        }

        return '';
    }

    /**
     * returns sql query statement to filter according to this data types value(s)
     *
     * @param mixed $value
     * @param string $operator
     * @param array $params
     *
     * @return string
     */
    public function getFilterCondition(mixed $value, string $operator, array $params = []): string
    {
        $params['name'] = $this->name;

        return $this->getFilterConditionExt(
            $value,
            $operator,
            $params
        );
    }

    /**
     * returns sql query statement to filter according to this data types value(s)
     *
     * @param mixed $value
     * @param string $operator
     * @param array $params optional params used to change the behavior
     *
     * @return string
     */
    public function getFilterConditionExt(mixed $value, string $operator, array $params = []): string
    {
        if ($operator === '=' || $operator === 'LIKE') {
            $name = $params['name'] ? $params['name'] : $this->name;

            $db = \Pimcore\Db::get();
            $key = $db->quoteIdentifier($name);
            if (!empty($params['brickPrefix'])) {
                $key = $params['brickPrefix'].$key;
            }

            if (str_contains($name, 'cskey') && is_array($value) && !empty($value)) {
                $values = array_map(function ($val) use ($db) {
                    return $db->quote('%' .Helper::escapeLike($val). '%');
                }, $value);

                return $key . ' LIKE ' . implode(' OR ' . $key . ' LIKE ', $values);
            }

            $value = $operator === '='
                ? "'%,".$value.",%'"
                : "'%,%".$value."%,%'";

            return $key.' LIKE '.$value.' ';
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function isDiffChangeAllowed(Concrete $object, array $params = []): bool
    {
        return true;
    }

    /** Generates a pretty version preview (similar to getVersionPreview) can be either html or
     * a image URL. See the https://github.com/pimcore/object-merger bundle documentation for details
     *
     * @param array|null $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return array|string
     */
    public function getDiffVersionPreview(?array $data, Concrete $object = null, array $params = []): array|string
    {
        if ($data) {
            $map = [];
            foreach ($data as $value) {
                $map[$value] = $value;
            }

            $html = '<ul>';

            foreach ($this->options as $option) {
                if ($map[$option['value']] ?? false) {
                    $value = $option['key'];
                    $html .= '<li>' . $value . '</li>';
                }
            }

            $html .= '</ul>';

            $value = [];
            $value['html'] = $html;
            $value['type'] = 'html';

            return $value;
        } else {
            return '';
        }
    }

    /**
     * @param DataObject\ClassDefinition\Data\Multiselect $masterDefinition
     */
    public function synchronizeWithMasterDefinition(DataObject\ClassDefinition\Data $masterDefinition)
    {
        $this->maxItems = $masterDefinition->maxItems;
        $this->options = $masterDefinition->options;
    }

    public function getOptionsProviderClass(): ?string
    {
        return $this->optionsProviderClass;
    }

    public function setOptionsProviderClass(?string $optionsProviderClass)
    {
        $this->optionsProviderClass = $optionsProviderClass;
    }

    public function getOptionsProviderData(): ?string
    {
        return $this->optionsProviderData;
    }

    public function setOptionsProviderData(?string $optionsProviderData)
    {
        $this->optionsProviderData = $optionsProviderData;
    }

    public function appendData(?array $existingData, array $additionalData): array
    {
        if (!is_array($existingData)) {
            $existingData = [];
        }

        $existingData = array_unique(array_merge($existingData, $additionalData));

        return $existingData;
    }

    public function removeData(mixed $existingData, mixed $removeData): array
    {
        if (!is_array($existingData)) {
            $existingData = [];
        }

        $existingData = array_unique(array_diff($existingData, $removeData));

        return $existingData;
    }

    /**
     * {@inheritdoc}
     */
    public function isFilterable(): bool
    {
        return true;
    }

    public function isEqual(mixed $oldValue, mixed $newValue): bool
    {
        return $this->isEqualArray($oldValue, $newValue);
    }

    public function jsonSerialize(): static
    {
        if ($this->getOptionsProviderClass() && Service::doRemoveDynamicOptions()) {
            $this->options = null;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveBlockedVars(): array
    {
        $blockedVars = parent::resolveBlockedVars();

        if ($this->getOptionsProviderClass()) {
            $blockedVars[] = 'options';
        }

        return $blockedVars;
    }

    public function getParameterTypeDeclaration(): ?string
    {
        return '?array';
    }

    public function getReturnTypeDeclaration(): ?string
    {
        return '?array';
    }

    public function getPhpdocInputType(): ?string
    {
        return 'string[]|null';
    }

    public function getPhpdocReturnType(): ?string
    {
        return 'string[]|null';
    }

    /**
     * Perform sanity checks, see #5010.
     *
     * @param mixed $containerDefinition
     * @param array $params
     */
    public function preSave(mixed $containerDefinition, array $params = [])
    {
        /** @var DataObject\ClassDefinition\DynamicOptionsProvider\MultiSelectOptionsProviderInterface|null $optionsProvider */
        $optionsProvider = DataObject\ClassDefinition\Helper\OptionsProviderResolver::resolveProvider(
            $this->getOptionsProviderClass(),
            DataObject\ClassDefinition\Helper\OptionsProviderResolver::MODE_MULTISELECT
        );
        if ($optionsProvider) {
            $context = [];
            $context['fieldname'] = $this->getName();

            try {
                $options = $optionsProvider->getOptions($context, $this);
            } catch (\Throwable $e) {
                // error from getOptions => no values => no comma => no problems
                $options = null;
            }
        } else {
            $options = $this->getOptions();
        }
        if (is_array($options) && array_reduce($options, static function ($containsComma, $option) {
            return $containsComma || str_contains($option['value'], ',');
        }, false)) {
            throw new \Exception("Field {$this->getName()}: Multiselect option values may not contain commas (,) for now, see <a href='https://github.com/pimcore/pimcore/issues/5010' target='_blank'>issue #5010</a>.");
        }
    }

    public function postSave(mixed $containerDefinition, array $params = [])
    {
        // nothing to do
    }

    /**
     * {@inheritdoc}
     */
    public function enrichFieldDefinition(array $context = []): static
    {
        $this->doEnrichDefinitionDefinition(null, $this->getName(),
            'fielddefinition', DataObject\ClassDefinition\Helper\OptionsProviderResolver::MODE_MULTISELECT, $context);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function enrichLayoutDefinition(?Concrete $object, array $context = []): static
    {
        $this->doEnrichDefinitionDefinition($object, $this->getName(),
            'layout', DataObject\ClassDefinition\Helper\OptionsProviderResolver::MODE_MULTISELECT, $context);

        return $this;
    }
}
