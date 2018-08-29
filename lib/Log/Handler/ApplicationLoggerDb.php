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

namespace Pimcore\Log\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Pimcore\Db;
use Pimcore\Log\ApplicationLogger;

class ApplicationLoggerDb extends AbstractProcessingHandler
{
    const TABLE_NAME = 'application_logs';
    const TABLE_ARCHIVE_PREFIX = 'application_logs_archive';

    /**
     * @var Db\Connection
     */
    private $db;

    public function __construct(Db\Connection $db, $level = 'debug', $bubble = true)
    {
        $this->db = $db;

        // Zend_Log compatibility
        $zendLoggerPsr3Mapping = ApplicationLogger::getZendLoggerPsr3Mapping();
        if (isset($zendLoggerPsr3Mapping[$level])) {
            $level = $zendLoggerPsr3Mapping[$level];
        }

        parent::__construct($level, $bubble);
    }

    /**
     * @param array $record
     */
    public function write(array $record)
    {
        $data = [
            'pid' => getmypid(),
            'priority' => strtolower($record['level_name']),
            'message' => $record['message'],
            'timestamp' => $record['datetime']->format('Y-m-d H:i:s'),
            'component' => $record['context']['component'] ?? $record['channel'],
            'fileobject' => $record['context']['fileObject'] ?? null,
            'relatedobject' => $record['context']['relatedObject'] ?? null,
            'relatedobjecttype' => $record['context']['relatedObjectType'] ?? null,
            'source' => $record['context']['source'] ?? null
        ];

        $this->db->insert(self::TABLE_NAME, $data);
    }

    /**
     * @deprecated
     *
     * @param $level
     */
    public function setFilterPriority($level)
    {
        // legacy ZF method
        $zendLoggerPsr3Mapping = ApplicationLogger::getZendLoggerPsr3Mapping();
        if (isset($zendLoggerPsr3Mapping[$level])) {
            $level = $zendLoggerPsr3Mapping[$level];
            $this->setLevel($level);
        }
    }

    /**
     * @static
     *
     * @return string[]
     */
    public static function getComponents()
    {
        $db = Db::get();

        $components = $db->fetchCol('SELECT component FROM ' . \Pimcore\Log\Handler\ApplicationLoggerDb::TABLE_NAME . ' WHERE NOT ISNULL(component) GROUP BY component;');

        return $components;
    }

    /**
     * @static
     *
     * @return string[]
     */
    public static function getPriorities()
    {
        $priorities = [];
        $priorityNames = [
            'debug' => 'DEBUG',
            'info' => 'INFO',
            'notice' => 'NOTICE',
            'warning' => 'WARN',
            'error' => 'ERR',
            'critical' => 'CRIT',
            'alert' => 'ALERT',
            'emergency' => 'EMERG'
        ];

        $db = Db::get();

        $priorityNumbers = $db->fetchCol('SELECT priority FROM ' . \Pimcore\Log\Handler\ApplicationLoggerDb::TABLE_NAME . ' WHERE NOT ISNULL(priority) GROUP BY priority;');
        foreach ($priorityNumbers as $priorityNumber) {
            $priorities[$priorityNumber] = $priorityNames[$priorityNumber];
        }

        return $priorities;
    }
}
