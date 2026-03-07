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
use Sales\Model As Sales;
use Purchase\Model As Purchase;
use DateTime;
class SamController extends AbstractActionController
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
		$this->_id = $this->params()->fromRoute('id');		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');		
		//$this->_dir =realpath($fileManagerDir);
		$this->_userloc = $this->_user->location;  
		//$this->_safedataObj =  $this->SafeDataPlugin();
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
	
	/**
	 * index Action of Stock Adjustments
	 */
	public function indexAction()
	{
		$this->init();
	    $year=0;
		$month=0;
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
		}
		$month = ($month == 0)? date('m'):$month;
		$year = ($year == 0)? date('Y'):$year;
		
		$data = array(
				'year' => $year,
				'month' => $month,
		);
		$subRoles = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author));  
		if(sizeof($subRoles) > 0){ $role_flag = true; } else{ $role_flag = false; }
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		$admin_loc_array = explode(',',$admin_locs);
		$Sams = $this->getDefinedTable(Stock\SamTable::class)->getDateWise('sam_date',$year,$month,$this->_userloc, $admin_loc_array, $role_flag);
		return new ViewModel( array(
				'title'      => 'Stock Adjustment',
				'statusObj'  => $this->getDefinedTable(Acl\StatusTable::class),
				'Sams'       => $Sams,
				'activityObj'  => $this->getDefinedTable(Administration\ActivityTable::class),
				'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
				'data'  => $data,
		) );
	}
    /**
	 * get Stock Balance from batch and moving_items
	 */
	public function getStockBalanceAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$batch_id = $form['batch_id'];
		$source_loc = $form['source_loc'];
		$item_id = $form['item_id'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		$item_valuation = $item['valuation'];
		
		if($item_valuation == 1)://Wt Movining Average/Food Grain
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
			$source_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'quantity');
			$batch_uom = $movingitemObj->getColumn(array('item'=>$item_id),'uom');
		else: //$item_valuation == 0/FIFO/Agency
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$source_qty = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$source_loc),'quantity');
			$batch_uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');	
		endif;
		$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
		
		echo json_encode(array(
				'source_qty' => $source_qty,
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
		$selected_uom = $form['uom_id'];
		$sam_qty = $form['sam_qty'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);		
		$item_valuation = $item['valuation'];
		$basic_uom = $item['uom'];		
		$batchObj = $this->getDefinedTable(Stock\BatchTable::class);
		$itemuomObj = $this->getDefinedTable(Stock\ItemUomTable::class);
		
		if($item_valuation == 1)://Wt Movining Average/Food Grain		
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);		
			$source_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'quantity');
			$batch_uom = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'uom');
		else: //$item_valuation == 0/FIFO/Agency
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$source_qty = $batchdltObj->getColumn(array('batch'=>$batch_id,'location'=>$source_loc),'quantity');
			$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');
			
		endif;
		
		$batch_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$batch_uom),'conversion');
		$selected_uom_conversion = $itemuomObj->getColumn(array('item'=>$item_id,'uom'=>$selected_uom),'conversion');			
		$source_basic_qty = ($batch_uom == $basic_uom)?$source_qty: $source_qty * $batch_uom_conversion;
		$source_converted_qty = ($basic_uom == $selected_uom)?$source_basic_qty: $source_basic_qty / $selected_uom_conversion;		
		$sam_basic_qty = ($basic_uom == $selected_uom)?$sam_qty : $sam_qty * $selected_uom_conversion;
		
		echo json_encode(array(
				'source_qty' => $source_converted_qty,
				'sam_basic_qty' => $sam_basic_qty,
		));
		exit;
	}
    /**
	 * add Stock Adjustments action
	 * 
	 */	
	public function addsamAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			$sam_type = $this->getDefinedTable(Stock\SamTypeTable::class)->getColumn(array('id' => $form['sam_type']),'type');
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$date = date('ym',strtotime($form['sam_date']));
			if($sam_type == '0'):		    
				$tmp_SAMNo = $location_prefix."SA".$date; 
			else:
			$tmp_SAMNo = $location_prefix."FS".$date;
			endif;
			$results = $this->getDefinedTable(Stock\SamTable::class)->getMonthlySAM($tmp_SAMNo);
			if(sizeof($results) > 0 ):
				$sa_no_list = array();
				foreach($results as $result):
				//print_r($result);
				array_push($sa_no_list, substr($result['sam_no'], 8));
				endforeach;
				$next_serial = max($sa_no_list) + 1;				
			else:
				$next_serial = "0001";
			endif;
				
			switch(strlen($next_serial)){
				case 1: $next_sa_serial = "000".$next_serial; break;
				case 2: $next_sa_serial = "00".$next_serial;  break;
				case 3: $next_sa_serial = "0".$next_serial;   break;
				default: $next_sa_serial = $next_serial;       break;
			}	
			$sam_no = $tmp_SAMNo.$next_sa_serial;
			$data = array(
					'sam_no'		=> $sam_no,
					'sam_date'	=> $form['sam_date'],
					'location' 	=> $form['location'],
					'activity'	=> $form['activity'],
					'remark'    => $form['note'],
					'status'	=> 1,					
					'author'	=> $this->_author,
					'created'	=> $this->_created,
					'modified'	=> $this->_modified,
			);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Stock\SamTable::class)->save($data);
		if($result>0):
			$item= $form['item'];
			$sam_type= $form['sam_type'];
			$batch= $form['batch'];
			$from_balance = $form['from_balance'];
			$quantity= $form['quantity'];
			$uom= $form['uom'];
			$basic_uom= $form['basic_uom'];
			$basic_quantity= $form['basic_quantity'];
			$remarks = $form['remarks'];
			for($i=0; $i < sizeof($item); $i++):
				if(isset($item[$i]) && is_numeric($quantity[$i])):
					$sam_detail_data = array(
						'sam' => $result,
						'sam_type' => $sam_type[$i],
						'item' => $item[$i],
						'batch' => $batch[$i],
						'uom' => $uom[$i],
						'quantity' => $quantity[$i],
						'basic_uom' => $basic_uom[$i],
						'basic_quantity' => $basic_quantity[$i],
						'remark' => $remarks[$i],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$sam_detail_data = $this->_safedataObj->rteSafe($sam_detail_data);
					$this->getDefinedTable(Stock\SamDetailsTable::class)->save($sam_detail_data);
				endif;
			endfor;
			$this->flashMessenger()->addMessage("success^ Successfully added the stock adjustment :".$sam_no);
			return $this->redirect()->toRoute('sam',array('action'=>'viewsam','id'=>$result));
		else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new sam details");
			return $this->redirect()->toRoute('sam');
		endif;
		endif;
		return new ViewModel(array(
				'title' => 'Add Stock Adjustment',
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'samTypes' => $this->getDefinedTable(Stock\SamTypeTable::class)->getAll(),
				'user_location' => $this->_userloc,
		));
	}	
	
	/**
	 * edit Stock Adjustment
	 *
	 */
	public function editsamAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):
		  $form = $this->getRequest()->getpost();
		  //echo"<pre>"; print_r($form); exit; 
		  if($form['location'] == ""):
		     $this->flashMessenger()->addMessage("error^ Failed to update, Please select location");
		     return $this->redirect()->toRoute('sam', array('action'=>'editsam','id'=>$form['sam_id']));
		  else:
			 $data = array(
			 		'id'        => $form['sam_id'], 				
					'sam_date'	=> $form['sam_date'],
					'location' 	=> $form['location'],
					'activity'	=> $form['activity'],
					'remark'    => $form['note'],
					'status'	=> 1,	
			 		'author' 	=> $this->_author,			 				 		
					'modified'	=> $this->_modified,
			);
			
		   $data   = $this->_safedataObj->rteSafe($data);
		   $result = $this->getDefinedTable(Stock\SamTable::class)->save($data);
		  if($result>0):
				
				$details_id   = $form['details_id'];
				$item= $form['item'];
				$sam_type= $form['sam_type'];
				$batch= $form['batch'];
				$from_balance = $form['from_balance'];
				$quantity= $form['quantity'];
				$uom= $form['uom'];
				$basic_uom= $form['basic_uom'];
				$basic_quantity= $form['basic_quantity'];
				$remarks = $form['remarks'];
				$delete_rows = $this->getDefinedTable(Stock\SamDetailsTable::class)->getNotIn($details_id, array('sam' => $result));
				
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && is_numeric($quantity[$i])):
						$sam_detail_data = array(
								'id' => $details_id[$i],
								'sam' => $result,
								'sam_type' => $sam_type[$i],
								'item' => $item[$i],
								'batch' => $batch[$i],
								'uom' => $uom[$i],
								'quantity' => $quantity[$i],
								'basic_uom' => $basic_uom[$i],
								'basic_quantity' => $basic_quantity[$i],
								'remark' => $remarks[$i],
								'author' =>$this->_author,
								'modified' =>$this->_modified,
						);
						$sam_detail_data = $this->_safedataObj->rteSafe($sam_detail_data);
						$this->getDefinedTable(Stock\SamDetailsTable::class)->save($sam_detail_data);
					endif;
				endfor;
				
				//deleting deleted table rows form database table;
				//print_r($delete_rows);exit;
				foreach($delete_rows as $delete_row):
				//echo $delete_row['id'];
					$this->getDefinedTable(Stock\SamDetailsTable::class)->remove($delete_row['id']);
				endforeach;
				
				$sam_no = $this->getDefinedTable(Stock\SamTable::class)->getColumn($form['sam_id'],'sam_no');
				$this->flashMessenger()->addMessage("success^ Successfully updated sam no. ".$sam_no);
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update sam details");
			endif;
			return $this->redirect()->toRoute('sam',array('action'=>'viewsam','id'=>$form['sam_id']));
		endif;	    
		
		endif;
		return new ViewModel(array(
				'title'        => 'Edit Goods Request',
				'regions'      => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'   => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'statusObj'    => $this->getDefinedTable(Acl\StatusTable::class),
				'samTypes'    => $this->getDefinedTable(Stock\SamTypeTable::class)->getAll(),
				'sam'          => $this->getDefinedTable(Stock\SamTable::class)->get($this->_id),
				'sam_details'  => $this->getDefinedTable(Stock\SamDetailsTable::class)->get(array('sam'=>$this->_id)),
				'itemsObj'     => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'   => $this->getDefinedTable(Stock\ItemUomTable::class),
				'uomObj'       => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj' 	   => $this->getDefinedTable(Stock\BatchTable::class),
				'movingitemObj'=> $this->getDefinedTable(Stock\MovingItemTable::class),
				'batch_detailsObj'=> $this->getDefinedTable(Stock\BatchDetailsTable::class),
		));
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
	 * Get List of UOM From item_uom Table
	 */
	public function getuombyitemAction()
	{
		$this->init();
		$itemID = $this->_id;
		$ViewModel = new ViewModel(array(
				'itemID' => $itemID,
				'items'	 => $this->getDefinedTable(Stock\ItemTable::class)->get($itemID),
				'item_uoms' => $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$itemID)),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * view Stock Adjustment
	 */
	public function viewsamAction()
	{
		$this->init();
		return new ViewModel(array(
				'title' => 'View Stock Adjustment Memo',
				'statusObj'    => $this->getDefinedTable(Acl\StatusTable::class),
				'activityObj'  => $this->getDefinedTable(Administration\ActivityTable::class),
				'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
				'userTable'    => $this->getDefinedTable(Acl\UsersTable::class),
				'sam'          => $this->getDefinedTable(Stock\SamTable::class)->get($this->_id),
				'sam_details'  => $this->getDefinedTable(Stock\SamDetailsTable::class)->get(array('sam'=>$this->_id)),
				'batchObj'     => $this->getDefinedTable(Stock\BatchTable::class),
				'itemsObj'     => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'   => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	/**
	 * Pending sam Action 
         * Check the existing quantity	
	**/
	
	public function pendingsamAction(){
		$this->init();
		$sams = $this->getDefinedTable(Stock\SamTable::class)->get($this->_id);	
		$sam_details = $this->getDefinedTable(Stock\SamDetailsTable::class)->get(array('sam' =>$this->_id));	
		foreach($sams as $sam);	
		$dump_qty = array();
		foreach($sam_details as $sam_detail):
			$sam_type = $this->getDefinedTable(Stock\SamTypeTable::class)->getColumn(array('id' => $sam_detail['sam_type']),'type');	
			$item_valuation =  $this->getDefinedTable(Stock\ItemTable::class)->getColumn($sam_detail['item_id'],'valuation');
			if($item_valuation == 0):
				$qty= $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn(array('batch'=>$sam_detail['batch'],'location'=>$sam['location']),'quantity');
			else:
			   $qty= $this->getDefinedTable(Stock\MovingItemTable::class)->getColumn(array('item'=>$sam_detail['item_id'],'location'=>$sam['location']),'quantity');
			endif;
			if($sam_type == 0):
				if($qty < $sam_detail['basic_quantity']):
					array_push($dump_qty,$sam_detail['id']);
				endif;
			endif;			    
		endforeach;		    
		$ViewModel = new ViewModel(array(
			'title'           => 'Pending Stock Adjustment',
			'id'              => $this->_id,
			'sam_details'     => $dump_qty,
			'samtypeObj'      => $this->getDefinedTable(Stock\SamTypeTable::class),
			'samObj'          => $this->getDefinedTable(Stock\SamTable::class),
			'samdetailsObj'   => $this->getDefinedTable(Stock\SamDetailsTable::class),
			'itemsObj'        => $this->getDefinedTable(Stock\ItemTable::class),
			'batchdtlsObj' 	  => $this->getDefinedTable(Stock\BatchDetailsTable::class),
			'movingitemsObj'  => $this->getDefinedTable(Stock\MovingItemTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * commit godds request Action
	 *
	 */
	public function commitsamAction()
	{   
		$this->init();
		$sams = $this->getDefinedTable(Stock\SamTable::class)->get($this->_id);	
		$sam_details = $this->getDefinedTable(Stock\SamDetailsTable::class)->get(array('sam' =>$this->_id));	
		    foreach($sams as $sam);
			foreach($sam_details as $sam_detail):
				$sam_type = $this->getDefinedTable(Stock\SamTypeTable::class)->getColumn(array('id' => $sam_detail['sam_type']),'type');
				$dump_qty = $sam_detail['basic_quantity'] ;		
				$item_valuation =  $this->getDefinedTable(Stock\ItemTable::class)->getColumn($sam_detail['item_id'],'valuation');
				//Item Valuation 0 for Agency (FIFO)
				if($item_valuation == 0):
					$batchdtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$sam_detail['batch'],'location'=>$sam['location']));
					foreach($batchdtls as $batchdtl);
					$batchdtls_qty = $batchdtl['quantity'];
					$batchdtls_actual_qty = $batchdtl['actual_quantity'];
					// Stock Adjustment Type 0 for Stock Adjustment 				
					if($sam_type == 0):
						$dump = array(
							'id'          => $batchdtl['id'],
							'quantity'    => $batchdtls_qty - $dump_qty,
							'actual_quantity'    => $batchdtls_actual_qty - $dump_qty,
							'author' => $this->_author,
						    'created' => $this->_modified,
						);
						$result1 = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($dump);	
					else:
						$adjustqty = array(
							'id'          => $batchdtl['id'],
							'quantity'    => $batchdtls_qty + $dump_qty,
							'actual_quantity'    => $batchdtls_actual_qty + $dump_qty,

						);
						$result1 = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($adjustqty);
					endif;
				//Item Valuation 1 for Food Grain (Moving Average)				
				else:
					$movingitems = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('item'=>$sam_detail['item_id'],'location'=>$sam['location']));
					foreach($movingitems as $movingitem);
					$movingitems_qty = $movingitem['quantity'];
					// Stock Adjustment Type 0 for Stock Adjustment 				
					if($sam_type == 0):
						$dump = array(
							'id'          => $movingitem['id'],
							'quantity'    => $movingitems_qty - $dump_qty,
						);
						//print_r($dump); exit;
						$result1 = $this->getDefinedTable(Stock\MovingItemTable::class)->save($dump);
					else:
						$adjustqty = array(
							'id'          => $movingitem['id'],
							'quantity'    => $movingitems_qty + $dump_qty,
						);
						$result1 = $this->getDefinedTable(Stock\MovingItemTable::class)->save($adjustqty);
					endif;
				endif;
		endforeach;
		 if($result1 > 0):
			$data1 = array(
				'id'			=>$this->_id,
				'status' 		=> 3,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
			);
			$result1 = $this->getDefinedTable(Stock\SamTable::class)->save($data1);
			$this->flashMessenger()->addMessage("success^ Successfully commited Stock Adjustment");
		else:
			$this->flashMessenger()->addMessage("error^ fail to commit Stock Adjustment");
		endif;
	return $this->redirect()->toRoute('sam',array('action'=>'viewsam','id'=>$this->_id));
	}

	/**
	 * Item Conversion 
	**/
	public function conversionAction()
	{
	    $this->init();
		$month=0;
		$year=0;
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
		}
		$month = ($month == 0)? date('m'):$month;
		$year = ($year == 0)? date('Y'):$year;

		$data = array(
				'year' => $year,
				'month' => $month,
		);
	    $conversionDtls = $this->getDefinedTable(Stock\ItemConversionTable::class)->getDateWise('conversion_date', $year, $month);
		return new ViewModel(array(
				'title' => 'Item Conversion',
				'details'  => $conversionDtls,
				'data'   => $data,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
		));
    }
   	/**
	 * Add item conversion
	**/
	public function addconversionAction()
	{
	    $this->init();
	    if($this->getRequest()->isPost()):
            $form = $this->getRequest()->getpost();
            $basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($form['item'],'uom');
            if($basic_uom != $form['uom']){
                 $conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$form['item'],'uom'=>$form['uom']),'conversion');
                 if($conversion > 0){
                    $basic_qty =  $form['quantity'] * $conversion;
                 }else{
                    	$this->flashMessenger()->addMessage("error^ Unsuccessful to save the data, Please check uom conversion");
		                return $this->redirect()->toRoute('sam', array('action' =>'addconversion'));
                 }
            }else{ $basic_qty =  $form['quantity']; }
            
            $converted_basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($form['item1'],'uom');
            if($converted_basic_uom != $form['uom1']){
                 $conversion1 = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$form['item1'],'uom'=>$form['uom1']),'conversion');
                 if($conversion1 > 0){
                    $basic_qty1 =  $form['converted_qty'] * $conversion1;
                 }else{
                    	$this->flashMessenger()->addMessage("error^ Unsuccessful to save the data, Please check uom conversion");
		                return $this->redirect()->toRoute('sam', array('action' =>'addconversion'));
                 }
            }else{ $basic_qty1 =  $form['converted_qty']; }

  			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$date = date('ym',strtotime($form['conversion_date']));
			$tmp_DCNo = $location_prefix."C".$date;
			$results = $this->getDefinedTable(Stock\ItemConversionTable::class)->getMonthlyDC($tmp_DCNo);

			$dc_no_list = array();
			foreach($results as $result):
				array_push($dc_no_list, substr($result['conversion_no'], 7));
			endforeach;
			$next_serial = max($dc_no_list) + 1;

			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "000".$next_serial; break;
				case 2: $next_dc_serial = "00".$next_serial;  break;
				case 3: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;       break;
			}
			$conversion_no = $tmp_DCNo.$next_dc_serial;
            $data = array(
               'conversion_no'         =>  $conversion_no,
               'conversion_date'       =>  $form['conversion_date'],
               'location'              =>  $form['location'],
               'item'                  =>  $form['item'],
               'batch'                 =>  $form['batch'],
               'uom'                   =>  $form['uom'],
               'issued_qty'            =>  $form['quantity'],
               'basic_uom'             =>  $basic_uom,
               'basic_qty'             =>  $basic_qty,
               'landed_cost'           =>  $form['landed_cost'],
               'converted_item'        =>  $form['item1'],
               'converted_item_uom'    =>  $form['uom1'],
               'converted_qty'         =>  $form['converted_qty'],
               'converted_basic_uom'   =>  $converted_basic_uom,
               'converted_basic_qty'   =>  $basic_qty1,
               'converted_item_batch'  =>  '',
               'converted_item_elc'     => '',
               'note'                   => $form['note'],
               'status'                 => '1',
               'author'                 => $this->_author,
               'created'                => $this->_created,
	           'modified'               => $this->_modified
            );
         $result = $this->getDefinedTable(Stock\ItemConversionTable::class)->save($data);
         if($result>0){
                $this->flashMessenger()->addMessage("success^ Item Conversion details Successfully saved");
                return $this->redirect()->toRoute('sam', array('action' =>'viewconversion','id'=>$result));
         }else{
                $this->flashMessenger()->addMessage("error^ Unsuccessful to saved Item Conversion Details");
                return $this->redirect()->toRoute('sam', array('action' =>'addconversion'));
         }
	    endif;
		return new ViewModel(array(
				'title' => 'Add Item Conversion',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
				'locationtypeObj' => $this->getDefinedTable(Administration\locationtypeTable::class),
		));
    }
   	/**
	 * View Item Conversion details
	**/
	public function viewconversionAction()
	{
	    $this->init();
	    $details = $this->getDefinedTable(Stock\ItemConversionTable::class)->get($this->_id);
		return new ViewModel(array(
				'title'            => 'Item Conversion',
                'details'          => $details,
                'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj'          => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'           => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'         => $this->getDefinedTable(Stock\BatchTable::class),
				'costingObj'       => $this->getDefinedTable(Stock\ConversionCostingTable::class),
				'costingHeadObj'   => $this->getDefinedTable('Stock\CostingHeadTable'),
	   ));
    }
    /**
	 * Update item conversion
	**/
	public function editconversionAction()
	{
	    $this->init();
		
		if($this->getRequest()->isPost()):
            $form = $this->getRequest()->getpost();
            $basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($form['item'],'uom');
            if($basic_uom != $form['uom']){
                 $conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$form['item'],'uom'=>$form['uom']),'conversion');
                 if($conversion > 0){
                    $basic_qty =  $form['quantity'] * $conversion;
                 }else{
                    	$this->flashMessenger()->addMessage("error^ Unsuccessful to save the data, Please check uom conversion");
		                return $this->redirect()->toRoute('sam', array('action' =>'addconversion'));
                 }
            }else{ $basic_qty =  $form['quantity']; }
            
            $converted_basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($form['item1'],'uom');
            if($converted_basic_uom != $form['uom1']){
                 $conversion1 = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$form['item1'],'uom'=>$form['uom1']),'conversion');
                 if($conversion1 > 0){
                    $basic_qty1 =  $form['converted_qty'] * $conversion1;
                 }else{
                    	$this->flashMessenger()->addMessage("error^ Unsuccessful to save the data, Please check uom conversion");
		                return $this->redirect()->toRoute('sam', array('action' =>'addconversion'));
                 }
            }else{ $basic_qty1 =  $form['converted_qty']; }

  			
            $data = array(
				'id'                   => $form['item_conversion_id'],
               //'conversion_no'         =>  $conversion_no,
               'conversion_date'       =>  $form['conversion_date'],
               'location'              =>  $form['location'],
               'item'                  =>  $form['item'],
               'batch'                 =>  $form['batch'],
               'uom'                   =>  $form['uom'],
               'issued_qty'            =>  $form['quantity'],
               'basic_uom'             =>  $basic_uom,
               'basic_qty'             =>  $basic_qty,
               'landed_cost'           =>  $form['landed_cost'],
               'converted_item'        =>  $form['item1'],
               'converted_item_uom'    =>  $form['uom1'],
               'converted_qty'         =>  $form['converted_qty'],
               'converted_basic_uom'   =>  $converted_basic_uom,
               'converted_basic_qty'   =>  $basic_qty1,
               'converted_item_batch'  =>  '',
               'converted_item_elc'     => '',
               'note'                   => $form['note'],
               //'status'                 => '1',
               'author'                 => $this->_author,
              //'created'                => $this->_created,
	           'modified'               => $this->_modified
            );
         $result = $this->getDefinedTable(Stock\ItemConversionTable::class)->save($data);
         if($result>0){
                $this->flashMessenger()->addMessage("success^ Item Conversion details Successfully updated");
                return $this->redirect()->toRoute('sam', array('action' =>'viewconversion','id'=>$result));
         }else{
                $this->flashMessenger()->addMessage("error^ Unsuccessful to update Item Conversion Details");
                return $this->redirect()->toRoute('sam', array('action' =>'editconversion'));
         }
	    endif;
		
        $details = $this->getDefinedTable(Stock\ItemConversionTable::class)->get($this->_id);
		return new ViewModel(array(
				'title'       => 'Edit Item Conversion',
                'details'     => $details,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj'     => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
				'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
				'movingObj' => $this->getDefinedTable(Stock\MovingItemTable::class),
				'batchdltObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
		));
    }
   	/**
	 * Costing of Item Conversion
	**/
	public function conversioncostingAction()
	{
	    $this->init();
	    if($this->getRequest()->isPost()):
            $form = $this->getRequest()->getpost();
            //echo"<pre>"; print_r($form); exit;
            $costing_head = $form['costing_head'];
            $elc = $form['elc'];
            $value = $form['value'];
            $formula = $form['formula'];
            $conversion_id = $form['conversion_id'];
            $recalculate = $form['elc_recalculate'];
            $this->_connection->beginTransaction(); //***Transaction begins here***//
            if($recalculate == "1"){
               $this->getDefinedTable(Stock\ConversionCostingTable::class)->remove(array('conversion'=>$conversion_id));
            }
            for($i =0; $i<sizeof($costing_head); $i++){
                 $data = array(
                          'conversion'    => $conversion_id,
                          'costing_head'  => $costing_head[$i],
                          'value'         => $value[$i],
                          'formula'       => $formula[$i],
                          'elc'           => $elc[$i],
                          'author'        => $this->_author,
                          'created'       => $this->_created,
	                      'modified'      => $this->_modified
                 );
                 $result = $this->getDefinedTable(Stock\ConversionCostingTable::class)->save($data);
                 if($result && $elc[$i] == '1'){
                      $data1 = array(
                                 'id'                   => $conversion_id,
                                 'converted_item_elc'   => $value[$i],
                                 'status'               => '2',
                                 'modified'             => $this->_modified
                         );
                      $this->getDefinedTable(Stock\ItemConversionTable::class)->save($data1);
                 }
            }
           if($result > 0){
				 $this->_connection->commit(); // commit transaction over success
				 $this->flashMessenger()->addMessage("success^ ELC generated and Saved. Please commit to procces further !");
				 return $this->redirect()->toRoute('sam', array('action' =>'viewconversion', 'id' => $conversion_id));
				}
		   else{
					 $this->_connection->rollback(); // rollback transaction over failure
					 $this->flashMessenger()->addMessage("error^ Fail to save costing Details");
		     }
        else:
 	     $convertedDtls = $this->getDefinedTable(Stock\ItemConversionTable::class)->get($this->_id);
         foreach($convertedDtls as $row);
         $item_id = $row['converted_item'];
         $batch_id = $row['batch'];
         $itemDtls = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
        endif;
		$ViewModel = new ViewModel(array(
				'title'            => 'Elc Costing',
                'itemDtls'         => $itemDtls,
                'id'               => $this->_id,
                'batch'            => $batch_id,
                'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj'          => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'           => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'       => $this->getDefinedTable(Stock\ItemUomTable::class),
				'itemConversionObj'=> $this->getDefinedTable(Stock\ItemConversionTable::class),
				'formulaObj'       => $this->getDefinedTable(Stock\CostingFormulaTable::class),
				'formulaDtlObj'    => $this->getDefinedTable('Stock\CostFormulaDtlsTable'),
				'batchObj'         => $this->getDefinedTable(Stock\BatchTable::class),
				'chargeObj'        => $this->getDefinedTable('Stock\ChargesTaxTable')
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
    }
    
    /**
	 * Commit to item conversion
	**/
	public function commitconversionAction()
	{
	    $this->init();
	    $dlts = $this->getDefinedTable(Stock\ItemConversionTable::class)->get($this->_id);
	    foreach($dlts as $row);
	    $converted_item  = $row['converted_item'];
	    $converted_item_elc = $row['converted_item_elc'];
	    $location = $row['location'];
	    $qty =  $row['converted_basic_qty'];
	    $conversion_date = $row['conversion_date'];
	    $item_uom = $row['converted_basic_uom'];
	    $itemValuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($converted_item, 'valuation');
	    $location_costing = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($converted_item, 'transportation_charge');
	    $location_formula = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($converted_item, 'location_formula');
					
		/* Check for existing batch */
		$trip_id = $this->getDefinedTable(Stock\TripTable::class)->getColumn(array('status'=>1),'id');
		if($location_costing == 1):
			$charge_sum = '100';
		endif;
		$item_margin = $this->getDefinedTable(Stock\MarginTable::class)->getSUM(array('item'=>$converted_item),'margin');
		if($location_costing == 1):
			$existing_Batches =  $this->getDefinedTable(Stock\BatchTable::class)->getExistingBatch(array('location'=>$location,'item'=>$converted_item, 'landed_cost'=>$converted_item_elc,'trip'=>$trip_id,'location_formula'=>$location_formula,'charge_sum'=>$charge_sum,'margin_sum'=>$item_margin,'status'=>'3'));
		else:
			$existing_Batches =  $this->getDefinedTable(Stock\BatchTable::class)->getExistingBatch(array('location'=>$location,'item'=>$converted_item, 'landed_cost'=>$converted_item_elc,'margin_sum'=>$item_margin,'status'=>'3'));
		endif;
		$multiple_array = array();
		foreach($existing_Batches as $existings):
			array_push($multiple_array,$existings['id']);
		endforeach;
		$max_existing_batch = max($multiple_array);
		$existing_Batch =  $this->getDefinedTable(Stock\BatchTable::class)->getExistingBatch($max_existing_batch);
		
        $this->_connection->beginTransaction(); //***Transaction begins here***//
        if(sizeof($existing_Batch)>0){
            foreach($existing_Batch as $dtl):
		       $existing_batch_id = $dtl['id'];
	           $batch_qty = $dtl['quantity'];
	           //update the batch table's qty
	           $existing_batchDtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('location'=>$dtl['location'],'batch'=>$dtl['id']));
						    if(sizeof($existing_batchDtls)>0){
							   foreach($existing_batchDtls as $batchdtl_row):
							       $existing_batchdtl_id = $batchdtl_row['id'];
							       $existing_actual_qty = $batchdtl_row['actual_quantity'];
							       $existing_qty = $batchdtl_row['quantity'];
							   endforeach;
							}
					        $new_actual_qty = $existing_actual_qty + $qty;
						 	$new_qty = $existing_qty + $qty;
							$new_batch_qty = $batch_qty + $qty;
							/*Update the batch and batch details with new quantity */
							$batch_data = array(
								 'id'          => $existing_batch_id,
								 'quantity'    => $new_batch_qty,
								 'modified'    => $this->_modified
			   	            );
							//echo"<pre>"; print_r($batch_data); exit;
			   	            $batchResult = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_data);
							if($batchResult){
								$batch_dtl_data = array(
								 'id'              => $existing_batchdtl_id,
								 'actual_quantity' => $new_actual_qty,
								 'quantity'        => $new_qty,
								 'modified'        => $this->_modified
			   	                );
								$batchDtlResult = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batch_dtl_data);
								if($batchDtlResult){
									if($itemValuation == "1"){
										$movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$location, 'item'=>$converted_item));
										if(sizeof($movingItemDtl) > 0){
											foreach($movingItemDtl as $mvdtl):
												$moving_item_id = $mvdtl['id'];
												$exist_qty = $mvdtl['quantity'];
											endforeach;
											$new_mv_qty =  $exist_qty + $qty;
											$mv_data = array(
												   'id'       => $moving_item_id,
												   'batch'=>$existing_batch_id,
												   'quantity' => $new_mv_qty,
												   'modified' => $this->_modified
											);
											$mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
										}else{
											$cur_location = $location;
											$uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($existing_batch_id,'uom');
											$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($location, 'location_type');
											$marginDtls = $this->getDefinedTable(Stock\MarginTable::class)->get(array('item'=>$converted_item, 'location_type'=>$location_type));
											if(sizeof($marginDtls)>0){
												foreach ($marginDtls as $dtl):
													$margin_value = $dtl['margin'];
												endforeach;
												$unit_uom = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$converted_item, 'costing_uom'=>'1'),'conversion');
                                                if($unit_uom > 0){
                                                     $selling_price = ($converted_item_elc + $converted_item_elc * ($margin_value/100))/$unit_uom;
                                                }else{
                                                	 $this->_connection->rollback(); // rollback transaction over failure
												     $this->flashMessenger()->addMessage("error^ No standard costing UOM set. Please Check");
												     return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
												}
                                                $mv_data = array(
														'item'   		=> $converted_item,
														'uom'    		=> $uom,
														'batch'  		=> $existing_batch_id,
														'location'  	=> $cur_location,
														'quantity'  	=> $qty,
														'selling_price' => $selling_price,
														'author'        => $this->_author,
														'created'       => $this->_created,
														'modified'      => $this->_modified
												);
												$mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
											}
											else{
												$this->_connection->rollback(); // rollback transaction over failure
												$this->flashMessenger()->addMessage("error^ Margin for this item is missing. Please Check");
                                                return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
											}
										}
										if(!$mvResult){
											$this->_connection->rollback(); // rollback transaction over failure
											$this->flashMessenger()->addMessage("error^ Cannot Save the qty in Moving Item table. Please Check");
                                            return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
										}
									}
									
									if($itemValuation == '1'){ 
										$date = strtotime("+2 day");
										$sp_effect_date = date('Y-m-d', $date);
										
										$moving_item_sp = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getMaxRow('id',array('item' => $converted_item));
										$uom = $this->getDefinedTable(Stock\BatchTable::class)->getColumn($existing_batch_id,'uom');
										foreach($moving_item_sp as $misp);
										if($misp['batch'] != $existing_batch_id){
											if($misp['sp_effect_date'] != $sp_effect_date){
												$misp_data = array(
														'item'         => $converted_item,
														'uom'          => $uom,
														'batch'        => $existing_batch_id,
														'costing_item'  => '',
														'sp_effect_date'  => $sp_effect_date,
														'author'        => $this->_author,
														'created'       => $this->_created,
														'modified'      => $this->_modified	
												);
											}else{
												$misp_data = array(
														'id'           => $misp['id'],
														'item'         => $converted_item,
														'uom'          => $uom,
														'batch'        => $existing_batch_id,
														'costing_item'  => '',
														'sp_effect_date'  => $sp_effect_date,
														'author'        => $this->_author,
														'created'       => $this->_created,
														'modified'      => $this->_modified	
												);
											}
											$this->getDefinedTable(Stock\MovingItemSpTable::class)->save($misp_data);
										}
									} else { 
										$sp_effect_date = date("Y-m-d", strtotime("NOW")); 
									}
									$item_details = array(
										'id'       => $this->_id,
										'converted_item_batch'	   => $existing_batch_id,
										'status'   => '3',
										'modified' => $this->_modified
									);
								    $updatecostitem = $this->getDefinedTable(Stock\ItemConversionTable::class)->save($item_details);
									/* Update pr_detail table with batchID */
								}
							}
				    endforeach;
				}else{
					$itemCode = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($converted_item, 'item_code');
					if(strlen($itemCode) == 0){
					    $this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("notice^ Item Code is missing. Please Check");
						return $this->redirect()->toRoute('cost', array('action' => 'elccosting', 'id' => $costing_sheet_id));
				    }
	             $date     = date('y', strtotime($conversion_date));
			     $tmp_PONo = $itemCode.$date;
			   	 $results  = $this->getDefinedTable(Stock\BatchTable::class)->getMonthlyBatch($tmp_PONo);
                 $sheet_no_list = array();
			   	 foreach($results as $result):
			   	   array_push($sheet_no_list, substr($result['batch'], 6));
			   	 endforeach;
			   	 if(sizeof($sheet_no_list)>0){
			   	 $next_serial = max($sheet_no_list) + 1;
			   	 }else{
			   	     $next_serial = 1;
			   	 }
			   	 switch(strlen($next_serial)){
			   	 	case 1: $next_b_serial  = "00".$next_serial;  break;
			   	 	case 2: $next_b_serial  = "0".$next_serial;   break;
			   	 	default: $next_b_serial = $next_serial;      break;
			   	 }
					$batch = $itemCode.$date.$next_b_serial;
			   	    $location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($location, 'location_type');
			   	    $marginDtls = $this->getDefinedTable(Stock\MarginTable::class)->get(array('item'=>$converted_item, 'location_type'=>$location_type));
                   if(sizeof($marginDtls)>0){
			   	    	foreach ($marginDtls as $dtl):
			   	    	    $margin_value = $dtl['margin'];
			   	    	endforeach;
			   	    }
			   	    else{
					    $this->_connection->rollback(); // rollback transaction over failure
			   	        $this->flashMessenger()->addMessage("error^ Margin for this item is missing. Please Check");
			   	        return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
			   	    }
		   	    	 $unit_uom = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$converted_item, 'costing_uom'=>'1'),'conversion');
                     if($unit_uom > 0){
                       $selling_price = ($converted_item_elc + $converted_item_elc * ($margin_value/100))/$unit_uom;
                     }else{
                 	   $this->_connection->rollback(); // rollback transaction over failure
				       $this->flashMessenger()->addMessage("error^ No standard costing UOM set. Please Check");
				       return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
                     }
			   	    $barcode =  $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, 'barcode');
					if($itemValuation == '1'){ $sp_effect_date =  date("Y-m-d", strtotime("Tomorrow")); } else { $sp_effect_date = date("Y-m-d", strtotime("NOW")); }
                    $batch_details = array(
			   	    	             'batch'       => $batch,
			   	    		         'item'        => $converted_item,
			   	    		         'uom'         => $item_uom,
									 'location'    => $location,
			   	    		         'quantity'    => $qty,
                					 'unit_uom'    => $unit_uom,
			   	    		         'barcode'     => $barcode,
			   	    		         'landed_cost' => $converted_item_elc,
			   	    		         'batch_date'  => date('Y-m-d'),
					   	    		 'expiry_date' => '',
			   	    				 'end_date'    => '',
									 'costing'     => '',
									 'trip'        => $trip_id, 
									 'location_formula' => $location_formula,
									 'charge_sum'  => $charge_sum,
									 'margin_sum'  => $item_margin,
									 'status'  => '1',
					   	    		 'author'      => $this->_author,
					   	    		 'created'     => $this->_created,
					   	    		 'modified'    => $this->_modified
			   	    );
			   	    //print_r($batch_details); exit;
			   	   $batchID = $this->getDefinedTable(Stock\BatchTable::class)->save($batch_details);
			   	    //change the status of Costing Sheet
			   	   if($batchID > 0 ){
			   	    	$batchDtls = array(
			   	    			'batch' => $batchID,
			   	    			'location' => $location,
			   	    			'actual_quantity' => $qty,
			   	    			'quantity' =>  $qty,
			   	    			'landed_cost' => $converted_item_elc,
			   	    	        'margin'       => $margin_value,
			   	    			'selling_price' => $selling_price,
			   	    			'author'          => $this->_author,
			   	    			'created'        => $this->_created,
			   	    			'modified'       => $this->_modified
			   	    	);
			   	        $batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls);
			   	    	if($batchDtlsId > 0) {
			   	    		$item_details = array(
			   	    		        'id'          => $this->_id,
			   	    				'converted_item_batch'	   => $batchID,
			   	 	                'status'   => '3',
			   	    		        'modified' => $this->_modified
			   	 	        );
			   	 	        $updatecostitem = $this->getDefinedTable(Stock\ItemConversionTable::class)->save($item_details);
			   	 	   	}
			   	    }
				}
				if($updatecostitem):
                    foreach( $this->getDefinedTable(Stock\ItemConversionTable::class)->get($this->_id) as $row);
                    $source_item = $row['item'];
                    $source_item_conversion_qty = $row['basic_qty'];
                    $source_item_batch = $row['batch'];
                    $source_item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($source_item, 'valuation');
                    if($source_item_valuation == "1"){
				           $movingItemDtl = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('location'=>$location, 'item'=>$source_item));
                           if(sizeof($movingItemDtl)>0):
                             foreach($movingItemDtl as $mvdtl):
						 	    $moving_item_id = $mvdtl['id'];
							    $exist_qty = $mvdtl['quantity'];
				             endforeach;
				             $new_mv_qty =  $exist_qty - $source_item_conversion_qty;
							 $mv_data = array(
								 'id'       => $moving_item_id,
				                 'quantity' => $new_mv_qty,
					             'modified' => $this->_modified
				             );
		                     $mvResult = $this->getDefinedTable(Stock\MovingItemTable::class)->save($mv_data);
                            endif;
                    }else{
                           $sourceBatchDtl = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('location'=>$location, 'batch'=>$source_item_batch));
                           if(sizeof($sourceBatchDtl)>0):
                             foreach($sourceBatchDtl as $mvdtl):
						 	    $batchDtl_id = $mvdtl['id'];
							    $exist_qty = $mvdtl['quantity'];
				             endforeach;
				             $new_qty =  $exist_qty - $source_item_conversion_qty;
							 $mv_data = array(
								 'id'       => $batchDtl_id,
				                 'quantity' => $new_qty,
					             'modified' => $this->_modified
				             );
		                     $mvResult = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($mv_data);
                            endif;
                    }
                    if($mvResult){
				       $this->_connection->commit(); // commit transaction over success
					   $this->flashMessenger()->addMessage("success^ Batching successfully completed with Batch No.".$batch);
					   return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
					}else{
                       $this->_connection->rollback(); // rollback transaction over failure
				       $this->flashMessenger()->addMessage("error^ Failed to update the batch or moving item details. Please check again");
				       return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
                    }
				else:
				   $this->_connection->rollback(); // rollback transaction over failure
				   $this->flashMessenger()->addMessage("error^ Failed batching. Please check again");
				   return $this->redirect()->toRoute('sam', array('action' => 'viewconversion', 'id' => $this->_id));
				endif;	//end of commit
    }
    
    /**
	 * get details of dispatch
	 */
	public function getitemsdtlsAction()
	{
		$this->init();		
		$form = $this->getRequest()->getpost();		
		$item_id = $form['item_id'];
		$source_loc = $form['source_loc'];
		/*$item_id = 172;
		$source_loc = 22;
		$destination_loc = 39;*/
		
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
			$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
			$batch_uom = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'uom');
			echo json_encode(array(
					'batch' => $batch,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					'source_qty' => $source_qty,
					'basic_uom' => $basic_uom,
					
			));
		else: //$item_valuation == 0/FIFO/Agency
			//$batchs = $batchObj->get(array('b.item'=>$item_id));
			$batchs = $batchObj->getSamBatch(array('b.item'=>$item_id,'d.location'=>$source_loc));
			$select_batch .="<option value=''></option>";
			foreach($batchs as $batch):
				if($batch['end_date'] == "0000-00-00" || $batch['end_date'] == ""):
					$select_batch .="<option value='".$batch['id']."'>".$batch['batch']."</option>";
				endif;
			endforeach;
			$max_batch_id = $batchObj->getMaxbatch('b.id',array('b.item'=>$item_id,'d.location'=>$source_loc));
			$batch_uom = $batchObj->getColumn(array('id'=>$max_batch_id,'item'=>$item_id),'uom');
			$batchdltObj = $this->getDefinedTable(Stock\BatchDetailsTable::class);
			$source_qty = $batchdltObj->getColumn(array('batch'=>$max_batch_id,'location'=>$source_loc),'quantity');
			$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
			echo json_encode(array(
					'batch' => $select_batch,
					'latest_batch' => $max_batch_id,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					'source_qty' => $source_qty,
					'basic_uom' => $basic_uom,
			));
		endif;
		exit;
	}
	/**
	 * get Basic Quantity
	 */
	public function getbasicqtyAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$sam_qty = $form['sam_qty'];
		$item_id = $form['item_id'];
		$selected_uom = $form['uom_id'];
		
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		
		$basic_uom = $item['uom'];
		
		$selected_uom_conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id,'uom'=>$selected_uom),'conversion');
		
		$basic_qty = ($basic_uom == $selected_uom)?$sam_qty : $sam_qty * $selected_uom_conversion;
		echo json_encode(array(
				'basic_qty' => $basic_qty,
		));
		exit;
	}
	/**
	 * get details of item conversion
	 */
	public function getdetailsAction()
	{
		$this->init();
		$form = $this->getRequest()->getpost();
		$item_id = $form['item_id'];
		$source_loc = $form['source_loc'];
		$conv_date = $form['conv_date'];
		$selected_item = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		foreach($selected_item as $item);
		$item_valuation = $item['valuation'];
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
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
			$source_qty = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'quantity');
			$source_qty = (is_numeric($source_qty))?$source_qty:"0.00";
			$batch_id = $this->getDefinedTable(Stock\MovingItemSpTable::class)->getBatchColumn(array('item'=>$item_id),$conv_date);
			$batch_code = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'batch');
			$batch_uom = $batchObj->getColumn(array('id'=>$batch_id,'item'=>$item_id),'uom');
			$batch = "<option value='".$batch_id."'>".$batch_code."</option>";
			$elc = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getColumn(array('batch'=>$batch_id,'location'=>$source_loc),'landed_cost');
			echo json_encode(array(
					'batch' => $batch,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					'source_qty' => $source_qty,
					'landed_cost' => $elc,s

			));
		else: //$item_valuation == 0/FIFO/Agency
			$batchs = $batchObj->get(array('b.item'=>$item_id));
			$select_batch .="<option value=''></option>";
			foreach($batchs as $batch):
				if($batch['end_date'] == "0000-00-00" || $batch['end_date'] == ""):
					$select_batch .="<option value='".$batch['id']."'>".$batch['batch']."</option>";
				endif;
			endforeach;
		        $max_batch_id = $batchObj->getMax('id',array('item'=>$item_id));
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
	 * View of Stock Opening
	**/
	public function viewstockAction()
	{
		$this->init();
	
		return new ViewModel(array(
			'title' 	=> 'View Stock Opening',
			'opening_stock' => $this->getDefinedTable(Stock\OpeningStockTable::class)->get($this->_id),
			'opening_details' => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('opening_stock' => $this->_id)),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
			'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
			'itemUomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
			'userTable' => $this->getDefinedTable(Acl\UsersTable::class),
			'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
		));
	}
	/**
	 * St Opening 
	**/
	public function openingstAction()
	{
		$this->init();		
            return new ViewModel(array(
				'title'        => 'St Opening',	
                'st_opening'   => $this->getDefinedTable(Stock\OpeningStTable::class)->getAll(),
                'st_openings'   => $this->getDefinedTable(Stock\OpeningStockTable::class)->getAll(),
                'itemObj'      => $this->getDefinedTable(Stock\ItemTable::class),	
                'batchObj'     => $this->getDefinedTable(Stock\BatchTable::class),		
				
		));		
	}
	/**
	 * interface to import csv data into sql server
	 */
	public function importdataAction()
	{
		$this->init();
		return new ViewModel();
	}
	/*
   * Imports csv file
   *
   * @param String $filename File path to csv file
   * @return Array $data Multi demensional array containing
   * associative arrays with the csv column names as keys
   */
   public function importAction()
   {
	$this->init();
	$keys = NULL;
	$request = $this->getRequest();
	if($request->isPost())
		{
		$file = $_FILES['file']['tmp_name'];
		$handle = fopen($file, "r");
		
		while (!feof($handle) )
		   {       
			   $row = fgetcsv($handle);   
				if (is_array($row)) 
			   {                    
				   //set record array keys from csv header
				   if ($keys == NULL) 
				   {   
					   $keys = array_flip($row);
				   } 
				   //set record array values from csv row
				   elseif ($keys != NULL)
				   {
					   foreach ($keys as $key => $value) 
					   {
						   $record[$key] = $row[$value];
					   }
					   $data[] = $record; 
				   }   
			   }
		   }
		   
		   if($handle) fclose($handle);
		//echo "<pre>"; print_r($data); exit;
		if($data > 0):
			foreach($data as $data1):
			   	//$stopenings = $this->getDefinedTable(Stock\OpeningStockTable::class)->get(array('item' =>$data1['item'],'batch' =>$data1['batchno']));
				//print_r($stopenings); exit;
				//if(sizeof($stopenings) > 0):
				   // foreach($stopenings as $stopening);
						 //echo "This is my 1 test"; exit;
						//$opening = array(
							//'batch' => $data1['batchno'],
							//'item' => $data1['item'],
							//'location' => $data1['location'],
							//'opening_date' => date('Y-m-d'),
							//'quantity' => $data1['closing'],
							//'elc' => $data1['elc'],
							//'selling_price' => $data1['sp'],
							//'batch_id' =>   $stopening['id'],
							//'author' => $this->_author,
							///'created' => $this->_created,
						//);
					    //print_r($opening); exit;
				    //$result = $this->getDefinedTable(Stock\OpeningStTable::class)->save($opening);
					//$result1 = $result + 1;
				//else:
       				//$results = $this->getDefinedTable(Stock\OpeningStTable::class)->getAll();					
				    //echo "This is my 2  testing"; exit;
					//$opening = array(
						//'batch' => $data1['batchno'],
						//'item' => $data1['item'],
						//'location' => $data1['location'],
						//'opening_date' => date('Y-m-d'),
						//'quantity' => $data1['closing'],
						//'elc' => $data1['elc'],
						//'selling_price' => $data1['sp'],
						//'batch_id' =>   $result1,
						//'author' => $this->_author,
						//'created' => $this->_created,
					//);
					//print_r($opening); exit;
					//$result = $this->getDefinedTable(Stock\OpeningStTable::class)->save($opening);	
               // endif;
			   $opening = array(
					'batch' => $data1['batchno'],
					'item' => $data1['item'],
					'location' => $data1['location'],
					'opening_date' => date('Y-m-d'),
					'quantity' => $data1['closing'],
					'elc' => $data1['elc'],
					'selling_price' => $data1['sp'],
					'author' => $this->_author,
					'created' => $this->_created,
				);
				//print_r($opening); exit;
				$result = $this->getDefinedTable(Stock\OpeningStTable::class)->save($opening);			   
                $opening_record = array(
					'batch_id'      => $result,
					'batch'         => $data1['batchno'],
					'item'          => $data1['item'],
					'location'      => $data1['location'],
					'opening_date'  => date('Y-m-d'),
					'quantity'      => $data1['closing'],
					'elc'           => $data1['elc'],
					'selling_price' => $data1['sp'],
					'author'        => $this->_author,
					'created'       => $this->_created,
				);				
				$this->getDefinedTable(Stock\OpeningStRecordTable::class)->save($opening_record);
            endforeach;	
			$this->flashMessenger()->addMessage("success^ Data Importing successful");
			return $this->redirect()->toRoute('sam',array('action' =>'openingst'));
		else:
			$this->flashMessenger()->addMessage("error^ error to import opening data");
			return $this->redirect()->toRoute('sam',array('action' =>'importdate'));
		endif;
		}
		//$this->flashMessenger()->addMessage("error^ error to import Sir/Master");
		return $this->redirect()->toRoute('sam',array('action' =>'openingst')); 
		return new ViewModel(array(
			'title' => 'Stock Opening',
			'stopeningObj' => $this->getDefinedTable(Stock\OpeningStTable::class),
		));
   }
   /**
	 * Delete Plot
	**/
	public function deletestopeningAction()
	{
		$this->init();
		$st_opening_id = $this->_id;
		$st_openings = $this->getDefinedTable(Stock\OpeningStTable::class)->getAll();
		foreach($st_openings as $st_opening):
	    $result = $this->getDefinedTable(Stock\OpeningStTable::class)->remove($st_opening['id']);
		endforeach;
			if($result):			
				$this->flashMessenger()->addMessage("success^ Successfully deleted opening Details ");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to deleted opening Details");
			endif;
		return $this->redirect()->toRoute('sam', array('action'=>'openingst'));
	}
	/**
	 * Update selling price by effect date
	*/
	public function updatespAction()
	{
		$this->init();
		
		return new ViewModel(array(
				'title' 	  => 'Update Selling Price',
				'batchObj' 	  => $this->getDefinedTable(Stock\BatchTable::class),
				'itemObj' 	  => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	
	/**
	 * Stock Opening 
	**/
	public function openingstockAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
				'item' => $form['item'],
			);
		else:
			$data = array(
				'item' => '-1',
			);
		endif;
		//$activity  = $this->getDefinedTable(Administration\ActivityTable::class)->get(array());
		//echo '<pre>';print_r($activity);exit;
		return new ViewModel(array(
					'title'         => "Stock Opening",
					'data'          => $data,
					'openingstocks' => $this->getDefinedTable(Stock\OpeningStockTable::class)->getByActivity($data['item']),
					'openingdtl' => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class),
					//'openingstocks' => $this->getDefinedTable(Stock\OpeningStockTable::class)->getAll(),
					'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
					'itemObj'   => $this->getDefinedTable(Stock\ItemTable::class),
					'uomObj'   => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
    /**
	 * OPENING STOCK ENTRY
	 * Add Stock Opening Action
	 * Initiate Status
	**/
	public function addstockopeningAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
			$activity_id = $this->getDefinedTable(Stock\ItemTable::class)->getcolumn(array('id'=>$form['item']),'activity');
			if(in_array($activity_id, array('2','7'))):
				$check_result = $this->getDefinedTable(Stock\OpeningStockTable::class)->get(array('item'=>$form['item']));
			else:
				$check_result = $this->getDefinedTable(Stock\OpeningStockTable::class)->get(array('item'=>$form['item'],'batch'=>$form['batch']));
			endif;
			/*if(sizeof($check_result)>0):
				foreach($check_result as $check_row);
				$this->flashMessenger()->addMessage("notice^ The opening data of this item and batch is already processed. Please continue from here.");
				return $this->redirect()->toRoute('sam', array('action' =>'viewstockopening','id'=>$check_row['id']));
			else:
				$unit_uom = $this->getDefinedTable(Stock\ItemUomTable::class)->getcolumn(array('item'=>$form['item'], 'costing_uom'=>'1'),'conversion');	  
				$landed_cost = $form['costing_location_sp'] * $unit_uom;
				$trip_id = $this->getDefinedTable(Stock\TripTable::class)->getColumn(array('status'=>1),'id');
				$location_formula = $this->getDefinedTable(Stock\ItemTable::class)->getcolumn(array('id'=>$form['item']),'location_formula');
				$location_costing = $this->getDefinedTable(Stock\ItemTable::class)->getcolumn(array('id'=>$form['item']),'transportation_charge');
				*/
				
				//$item_margin = $this->getDefinedTable(Stock\MarginTable::class)->getSUM(array('item'=>$form['item']),'margin');
				$opening_stock = array(
					 'batch'       => '1',
					 'item'        => $form['item'],
					 'uom'         => $form['basic_uom'],
					 'location'    => '1',
					 'quantity'    => $form['quantity'],
					 'unit_uom'    => '1',
					 'landed_cost' => '0',
					 'opening_date'=> $form['batch_date'],
					 'issue_date'=> $form['issue_date'],
					 'selling_price'  => $form['selling_price'],
					 'cost_price'  => $form['cost_price'],
					 'barcode'     => '',
					 'trip'        => '1',
					 'location_formula' =>'1',
					 'charge_sum'  => '0',
					 'margin_sum'  => '0',
					 'status'      => '2',
					 'item_subgroup' =>$form['item_subgroup'],
					 'author'      => $this->_author,
					 'created'     => $this->_created,
					 'modified'    => $this->_modified
				);
				//echo "<pre>"; print_r($opening_stock); exit;
				$opening_stock = $this->_safedataObj->rteSafe($opening_stock);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$openingID = $this->getDefinedTable(Stock\OpeningStockTable::class)->save($opening_stock); 
				if($openingID > 0){
					$qty = 0;
					for ($i=0;$i < sizeof($form['location']); $i++){
						//if($form['quantity'][$i] > 0):
							$qty += $form['quantity'][$i];
							$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($form['location'][$i],'location_type');
							$marginPercent = $this->getDefinedTable(Stock\MarginTable::class)->getColumn(array('item'=>$form['item'],'location_type'=>$location_type),'margin');
							$marginPercent = (!is_numeric($marginPercent))?'0':$marginPercent;
							$sp_unit_uom = $form['selling_price'][$i] * $unit_uom;
							$selling_price=$form['selling_price'];
							$cost_price=$form['cost_price'];
							$item=$form['item'];
							//print_r($selling_price);exit;
							$location_elc = ($sp_unit_uom * 100)/(100+$marginPercent);
							$openingDtls = array(
								'opening_stock'  => $openingID,
								'location'       => $form['location'][$i],
								'opening_qty'       =>$form['quantity'][$i],
								'quantity'       => $form['quantity'][$i],
								//'landed_cost'    => $location_elc,
								//'margin'         => $marginPercent,
								'item'  => $item,
								'selling_price'  => $selling_price,
								'cost_price'  => $cost_price,
								'status'         => '2',
								'author'         => $this->_author,
								'created'        => $this->_created,
								'modified'       => $this->_modified
							);
							//echo'<pre>';print_r($openingDtls);exit;
							$openingDtls = $this->_safedataObj->rteSafe($openingDtls);
							$openingDtlsId = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($openingDtls);
							$item_group=$this->getDefinedTable(Stock\ItemTable::class)->getColumn($form['item'],'item_group');
							if($item_group==64 || $item_group==67){
								$dataprice=array(
								'opening'			=> $openingID,
								'item'        		=> $form['item'],
								'uom'         		=> $form['basic_uom'],
								'selling_price' 	=> $form['selling_price'],
								'cost_price'  		=> $form['cost_price'],
								'weighted_price'	=> $form['cost_price'],
								'date'				=> $form['batch_date'],
								'author'	    	=> $this->_author,
								'created'     => $this->_created,
								'modified'    => $this->_modified
							);
							$dataprice=$this->_safedataObj->rteSafe($dataprice);
							$dataprice = $this->getDefinedTable(Stock\PriceTable::class)->save($dataprice);	}
							
							if($openingDtlsId > 0){ 
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Unsuccessful to save opening details.");
								return $this->redirect()->toRoute('sam', array('action' =>'addopeningstock'));
							}
						//endif;
					}
					if($openingDtlsId > 0){ 
						$this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$openingID,'quantity' => $qty));
						$this->_connection->commit(); // commit transaction over success
						$this->flashMessenger()->addMessage("success^ Successfully added new Stock Opening and selling price with Batch No : ". $form['batch']);
						return $this->redirect()->toRoute('sam', array('action' =>'viewstockopening','id'=>$openingID));
					}else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Total quantity in stock opening not be null in opening details");
						return $this->redirect()->toRoute('sam', array('action' =>'addopeningstock'));
					}
				}else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ failed to add stock opening in stock opening table");
					return $this->redirect()->toRoute('sam', array('action' =>'addopeningstock'));
				}	
			//endif;
		endif; //end of Post
		if($this->_login_role==99 ||$this->_login_role==100 ):
		    $admin_locs = $this->getDefinedTable(Administration\LocationTable::class)->getAll();
		else:
		    $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'admin_location');
		endif;
		//echo '<pre>';print_r($admin_locs);exit;
		return new ViewModel(array(
				'title'           => 'Add Stock Opening',
				'locations'     => $this->getDefinedTable(Administration\LocationTable::class)->getlocation($admin_locs),
				'activityObj'     => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
				'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupsObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
		));
	}
	/**
	 * Stock Opening Report
	**/
	public function stockreportAction()
	{
		$this->init();
		$user_loc=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'location');
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
				'start_date' => $form['start_date'],
				'end_date' => $form['end_date'],
				'location' => $form['location'],
				'item' => $form['item'],
				'group' => $form['group'],
				'class' => $form['class'],
			);
		else:
			$data = array(
				'start_date' =>date('Y-m-d'),
				'end_date'   =>date('Y-m-d'),
				'location' => $user_loc,
				'item' => '-1',
				'group' => '-1',
				'class' => '-1',
			);
		endif;
		$date = new DateTime($data['start_date']);
		$year = $date->format('Y');
		$month = $date->format('m');
		//$test =$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getByActivity($data['item'],$data['location']);
		//echo '<pre>';print_r($month);exit;
		return new ViewModel(array(
					'title'         => "Stock Opening",
					'data'          => $data,
					'month'			=> $month,
					'year'			=> $year,
					'openingstocks' => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getByActivity($data['item'],$data['location'],$data['group'],$data['class']),
					'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
					'itemObj'   	=> $this->getDefinedTable(Stock\ItemTable::class),
					'groupObj'   	=> $this->getDefinedTable(Stock\ItemGroupTable::class),
					'uomObj'   		=> $this->getDefinedTable(Stock\UomTable::class),
					'classObj'   	=> $this->getDefinedTable(Stock\ItemClassTable::class),
					'salesObj' 		=> $this->getDefinedTable(Sales\SalesTable::class),
					'purchaseObj' 	=> $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
					'dispatchObj' 	=> $this->getDefinedTable(Stock\DispatchTable::class),
					'priceObj' 		=> $this->getDefinedTable(Stock\PriceTable::class),
		));
	}
	/**
	 * Get uom
	 */
	public function getuomAction()
	{		
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$item = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$itemId));
		foreach($item as $items);
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id'=>$items['uom']));
		$buom = "<option value=''></option>";
		foreach($uom as $uoms):
			$buom.="<option value='".$uoms['id']."'>".$uoms['code']."</option>";
		endforeach;
		echo json_encode(array(
				'uom' => $buom,
		));
		exit;
	}
	/**
	 * Get item
	 */
	public function getitemAction()
	{		
		$form = $this->getRequest()->getPost();
		$itemsubgroupId =$form['itemsubgroupId'];
		$itemgrp = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.item_subgroup'=>$itemsubgroupId));
		$item = "<option value=''></option>";
		foreach($itemgrp as $itemgrp):
			$item.="<option value='".$itemgrp['id']."'>".$itemgrp['name']."</option>";
		endforeach;
		echo json_encode(array(
				'item' => $item,
		));
		exit;
	}
	/**
	 * getitemsubgroup - Get item based on item class
	 * **/
	public function getItemgroupAction()
	{
		$form = $this->getRequest()->getPost();
		$itemclass=$form['item_class'];
		$itemgroup= $this->getDefinedTable(Stock\ItemGroupTable::class)->get(array('item_class'=>$itemclass));	
		
		$group = "<option value='-1'>All</option>";
		foreach($itemgroup as $itemgroup):
			$group.="<option value='".$itemgroup['id']."'>".$itemgroup['item_group']."</option>";
		endforeach;
		echo json_encode(array(
				'subgroup' => $group,
		));
		exit;
	}
	/**
	 * getitem - Get item based on location
	 * **/
	public function getItemChangeAction()
	{
		//echo("Hello");
		$form = $this->getRequest()->getPost();
		$locationId = $form['locationId'];
		$subgroup = $form['item_subgroup'];

		$itemOptions = "<option value='-1'>All</option>";
		if($locationId==-1){
			$itemlist = $this->getDefinedTable(Stock\ItemTable::class)->get(array('item_group'=>$subgroup));
		}
		else{
		$itemlist = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getitems(array('osd.location'=>$locationId,'i.item_group'=>$subgroup));
		}
		//echo($itemlist);
		foreach($itemlist as $item):
			$itemOptions .= "<option value='".$item['id']."'>".$item['name']."</option>";
		endforeach;

		echo json_encode(array(
			'items' => $itemOptions,
		));

		exit;
	}
	/**
	 * OPENING STOCK ENTRY
	 * Get Basic Uom
	 * Get Standard Costing Uom
	**/
	public function getuomsAction()
	{
		$this->init();		
		$form = $this->getRequest()->getPost();		
		$itemID = $form['item'];
		$batchDate = $form['batch_date'];
		
		$itemDtls = $this->getDefinedTable(Stock\ItemTable::class)->get($itemID);
		$itemuoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$itemID,'ui.costing_uom'=>'1'));
		$basic_for .="<option value=''></option>";
		foreach($itemDtls as $dtl):
			$basic_for .="<option value='".$dtl['st_uom_id']."' selected>".$dtl['st_uom_code']."</option>";		
		endforeach;	
		
		$costingUom_for .="<option value=''></option>";
		foreach($itemuoms as $row):
			$costingUom_for .="<option value='".$row['uom_id']."' selected>".$row['uom_code']."</option>";
		endforeach;
		
		$activity = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'activity');	
		if($activity == 2 || $activity == 7):
		    $batch_rows  = $this->getDefinedTable(Stock\BatchTable::class)->get(array('item'=>$itemID));
			foreach($batch_rows as $batch_row);
            if(sizeof($batch_rows) > 0):	
                $batch = $batch_row['batch'];			
            else:
			    $itemCode = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($itemID,'item_code');	
				$date = date('y', strtotime($batchDate));
				$tmp_PONo = $itemCode.$date; 
				$results  = $this->getDefinedTable(Stock\BatchTable::class)->getMonthlyBatch($tmp_PONo);		   	  
						 
				$sheet_no_list = array();
				foreach($results as $result):			   	
					array_push($sheet_no_list, substr($result['batch'], 6));
				endforeach;
				if(sizeof($sheet_no_list)>0){
					$next_serial = max($sheet_no_list) + 1;
				}else{
					$next_serial = 1;
				}			   	  
				switch(strlen($next_serial)){
					case 1: $next_b_serial  = "00".$next_serial;  break;
					case 2: $next_b_serial  = "0".$next_serial;   break;
					default: $next_b_serial = $next_serial;      break;
				}			   	  
				$batch = $tmp_PONo.$next_b_serial; 
            endif;			
		else:
			$batch = '';
		endif;
		
		echo json_encode(array(
				'basic_uom' => $basic_for,
				'costingUom' => $costingUom_for,
				'batch_code' => $batch,
		));
		exit;
	}
	/**
	 * View of Stock Opening
	**/
	public function viewstockopeningAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' 	      => 'View Stock Opening',
			'role'			  =>$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role'),
			'openings'        => $this->getDefinedTable(Stock\OpeningStockTable::class)->get($this->_id),
			'openingdtls'     => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('opening_stock' =>$this->_id)),
			'openingdtlObj' => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class),
			'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
			'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
			'uomObj'          => $this->getDefinedTable(Stock\UomTable::class),
			'itemUomObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
			'usersObj'        => $this->getDefinedTable(Administration\UsersTable::class),
			'locationTypeObj' => $this->getDefinedTable(Administration\LocationTypeTable::class),
			'costingformulaObj'=> $this->getDefinedTable(Stock\CostingFormulaTable::class),
			'author'           => $this->_author,
		));
	}
	/**
	 * Edit of Stock Opening
	**/
	public function editstockopeningAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
			$openingID = $form['opening_id'];
			$item = $form['item'];
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			if($openingID > 0){
				for ($i=0;$i < sizeof($form['location']); $i++){
					//if($form['quantity'][$i] > 0):
						//$unit_uom = $this->getDefinedTable(Stock\ItemUomTable::class)->getcolumn(array('item'=>$form['item'], 'costing_uom'=>'1'),'conversion');
						//$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($form['location'][$i],'location_type');
						//$marginPercent = $this->getDefinedTable(Stock\MarginTable::class)->getColumn(array('item'=>$form['item'],'location_type'=>$location_type),'margin');
						//$marginPercent = (!is_numeric($marginPercent))?'0':$marginPercent;
						//$sp_unit_uom = $form['selling_price'][$i] * $unit_uom;
						//$location_elc = ($sp_unit_uom * 100)/(100+$marginPercent);
						$openingDtls = array(
							'opening_stock'  => $openingID,
							'location'       => $form['location'][$i],
							'quantity'       => $form['quantity'][$i],
							'opening_qty'    => $form['quantity'][$i],
							'landed_cost'    => 1,
							'margin'         => 1,
							'selling_price'  => $form['selling_price'],
							'cost_price'  	 => $form['cost_price'],
							'item'  		 => $item,
							'status'         => '2',
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
						);
						//echo'<pre>';print_r($openingDtls);exit;
						$openingDtls = $this->_safedataObj->rteSafe($openingDtls);
						$openingDtlsId = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($openingDtls);	
						if($openingDtlsId > 0){ 
						}else{
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Unsuccessful to save opening details.");
							return $this->redirect()->toRoute('sam', array('action' =>'addopeningstock'));
						}
					//endif;
				}
				if($openingDtlsId > 0){ 
					$qty = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getSMSUM(array('opening_stock'=>$openingID),'quantity');
					$this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$openingID,'quantity' => $qty));
					$this->_connection->commit(); // commit transaction over success
					$this->flashMessenger()->addMessage("success^ Successfully added new Stock Opening and selling price with Batch No : ". $form['batch']);
					return $this->redirect()->toRoute('sam', array('action' =>'viewstockopening','id'=>$openingID));
				}else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Total quantity in stock opening not be null in opening details");
					return $this->redirect()->toRoute('sam', array('action' =>'addopeningstock'));
				}
			}else{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Opening stock id not found.");
				return $this->redirect()->toRoute('sam', array('action' =>'addopeningstock'));
			}
		endif; //end of Post
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		
		return new ViewModel(array(
				'title'           => 'Add Stock Opening',
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'location'     => $this->getDefinedTable(Administration\LocationTable::class)->getlo($this->_id),
				'admin_loc_array' => $admin_loc_array,
				'activityObj'     => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
				'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupsObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
				'openings'        => $this->getDefinedTable(Stock\OpeningStockTable::class)->get($this->_id),
				'openingdtls'     => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('opening_stock'=>$this->_id)),
		));
	}
	/**
	 * OPENING STOCK ENTRY
	 * Add Stock Opening Action
	 * Initiate Status
	**/
	public function editopeningAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();	
				$opening_stock = array(
					 'id'          => $form['open_id'],
					 'batch'       => '1',
					 'item'        => $form['item'],
					 'uom'         => $form['basic_uom'],
					 'location'    => '1',
					// 'quantity'    => $form['quantity'],
					 'unit_uom'    => '1',
					 'landed_cost' => '0',
					 'opening_date'=> $form['batch_date'],
					 'issue_date'=> $form['issue_date'],
					 'selling_price'  => $form['selling_price'],
					 'cost_price'  => $form['cost_price'],
					 'barcode'     => '',
					 'trip'        => '1',
					 'location_formula' =>'1',
					 'charge_sum'  => '0',
					 'margin_sum'  => '0',
					 'status'      => '2',
					 'item_subgroup' =>$form['item_subgroup'],
					 'author'      => $this->_author,
					 'created'     => $this->_created,
					 'modified'    => $this->_modified
				);
				//echo "<pre>"; print_r($opening_stock); exit;
				$opening_stock = $this->_safedataObj->rteSafe($opening_stock);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$openingID = $this->getDefinedTable(Stock\OpeningStockTable::class)->save($opening_stock); 
				if($openingID > 0){ 
				$opening=$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('opening_stock'=>$this->_id));
				foreach($opening as $openings):
				$data = array(
					 'id'          => $openings['id'],
					 'item'          => $form['item'],
					 'opening_stock'       => $form['open_id'],
					 'selling_price'  => $form['selling_price'],
					 'cost_price'  => $form['cost_price'],
					 'author'      => $this->_author,
					 'created'     => $this->_created,
					 'modified'    => $this->_modified
				);
				//echo "<pre>"; print_r($opening_stock); exit;
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data); 
				$item_group=$this->getDefinedTable(Stock\ItemTable::class)->getColumn($form['item'],'item_group');
				if($item_group==64 || $item_group==67){
					$priceid=$this->getDefinedTable(Stock\PriceTable::class)->getMin('id',array('opening'=>$form['open_id'])); 
				$data = array(
					'id'          => $priceid,
					'item'          => $form['item'],
					'opening'       => $form['open_id'],
					'selling_price'  => $form['selling_price'],
					'cost_price'  => $form['cost_price'],
					'weighted_price'	=> $form['selling_price'],
					'author'      => $this->_author,
					'created'     => $this->_created,
					'modified'    => $this->_modified
			   );
			   //echo "<pre>"; print_r($opening_stock); exit;
			   $data = $this->_safedataObj->rteSafe($data);
			   $result = $this->getDefinedTable(Stock\PriceTable::class)->save($data); 
				}
				
				endforeach;
					$this->_connection->commit(); // commit transaction over success
					$this->flashMessenger()->addMessage("success^ Successfully edited  Stock Opening");
					return $this->redirect()->toRoute('sam', array('action' =>'viewstockopening','id'=>$form['open_id']));
				}else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Total quantity in stock opening not be null in opening details");
					return $this->redirect()->toRoute('sam', array('action' =>'addopeningstock'));
				}
				
		endif; 
		return new ViewModel(array(
				'title'           => 'Add Stock Opening',
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'location'     => $this->getDefinedTable(Administration\LocationTable::class)->getlo(array('opening_stock'=>$this->_id)),
				'userid'     => $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author)),
				//'admin_loc_array' => $admin_loc_array,
				'activityObj'     => $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj'         => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'         => $this->getDefinedTable(Stock\UomTable::class),
				'itemclasses' => $this->getDefinedTable(Stock\ItemClassTable::class) -> getAll(),
				'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupsObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
				'openings' => $this->getDefinedTable(Stock\OpeningStockTable::class)->get($this->_id),
		));
	}
	/**
	 * Update to batch_table
	**/
	public function updatebatchAction()
	{
		$this->init();
		$opening_stocks = $this->getDefinedTable(Stock\OpeningStockTable::class)->get($this->_id);
		foreach($opening_stocks as $opening);
		
		if($opening['status']==2): //opening stock status = 2 pending.
			$data = array(
					'batch' => $opening['batch'],
					'item'  => $opening['item'],
					'uom'   => $opening['uom'],	
					'location' => $opening['location'],
					'quantity' => $opening['quantity'],
					'unit_uom' => $opening['unit_uom'],
					'barcode' => '',
					'landed_cost' => $opening['landed_cost'],
					'batch_date' => $opening['opening_date'],
					'expiry_date' => '',
					'end_date' => '',
					'costing' => '1',
					'trip'        => $opening['trip'],
					'location_formula' => $opening['location_formula'],
					'charge_sum' => $opening['charge_sum'],
					'margin_sum' => $opening['margin_sum'],
					'status' => '3',
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,					
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$batchID = $this->getDefinedTable(Stock\BatchTable::class)->save($data); 
			if($batchID > 0){
				$opening_dtls = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('opening_stock' =>$opening['id'],'status'=>'2','author'=>$this->_author));
				foreach($opening_dtls as $detail):
					$batchDtls = array(
						'batch'          => $batchID,
						'location'       => $detail['location'],
						'actual_quantity'=> $detail['quantity'],
						'quantity'       => $detail['quantity'],
						'landed_cost'    => $detail['landed_cost'],
						'margin'         => $detail['margin'],
						'selling_price'  => $detail['selling_price'],
						'author'         => $this->_author,
						'created'        => $this->_created,
						'modified'       => $this->_modified,
					);
					$batchDtls = $this->_safedataObj->rteSafe($batchDtls);
					$batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls);
					$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($opening['item'],'valuation');
					if($item_valuation == 1):
						$movingDtls = array(
							'item'           => $opening['item'],
							'uom'            => $opening['uom'],	
							'batch'          => $batchID,
							'location'       => $detail['location'],
							'quantity'       => $detail['quantity'],
							'selling_price'  => $detail['selling_price'],
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
						);
						$movingDtls = $this->_safedataObj->rteSafe($movingDtls);
						$movingDtlsId = $this->getDefinedTable(Stock\MovingItemTable::class)->save($movingDtls);
					endif;
					if($batchDtlsId){
						$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save(array('id'=>$detail['id'],'status'=>'3'));
					}else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Unsuccessful to update batch details.");
					}
				endforeach;
				if($batchDtlsId){
				    $result1 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$this->_id,'count'=>'1','status'=>'3','modified'=>_modified));
					if($result1):
						$this->_connection->commit(); // commit transaction over success
						$this->flashMessenger()->addMessage("success^ Successfully updated new batch, Batch No : ". $opening['batch']);
					else:
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to update sum in opening stock.");
					endif;
				}else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Unsuccessful to new update batch details");
				}
			}else{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Unsuccessful to update new batch");
			}
		else: //opening stock status = 3 committed.
			$batchID = $this->getDefinedTable(Stock\BatchTable::class)->getColumn(array('item' => $opening['item'],'batch'=>$opening['batch']),'id');
			
			if($batchID > 0){
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$opening_dtls = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('opening_stock' =>$opening['id'],'status'=>'2','author'=>$this->_author));
				foreach($opening_dtls as $detail):
					$batchDtls = array(
						'batch'          => $batchID,
						'location'       => $detail['location'],
						'actual_quantity'=> $detail['quantity'],
						'quantity'       => $detail['quantity'],
						'landed_cost'    => $detail['landed_cost'],
						'margin'         => $detail['margin'],
						'selling_price'  => $detail['selling_price'],
						'author'         => $this->_author,
						'created'        => $this->_created,
						'modified'       => $this->_modified
					);
					$batchDtls = $this->_safedataObj->rteSafe($batchDtls);
					$batchDtlsId = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batchDtls);
					$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($opening['item'],'valuation');
					if($item_valuation == 1):
						$movingDtls = array(
							'item'           => $opening['item'],
							'uom'            => $opening['uom'],	
							'batch'          => $batchID,
							'location'       => $detail['location'],
							'quantity'       => $detail['quantity'],
							'selling_price'  => $detail['selling_price'],
							'author'         => $this->_author,
							'created'        => $this->_created,
							'modified'       => $this->_modified
						);
						$movingDtls = $this->_safedataObj->rteSafe($movingDtls);
						$movingDtlsId = $this->getDefinedTable(Stock\MovingItemTable::class)->save($movingDtls);
					endif;
					if($batchDtlsId){
						$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save(array('id'=>$detail['id'],'status'=>'3','modified'=>_modified));
					}else{
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Unsuccessful to update batch details");
					}
				endforeach;
				if($batchDtlsId){
					$count_old = $this->getDefinedTable(Stock\OpeningStockTable::class)->getColumn($this->_id,'count');
					$count_new = $count_old + 1;
				    $result1 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$this->_id,'count'=>$count_new,'modified'=>_modified));
					if($result1):
						$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getSum(array('batch'=>$batchID),'actual_quantity');
						$result2 = $this->getDefinedTable(Stock\BatchTable::class)->save(array('id'=>$batchID,'quantity'=>$qty,'modified'=>_modified));
						if($result2):
							$this->_connection->commit(); // commit transaction over success
							$this->flashMessenger()->addMessage("success^ Successfully updated batch, Batch No : ". $opening['batch']);
						else:
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Failed to update sum in batch table.");
						endif;
					else:
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to update sum in opening stock.");
					endif;
				}else{
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Unsuccessful to update batch details");
				}
			}else{
				$this->flashMessenger()->addMessage("notice^ Unsuccessfull to update batch");
			}
		endif;
		return $this->redirect()->toRoute('sam', array('action' =>'viewstockopening','id'=>$this->_id));
	}
	
	/**
	 * editstockopeningdetail Action
	 */
	public function editstockopeningdetailAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$openingdtl_id = $form['openingdtl_id'];
			$openingdtls = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get($openingdtl_id);
			foreach($openingdtls as $openingdtl);
			$openings = $this->getDefinedTable(Stock\OpeningStockTable::class)->get($openingdtl['opening_stock']);
			foreach($openings as $opening);
			$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($opening['item'],'valuation');
			$unit_uom = $this->getDefinedTable(Stock\ItemUomTable::class)->getcolumn(array('item'=>$opening['item'], 'costing_uom'=>'1'),'conversion');
			$location_type = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($openingdtl['location'],'location_type');
			$marginPercent = $this->getDefinedTable(Stock\MarginTable::class)->getColumn(array('item'=>$opening['item'],'location_type'=>$location_type),'margin');
			$marginPercent = (!is_numeric($marginPercent))?'0':$marginPercent;
			$sp_unit_uom = $form['selling_price'] * $unit_uom;
			$location_elc = ($sp_unit_uom * 100)/(100+$marginPercent);
			if($openingdtl['status']==3):
				$batchID = $this->getDefinedTable(Stock\BatchTable::class)->getColumn(array('item' => $opening['item'],'batch'=>$opening['batch']),'id');
				if($batchID > 0):
					$batchdtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$batchID,'location'=>$openingdtl['location']));
					if(sizeof($batchdtls)>0):
						foreach($batchdtls as $batchdtl);
						$nullify_actual_qty = $batchdtl['actual_quantity'] - $openingdtl['quantity'];
						$nullify_qty = $batchdtl['quantity'] - $openingdtl['quantity'];
						$actual_qty = $nullify_actual_qty + $form['quantity'];
						$qty = $nullify_qty + $form['quantity'];
						$batch_dtl_data = array(
								'id'             => $batchdtl['id'],
								'actual_quantity'=> $actual_qty,
								'quantity'       => $qty,
								'landed_cost'    => $location_elc,
								'margin'         => $marginPercent,
								'selling_price'  => $form['selling_price'],
								'author'         => $this->_author,
								'modified'       => $this->_modified,
						);
						$batch_dtl_data = $this->_safedataObj->rteSafe($batch_dtl_data);
						$this->_connection->beginTransaction(); //***Transaction begins here***//
						$result = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batch_dtl_data); 
						if($item_valuation == 1):
							$movings = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('item'=>$opening['item'],'location'=>$openingdtl['location']));
							if(sizeof($movings)>0):
								foreach($movings as $moving);
								$nullify_moving_qty = $moving['quantity'] - $openingdtl['quantity'];
								$moving_qty = $nullify_moving_qty + $form['quantity'];
								$movingDtls = array(
									'id'             => $moving['id'],
									'quantity'       => $moving_qty,
									'selling_price'  => $form['selling_price'],
									'modified'       => $this->_modified
								);
								$movingDtls = $this->_safedataObj->rteSafe($movingDtls);
								$movingDtlsId = $this->getDefinedTable(Stock\MovingItemTable::class)->save($movingDtls);
							endif;
						endif;
						if($result):
							$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getSum(array('batch'=>$batchID),'actual_quantity');
							$result1 = $this->getDefinedTable(Stock\BatchTable::class)->save(array('id'=>$batchID,'quantity'=>$qty,'modified'=>$this->_modified));
							if($result1):
								$data = array(
										'id'            => $openingdtl['id'],
										'quantity'      => $form['quantity'],
										'opening_qty'   => $form['opening_stock'],
										'landed_cost'   => $location_elc,
										'location'		=> $form['location'],
										'margin'        => $marginPercent,
										'selling_price' => $form['selling_price'],
										'status'        => '3',
										'author'        => $this->_author,
										'modified'      => $this->_modified,
								);
								$data = $this->_safedataObj->rteSafe($data);
								$result3 = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data);
								if($result3):
									$count_old = $opening['count'];
									$count_new = $count_old + 1;
									$qty = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getSMSUM(array('opening_stock'=>$opening['id']),'quantity');
									$result4 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'count'=>$count_new,'modified'=>$this->_modified));
									if($result4):
										$this->_connection->commit(); // commit transaction over success
										$this->flashMessenger()->addMessage("success^ Successfully updated opening stock details.");
									else:
										$this->_connection->rollback(); // rollback transaction over failure
										$this->flashMessenger()->addMessage("error^ Failed to update sum and count in opening stock.");
									endif;
								else:
									$this->_connection->rollback(); // rollback transaction over failure
									$this->flashMessenger()->addMessage("error^ Failed to update opening stock details.");
								endif;
							else:
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Failed to update the sum of quantity in batch table.");
							endif;
						else:
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Failed to update batch details.");
						endif;
					else:
						$this->flashMessenger()->addMessage("notice^ Batch Details not found. Please check.");
					endif;
				else:
					$this->flashMessenger()->addMessage("notice^ Batch not found in batch table. Please check.");
				endif;
			else:
				$data = array(
						'id'            => $openingdtl['id'],
						'quantity'   	=> $form['quantity'],
						'opening_qty'   	=> $form['opening_stock'],
						'landed_cost'   => $location_elc,
						'margin'        => $marginPercent,
						'location'		=> $form['location'],
						'selling_price' => $form['selling_price'],
						'status'        => '2',
						'author'        => $this->_author,
						'modified'      => $this->_modified,
				);
				$data = $this->_safedataObj->rteSafe($data);
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$result = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data);
				if($result):
					$qty = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getSMSUM(array('opening_stock'=>$opening['id']),'quantity');
					$result1 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'modified'=>$this->_modified));
					if($result1):
						$this->_connection->commit(); // commit transaction over success
						$this->flashMessenger()->addMessage("success^ Successfully updated opening stock details. Please update to the batch.");
					else:
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Failed to update sum in opening stock.");
					endif;
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to update opening stock details.");
				endif;
			endif;
			return $this->redirect()->toRoute('sam',array('action' => 'viewstockopening','id'=>$opening['id']));
		}
		$ViewModel = new ViewModel(array(
				'title'       => 'Edit Opening Details',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'location'	  => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
				'openingdtls' => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * deletestockopeningdetail Action
	 */
	public function deletestockopeningdetailAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$openingdtl_id = $form['openingdtl_id'];
			$openingdtls = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get($openingdtl_id);
			foreach($openingdtls as $openingdtl);
			$openings = $this->getDefinedTable(Stock\OpeningStockTable::class)->get($openingdtl['opening_stock']);
			foreach($openings as $opening);
			$item_valuation = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($opening['item'],'valuation');
			if($openingdtl['status']==3):
				$batchID = $this->getDefinedTable(Stock\BatchTable::class)->getColumn(array('item' => $opening['item'],'batch'=>$opening['batch']),'id');
				if($batchID > 0):
					$batchdtls = $this->getDefinedTable(Stock\BatchDetailsTable::class)->get(array('batch'=>$batchID,'location'=>$openingdtl['location']));
					if(sizeof($batchdtls)>0):
						foreach($batchdtls as $batchdtl);
						$nullify_actual_qty = $batchdtl['actual_quantity'] - $openingdtl['quantity'];
						$nullify_qty = $batchdtl['quantity'] - $openingdtl['quantity'];
						$actual_qty = $nullify_actual_qty;
						$qty = $nullify_qty;
						$batch_dtl_data = array(
								'id'             => $batchdtl['id'],
								'actual_quantity'=> $actual_qty,
								'quantity'       => $qty,
								'author'         => $this->_author,
								'modified'       => $this->_modified,
						);
						$batch_dtl_data = $this->_safedataObj->rteSafe($batch_dtl_data);
						$this->_connection->beginTransaction(); //***Transaction begins here***//
						$result = $this->getDefinedTable(Stock\BatchDetailsTable::class)->save($batch_dtl_data);
						if($item_valuation == 1):
							$movings = $this->getDefinedTable(Stock\MovingItemTable::class)->get(array('item'=>$opening['item'],'location'=>$openingdtl['location']));
							if(sizeof($movings)>0):
								foreach($movings as $moving);
								$nullify_moving_qty = $moving['quantity'] - $openingdtl['quantity'];
								$moving_qty = $nullify_moving_qty + $form['quantity'];
								$movingDtls = array(
									'id'             => $moving['id'],
									'quantity'       => $moving_qty,
									'selling_price'  => $form['selling_price'],
									'modified'       => $this->_modified
								);
								$movingDtls = $this->_safedataObj->rteSafe($movingDtls);
								$movingDtlsId = $this->getDefinedTable(Stock\MovingItemTable::class)->save($movingDtls);
							endif;
						endif;
						if($result):
							$qty = $this->getDefinedTable(Stock\BatchDetailsTable::class)->getSum(array('batch'=>$batchID),'actual_quantity');
							$result1 = $this->getDefinedTable(Stock\BatchTable::class)->save(array('id'=>$batchID,'quantity'=>$qty,'modified'=>$this->_modified));
							if($result1):
								$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->remove($openingdtl['id']);
								
								$count_old = $opening['count'];
								$count_new = $count_old + 1;
								$qty = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getSMSUM(array('opening_stock'=>$opening['id']),'quantity');
								$result4 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'count'=>$count_new,'modified'=>$this->_modified));
								if($result4):
									$this->_connection->commit(); // commit transaction over success
									$this->flashMessenger()->addMessage("success^ Successfully removed opening stock details.");
								else:
									$this->_connection->rollback(); // rollback transaction over failure
									$this->flashMessenger()->addMessage("error^ Failed to update sum and count in opening stock.");
								endif;
							else:
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Failed to update the sum of quantity in batch table.");
							endif;
						else:
							$this->_connection->rollback(); // rollback transaction over failure
							$this->flashMessenger()->addMessage("error^ Failed to update batch details.");
						endif;
					else:
						$this->flashMessenger()->addMessage("notice^ Batch Details not found. Please check.");
					endif;
				else:
					$this->flashMessenger()->addMessage("notice^ Batch not found in batch table. Please check.");
				endif;
			else:
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->remove($openingdtl['id']);
				$qty = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getSMSUM(array('opening_stock'=>$opening['id']),'quantity');
				$result1 = $this->getDefinedTable(Stock\OpeningStockTable::class)->save(array('id'=>$opening['id'],'quantity'=>$qty,'modified'=>$this->_modified));
				if($result1):
					$this->_connection->commit(); // commit transaction over success
					$this->flashMessenger()->addMessage("success^ Successfully removed opening stock details.");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("error^ Failed to update sum in opening stock.");
				endif;
			endif;
			return $this->redirect()->toRoute('sam',array('action' => 'viewstockopening','id'=>$opening['id']));
		}
		$ViewModel = new ViewModel(array(
				'title'       => 'Delete Opening Details',
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'openingdtls' => $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
}
