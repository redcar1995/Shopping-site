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

namespace Pimcore\Model\DataObject\ImportColumnConfig;


use Pimcore\Model\ImportConfig;

class Service
{
    /**
     * @param $outputDataConfig
     *
     * @return ConfigElementInterface[]
     */
    public function buildInputDataConfig($outputDataConfig, $context = null)
    {
        $config = [];
        $config = $this->doBuildConfig($outputDataConfig, $config, $context);

        return $config;
    }

    /**
     * @param $jsonConfig
     * @param $config
     * @param null $context
     *
     * @return array
     */
    private function doBuildConfig($jsonConfig, $config, $context = null)
    {
        if (!empty($jsonConfig)) {
            foreach ($jsonConfig as $configElement) {
                if ($configElement->type == 'value') {
                    $name = 'Pimcore\\Model\\DataObject\\ImportColumnConfig\\Value\\' . ucfirst($configElement->class);

                    if (class_exists($name)) {
                        $config[] = new $name($configElement, $context);
                    }
                } elseif ($configElement->type == 'operator') {
                    $name = 'Pimcore\\Model\\DataObject\\ImportColumnConfig\\Operator\\' . ucfirst($configElement->class);

                    if (!empty($configElement->childs)) {
                        $configElement->childs = $this->doBuildConfig($configElement->childs, [], $context);
                    }

                    if (class_exists($name)) {
                        $operatorInstance = new $name($configElement, $context);
//                        if ($operatorInstance instanceof PHPCode) {
//                            $operatorInstance = $operatorInstance->getRealInstance();
//                        }
                        if ($operatorInstance) {
                            $config[] = $operatorInstance;
                        }
                    }
                }
            }
        }

        return $config;
    }

    /**
     * @param $userId
     * @param $classId
     *
     * @return mixed
     */
    public function getMyOwnImportConfigs($userId, $classId)
    {
            $configListingConditionParts = [];
        $configListingConditionParts[] = 'ownerId = ' . $userId;
        $configListingConditionParts[] = 'classId = ' . $classId;
        $configCondition = implode(' AND ', $configListingConditionParts);
        $configListing = new ImportConfig\Listing();
        $configListing->setOrderKey('name');
        $configListing->setOrder('ASC');
        $configListing->setCondition($configCondition);
        $configListing = $configListing->load();

        $result = [];
        if ($configListing) {
            /** @var $item ImportConfig */
            foreach ($configListing as $item) {
                $result[] = [
                    'id' => $item->getId(),
                    'name' => $item->getName()
                ];
            }
        }

        return $result;
    }

}
