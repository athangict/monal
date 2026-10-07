<?php
namespace Accounts\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Select;

class PeriodsnapshotTable extends AbstractTableGateway
{
	protected $table = 'fa_period_snapshot';

	public function __construct(Adapter $adapter)
	{
		$this->adapter = $adapter;
	}

	public function get($param)
	{
		$where = (is_array($param)) ? $param : array('id' => $param);
		$sql = new Sql($this->adapter);
		$select = $sql->select();
		$select->from($this->table)->where($where);
		return $this->adapter->query($sql->getSqlStringForSqlObject($select), $this->adapter::QUERY_MODE_EXECUTE)->toArray();
	}

	public function getLatestBefore($periodEnd, $region = -1, $location = -1)
	{
		$sql = new Sql($this->adapter);
		$select = $sql->select();
		$select->from($this->table)
			->where(array('region' => $region, 'location' => $location))
			->where->lessThan('period_end', $periodEnd);
		$select->order('period_end DESC');
		$select->limit(1);
		$rows = $this->adapter->query($sql->getSqlStringForSqlObject($select), $this->adapter::QUERY_MODE_EXECUTE)->toArray();
		return (!empty($rows)) ? $rows[0] : array();
	}

	public function getByPeriod($periodEnd, $region = -1, $location = -1)
	{
		$rows = $this->get(array(
			'period_end' => $periodEnd,
			'region' => $region,
			'location' => $location,
		));
		return (!empty($rows)) ? $rows[0] : array();
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

		$existing = $this->getByPeriod($data['period_end'], $data['region'], $data['location']);
		if (!empty($existing) && isset($existing['id'])) {
			$data['id'] = $existing['id'];
			return ($this->update($data, array('id' => $existing['id']))) ? (int)$existing['id'] : 0;
		}

		$this->insert($data);
		return (int)$this->getLastInsertValue();
	}
}
