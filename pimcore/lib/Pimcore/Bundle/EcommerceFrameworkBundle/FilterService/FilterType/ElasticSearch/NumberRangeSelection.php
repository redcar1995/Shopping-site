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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\ElasticSearch;

use Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\AbstractFilterType;
use Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\ProductList\IProductList;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractFilterDefinitionType;

class NumberRangeSelection extends \Pimcore\Bundle\EcommerceFrameworkBundle\FilterService\FilterType\NumberRangeSelection
{
    public function prepareGroupByValues(AbstractFilterDefinitionType $filterDefinition, IProductList $productList)
    {
        $productList->prepareGroupByValues($this->getField($filterDefinition), true);
    }

    public function addCondition(AbstractFilterDefinitionType $filterDefinition, IProductList $productList, $currentFilter, $params, $isPrecondition = false)
    {
        $field = $filterDefinition->getField();
        $rawValue = $params[$field];

        if (!empty($rawValue) && $rawValue != AbstractFilterType::EMPTY_STRING) {
            $values = explode('-', $rawValue);
            $value['from'] = trim($values[0]);
            $value['to'] = trim($values[1]);
        } elseif ($rawValue == AbstractFilterType::EMPTY_STRING) {
            $value = null;
        } else {
            $value['from'] = $filterDefinition->getPreSelectFrom();
            $value['to'] = $filterDefinition->getPreSelectTo();
        }

        $currentFilter[$field] = $value;

        if (!empty($value)) {
            $range = [];
            if (strlen($value['from']) > 0) {
                $range['gte'] = $value['from'];
            }
            if (strlen($value['to']) > 0) {
                $range['lte'] = $value['to'];
            }
            $productList->addCondition(['range' => ['attributes.' . $field => $range]], $field);
        }

        return $currentFilter;
    }
}
