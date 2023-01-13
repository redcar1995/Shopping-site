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
use Pimcore\Tool\Serialize;

class RgbaColor extends Data implements
    ResourcePersistenceAwareInterface,
    QueryResourcePersistenceAwareInterface,
    TypeDeclarationSupportInterface,
    EqualComparisonInterface,
    VarExporterInterface,
    NormalizerInterface,
    BeforeEncryptionMarshallerInterface,
    AfterDecryptionUnmarshallerInterface
{
    use DataObject\Traits\DataWidthTrait;
    use Extension\ColumnType;
    use Extension\QueryColumnType;

    /**
     * Static type of this element
     *
     * @internal
     *
     * @var string
     */
    public string $fieldtype = 'rgbaColor';

    /**
     * Type for the column to query
     *
     * @internal
     *
     * @var array
     */
    public $queryColumnType = [
        'rgb' => 'VARCHAR(6) NULL DEFAULT NULL',
        'a' => 'VARCHAR(2) NULL DEFAULT NULL',
    ];

    /**
     * Type for the column
     *
     * @internal
     *
     * @var array
     */
    public $columnType = ['rgb' => 'VARCHAR(6) NULL DEFAULT NULL',
        'a' => 'VARCHAR(2) NULL DEFAULT NULL',
    ];

    /**
     * @param mixed $data
     * @param null|Model\DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     *
     * @see ResourcePersistenceAwareInterface::getDataForResource
     *
     */
    public function getDataForResource(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        if ($data instanceof Model\DataObject\Data\RgbaColor) {
            $rgb = sprintf('%02x%02x%02x', $data->getR(), $data->getG(), $data->getB());
            $a = sprintf('%02x', $data->getA());

            return [
                $this->getName() . '__rgb' => $rgb,
                $this->getName() . '__a' => $a,
            ];
        }

        return [
            $this->getName() . '__rgb' => null,
            $this->getName() . '__a' => null,
        ];
    }

    /**
     * @param mixed $data
     * @param null|Model\DataObject\Concrete $object
     * @param array $params
     *
     * @return Model\DataObject\Data\RgbaColor|null
     *
     * @see ResourcePersistenceAwareInterface::getDataFromResource
     *
     */
    public function getDataFromResource(mixed $data, DataObject\Concrete $object = null, array $params = []): ?Model\DataObject\Data\RgbaColor
    {
        if (is_array($data) && isset($data[$this->getName() . '__rgb']) && isset($data[$this->getName() . '__a'])) {
            list($r, $g, $b) = sscanf($data[$this->getName() . '__rgb'], '%02x%02x%02x');
            $a = hexdec($data[$this->getName() . '__a']);
            $data = new Model\DataObject\Data\RgbaColor($r, $g, $b, $a);
        }

        if ($data instanceof Model\DataObject\Data\RgbaColor) {
            if (isset($params['owner'])) {
                $data->_setOwner($params['owner']);
                $data->_setOwnerFieldname($params['fieldname']);
                $data->_setOwnerLanguage($params['language'] ?? null);
            }

            return $data;
        }

        return null;
    }

    /**
     * @param mixed $data
     * @param null|Model\DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     *
     * @see QueryResourcePersistenceAwareInterface::getDataForQueryResource
     */
    public function getDataForQueryResource(mixed $data, DataObject\Concrete $object = null, array $params = []): array
    {
        return $this->getDataForResource($data, $object, $params);
    }

    /**
     * @param mixed $data
     * @param null|Model\DataObject\Concrete $object
     * @param array $params
     *
     * @return string|null
     *
     * @see Data::getDataForEditmode
     *
     */
    public function getDataForEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): ?string
    {
        if ($data instanceof  Model\DataObject\Data\RgbaColor) {
            $rgba = sprintf('#%02x%02x%02x%02x', $data->getR(), $data->getG(), $data->getB(), $data->getA());

            return $rgba;
        }

        return null;
    }

    /**
     * @param mixed $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return Model\DataObject\Data\RgbaColor|null
     *
     * @see Data::getDataFromEditmode
     */
    public function getDataFromEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): ?Model\DataObject\Data\RgbaColor
    {
        if ($data) {
            $data = trim($data, '# ');
            list($r, $g, $b, $a) = sscanf($data, '%02x%02x%02x%02x');
            $color = new Model\DataObject\Data\RgbaColor($r, $g, $b, $a);

            return $color;
        }

        return null;
    }

    /**
     * @param string|null $data
     * @param Model\DataObject\Concrete|null $object
     * @param array $params
     *
     * @return Model\DataObject\Data\RgbaColor|null
     */
    public function getDataFromGridEditor(?string $data, Concrete $object = null, array $params = []): ?Model\DataObject\Data\RgbaColor
    {
        return $this->getDataFromEditmode($data, $object, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function checkValidity(mixed $data, bool $omitMandatoryCheck = false, array $params = []): void
    {
        parent::checkValidity($data, $omitMandatoryCheck);

        if ($data instanceof Model\DataObject\Data\RgbaColor) {
            $this->checkColorComponent($data->getR());
            $this->checkColorComponent($data->getG());
            $this->checkColorComponent($data->getB());
            $this->checkColorComponent($data->getA());
        }
    }

    /**
     * @throws Model\Element\ValidationException
     */
    private function checkColorComponent(?int $color): void
    {
        if (!is_null($color)) {
            if (!($color >= 0 && $color <= 255)) {
                throw new Model\Element\ValidationException('Color component out of range');
            }
        }
    }

    /**
     * @param Model\DataObject\ClassDefinition\Data\RgbaColor $masterDefinition
     */
    public function synchronizeWithMasterDefinition(Model\DataObject\ClassDefinition\Data $masterDefinition): void
    {
        $this->width = $masterDefinition->width;
    }

    public function isEmpty(mixed $data): bool
    {
        return $data === null;
    }

    /**
     * display the quantity value field data in the grid
     *
     * @param Model\DataObject\Data\RgbaColor|null $data
     * @param Model\DataObject\Concrete|null $object
     * @param array $params
     *
     * @return string|null
     */
    public function getDataForGrid(?Model\DataObject\Data\RgbaColor $data, Concrete $object = null, array $params = []): ?string
    {
        return $this->getDataForEditmode($data, $object, $params);
    }

    /**
     * @param mixed $data
     * @param null|Model\DataObject\Concrete $object
     * @param array $params
     *
     * @return string
     */
    public function getVersionPreview(mixed $data, DataObject\Concrete $object = null, array $params = []): string
    {
        if ($data instanceof  Model\DataObject\Data\RgbaColor) {
            $value = $data->getHex(true, true);
            $result = '<div style="float: left;"><div style="float: left; margin-right: 5px; background-image: ' . ' url(/bundles/pimcoreadmin/img/ext/colorpicker/checkerboard.png);">'
                        . '<div style="background-color: ' . $value . '; width:15px; height:15px;"></div></div>' . $value . '</div>';

            return $result;
        }

        return '';
    }

    public function normalize(mixed $value, array $params = []): ?array
    {
        if ($value instanceof Model\DataObject\Data\RgbaColor) {
            return [
                'r' => $value->getR(),
                'g' => $value->getG(),
                'b' => $value->getB(),
                'a' => $value->getA(),
            ];
        }

        return null;
    }

    public function denormalize(mixed $value, array $params = []): ?Model\DataObject\Data\RgbaColor
    {
        if (is_array($value)) {
            $color = new Model\DataObject\Data\RgbaColor();
            $color->setR($value['r']);
            $color->setG($value['g']);
            $color->setB($value['b']);
            $color->setA($value['a']);

            return $color;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getForCsvExport(DataObject\Localizedfield|DataObject\Fieldcollection\Data\AbstractData|DataObject\Objectbrick\Data\AbstractData|DataObject\Concrete $object, array $params = []): string
    {
        $data = $this->getDataFromObjectParam($object, $params);

        return $this->getDataForEditmode($data, $object, $params) ?? '';
    }

    /**
     * returns sql query statement to filter according to this data types value(s)
     *
     * @param mixed $value
     * @param string $operator
     * @param array $params
     *
     * @return string
     *
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
        $db = \Pimcore\Db::get();
        $name = $params['name'] ? $params['name'] : $this->name;
        $key = 'concat(' . $db->quoteIdentifier($name  . '__rgb') .' ,'
            . $db->quoteIdentifier($name  . '__a') .')';

        if ($value === 'NULL') {
            if ($operator === '=') {
                $operator = 'IS';
            } elseif ($operator === '!=') {
                $operator = 'IS NOT';
            }
        } elseif (!is_array($value) && !is_object($value)) {
            if ($operator === 'LIKE') {
                $value = $db->quote('%' . $value . '%');
            } else {
                $value = $db->quote($value);
            }
        }

        return $key . ' ' . $operator . ' ' . $value . ' ';
    }

    /** { @inheritdoc } */
    public function marshalBeforeEncryption(mixed $value, Concrete $object = null, array $params = []): mixed
    {
        return Serialize::serialize($value);
    }

    /** { @inheritdoc } */
    public function unmarshalAfterDecryption(mixed $value, Concrete $object = null, array $params = []): mixed
    {
        return Serialize::unserialize($value);
    }

    public function isEqual(mixed $oldValue, mixed $newValue): bool
    {
        $oldValue = $oldValue instanceof Model\DataObject\Data\RgbaColor ? $oldValue->getHex(true, false) : null;
        $newValue = $newValue instanceof Model\DataObject\Data\RgbaColor ? $newValue->getHex(true, false) : null;

        return $oldValue === $newValue;
    }

    public function getParameterTypeDeclaration(): ?string
    {
        return '?\\' . Model\DataObject\Data\RgbaColor::class;
    }

    public function getReturnTypeDeclaration(): ?string
    {
        return '?\\' . Model\DataObject\Data\RgbaColor::class;
    }

    public function getPhpdocInputType(): ?string
    {
        return '\\' . Model\DataObject\Data\RgbaColor::class . '|null';
    }

    public function getPhpdocReturnType(): ?string
    {
        return '\\' . Model\DataObject\Data\RgbaColor::class . '|null';
    }
}
