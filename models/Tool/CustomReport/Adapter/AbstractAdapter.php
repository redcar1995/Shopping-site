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
 * @package    Pimcore
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Tool\CustomReport\Adapter;

abstract class AbstractAdapter implements CustomReportAdapterInterface
{
    protected $config;
    protected $fullConfig;

    /**
     * @param $config
     * @param null $fullConfig
     */
    public function __construct($config, $fullConfig = null)
    {
        $this->config = $config;
        $this->fullConfig = $fullConfig;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getData($filters, $sort, $dir, $offset, $limit, $fields = null, $drillDownFilters = null);

    /**
     * {@inheritdoc}
     */
    abstract public function getColumns($configuration);

    /**
     * {@inheritdoc}
     */
    abstract public function getAvailableOptions($filters, $field, $drillDownFilters);
}
