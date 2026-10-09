<?php

namespace HrTest\View;

use Accounts\Model as Accounts;
use Administration\Model as Administration;
use Acl\Model as Acl;
use Hr\Model as Hr;
use Hr\Controller\PayrollController;
use Interop\Container\ContainerInterface;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Stdlib\Parameters;
use PHPUnit\Framework\TestCase;

final class PayrollCommitTest extends TestCase
{
    public function testCommitCreditsSelectedBankMapping(): void
    {
        $banks = $this->createMock(Accounts\BankaccountTable::class);
        $banks->expects(self::once())->method('getPayrollAccounts')->with(1)->willReturn([
            ['bank_account_id' => 10],
        ]);
        $banks->expects(self::once())->method('getPayrollMappings')->with(1, 10)->willReturn([
            ['bank_account_id' => 10, 'head_id' => 55, 'subhead_id' => 101],
        ]);
        $transactions = $this->createMock(Accounts\TransactionTable::class);
        $transactions->expects(self::once())->method('getNextSerial')->with('HQ-PY' . date('ym'))->willReturn(1);
        $transactions->method('save')->willReturn(500);
        $details = $this->createMock(Accounts\TransactiondetailTable::class);
        $saved = [];
        $details->method('save')->willReturnCallback(function ($data) use (&$saved) {
            $saved[] = $data;
            return count($saved);
        });
        $users = $this->createMock(Administration\UsersTable::class);
        $users->method('getColumn')->willReturn(1);
        $users->method('get')->willReturn([]);
        $locations = $this->createMock(Administration\LocationTable::class);
        $locations->method('getColumn')->willReturn('HQ');
        $journals = $this->createMock(Accounts\JournalTable::class);
        $journals->method('getColumn')->willReturn('PY');
        $payroll = $this->createMock(Hr\PayrollTable::class);
        $payroll->method('getSumGross')->willReturnCallback(static function ($column) {
            return $column === 'gross' ? '65,400.00' : '61,200.00';
        });
        $paydetails = $this->createMock(Hr\PaydetailTable::class);
        $paydetails->method('getDistinct')->willReturn([]);
        $paydetails->method('getDistinctReport')->willReturn([['id' => 1, 'location' => 1, 'amount' => 65400]]);
        $paydetails->method('getDistinctDeduction')->willReturn([['id' => 3, 'amount' => 4200]]);
        $paydetails->method('getEmployeeDed')->willReturn([]);
        $subheads = $this->createMock(Accounts\SubheadTable::class);
        $subheads->method('getColumn')->willReturnCallback(static function ($where, $column) {
            return $column === 'head' ? 22 : $where['ref_id'];
        });
        $flows = $this->createMock(Administration\FlowTransactionTable::class);
        $flows->method('save')->willReturn(1);
        $notifications = $this->createMock(Acl\NotificationTable::class);
        $notifications->method('save')->willReturn(1);
        $controller = $this->makeController([
            Accounts\BankaccountTable::class => $banks,
            Accounts\TransactionTable::class => $transactions,
            Accounts\TransactiondetailTable::class => $details,
            Administration\UsersTable::class => $users,
            Administration\LocationTable::class => $locations,
            Accounts\JournalTable::class => $journals,
            Hr\PayrollTable::class => $payroll,
            Hr\PaydetailTable::class => $paydetails,
            Accounts\SubheadTable::class => $subheads,
            Administration\FlowTransactionTable::class => $flows,
            Acl\NotificationTable::class => $notifications,
        ], '10');
        $controller->commitpayrollAction();
        self::assertCount(3, $saved);
        self::assertSame('55', $saved[2]['head']);
        self::assertSame('101', $saved[2]['sub_head']);
        self::assertSame('61200.00', $saved[2]['credit']);
        self::assertSame(1, $controller->connection->commits);
        self::assertSame(0, $controller->connection->rollbacks);
    }

    public function testInvalidBankIsRejectedBeforeStartingTransaction(): void
    {
        $banks = $this->createMock(Accounts\BankaccountTable::class);
        $banks->method('getPayrollAccounts')->willReturn([]);
        $controller = $this->makeController([Accounts\BankaccountTable::class => $banks], '999');
        $controller->commitpayrollAction();
        self::assertSame(0, $controller->connection->begins);
        self::assertStringContainsString('Please select a bank account', $controller->messages[0]);
    }

    public function testMissingOrAmbiguousMappingIsRejectedBeforeWrites(): void
    {
        foreach ([[], [['head_id' => 55, 'subhead_id' => 101], ['head_id' => 56, 'subhead_id' => 102]]] as $mappings) {
            $banks = $this->createMock(Accounts\BankaccountTable::class);
            $banks->method('getPayrollAccounts')->willReturn([['bank_account_id' => 10]]);
            $banks->method('getPayrollMappings')->with(1, 10)->willReturn($mappings);
            $controller = $this->makeController([Accounts\BankaccountTable::class => $banks], '10');
            $controller->commitpayrollAction();
            self::assertSame(0, $controller->connection->begins);
            self::assertStringContainsString('mapping', $controller->messages[0]);
        }
    }

    private function makeController(array $tables, string $bankAccount)
    {
        $controller = new class($this->createMock(ContainerInterface::class)) extends PayrollController {
            public $tables;
            public $request;
            public $connection;
            public $messages = [];
            public function init()
            {
                $this->_user = (object) ['location' => 1];
                $this->_author = 1;
                $this->_created = $this->_modified = '2026-10-08 16:00:00';
                $this->_safedataObj = new \Application\Controller\Plugin\SafeDataPlugin();
                $this->_connection = $this->connection;
            }
            public function getDefinedTable($table) { return $this->tables[$table]; }
            public function getRequest() { return $this->request; }
            public function flashMessenger() { return $this; }
            public function addMessage($message) { $this->messages[] = $message; }
            public function redirect() { return $this; }
            public function toRoute($route, $params) { return $params; }
        };
        $controller->tables = $tables;
        if (!isset($controller->tables[Administration\UsersTable::class])) {
            $users = $this->createMock(Administration\UsersTable::class);
            $users->method('getColumn')->willReturn(1);
            $controller->tables[Administration\UsersTable::class] = $users;
        }
        $controller->request = new Request();
        $controller->request->setMethod('POST');
        $controller->request->setPost(new Parameters(['year' => '2026', 'month' => '10', 'bank_account' => $bankAccount]));
        $controller->connection = new class {
            public $begins = 0;
            public $commits = 0;
            public $rollbacks = 0;
            public function beginTransaction() { $this->begins++; }
            public function commit() { $this->commits++; }
            public function rollback() { $this->rollbacks++; }
        };
        return $controller;
    }
}
