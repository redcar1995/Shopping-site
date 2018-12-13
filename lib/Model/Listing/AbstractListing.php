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

namespace Pimcore\Model\Listing;

use Pimcore\Db;
use Pimcore\Model\AbstractModel;

/**
 * Class AbstractListing
 *
 * @package Pimcore\Model\Listing
 *
 * @method \Pimcore\Db\ZendCompatibility\QueryBuilder getQuery()
 */
abstract class AbstractListing extends AbstractModel
{
    /**
     * @var string|array
     */
    protected $order;

    /**
     * @var string|array
     */
    protected $orderKey;

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @var string
     */
    protected $condition;

    /**
     * @var array
     */
    protected $conditionVariables = [];

    /**
     * @var array
     */
    protected $conditionVariablesFromSetCondition;

    /**
     * @var string
     */
    protected $groupBy;

    /**
     * @var array
     */
    protected $validOrders = [
        'ASC',
        'DESC'
    ];

    /**
     * @var array
     */
    protected $conditionParams = [];

    /**
     * @param  $key
     *
     * @return bool
     */
    public function isValidOrderKey($key)
    {
        return true;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @return array|string
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param  $limit
     *
     * @return $this
     */
    public function setLimit($limit)
    {
        if (intval($limit) > 0) {
            $this->limit = intval($limit);
        }

        return $this;
    }

    /**
     * @param  $offset
     *
     * @return $this
     */
    public function setOffset($offset)
    {
        if (intval($offset) > 0) {
            $this->offset = intval($offset);
        }

        return $this;
    }

    /**
     * @param  $order
     *
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = [];

        if (is_string($order) && !empty($order)) {
            $order = strtoupper($order);
            if (in_array($order, $this->validOrders)) {
                $this->order[] = $order;
            }
        } elseif (is_array($order) && !empty($order)) {
            $this->order = [];
            foreach ($order as $o) {
                $o = strtoupper($o);
                if (in_array($o, $this->validOrders)) {
                    $this->order[] = $o;
                }
            }
        }

        return $this;
    }

    /**
     * @return array|string
     */
    public function getOrderKey()
    {
        return $this->orderKey;
    }

    /**
     * @param string|array $orderKey
     * @param bool $quote
     *
     * @return $this
     */
    public function setOrderKey($orderKey, $quote = true)
    {
        $this->orderKey = [];

        if (is_string($orderKey) && !empty($orderKey)) {
            $orderKey = [$orderKey];
        }

        if (is_array($orderKey)) {
            foreach ($orderKey as $o) {
                if ($quote === false) {
                    $this->orderKey[] = $o;
                } elseif ($this->isValidOrderKey($o)) {
                    $this->orderKey[] = '`' . $o . '`';
                }
            }
        }

        return $this;
    }

    /**
     * @param $key
     * @param null $value
     * @param string $concatenator
     *
     * @return $this
     */
    public function addConditionParam($key, $value = null, $concatenator = 'AND')
    {
        $key = '('.$key.')';
        $ignore = true;
        if (strpos($key, '?') !== false || strpos($key, ':') !== false) {
            $ignore = false;
        }
        $this->conditionParams[$key] = [
            'value' => $value,
            'concatenator' => $concatenator,
            'ignore-value' => $ignore, // If there is not a placeholder, ignore value!
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getConditionParams()
    {
        return $this->conditionParams;
    }

    /**
     * @return $this
     */
    public function resetConditionParams()
    {
        $this->conditionParams = [];

        return $this;
    }

    /**
     * @return string
     */
    public function getCondition()
    {
        $conditionString = '';
        $conditionParams = $this->getConditionParams();
        $db = \Pimcore\Db::get();

        $params = [];
        if (!empty($conditionParams)) {
            $i = 0;
            foreach ($conditionParams as $key => $value) {
                if (!$this->condition && $i == 0) {
                    $conditionString .= $key . ' ';
                } else {
                    $conditionString .= ' ' . $value['concatenator'] . ' ' . $key . ' ';
                }

                // If there is not a placeholder, ignore value!
                if (!$value['ignore-value']) {
                    if (is_array($value['value'])) {
                        foreach ($value['value'] as $k => $v) {
                            $params[$k] = $v;
                        }
                    } else {
                        $params[] = $value['value'];
                    }
                }
                $i++;
            }
        }
        $params = array_merge((array) $this->getConditionVariablesFromSetCondition(), $params);

        $this->setConditionVariables($params);

        $condition = $this->condition . $conditionString;

        return $condition;
    }

    /**
     * @param $condition
     * @param null $conditionVariables
     *
     * @return $this
     */
    public function setCondition($condition, $conditionVariables = null)
    {
        $this->condition = $condition;

        // statement variables
        if (is_array($conditionVariables)) {
            $this->setConditionVariablesFromSetCondition($conditionVariables);
        } elseif ($conditionVariables !== null) {
            $this->setConditionVariablesFromSetCondition([$conditionVariables]);
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getGroupBy()
    {
        return $this->groupBy;
    }

    /**
     * @return array
     */
    public function getValidOrders()
    {
        return $this->validOrders;
    }

    /**
     * @param $groupBy
     * @param bool $qoute
     *
     * @return $this
     */
    public function setGroupBy($groupBy, $qoute = true)
    {
        if ($groupBy) {
            $this->groupBy = $groupBy;

            if ($qoute && strpos($groupBy, '`') !== 0) {
                $this->groupBy = '`' . $this->groupBy . '`';
            }
        }

        return $this;
    }

    /**
     * @param $validOrders
     *
     * @return $this
     */
    public function setValidOrders($validOrders)
    {
        $this->validOrders = $validOrders;

        return $this;
    }

    /**
     * @param $value
     * @param $type
     *
     * @return string
     */
    public function quote($value, $type = null)
    {
        $db = Db::get();

        return $db->quote($value, $type);
    }

    /**
     * @param $conditionVariables
     *
     * @return $this
     */
    public function setConditionVariables($conditionVariables)
    {
        $this->conditionVariables = $conditionVariables;

        return $this;
    }

    /**
     * @return array
     */
    public function getConditionVariables()
    {
        $this->getCondition();          // this will merge conditionVariablesFromSetCondition and additional params into conditionVariables
        return $this->conditionVariables;
    }

    /**
     * @param $conditionVariables
     *
     * @return $this
     */
    public function setConditionVariablesFromSetCondition($conditionVariables)
    {
        $this->conditionVariablesFromSetCondition = $conditionVariables;

        return $this;
    }

    /**
     * @return array
     */
    public function getConditionVariablesFromSetCondition()
    {
        return $this->conditionVariablesFromSetCondition;
    }
}
