<?php

namespace HrTest\View;

use PHPUnit\Framework\TestCase;

final class PayrollSlabTypeTest extends TestCase
{
    public function testPitNetPayWithMissingAndNumericDeductions(): void
    {
        foreach ([2, 3] as $type) {
            foreach ([
                ['1000.50', '', '', '1000.5'],
                ['1000.50', '100.25', '', '900.25'],
                ['1000.50', '', '50.25', '950.25'],
                ['1000.50', '100.25', '50.25', '850'],
                ['0', null, '0', '0'],
                ['100', '80', '40', '-20'],
            ] as [$gross, $pf, $gis, $expected]) {
                $html = $this->renderSlab($type, $gross, $pf, $gis);
                $field = $type === 2 ? 'baseamount' : 'basic';
                self::assertStringContainsString('id="' . $field . '" value="' . $expected . '"', $html);
                self::assertStringContainsString('id="roundup" value="0"', $html);
            }
        }
    }

    public function testMalformedDeductionIsNotSilentlyConvertedToZero(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid payroll amount for PF deduction.');
        $this->renderSlab(3, '1000', 'invalid', '');
    }

    public function testMissingSlabsShowConfigurationErrorWithoutCalculationFields(): void
    {
        $html = $this->renderSlab(3, '1000', '', '', []);
        self::assertStringContainsString('No pay slabs are configured', $html);
        self::assertStringNotContainsString('name="rate"', $html);
        self::assertStringNotContainsString('name="value"', $html);
    }

    public function testUnmatchedAmountDoesNotUseTheLastSlab(): void
    {
        $html = $this->renderSlab(3, '3000', '', '', [
            ['from_range' => 0, 'to_range' => 2000, 'formula' => 0, 'value' => '99'],
        ]);
        self::assertStringContainsString('No pay slab matches this base amount', $html);
        self::assertStringNotContainsString('name="value"', $html);
    }

    public function testDecimalAmountsAndInclusiveBoundariesMatchTheCorrectSlab(): void
    {
        foreach (['1000', '1000.50', '2000'] as $gross) {
            $html = $this->renderSlab(3, $gross, '', '', [
                ['from_range' => 1000, 'to_range' => 2000, 'formula' => 1, 'rate' => '10', 'base' => '50'],
            ]);
            self::assertStringContainsString('id="rate" value="10"', $html);
            self::assertStringContainsString('id="base" value="50"', $html);
            self::assertStringContainsString('id="min" value="1000"', $html);
        }
    }

    private function renderSlab($type, $gross, $pf, $gis, $slabs = null): string
    {
        $renderer = new class {
            public $pay_head = 1;
            public $employee = 1;
            public $payheadObj;
            public $paystructureObj;
            public $tempPayrollObj;
            public $payslabTable;

            public function render(): string
            {
                $employee = $this->employee;
                ob_start();
                try {
                    include dirname(__DIR__, 2) . '\view\hr\payroll\getslabtype.phtml';
                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        $renderer->payheadObj = new class($type) {
            private $type;
            public function __construct($type) { $this->type = $type; }
            public function get($id): array
            {
                return [['id' => $id, 'type' => $this->type, 'against' => '-2', 'percentage' => '10', 'roundup' => '0']];
            }
        };
        $renderer->paystructureObj = new class($pf, $gis) extends \Hr\Model\PaystructureTable {
            private $amounts;
            public function __construct($pf, $gis) { $this->amounts = [['code' => 'PF', 'amount' => $pf], ['code' => 'GIS', 'amount' => $gis]]; }
            public function get($where, $zeroAmt = true) { return $this->amounts; }
        };
        $renderer->tempPayrollObj = new class($gross) {
            private $gross;
            public function __construct($gross) { $this->gross = $gross; }
            public function getColumn($where, $column) { return $this->gross; }
        };
        $renderer->payslabTable = new class($slabs) {
            private $slabs;
            public function __construct($slabs)
            {
                $this->slabs = $slabs ?? [['from_range' => -100, 'to_range' => 2000, 'formula' => 0, 'value' => '0']];
            }
            public function get($where): array
            {
                return $this->slabs;
            }
        };
        return $renderer->render();
    }
}
