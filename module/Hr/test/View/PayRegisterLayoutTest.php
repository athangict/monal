<?php

namespace HrTest\View;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class PayRegisterLayoutTest extends TestCase
{
    public function testTemplateDoesNotOutputBlankLineBeforeTabs(): void
    {
        $source = file_get_contents(__DIR__ . '\..\..\view\hr\payroll-report\payregister.phtml');
        self::assertStringStartsWith('<?php', $source);
    }

    public function testHeaderBodyAndFooterAlignForEveryPayheadCombination(): void
    {
        foreach ([[0, 0], [2, 0], [0, 2], [2, 2]] as [$earnings, $deductions]) {
            $html = $this->renderRegister($earnings, $deductions);
            $xpath = $this->xpath($html);
            $expected = 9 + $earnings + $deductions;
            $width = 0;
            foreach ($xpath->query('//table[@id="example"]/thead/tr[1]/th') as $cell) {
                $width += $cell->hasAttribute('colspan') ? (int) $cell->getAttribute('colspan') : 1;
            }
            self::assertSame($expected, $width);
            self::assertSame(0, $xpath->query('//table[@id="example"]//*[@colspan="0"]')->length);
            self::assertSame(1, $xpath->query('//table[@id="example"]/tbody/tr')->length);
            self::assertSame($expected, $xpath->query('//table[@id="example"]/tbody/tr[1]/td')->length);
            self::assertSame($expected, $xpath->query('//table[@id="example"]/tfoot/tr/th')->length);
            self::assertSame('100', trim($xpath->query('//table[@id="example"]/tbody/tr/td[last()-2]')->item(0)->textContent));
            self::assertSame('1,000', trim($xpath->query('//table[@id="example"]/tbody/tr/td[last()-1]')->item(0)->textContent));
            self::assertSame('900', trim($xpath->query('//table[@id="example"]/tbody/tr/td[last()]')->item(0)->textContent));
            self::assertSame(5, substr_count($html, 'footer: true'));
        }
    }

    public function testDraftUsesEachPayheadsOwnId(): void
    {
        $xpath = $this->xpath($this->renderRegister(2, 2, true));
        foreach ([7 => '10', 8 => '20', 9 => '30', 10 => '40'] as $column => $amount) {
            self::assertSame($amount, trim($xpath->query('//table[@id="example"]/tbody/tr/td[' . $column . ']')->item(0)->textContent));
            self::assertSame($amount, trim($xpath->query('//table[@id="example"]/tfoot/tr/th[' . $column . ']')->item(0)->textContent));
        }
    }

    public function testUnpreparedReportHasEmptyBodyAndValidFooter(): void
    {
        $xpath = $this->xpath($this->renderRegister(0, 0, false, true));
        self::assertSame(0, $xpath->query('//table[@id="example"]/tbody/*')->length);
        self::assertSame(9, $xpath->query('//table[@id="example"]/tfoot/tr/th')->length);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return new DOMXPath($document);
    }

    private function renderRegister(int $earnings, int $deductions, bool $draft = false, bool $empty = false): string
    {
        $employee = [
            'id' => 1, 'employee_id' => 1, 'full_name' => 'Employee',
            'emp_id' => 'E001', 'position_title' => 'Developer',
            'department' => 'Corporate', 'position_level' => '2',
        ];
        $payroll = static function (array $rows) {
            return new class($rows) {
                private $rows;
                public function __construct(array $rows) { $this->rows = $rows; }
                public function getforReport($filter) { return $this->rows; }
                public function getColumn($filter, $column) {
                    return ['gross' => 1000, 'total_deduction' => 100, 'net_pay' => 900][$column];
                }
            };
        };
        $view = new class {
            public $title = 'Pay Register';
            public $minYear = 2026;
            public $data = ['year' => 2026, 'month' => 10, 'region' => -1, 'location' => -1, 'department' => -1];
            public $regions = [];
            public $location = [];
            public $department = [];
            public $earningHead = [];
            public $deductionHead = [];
            public $payrollObj;
            public $temppayrollObj;
            public $paystructureObj;
            public $paydetailObj;
            public function headTitle($title) {}
            public function tabs_helper($tabs) { return ''; }
            public function url($route, $params) { return '#'; }
            public function decimal($amount) { return number_format($amount); }
            public function render(): string {
                $data = $this->data;
                $regions = $this->regions;
                $location = $this->location;
                $department = $this->department;
                ob_start();
                try {
                    include __DIR__ . '\..\..\view\hr\payroll-report\payregister.phtml';
                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        for ($id = 1; $id <= $earnings; $id++) {
            $view->earningHead[] = ['id' => $id, 'code' => 'E' . $id];
        }
        for ($id = 1; $id <= $deductions; $id++) {
            $view->deductionHead[] = ['id' => $id + $earnings, 'code' => 'D' . $id];
        }
        $view->payrollObj = $payroll(!$draft && !$empty ? [$employee] : []);
        $view->temppayrollObj = $payroll($draft && !$empty ? [$employee] : []);
        $view->paystructureObj = $view->paydetailObj = new class {
            public function getColumn($filter, $column) { return $filter['pay_head'] * 10; }
        };
        return $view->render();
    }
}
