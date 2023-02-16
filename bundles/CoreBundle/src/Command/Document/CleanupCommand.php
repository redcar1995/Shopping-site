<?php

namespace Pimcore\Bundle\CoreBundle\Command\Document;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Pimcore\Console\AbstractCommand;
use Pimcore\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends AbstractCommand
{
    protected const STANDARD_DOCUMENT_ENUM_TYPES = [
        'page',
        'link',
        'snippet',
        'folder',
        'hardlink',
        'email',
        'newsletter'
    ];

    private const PROTECTED_DOCUMENT_TYPES = ['page','link','snippet','folder','hardlink','email','newsletter'];
    protected function configure(): void
    {
        $this
            ->setName('pimcore:documents:cleanup')
            ->setDescription('Cleans up unused document types. Removes type from enums and tables if exist')
            ->addArgument('documentTypes',
            InputArgument::IS_ARRAY,
            'Which types do you want to clean up');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $documentTypes = $input->getArgument('documentTypes');

        $filteredDocumentTypes = [];
        foreach($documentTypes as $documentType) {
            if(in_array($documentType, self::PROTECTED_DOCUMENT_TYPES)) {
                $this->output->writeln('<comment>Cannot remove protected document type: ' . $documentType. '</comment>');
                continue;
            }
            $filteredDocumentTypes[] = $documentType;
        }

        if(!empty($filteredDocumentTypes)) {
            $db = Db::get();
            $type = Connection::PARAM_STR_ARRAY;
            if (class_exists('Doctrine\\DBAL\\ArrayParameterType')) {
                $type = ArrayParameterType::STRING;
            }
            try {
                // remove all documents with certain types
                $db->executeQuery("DELETE FROM documents WHERE type IN (:types)", ['types' => $filteredDocumentTypes], ['types' => $type]);
            } catch (\Exception $ex) {
                $output->writeln('Could not delete all document types from documents table');
            }

            // getting current enums
            $enums = $this->getCurrentEnumTypes();
            $enums = array_diff($enums, $filteredDocumentTypes);

            $this->modifyEnumTypes($enums);

            // drop tables if exist
            foreach ($filteredDocumentTypes as $filteredDocumentType) {
                $tableName = 'documents_' . $filteredDocumentType;
                try {
                    $db->executeQuery('DROP TABLE IF EXISTS ' . $tableName);
                } catch (\Exception $ex) {
                    $output->writeln(sprintf('Could not drop table %s: %s', $tableName, $ex));
                }
            }
        }
        return Command::SUCCESS;
    }

    private function getCurrentEnumTypes(): array
    {
        $db = Db::get();
        try {
            $result = $db->executeQuery("SHOW COLUMNS FROM `documents` LIKE 'type'");
            $typeColumn = $result->fetchAllAssociative();
            return explode("','",preg_replace("/(enum)\('(.+?)'\)/","\\2", $typeColumn[0]['Type']));
        } catch (\Exception $ex) {
            // nothing to do here if it does not work we return the standard types
        }
        return self::STANDARD_DOCUMENT_ENUM_TYPES;
    }

    private function modifyEnumTypes(array $enums): void
    {
        $type = Connection::PARAM_STR_ARRAY;
        if(class_exists('Doctrine\\DBAL\\ArrayParameterType')) {
            $type = ArrayParameterType::STRING;
        }
        $db = Db::get();
        $db->executeQuery('ALTER TABLE documents MODIFY COLUMN `type` ENUM(:enums);', ['enums' => $enums], ['enums' => $type]);
    }
}
