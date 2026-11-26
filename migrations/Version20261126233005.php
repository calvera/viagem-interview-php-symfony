<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126233005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Úvodní schéma: obce, vlastníci, parcely, vlastnické vztahy, poznámky, access log, noční reporty (FK RESTRICT).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE access_log (id INT AUTO_INCREMENT NOT NULL, page VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL, user_name VARCHAR(60) DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE municipalities (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(6) NOT NULL, name VARCHAR(120) NOT NULL, district VARCHAR(80) NOT NULL, region VARCHAR(80) NOT NULL, INDEX idx_municipalities_name (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE owner_notes (id INT AUTO_INCREMENT NOT NULL, note LONGTEXT NOT NULL, created_by VARCHAR(60) DEFAULT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, INDEX IDX_F18293AB7E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE owners (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(30) DEFAULT NULL, first_name VARCHAR(60) DEFAULT NULL, birth_date DATE DEFAULT NULL, ico VARCHAR(10) DEFAULT NULL, street VARCHAR(120) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, zip VARCHAR(6) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(120) DEFAULT NULL, do_not_contact TINYINT NOT NULL, duedate DATE DEFAULT NULL, note LONGTEXT DEFAULT NULL, type VARCHAR(1) NOT NULL, last_name VARCHAR(160) NOT NULL, created_at DATETIME NOT NULL, municipality_id INT NOT NULL, INDEX idx_owners_name (last_name, first_name, id), INDEX idx_owners_municipality (municipality_id, last_name, first_name, id), INDEX idx_owners_ico (ico), INDEX IDX_427292FAAE6F181C (municipality_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE ownerships (id INT AUTO_INCREMENT NOT NULL, lv_number INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, share_num INT NOT NULL, share_den INT NOT NULL, owner_id INT NOT NULL, parcel_id INT NOT NULL, INDEX IDX_EC7408BA7E3C61F9 (owner_id), INDEX IDX_EC7408BA465E670C (parcel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE parcels (id INT AUTO_INCREMENT NOT NULL, land_type VARCHAR(60) DEFAULT NULL, cadastral_area VARCHAR(160) NOT NULL, cadastral_code VARCHAR(6) NOT NULL, parcel_number VARCHAR(20) NOT NULL, area_m2 INT NOT NULL, municipality_id INT NOT NULL, INDEX idx_parcels_land_type (land_type), INDEX IDX_5675350EAE6F181C (municipality_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('CREATE TABLE report_runs (id INT AUTO_INCREMENT NOT NULL, run_date DATE NOT NULL, hostname VARCHAR(120) DEFAULT NULL, duration NUMERIC(8, 2) DEFAULT NULL, owners_total INT DEFAULT NULL, report_file VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_report_runs_date (run_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_czech_ci`');
        $this->addSql('ALTER TABLE owner_notes ADD CONSTRAINT FK_F18293AB7E3C61F9 FOREIGN KEY (owner_id) REFERENCES owners (id)');
        $this->addSql('ALTER TABLE owners ADD CONSTRAINT FK_427292FAAE6F181C FOREIGN KEY (municipality_id) REFERENCES municipalities (id)');
        $this->addSql('ALTER TABLE ownerships ADD CONSTRAINT FK_EC7408BA7E3C61F9 FOREIGN KEY (owner_id) REFERENCES owners (id)');
        $this->addSql('ALTER TABLE ownerships ADD CONSTRAINT FK_EC7408BA465E670C FOREIGN KEY (parcel_id) REFERENCES parcels (id)');
        $this->addSql('ALTER TABLE parcels ADD CONSTRAINT FK_5675350EAE6F181C FOREIGN KEY (municipality_id) REFERENCES municipalities (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE owner_notes DROP FOREIGN KEY FK_F18293AB7E3C61F9');
        $this->addSql('ALTER TABLE owners DROP FOREIGN KEY FK_427292FAAE6F181C');
        $this->addSql('ALTER TABLE ownerships DROP FOREIGN KEY FK_EC7408BA7E3C61F9');
        $this->addSql('ALTER TABLE ownerships DROP FOREIGN KEY FK_EC7408BA465E670C');
        $this->addSql('ALTER TABLE parcels DROP FOREIGN KEY FK_5675350EAE6F181C');
        $this->addSql('DROP TABLE access_log');
        $this->addSql('DROP TABLE municipalities');
        $this->addSql('DROP TABLE owner_notes');
        $this->addSql('DROP TABLE owners');
        $this->addSql('DROP TABLE ownerships');
        $this->addSql('DROP TABLE parcels');
        $this->addSql('DROP TABLE report_runs');
    }
}
