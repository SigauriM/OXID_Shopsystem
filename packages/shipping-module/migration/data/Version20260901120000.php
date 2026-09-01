<?php

declare(strict_types=1);

namespace OxidShipping\Module\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260901120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Shipping tariff versions; one active row per shop.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS `oxidshipping_tariff` (
                `OXID` CHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
                `OXSHOPID` INT NOT NULL,
                `OXVERSION` VARCHAR(64) NOT NULL,
                `OXPAYLOAD` LONGTEXT NOT NULL,
                `OXHASH` CHAR(64) NOT NULL,
                `OXACTIVEFLAG` TINYINT(1) NULL,
                `OXAUTHORID` CHAR(32) CHARACTER SET latin1 COLLATE latin1_general_ci NULL,
                `OXTIMESTAMP` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`OXID`),
                UNIQUE KEY `OXSHOPID_OXACTIVEFLAG` (`OXSHOPID`, `OXACTIVEFLAG`),
                KEY `OXSHOPID_OXHASH` (`OXSHOPID`, `OXHASH`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
    }
}
