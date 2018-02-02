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
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ClassesRebuildCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('pimcore:deployment:classes-rebuild')
            ->setAliases(['deployment:classes-rebuild'])
            ->setDescription('rebuilds db structure for classes, field collections and object bricks based on updated var/classes/definition_*.php files')
            ->addOption(
                'create-classes',
                'c',
                InputOption::VALUE_NONE,
                'Create missing Classes (Classes that exists in var/classes but not in the database)'
            )
        ;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $list = new ClassDefinition\Listing();
        $list->load();

        if ($output->isVerbose()) {
            $output->writeln('---------------------');
            $output->writeln('Saving all classes');
        }

        if ($input->getOption('create-classes')) {
            $objectClassesFolder = PIMCORE_CLASS_DIRECTORY ;
            $files = glob($objectClassesFolder . '/*.php');

            foreach ($files as $file) {
                $class = include $file;

                if ($class instanceof ClassDefinition) {
                    $existingClass = ClassDefinition::getByName($class->getName());

                    if ($existingClass instanceof ClassDefinition) {
                        if ($output->isVerbose()) {
                            $output->writeln($class->getName() . ' [' . $class->getId() . '] saved');
                        }

                        $existingClass->save(false);
                    } else {
                        if ($output->isVerbose()) {
                            $output->writeln($class->getName() . ' [' . $class->getId() . '] created');
                        }

                        $class->save(false);
                    }
                }
            }
        } else {
            foreach ($list->getClasses() as $class) {
                if ($output->isVerbose()) {
                    $output->writeln($class->getName() . ' [' . $class->getId() . '] saved');
                }

                $class->save(false);
            }
        }

        if ($output->isVerbose()) {
            $output->writeln('---------------------');
            $output->writeln('Saving all object bricks');
        }
        $list = new DataObject\Objectbrick\Definition\Listing();
        $list = $list->load();
        foreach ($list as $brickDefinition) {
            if ($output->isVerbose()) {
                $output->writeln($brickDefinition->getKey() . ' saved');
            }

            $brickDefinition->save();
        }

        if ($output->isVerbose()) {
            $output->writeln('---------------------');
            $output->writeln('Saving all field collections');
        }
        $list = new DataObject\Fieldcollection\Definition\Listing();
        $list = $list->load();
        foreach ($list as $fc) {
            if ($output->isVerbose()) {
                $output->writeln($fc->getKey() . ' saved');
            }

            $fc->save();
        }
    }
}
