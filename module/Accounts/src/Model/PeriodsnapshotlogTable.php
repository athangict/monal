<?php
namespace Accounts\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class PeriodsnapshotlogTable extends AbstractTableGateway
{
	protected $table = 'fa_period_snapshot_log';

	public function __construct(Adapter $adapter)
	{
		$this->adapter = $adapter;
	}

	public function getMaxRunNo($periodEnd, $region = -1, $location = -1)
	{
		$sql = new Sql($this->adapter);
		$select = $sql->select();
		$select->from($this->table)
			->columns(array('max_run_no' => new Expression('MAX(run_no)')))
			->where(array(
				'period_end' => $periodEnd,
				'region' => $region,
				'location' => $location,
			));
		$rows = $this->adapter->query($sql->getSqlStringForSqlObject($select), $this->adapter::QUERY_MODE_EXECUTE)->toArray();
		if (empty($rows) || !isset($rows[0]['max_run_no']) || $rows[0]['max_run_no'] === null) {
			return 0;
		}
		return (int)$rows[0]['max_run_no'];
	}

	public function save($data)
	{
		if (!is_array($data)) {
			$data = $data->toArray();
		}
		$id = isset($data['id']) ? (int)$data['id'] : 0;
		if ($id > 0) {
			return ($this->update($data, array('id' => $id))) ? $id : 0;
		}
		$this->insert($data);
		return (int)$this->getLastInsertValue();
	}
}
