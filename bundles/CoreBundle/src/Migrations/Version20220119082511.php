<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\CoreBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220119082511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use foreign key for delete cascade on gridconfig_favourites & gridconfig_shares';
    }

    public function up(Schema $schema): void
    {
        //disable foreign key checks
        $this->addSql('SET foreign_key_checks = 0');

        $this->addSql('ALTER TABLE `gridconfig_favourites` CHANGE `gridConfigId` `gridConfigId` int(11) NOT NULL');
        $this->addSql('ALTER TABLE `gridconfig_favourites`
            ADD INDEX `grid_config_id` (`gridConfigId`),
            ADD CONSTRAINT `fk_gridconfig_favourites_gridconfigs`
            FOREIGN KEY (`gridConfigId`)
            REFERENCES `gridconfigs` (`id`)
            ON UPDATE NO ACTION
            ON DELETE CASCADE;');

        $this->addSql('ALTER TABLE `gridconfig_shares`
            ADD INDEX `grid_config_id` (`gridConfigId`),
            ADD CONSTRAINT `fk_gridconfig_shares_gridconfigs`
            FOREIGN KEY (`gridConfigId`)
            REFERENCES `gridconfigs` (`id`)
            ON UPDATE NO ACTION
            ON DELETE CASCADE;');

        //enable foreign key checks
        $this->addSql('SET foreign_key_checks = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `gridconfig_favourites`
            DROP INDEX IF EXISTS `grid_config_id`,
            DROP FOREIGN KEY IF EXISTS `fk_gridconfig_favourites_gridconfigs`,
            CHANGE `gridConfigId` `gridConfigId` int(11) NULL;');

        $this->addSql('ALTER TABLE `gridconfig_shares`
            DROP INDEX IF EXISTS `grid_config_id`,
            DROP FOREIGN KEY IF EXISTS `fk_gridconfig_shares_gridconfigs`;');
    }
}
