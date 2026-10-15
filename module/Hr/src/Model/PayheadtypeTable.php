<?php
namespace Hr\Model;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\AbstractTableGateway;
use Laminas\Db\ResultSet\HydratingResultSet;
use Laminas\Db\Adapter\AdapterAwareInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Expression;

class PayheadtypeTable extends AbstractTableGateway
{
	protected $table = 'hr_pay_head_type'; //tablename
	private $hasFinanceSubheadColumn;

	public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }	

		/**
	 * Return All records of table
	 * @return Array
	 */
	public function getAll()
	{  
	    $adapter = $this->adapter;
	    $sql = new Sql($adapter);
	    $select = $sql->select();
	    $select->from($this->table);
	    
	    $selectString = $sql->getSqlStringForSqlObject($select);
	    $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
	    return $results;
	}

	public function getAllWithFinanceSubhead()
	{
		$sql = new Sql($this->adapter);
		$select = $sql->select();
		$select->from(array('pht' => $this->table))
			->columns(array('id', 'head_type', 'code', 'deduction', 'fa_sub_head'));
		$join = $this->hasFinanceSubheadColumn()
			? new Expression('sh.id = pht.fa_sub_head AND sh.type = ?', array(5))
			: new Expression('sh.ref_id = pht.id AND sh.type = ?', array(5));
		$select->join(
			array('sh' => 'fa_sub_head'),
			$join,
			array(
				'finance_sub_head_id' => 'id',
				'finance_sub_head_code' => 'code',
				'finance_sub_head_name' => 'name',
			),
			$select::JOIN_LEFT
		);
		$select->order('pht.id ASC');
		$selectString = $sql->getSqlStringForSqlObject($select);
		return $this->adapter->query($selectString, Adapter::QUERY_MODE_EXECUTE)->toArray();
	}
	
	/**
	 * Return records of given condition array | given id
	 * @param Int $id
	 * @return Array
	 */
	public function get($param)
	{
		$where = ( is_array($param) )? $param: array('id' => $param);
		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$select = $sql->select();
		$select->from($this->table)
		       ->where($where);
		
		$selectString = $sql->getSqlStringForSqlObject($select);
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}
	
	/**
	 * Return column value of given id
	 * @param Int $id
	 * @param String $column
	 * @return String | Int
	 */
  public function getColumn($param, $column)
    {          
            $where = ( is_array($param) )? $param: array('id' => $param);
            $fetch = array($column);
            $adapter = $this->adapter;       
            $sql = new Sql($adapter);
            $select = $sql->select();
            $select->from($this->table);
            $select->columns($fetch);
            $select->where($where);

            $selectString = $sql->getSqlStringForSqlObject($select);
            $results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();           
            $columns="";
           foreach ($results as $result):
              $columns =  $result[$column];
           endforeach;  
           
           return $columns;        
    }
	/**
	 * Save record
	 * @param String $array
	 * @return Int
	 */
	public function save($data)
	{
	    if ( !is_array($data) ) $data = $data->toArray();
	    $id = isset($data['id']) ? (int)$data['id'] : 0;
	    
	    if ( $id > 0 )
	    {
	    	$result = ($this->update($data, array('id'=>$id)))?$id:0;
	    } else {
	        $this->insert($data);
	    	$result = $this->getLastInsertValue(); 
	    }	    	    
	    return $result;	     
	}
	/**
	 * get payheads not in subhead
	 */
	public function getNotIn()
	{
		if ($this->hasFinanceSubheadColumn()) {
			$adapter = $this->adapter;
			$sql = new Sql($adapter);
			$select = $sql->select();
			$select->from(array('ph' => $this->table))
				->where(array('ph.fa_sub_head' => null))
				->where->notEqualTo('id', 12);
			$selectString = $sql->getSqlStringForSqlObject($select);
			return $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		}

		$adapter = $this->adapter;
		$sql = new Sql($adapter);
		$sub0 = new Select(array('sh'=>'fa_sub_head'));
		$sub0->columns(array('ref_id'))
			 ->where(array('sh.type'=>5));
		$select = $sql->select();
		$select->from(array('ph'=>$this->table))
				->where->notin('ph.id',$sub0)
				->where->notEqualTo('id',12);
				
		$selectString = $sql->getSqlStringForSqlObject($select);
		//echo $selectString; exit;
		$results = $adapter->query($selectString, $adapter::QUERY_MODE_EXECUTE)->toArray();
		return $results;
	}

	public function hasFinanceSubheadColumn()
	{
		if ($this->hasFinanceSubheadColumn !== null) {
			return $this->hasFinanceSubheadColumn;
		}
		$platform = strtolower((string) $this->adapter->getPlatform()->getName());
		if ($platform === 'sqlite') {
			$rows = $this->adapter->query('PRAGMA table_info(' . $this->table . ')', Adapter::QUERY_MODE_EXECUTE)->toArray();
			foreach ($rows as $column) {
				if (isset($column['name']) && $column['name'] === 'fa_sub_head') {
					$this->hasFinanceSubheadColumn = true;
					return true;
				}
			}
			$this->hasFinanceSubheadColumn = false;
			return false;
		}

		$rows = $this->adapter->query("SHOW COLUMNS FROM {$this->table} LIKE 'fa_sub_head'", Adapter::QUERY_MODE_EXECUTE)->toArray();
		$this->hasFinanceSubheadColumn = !empty($rows);
		return $this->hasFinanceSubheadColumn;
	}

	public function getFinanceSubheadId($payheadTypeId)
	{
		if (!is_scalar($payheadTypeId) || !ctype_digit((string) $payheadTypeId) || (int) $payheadTypeId < 1) {
			return null;
		}
		$payheadTypeId = (int) $payheadTypeId;

		if ($this->hasFinanceSubheadColumn()) {
			$mapped = $this->getColumn($payheadTypeId, 'fa_sub_head');
			if (is_scalar($mapped) && ctype_digit((string) $mapped) && (int) $mapped > 0) {
				return (int) $mapped;
			}
		}

		$sql = new Sql($this->adapter);
		$select = $sql->select();
		$select->from(array('sh' => 'fa_sub_head'))
			->columns(array('id'))
			->where(array('sh.type' => 5, 'sh.ref_id' => $payheadTypeId))
			->order('sh.id ASC');
		$rows = $this->adapter->query($sql->getSqlStringForSqlObject($select), Adapter::QUERY_MODE_EXECUTE)->toArray();
		if (!empty($rows[0]['id']) && ctype_digit((string) $rows[0]['id'])) {
			return (int) $rows[0]['id'];
		}
		return null;
	}

	public function getLeaveEncashmentSubheadId()
	{
		$payheadTypes = $this->get(array('code' => 'LE'));
		if (count($payheadTypes) !== 1) {
			return null;
		}
		$subheadId = $this->getFinanceSubheadId($payheadTypes[0]['id']);
		if ($subheadId === null) {
			return null;
		}

		$sql = new Sql($this->adapter);
		$select = $sql->select();
		$select->from(array('sh' => 'fa_sub_head'))
			->columns(array('id'))
			->join(array('h' => 'fa_head'), 'h.id = sh.head', array())
			->where(array('sh.id' => $subheadId, 'sh.type' => 5));
		$rows = $this->adapter->query($sql->getSqlStringForSqlObject($select), Adapter::QUERY_MODE_EXECUTE)->toArray();
		return empty($rows) ? null : $subheadId;
	}

	public function getPayrollNetPayableOptions($onlyNetPayable = false)
	{
		$sql = new Sql($this->adapter);
		$select = $sql->select();
		$join = $this->hasFinanceSubheadColumn() ? 'sh.id = pht.fa_sub_head' : 'sh.ref_id = pht.id';
		$select->from(array('pht' => $this->table))
			->columns(array(
				'payhead_type_id' => 'id',
				'payhead_type' => 'head_type',
			))
			->join(
				array('sh' => 'fa_sub_head'),
				$join,
				array(
					'subhead_id' => 'id',
					'subhead_code' => 'code',
					'subhead_name' => 'name',
				)
			)
			->join(
				array('h' => 'fa_head'),
				'h.id = sh.head',
				array(
					'head_id' => 'id',
					'head_name' => 'name',
				)
			)
			->where(array('sh.type' => 5))
			->order(array('pht.head_type ASC', 'sh.code ASC'));
		if ($onlyNetPayable) {
			$select->where->nest
				->like('pht.head_type', '%Net Payable%')
				->or
				->equalTo('pht.code', 'SNP')
				->unnest;
		}
		$selectString = $sql->getSqlStringForSqlObject($select);
		return $this->adapter->query($selectString, $this->adapter::QUERY_MODE_EXECUTE)->toArray();
	}
}
