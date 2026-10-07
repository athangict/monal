<?php
namespace Accounts\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;

class TaxationTable extends AbstractTableGateway
{
	protected $table = 'fa_taxation';

	public function __construct(Adapter $adapter)
	{
		$this->adapter = $adapter;
	}

	public function getAll()
	{
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('tx' => $this->table))
			->join(array('ht' => 'fa_head_type'), 'ht.id = tx.head_type', array('head_type_name' => 'head_type'), Select::JOIN_LEFT)
			->join(array('h' => 'fa_head'), 'h.id = tx.head', array('head_name' => 'name'), Select::JOIN_LEFT)
			->order(array('tx.taxation ASC'));
		$results = $adapter->query($sql->getSqlStringForSqlObject($select), $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}

	public function get($param)
	{
		$where = (is_array($param)) ? $param : array('tx.id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from(array('tx' => $this->table))
			->join(array('ht' => 'fa_head_type'), 'ht.id = tx.head_type', array('head_type_name' => 'head_type'), Select::JOIN_LEFT)
			->join(array('h' => 'fa_head'), 'h.id = tx.head', array('head_name' => 'name'), Select::JOIN_LEFT)
			->where($where);
		$results = $adapter->query($sql->getSqlStringForSqlObject($select), $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}

	public function getColumn($param, $column)
	{
		$where = (is_array($param)) ? $param : array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)->columns(array($column))->where($where);
		$results = $adapter->query($sql->getSqlStringForSqlObject($select), $adapter::QUERY_MODE_EXECUTE)->toArray();
		$value = '';
		foreach ($results as $result) {
			$value = $result[$column];
		}
		return $value;
	}

	public function save($data)
	{
		if (!is_array($data)) $data = $data->toArray();
		$id = isset($data['id']) ? (int)$data['id'] : 0;
		if ($id > 0) {
			return ($this->update($data, array('id' => $id))) ? $id : 0;
		}
		$this->insert($data);
		return $this->getLastInsertValue();
	}

	public function remove($id)
	{
		return $this->delete(array('id' => $id));
	}
}
