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

namespace Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config;

use Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Config\Definition\Attribute;
use Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\Worker\IWorker;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\IIndexable;

/**
 * Interface for IndexService Tenant Configurations
 */
interface IConfig
{
    /**
     * returns tenant name
     *
     * @return string
     */
    public function getTenantName();

    /**
     * Returns configured attributes for product index
     *
     * @return Attribute[]
     */
    public function getAttributes(): array;

    /**
     * Returns full text search index attribute names for product index
     *
     * @return array
     */
    public function getSearchAttributes(): array;

    /**
     * return all supported filter types for product index
     *
     * @return array
     */
    public function getFilterTypeConfig();

    /**
     * returns if given product is active for this tenant
     *
     * @param IIndexable $object
     *
     * @return bool
     */
    public function isActive(IIndexable $object);

    /**
     * checks, if product should be in index for current tenant
     *
     * @param IIndexable $object
     *
     * @return bool
     */
    public function inIndex(IIndexable $object);

    /**
     * Returns categories for given object in context of the current tenant.
     * Possible hook to filter categories for specific tenants.
     *
     * @param IIndexable $object
     *
     * @return \Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractCategory[]
     */
    public function getCategories(IIndexable $object);

    /**
     * in case of subtenants returns a data structure containing all sub tenants
     *
     * @param IIndexable $object
     * @param null $subObjectId
     *
     * @return mixed $subTenantData
     */
    public function prepareSubTenantEntries(IIndexable $object, $subObjectId = null);

    /**
     * populates index for tenant relations based on given data
     *
     * @param mixed $objectId
     * @param mixed $subTenantData
     * @param mixed $subObjectId
     *
     * @return void
     */
    public function updateSubTenantEntries($objectId, $subTenantData, $subObjectId = null);

    /**
     * Config <-> worker have a 1:1 relation as the config
     * needs to access its worker in certain cases.
     *
     * @param IWorker $tenantWorker
     *
     * @throws \LogicException If the config already has a worker set
     * @throws \LogicException If the config used from the worker does not match the config object the worker is
     *                         about to be set to
     */
    public function setTenantWorker(IWorker $tenantWorker);

    /**
     * creates and returns tenant worker suitable for this tenant configuration
     *
     * @return IWorker
     */
    public function getTenantWorker();

    /**
     * creates an array of sub ids for the given object
     * use that function, if one object should be indexed more than once (e.g. if field collections are in use)
     *
     * @param IIndexable $object
     *
     * @return IIndexable[]
     */
    public function createSubIdsForObject(IIndexable $object);

    /**
     * checks if there are some zombie subIds around and returns them for cleanup
     *
     * @param IIndexable $object
     * @param array $subIds
     *
     * @return mixed
     */
    public function getSubIdsToCleanup(IIndexable $object, array $subIds);

    /**
     * creates virtual parent id for given sub id
     * default is getOSParentId
     *
     * @param IIndexable $object
     * @param $subId
     *
     * @return mixed
     */
    public function createVirtualParentIdForSubId(IIndexable $object, $subId);

    /**
     * Gets object by id, can consider subIds and therefore return e.g. an array of values
     * always returns object itself - see also getObjectMockupById
     *
     * @param $objectId
     * @param $onlyMainObject - only returns main object
     *
     * @return mixed
     */
    public function getObjectById($objectId, $onlyMainObject = false);

    /**
     * Gets object mockup by id, can consider subIds and therefore return e.g. an array of values
     * always returns a object mockup if available
     *
     * @param $objectId
     *
     * @return IIndexable | array
     */
    public function getObjectMockupById($objectId);
}
