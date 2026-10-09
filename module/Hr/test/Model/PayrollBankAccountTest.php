<?php

namespace HrTest\Model;

use Accounts\Model\BankaccountTable;
use Accounts\Model\TransactionTable;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

final class PayrollBankAccountTest extends TestCase
{
    public function testBanksAreListedDirectlyAndMappingsResolvedSeparately(): void
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        foreach ([
            'CREATE TABLE fa_bank_account (id INTEGER, code TEXT, account TEXT, location INTEGER)',
            'CREATE TABLE fa_sub_head (id INTEGER, head INTEGER, ref_id INTEGER, type INTEGER, name TEXT, code TEXT)',
            'CREATE TABLE fa_head (id INTEGER, name TEXT)',
            "INSERT INTO fa_bank_account VALUES (10, 'BANK1', '111', 1), (20, 'BANK2', '222', 2), (30, 'UNMAPPED', '333', 1)",
            "INSERT INTO fa_head VALUES (55, 'Bank Head'), (56, 'Other Bank Head')",
            "INSERT INTO fa_sub_head VALUES (101, 55, 10, 3, 'Bank ledger', 'A'), (102, 56, 20, 3, 'Other location', 'B'), (103, 55, 30, 5, 'Wrong type', 'C'), (104, 999, 30, 3, 'Missing head', 'D')",
        ] as $sql) {
            $adapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        $table = new BankaccountTable($adapter);
        $accounts = $table->getPayrollAccounts(1);
        self::assertCount(2, $accounts);
        self::assertSame(10, $accounts[0]['bank_account_id']);
        $mappings = $table->getPayrollMappings(1, 10);
        self::assertSame(101, $mappings[0]['subhead_id']);
        self::assertSame(55, $mappings[0]['head_id']);
        self::assertSame([], $table->getPayrollMappings(1, 20));
        self::assertSame([], $table->getPayrollMappings(1, 30));
        self::assertSame([], $table->getPayrollAccounts(99));
    }

    public function testFirstAndSubsequentVoucherSerialsUseTheActualPrefixLength(): void
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        $adapter->query('CREATE TABLE fa_transaction (voucher_no TEXT)', Adapter::QUERY_MODE_EXECUTE);
        $table = new TransactionTable($adapter);
        self::assertSame(1, $table->getNextSerial('HQ-PY2610'));
        foreach (['HQ-PY261000001', 'HQ-PY261000009', 'HQ-PY261000003', 'OTHER00099'] as $voucher) {
            $adapter->query('INSERT INTO fa_transaction VALUES (?)', [$voucher]);
        }
        self::assertSame(10, $table->getNextSerial('HQ-PY2610'));
        self::assertSame(1, $table->getNextSerial('LONGLOCATION-PY2610'));
    }
}
