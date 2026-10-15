<?php

namespace HrTest\View;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class RemittanceAccountTest extends TestCase
{
    public function testMissingAccountsDoNotReusePreviousEmployeesAccount(): void
    {
        foreach ([9 => 'PF-001', 12 => 'GIS-001'] as $payhead => $expected) {
            self::assertSame([$expected, 'Not configured'], $this->renderAccounts($payhead));
        }
    }

    public function testOtherDeductionsUsePayrollReferenceWithoutTpnLookup(): void
    {
        self::assertSame(['REF-1', 'REF-2'], $this->renderAccounts(2));
    }

    public function testBlankSavedReferencesDisplayNotConfigured(): void
    {
        self::assertSame(['Not configured', 'Not configured'], $this->renderAccounts(2, false, true));
    }

    public function testDraftAccountsShowConfiguredPfGisOrMissingLabel(): void
    {
        foreach ([9 => 'PF-001', 12 => 'GIS-001'] as $payhead => $expected) {
            self::assertSame([$expected, 'Not configured'], $this->renderAccounts($payhead, true));
        }
        self::assertSame(['Not configured', 'Not configured'], $this->renderAccounts(2, true));
    }

    public function testTaxUsesEmployeeTpnForDraftAndSavedReports(): void
    {
        foreach ([false, true] as $draft) {
            self::assertSame(['TPN-001', 'Not configured'], $this->renderAccounts(42, $draft));
            self::assertSame(['TPN-001', 'Not configured'], $this->renderAccounts(43, $draft));
        }
    }

    private function renderAccounts(int $payhead, bool $draft = false, bool $blankReference = false): array
    {
        $view = new class {
            public $title = 'Remittance';
            public $minYear = 2026;
            public $payhead;
            public $payheadObj;
            public $tpnObj;
            public $temppayrollObj;
            public $paystructureObj;
            public $employeeObj;
            public $positionlevelObj;
            public $rows;
            public $draft;
            public function headTitle($title) {}
            public function tabs_helper($tabs) { return ''; }
            public function url($route, $params) { return '#'; }
            public function escapeHtml($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
            public function render(): string {
                $payheads = $this->payhead;
                $data = ['year' => 2026, 'month' => 10, 'region' => -1, 'location' => -1, 'payheads' => $payheads];
                $regions = $location = [];
                $payroll = $this->draft ? [] : $this->rows;
                ob_start();
                try {
                    include __DIR__ . '\..\..\view\hr\payroll-report\loanreport.phtml';
                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        $view->payhead = $payhead;
        $view->draft = $draft;
        $view->rows = [];
        foreach ([1, 2] as $id) {
            $view->rows[] = [
                'employee' => $id, 'employee_id' => $id, 'emp_id' => 'E' . $id,
                'dob' => '2000-01-01', 'full_name' => 'Employee ' . $id,
                'cid' => 'CID' . $id, 'actual_amount' => 100,
                'ref_no' => $blankReference ? '' : 'REF-' . $id,
            ];
        }
        $view->payheadObj = new class($payhead) {
            private $id;
            public function __construct($id) { $this->id = $id; }
            public function get($filter) { return []; }
            public function getColumn($filter, $column) {
                if ($column === 'code') {
                    return [9 => 'PF', 12 => 'GIS', 42 => 'TDS', 43 => 'OTHER-TAX'][$this->id] ?? 'LOAN';
                }
                if ($column === 'against') {
                    return $this->id === 43 ? -2 : 0;
                }
                return 'Deduction';
            }
        };
        $view->employeeObj = $view->positionlevelObj = new class {
            public function getColumn($filter, $column) { return 2; }
        };
        $view->temppayrollObj = new class($view->rows) {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function getforReportByLoc($filter) { return $this->rows; }
        };
        $view->paystructureObj = new class {
            public function getColumn($filter, $column) { return 100; }
        };
        $view->tpnObj = new class($payhead) {
            private $payhead;
            public function __construct($payhead) { $this->payhead = $payhead; }
            public function get($filter) {
                if (!in_array($this->payhead, [9, 12, 42, 43], true)) {
                    throw new \RuntimeException('Unrelated deductions must not query TPN records.');
                }
                return $filter['employee'] === 1 ? [['pf' => 'PF-001', 'gis' => 'GIS-001', 'tpn' => 'TPN-001']] : [];
            }
        };
        $html = $view->render();
        self::assertStringNotContainsString('Warning', $html);
        if ($draft) {
            self::assertStringContainsString('>DRAFT</span>', $html);
        } else {
            self::assertStringNotContainsString('>DRAFT</span>', $html);
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $accounts = [];
        foreach ((new DOMXPath($document))->query('//table[@id="example"]/tbody/tr/td[last()]') as $cell) {
            $accounts[] = trim($cell->textContent);
        }
        return $accounts;
    }
}
