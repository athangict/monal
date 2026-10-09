<?php

namespace HrTest\Model;

use Hr\Model\PaystructureTable;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

final class PitNetPayTest extends TestCase
{
    private function createTable(): PaystructureTable
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        foreach ([
            'CREATE TABLE hr_pay_head_type (id INTEGER, deduction INTEGER, head_type TEXT)',
            'CREATE TABLE hr_pay_heads (id INTEGER, payhead_type INTEGER, pay_head TEXT, code TEXT, against INTEGER, roundup INTEGER, type INTEGER)',
            'CREATE TABLE hr_pay_structure (id INTEGER, employee INTEGER, pay_head INTEGER, amount TEXT, status INTEGER)',
            "INSERT INTO hr_pay_head_type VALUES (1, 1, 'Deduction'), (2, 0, 'Earning')",
            "INSERT INTO hr_pay_heads VALUES (3, 1, 'Provisional Fund', 'PF', 1, 1, 2), (40, 1, 'GIS', 'GIS', 0, 0, 1), (6, 2, 'Voucher Allowance', 'VA', 0, 0, 1), (7, 2, 'Company Allowance', 'CA', 0, 0, 1), (9, 1, 'Other deduction', 'OTHER', 0, 0, 1), (12, 1, 'Other deduction 2', 'OTHER2', 0, 0, 1)",
            "INSERT INTO hr_pay_structure VALUES (1, 1, 3, '4200', 1), (2, 1, 40, '100.50', 1), (3, 1, 6, '1000', 1), (4, 1, 7, '4400', 1), (5, 1, 9, '500', 1), (6, 1, 12, '300', 1), (7, 2, 3, '999', 1), (8, 1, 3, '999', 0)",
        ] as $sql) {
            $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return new PaystructureTable($adapter);
    }

    public function testUsesCodesAndOnlyActiveDeductionsForTheEmployee(): void
    {
        $table = $this->createTable();
        self::assertSame(61099.5, $table->getPitNetPay(1, '65400'));
        self::assertSame(64401.0, $table->getPitNetPay(2, '65400'));
        self::assertSame(65400.0, $table->getPitNetPay(3, '65400'));
    }

    public function testMalformedGrossIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->createTable()->getPitNetPay(1, 'invalid');
    }
}
