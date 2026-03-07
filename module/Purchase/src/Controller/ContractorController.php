<?php
namespace Purchase\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Stock\Model As Stock;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Administration\Model As Administration;
class ContractorController extends AbstractActionController
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
		//$this->_userloc = $this->_user->location;
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
	}
	
	/**
	 * repacking Action for contractor invoice
	 */
	public function repackingAction()
	{
		$this->init();
		$year = '';
		$month = '';
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
		$repackings = $this->getDefinedTable(Purchase\RepackingTable::class)->getDateWise('repacking_date',$year,$month);
		return new ViewModel(array(
				'title'      => 'Repacking/Shifting Task',
				'data'       => $data,
				'minYear'    => $this->getDefinedTable(Purchase\RepackingTable::class)->getMin('repacking_date'),
				'repackings' => $repackings,
				'partyObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'locationObj'=> $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj'=> $this->getDefinedTable(Administration\ActivityTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	
	/**
	 * add repacking Action
	 */
	public function addrepackingAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');			
			$date = date('ym',strtotime($form['repacking_date']));		
			
			$tmp_InvNo = ($form['work_type']=='1')?$location_prefix."RP".$date:$location_prefix."SH".$date; 			
			$results = $this->getDefinedTable(Purchase\RepackingTable::class)->getMonthlyInv($tmp_InvNo);
			
			$inv_no_list = array();
            foreach($results as $result):
	       		array_push($inv_no_list, substr($result['repacking_no'], 8)); 
		   	endforeach;
            $next_serial = max($inv_no_list) + 1;
               
			switch(strlen($next_serial)){
				case 1: $next_inv_serial = "000".$next_serial; break;
			    case 2: $next_inv_serial = "00".$next_serial;  break;
			    case 3: $next_inv_serial = "0".$next_serial;   break;
			   	default: $next_inv_serial = $next_serial;      break; 
			}					   
			
			$repacking_no = $tmp_InvNo.$next_inv_serial;
			$data = array(
					'repacking_no'   => $repacking_no,
					'repacking_date' => $form['repacking_date'],
					'work_type'      => $form['work_type'],
					'contractor'     => $form['contractor'],
					'location'       => $form['location'],
					'activity'       => $form['activity'],
					'item'           => $form['item'],
					'uom'            => $form['uom'],
					'quantity'       => $form['quantity'],
					'rate'           => $form['rate'],
					'amount'         => $form['amount'],
					'status'         => '1',
					'author'         => $this->_author,
					'created'        => $this->_created,
					'modified'       => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>";print_r($data);exit;
			$result = $this->getDefinedTable(Purchase\RepackingTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added new work charge ".$repacking_no);
			else:
				$this->flashMessenger()->addMessage("failed^ Unsuccessful to add new work Charge");
			endif;
			return $this->redirect()->toRoute('contractor', array('action'=>'repacking'));
		endif;
		
		$ViewModel = new ViewModel(array(
				'contractors' => $this->getDefinedTable(Stock\ContractorAgreementTable::class)->getAll(),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'user_location' => $this->_userloc,
				'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	/**
	 * get item by activity Action
	**/
	public function getitembyactivityAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$activity_id = $form['activity_id'];
		$items = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.activity' => $activity_id));
		
		$stock_item.="<option value=''></option>";
		foreach($items as $item):
			$stock_item .="<option value='".$item['id']."'>".$item['code']."</option>";
		endforeach;
		
		echo json_encode(array(
				'stock_item' => $stock_item,
		));
		exit;
	}
	
	/**
	 * Get Item UOM According to Item
	*/
	public function getuombyitemAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$item_id = $form['item_id'];
		
		$items = $this->getDefinedTable(Stock\ItemTable::class)->get($item_id);
		$item_uoms = $this->getDefinedTable(Stock\ItemUomTable::class)->get(array('ui.item'=>$item_id));
		
		$uoms .="<option value=''></option>";
		foreach($items as $item):
			$uoms .="<option value='".$item['st_uom_id']."'>".$item['st_uom_code']."</option>";
		endforeach;
		foreach($item_uoms as $item_uom):
			$uoms .="<option value='".$item_uom['uom_id']."'>".$item_uom['uom_code']."</option>";
		endforeach;
		
		echo json_encode(array(
				'uoms' => $uoms,
		));
		exit;
	}
	
	/**
	 * edit repacking Action
	**/
	public function editrepackingAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'             => $form['repacking_id'],
					'repacking_date' => $form['repacking_date'],
					'work_type'      => $form['work_type'],
					'contractor'     => $form['contractor'],
					'location'       => $form['location'],
					'activity'       => $form['activity'],
					'item'           => $form['item'],
					'uom'            => $form['uom'],
					'quantity'       => $form['quantity'],
					'rate'           => $form['rate'],
					'amount'         => $form['amount'],
					'status'         => '1',
					'author'         => $this->_author,
					'modified'       => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>";print_r($data);exit;
			$result = $this->getDefinedTable(Purchase\RepackingTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully edited work charge ".$form['repacking_no']);
			else:
				$this->flashMessenger()->addMessage("failed^ Unsuccessful to add new work Charge");
			endif;
			return $this->redirect()->toRoute('contractor', array('action'=>'repacking'));
		endif;
		
		$ViewModel = new ViewModel(array(
				'repackings'  => $this->getDefinedTable(Purchase\RepackingTable::class)->get($this->_id),
				'contractors' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>2)),
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'itemObj'     => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'      => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'  => $this->getDefinedTable(Stock\ItemUomTable::class),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	
	/**
	 * commit repacking Action
	 * 
	 */
	public function commitrepackingAction()
	{
		$this->init();
		
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 3,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Purchase\RepackingTable::class)->save($data);
		if($result):
			$this->flashMessenger()->addMessage("success^ Successfully commited Repacking");
			return $this->redirect()->toRoute('contractor',array('action'=>'repacking'));
		endif;
	}
	
	/**
	 * contractor invoice Action 
	 */
	public function contractorinvoiceAction()
	{
		$this->init();
		//echo 'hellow';exit;
		$year = 0;
		$month = 0;
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
		$contractor_invoice = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->getDateWise('invoice_date',$year,$month);
		return new ViewModel( array(
                'title'   => 'Contractor Invoice',
                'contractor_invoice' => $contractor_invoice,
				'minYear' => $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->getMin('invoice_date'),
				'data' => $data,
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
		) );
	}
	/**
	 * viewcontractor Action of Contractor Invoice
	**/
	public function viewcontractorAction()
	{
		$this->init();
		$con_inv_id = $this->_id;
		return new ViewModel(array(
				'title' => 'View Contractor Invoice',
				'contractor_invoice' => $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->get($con_inv_id),
				'con_inv_details' => $this->getDefinedTable(Purchase\ConInvDetailsTable::class)->get(array('co.contractor_invoice' => $con_inv_id)),   
				'userTable' => $this->getDefinedTable(Acl\UsersTable::class),
				'worktypeObj' => $this->getDefinedTable(Purchase\WorkTypeTable::class),
		));
	}
	
	/**
	 * addcontractor Action of Contractor Invoice
	 */
	public function addcontractorAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$date = date('ym',strtotime($form['invoice_date']));
			$tmp_SerialNo = $location_prefix."CP".$date;
			$results = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->getMonthlySerialNo($tmp_SerialNo);
			
			if(sizeof($results) <= 0):
				$next_serial = 1;
			else:
				$serial_no_list = array();
				foreach($results as $result):
					array_push($serial_no_list, substr($result['invoice_no'], 8));
				endforeach;
				$next_serial = max($serial_no_list) + 1;
			endif;
			switch(strlen($next_serial)){
				case 1: $next_serial_no = "000".$next_serial; break;
				case 2: $next_serial_no = "00".$next_serial;  break;
				case 3: $next_serial_no = "0".$next_serial;   break;
				default: $next_serial_no = $next_serial;      break;
			}
				
			$invoice_no = $tmp_SerialNo.$next_serial_no;
			
			$data = array(
					'invoice_no' => $invoice_no,
					'invoice_date' => $form['invoice_date'],
					'location' => $form['location'],
					'activity' => $form['activity'],
					'contractor' => $form['contractor'],
					'from_date' => $form['start_date'],
					'to_date' => $form['end_date'],
					'invoice_amount' => $form['invoice_amount'],
					'deduction' => $form['deduction'],
					'payable_amount' => $form['payable_amount'],
					'note' => $form['note'],
					'status' => 1,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$result = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->save($data);
			if($result > 0):
				$work_type = $form['work_type'];
				$qty = $form['qty'];
				$rate = $form['rate'];
				$amount = $form['amount'];
				for($i=0; $i < sizeof($amount); $i++):
					if(isset($amount[$i]) && $amount[$i] > 0):
						$con_data = array(
								'contractor_invoice' => $result,
								'work_type' => $work_type[$i],
								'qty' => $qty[$i],
								'rate' => $rate[$i],
								'amount' => $amount[$i],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' => $this->_modified,
						);
		     		$con_data   = $this->_safedataObj->rteSafe($con_data);
					//echo "<pre>"; print_r($con_data);
			     	$this->getDefinedTable(Purchase\ConInvDetailsTable::class)->save($con_data);
				   	endif; 		     
				endfor;
				//exit;
				
				$this->flashMessenger()->addMessage("success^ Successfully added new Contractor Invoice :". $invoice_no);
				return $this->redirect()->toRoute('contractor', array('action'=> 'viewcontractor', 'id'=>$result));
			else:
				$this->flashMessenger()->addMessage("Falied^ Failed to add new Contractor Invoice");
				return $this->redirect()->toRoute('contractor',array('action'=>'addcontractor'));
			endif;
		endif;//end of post
		
		return new ViewModel(array(
				'title' => 'Add Contractor Invoice',
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));
	}
        /**
	 * get the Contractor for the location selected
	 */
	public function getloccontractorAction()
	{
		$form = $this->getRequest()->getpost();
		
		$loc = $form['loc'];
		$contractor = $this->getDefinedTable(Stock\ContractorAgreementTable::class)->get(array('ca.location' => $loc));
		foreach($contractor as $con):
			$contractors.="<option value='".$con['contractor_id']."'>".$con['contractor']."</option>";
		endforeach;
		
		echo json_encode(array(
			'contractors' => $contractors,
		));
		exit;
	}
	
	/**
	 * get the PRN and Dispatch for Contractor Invoice
	 */
	public function getconinvdetailsAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getpost();
		
		$start_date = $form['start_date'];
		$end_date = $form['end_date'];
		$location = $form['location'];
		$activity = $form['activity'];
		$contractor = $form['contractor'];
		
		$data = array(
				'start_date' => $start_date,
				'end_date' => $end_date,
				'location' => $location,
				'activity' => $activity,
				'contractor' => $contractor,
		);
		//echo "<pre>";print_r($contractor_inv_data); exit;
                //last_date var it to get end_date for same location, activity and contractor
                $last_date = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->getColumn(array('location' => $location, 'activity' => $activity, 'contractor' => $contractor),'to_date');
                $last_month = date('m',strtotime($last_date));
		$ViewModel = new ViewModel(array(
				'data' => $data,
                                'last_month' => $last_month,
                                				'poObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
                                								'po_detailsObj' => $this->getDefinedTable(Purchase\PODetailsTable::class),


				'prnObj' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'prn_detailsObj' => $this->getDefinedTable(Purchase\PRDetailsTable::class),
				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'disp_detailsObj' => $this->getDefinedTable(Stock\DispatchDetailsTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
				'worktypeObj' => $this->getDefinedTable(Purchase\WorkTypeTable::class),
				'chargetaxObj' => $this->getDefinedTable(Stock\ChargesTaxTable::class),
				'repackingObj' => $this->getDefinedTable(Purchase\RepackingTable::class),
				'scalarconversionObj' => $this->getDefinedTable(Stock\ScalarConversionTable::class),
				'salesObj' => $this->getDefinedTable(Sales\SalesTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'itemclassObj' => $this->getDefinedTable(Stock\ItemClassTable::class),
				'itemgroupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * get Contractor Invoice Details
	 */
	public function getdetailsAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getpost();
		$id = $form['id'];
		$start_date = $form['start_date'];
		$end_date = $form['end_date'];
		$location = $form['location'];
		$activity = $form['activity'];
		$contractor = $form['contractor'];
		
		$data = array(
				'id'         => $id,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'location' => $location,
				'activity' => $activity,
				'contractor' => $contractor,
		);
		//echo "<pre>";print_r($data); exit;
		$ViewModel = new ViewModel(array(
				'data' => $data,
								'poObj' => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),

				'prnObj' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'prn_detailsObj' => $this->getDefinedTable(Purchase\PRDetailsTable::class),
								'po_detailsObj' => $this->getDefinedTable(Purchase\PODetailsTable::class),

				'dispatchObj' => $this->getDefinedTable(Stock\DispatchTable::class),
				'disp_detailsObj' => $this->getDefinedTable(Stock\DispatchDetailsTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
				'worktypeObj' => $this->getDefinedTable(Purchase\WorkTypeTable::class),
				'chargetaxObj' => $this->getDefinedTable(Stock\ChargesTaxTable::class),
				'repackingObj' => $this->getDefinedTable(Purchase\RepackingTable::class),
				'scalarconversionObj' => $this->getDefinedTable(Stock\ScalarConversionTable::class),
				'salesObj' => $this->getDefinedTable(Sales\SalesTable::class),
                                'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'itemclassObj' => $this->getDefinedTable(Stock\ItemClassTable::class),
				'itemgroupObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Stock\ItemSubGroupTable::class),
		));
                $this->layout('layout/detailview');
		//$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * view Contractor Invoice Details
	 */
	public function viewdetailsAction()
	{
		$this->init();
		
		return new ViewModel(array(
				'title' => 'View Contractor Invoice Details',
				'contractor_invoice' => $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->get($this->_id),
				'con_inv_details' => $this->getDefinedTable(Purchase\ConInvDetailsTable::class)->get(array('co.contractor_invoice' => $this->_id)),   
				'worktypeObj' => $this->getDefinedTable(Purchase\WorkTypeTable::class),
		));
	}
	
	/**
	 * editcontractorAction for Contractor Invoice
	 */
	public function editcontractorAction()
	{
		$this->init();
		
		$con_inv_id = $this->_id;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getpost();
				
			$data = array(
					'id' => $form['con_inv_id'],
					'invoice_date' => $form['invoice_date'],
					'location' => $form['location'],
					'activity' => $form['activity'],
					'contractor' => $form['contractor'],
					'from_date' => $form['start_date'],
					'to_date' => $form['end_date'],
					'invoice_amount' => $form['invoice_amount'],
					'deduction' => $form['deduction'],
					'payable_amount' => $form['payable_amount'],
					'note' => $form['note'],
					'status' => 1,
					'author' => $this->_author,
					'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data);exit;
			$result = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->save($data);
			if($result > 0):
			/*************** Contractor Invoice Details *********************/
			//Clear the old datas in contractor invoice details
				$clear_details = $this->getDefinedTable(Purchase\ConInvDetailsTable::class)->get(array('contractor_invoice'=>$form['con_inv_id']));
				foreach($clear_details as $clear):
					$this->getDefinedTable(Purchase\ConInvDetailsTable::class)->remove($clear['id']);
				endforeach;
				
				$work_type = $form['work_type'];
				$qty = $form['qty'];
				$rate = $form['rate'];
				$amount = $form['amount'];
				for($i=0; $i < sizeof($amount); $i++):
					if(isset($amount[$i]) && $amount[$i] > 0):
						$con_data = array(
								'contractor_invoice' => $result,
								'work_type' => $work_type[$i],
								'qty' => $qty[$i],
								'rate' => $rate[$i],
								'amount' => $amount[$i],
								'author' => $this->_author,
								'created' => $this->_created,
								'modified' => $this->_modified,
						);
		     		$con_data   = $this->_safedataObj->rteSafe($con_data);
					//echo "<pre>"; print_r($con_data);
			     	$this->getDefinedTable(Purchase\ConInvDetailsTable::class)->save($con_data);
				   	endif; 		     
				endfor;
				//exit;
				$invoice_no = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->getColumn($form['con_inv_id'],'invoice_no');
				$this->flashMessenger()->addMessage("success^ Successfully updated Contractor Invoice ".$invoice_no);
				return $this->redirect()->toRoute('contractor', array('action'=> 'viewcontractor', 'id'=>$result));
			else:
				$this->flashMessenger()->addMessage("Falied^ Failed to update Contractor Invoice");
				return $this->redirect()->toRoute('contractor',array('action'=>'addcontractor'));
			endif;
		endif;//end of post
		
		return new ViewModel(array(
				'title' => 'Edit Contractor Invoice',
				'contractor_invoice' => $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->get($con_inv_id),
				'con_inv_details' => $this->getDefinedTable(Purchase\ConInvDetailsTable::class)->get(array('contractor_invoice'=>$con_inv_id)),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'contractors' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>2)),
				'worktypeObj' => $this->getDefinedTable(Purchase\WorkTypeTable::class),
		));
	}
	/*
	 * commit contractor Action
	 
	   public function commitcontractorAction()
	    {
	  	$this->init();
		
		$contractor_inv = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->get($this->_id);
		
		foreach ($contractor_inv as $con_inv):
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 3,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->save($data);
		if($result):
			$this->flashMessenger()->addMessage("success^ Successfully commited Contractor Invoice ".$con_inv['invoice_no']);
			return $this->redirect()->toRoute('contractor',array('action'=>'viewcontractor','id'=>$this->_id));
		endif;
		endforeach;
	*    }
        * 
	*/
        public function commitcontractorAction()
	{
		$this->init();
		
		$contractor_inv = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->get($this->_id);
		foreach ($contractor_inv as $con_inv);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 3,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result1 = $this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->save($data);
		if($result1):
			//booking of contractor voucher 
			$location = $con_inv['location_id'];
			$voucherType = 11;
			
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix'); 
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');
			
			$date = date('ym',strtotime($con_inv['invoice_date']));        	    		
			$tmp_VCNo = $loc.$prefix.$date; 
			$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
		
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], 8));
			endforeach;
			$next_serial = max($pltp_no_list) + 1;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "000".$next_serial; break;
				case 2: $next_dc_serial = "00".$next_serial;  break;
				case 3: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;       break;
			}	
			$voucher_no = $tmp_VCNo.$next_dc_serial;
				
			$fa_data = array(
						'voucher_date' => $con_inv['invoice_date'],
						'voucher_type' => $voucherType,
						'doc_id' => $con_inv['invoice_no'],
						'doc_type' => 'ContractorInvoice',
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",","",$con_inv['payable_amount']),
						'remark' => $con_inv['note'],
						'status' => 3, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,	
			);
			$fa_data = $this->_safedataObj->rteSafe($fa_data);
			$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($fa_data);
			if($result):
                                //safe transaction id in contractor inv table
				$txn = array(
						'id' => $this->_id,
						'transaction_id' => $result,
				);
				$this->_safedataObj->rteSafe($txn);
				$this->getDefinedTable(Purchase\ContractorInvoiceTable::class)->save($txn);

				//for head selection as debit booking
                                $count=0;
				$subheadDtls = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>'285','sh.ref_id'=>$con_inv['contractor_id']));
				foreach($subheadDtls as $subheadD);

                                        if($subheadD == 0 || $subheadD == ""){ $count=1;}
					$debit = $con_inv['payable_amount'];
					$credit = 0;
					
					$fa_data1 = array(
								'transaction' => $result,
								'location' => $location,
								'activity' => $con_inv['activity_id'],
								'head' => $subheadD['head_id'],
								'sub_head' => $subheadD['id'],
								'bank_ref_type' => '',
								'cheque_no' => '',
								'debit' => (isset($debit))? $debit:'0.000',
								'credit' => (isset($credit))? $credit:'0.000',
								'ref_no'=> '',
								'type' => '2', //System Generated
								'author' =>$this->_author,
								'created' =>$this->_created,
					);
					$fa_data1 = $this->_safedataObj->rteSafe($fa_data1);
					$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data1);
					
					//for head as credit booking 
					$subheadDtls2 = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>'98','sh.ref_id'=>$con_inv['contractor_id']));
					foreach($subheadDtls2 as $subheadD2);

                                        if($subheadD2 == 0 || $subheadD2 == ""){ $count=1;}
					$debit = 0;
					$credit = $con_inv['payable_amount'];
					
					$fa_data2 = array(
								'transaction' => $result,
								'location' => $location,
								'activity' => $con_inv['activity_id'],
								'head' => $subheadD2['head_id'],
								'sub_head' => $subheadD2['id'],
								'bank_ref_type' => '',
								'cheque_no' => '',
								'debit' => (isset($debit))? $debit:'0.000',
								'credit' => (isset($credit))? $credit:'0.000',
								'ref_no'=> '',
								'type' => '2', //System Generated
								'author' =>$this->_author,
								'created' =>$this->_created,
					);
					$fa_data2 = $this->_safedataObj->rteSafe($fa_data2);
					$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data2);
					if($count == 0):
						$this->_connection->commit(); // commit transaction on success
						$this->flashMessenger()->addMessage("success^ Successfully commited Contractor Invoice ".$con_inv['invoice_no']."  and Voucher Booking ".$voucher_no);
					else:
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("error^ Subhead Missing for this Party, Unsuccessfull to commit Contractor Invoice");
					endif;
			endif;
		endif;
		return $this->redirect()->toRoute('contractor',array('action'=>'viewcontractor','id'=>$this->_id));
	}
}


