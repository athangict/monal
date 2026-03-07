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
use Stock\Model As Stock;
use Administration\Model As Administration;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Hr\Model As Hr;
class DispatchController extends AbstractActionController
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
		$this->_id = $this->params()->fromRoute('id');		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');		
		//$this->_dir =realpath($fileManagerDir);
		//$this->_safedataObj =  $this->SafeDataPlugin();
		$this->_userloc = $this->_user->location;  
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	/**
	 * index of the Batch Controller
	 */
	public function indexAction()
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
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		//$assigned_act = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'adm_activity');
		//$assigned_act_array = explode(',',$assigned_act);
		foreach($subRoles as $subRoles);
		if($subRoles['role']==100 || $subRoles['role']==100){
			$dispatchs = $this->getDefinedTable(Stock\DispatchTable::class)->getDateWiseAdmin('dispatch_date',$year,$month);
		}else{
			$dispatchs = $this->getDefinedTable(Stock\DispatchTable::class)->getDateWise('dispatch_date',$year,$month,$this->_userloc, $admin_loc_array, $role_flag);
		}
		
		
		return new ViewModel( array(
				'title' => "Goods Dispatch",
				'dispatchs' => $dispatchs,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'minYear' => $this->getDefinedTable(Stock\DispatchTable::class)->getMin('dispatch_date'),
				'data' => $data,
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
		) );
	}
	
	/**
	 * Add Dispatch
	 */
	public function adddispatchAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['from_location'],'prefix');
			$date = date('ym',strtotime($form['dispatch_date']));
			$tmp_DCNo = $location_prefix."DC".$date;
			$results = $this->getDefinedTable(Stock\DispatchTable::class)->getMonthlyDC($tmp_DCNo);
		
			$dc_no_list = array();	
			foreach($results as $result):
				array_push($dc_no_list, substr($result['challan_no'], 11));
			endforeach;
			$next_serial = max($dc_no_list) + 1;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "000".$next_serial; break;
				case 2: $next_dc_serial = "00".$next_serial;  break;
				case 3: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;       break;
			}	
			$challan_no = $tmp_DCNo.$next_dc_serial;
			//$transporter = ($form['fcb_transport'] == 1)?$form['transporter_fcb']:$form['transporter_nonfcb'];
			$data =array(
					'dispatch_date' => $form['dispatch_date'],
					'challan_no' => $challan_no,
					'from_location' => $form['from_location'],
					'to_location' => $form['to_location'],
					'activity' => 1,
					'fcb_transport' => $form['fcb_transport'],
					'transporter' => 0,
					'vehicle_no' => 0,
					'party' => 0,
					'note' => $form['note'],
					'goodrequest_no' => $form['goodrequest'],
					'tracking_no' => $form['tracking_no'],
					'status' => 2,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>";print_r($data); exit;
			$result = $this->getDefinedTable(Stock\DispatchTable::class)->save($data);
			
			/*if($result>0):
				$item= $form['item'];
				$batch= $form['batch'];
				$from_balance = $form['from_balance'];
				$quantity= $form['quantity'];
				$uom= $form['uom'];
				$basic_uom= $form['basic_uom'];
				$basic_quantity= $form['basic_quantity'];
				$remarks = $form['remarks'];
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && is_numeric($quantity[$i])):
						$dispatch_detail_data = array(
							'dispatch' => $result,
							'item' => $item[$i],
							'batch' => $batch[$i],
							'uom' => $uom[$i],
							'quantity' => $quantity[$i],
							'basic_uom' => $basic_uom[$i],
							'basic_quantity' => $basic_quantity[$i],
							'remarks' => $remarks[$i],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$dispatch_detail_data = $this->_safedataObj->rteSafe($dispatch_detail_data);
						$this->getDefinedTable(Stock\DispatchDetailsTable::class)->save($dispatch_detail_data);
					endif;
				endfor;*/
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new dispatch :".$challan_no);
				return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$result));
			
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new dispatch");
				return $this->redirect()->toRoute('dispatch');
			endif;
		endif;
                $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
                $admin_loc_array = explode(',',$admin_locs);
		return new ViewModel( array(
				'title' 		=> "Add Dispatch",
				'user_location' => $this->_userloc,
				'regionObj'     => $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
                'admin_locs' =>$this->getDefinedTable(Administration\LocationTable::class)->get(array('id'=>$admin_loc_array)),
				'activities'  	=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'parties'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
				'employee_driver' => $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.position_title'=>'32')),
				'user_location' => $this->_userloc,
				'userRoleObj'  => $this->getDefinedTable(Administration\UsersTable::class),
				'itemObj'  => $this->getDefinedTable(Stock\ItemTable::class),
				'goodrequest'  => $this->getDefinedTable(Stock\GoodsRequestTable::class)->getGoodRequest(array('gr.status'=>11)),
				'userID' => $this->_author,
		));
	}
	/**
	 * Add Dispatch Details
	 */
	public function adddispatchdtlsAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			$goodrequest =$this->getDefinedTable(Stock\GoodsRequestTable::class)->getColumn(array('gr_no'=>$form['goodrequest_no']),'id'); 
			foreach($this->getDefinedTable(Stock\GRDetailsTable::class)->get(array('gr'=>$goodrequest)) as $goodreq):
				$data =array(
				'dispatch' => $form['dispatch_id'],
				'item' =>  $goodreq['item_id'],
				'batch' =>  1,
				'uom' =>  $goodreq['uom_id'],
				'quantity' =>  $goodreq['requested_qty'],
				//'basic_quantity' =>  $form['basic_quantity'],
				//'remarks' =>  $form['remarks'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->save($data);
		endforeach;
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new dispatch :".$challan_no);
				return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$form['dispatch_id']));
			
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new dispatch");
				return $this->redirect()->toRoute('dispatch');
			endif;
		endif;
                $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
                $admin_loc_array = explode(',',$admin_locs);
		$ViewModel = new ViewModel(array(
			'title' 		=> "Add Dispatch Details",
			'dispatch'=>$this->getDefinedTable(Stock\DispatchTable::class)->get($this->_id),
			'st_opening'=>$this->getDefinedTable(Stock\OpeningStockTable::class),
			'userRoleObj'  => $this->getDefinedTable(Administration\UsersTable::class),
			'itemObj'  => $this->getDefinedTable(Stock\ItemTable::class),
			'userID' => $this->_author,
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
		
	}
	/**
	 * Add Dispatch Details
	 */
	public function editdispatchdtlsAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			$data =array(
				'id' => $this->_id,
				'dispatch' => $form['dispatch_id'],
				'item' =>  $form['item'],
				'batch' =>  1,
				'uom' =>  $form['uom'],
				'quantity' =>  $form['quantity'],
				//'basic_quantity' =>  $form['basic_quantity'],
				'remarks' =>  $form['remarks'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>";print_r($data); exit;
			$result = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully added new dispatch details");
				return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$form['dispatch_id']));
			
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new dispatch");
				return $this->redirect()->toRoute('dispatch');
			endif;
		endif;
                $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
                $admin_loc_array = explode(',',$admin_locs);
		$ViewModel = new ViewModel(array(
			'title' 		=> "Add Dispatch Details",
			'dispatchObj'=>$this->getDefinedTable(Stock\DispatchTable::class),
			'dispatch_dtls'=>$this->getDefinedTable(Stock\DispatchDetailsTable::class)->get($this->_id),
			'st_opening'=>$this->getDefinedTable(Stock\OpeningStockTable::class),
			'userRoleObj'  => $this->getDefinedTable(Administration\UsersTable::class),
			'itemObj'  => $this->getDefinedTable(Stock\ItemTable::class),
			'uom'  => $this->getDefinedTable(Stock\UomTable::class)->getAll(),
			'userID' => $this->_author,
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
		
	}
	/**
	 * get items according to activity
	 */
	public function getitemactivityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$activity_id = 1;
		$source_loc = $form['from_location'];
		$batchitems = $this->getDefinedTable(Stock\BatchTable::class)->getDispatchItems(array('b.status'=>'3','d.location'=>$source_loc));
		$movingitems = $this->getDefinedTable(Stock\MovingItemTable::class)->getDispatchItems(array('m.location'=>$source_loc));
		
		$items .="<option value=''></option>";
		foreach($batchitems as $batchitem):
			$items .="<option value='".$batchitem['item_id']."'>".$batchitem['name']."</option>";
		endforeach;
		foreach($movingitems as $movingitem):
			$items .="<option value='".$movingitem['item_id']."'>".$movingitem['name']."</option>";
		endforeach;
		
		echo json_encode(array(
					'items' => $items,
		));
		exit;
	}
	
	/**
	 * get details of dispatch
	 */
	public function getdetailsAction()
	{
		$this->init();		
		$form = $this->getRequest()->getpost();		
		$item_id = $form['item_id'];
		$source_loc = $form['source_loc'];
		$destination_loc = $form['destination_loc'];		
		
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
	
		foreach($selected_item as $item);		
		$item_valuation = $item['valuation'];		
		$basic_uom_code = $this->getDefinedTable(Stock\UomTable::class)->getColumn($item['uom'],'code');		
		$basic_uom .="<option value='".$item['uom']."'>".$basic_uom_code."</option>";		
		$batchObj = $this->getDefinedTable(Stock\BatchTable::class);		
		/***** Select UOM Options *****/
		$itemuoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$item_id));
		$select_uom .="<option value=''></option>";
		foreach($selected_item as $item):
			$select_uom .="<option value='".$item['st_uom_id']."'>".$item['st_uom_code']."</option>";
		endforeach;
		foreach($itemuoms as $itemuom):
			$select_uom .="<option value='".$itemuom['uom_id']."'>".$itemuom['uom_code']."</option>";
		endforeach;
		
		if($item_valuation == 1)://Wt Movining Average/Food Grain
			$batch = "<option value=''>N/A</option>";
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
			$source_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'quantity');
			$destination_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$destination_loc),'quantity');
			$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
			$destination_qty = (is_numeric($destination_qty))?$destination_qty:"0.00";
			//$batch_id = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'batch');
			//$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom'); 
			$batch_uom = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'uom');
			echo json_encode(array(
					'batch' => $batch,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					'source_qty' => $source_qty,
					'destination_qty' => $destination_qty,
					'basic_uom' => $basic_uom,
					
			));
		else: //$item_valuation == 0/FIFO/Agency
			//$batchs = $batchObj->get(array('b.item'=>$item_id));
			$batchs = $batchObj->getSalesBatch(array('b.item'=>$item_id,'d.location'=>$source_loc));
			$select_batch .="<option value=''></option>";
			foreach($batchs as $batch):
				if($batch['end_date'] == "0000-00-00" || $batch['end_date'] == ""):
					$select_batch .="<option value='".$batch['id']."'>".$batch['batch']."</option>";
				endif;
			endforeach;
			//$max_batch_id = $batchObj->getMax('id',array('item'=>$item_id));
			$max_batch_id = $batchObj->getMaxbatch('b.id',array('b.item'=>$item_id,'d.location'=>$source_loc));
			$batch_uom = $batchObj->getColumn(array('id'=>$max_batch_id,'item'=>$item_id),'uom');
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$source_qty = $batchdltObj->getColumn(array('batch'=>$max_batch_id,'location'=>$source_loc),'quantity');
			$destination_qty = $batchdltObj->getColumn(array('batch'=>$max_batch_id,'location'=>$destination_loc),'quantity');
			$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
			$destination_qty = (is_numeric($destination_qty))?$destination_qty:"0.00";
			echo json_encode(array(
					'batch' => $select_batch,
					'latest_batch' => $max_batch_id,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					'source_qty' => $source_qty,
					'destination_qty' => $destination_qty,
					'basic_uom' => $basic_uom,
			));
		endif;
		exit;
	}
	
	/**
	 * get Stock Balance from batch and moving_items
	 */
	public function getstockbalanceAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$batch_id = $form['batch_id'];
		$source_loc = $form['source_loc'];
		$destination_loc = $form['destination_loc'];
		$item_id = $form['item_id'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		$item_valuation = $item['valuation'];
		
		if($item_valuation == 1)://Wt Movining Average/Food Grain
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
			$source_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'quantity');
			$destination_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$destination_loc),'quantity');
			$batch_uom = $movingitemObj->getColumn(array('item'=>$item_id),'uom');
		else: //$item_valuation == 0/FIFO/Agency
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$source_qty = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$source_loc),'quantity');
			$destination_qty = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$destination_loc),'quantity');
			$batch_uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');	
		endif;
		$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
		$destination_qty = (is_numeric($destination_qty))?$destination_qty:"0.00";
		
		echo json_encode(array(
				'source_qty' => $source_qty,
				'destination_qty' => $destination_qty,
				'batch_uom' => $batch_uom,
		));
		exit;
	}
	
	/**
	 * get converted qty Action
	 */
	public function getconvertedqtyAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$item_id = $form['item_id'];
		$batch_id = $form['batch_id'];
		$source_loc = $form['source_loc'];
		$destination_loc = $form['destination_loc'];
		$selected_uom = $form['uom_id'];
		$dispatch_qty = $form['dispatch_qty'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);		
		$item_valuation = $item['valuation'];
		$basic_uom = $item['uom'];		
		$batchObj = $this->getDefinedTable(Stock\BatchTable::class);
		$itemuomObj = $this->getDefinedTable(Stock\ItemUomTable::class);
		
		if($item_valuation == 1)://Wt Movining Average/Food Grain		
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);		
			$source_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'quantity');
			$destination_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$destination_loc),'quantity');				
			//$batch_id = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'batch');
			//$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');
			$batch_uom = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'uom');
		else: //$item_valuation == 0/FIFO/Agency
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$source_qty = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$source_loc),'quantity');
			$destination_qty = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$destination_loc),'quantity');		
			$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');
			
		endif;
		
		$batch_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$batch_uom),'conversion');
		$selected_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$selected_uom),'conversion');			
		$source_basic_qty = ($batch_uom == $basic_uom)?$source_qty: $source_qty * $batch_uom_conversion;
		$source_converted_qty = ($basic_uom == $selected_uom)?$source_basic_qty: $source_basic_qty / $selected_uom_conversion;		
		$destination_basic_qty = ($batch_uom == $basic_uom)?$destination_qty: $destination_qty * $batch_uom_conversion;
		$destination_converted_qty = ($basic_uom == $selected_uom)?$destination_basic_qty: $destination_basic_qty / $selected_uom_conversion;		
		$dispatch_basic_qty = ($basic_uom == $selected_uom)?$dispatch_qty : $dispatch_qty * $selected_uom_conversion;
		
		echo json_encode(array(
				'source_qty' => $source_converted_qty,
				'destination_qty' => $destination_converted_qty,
				'dispatch_basic_qty' => $dispatch_basic_qty,
		));
		exit;
	}
	
	/**
	 * get Basic Quantity
	 */
	public function getbasicqtyAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$dispatch_qty = $form['dispatch_qty'];
		$item_id = $form['item_id'];
		$selected_uom = $form['uom_id'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		
		$basic_uom = $item['uom'];
		
		$selected_uom_conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id,'uom'=>$selected_uom),'conversion');
		
		$basic_qty = ($basic_uom == $selected_uom)?$dispatch_qty : $dispatch_qty * $selected_uom_conversion;
		echo json_encode(array(
				'basic_qty' => $basic_qty,
		));
		exit;
	}
	/**
	 * get to location according to from location
	 */
	public function gettolocationAction()
	{
		$this->init();
		
		$ViewModel = new ViewModel(array(
				'from_location' => $this->_id,	
				'tripObj' => $this->getDefinedTable(Stock\TripTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * View Goods Dispatch
	 */
	public function viewdispatchAction()
	{
		$this->init();		
		$params = explode("-", $this->_id);
	/*	if($params['1'] == '1' && $params['2'] > 0){
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
			if($flag == "0") {
				$notify = array('id' => $params['2'], 'flag'=>'1');
               	$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
			}				
		}	*/	
		$dispatch_ID = $params['0'];		
		return new ViewModel(array(
				'title' => 'View Goods Dispatch',
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'usersObj' => $this->getDefinedTable(Administration\UsersTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
				'batch_detailsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'dispatchs' => $this->getDefinedTable(Stock\DispatchTable::class)->get($dispatch_ID),
				'dispatch_detailsObj' => $this->getDefinedTable(Stock\DispatchDetailsTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'request_detailsObj' => $this->getDefinedTable(Stock\GRDetailsTable::class),
				'requestObj' => $this->getDefinedTable(Stock\GoodsRequestTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'movingitemObj' => $this->getDefinedTable(Stock\MovingItemTable::class),	
				'transchargeObj' => $this->getDefinedTable(Stock\TransportChargeTable::class),
				'ScalarconversionObj' => $this->getDefinedTable(Stock\ScalarConversionTable::class),
				'user_location' => $this->_userloc,
				'userRoleObj'  => $this->getDefinedTable(Administration\UsersTable::class),
				'userID' => $this->_author,
                'movingitemspObj' => $this->getDefinedTable(Stock\MovingItemSpTable::class),
				'stopeningObj'=>$this->getDefinedTable(Stock\OpeningStockTable::class),
		));
	}
	
	/**
	 * edit goods dispatch action
	 */
	public function editdispatchAction()
	{
		$this->init();	
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			//$transporter = ($form['fcb_transport'] == 1)?$form['transporter_fcb']:$form['transporter_nonfcb'];
			$data =array(
					'id' => $form['dispatch_id'],
					'dispatch_date' => $form['dispatch_date'],
					'from_location' => $form['from_location'],
					'to_location' => $form['to_location'],
					'fcb_transport' => 0,
					'transporter' => 0,
					'party' => 0,
					'vehicle_no' => 0,
					'note' => $form['note'],
					'status' => 2,
					'author' =>$this->_author,
					'modified' =>$this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\DispatchTable::class)->save($data);
		/*	if($result>0):
				
				$details_id   = $form['details_id'];
				$item= $form['item'];
				$batch= $form['batch'];
				$from_balance = $form['from_balance'];
				$quantity= $form['quantity'];
				$uom= $form['uom'];
				$basic_uom= $form['basic_uom'];
				$basic_quantity= $form['basic_quantity'];
				$remarks = $form['remarks'];
				$delete_rows = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->getNotIn($details_id, array('dispatch' => $result));
				
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && is_numeric($quantity[$i])):
						$dispatch_detail_data = array(
								'id' => $details_id[$i],
								'dispatch' => $result,
								'item' => $item[$i],
								'batch' => $batch[$i],
								'uom' => $uom[$i],
								'quantity' => $quantity[$i],
								'basic_uom' => $basic_uom[$i],
								'basic_quantity' => $basic_quantity[$i],
								'remarks' => $remarks[$i],
								'author' =>$this->_author,
								'modified' =>$this->_modified,
						);
						$dispatch_detail_data = $this->_safedataObj->rteSafe($dispatch_detail_data);
						$this->getDefinedTable(Stock\DispatchDetailsTable::class)->save($dispatch_detail_data);
					endif;
				endfor;
				
				//deleting deleted table rows form database table;
				//print_r($delete_rows);exit;
				foreach($delete_rows as $delete_row):
				//echo $delete_row['id'];
					$this->getDefinedTable(Stock\DispatchDetailsTable::class)->remove($delete_row['id']);
				endforeach;*/
			if($result):	
				$challan_no = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($form['dispatch_id'],'challan_no');
				$this->flashMessenger()->addMessage("success^ Successfully updated the dispatch");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update dispatch");
			endif;
			return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$form['dispatch_id']));
		endif;
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
                $admin_loc_array = explode(',',$admin_locs);
		return new ViewModel(array(
				'title' => 'Edit Dispatch',
				'regionObj'     	=> $this->getDefinedTable(Administration\RegionTable::class),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj'  	=> $this->getDefinedTable(Administration\ActivityTable::class),
				'parties'   	=> $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
				'dispatchs'		=> $this->getDefinedTable(Stock\DispatchTable::class)->get($this->_id),
				'dispatch_detailsObj' => $this->getDefinedTable(Stock\DispatchDetailsTable::class),
				'itemObj' 	=> $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'uomObj'    => $this->getDefinedTable(Stock\UomTable::class),
                'admin_locs' 	=> $this->getDefinedTable(Administration\LocationTable::class)->get(array('id'=>$admin_loc_array)),
				'batchObj' 	=> $this->getDefinedTable(Stock\BatchTable::class),
				'batch_detailsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingitemObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
				'employee_driver' => $this->getDefinedTable(Hr\EmployeeTable::class)->get(array('e.position_title'=>array(32))),
				'userRoleObj'  => $this->getDefinedTable(Administration\UsersTable::class),
				'userID' => $this->_author,
				'user_location' => $this->_userloc,
				'goodrequest'  => $this->getDefinedTable(Stock\GoodsRequestTable::class)->getAll(),
		));
	}
	
	/**
	 * receive goods Action
	 */
	public function receivedispatchAction()
	{
		$this->init();	
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			$data =array(
					'id' => $form['dispatch_id'],
					'status' => 11,
					'note' => $form['note'],
					'received_by' => $form['received_by'],
					'received_on' => $form['received_date'],
					'author' 	  => $this->_author,					
					'modified'    => $this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Stock\DispatchTable::class)->save($data);
			$challan_no = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($form['dispatch_id'],'challan_no');
			if($result>0):
			/*** Receiving Goods Dispatch ***/
				$item_id         = $form['item'];
				$uom_id          = $form['uom'];
				$details_id  	 = $form['details_id'];
				$accept_qty      = $form['accept_qty'];
				$sound_qty		 = $form['sound_qty'];
				$damage_qty      = $form['damage_qty'];
				$shortage_qty    = $form['shortage_qty'];
				
				for($i=0; $i < sizeof($details_id); $i++):
					if(isset($details_id[$i])):
						$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id[$i],'uom');
						//$conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id[$i],'uom'=>$uom_id[$i]),'conversion');
						
						//$accept_qty[$i] = ($basic_uom == $uom_id[$i])?$accept_qty[$i] : $accept_qty[$i] * $conversion;
						//$sound_qty[$i] = ($basic_uom == $uom_id[$i])?$sound_qty[$i] : $sound_qty[$i] * $conversion;
						//$damage_qty[$i] = ($basic_uom == $uom_id[$i])?$damage_qty[$i] : $damage_qty[$i] * $conversion;
						//$shortage_qty[$i] = ($basic_uom == $uom_id[$i])?$shortage_qty[$i] : $shortage_qty[$i] * $conversion;
						
						$dispatch_detail_data = array(
								'id' 			=> $details_id[$i],
								'dispatch' 		=> $result,
								'accepted_qty'  => $accept_qty[$i],
								'sound_qty'		=> $sound_qty[$i],
								'damage_qty'    => $damage_qty[$i],
								'shortage_qty' 	=> $shortage_qty[$i],
								'author' 		=> $this->_author,
								'modified' 		=> $this->_modified,
						);
						$dispatch_detail_data = $this->_safedataObj->rteSafe($dispatch_detail_data);
						//echo "<pre>"; print_r($dispatch_detail_data);exit;
						$this->getDefinedTable(Stock\DispatchDetailsTable::class)->save($dispatch_detail_data);
					endif;
				endfor;
				$dispatch_destination = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($form['dispatch_id'],'to_location');
				$source_location = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($form['dispatch_id'],'from_location');
				$dispatch_date = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($form['dispatch_id'],'dispatch_date');
				$dispatch_dtls = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch' => $form['dispatch_id']));
				$this->_connection->commit(); 
				if($batchDtlsId){
					$this->flashMessenger()->addMessage("success^ Successfully received challan no. ".$challan_no." . And Location Costing added for the Destination Location.");
				}else{
					$this->flashMessenger()->addMessage("success^ Successfully received challan no. ".$challan_no);
				}
			else:
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("Failed^ Failed to receive dispatch");
			endif;
			return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$form['dispatch_id']));
		endif;
		
		return new ViewModel(array(
				'title' => 'Receive the Dispatched Goods',
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj'  	=> $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj'   	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'dispatchs'		=> $this->getDefinedTable(Stock\DispatchTable::class)->get($this->_id),
				'dispatch_detailsObj' => $this->getDefinedTable(Stock\DispatchDetailsTable::class),
				'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
				'itemObj'  => $this->getDefinedTable(Stock\ItemTable::class), 
				'itemuomObj'=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'uomObj'    => $this->getDefinedTable(Stock\UomTable::class),
				'batchdetailsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'openingstObj' => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'openingstdtlsObj' => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class),
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'userRoleObj'  => $this->getDefinedTable(Administration\UsersTable::class),
				'userID' => $this->_author,
				'user_location' => $this->_userloc,
				'usersObj' => $this->getDefinedTable(Administration\UsersTable::class),
		));
	}
	
	/**
	 * get converted received qty Action
	 */
	public function getconvreceivedqtyAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$item_id = $form['item_id'];
		$to_qty = $form['to_qty'];
		$selected_uom = $form['uom_id'];
		$dispatch_uom = $form['dispatch_uom'];
		$dispatch_qty = $form['dispatch_qty'];
		$shortage_qty = $form['shortage_qty'];
		$damage_qty = $form['damage_qty'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		$basic_uom = $item['uom'];
		
		$itemuomObj = $this->getDefinedTable(Stock\ItemUomTable::class);
			
		$dispatch_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$dispatch_uom),'conversion');
		$selected_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$selected_uom),'conversion');
			
		$dispatch_basic_qty = ($dispatch_uom == $basic_uom)?$dispatch_qty: $dispatch_qty * $dispatch_uom_conversion;
		$dispatch_converted_qty = ($basic_uom == $selected_uom)?$dispatch_basic_qty: $dispatch_basic_qty / $selected_uom_conversion;
	
		$to_basic_qty = ($dispatch_uom == $basic_uom)?$to_qty: $to_qty * $dispatch_uom_conversion;
		$to_converted_qty = ($basic_uom == $selected_uom)?$to_basic_qty: $to_basic_qty / $selected_uom_conversion;
		
		$accept_qty = $dispatch_converted_qty - $shortage_qty;
		$sound_qty = $dispatch_converted_qty - ($shortage_qty + $damage_qty);
		echo json_encode(array(
				'dispatch_qty' => $dispatch_converted_qty,
				'to_qty' => $to_converted_qty,	
				'accept_qty' => $accept_qty,
				'sound_qty'	=> $sound_qty
		));
		exit;
	}
	
	/**
	 * pending goods dispatch action {Dispatch}
	 */
	public function pendingdispatchAction()
	{
		$this->init();	
		$goods_dispatchs = $this->getDefinedTable(Stock\DispatchTable::class)->get($this->_id);
		$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$this->_id));
		//$goodreq_details = $this->getDefinedTable(Stock\GRDetailsTable::class)->get(array('dispatch'=>$this->_id));
		//echo "<pre>"; print_r($goods_dispatchs); exit;
		foreach($goods_dispatchs as $dispatch);
			$data = array(
				'id' => $dispatch['id'],
				'status' => 3,
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified					
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\DispatchTable::class)->save($data); 
			foreach($dispatch_details as $detail):
					$from_item = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$detail['item'],'location'=>$dispatch['from_location']));
					foreach($from_item as $from_items);
					$remaining_qty = $from_items['quantity'] - $detail['quantity'];
					$datafromloc = array(
						'id'	=> $from_items['id'],
						'quantity' => $remaining_qty,
						'author' => $this->_author,
						'created' => $this->_modified,
					);
					$datafromloc   = $this->_safedataObj->rteSafe($datafromloc);
					$fromlocresult = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($datafromloc);
			endforeach;
			if($datafromloc)
					$this->flashMessenger()->addMessage("success^ Successfully dispatched Dispatch No. ".$challan_no." ");
				else{
					$this->flashMessenger()->addMessage("success^ Successfully dispatched Dispatch Received");
				}
				return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$dispatch['id']));
			
		$ViewModel = new ViewModel(array(
				'title' => 'Check Model',
				//'id' => $this->_id,
				//'dispatch_details' => $dspdtl,
				//'dispatchdtlsObj' => $this->getDefinedTable(Stock\DispatchDetailsTable::class),
				//'batchdtlsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingitemObj' => $this->getDefinedTable(Stock\MovingitemTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'=> $this->getDefinedTable(Stock\ItemUomTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
          /**
	 * pending goods dispatch action {Dispatch}
	 */
	public function pendingrectifydispatchAction()
	{
		$this->init();	
		$goods_dispatchs = $this->getDefinedTable(Stock\DispatchTable::class)->get($this->_id);
		$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$this->_id));
		//echo "<pre>"; print_r($dispatch_details); exit;
		foreach($goods_dispatchs as $dispatch);
		$lessThanQty = array();
		foreach($dispatch_details as $dspdtl):
			$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($dspdtl['item_id'],'valuation');
			if($item_valuation == 0):
				$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn(array('batch' => $dspdtl['batch'],'location' => $dispatch['to_location']),'quantity');
			else:
				$qty = $this->getDefinedTable(Stock\MovingitemTable::class)->getColumn(array('item' => $dspdtl['item_id'],'location'=> $dispatch['to_location']),'quantity');
			endif;
			if($qty < $dspdtl['basic_quantity']):
				array_push($lessThanQty,$dspdtl['id']);
			endif;
		endforeach;
		$ViewModel = new ViewModel(array(
				'title' => 'Check Model',
				'id' => $this->_id,
				'dispatch_details' => $lessThanQty,
				'dispatchdtlsObj' => $this->getDefinedTable(Stock\DispatchDetailsTable::class),
				'batchdtlsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
				'movingitemObj' => $this->getDefinedTable(Stock\MovingitemTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'=> $this->getDefinedTable(Stock\ItemUomTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
        /**
	 *
	 *rectify dispatch
	 */
	 public function rectifydispatchAction()
	 {
		$this->init();
		$dispatchs = $this->getDefinedTable(Stock\DispatchTable::class)->get($this->_id); 
		foreach($dispatchs as $dispatch);
		    $dispatch_id = $dispatch['id'];
			$to_location = $dispatch['to_location'];
			$from_location = $dispatch['from_location'];
		    switch($dispatch['status']){
			    // Rectify when the dispatch is in pending process
			    case 2:
					$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$dispatch_id));
					foreach($dispatch_details as $dispatch_detail): 
						$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($dispatch_detail['item_id'],'valuation');
						if($item_valuation == 0):
							$from_loc_batch_details = $this->getDefinedTable(Stock/BatchDetailsTable::class)->get(array('batch' =>$dispatch_detail['batch'],'location'=>$from_location));
							foreach($from_loc_batch_details as $from_loc_batch_detail);
								$from_loc_update_batch_dtls = array(
									'id'              => $from_loc_batch_detail['id'],
									'actual_quantity' => $from_loc_batch_detail['actual_quantity'] + $dispatch_detail['basic_quantity'],
									'quantity'        => $from_loc_batch_detail['quantity'] + $dispatch_detail['basic_quantity'],
									'author'          => $this->_author,
									'modified'        => $this->_modified					
								);
								//print_r($update_batch_dtls); exit;
							$this->getDefinedTable(Stock/BatchDetailsTable::class)->save($from_loc_update_batch_dtls);
						else:
							$from_loc_movingitems= $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('item' =>$dispatch_detail['item_id'],'location'=>$from_location));
							foreach($from_loc_movingitems as $from_loc_movingitem);
								$from_loc_update_movingitems = array(
									'id'              => $from_loc_movingitem['id'],
									'quantity'        => $from_loc_movingitem['quantity'] + $dispatch_detail['basic_quantity'],
									'author'          => $this->_author,
									'modified'        => $this->_modified					
								);
							//print_r($update_batch_dtls); exit;
							$this->getDefinedTable(Stock/MovingItemTable::class)->save($from_loc_update_movingitems);
						endif;
						$update_dispatch_dtls = array(
							'id'             => $dispatch_detail['id'],
							'accepted_qty'   => 0.00,
							'sound_qty'      => 0.00,
							'damage_qty'     => 0.00,
							'author'         => $this->_author,
							'modified'       => $this->_modified					
						);
					    $this->getDefinedTable(Stock/DispatchDetailsTable::class)->save($update_dispatch_dtls);
					endforeach;
					$update_dispatch_status = array(
							'id'             => $dispatch_id,
							'status'         =>1,
							'author'         => $this->_author,
							'modified'       => $this->_modified					
						);
						$this->getDefinedTable(Stock\DispatchTable::class)->save($update_dispatch_status);
						$this->flashMessenger()->addMessage("success^ Successfully rectify the dispatch challan no. ".$dispatch['challan_no']." to Initiated Mode");
					return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$dispatch_id));
			    break;
				// Rectify when the dispatch is in received mode
			    case 10:
					$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$dispatch_id));
					foreach($dispatch_details as $dispatch_detail): 
						$update_dispatch_dtls = array(
							'id'             => $dispatch_detail['id'],
							'accepted_qty'   => 0.00,
							'sound_qty'      => 0.00,
							'damage_qty'     => 0.00,
							'author'         => $this->_author,
							'modified'       => $this->_modified					
						);
					$this->getDefinedTable(Stock/DispatchDetailsTable::class)->save($update_dispatch_dtls);
					endforeach;
					$update_dispatch_status = array(
						'id'             => $dispatch_id,
						'status'         =>2,
						'author'         => $this->_author,
						'modified'       => $this->_modified					
						);
						$this->getDefinedTable(Stock\DispatchTable::class)->save($update_dispatch_status);
						$this->flashMessenger()->addMessage("success^ Successfully rectify the dispatch challan no. ".$dispatch['challan_no']." to Pending Mode");
				    return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$dispatch_id));
				break;
				// Rectify when the dispatch is in committed mode
				case 3:
					$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$dispatch_id));
					foreach($dispatch_details as $dispatch_detail):  			
						$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($dispatch_detail['item_id'],'valuation');
						if($item_valuation == 0):
						    if(sizeof($dispatch_detail['shortage_qty']) > 0):
								$transit_loss_dtls = $this->getDefinedTable(Stock/TransitLossTable::class)->get(array('dispatch' =>$dispatch_detail['dispatch']));
								foreach($transit_loss_dtls as $transit_loss_dtl);
							   //print_r($update_tran_loss_dtls); exit;
								$this->getDefinedTable(Stock/TransitLossTable::class)->remove($transit_loss_dtl['id']);
							endif;
							$to_loc_batch_details = $this->getDefinedTable(Stock/BatchDetailsTable::class)->get(array('batch' =>$dispatch_detail['batch'],'location'=>$to_location));
							foreach($to_loc_batch_details as $to_loc_batch_detail);
							    $to_loc__update_batch_dtls = array(
									'id'              => $to_loc_batch_detail['id'],
									'actual_quantity' => $to_loc_batch_detail['actual_quantity'] - $dispatch_detail['accepted_qty'],
									'quantity'        => $to_loc_batch_detail['quantity'] - $dispatch_detail['accepted_qty'],
									'author'          => $this->_author,
									'modified'        => $this->_modified					
								);
							//print_r($to_loc__update_batch_dtls ); exit;
							$this->getDefinedTable(Stock/BatchDetailsTable::class)->save($to_loc__update_batch_dtls);
					    else:
                            if(sizeof($dispatch_detail['shortage_qty']) > 0):
								$transit_loss_dtls = $this->getDefinedTable(Stock/TransitLossTable::class)->get(array('dispatch' =>$dispatch_detail['dispatch']));
								foreach($transit_loss_dtls as $transit_loss_dtl);
									$this->getDefinedTable(Stock/TransitLossTable::class)->remove($transit_loss_dtl['id']);
							endif;							
							$to_loc_movingitems = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('item' =>$dispatch_detail['item_id'],'location'=>$to_location));	
							foreach($to_loc_movingitems as $to_loc_movingitem);
								$to_loc_update_movingitems = array(
									'id'              => $to_loc_movingitem['id'],
									'quantity'        => $to_loc_movingitem['quantity'] - $dispatch_detail['accepted_qty'],
									'author'          => $this->_author,
									'modified'        => $this->_modified					
								);
							//print_r($to_loc_update_movingitems); exit;
							$this->getDefinedTable(Stock/MovingItemTable::class)->save($to_loc_update_movingitems);
						endif;
						$transportation_charges = $this->getDefinedTable(Stock\TransportChargeTable::class)->get(array('dispatch' =>$dispatch_id));
						foreach($transportation_charges as $transportation_charge):
							if($transportation_charge['invoiced'] >= 0):
								$transportation_invoices = $this->getDefinedTable(Stock\TranspInvDetailsTable::class)->get(array('transport_charge' =>$transportation_charge['id']));
								foreach($trans_inv_dtls as $trans_inv_dtl):
									$transinvs = $this->getDefinedTable(Stock\TransporterInvoiceTable::class)->get(array('id' =>$trans_inv_dtl['transportation_invoice']));
									foreach($transinvs as $transinv):
										$update_trans_inv = array(
											'id'              =>$trans_inv_dtl['id'],
											'total_amount'    =>$transinv['total_amount'] - $trans_inv_dtl['amount'],
											'deduction'       =>$transinv['deduction'] - $trans_inv_dtl['deduction'],
											'payable_amount'  =>$transinv['payable_amount'] - $trans_inv_dtl['payable_amount'],
										);
									endforeach;
									$this->$this->getDefinedTable(Stock\TransporterInvoiceTable::class)->save($update_trans_inv);
									$this->getDefinedTable(Stock\TranspInvDetailsTable::class)->remove($trans_inv_dtl['id']);
								endforeach;
							endif;
							$this->getDefinedTable(Stock\TransportChargeTable::class)->remove($transportation_charge['id']);
						endforeach;
					endforeach;
					$update_dispatch_status = array(
						'id'             => $dispatch_id,
						'status'         =>10,
						'author'         => $this->_author,
						'modified'       => $this->_modified					
						);
						$this->getDefinedTable(Stock\DispatchTable::class)->save($update_dispatch_status);
						$this->flashMessenger()->addMessage("success^ Successfully rectify the dispatch challa no. ".$dispatch['challan_no']." to Received Mode");
					return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$dispatch_id));
				break;	
            }				
        }
	/**
	 *
	 *do dispatch after confirm
	 */
	 public function dodispatchAction()
	 {
		$this->init();
		$goods_dispatchs = $this->getDefinedTable(Stock\DispatchTable::class)->get($this->_id);
		$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$this->_id));
		foreach($goods_dispatchs as $dispatch);
		if($dispatch['activity'] == '2'):
			$this->_connection->beginTransaction(); //***Transaction begins here***//	
			foreach($dispatch_details as $detail):
				$moving_item = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('item'=>$detail['item_id'],'location'=>$dispatch['from_location']));
				foreach($moving_item as $wt);
				$remaining_qty = $wt['quantity'] - $detail['basic_quantity'];				
				$data1 = array(
					'id'	=> $wt['id'],
					'quantity' => $remaining_qty,
					'author' => $this->_author,
					'created' => $this->_modified,
				);
				$data1   = $this->_safedataObj->rteSafe($data1);
				$result1 = $this->getDefinedTable(Stock\MovingItemTable::class)->save($data1);
			endforeach;
		elseif($dispatch['activity'] == "1" || $dispatch['activity'] == "7" ):
		    $this->_connection->beginTransaction(); //***Transaction begins here***//	
			foreach($dispatch_details as $detail):
				$batchs = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch' => $detail['batch'],'location'=>$dispatch['from_location']));
				foreach($batchs as $batch);
				$remaining_qty = $batch['quantity'] - $detail['basic_quantity'];
				$actual_remaining = $batch['actual_quantity'] - $detail['basic_quantity'];
				$data1 = array(
					'id'	=> $batch['id'],
					'actual_quantity' => $actual_remaining,
					'quantity' => $remaining_qty,
					'author' => $this->_author,
					'created' => $this->_created,
				);
				$data1   = $this->_safedataObj->rteSafe($data1);
				$result1 = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($data1);
			endforeach;
		endif;
		
		if($result1){
			if($dispatch['status'] == "1"){
				$data = array(
						'id'			=>$this->_id,
						'status' 		=> 2,
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
				);
				$data   = $this->_safedataObj->rteSafe($data);				
				$result = $this->getDefinedTable(Stock\DispatchTable::class)->save($data);
				if($result):
				    $notification_data = array(
					    'route'         => 'dispatch',
						'action'        => 'viewdispatch',
						'key' 		    => $this->_id,
						'description'   => 'dispatch of Goods',
						'author'	    => $this->_author,
						'created'       => $this->_created,
						'modified'      => $this->_modified,   
					);
					$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
					if($notificationResult > 0 ){
						/*Get users under destination location with sub role Depoy Manager*/
						$sourceLocation = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($this->_id, 'to_location');
						$depoyManagers = $this->getDefinedTable(Acl\UserroleTable::class)->get(array('subrole'=>'9'));
						foreach($depoyManagers as $row):
						    $user_location_id = $this->getDefinedTable(Acl\UsersTable::class)->getColumn($row['user'], 'location');
						    if($user_location_id == $sourceLocation ):
							    $notify_data = array(
								    'notification' => $notificationResult,
									'user'    	   => $row['user'],
									'flag'    	 => '0',
									'desc'    	 => 'Goods have been dispatched to your location',
									'author'	 => $this->_author,
									'created'    => $this->_created,
									'modified'   => $this->_modified,  
 								);
								$notifyResult = $this->getDefinedTable(Acl\NotifyTable::class)->save($notify_data);
							endif;
						endforeach;
					}
					$this->_connection->commit(); // commit transaction over success
			     	$this->flashMessenger()->addMessage("success^ Successfully dispatched Challan no.".$dispatch['challan_no']);					
				else:
				   $this->_connection->rollback(); // rollback transaction over failure
				   $this->flashMessenger()->addMessage("error^ Cannot dispatch Challan no.".$dispatch['challan_no']);
				endif;
				return $this->redirect()->toRoute('dispatch');
			}
		}
	 }
	/**
	 * confirm dispatch goods received
	 */
	public function confirmreceiveAction()
	{
		$this->init();
		
		$dispatch_id = $this->_id;
		$dispatchs = $this->getDefinedTable(Stock\DispatchTable::class)->get($dispatch_id);
		foreach($dispatchs as $dispatch);
		//echo "<pre>";print_r($dispatch); exit;
		//echo "<pre>";print_r($dispatch); exit;
		//if($dispatch['party'] != '323'):
			/*** Generate Transport No **
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($dispatch['to_location'],'prefix');
			$date = date('ym',strtotime($this->_created));
			$tmp_SerialNo = $location_prefix."TC".$date;
			$results = $this->getDefinedTable(Stock\TransportChargeTable::class)->getMonthlySerialNo($tmp_SerialNo);
			
			if(sizeof($results) <= 0):
				$next_serial = 1;
			else:
				$serial_no_list = array();
				foreach($results as $result):
					array_push($serial_no_list, substr($result['transport_no'], 8));
				endforeach;
				$next_serial = max($serial_no_list) + 1;
			endif;
			switch(strlen($next_serial)){
				case 1: $next_serial_no = "000".$next_serial; break;
				case 2: $next_serial_no = "00".$next_serial;  break;
				case 3: $next_serial_no = "0".$next_serial;   break;
				default: $next_serial_no = $next_serial;      break;
			}
			
			$transport_no = $tmp_SerialNo.$next_serial_no;
			/****** Transit Loss Details *******/
			$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$dispatch_id));
			$dispatch_date = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($dispatch_id,'dispatch_date');
			//Sum of Dispatch Quantities
			foreach($dispatch_details as $disp_detail):
				if($disp_detail['shortage_qty'] > 0):
					//check if item is FIFO or Wt Moving Avg
					$item_valuation =  $this->getDefinedTable(Stock\ItemTable::class)->getColumn($disp_detail['item'],'valuation');
					//echo $item_valuation; 
					//Considering the selling price is always in basic unit
					$selling_price = $this->getDefinedTable(Stock\SellingPriceTable::class)->getColumn(array('item'=>$disp_detail['item']),'selling_price');
					
					$amount = $selling_price * $disp_detail['shortage_qty'];
					$transit_loss = array(
							'dispatch' => $dispatch_id,
							'item' => $disp_detail['item'],
							'batch' => 1,
							'uom' => $disp_detail['uom'],
							'qty_loss' => $disp_detail['shortage_qty'],
							'rate' => $selling_price,
							'amount' => $amount,
							'author' => $this->_author,
							'created' => $this->_created,
							'modified' => $this->_modified					
					);
					$total_amount += $amount;
					$transit_loss = $this->_safedataObj->rteSafe($transit_loss);
					$transit_result = $this->getDefinedTable(Stock\TransitLossTable::class)->save($transit_loss); 
				endif;
			endforeach;
			
			/****** Transport Charge Details ******
			$total_quantity = 0;
			foreach($dispatch_details as $disp_detail):
				//echo "<pre>"; print_r($disp_detail); 
				$item = $this->getDefinedTable(Stock\ItemTable::class)->get($disp_detail['item_id']);
				foreach($item as $itemcol);
				//MT has to be given static MT = 59001
				//echo $itemcol['scalar_uom']."<br>"; 
				//echo $itemcol['net_weight']."<br>"; 
				if($itemcol['scalar_uom'] != 59001){
					$conversion = $this->getDefinedTable(Stock\ScalarConversionTable::class)->getColumn(array('from_uom'=>$itemcol['scalar_uom'],'to_uom'=>59001),'conversion');
					//echo $conversion."<br>";
					$MT_Quantity = $itemcol['net_weight']/$conversion;
				}
				$MT_qty = $MT_Quantity * $disp_detail['basic_quantity'];
				//echo $MT_Quantity;
				$total_quantity += $MT_qty;	
			endforeach; 
			//echo $total_quantity."<br>";
			
                        $trip_id = $this->getDefinedTable(Stock\TripTable::class)->getColumn(array('status' => 1),'id');
                        
			$tripsdtls = $this->getDefinedTable(Stock\TripDtlsTable::class)->get(array('trip' => $trip_id,'source_location'=>$dispatch['from_location'],'destination_location'=>$dispatch['to_location']));
			foreach($tripsdtls as $trip);
			//echo"<pre>"; print_r($trip); 
			
			$check = $trip['hill_distance'] + $trip['plain_distance'];
			if($check > 0):
				$hill_transp_charge = $trip['hill_distance'] * $trip['hill_rate'] * $total_quantity;
				$plain_transp_charge = $trip['plain_distance'] * $trip['plain_rate'] * $total_quantity;
				$total_charge = $hill_transp_charge + $plain_transp_charge;
			else:
				$tripsrev = $this->getDefinedTable(Stock\TripDtlsTable::class)->get(array('trip' => $trip_id,'source_location'=>$dispatch['to_location'],'destination_location'=>$dispatch['from_location']));
				foreach($tripsrev as $trip);
				
				$hill_transp_charge = $trip['hill_distance'] * $trip['hill_rate'] * $total_quantity;
				$plain_transp_charge = $trip['plain_distance'] * $trip['plain_rate'] * $total_quantity;
				$total_charge = $hill_transp_charge + $plain_transp_charge;
			endif;
			
			$transp_charge = $total_charge - $total_amount;
			
			$data = array(
					'transport_no' => $transport_no,
					'transport_date' => date('Y-m-d'),
					'dispatch' => $dispatch_id,
					'location' => $dispatch['to_location'],
					'activity' => $dispatch['activity'],
					'transporter' => $dispatch['party'],
					'source_location' => $dispatch['from_location'],
					'destination_location' => $dispatch['to_location'],
					'hill_distance' => $trip['hill_distance'],
					'hill_rate' => $trip['hill_rate'],
					'plain_distance' => $trip['plain_distance'],
					'plain_rate' => $trip['plain_rate'],
					'qty' => $total_quantity,
					'transportation_charge' => $transp_charge,
					'status' => 1,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified
			);
			
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$result = $this->getDefinedTable(Stock\TransportChargeTable::class)->save($data);
		//endif;//endof fcb_transport == 0
		
		/*** Change in MovingItem/BatchTable and Change the Status in Dispatch Table ***/
		$dispatch_details = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->get(array('dispatch'=>$this->_id));
		//print_r($dispatch_details);exit;
		
		$goodrequest = $this->getDefinedTable(Stock\GoodsRequestTable::class)->get(array('gr_no'=>$dispatch['goodrequest_no']));
		foreach($goodrequest as $goodrequests);
		$opening_date = $this->getDefaultTable('st_opening_location')->select(array('location'=>$dispatch['to_location']));
		foreach($opening_date as $opening);
		//if($opening['opening_date'] <= $dispatch['received_on']):
			foreach($dispatch_details as $detail):
			$item_group = $this->getDefinedTable(Stock\ItemTable::class)->getColumn(array('id'=>$detail['item']),'item_group');
				$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($detail['item'],'valuation');
				//echo "Item Valuation is : ".$item_valuation; exit;
					$moving_item = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$detail['item'],'location'=>$dispatch['to_location']));
					foreach($moving_item as $wt);
					$from_item = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$detail['item'],'location'=>$dispatch['from_location']));
					foreach($from_item as $from_items);
					//$opening_st = $this->getDefinedTable(Stock\OpeningStockTable::class)->get(array('id'=>$from_items['opening_stock']));
					//foreach($opening_st as $opening_sts);
					
					$total_qty = $wt['quantity'] + $detail['accepted_qty'];
					$remaining_qty = $from_items['quantity'] - $detail['quantity'];
						$data1 = array(
							'id'	=> $wt['id'],
							'quantity' => $total_qty,
							'author' => $this->_author,
							'created' => $this->_modified,
						);
						//echo "<pre>"; print_r($data1); exit;
						$data1   = $this->_safedataObj->rteSafe($data1);
						$result1 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data1);
					//endif;
			endforeach;
		//endif;
		//exit;
		
		$disp_data = array(
				'id' => $dispatch_id,
				'status' => 4,
				'author' => $this->_author,
				'modified' => $this->_modified,		
		);
		//echo "<pre>"; print_r($disp_data); exit;
		$disp_data   = $this->_safedataObj->rteSafe($disp_data);
		$result2 = $this->getDefinedTable(Stock\DispatchTable::class)->save($disp_data);
		$goodrequest_data = array(
			'id' => $goodrequests['id'],
			'status' => 7,
			'author' => $this->_author,
			'modified' => $this->_modified,		
		);
		//echo "<pre>"; print_r($goodrequest_data); exit;
		$goodrequest_data   = $this->_safedataObj->rteSafe($goodrequest_data);
		$result3 = $this->getDefinedTable(Stock\GoodsRequestTable::class)->save($goodrequest_data);
		$challan_no = $this->getDefinedTable(Stock\DispatchTable::class)->getColumn($dispatch_id,'challan_no');
		if($result2){
			if($result)
				$this->flashMessenger()->addMessage("success^ Successfully Committed Dispatch No. ".$challan_no." ");
			else{
				$this->flashMessenger()->addMessage("success^ Successfully Committed Dispatch Received");
			}
		}else{
			$this->flashMessenger()->addMessage("error^ Failed to Commit the Received Dispatch");
		}
		return $this->redirect()->toRoute('dispatch',array('action'=>'viewdispatch','id'=>$dispatch_id));
	}
	
	/**
	 * Revice Dispatch Destination Location
	**/
	public function revicedispatchAction()
	{
		$this->init();
		$dispatch_id = $this->_id;
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id' => $form['dispatch_id'],
				'to_location' => $form['to_location'],
				'note' => $form['note'],
				'author'      => $this->_author,
				'modified'    => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data); 
			$result = $this->getDefinedTable(Stock\DispatchTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated the Dispatch Destination Location");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to update Dispatch Destination Location");
			endif;
			return $this->redirect()->toRoute('dispatch', array('action' => 'viewdispatch', 'id' => $form['dispatch_id']));
		}
		$ViewModel = new ViewModel(array(
				'title' => 'Revice Dispatch',
				'dispatchs' => $this->getDefinedTable(Stock\DispatchTable::class)->get($dispatch_id),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Dispatch Report
	**/
	public function dispatchreportAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'source_location'      => $form['source_location'],
				'destination_location' => $form['destination_location'],
				'start_date'           => $form['start_date'],
				'end_date'             => $form['end_date'],
				'class'             	=> $form['class'],
				'group'             	=> $form['group'],
				'item'             	   => $form['item'],
			);
		}else{
			$data = array(
				'source_location'      => '-1',
				'destination_location' => '-1',
				'start_date'           => '',
				'end_date'             => '',
				'class'             => '-1',
				'group'             => '-1',
				'item'             => '-1',
			);
		}
		
		return new ViewModel(array(
					'title'       => 'Dispatch Report',
					'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
					'regionObj'   => $this->getDefinedTable(Administration\RegionTable::class),
					'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
					'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
					'data'        => $data,
					'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
					'itemObj'     => $this->getDefinedTable(Stock\ItemTable::class),
					'openingObj'     => $this->getDefinedTable(Stock\OpeningStockTable::class),
					'classObj'     => $this->getDefinedTable(Stock\ItemClassTable::class),
					'groupObj'     => $this->getDefinedTable(Stock\ItemGroupTable::class),
					'item'     => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
					'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
					'batchObj'    => $this->getDefinedTable(Stock\BatchTable::class),
		));
	}
	/**2023
	 * Get quantity
	 */
	public function getquantityAction()
	{		
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$from_loc =$form['from_loc'];
		$to_loc =$form['to_loc'];
		$item_id = $this->getDefinedTable(Stock\OpeningStockTable::class)->getColumn(array('item'=>$itemId),'id');
		$opening_dtls = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('opening_stock'=>$item_id));
		foreach($opening_dtls as $opening_dtl);
		$from_loc_quantity = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getColumn(array('opening_stock'=>$opening_dtl['opening_stock'],'location'=>$from_loc),'quantity');
		$to_loc_quantity = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getColumn(array('opening_stock'=>$opening_dtl['opening_stock'],'location'=>$to_loc),'quantity');
		//print_r($opening_dtls);exit;
		echo json_encode(array(
				'to_balance' => $to_loc_quantity,
				'from_balance' => $from_loc_quantity,
		));
		exit;
	}
	/**
	 * Get to location
	 */
	public function getlocationAction()
	{		
		$form = $this->getRequest()->getPost();
		$grno =$form['grno'];
		$goodreqId = $this->getDefinedTable(Stock\GoodsRequestTable::class)->get(array('gr_no'=>$grno));
		foreach($goodreqId as $row);
		$goodreq = $this->getDefinedTable(Administration\LocationTable::class)->get(array('id'=>$row['to_location']));
		
		$to_location = "<option value=''></option>";
		foreach($goodreq as $goodreqs):
			$to_location.="<option value='".$goodreqs['id']."'>".$goodreqs['location']."</option>";
		endforeach;
		echo json_encode(array(
				'to_location' => $to_location,
		));
		exit;
	}
	/**
	 * checkavailability Action
	**/
	public function getcheckavailabilityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		switch ($form['type']) {
			case 'goodrequest':
				$goodrequest =$form['goodrequest'];
				// Check the item code existence ...
				$result = $this->getDefinedTable(Stock\DispatchTable::class)->isPresent('goodrequest_no', $goodrequest);
				break;

			case 'to_location':
			//default:
				$to_location = $form['to_location'];
				// Check the item name existence ...
				$result = $this->getDefinedTable(Stock\DispatchTable::class)->isPresent('to_location', $to_location);
				break;
		}
		
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}
}


