<?php
namespace Store\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Store\Model As Store;
class PurchaseController extends AbstractActionController
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
		
		$this->_config = $this->_container->get('Config');
		
		$this->_user = $this->identity();		
		$this->_login_id = $this->_user->id;  
		$this->_login_role = $this->_user->role;  
		$this->_author = $this->_user->id;  
		
		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		//$this->_safedataObj = $this->SafeDataPlugin();
		$this->_userloc = $this->_user->location;
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	public function indexAction()
	{
		$this->init();
		$month='';
		$year='';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			$group = $form['group'];
			
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
					'group' => $group,
			);
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$group = '-1';
			
			$data = array(
					'year' => $year,
					'month' => $month,
					'group' => $group,
			);
		}
		$purchase_results = $this->getDefinedTable(Store\PurchaseOrderTable::class)->getDateWise('purchase_order_date',$year,$month,$group);

		return new ViewModel( array(
               'purchase_results' => $purchase_results,
			   'statusObj'        => $this->getDefinedTable(Acl\StatusTable::class),
			   'minYear' 		  => $this->getDefinedTable(Store\PurchaseOrderTable::class)->getMin('purchase_order_date'),
			   'data'             => $data,
			   'groupObj'         => $this->getDefinedTable(Store\GroupTable::class),
			   'activitiesObj'    => $this->getDefinedTable(Administration\ActivityTable::class),
		) );
	}
	
	/** 
	 * Add Purchase Order Action
	 */
	public function addporderAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');			
			$date = date('ym',strtotime($form['purchase_order_date']));					
			$tmp_PONo = $location_prefix."PO".$date; 			
			$results = $this->getDefinedTable(Store\PurchaseOrderTable::class)->getMonthlyPO($tmp_PONo);
			
			$po_no_list = array();
            foreach($results as $result):
	       		array_push($po_no_list, substr($result['purchase_order_no'], 8)); 
		   	endforeach;
            $next_serial = max($po_no_list) + 1;
               
			switch(strlen($next_serial)){
				case 1: $next_po_serial = "000".$next_serial; break;
			    case 2: $next_po_serial = "00".$next_serial;  break;
			    case 3: $next_po_serial = "0".$next_serial;   break;
			   	default: $next_po_serial = $next_serial;       break; 
			}					   
			
			$purchase_order_no = $tmp_PONo.$next_po_serial;
			
			$item         = $form['item'];
			$quantity     = $form['quantity'];
			$rate         = $form['rate'];
			for($i=0; $i < sizeof($item); $i++):
				if(isset($item[$i]) && $item[$i] > 0):
					$net_amount += $quantity[$i] * $rate[$i];
				endif;
			endfor;
			$data = array(
				'purchase_order_no'=> $purchase_order_no, 
				'location'         => $form['location'],
				'cost_center'      => $form['cost_center'],
				'supplier'         => $form['supplier'],
				'item_group'         => $form['group'],
				'purchase_order_date' => $form['purchase_order_date'], 
				'total_amount'        => $net_amount,
				'quotation_no'     => $form['quotation_no'],
				'quotation_date'   => $form['quotation_date'],
				'note'             => $form['note'],
				'delivery_location'=> $form['delivery_location'],
				'status' 		   => '1', 
				'paid_amount'      => $form['paid_amount'],
				'author'           => $this->_author,
				'created'          => $this->_created,
				'modified'         => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\PurchaseOrderTable::class)->save($data);
			if($result > 0){ 
				$item         = $form['item'];
				$uom          = $form['uom'];
				$quantity     = $form['quantity'];
				$rate         = $form['rate'];
				$amount       = $form['amount'];
				$remarks      = $form['remarks'];
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
						$po_details = array(
		      					'purchase_order' => $result,
					     		'item'           => $item[$i],
					     		'uom'            => $uom[$i],
					     		'quantity'  	 => $quantity[$i],
					     		'rate'      	 => $rate[$i],
					     		'amount'      	 => $amount[$i],
								'remark' 	 	 => $remarks[$i],
					      		'author'    	 => $this->_author,
					      		'created'   	 => $this->_created,
					      		'modified'  	 => $this->_modified
						);
		     		$po_details   = $this->_safedataObj->rteSafe($po_details);
			     	$this->getDefinedTable(Store\PODetailsTable::class)->save($po_details);		
				   	endif; 		     
				endfor;
				$this->flashMessenger()->addMessage("success^ Successfully added new Purchase Order :". $purchase_order_no);
				return $this->redirect()->toRoute('inpurorder', array('action' =>'viewporder', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Purchase Order");
				return $this->redirect()->toRoute('addporder');
			}		
		}		
		return new ViewModel( array(
				'user_location' => $this->_userloc,
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'groupObj'         => $this->getDefinedTable(Store\GroupTable::class),
		));		
	}
	
	/**
	 * Get Item List According to Activity
	 */
	public function getitemAction()
	{	
	    $this->init();
		$form = $this->getRequest()->getPost();
		$group_id = $form['group_id'];
		if($group_id == '1'):
			$items = $this->getDefinedTable(Store\ItemTable::class)->get(array('i.item_group' => $group_id));
		else:
			$items = $this->getDefinedTable(Store\ItemTable::class)->get(array('i.item_group' => $group_id));
		endif;
		$stock_items.="<option value=''></option>";
		foreach($items as $item):
		    $item_id =  $item['id'];
			if($item_id):
				$stock_items .="<option value='".$item_id."'>".$item['name']."</option>";
			endif;
		endforeach;
		echo json_encode(array(
				'stock_items' => $stock_items,
		));
		exit;
	}

	/**
	 * Get Uom
	**/
	public function getitemuomAction()
	{
		$this->init();		
		$form = $this->getRequest()->getPost();		
		$itemID = $form['item_id'];

		$itemDtls = $this->getDefinedTable(Store\ItemTable::class)->get($itemID);
		$itemuoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$itemID,'ui.costing_uom'=>'1'));
		$uom_for .="<option value=''></option>";
		foreach($itemDtls as $dtl):
			$uom_for .="<option value='".$dtl['st_uom_id']."' selected>".$dtl['uom_code']."</option>";		
		endforeach;	
		echo json_encode(array(
				'uom' => $uom_for,
		));
		exit;
	}
	/**
	 * Edit Purchase Order Action
	 * Ammend Purchase Order Action
	 */
	public function editporderAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			
			$item         = $form['item'];
			$quantity     = $form['quantity'];
			$rate         = $form['rate'];
			for($i=0; $i < sizeof($item); $i++):
				if(isset($item[$i]) && $item[$i] > 0):
					$net_amount += $quantity[$i] * $rate[$i];
				endif;
			endfor; 
			$data = array(
					'id'			   => $form['purchase_order_id'],
					'purchase_order_no'=> $form['purchase_order_no'],
					'location'         => $form['location'],
					'cost_center'      => $form['cost_center'],
					'supplier'         => $form['supplier'],
					'purchase_order_date' => $form['purchase_order_date'],
					'total_amount'        => $net_amount,
					'quotation_no'     => $form['quotation_no'],
					'quotation_date'   => $form['quotation_date'],
					'note'             => $form['note'],
					'delivery_location'=> $form['delivery_location'],
					'paid_amount'      => $form['paid_amount'],
					'author'           => $this->_author,
					'modified'         => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\PurchaseOrderTable::class)->save($data);
			if($result > 0){
				$details_id   = $form['details_id'];
				$item         = $form['item'];
				$uom          = $form['uom'];
				$quantity     = $form['quantity'];
				$rate         = $form['rate'];
				$remarks      = $form['remarks'];
				$delete_rows  = $this->getDefinedTable(Store\PODetailsTable::class)->getNotIn($details_id, array('purchase_order' => $result));
				for($i=0; $i < sizeof($item); $i++):
				    if(isset($item[$i]) && is_numeric($quantity[$i])):
						$po_details = array(
							'id'             => $details_id[$i],
							'purchase_order' => $result,
							'item'           => $item[$i],
							'uom'            => $uom[$i],
							'quantity'  	 => $quantity[$i],
							'rate'      	 => $rate[$i],
							'remark' 	     => $remarks[$i],
							'author'    	 => $this->_author,
							'created'		 => $this->_created,
							'modified'  	 => $this->_modified
						);
						$po_details   = $this->_safedataObj->rteSafe($po_details);
						$this->getDefinedTable(Store\PODetailsTable::class)->save($po_details);
					endif;
				endfor;
				//deleting deleted table rows form database table;
				//print_r($delete_rows);exit;
				foreach($delete_rows as $delete_row):
					//echo $delete_row['id'];
				 	$this->getDefinedTable(Store\PODetailsTable::class)->remove($delete_row['id']);
				endforeach;
				
				$this->flashMessenger()->addMessage("success^ Successfully updated Purchase order no. ". $form['purchase_order_no']);
				return $this->redirect()->toRoute('inpurorder', array('action' =>'viewporder', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to update Purchase Order");
				return $this->redirect()->toRoute('inpurorder', array('action' => 'editporder', 'id' => $this->_id));
			}
		}	
		$pur_order = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get($this->_id);
		
		foreach ($pur_order as $porder):
		    if( $porder['status'] == "3" || $porder['status'] == "4"){
				 $this->flashMessenger()->addMessage("notice^ Cannot edit a canceled/closed Purchase Order");
				 return $this->redirect()->toRoute('inpurorder');		     	  
		     }
			 $po_details = $this->getDefinedTable(Store\PODetailsTable::class)->get(array('d.purchase_order' => $porder['id'])); 
		endforeach;
		
		return new ViewModel( array(
				'purchase_order' => $pur_order,
		        'po_details' => $po_details,
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'itemuomObj'      => $this->getDefinedTable(Stock\ItemUomTable::class),
				'itemsObj' => $this->getDefinedTable(Store\ItemTable::class),
				'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
				'groupObj'=> $this->getDefinedTable(Store\GroupTable::class),
		) );	
	}

    /**
	 * View Individual Purchase Order
	 *
	**/
	public function viewporderAction(){
		$this->init();	
		return new ViewModel( array(
				'purchase_order' => $this->getDefinedTable(Store\PurchaseOrderTable::class)->get($this->_id),
		        'PODObject'      => $this->getDefinedTable(Store\PODetailsTable::class),
    		    'userTable'      => $this->getDefinedTable(Acl\UsersTable::class),
				'locationObj'    => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj'      => $this->getDefinedTable(Acl\StatusTable::class),
				'uomObj'=> $this->getDefinedTable(Stock\UomTable::class),
		) );
	}
	
	/**
	 * pending purchase order action
	 * Send PO
	 */
	public function pendingporderAction(){
		$this->init();
		
		$pur_order = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get($this->_id);
		
		foreach ($pur_order as $porder):
		if( $porder['status'] == "1"){
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 2,
					'author'	    => $this->_author,
     				'modified'      => $this->_modified,
					);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\PurchaseOrderTable::class)->save($data);
			if($result):
				$this->flashMessenger()->addMessage("success^ Successfully send PO No.".$porder['purchase_order_no']);
				return $this->redirect()->toRoute('inpurorder',array('action'=>'viewporder','id'=>$this->_id));
			endif;
		}
		endforeach;
	}
	
	/**
	 * cancel purchase order Action
	 * 
	 */
	public function cancelporderAction(){
		$this->init();
		
		$pur_order = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get($this->_id);
		
		foreach ($pur_order as $porder):
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 4,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\PurchaseOrderTable::class)->save($data);
			if($result):
			$this->flashMessenger()->addMessage("success^ Successfully cancelled PO No.".$porder['po_no']);
			return $this->redirect()->toRoute('inpurorder',array('action'=>'viewporder','id'=>$this->_id));
			endif;
		endforeach;
	}
	
	/**
	 * commit purchase order Action
	 * 
	 */
	public function commitporderAction(){
		$this->init();
		
		$pur_order = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get($this->_id);
		
		foreach ($pur_order as $porder):
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 3,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Store\PurchaseOrderTable::class)->save($data);
		if($result):
			$this->flashMessenger()->addMessage("success^ Successfully commited PO No.".$porder['po_no']);
			return $this->redirect()->toRoute('inpurorder',array('action'=>'viewporder','id'=>$this->_id));
		endif;
		endforeach;
	}
}
