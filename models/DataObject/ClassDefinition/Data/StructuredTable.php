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

use Pimcore\Model;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Normalizer\NormalizerInterface;

class StructuredTable extends Data implements ResourcePersistenceAwareInterface, QueryResourcePersistenceAwareInterface, TypeDeclarationSupportInterface, EqualComparisonInterface, VarExporterInterface, NormalizerInterface
{
    use DataObject\Traits\SimpleComparisonTrait;
    use Extension\ColumnType;
    use Extension\QueryColumnType;
    use Data\Extension\PositionSortTrait;

    /**
     * Static type of this element
     *
     * @var string
     */
    public string $fieldtype = 'structuredTable';

    /**
     * @internal
     *
     * @var string|int
     */
    public string|int $width = 0;

    /**
     * @internal
     *
     * @var string|int
     */
    public string|int $height = 0;

    /**
     * @internal
     *
     * @var int
     */
    public int $labelWidth = 0;

    /**
     * v
     *
     * @var string
     */
    public string $labelFirstCell;

    /**
     * @internal
     *
     * @var array
     */
    public array $cols = [];

    /**
     * @internal
     *
     * @var array
     */
    public array $rows = [];

    public function getWidth(): int|string
    {
        return $this->width;
    }

    public function setWidth(int|string $width): static
    {
        if (is_numeric($width)) {
            $width = (int)$width;
        }
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int|string
    {
        return $this->height;
    }

    public function setHeight(int|string $height): static
    {
        if (is_numeric($height)) {
            $height = (int)$height;
        }
        $this->height = $height;

        return $this;
    }

    public function getLabelWidth(): int
    {
        return $this->labelWidth;
    }

    public function setLabelWidth(int $labelWidth): static
    {
        $this->labelWidth = (int)$labelWidth;

        return $this;
    }

    public function setLabelFirstCell(string $labelFirstCell): static
    {
        $this->labelFirstCell = $labelFirstCell;

        return $this;
    }

    public function getLabelFirstCell(): string
    {
        return $this->labelFirstCell;
    }

    public function getCols(): array
    {
        return $this->cols;
    }

    public function setCols(array $cols): static
    {
        if (isset($cols['key'])) {
            $cols = [$cols];
        }
        usort($cols, [$this, 'sort']);

        $this->cols = [];

        foreach ($cols as $c) {
            $c['key'] = strtolower($c['key']);
            $this->cols[] = $c;
        }

        return $this;
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function setRows(array $rows): static
    {
        if (isset($rows['key'])) {
            $rows = [$rows];
        }

        usort($rows, [$this, 'sort']);

        $this->rows = [];

        foreach ($rows as $r) {
            $r['key'] = strtolower($r['key']);
            $this->rows[] = $r;
        }

        return $this;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     *
     * @see ResourcePersistenceAwareInterface::getDataForResource
     *
     */
    public function getDataForResource(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        $resourceData = [];
        if ($data instanceof DataObject\Data\StructuredTable) {
            $data = $data->getData();

            foreach ($this->getRows() as $r) {
                foreach ($this->getCols() as $c) {
                    $name = $r['key'] . '#' . $c['key'];
                    $resourceData[$this->getName() . '__' . $name] = $data[$r['key']][$c['key']];
                }
            }
        }

        return $resourceData;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return DataObject\Data\StructuredTable
     *
     * @see ResourcePersistenceAwareInterface::getDataFromResource
     *
     */
    public function getDataFromResource(mixed $data, Concrete $object = null, array $params = []): DataObject\Data\StructuredTable
    {
        $structuredData = [];
        foreach ($this->getRows() as $r) {
            foreach ($this->getCols() as $c) {
                $name = $r['key'] . '#' . $c['key'];
                $structuredData[$r['key']][$c['key']] = $data[$this->getName() . '__' . $name] ?? null;
            }
        }

        $structuredTable = new DataObject\Data\StructuredTable($structuredData);

        if (isset($params['owner'])) {
            $structuredTable->_setOwner($params['owner']);
            $structuredTable->_setOwnerFieldname($params['fieldname']);
            $structuredTable->_setOwnerLanguage($params['language'] ?? null);
        }

        return $structuredTable;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     *
     *@see QueryResourcePersistenceAwareInterface::getDataForQueryResource
     *
     */
    public function getDataForQueryResource(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        return $this->getDataForResource($data, $object, $params);
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     *
     * @see Data::getDataForEditmode
     *
     */
    public function getDataForEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        $editArray = [];
        if ($data instanceof DataObject\Data\StructuredTable) {
            if ($data->isEmpty()) {
                return [];
            } else {
                $data = $data->getData();
                foreach ($this->getRows() as $r) {
                    $editArrayItem = [];
                    $editArrayItem['__row_identifyer'] = $r['key'];
                    $editArrayItem['__row_label'] = $r['label'];
                    foreach ($this->getCols() as $c) {
                        $editArrayItem[$c['key']] = $data[$r['key']][$c['key']];
                    }
                    $editArray[] = $editArrayItem;
                }
            }
        }

        return $editArray;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return DataObject\Data\StructuredTable
     *
     *@see Data::getDataFromEditmode
     *
     */
    public function getDataFromEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): DataObject\Data\StructuredTable
    {
        $table = new DataObject\Data\StructuredTable();
        $tableData = [];
        foreach ($data as $dataLine) {
            foreach ($this->cols as $c) {
                $tableData[$dataLine['__row_identifyer']][$c['key']] = $dataLine[$c['key']];
            }
        }
        $table->setData($tableData);

        return $table;
    }

    /**
     * @param DataObject\Data\StructuredTable|null $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return array|null
     */
    public function getDataForGrid(?DataObject\Data\StructuredTable $data, Concrete $object = null, array $params = []): ?array
    {
        if ($data instanceof DataObject\Data\StructuredTable) {
            if (!$data->isEmpty()) {
                return $data->getData();
            }
        }

        return null;
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
        if ($data instanceof DataObject\Data\StructuredTable) {
            return $data->getHtmlTable($this->rows, $this->cols);
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function checkValidity(mixed $data, bool $omitMandatoryCheck = false, array $params = [])
    {
        if (!$omitMandatoryCheck && $this->getMandatory()) {
            $empty = true;
            if (!empty($data)) {
                $dataArray = $data->getData();
                foreach ($this->getRows() as $r) {
                    foreach ($this->getCols() as $c) {
                        if (!empty($dataArray[$r['key']][$c['key']])) {
                            $empty = false;
                        }
                    }
                }
            }
            if ($empty) {
                throw new Model\Element\ValidationException('Empty mandatory field [ '.$this->getName().' ]');
            }
        }

        if (!empty($data) && !$data instanceof DataObject\Data\StructuredTable) {
            throw new Model\Element\ValidationException('invalid table data');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getForCsvExport(DataObject\Localizedfield|DataObject\Fieldcollection\Data\AbstractData|DataObject\Objectbrick\Data\AbstractData|DataObject\Concrete $object, array $params = []): string
    {
        $value = $this->getDataFromObjectParam($object, $params);
        $string = '';

        if ($value instanceof DataObject\Data\StructuredTable) {
            $dataArray = $value->getData();
            foreach ($this->getRows() as $r) {
                foreach ($this->getCols() as $c) {
                    $string .= $dataArray[$r['key']][$c['key']] . '##';
                }
            }
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnType(): array|string|null
    {
        $columns = [];
        foreach ($this->calculateDbColumns() as $c) {
            $columns[$c->name] = $c->type;
        }

        return $columns;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryColumnType(): array|string|null
    {
        $columns = [];
        foreach ($this->calculateDbColumns() as $c) {
            $columns[$c->name] = $c->type;
        }

        return $columns;
    }

    protected function calculateDbColumns(): array
    {
        $rows = $this->getRows();
        $cols = $this->getCols();

        $dbCols = [];

        foreach ($rows as $r) {
            foreach ($cols as $c) {
                $name = $r['key'] . '#' . $c['key'];

                $col = new \stdClass();
                $col->name = $name;
                $length = 0;
                if (isset($c['length']) && $c['length']) {
                    $length = $c['length'];
                }
                $col->type = $this->typeMapper($c['type'], $length);
                $dbCols[] = $col;
            }
        }

        return $dbCols;
    }

    /**
     * @param string $type text|number|bool
     * @param int|null $length The length of the column, default is 255 for text
     *
     * @return string|null
     */
    protected function typeMapper(string $type, int $length = null): ?string
    {
        $mapper = [
            'text' => 'varchar('.($length > 0 ? $length : '190').')',
            'number' => 'double',
            'bool' => 'tinyint(1)',
        ];

        return $mapper[$type];
    }

    public function isEmpty(mixed $data): bool
    {
        if ($data instanceof DataObject\Data\StructuredTable) {
            return $data->isEmpty();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDiffChangeAllowed(Concrete $object, array $params = []): bool
    {
        return true;
    }

    /** See parent class.
     * @param mixed $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return array|null
     */
    public function getDiffDataForEditMode(mixed $data, DataObject\Concrete $object = null, array $params = []): ?array
    {
        $defaultData = parent::getDiffDataForEditMode($data, $object, $params);
        $html = $defaultData[0]['value'];
        $value = [];
        $value['html'] = $html;
        $value['type'] = 'html';
        $defaultData[0]['value'] = $value;

        return $defaultData;
    }

    /**
     * @param DataObject\ClassDefinition\Data\StructuredTable $masterDefinition
     */
    public function synchronizeWithMasterDefinition(DataObject\ClassDefinition\Data $masterDefinition)
    {
        $this->labelWidth = $masterDefinition->labelWidth;
        $this->labelFirstCell = $masterDefinition->labelFirstCell;
        $this->cols = $masterDefinition->cols;
        $this->rows = $masterDefinition->rows;
    }

    public function isEqual(mixed $oldValue, mixed $newValue): bool
    {
        $oldData = $oldValue instanceof DataObject\Data\StructuredTable ? $oldValue->getData() : [];
        $newData = $newValue instanceof DataObject\Data\StructuredTable ? $newValue->getData() : [];

        return $this->isEqualArray($oldData, $newData);
    }

    public function getParameterTypeDeclaration(): ?string
    {
        return '?\\' . DataObject\Data\StructuredTable::class;
    }

    public function getReturnTypeDeclaration(): ?string
    {
        return '?\\' . DataObject\Data\StructuredTable::class;
    }

    public function getPhpdocInputType(): ?string
    {
        return '\\' . DataObject\Data\StructuredTable::class . '|null';
    }

    public function getPhpdocReturnType(): ?string
    {
        return '\\' . DataObject\Data\StructuredTable::class . '|null';
    }

    public function normalize(mixed $value, array $params = []): ?array
    {
        if ($value instanceof DataObject\Data\StructuredTable) {
            $data = $value->getData();

            return $data;
        }

        return null;
    }

    public function denormalize(mixed $value, array $params = []): ?DataObject\Data\StructuredTable
    {
        if (is_array($value)) {
            $table = new DataObject\Data\StructuredTable();
            $table->setData($value);

            return $table;
        }

        return null;
    }
}
