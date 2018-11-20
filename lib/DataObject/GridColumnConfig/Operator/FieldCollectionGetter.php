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
 * @category   Pimcore
 * @package    Object
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\DataObject\GridColumnConfig\Operator;

use Pimcore\Model\DataObject\Fieldcollection;

class FieldCollectionGetter extends AbstractOperator
{
    private $attr;
    private $idx;
    private $colAttr;

    public function __construct(\stdClass $config, $context = null)
    {
        parent::__construct($config, $context);

        $this->attr = $config->attr;
        $this->idx = $config->idx;
        $this->colAttr = $config->colAttr;
    }

    public function getLabeledValue($element)
    {
        $result = new \stdClass();
        $result->label = $this->label;
        $result->isEmpty = true;

        if (!$this->attr) {
            return $result;
        }

        $getter = 'get' . ucfirst($this->attr);

        /** @var $fc Fieldcollection */
        $fc = $element->$getter();

        if ($fc) {
            $item = $fc->get($this->idx);
            if ($item) {
                $itemGetter = 'get' . ucfirst($this->colAttr);
                $value = $item->$itemGetter();
                $result->value = $value;
                $result->isEmpty = false;
            }
        }

        return $result;
    }
}
