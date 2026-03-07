<?php
namespace Stock\Controller;


use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Stock\Model As Stock;
class GoodsrequestController extends AbstractActionController
{   
	private $_container;
	protected $_table; 		// database table 
    protected $_user; 		// user detail
    protected $_login_id; 	// logined user id
    protected $_login_role; // logined user role
    protected $_author; 	// logined user id
    protected $_created; 	// current date to be used as created dated
    protected $_modified; 	// current date to be used as modified date
    protected $_config; 	// configuration details
    protected $_dir; 		// default file directory
    protected $_id; 		// route parameter id, usally used by crude
    protected $_auth; 		// checking authentication
    protected $_safedataObj; //safedata controller plugin
    protected $_connection; //Transaction connection
    
	public function __construct(ContainerInterface $container)
    {
        $this->_container = $container;
    }
	/**
	 * Laminas Default TableGateway
	 * Table name as the parameter
	 * returns obj
	 */
	public function getDefaultTable($table)
	{
		$this->_table = new TableGateway($table, $this->_container->get('Laminas\Db\Adapter\Adapter'));
		return $this->_table;
	}

	/**
	 * User defined Model
	 * Table name as the parameter
	 * returns obj
	 */
	  public function getDefinedTable($table)
    {
        $definedTable = $this->_container->get($table);
        return $definedTable;
    }
    /**
     * initial set up
     * general variables are defined here
     */
	public function init()
	{
		$this->_auth = new AuthenticationService;
		if(!$this->_auth->hasIdentity()):
			$this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
			$this->redirect()->toRoute('auth', array('action' => 'login'));
		endif;
		
		if(!isset($this->_config)) {
			$this->_config = $this->_container->get('Config');
		}
		$this->_user = $this->identity();		
		$this->_login_id = $this->_user->id;  
		$this->_login_role = $this->_user->role;  
		$this->_author = $this->_user->id;  
		$this->_userloc = $this->_user->location;  
		$this->_id = $this->params()->fromRoute('id');		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');		
		//$this->_dir =realpath($fileManagerDir);
		//$this->_safedataObj =  $this->SafeDataPlugin();
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
	/**
	 * index Action of Goods Request
	 */
	public function goodsrequestAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();				
			$year = $form['year'];
			$month = $form['month'];				
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
			);
		}else{
			$month = isset($_GET['month']) ? $_GET['month'] : 0;
			$year = isset($_GET['year']) ? $_GET['year'] : 0;
			$month = date('m');
			$year = date('Y');
			$data = array(
					'year' => $year,
					'month' => $month,
			);
		}
		$subRoles = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author));  
		if(sizeof($subRoles) > 0){ $role_flag = true; } else{ $role_flag = false; }	  
		foreach($subRoles as $subRole);
		$user_role=$subRole['role'];
		if($subRole['role']==100||$subRole['role']==99):
		$good_request = $this->getDefinedTable(Stock\GoodsRequestTable::class)->getDateWiseAdmin('gr_date',$year,$month);
		else:$good_request = $this->getDefinedTable(Stock\GoodsRequestTable::class)->getDateWise('gr_date',$year,$month,$this->_userloc, $role_flag);
		endif;
		return new ViewModel( array(
				'title' => 'Goods Request',
				'good_request' => $good_request,
				'minYear' => $this->getDefinedTable(Stock\GoodsRequestTable::class)->getMin('gr_date'),
				'data' => $data,
				'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj'	 => $this->getDefinedTable(Administration\ActivityTable::Class),
				'user'=>$this->_login_id,
				'role'			=> $user_role,
				'user_role'  =>$this->_login_role,
		));
	}
	
	/**
	 * add item action
	 * 
	 */	
	public function addgoodsrequestAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$date = date('ym',strtotime($form['gr_date']));
			$tmp_GRNNo = $location_prefix."GR".$date;
			$results = $this->getDefinedTable(Stock\GoodsRequestTable::class)->getMonthlyGRN($tmp_GRNNo);
			
		
			$gr_no_list = array();
				foreach($results as $result):
					array_push($gr_no_list, substr($result['gr_no'], 11));
				endforeach;
				$next_serial = max($gr_no_list) + 1;
					
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "000".$next_serial; break;
					case 2: $next_dc_serial = "00".$next_serial;  break;
					case 3: $next_dc_serial = "0".$next_serial;   break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$gr_no = $tmp_GRNNo.$next_dc_serial;
			//print_r($gr_no);exit;
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$data = array(
					'gr_no'		 => $gr_no,
					'gr_date'	 => $form['gr_date'],
					'type'		 => $form['gr_type'],
					'from_location' => $form['location'],
					'to_location' 	=> $form['to_location'],
					'activity'	=> 1,				
					'status'	=> 2,
					'note'      => $form['note'],
					'author'	=> $this->_author,
					'created'	=> $this->_created,
					'modified'	=> $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\GoodsRequestTable::class)->save($data);
			/*if($result > 0):
				$item          = $form['item'];
				$uom           = $form['uom'];
				$quantity      = $form['quantity'];
				$stock_qty = $form['stock_qty'];
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
						$gr_details = array(
								'gr' 			=> $result,
								'item'      	=> $item[$i],
								'uom'       	=> $uom[$i],
								'requested_qty' => $quantity[$i],
								'existing_qty'  => $stock_qty[$i],
								'author'    	=> $this->_author,
								'created'   	=> $this->_created,
								'modified'  	=> $this->_modified
						);
						$gr_details   = $this->_safedataObj->rteSafe($gr_details);
						$this->getDefinedTable(Stock\GRDetailsTable::class)->save($gr_details);
					endif;
				endfor;*/
			if($result > 0):
				$this->_connection->commit(); // commit transaction over success
				$this->flashMessenger()->addMessage("success^ Successfully added with new good request ". $gr_no);
				return $this->redirect()->toRoute('goodsrequest', array('action' =>'viewgoodsrequest', 'id' => $result));
			else:
			    $this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to add new Goods Request");
				return $this->redirect()->toRoute('goodsrequest');
			endif;
		endif;
		return new ViewModel(array(
				'title' => 'Add Goods Request',
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities' => $this->getDefinedTable(Administration\ActivityTable::Class)->getAll(),
				'user_location' => $this->_userloc,
		));
	}	
	/**
	 * add good request details
	 * 
	 */	
	public function addgoodsrequestdtlsAction()
	{
		
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			$stockqty_tolocation = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('location'=>$form['to_location'],'item'=>$form['item']));
			if(empty($stockqty_tolocation)):
				$this->flashMessenger()->addMessage("warning^ Add opening stock to your location");
				return $this->redirect()->toRoute('goodsrequest',array('action' => 'viewgoodsrequest', 'id' => $form['gr_id']));
			endif;
			$data = array(
				'gr' 			=> $form['gr_id'],
				'item'      	=> $form['item'],
				'uom'       	=> $form['uom'],
				'requested_qty' => $form['quantity'],
				'existing_qty'  => $form['stock_qty'],
				'author'    	=> $this->_author,
				'created'   	=> $this->_created,
				'modified'  	=> $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\GRDetailsTable::class)->save($data);
				
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New good request details added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to good request details");
			endif;
			return $this->redirect()->toRoute('goodsrequest',array('action' => 'viewgoodsrequest', 'id' => $form['gr_id']));
		endif;
		$ViewModel = new ViewModel(array(
			'title' => 'Add Goods Request details',
			'item' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
			'goodrequest' => $this->getDefinedTable(Stock\GoodsRequestTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * add good request details
	 * 
	 */	
	public function editgoodrequestdtlsAction()
	{
		
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			$data = array(
				'id' 			=> $this->_id,
				'gr' 			=> $form['gr_id'],
				'item'      	=> $form['item'],
				'uom'       	=> $form['uom'],
				'requested_qty' => $form['quantity'],
				'existing_qty'  => $form['stock_qty'],
				'author'    	=> $this->_author,
				'created'   	=> $this->_created,
				'modified'  	=> $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\GRDetailsTable::class)->save($data);
				
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New good request details added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to good request details");
			endif;
			return $this->redirect()->toRoute('goodsrequest',array('action' => 'viewgoodsrequest', 'id' => $form['gr_id']));
		endif;
		$ViewModel = new ViewModel(array(
			'title' => 'Add Goods Request details',
			'item' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
			'uom' => $this->getDefinedTable(Stock\UomTable::class)->getAll(),
			'goodrequestdtls' => $this->getDefinedTable(Stock\GRDetailsTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * Get List of UOM From item_uom Table
	 */
	public function getitembyactivityAction()
	{
		$this->init();
		$activityID = $this->_id;
		$ViewModel = new ViewModel(array(
				'activityID' => $activityID,
				'items' => $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.activity' => $activityID)),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * Get Uom and Stock Qty
	 */
	public function getuomstockqtyAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$item_id = $form['item_id'];
		$location = $form['location'];
		
		$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id,'valuation');
		$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id,'uom');
		
		/***** Select UOM Options *****/
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		$itemuoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$item_id));
		$select_uom .="<option value=''></option>";
		foreach($selected_item as $item):
			$select_uom .="<option value='".$item['st_uom_id']."'>".$item['st_uom_code']."</option>";
		endforeach;
		foreach($itemuoms as $itemuom):
			$select_uom .="<option value='".$itemuom['uom_id']."'>".$itemuom['uom_code']."</option>";
		endforeach;
		
		if($item_valuation == 0): //FIFO
			$batch_ids = $this->getDefinedTable(Stock\BatchTable::class)->get(array('item'=>$item_id));
			//echo "<pre>"; print_r($batch_ids);
			foreach($batch_ids as $batch_id):
				$batch_qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn(array('batch'=>$batch_id['id'],'location'=>$location),'quantity');
				if(isset($batch_qty)):
					$total += $batch_qty; 
				endif;
			endforeach;
		else: //Wt Moving Avg
			$total = $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('item'=>$item_id,'location'=>$location),'quantity');
		endif;
		$total = ($total == null || !is_numeric($total))?'0.00':$total;
		echo json_encode(array(
				'uom' => $select_uom,
				'ba_mov_uom' => $basic_uom,
				'qty' => $total,
		));
		exit;
	}
	/**
	 * Change in Stock Qty on Change of Uom
	 */
	public function getuomchangeAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		
		$item_id = $form['item_id'];
		$uom_id = $form['uom_id'];
		$location = $form['location'];
		
		//basic unit of the item
		$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id,'uom');
		
		//selected uom converion
		$selected_uom_conversion = (int)$this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id,'uom'=>$uom_id),'conversion');
		$selected_uom_conversion = ($selected_uom_conversion == 0)?"1":$selected_uom_conversion;
		
		$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id,'valuation');
		
		$batchObj = $this->getDefinedTable(Stock\BatchTable::class);
		$itemuomObj = $this->getDefinedTable(Stock\ItemUomTable::class);
		if($item_valuation == 1)://Wt Movining Average/Food Grain
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
			
			$total = $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('item'=>$item_id,'location'=>$location),'quantity');
			
			$batch_uom = $movingitemObj->getColumn($item_id,'uom');
			
			$batch_uom_conversion = (int)$itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$batch_uom),'conversion');
			$batch_uom_conversion = ($batch_uom_conversion == 0)?"1":$batch_uom_conversion;
			
			$basic_qty = ($batch_uom == $basic_uom)?$total: $total * $batch_uom_conversion;
			$converted_qty = ($basic_uom == $uom_id)?$basic_qty: $basic_qty / $selected_uom_conversion;
			$converted_qty = ($converted_qty == null || !is_numeric($converted_qty))?'0.00':$converted_qty;
			echo json_encode(array(
					'qty' => $converted_qty,
			));
		else: //$item_valuation == 0/FIFO/Agency
			$batch_ids = $batchObj->get(array('item'=>$item_id));
			foreach($batch_ids as $batch_id):
				$batch_qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn(array('batch'=>$batch_id['id'],'location'=>$location),'quantity');
				if(isset($batch_qty)):
					$total += $batch_qty; 
				endif;
			endforeach;	
		
			$batch_uom = $basic_uom;
			
			$batch_uom_conversion = (int)$itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$batch_uom),'conversion');
			$batch_uom_conversion = ($batch_uom_conversion == 0)?"1":$batch_uom_conversion;
			
			$basic_qty = ($batch_uom == $basic_uom)?$total: $total * $batch_uom_conversion;
			$converted_qty = ($basic_uom == $uom_id)?$basic_qty: $basic_qty / $selected_uom_conversion;
			$converted_qty = ($converted_qty == null || !is_numeric($converted_qty))?'0.00':$converted_qty;
			echo json_encode(array(
					'qty' => $converted_qty,
			));
		endif;
		exit;
	}
	
	/**
	 * view goods request
	 */
	public function viewgoodsrequestAction()
	{
		$this->init();		
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1');
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
        $dispatch_ID = $params['0'];
		
		return new ViewModel(array(
				'title' => 'View Goods Request',
				'statusObj'    => $this->getDefinedTable(Acl\StatusTable::class),
				'userTable'      => $this->getDefinedTable(Administration\UsersTable::class),
				'good_request' => $this->getDefinedTable(Stock\GoodsRequestTable::class)->get($dispatch_ID),
				'gr_details'   => $this->getDefinedTable(Stock\GRDetailsTable::class)->get(array('gr'=>$dispatch_ID)),
				'itemsObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
				'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
				'batchdetailsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingitemObj'   => $this->getDefinedTable(Stock\MovingItemTable::class),
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'stopeningObj'     => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'user_location'   => $this->_userloc,
				'userRoleObj'     => $this->getDefinedTable(Administration\UsersTable::class),
				'userID'          => $this->_author,
		));
	}
	/**
	 * edit goods request action
	 *
	 */	
	public function editgoodsrequestAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();		
			/*$selected_activity = $this->getDefinedTable(Stock\GoodsRequestTable::class)->getColumn($form['grn_id'],'activity');
			if($selected_activity != $form['activity']):
				$this->flashMessenger()->addMessage("error^ Failed to update, Activity is ambiguous");
				return $this->redirect()->toRoute('goodsrequest', array('action'=>'editgoodsrequest','id'=>$form['grn_id']));
			else:
			    $this->_connection->beginTransaction(); //***Transaction begins here***/
				$data = array(
						'id'		=> $form['grn_id'],
						'gr_date'	 => $form['gr_date'],
						'type'		 => $form['gr_type'],
						'from_location' => $form['location'],
						'to_location' 	=> $form['to_location'],
						'activity'	=> 1,
						'note'      => $form['note'],
						'author'	=> $this->_author,					
						'modified'	=> $this->_modified,
				);
				//echo '<pre>';print_r($data);exit;
				$data   = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Stock\GoodsRequestTable::class)->save($data);
			/*	if($result > 0):
					$details_id   = $form['details_id'];
					$item          = $form['item'];
					$uom           = $form['uom'];
					$quantity      = $form['quantity'];
					$stock_qty = $form['stock_qty'];
					$delete_rows = $this->getDefinedTable(Stock\GRDetailsTable::class)->getNotIn($details_id, array('gr' => $result));
					for($i=0; $i < sizeof($item); $i++):
						if(isset($item[$i]) && $item[$i] > 0):
							$gr_details = array(
									'id'            => $details_id[$i],
									'gr' 			=> $result,
									'item'      	=> $item[$i],
									'uom'       	=> $uom[$i],
									'requested_qty' => $quantity[$i],
								    'existing_qty'  => $stock_qty[$i],
									'author'    	=> $this->_author,
									'created'   	=> $this->_created,
									'modified'  	=> $this->_modified
							);
							$gr_details   = $this->_safedataObj->rteSafe($gr_details);
							$this->getDefinedTable(Stock\GRDetailsTable::class)->save($gr_details);
						endif;
					endfor;
					foreach($delete_rows as $delete_row):
						$this->getDefinedTable(Stock\GRDetailsTable::class)->remove($delete_row['id']);
					endforeach;*/
				if($result):
					$this->flashMessenger()->addMessage("success^ Successfully updated good request ".$form['grn_no']);
					return $this->redirect()->toRoute('goodsrequest', array('action' =>'viewgoodsrequest', 'id' => $form['grn_id']));
				else:
				    $this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to update Goods Request");
					return $this->redirect()->toRoute('goodsrequest',array('action'=>'editgoodsrequest','id'=> $form['grn_id']));
				endif;
			//endif;//endif of activity
		endif;
		return new ViewModel(array(
				'title' => 'Edit Goods Request',
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities' => $this->getDefinedTable(Administration\ActivityTable::Class)->getAll(),
				'statusObj'    => $this->getDefinedTable(Acl\StatusTable::class),
				'good_request' => $this->getDefinedTable(Stock\GoodsRequestTable::class)->get($this->_id),
				'gr_details'   => $this->getDefinedTable(Stock\GRDetailsTable::class)->get(array('gr'=>$this->_id)),
				'itemsObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
				'batchdetailsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingitemObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
		));
	}
	/**
	 * define pay :delete paydetail
	 */
	public function deleteitemsAction(){
		$this->init();
		$item_id= $this->_id;
		foreach($this->getDefinedTable(Stock\GRDetailsTable::Class)->get(array('d.id'=>$item_id)) as $row);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$result = $this->getDefinedTable(Stock\GRDetailsTable::Class)->remove($row['id']);
			if($result > 0):
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Paydetail deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete Paydetail");
			endif;
		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();	
		return $this->redirect()->toUrl($redirectUrl);
	}
	/**
	 * delete good request
	 */
	 public function deletegoodrequestAction()
	{
		
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			$gr_id = $form['id'];
		foreach ($this->getDefinedTable(Stock\GoodsRequestTable::class)->get(['gr.id' => $gr_id]) as $row);

		$gr_details = $this->getDefinedTable(Stock\GRDetailsTable::class)->get(['d.gr' => $row['id']]);
		$dispatch_id= $this->getDefinedTable(Stock\DispatchTable::class)->getColumn(array('goodrequest_no' => $row['gr_no']),'id');
		$from_location=$row['from_location'];
		$to_location=$row['to_location'];
		$this->_connection->beginTransaction(); 
		//delete data from disptach detail table
		foreach($this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$dispatch_id)) as $dispatchd):
			//for stock adjustment for to location
			foreach($this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$dispatchd['item'],'location'=>$to_location)) as $stock);
			$data1=array(
				'id'		=> $stock['id'],
				'quantity'	=> $stock['quantity']-$dispatchd['accepted_qty'],
				'author' => $this->_author,
				'modified' => $this->_modified,
				
			);
			$data1   = $this->_safedataObj->rteSafe($data1);
		$result1 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data1);
		foreach($this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$dispatchd['item'],'location'=>$from_location)) as $stock1);
			$data2=array(
				'id'		=> $stock1['id'],
				'quantity'	=> $stock1['quantity']+$dispatchd['accepted_qty'],
				'author' => $this->_author,
				'modified' => $this->_modified,
				
			);
			$data2   = $this->_safedataObj->rteSafe($data2);
		$result2 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data2);
		$result3 = $this->getDefinedTable(Stock\DispatchDetailsTable::Class)->remove($dispatchd['id']);
		endforeach;
		//delete dispatch 
		$result4 = $this->getDefinedTable(Stock\DispatchTable::Class)->remove($dispatch_id);
		foreach($gr_details as $grs):
			$result4 = $this->getDefinedTable(Stock\GRDetailsTable::Class)->remove($grs['id']);

		endforeach;
		$result=$this->getDefinedTable(Stock\GoodsRequestTable::Class)->remove($gr_id);
		//print_r($grs['id']);exit;	
			if($result):
				$this->_connection->commit(); // commit transaction on success
			$this->flashMessenger()->addMessage("success^ Good request deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete");
			endif;
			return $this->redirect()->toRoute('goodsrequest',array('action' => 'goodsrequest'));
		endif;
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Goods Request details',
			'goodreqs' => $this->getDefinedTable(Stock\GoodsRequestTable::class)->get($this->_id),
			
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}



	public function deleteGoodrequestAAction() 
	{
		$this->init();
		$gr_id = $this->_id;
		foreach ($this->getDefinedTable(Stock\GoodsRequestTable::class)->get(['gr.id' => $gr_id]) as $row);
		
		$gr_details = $this->getDefinedTable(Stock\GRDetailsTable::class)->get(['d.gr' => $row['id']]);

		// Check if size of gr_details is more than 0
		if (sizeof($gr_details) > 0) {
			$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();
			return $this->redirect()->toUrl($redirectUrl);
		}

		$this->_connection->beginTransaction(); // Transaction begins here

		$result = $this->getDefinedTable(Stock\GoodsRequestTable::class)->remove($row['id']);
		$result2 = $this->getDefinedTable(Stock\GRDetailsTable::class)->remove(array('gr'=>$row['id']));
		$result3 = $this->getDefinedTable(Stock\DispatchTable::class)->remove(array('goodrequest_no'=>$row['gr_no']));
		$gr_id = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn(array('goodrequest_no'=>$row['gr_no']),'id');
		$result4 = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->remove(array('dispatch'=>$gr_id));
		$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$gr_id));
		foreach($dispatch_details as $detail):
			$moving_item = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$detail['item'],'location'=>$dispatch['to_location']));
					foreach($moving_item as $wt);
					$from_item = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$detail['item'],'location'=>$dispatch['from_location']));
					foreach($from_item as $from_items);
					$total_qty = $wt['quantity'] - $detail['accepted_qty'];
					$remaining_qty = $from_items['quantity'] + $detail['quantity'];
						$data1 = array(
							'id'	=> $wt['id'],
							'quantity' => $total_qty,
							'author' => $this->_author,
							'created' => $this->_modified,
						);
						//echo "<pre>"; print_r($data1); exit;
						$data1   = $this->_safedataObj->rteSafe($data1);
						$result1 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data1);
					
						$datafromloc = array(
							'id'	=> $from_items['id'],
							'quantity' => $remaining_qty,
							'author' => $this->_author,
							'created' => $this->_modified,
						);
						//echo "<pre>"; print_r($datafromloc); exit;
						$datafromloc   = $this->_safedataObj->rteSafe($datafromloc);
						$fromlocresult = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($datafromloc);
						//endif;
			endforeach;
		if ($fromlocresult > 0) {
			$this->_connection->commit(); // commit transaction on success
			$this->flashMessenger()->addMessage("success^ Good request deleted successfully");
		} else {
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete");
		}

		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();
		return $this->redirect()->toUrl($redirectUrl);
	}
	/**/
		public function cancelgoodrequestAction() 
	{
		$this->init();
		$gr_id = $this->_id;
		//foreach ($this->getDefinedTable(Stock\GoodsRequestTable::class)->get(['gr.id' => $gr_id]) as $row);
				$data = array(
						'id'	=> $gr_id,
						'status' => '5',
						'author' => $this->_author,
						'created' => $this->_modified,
					);
					$data   = $this->_safedataObj->rteSafe($data);
					$this->_connection->beginTransaction(); // Transaction begins here
					$result = $this->getDefinedTable(Stock\GoodsRequestTable::class)->save($data);
		if ($result > 0) {
			$this->_connection->commit(); // commit transaction on success
			$this->flashMessenger()->addMessage("success^ Good request cancelled successfully");
		} else {
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to cancel");
		}

		$redirectUrl = $this->getRequest()->getHeader('Referer')->getUri();
		return $this->redirect()->toUrl($redirectUrl);
	}
	/**
	 * commit godds request Action
	 *
	 */
	public function processgrAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();		
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			if($form['action'] == "1") { 
			        /* Send Request */
					$data = array(
						'id'			=> $form['gr_id'],
						'status' 		=> 6,
						'remark'        => $form['remarks'],
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
				    );
					$message = "Successfully Send GRN";
					$desc = "New Good Request send";
					/*Get users under destination location with sub role Depoy Manager*/
					$sourceLocation = $this->getDefinedTable(Stock\GoodsRequestTable::class)->getColumn($form['gr_id'], 'from_location');			
			} elseif($form['action'] == "2"){	 /* Received Request */			
				    $data = array(
						'id'			=> $form['gr_id'],
						'status' 		=> 11,
						'remark'        => $form['remarks'],
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
		            );
					$message = "Successfully Received GRN";
					$desc = "GRN have been received ";
					/*Get users under request location with sub role Depoy Manager*/
					$sourceLocation = $this->getDefinedTable(Stock\GoodsRequestTable::class)->getColumn($form['gr_id'], 'to_location');
			}	
			//print_r($data);exit;	
			$result = $this->getDefinedTable(Stock\GoodsRequestTable::class)->save($data);		
			if($result):
			    	$notification_data = array(
					    'route'         => 'goodsrequest',
						'action'        => 'viewgoodsrequest',
						'key' 		    => $form['gr_id'],
						'description'   => $desc,
						'author'	    => $this->_author,
						'created'       => $this->_created,
						'modified'      => $this->_modified,   
					);
					//print_r($notification_data);exit;
					$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
					//echo $notificationResult; exit;
					if($notificationResult > 0 ){	
						$user_list = $this->getDefinedTable(Administration\UsersTable::class)->get(array('location'=>$sourceLocation ) );
						foreach($user_list as $row):						    
							$role =  explode(",", $row['role']);
							$count=0;
							for($i=0;$i<sizeof($role);$i++){
								if($role[$i]==2){
									$count++;
									break;
								}
							}
						    if($count==1 ):						
							    $notify_data = array(
								    'notification' => $notificationResult,
									'user'    	   => $row['id'],
									'flag'    	 => '0',
									'desc'    	 => $desc,
									'author'	 => $this->_author,
									'created'    => $this->_created,
									'modified'   => $this->_modified,  
 								);
								//print_r($notify_data);exit;
								$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
							endif;
						endforeach;
					}
				$this->_connection->commit(); // commit transaction over success
				$this->flashMessenger()->addMessage("success^".$message);
			else:
			    $this->_connection->rollback(); // rollback transaction over failure
			    $this->flashMessenger()->addMessage("error^ Cannot send good request");			  
			endif;
			return $this->redirect()->toRoute('goodsrequest',array('action'=>'viewgoodsrequest','id'=>$form['gr_id']));
		endif; 		
		$viewModel =  new ViewModel(array(
			'title' => 'GRN',
			'id'  => $this->_id,
			'userID' => $this->_author,
		));	
		$viewModel->setTerminal('false');
        return $viewModel;	
	}
	
	/**
	 * reorder list
	 */
	public function reorderAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$item = $form['item'];
			//$request_type = $form['request_type'];
		}else{
			$location = -1;
			$item = -1;
			//$request_type = -1;
		}
		$reorderdtls = $this->getDefinedTable(Stock\ReOrderTable::class)->getByItemLocationType($item,$location);
		return new ViewModel(array(
				'title' => 'Reorder Level',
				'item_id' => $item,
				'location_id' => $location,
				//'request_type' => $request_type,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'reorderdtls' => $reorderdtls,
				'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
				'batchdetailsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingitemObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
		));
	}
	/**
	 * Get uom
	 */
	public function getuomAction()
	{		
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$locationId =$form['locationId'];
		$item = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$itemId));
		//foreach($item as $items);
		//$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id'=>$items['uom']));
		 $selectedUomId = $item[0]['uom'];
			$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id' => $selectedUomId));
			
			$buom = "<option value=''></option>";
			foreach ($uom as $uoms) {
				$isSelected = ($uoms['id'] == $selectedUomId) ? ' selected' : '';
				$buom .= "<option value='" . $uoms['id'] . "'" . $isSelected . ">" . $uoms['code'] . "</option>";
			}
	/*	$buom = "<option value=''></option>";
		foreach($uom as $uoms):
			$buom.="<option value='".$uoms['id']."'>".$uoms['code']."</option>";
		endforeach;*/
		$st_quantity = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$itemId,'location'=>$locationId));
		foreach($st_quantity as $rows):
			$quantity=$rows['quantity'];
		endforeach;
		echo json_encode(array(
				'uom' => $buom,
				'quantity' => $quantity,
		));
		exit;
	}
}
