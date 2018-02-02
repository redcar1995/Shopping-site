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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType;

use Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\ProductList\IProductList;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractFilterDefinitionType;

class Select extends AbstractFilterType
{
    public function getFilterFrontend(AbstractFilterDefinitionType $filterDefinition, IProductList $productList, $currentFilter)
    {
        $field = $this->getField($filterDefinition);

        return $this->render($this->getTemplate($filterDefinition), [
            'hideFilter' => $filterDefinition->getRequiredFilterField() && empty($currentFilter[$filterDefinition->getRequiredFilterField()]),
            'label' => $filterDefinition->getLabel(),
            'currentValue' => $currentFilter[$field],
            'values' => $productList->getGroupByValues($field, true),
            'fieldname' => $field,
            'metaData' => $filterDefinition->getMetaData(),
            'resultCount' => $productList->count()
        ]);
    }

    public function addCondition(AbstractFilterDefinitionType $filterDefinition, IProductList $productList, $currentFilter, $params, $isPrecondition = false)
    {
        $field = $this->getField($filterDefinition);
        $preSelect = $this->getPreSelect($filterDefinition);

        $value = $params[$field];

        if ($value == AbstractFilterType::EMPTY_STRING) {
            $value = null;
        } elseif (empty($value) && !$params['is_reload']) {
            $value = $preSelect;
        }

        $value = trim($value);
        $currentFilter[$field] = $value;

        if (!empty($value)) {
            if ($isPrecondition) {
                $productList->addCondition('TRIM(`' . $field . '`) = ' . $productList->quote($value), 'PRECONDITION_' . $field);
            } else {
                $productList->addCondition('TRIM(`' . $field . '`) = ' . $productList->quote($value), $field);
            }
        }

        return $currentFilter;
    }
}
