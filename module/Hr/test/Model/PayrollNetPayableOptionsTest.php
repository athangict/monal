<?php

namespace HrTest\Model;

use Hr\Model\PayheadtypeTable;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

final class PayrollNetPayableOptionsTest extends TestCase
{
    public function testReturnsOnlyTypeFiveSubheadMappings(): void
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        foreach ([
            'CREATE TABLE hr_pay_head_type (id INTEGER, head_type TEXT)',
            'CREATE TABLE fa_head (id INTEGER, name TEXT)',
            'CREATE TABLE fa_sub_head (id INTEGER, head INTEGER, type INTEGER, ref_id INTEGER, code TEXT, name TEXT)',
            "INSERT INTO hr_pay_head_type VALUES (9, 'Net Payable'), (10, 'Deductions')",
            "INSERT INTO fa_head VALUES (55, 'Current Liability')",
            "INSERT INTO fa_sub_head VALUES (101, 55, 5, 9, 'NP', 'Salary Net Payable'), (102, 55, 3, 9, 'BANK', 'Bank Link')",
        ] as $sql) {
            $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }

        $table = new PayheadtypeTable($adapter);
        $options = $table->getPayrollNetPayableOptions();
        $netPayableOnly = $table->getPayrollNetPayableOptions(true);

        self::assertCount(1, $options);
        self::assertCount(1, $netPayableOnly);
        self::assertSame(9, $options[0]['payhead_type_id']);
        self::assertSame(55, $options[0]['head_id']);
        self::assertSame(101, $options[0]['subhead_id']);
        self::assertSame('NP', $options[0]['subhead_code']);
    }

    public function testPrefersPayheadTypeColumnMappingWhenAvailable(): void
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        foreach ([
            'CREATE TABLE hr_pay_head_type (id INTEGER, head_type TEXT, code TEXT, deduction INTEGER, fa_sub_head INTEGER)',
            'CREATE TABLE fa_head (id INTEGER, name TEXT)',
            'CREATE TABLE fa_sub_head (id INTEGER, head INTEGER, type INTEGER, ref_id INTEGER, code TEXT, name TEXT)',
            "INSERT INTO hr_pay_head_type VALUES (9, 'Net Payable', 'NP', 1, 201), (10, 'Allowance', 'ALW', 0, NULL)",
            "INSERT INTO fa_head VALUES (55, 'Current Liability')",
            "INSERT INTO fa_sub_head VALUES (201, 55, 5, 999, 'NP201', 'Mapped by column'), (202, 55, 5, 9, 'NP202', 'Legacy ref map')",
        ] as $sql) {
            $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }

        $table = new PayheadtypeTable($adapter);
        $options = $table->getPayrollNetPayableOptions();

        self::assertCount(1, $options);
        self::assertSame(201, $options[0]['subhead_id']);
        self::assertSame(201, $table->getFinanceSubheadId(9));
        self::assertSame(202, $table->getFinanceSubheadId(10));
        self::assertTrue($table->hasFinanceSubheadColumn());
    }
}
