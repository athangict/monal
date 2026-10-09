<?php
namespace AccountsTest\Report;

use Accounts\Controller\ReportController;
use Accounts\Model;
use Accounts\Support\AccountLabel;
use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AccountLabelTest extends TestCase
{
	public function testLabelsDecodeStoredEntitiesAndEscapeMarkup(): void
	{
		self::assertSame('ECB-Employee Compensation &amp; Benefits', AccountLabel::html('Employee Compensation &amp; Benefits', 'ECB'));
		self::assertSame('CURRENT LIABILITIES &amp; PROVISION', AccountLabel::html('CURRENT LIABILITIES &amp; PROVISION'));
		self::assertSame('A &amp; B', AccountLabel::html('A & B'));
		self::assertSame('X-&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', AccountLabel::html('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', 'X'));
		self::assertSame('001-Bank', AccountLabel::html('Bank', '001'));
	}

	public function testLazyReportHeadsUseCodeAndNameWithoutChangingAmounts(): void
	{
		$heads = $this->createMock(Model\HeadTable::class);
		$head = ['id' => 1, 'group' => 2, 'head_type' => 3, 'code' => 'ECB', 'name' => 'Employee &amp; Staff'];
		$heads->method('getTransactionHead')->willReturn([$head]);
		$heads->method('getTransactionHeadforBS')->willReturn([$head]);
		$groups = $this->createMock(Model\GroupTable::class);
		$groups->method('getColumn')->willReturn(4);
		$details = $this->createMock(Model\TransactiondetailTable::class);
		$details->method('getSumbyHead')->willReturn(100);
		$details->method('getClosingBalanceIE')->willReturn(100);
		$details->method('getClosingBalanceforPresBS')->willReturn(100);
		$details->method('getClosingBalanceforPrevBS')->willReturn(50);
		$details->method('getClosingBalanceforPresPLSCLASS')->willReturn(100);
		$details->method('getClosingBalanceforPrevPLS')->willReturn(50);
		$tables = [
			Model\HeadTable::class => $heads,
			Model\GroupTable::class => $groups,
			Model\TransactiondetailTable::class => $details,
			Model\SubheadTable::class => $this->createMock(Model\SubheadTable::class),
		];
		$controller = new class($this->createMock(ContainerInterface::class), $tables) extends ReportController {
			private array $tables;
			public function __construct(ContainerInterface $container, array $tables)
			{
				parent::__construct($container);
				$this->tables = $tables;
			}
			public function getDefinedTable($table)
			{
				return $this->tables[$table];
			}
		};
		$filters = ['activity' => -1, 'region' => -1, 'location' => -1, 'start_date' => '2026-01-01', 'end_date' => '2026-10-08', 'head_type_id' => 3];
		foreach (['buildTrialBalanceLazyRows', 'buildBalanceSheetLazyRows', 'buildProfitLossLazyRows'] as $method) {
			$html = (new ReflectionMethod($controller, $method))->invoke($controller, 'head', 2, $filters);
			self::assertStringContainsString('ECB-Employee &amp; Staff', $html);
			self::assertStringNotContainsString('&amp;amp;', $html);
			self::assertStringContainsString('100.00', $html);
			self::assertStringContainsString('data-parent="headtype-2_3"', $html);
		}
		$html = (new ReflectionMethod($controller, 'esc'))->invoke($controller, 'Employee &amp; Staff');
		self::assertSame('Employee &amp; Staff', $html);
	}
}
