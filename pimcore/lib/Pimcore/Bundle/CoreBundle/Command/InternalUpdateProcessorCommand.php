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

namespace Pimcore\Bundle\CoreBundle\Command;

use Pimcore\Console\AbstractCommand;
use Pimcore\Logger;
use Pimcore\Update;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InternalUpdateProcessorCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setHidden(true)
            ->setName('internal:update-processor')
            ->setDescription('For internal use only')
            ->addArgument('config');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // remove terminate event listeners as they break with a cleared container
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        foreach ($eventDispatcher->getListeners(ConsoleEvents::TERMINATE) as $listener) {
            $eventDispatcher->removeListener(ConsoleEvents::TERMINATE, $listener);
        }

        $status = ['success' => true];
        $config = $input->getArgument('config');

        if ($config) {
            $job = json_decode($config, true);

            if (is_array($job)) {
                if (isset($job['dry-run'])) {
                    // do not do anything here
                    Logger::info('skipped update job because it is in dry-run mode', $job);
                } elseif ($job['type'] == 'files') {
                    Update::installData($job['revision'], $job['updateScript']);
                } elseif ($job['type'] == 'clearcache') {
                    \Pimcore\Cache::clearAll();
                    \Pimcore\Update::clearSymfonyCaches();
                } elseif ($job['type'] == 'preupdate') {
                    $status = Update::executeScript($job['revision'], 'preupdate');
                } elseif ($job['type'] == 'postupdate') {
                    $status = Update::executeScript($job['revision'], 'postupdate');
                } elseif ($job['type'] == 'cleanup') {
                    Update::cleanup();
                } elseif ($job['type'] == 'composer-update') {
                    $options = [];
                    if (isset($job['no-scripts'])) {
                        $options[] = '--no-scripts';
                    }
                    $status = Update::composerUpdate($options);
                } elseif ($job['type'] == 'composer-invalidate-classmaps') {
                    $status = Update::invalidateComposerAutoloadClassmap();
                }
            }
        }

        $this->output->write(json_encode($status));

        // the exit() is necessary as we need to prevent any code running after the update which potentially relies
        // on services which don't exist anymore due to an updated container - see #2434
        exit(0);
    }
}
