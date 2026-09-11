<?php
namespace Administration\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;

class AppSettingTable
{
    protected $adapter;
    protected $table = 'adm_app_setting';

    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function ensureTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` int(11) NOT NULL,
            `app_name` varchar(255) DEFAULT NULL,
            `app_logo` varchar(255) DEFAULT NULL,
            `app_template` varchar(60) DEFAULT NULL,
            `mail_from_email` varchar(255) DEFAULT NULL,
            `mail_from_name` varchar(255) DEFAULT NULL,
            `smtp_host` varchar(255) DEFAULT NULL,
            `smtp_port` int(11) DEFAULT NULL,
            `smtp_encryption` varchar(20) DEFAULT NULL,
            `smtp_username` varchar(255) DEFAULT NULL,
            `smtp_password` text DEFAULT NULL,
            `support_email` varchar(255) DEFAULT NULL,
            `support_phone` varchar(80) DEFAULT NULL,
            `author` int(11) DEFAULT NULL,
            `created` datetime DEFAULT NULL,
            `modified` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

        $this->adapter->query($sql, $this->adapter::QUERY_MODE_EXECUTE);

        $columnMap = [
            'mail_from_email' => "ALTER TABLE `{$this->table}` ADD COLUMN `mail_from_email` varchar(255) DEFAULT NULL",
            'mail_from_name' => "ALTER TABLE `{$this->table}` ADD COLUMN `mail_from_name` varchar(255) DEFAULT NULL",
            'smtp_host' => "ALTER TABLE `{$this->table}` ADD COLUMN `smtp_host` varchar(255) DEFAULT NULL",
            'smtp_port' => "ALTER TABLE `{$this->table}` ADD COLUMN `smtp_port` int(11) DEFAULT NULL",
            'smtp_encryption' => "ALTER TABLE `{$this->table}` ADD COLUMN `smtp_encryption` varchar(20) DEFAULT NULL",
            'smtp_username' => "ALTER TABLE `{$this->table}` ADD COLUMN `smtp_username` varchar(255) DEFAULT NULL",
            'smtp_password' => "ALTER TABLE `{$this->table}` ADD COLUMN `smtp_password` text DEFAULT NULL",
            'support_email' => "ALTER TABLE `{$this->table}` ADD COLUMN `support_email` varchar(255) DEFAULT NULL",
            'support_phone' => "ALTER TABLE `{$this->table}` ADD COLUMN `support_phone` varchar(80) DEFAULT NULL",
        ];

        foreach ($columnMap as $column => $alterSql) {
            $hasColumn = $this->adapter->query(
                "SHOW COLUMNS FROM `{$this->table}` LIKE '{$column}'",
                $this->adapter::QUERY_MODE_EXECUTE
            )->count() > 0;

            if (!$hasColumn) {
                $this->adapter->query($alterSql, $this->adapter::QUERY_MODE_EXECUTE);
            }
        }
    }

    public function getSettings()
    {
        $this->ensureTable();

        $sql = new Sql($this->adapter);
        $select = $sql->select();
        $select->from($this->table)->where(['id' => 1]);

        $result = $this->adapter
            ->query($sql->getSqlStringForSqlObject($select), $this->adapter::QUERY_MODE_EXECUTE)
            ->toArray();

        if (!empty($result)) {
            return $result[0];
        }

        return [
            'id' => 1,
            'app_name' => 'Monal-ERP',
            'app_logo' => 'images/logo.png',
            'app_template' => 'ace',
            'mail_from_email' => '',
            'mail_from_name' => '',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => '',
            'smtp_password' => '',
            'support_email' => '',
            'support_phone' => '',
            'author' => null,
            'created' => null,
            'modified' => null,
        ];
    }

    public function saveSettings(array $data)
    {
        $this->ensureTable();

        $payload = [
            'id' => 1,
            'app_name' => $data['app_name'],
            'app_logo' => $data['app_logo'],
            'app_template' => $data['app_template'],
            'mail_from_email' => $data['mail_from_email'],
            'mail_from_name' => $data['mail_from_name'],
            'smtp_host' => $data['smtp_host'],
            'smtp_port' => $data['smtp_port'],
            'smtp_encryption' => $data['smtp_encryption'],
            'smtp_username' => $data['smtp_username'],
            'smtp_password' => $data['smtp_password'],
            'support_email' => $data['support_email'],
            'support_phone' => $data['support_phone'],
            'author' => $data['author'],
            'created' => $data['created'],
            'modified' => $data['modified'],
        ];

        $sql = new Sql($this->adapter);
        $insert = $sql->insert($this->table)->values($payload);
        $insertSql = $sql->buildSqlString($insert) . " ON DUPLICATE KEY UPDATE "
            . "app_name=VALUES(app_name), "
            . "app_logo=VALUES(app_logo), "
            . "app_template=VALUES(app_template), "
            . "mail_from_email=VALUES(mail_from_email), "
            . "mail_from_name=VALUES(mail_from_name), "
            . "smtp_host=VALUES(smtp_host), "
            . "smtp_port=VALUES(smtp_port), "
            . "smtp_encryption=VALUES(smtp_encryption), "
            . "smtp_username=VALUES(smtp_username), "
            . "smtp_password=VALUES(smtp_password), "
            . "support_email=VALUES(support_email), "
            . "support_phone=VALUES(support_phone), "
            . "author=VALUES(author), "
            . "modified=VALUES(modified)";

        $this->adapter->query($insertSql, $this->adapter::QUERY_MODE_EXECUTE);
        return true;
    }
}