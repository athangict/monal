<?php

namespace HrTest\Model;

use Hr\Model\PayheadtypeTable;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

final class LeaveEncashmentMappingTest extends TestCase
{
    private function createAdapter(bool $explicitMapping): Adapter
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        $column = $explicitMapping ? ', fa_sub_head INTEGER' : '';
        foreach ([
            'CREATE TABLE hr_pay_head_type (id INTEGER, code TEXT' . $column . ')',
            'CREATE TABLE fa_head (id INTEGER, name TEXT)',
            'CREATE TABLE fa_sub_head (id INTEGER, head INTEGER, type INTEGER, ref_id INTEGER)',
            "INSERT INTO fa_head VALUES (88, 'Provision'), (22, 'Employee Compensation & Benefits')",
            'INSERT INTO fa_sub_head VALUES (901, 88, 5, 77), (902, 22, 5, 1)',
        ] as $sql) {
            $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return $adapter;
    }

    public function testResolvesProvisionWithDifferentIdsUsingLegacyMapping(): void
    {
        $adapter = $this->createAdapter(false);
        $adapter->query("INSERT INTO hr_pay_head_type VALUES (77, 'LE'), (1, 'BP')", Adapter::QUERY_MODE_EXECUTE);
        self::assertSame(901, (new PayheadtypeTable($adapter))->getLeaveEncashmentSubheadId());
    }

    public function testPrefersExplicitMappingOverLegacyMapping(): void
    {
        $adapter = $this->createAdapter(true);
        $adapter->query("INSERT INTO hr_pay_head_type VALUES (77, 'LE', 903)", Adapter::QUERY_MODE_EXECUTE);
        $adapter->query('INSERT INTO fa_sub_head VALUES (903, 88, 5, 999)', Adapter::QUERY_MODE_EXECUTE);
        self::assertSame(903, (new PayheadtypeTable($adapter))->getLeaveEncashmentSubheadId());
    }

    public function testDoesNotFallBackToBasicPayWhenProvisionIsMissing(): void
    {
        $adapter = $this->createAdapter(false);
        $adapter->query("INSERT INTO hr_pay_head_type VALUES (1, 'BP')", Adapter::QUERY_MODE_EXECUTE);
        self::assertNull((new PayheadtypeTable($adapter))->getLeaveEncashmentSubheadId());
        $adapter->query("INSERT INTO hr_pay_head_type VALUES (78, 'LE')", Adapter::QUERY_MODE_EXECUTE);
        self::assertNull((new PayheadtypeTable($adapter))->getLeaveEncashmentSubheadId());
    }

    public function testRejectsDuplicateLeaveEncashmentCodes(): void
    {
        $adapter = $this->createAdapter(false);
        $adapter->query("INSERT INTO hr_pay_head_type VALUES (77, 'LE'), (78, 'LE')", Adapter::QUERY_MODE_EXECUTE);
        self::assertNull((new PayheadtypeTable($adapter))->getLeaveEncashmentSubheadId());
    }

    public function testRejectsInvalidExplicitAccountMappings(): void
    {
        $adapter = $this->createAdapter(true);
        $adapter->query("INSERT INTO hr_pay_head_type VALUES (77, 'LE', 903)", Adapter::QUERY_MODE_EXECUTE);
        $table = new PayheadtypeTable($adapter);
        self::assertNull($table->getLeaveEncashmentSubheadId());
        $adapter->query('INSERT INTO fa_sub_head VALUES (903, 88, 3, 77)', Adapter::QUERY_MODE_EXECUTE);
        self::assertNull($table->getLeaveEncashmentSubheadId());
        $adapter->query('UPDATE fa_sub_head SET type = 5, head = 999 WHERE id = 903', Adapter::QUERY_MODE_EXECUTE);
        self::assertNull($table->getLeaveEncashmentSubheadId());
    }
}
