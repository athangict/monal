<?php

namespace HrTest\Model;

use Accounts\Model\SubheadTable;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

final class PayheadSubheadsTest extends TestCase
{
    public function testReturnsAllSubheadsUnderLinkedHeadWithoutDuplicates(): void
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        foreach ([
            'CREATE TABLE fa_class (id INTEGER, name TEXT)',
            'CREATE TABLE fa_group (id INTEGER, name TEXT, class INTEGER)',
            'CREATE TABLE fa_head_type (id INTEGER, head_type TEXT)',
            'CREATE TABLE fa_head (id INTEGER, name TEXT, "group" INTEGER, head_type INTEGER)',
            'CREATE TABLE fa_type (id INTEGER, type TEXT, class INTEGER)',
            'CREATE TABLE fa_sub_head (id INTEGER, head INTEGER, type INTEGER, ref_id INTEGER, code TEXT, name TEXT)',
            "INSERT INTO fa_class VALUES (1, 'Expense')",
            "INSERT INTO fa_group VALUES (1, 'Expenses', 1)",
            "INSERT INTO fa_head_type VALUES (1, 'Employee Expense')",
            "INSERT INTO fa_head VALUES (22, 'Salary', 1, 1), (23, 'Other Head', 1, 1)",
            "INSERT INTO fa_type VALUES (5, 'Payroll', 1), (1, 'Other', 1)",
            "INSERT INTO fa_sub_head VALUES (1, 22, 5, 10, 'A', 'Basic Pay'), (2, 22, 5, 11, 'B', 'Allowance'), (3, 22, 1, NULL, 'C', 'Other salary subhead'), (4, 23, 5, 12, 'D', 'Unrelated'), (5, 22, 5, 10, 'E', 'Second link')",
        ] as $sql) {
            $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        $table = new SubheadTable($adapter);
        $choices = $table->getPayheadTypeSubheads(10);
        self::assertSame([1, 2, 3, 5], array_column($choices, 'id'));
        self::assertSame([10, 10, 10, 10], array_column($choices, 'payhead_type_id'));
        self::assertSame([], $table->getPayheadTypeSubheads(99));
        self::assertCount(9, $table->getPayheadTypeSubheads());
    }
}
