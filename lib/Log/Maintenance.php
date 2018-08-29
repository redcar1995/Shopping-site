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

namespace Pimcore\Log;

use Pimcore\Config;
use Pimcore\Log\Handler\ApplicationLoggerDb;
use Pimcore\Model\Tool\TmpStore;

class Maintenance
{
    public function httpErrorLogCleanup()
    {

        // keep the history for max. 7 days (=> exactly 144h), according to the privacy policy (EU/German Law)
        // it's allowed to store the IP for 7 days for security reasons (DoS, ...)
        $limit = time() - (6 * 86400);

        $db = \Pimcore\Db::get();
        $db->deleteWhere('http_error_log', 'date < ' . $limit);
    }

    public function cleanupLogFiles()
    {
        // we don't use the RotatingFileHandler of Monolog, since rotating asynchronously is recommended + compression
        $logFiles = glob(PIMCORE_LOG_DIRECTORY . '/*.log');

        foreach ($logFiles as $log) {
            $tmpStoreTimeId = 'log-' . basename($log);
            $lastTimeItem = TmpStore::get($tmpStoreTimeId);
            if ($lastTimeItem) {
                $lastTime = $lastTimeItem->getData();
            } else {
                $lastTime = time() - 86400;
            }

            if (file_exists($log) && date('Y-m-d', $lastTime) != date('Y-m-d')) {
                // archive log (will be cleaned up by maintenance)
                $archiveFilename = preg_replace('/\.log$/', '', $log) . '-archive-' . date('Y-m-d', $lastTime) . '.log';
                rename($log, $archiveFilename);

                if ($lastTimeItem) {
                    $lastTimeItem->setData(time());
                    $lastTimeItem->update(86400 * 7);
                } else {
                    TmpStore::add($tmpStoreTimeId, time(), null, 86400 * 7);
                }
            }
        }

        // archive and cleanup logs
        $files = [];
        $logFiles = glob(PIMCORE_LOG_DIRECTORY . '/*-archive-*.log');
        if (is_array($logFiles)) {
            $files = array_merge($files, $logFiles);
        }
        $archivedLogFiles = glob(PIMCORE_LOG_DIRECTORY . '/*-archive-*.log.gz');
        if (is_array($archivedLogFiles)) {
            $files = array_merge($files, $archivedLogFiles);
        }

        if (is_array($files)) {
            foreach ($files as $file) {
                if (filemtime($file) < (time() - (86400 * 7))) { // we keep the logs for 7 days
                    unlink($file);
                } elseif (!preg_match("/\.gz$/", $file)) {
                    gzcompressfile($file);
                    unlink($file);
                }
            }
        }
    }

    public function checkErrorLogsDb()
    {
        $db = \Pimcore\Db::get();
        $conf = Config::getSystemConfig();
        $config = $conf->applicationlog;

        if ($config->mail_notification->send_log_summary) {
            $receivers = preg_split('/,|;/', $config->mail_notification->mail_receiver);

            array_walk($receivers, function (&$value) {
                $value = trim($value);
            });

            $logLevel = (int)$config->mail_notification->filter_priority;

            $query = 'SELECT * FROM '. ApplicationLoggerDb::TABLE_NAME . " WHERE maintenanceChecked IS NULL AND priority <= $logLevel order by id desc";

            $rows = $db->fetchAll($query);
            $limit = 100;
            $rowsProcessed = 0;

            $rowCount = count($rows);
            if ($rowCount) {
                while ($rowsProcessed < $rowCount) {
                    $entries = [];

                    if ($rowCount <= $limit) {
                        $entries = $rows;
                    } else {
                        for ($i = $rowsProcessed; $i < $rowCount && count($entries) < $limit; $i++) {
                            $entries[] = $rows[$i];
                        }
                    }

                    $rowsProcessed += count($entries);

                    $html = var_export($entries, true);
                    $html = "<pre>$html</pre>";
                    $mail = new \Pimcore\Mail();
                    $mail->setIgnoreDebugMode(true);
                    $mail->setBodyHtml($html);
                    $mail->addTo($receivers);
                    $mail->setSubject('Error Log ' . \Pimcore\Tool::getHostUrl());
                    $mail->send();
                }
            }
        }

        // flag them as checked, regardless if email notifications are enabled or not
        // otherwise, when activating email notifications, you'll receive all log-messages from the past and not
        // since the point when you enabled the notifications
        $db->query('UPDATE ' . ApplicationLoggerDb::TABLE_NAME . ' set maintenanceChecked = 1');
    }

    public function archiveLogEntries()
    {
        $conf = Config::getSystemConfig();
        $config = $conf->applicationlog;

        $db = \Pimcore\Db::get();

        $date = new \DateTime('now');
        $tablename = ApplicationLoggerDb::TABLE_ARCHIVE_PREFIX . '_' . $date->format('m') . '_' . $date->format('Y');

        if ($config->archive_alternative_database) {
            $tablename = $db->quoteIdentifier($config->archive_alternative_database) . '.' . $tablename;
        }

        $archive_treshold = intval($config->archive_treshold) ?: 30;

        $timestamp = time();
        $sql = ' SELECT %s FROM ' .  ApplicationLoggerDb::TABLE_NAME . ' WHERE `timestamp` < DATE_SUB(FROM_UNIXTIME(' . $timestamp . '), INTERVAL ' . $archive_treshold . ' DAY)';

        if ($db->fetchOne(sprintf($sql, 'COUNT(*)')) > 1 || true) {
            $db->query('CREATE TABLE IF NOT EXISTS ' . $tablename . " (
                       id BIGINT(20) NOT NULL,
                       `pid` INT(11) NULL DEFAULT NULL,
                       `timestamp` DATETIME NOT NULL,
                       message VARCHAR(1024),
                       `priority` ENUM('emergency','alert','critical','error','warning','notice','info','debug') DEFAULT NULL,
                       fileobject VARCHAR(1024),
                       info VARCHAR(1024),
                       component VARCHAR(255),
                       source VARCHAR(255) NULL DEFAULT NULL,
                       relatedobject BIGINT(20),
                       relatedobjecttype ENUM('object', 'document', 'asset'),
                       maintenanceChecked TINYINT(4)
                    ) ENGINE = ARCHIVE ROW_FORMAT = DEFAULT;");

            $db->query('INSERT INTO ' . $tablename . ' ' . sprintf($sql, '*'));
            $db->query('DELETE FROM ' . ApplicationLoggerDb::TABLE_NAME . ' WHERE `timestamp` < DATE_SUB(FROM_UNIXTIME(' . $timestamp . '), INTERVAL ' . $archive_treshold . ' DAY);');
        }
    }
}
