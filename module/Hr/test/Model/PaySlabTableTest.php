<?php

namespace HrTest\Model;

use Hr\Model\PaySlabTable;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

final class PaySlabTableTest extends TestCase
{
    public function testBlankValuesPersistAsNullOnCreateAndEdit(): void
    {
        $adapter = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        $adapter->query(
            'CREATE TABLE hr_pay_slab (id INTEGER PRIMARY KEY AUTOINCREMENT, rate NUMERIC NULL, base NUMERIC NULL, value NUMERIC NULL)',
            Adapter::QUERY_MODE_EXECUTE
        );
        $table = new PaySlabTable($adapter);
        $id = $table->save(['rate' => '', 'base' => '  ', 'value' => null]);
        $row = $table->get($id)[0];
        foreach (['rate', 'base', 'value'] as $field) {
            self::assertNull($row[$field]);
        }
        $table->save(['id' => $id, 'rate' => '0', 'base' => '12.5', 'value' => 0]);
        $row = $table->get($id)[0];
        self::assertEquals(0, $row['rate']);
        self::assertEquals(12.5, $row['base']);
        self::assertEquals(0, $row['value']);
        $table->save(['id' => $id, 'rate' => '', 'base' => '', 'value' => '']);
        $row = $table->get($id)[0];
        foreach (['rate', 'base', 'value'] as $field) {
            self::assertNull($row[$field]);
        }
    }
}
