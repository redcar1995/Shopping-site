<?php

namespace Pimcore\Bundle\CoreBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Migrations\Migration\AbstractPimcoreMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180912140913 extends AbstractPimcoreMigration
{

    public function doesSqlMigrations(): bool
    {
        return false;
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {

        $list = new ClassDefinition\Listing();
        $list = $list->load();

        foreach ($list as $class) {
            $class->save(false);
        }

        $list = new Definition\Listing();
        $list = $list->load();
        foreach ($list as $brickDefinition) {
            $brickDefinition->save();
        }

        $list = new \Pimcore\Model\DataObject\Fieldcollection\Definition\Listing();
        $list = $list->load();
        foreach ($list as $fc) {
            $fc->save();
        }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {

    }
}
