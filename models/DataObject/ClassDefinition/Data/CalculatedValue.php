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
use Pimcore\Model\DataObject\Fieldcollection\Definition;
use Pimcore\Normalizer\NormalizerInterface;

class CalculatedValue extends Data implements QueryResourcePersistenceAwareInterface, TypeDeclarationSupportInterface, EqualComparisonInterface, VarExporterInterface, NormalizerInterface
{
    use DataObject\Traits\DataWidthTrait;
    use DataObject\Traits\SimpleNormalizerTrait;
    use Extension\QueryColumnType;

    /**
     * @internal
     */
    const CALCULATOR_TYPE_EXPRESSION = 'expression';

    /**
     * @internal
     */
    const CALCULATOR_TYPE_CLASS = 'class';

    /**
     * Static type of this element
     *
     * @internal
     *
     * @var string
     */
    public string $fieldtype = 'calculatedValue';

    /**
     * @internal
     *
     * @var string
     */
    public string $elementType = 'input';

    /**
     * @internal
     */
    public string $calculatorType = self::CALCULATOR_TYPE_CLASS;

    /**
     * @internal
     */
    public ?string $calculatorExpression = null;

    /**
     * @internal
     *
     * @var string
     */
    public string $calculatorClass;

    /**
     * Type for the column to query
     *
     * @internal
     *
     * @var string
     */
    public $queryColumnType = 'varchar';

    /**
     * Column length
     *
     * @internal
     *
     * @var int
     */
    public int $columnLength = 190;

    public function getElementType(): string
    {
        return $this->elementType;
    }

    /**
     * @return $this
     */
    public function setElementType(string $elementType): static
    {
        if ($elementType) {
            $this->elementType = $elementType;
        }

        return $this;
    }

    public function getColumnLength(): int
    {
        return $this->columnLength;
    }

    /**
     * @return $this
     */
    public function setColumnLength(?int $columnLength): static
    {
        if ($columnLength) {
            $this->columnLength = $columnLength;
        }

        return $this;
    }

    public function getCalculatorClass(): string
    {
        return $this->calculatorClass;
    }

    public function setCalculatorClass(string $calculatorClass)
    {
        $this->calculatorClass = $calculatorClass;
    }

    public function getCalculatorType(): string
    {
        return $this->calculatorType;
    }

    public function setCalculatorType(string $calculatorType): void
    {
        $this->calculatorType = $calculatorType;
    }

    public function getCalculatorExpression(): ?string
    {
        return $this->calculatorExpression;
    }

    public function setCalculatorExpression(?string $calculatorExpression): void
    {
        $this->calculatorExpression = $calculatorExpression;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return string|null
     *
     *@see QueryResourcePersistenceAwareInterface::getDataForQueryResource
     *
     */
    public function getDataForQueryResource(mixed $data, DataObject\Concrete $object = null, array $params = []): ?string
    {
        return $data;
    }

    /**
     * @param mixed $data
     * @param Concrete|null $object
     * @param array $params
     *
     * @return string|null
     *
     * @see Data::getDataForEditmode
     */
    public function getDataForEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): ?string
    {
        if ($data instanceof Model\DataObject\Data\CalculatedValue) {
            return Model\DataObject\Service::getCalculatedFieldValueForEditMode($object, $params, $data);
        }

        return $data;
    }

    /**
     * @param mixed $data
     * @param null|DataObject\Concrete $object
     * @param array $params
     *
     * @return null
     *
     * @see Data::getDataFromEditmode
     *
     */
    public function getDataFromEditmode(mixed $data, DataObject\Concrete $object = null, array $params = []): mixed
    {
        return null;
    }

    /**
     * @param mixed $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return string
     *
     * @see Data::getVersionPreview
     *
     */
    public function getVersionPreview(mixed $data, DataObject\Concrete $object = null, array $params = []): string
    {
        return (string)$this->getDataForEditmode($data, $object, $params);
    }

    /**
     * {@inheritdoc}
     */
    public function checkValidity(mixed $data, bool $omitMandatoryCheck = false, array $params = [])
    {
        // nothing to do
    }

    /**
     * {@inheritdoc}
     */
    public function getForCsvExport(DataObject\Localizedfield|DataObject\Fieldcollection\Data\AbstractData|DataObject\Objectbrick\Data\AbstractData|DataObject\Concrete $object, array $params = []): string
    {
        return $this->getDataFromObjectParam($object, $params) ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryColumnType(): array|string|null
    {
        return $this->queryColumnType . '(' . $this->getColumnLength() . ')';
    }

    /**
     * {@inheritdoc}
     */
    public function getGetterCode(DataObject\Objectbrick\Definition|DataObject\ClassDefinition|DataObject\Fieldcollection\Definition $class): string
    {
        $key = $this->getName();

        $code = '/**' . "\n";
        $code .= '* Get ' . str_replace(['/**', '*/', '//'], '', $this->getName()) . ' - ' . str_replace(['/**', '*/', '//'], '', $this->getTitle()) . "\n";
        $code .= '* @return ' . $this->getPhpdocReturnType() . "\n";
        $code .= '*/' . "\n";
        $code .= 'public function get' . ucfirst($key) . '(): ' . $this->getReturnTypeDeclaration() . "\n";
        $code .= '{' . "\n";

        $code .= "\t" . '$data' . " = new \\Pimcore\\Model\\DataObject\\Data\\CalculatedValue('" . $key . "');\n";
        $code .= "\t" . '$data->setContextualData("object", null, null, null);' . "\n";

        if ($class instanceof DataObject\Objectbrick\Definition) {
            $code .= "\t" . '$object = $this->getObject();'  . "\n";
        } else {
            $code .= "\t" . '$object = $this;'  . "\n";
        }

        $code .= "\t" . '$data = \\Pimcore\\Model\\DataObject\\Service::getCalculatedFieldValue($object, $data);' . "\n\n";
        $code .= "\t" . 'return $data;' . "\n";
        $code .= "}\n\n";

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getGetterCodeLocalizedfields(DataObject\Objectbrick\Definition|DataObject\ClassDefinition|DataObject\Fieldcollection\Definition $class): string
    {
        $key = $this->getName();
        $code = '/**' . "\n";
        $code .= '* Get ' . str_replace(['/**', '*/', '//'], '', $this->getName()) . ' - ' . str_replace(['/**', '*/', '//'], '', $this->getTitle()) . "\n";
        $code .= '* @return ' . $this->getPhpdocReturnType() . "\n";
        $code .= '*/' . "\n";
        $code .= 'public function get' . ucfirst($key) . '(?string $language = null): ' . $this->getReturnTypeDeclaration() . "\n";
        $code .= '{' . "\n";
        $code .= "\t" . 'if (!$language) {' . "\n";
        $code .= "\t\t" . 'try {' . "\n";
        $code .= "\t\t\t" . '$locale = \Pimcore::getContainer()->get("pimcore.locale")->getLocale();'  . "\n";
        $code .= "\t\t\t" . 'if (\Pimcore\Tool::isValidLanguage($locale)) {'  . "\n";
        $code .= "\t\t\t\t" . '$language = (string) $locale;'  . "\n";
        $code .= "\t\t\t" . '} else {'  . "\n";
        $code .= "\t\t\t\t" . 'throw new \Exception("Not supported language");'  . "\n";
        $code .= "\t\t\t" . '}'  . "\n";
        $code .= "\t\t" . '} catch (\Exception $e) {' . "\n";
        $code .= "\t\t\t" . '$language = \Pimcore\Tool::getDefaultLanguage();' . "\n";
        $code .= "\t\t" . '}' . "\n";
        $code .= "\t" . '}'  . "\n";

        if ($class instanceof DataObject\Objectbrick\Definition) {
            $ownerType = 'objectbrick';
            $index = $class->getKey();
            $ownerName = '$this->getFieldName()';

            $code .= "\t" . '$object = $this->getObject();'  . "\n";
        } else {
            $ownerType = 'localizedfield';
            $ownerName = '"localizedfields"';
            $index = null;

            $code .= "\t" . '$object = $this;'  . "\n";
        }

        if ($class instanceof DataObject\Fieldcollection\Definition) {
            $code .= "\t" . '$fieldDefinition = $this->getDefinition()->getFieldDefinition("localizedfields")->getFieldDefinition("'.$key.'");'  . "\n";
        } else {
            $code .= "\t" . '$fieldDefinition = $this->getClass()->getFieldDefinition("localizedfields")->getFieldDefinition("'.$key.'");'  . "\n";
        }

        $code .= "\t" . '$data' . " = new \\Pimcore\\Model\\DataObject\\Data\\CalculatedValue('" . $key . "');\n";
        $code .= "\t" . '$data->setContextualData("'.$ownerType.'", ' . $ownerName . ', '.($index === null ? 'null' : '"'.$index.'"').', $language, null, null, $fieldDefinition);' . "\n";

        $code .= "\t" . '$data = \\Pimcore\\Model\\DataObject\\Service::getCalculatedFieldValue($object, $data);' . "\n";
        $code .= "\treturn " . '$data' . ";\n";
        $code .= "}\n\n";

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getGetterCodeObjectbrick(\Pimcore\Model\DataObject\Objectbrick\Definition $brickClass): string
    {
        $key = $this->getName();
        $code = '';
        $code .= '/**' . "\n";
        $code .= '* Set ' . str_replace(['/**', '*/', '//'], '', $this->getName()) . ' - ' . str_replace(['/**', '*/', '//'], '', $this->getTitle()) . "\n";
        $code .= '* @return ' . $this->getPhpdocReturnType() . "\n";
        $code .= '*/' . "\n";
        $code .= 'public function get' . ucfirst($key) . '(?string $language = null): ' . $this->getReturnTypeDeclaration() . "\n";
        $code .= '{' . "\n";

        $code .= "\t" . '$brickDefinition = DataObject\Objectbrick\Definition::getByKey("' . $brickClass->getKey() . '");' . "\n";
        $code .= "\t" . '$fd = $brickDefinition->getFieldDefinition("' . $key . '");' . "\n";

        $code .= "\t" . '$data' . ' = new \\Pimcore\\Model\\DataObject\\Data\\CalculatedValue($fd->getName());' . "\n";
        $code .= "\t" . '$data->setContextualData("objectbrick", $this->getFieldName() , $this->getType(), $fd->getName(), null, null, $fd);' . "\n";

        $code .= "\t" . '$data = DataObject\Service::getCalculatedFieldValue($this->getObject(), $data);' . "\n";
        $code .= "\treturn " . '$data' . ";\n";
        $code .= "}\n\n";

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getGetterCodeFieldcollection(Definition $fieldcollectionDefinition): string
    {
        $key = $this->getName();

        $code = '';
        $code .= '/**' . "\n";
        $code .= '* Get ' . str_replace(['/**', '*/', '//'], '', $this->getName()) . ' - ' . str_replace(['/**', '*/', '//'], '', $this->getTitle()) . "\n";
        $code .= '* @return ' . $this->getPhpdocReturnType() . "\n";
        $code .= '*/' . "\n";
        $code .= 'public function get' . ucfirst($key) . '(): ' . $this->getReturnTypeDeclaration() . "\n";
        $code .= '{' . "\n";

        $code .= "\t" . '$data' . " = new \\Pimcore\\Model\\DataObject\\Data\\CalculatedValue('" . $key . "');\n";

        $code .= "\t" . '$fcDef = DataObject\Fieldcollection\Definition::getByKey($this->getType());' . "\n";
        $code .= "\t" . '$definition = $fcDef->getFieldDefinition(\'' . $this->getName() . '\');' . "\n";

        $code .= "\t" . '$data->setContextualData("fieldcollection", $this->getFieldname(), $this->getIndex(), null, null, null, $definition);' . "\n";

        $code .= "\t" . '$data = DataObject\Service::getCalculatedFieldValue($this, $data);' . "\n\n";
        $code .= "\t" . 'return $data;' . "\n";
        $code .= "}\n\n";

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getSetterCode(DataObject\Objectbrick\Definition|DataObject\ClassDefinition|DataObject\Fieldcollection\Definition $class): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getSetterCodeObjectbrick(\Pimcore\Model\DataObject\Objectbrick\Definition $brickClass): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getSetterCodeFieldcollection(Definition $fieldcollectionDefinition): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getSetterCodeLocalizedfields(DataObject\Objectbrick\Definition|DataObject\ClassDefinition|DataObject\Fieldcollection\Definition $class): string
    {
        return '';
    }

    /**
     * @param mixed $data
     * @param DataObject\Concrete|null $object
     * @param array $params
     *
     * @return mixed
     */
    public function getDataForGrid(mixed $data, DataObject\Concrete $object = null, array $params = []): mixed
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsInheritance(): bool
    {
        return false;
    }

    public function isEqual(mixed $oldValue, mixed $newValue): bool
    {
        return $oldValue === $newValue;
    }

    public function getParameterTypeDeclaration(): ?string
    {
        return '';
    }

    public function getReturnTypeDeclaration(): ?string
    {
        return 'mixed';
    }

    public function getPhpdocInputType(): ?string
    {
        return '';
    }

    public function getPhpdocReturnType(): ?string
    {
        return 'mixed';
    }
}
