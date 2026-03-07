<?php
namespace Purchase\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Stock\Model As Stock;
use Purchase\Model As Purchase;
class SupplierController extends AbstractActionController
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
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	public function indexAction()
	{
		$this->init();
		$year ='';
		$month = '';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
				
			$year = $form['year'];
			$month = $form['month'];
			$activity = $form['activity'];
				
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$data = array(
					'year' => $year,
					'month' => $month,
					'activity' => $activity,
			);
		}else{
			$month = ($month == 0)? date('m'):$month;
			$year = ($year == 0)? date('Y'):$year;
			$activity = '-1';
			
			$data = array(
					'year' => $year,
					'month' => $month,
					'activity' => $activity,
			);
		}
		$supplier_invoice = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getDateWise('inv_record_date',$year,$month,$activity);
		
		return new ViewModel( array(
                'title'       => 'Supplier Invoice',
				'sup_inv'     => $supplier_invoice,
				'minYear'     => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getMin('inv_record_date'),
				'data'        => $data,
				'partyObj'    => $this->getDefinedTable(Accounts\PartyTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'purchase_receiptObj' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
		) );
	}
	
	/** 
	 * Add Purchase Order Action
	 */
	public function addsupplierinvAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();	
			$preceipt_id = $form['preceipt']; 
			$supplier_id = $form['supplier'];
			
			if($preceipt_id == "" || $preceipt_id == 0):
				$data1 = array(
						'location'			=> $form['location'],
						'activity'			=> $form['activity'],
						'invoice_no'		=> $form['invoice_no'],
						'supplier'			=> $form['supplier'],
						'inv_defn'			=> $form['inv_defn'],
						'inv_date'			=> $form['inv_date'],
						'inv_record_date'	=> $form['inv_record_date'],
						/*'inv_due_date'		=> $form['inv_due_date'],*/
						'purchase_amount'	=> $form['purchase_amount'],
						'freight_charge'	=> $form['freight_charge'],
						'deduction_amount'	=> $form['deduction_amount'],
						'net_inv_amount'	=> $form['net_inv_amount'],
						'payable_amount'	=> $form['payable_amount'],
						'paid_amount'		=> '',
						'note'              => $form['note'],
						'purchase_receipt'	=> $form['purchase_receipt'],
						'status'			=> 1,
						'costing'			=> $form['po_costing'],
						'author'			=> $this->_author,
						'created'			=> $this->_created,
						'modified'			=> $this->_modified,
				);
				$data1   = $this->_safedataObj->rteSafe($data1);
				//echo "<pre>";print_r($data1);exit;
				$result1 = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->save($data1);
				if($result1 > 0):	
					$item		= $form['item'];
					$uom		= $form['uom'];
					//$unit_uom 	= $form['unit_uom'];
					$quantity	= $form['quantity'];
					$rate		= $form['rate'];
					$amount		= $form['amount'];
					$mrp        = $form['mrp'];
					$prn_details_id = $form['prn_details_id'];
					for($i=0; $i < sizeof($item); $i++):
						$data2 = array(
								'supplier_invoice'	=> $result1,
								'item'				=> $item[$i],
								'uom'				=> $uom[$i],
								//'unit_uom'			=> $unit_uom[$i],
								'quantity'			=> $quantity[$i],
								'rate'				=> $rate[$i],
								'mrp'               => $mrp[$i],
								'prn_details_id'    => $prn_details_id[$i],
								'author'			=> $this->_author,
								'created'			=> $this->_created,
								'modified'			=> $this->_modified,
						);
						$data2 	= $this->_safedataObj->rteSafe($data2);
						$result2 = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->save($data2);
						if($form['inv_defn'] != '-1'):
							$inv_def_fields = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->get(array('d.inv_def' => $form['inv_defn']));
							foreach($inv_def_fields as $row):
								$data3 = array(
										'sinv_details'	=> $result2,
										'def_details'	=> $row['id'],
										'data'			=> $form[$row['id']][$i],
										'author'		=> $this->_author,
										'created'		=> $this->_created,
										'modified'		=> $this->_modified,
								);
								$data3 = $this->_safedataObj->rteSafe($data3);
								$result3 = $this->getDefinedTable(Purchase\SupInvDefDataTable::class)->save($data3);
							endforeach;
						endif;
					endfor;
					$this->flashMessenger()->addMessage("success^ Successfully added new supplier invoice ".$form['invoice_no']);
					return $this->redirect()->toRoute('supplier', array('action'=>'viewsupplierinv', 'id'=>$result1));
				else:
					$this->flashMessenger()->addMessage("Failed^ Failed to add new invoice");
					return $this->redirect()->toRoute('supplier', array('action' => 'addsupplierinv'));
				endif;
			else:
				$pur_receipts 		= $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get($preceipt_id);
				$pur_receipt_details= $this->getDefinedTable(Purchase\PRDetailsTable::class)->get(array('purchase_receipt' => $preceipt_id));
				
			endif;
		}		
		return new ViewModel( array(
		        'title'     => "Add Supplier Invoice",
				'suppliers'   => $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
				'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'pur_receipts' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getAll(),
				//'pur_receipts'		=> $pur_receipts,
				//'pr_details'  		=> $pur_receipt_details,
				//'inv_definations'	=> $this->getDefinedTable(Purchase\InvDefinationTable::class)->get(array('supplier' => $supplier_id)),
				'itemObj'		    => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'			=> $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'	    => $this->getDefinedTable(Stock\ItemUomTable::class),	
				'po_detailsObj'     => $this->getDefinedTable(Purchase\PODetailsTable::class),
				'poObj'             => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
				'item'              => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
		));
	}
	
	/**
	 * Get Purchase Receipt
	 */
	public function getpreceiptAction()
	{
		$this->init();
		
		$ViewModel = new ViewModel(array(
				'suppliers' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role' => '1')),
		));
		
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * Get Purchase Receipt by Supplier
	 */
	public function getreceiptbysupAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		$supplier_id = $form['supplier_id'];
		
		$purchase_receipts = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get(array('status' => 3, 'supplier' => $supplier_id, 'invoiced'=>''));
		
		$prn.="<option value=''></option>";
		foreach($purchase_receipts as $purchase_receipt):
			$prn .="<option value='".$purchase_receipt['id']."'>".$purchase_receipt['prn_no']."|".$purchase_receipt['activity']."|".$purchase_receipt['prn_date']."|".$purchase_receipt['challan_no']."</option>";
		endforeach;
		
		echo json_encode(array(
				'prn' => $prn,
		));
		exit;
	}
	
	/**
	 * Get the Unit Uom, Quantity and Rate on Change of Uom
	 */
	public function getuomchangeAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$item_id = $form['item_id'];
		$uom_id = $form['uom_id'];
		$prn_id = $form['prn_id'];
		$prn_detail_id = $form['prn_detail_id'];
		$sinv_dtl_id = $form['sinv_dtl_id'];
		$uom_task = $form['uom_task'];
		  
		//change in unit uom
		$basic_uom = $this->getDefinedTable(Stock\ItemTable::class)->getColumn($item_id, 'uom');
		$basic_uom_type = $this->getDefinedTable(Stock\UomTable::class)->getColumn($basic_uom,'uom_type');
		
		//change in quantity
		if($prn_detail_id > 0):
			$prn_details = $this->getDefinedTable(Purchase\PRDetailsTable::class)->get($prn_detail_id);
		else:
			$prn_details = $this->getDefinedTable(Purchase\PRDetailsTable::class)->get(array('purchase_receipt'=>$prn_id,'item'=>$item_id));
		endif;
		foreach($prn_details as $prn_detail);
		
		$prn_uom_conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$prn_detail['uom_id']),'conversion');
		$uom_conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$uom_id),'conversion');
		
		//supplier invoice edit
		$sinv_details = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->get($sinv_dtl_id);
		foreach($sinv_details as $sinv_detail);
		$sinv_uom_conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$sinv_detail['uom_id']),'conversion');
		$sinv_basic_qty = ($basic_uom == $sinv_detail['uom_id'])?$sinv_detail['quantity']: $sinv_detail['quantity'] * $sinv_uom_conversion;
		$sinv_quantity = ($basic_uom == $uom_id)?$sinv_basic_qty: $sinv_basic_qty / $uom_conversion;
		
		$sinv_basic_rate = ($basic_uom == $sinv_detail['uom_id'])?$sinv_detail['rate']: $sinv_detail['rate'] / $sinv_uom_conversion;
		$sinv_rate = ($basic_uom == $uom_id)?$sinv_basic_rate: $sinv_basic_rate * $uom_conversion;
		
		$prn_basic_qty = ($basic_uom == $prn_detail['uom_id'])?$prn_detail['sound_qty']: $prn_detail['sound_qty'] * $prn_uom_conversion;
		$quantity = ($basic_uom == $uom_id)?$prn_basic_qty: $prn_basic_qty / $uom_conversion;
		
		//change in rate
		if($prn_detail_id > 0):
			$po_details_id = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getColumn($prn_detail_id,'po_details_id');
			if($po_details_id > 0):
				$po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get($po_details_id);
			else:
				$po_id = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($prn_id,'purchase_order');
				$po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order'=>$po_id,'item'=>$item_id));
			endif;
		else:
			$po_id = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($prn_id,'purchase_order');
			$po_details = $this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order'=>$po_id,'item'=>$item_id));
		endif;
		foreach($po_details as $po_detail);
		
		$po_uom_conversion = $this->getDefinedTable(Stock\ItemUomTable::class)->getColumn(array('item'=>$item_id, 'uom'=>$po_detail['uom_id']),'conversion');
		
		$po_basic_rate = ($basic_uom == $po_detail['uom_id'])?$po_detail['rate']: $po_detail['rate'] / $po_uom_conversion;
		$rate = ($basic_uom == $uom_id)?$po_basic_rate: $po_basic_rate * $uom_conversion;
		if($uom_task == 1):
			echo json_encode(array(
					//'unit_uom' => $unit_uom,
					'quantity' => $quantity,
					'rate' => $rate,
			));
		else:
			echo json_encode(array(
					'quantity' => $sinv_quantity,
					'rate' => $sinv_rate,
			));
		endif;
		
		exit;
	}
	
	
	/**
	 * Get Invoice Defination Fields by Invoice 
	 * Defination ID
	 */
	public function getinvdeffieldsAction()
	{
		$this->init();
		
		$ViewModel = new ViewModel(array(
				'inv_def_fields' => $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->get(array('d.inv_def' => $this->_id)),
		));
		
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * View Individual Purchase Order
	 *
	 **/
	public function viewsupplierinvAction()
	{
		$this->init();
	
		return new ViewModel( array(
				'title'	=> 'View Invoice',
				'supplier_invoice'	=> $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->get($this->_id),
				'inv_defObj'	   => $this->getDefinedTable(Purchase\InvDefinationTable::class),
				'prnObj'      => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				/** for the table **/
				'inv_def_detailsObj'=> $this->getDefinedTable(Purchase\InvDefDetailsTable::class),
				'sup_inv_detailsObj'=> $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
				'sup_inv_def_dataObj' => $this->getDefinedTable(Purchase\SupInvDefDataTable::class),
				'prn_detailsObj'      => $this->getDefinedTable(Purchase\PRDetailsTable::class),
				'itemObj'             => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj'          => $this->getDefinedTable(Stock\ItemUomTable::class),
		) );
	}
	
	/**
	 * Edit Invoice Action
	*/
	public function editsupplierinvAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			
			$data1 = array(
					'id'				=> $form['sup_inv_id'],
					'location'			=> $form['location'],
					'activity'			=> $form['activity'],
					'invoice_no'		=> $form['invoice_no'],
					'supplier'			=> $form['supplier'],
					'inv_defn'			=> $form['inv_defn'],
					'inv_date'			=> $form['inv_date'],
					'inv_record_date'	=> $form['inv_record_date'],
					/*'inv_due_date'		=> $form['inv_due_date'],*/
					'purchase_amount'	=> $form['purchase_amount'],
					'freight_charge'	=> $form['freight_charge'],
					'deduction_amount'	=> $form['deduction_amount'],
					'net_inv_amount'	=> $form['net_inv_amount'],
					'payable_amount'	=> $form['payable_amount'],
					'paid_amount'		=> '',
					'note'              => $form['note'],
					'purchase_receipt'	=> $form['purchase_receipt'],
					'status'			=> 1,
					/*'costing'			=> '',*/
					'author'			=> $this->_author,
					'modified'			=> $this->_modified,
			);
			$data1   = $this->_safedataObj->rteSafe($data1);
			$result1 = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->save($data1);

			if($result1 > 0):
				$sup_inv_details_id = $form['sup_inv_details_id'];
				$item		= $form['item'];
				$uom		= $form['uom'];
				//$unit_uom 	= $form['unit_uom'];
				$quantity	= $form['quantity'];
				$rate		= $form['rate'];
				$amount		= $form['amount'];
				$mrp        = $form['mrp'];
				
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
						$data2 = array(
								'id'				=> $sup_inv_details_id[$i],
								'supplier_invoice'	=> $result1,
								'item'				=> $item[$i],
								'uom'				=> $uom[$i],
								//'unit_uom'			=> $unit_uom[$i],
								'quantity'			=> $quantity[$i],
								'rate'				=> $rate[$i],
								'mrp'               => $mrp[$i],
								'author'			=> $this->_author,
								'modified'			=> $this->_modified,
						);
						$data2 	= $this->_safedataObj->rteSafe($data2);
						$result2 = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->save($data2);
						
						$deleterows = $this->getDefinedTable(Purchase\SupInvDefDataTable::class)->get(array('sinv_details'=>$result2));
						foreach($deleterows as $deleterow):
							$this->getDefinedTable(Purchase\SupInvDefDataTable::class)->remove($deleterow['id']);
						endforeach;
						if($form['inv_defn'] != '-1'):
							$inv_def_fields = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->get(array('d.inv_def' => $form['inv_defn']));
							foreach($inv_def_fields as $row):
								$data3 = array(
										'sinv_details'	=> $result2,
										'def_details'	=> $row['id'],
										'data'			=> $form[$row['id']][$i],
										'author'		=> $this->_author,
										'created'		=> $this->_created,
										'modified'		=> $this->_modified,
								);
								$data3 = $this->_safedataObj->rteSafe($data3);
								$result3 = $this->getDefinedTable(Purchase\SupInvDefDataTable::class)->save($data3);
							endforeach;
						endif;
					endif;
				endfor;
				
				$this->flashMessenger()->addMessage("success^ Successfully updated supplier invoice ".$form['invoice_no']);
				return $this->redirect()->toRoute('supplier', array('action'=>'viewsupplierinv', 'id'=>$result1));
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update supplier invoice");
				return $this->redirect()->toRoute('supplier', array('action' => 'editsupplierinv','id'=>$this->_id));
			endif;
		}
		
		$supplier_invoice	= $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->get($this->_id);
		
		foreach ($supplier_invoice as $sup_inv):
		     if($sup_inv['status'] == "3" || $sup_inv['status'] == "4"){
                     $this->flashMessenger()->addMessage("notice^ Cannot edit a canceled/closed Supplier Invoice");
				     return $this->redirect()->toRoute('supplier');		     	  
		     }
		endforeach;
		
		return new ViewModel( array(
				'title'			   => 'Edit Invoice',
				'supplier_invoice' => $supplier_invoice,
				'regions'     	   => $this->getDefinedTable(Aministration\RegionTable::class)->getAll(),
				'locationObj' 	   => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'       => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'suppliers'        => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'inv_defObj'	   => $this->getDefinedTable(Purchase\InvDefinationTable::class),
				/** for the table **/
				'inv_def_detailsObj' => $this->getDefinedTable(Purchase\InvDefDetailsTable::class),
				'sup_inv_detailsObj' => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
				'sup_inv_def_dataObj'=> $this->getDefinedTable(Purchase\SupInvDefDataTable::class),
				'itemObj'		=> $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'		=> $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'	=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'prnObj'        => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
		) );	
	}

	/**
	 * commit invoice Action
	 *
	 */
	public function commitsupplierinvAction()
	{
		$this->init();
		
		$supplier_invoice	= $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->get($this->_id);
		foreach ($supplier_invoice as $sup_inv);
		$this->_connection->beginTransaction(); //***Transaction begins here***//
		//echo "<pre>"; print_r($sup_inv);exit;
		//In Purchase Received - Alter as Invoiced
		$prn_data = array(
				'id' => $sup_inv['purchase_receipt'],
				'invoiced' => '1',
		);
		$prn_data   = $this->_safedataObj->rteSafe($prn_data);
		$prn_result = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->save($prn_data);
		
		//In Purchase Order - Add the Received Amount
		$po_id = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($sup_inv['purchase_receipt'],'purchase_order');
		$po_received_amt = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getColumn($po_id,'received_amount');
		$total_received = $po_received_amt + $sup_inv['purchase_amount'];
		
		$po_data = array(
				'id' => $po_id,
				'received_amount' => $total_received,
		);
		$po_data   = $this->_safedataObj->rteSafe($po_data);
		$po_result = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->save($po_data);
		
		
		
		//In Supplier Invoice - Now Change the Status as Committed
		if(prn_result){
			$data = array(
					'id'			=>$this->_id,
					'status' 		=> 3,
					'author'	    => $this->_author,
					'modified'      => $this->_modified,
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result1 = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->save($data);
			
			if($result1){
				//to book voucher in finance module for supplier invoice only for p/ling bulk or head office
				$location = $sup_inv['location_id'];
				
				if($location == '22' || $location == '7' || $location == '100'):
					$voucherType = '5';
					//generate voucher no
					$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
					$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');
					
					$date = date('ym',strtotime($sup_inv['inv_date']));  
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
							    'voucher_date' =>$sup_inv['inv_record_date'],
								'voucher_type' => $voucherType,
								'doc_id' => $sup_inv['invoice_no'],
								'doc_type' => 'SupplierInvoice',
								'voucher_no' => $voucher_no,
								'voucher_amount' => str_replace( ",","",$sup_inv['payable_amount']),
								'remark' => $sup_inv['note'],
								'status' => 3, // status initiated
								'author' =>$this->_author,
								'created' =>$this->_created,
								'modified' =>$this->_modified,	
					);
					$fa_data = $this->_safedataObj->rteSafe($fa_data);
					$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($fa_data);
					
						if($result):
                                                        //save transaction id in supplier inv table
							$txn = array(
									'id' => $this->_id,
									'transaction_id' => $result,
							);
							$this->_safedataObj->rteSafe($txn);
							$this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->save($txn);

							$count=0;
							$activity = $sup_inv['activity_id'];
							
							if($activity == 1){
								$head = 121;
							}elseif($activity == 7){ $head = 278; }
							else{$head = 127;}
							$subheadDtls = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>$head,'sh.ref_id'=>$sup_inv['supplier_id']));
							foreach($subheadDtls as $subheadD);
							
                                                        if($subheadD > 0){ $count+=1;}
							$debit = $sup_inv['payable_amount'];
							$credit = 0;
							
							$fa_data1 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => $sup_inv['activity_id'],
										'head' => $head,
										'sub_head' => $subheadD['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit))? $debit:'0.000',
										'credit' => (isset($credit))? $credit:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data1 = $this->_safedataObj->rteSafe($fa_data1);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data1);
							
							$subheadDtls2 = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>'98','sh.ref_id'=>$sup_inv['supplier_id']));
							foreach($subheadDtls2 as $subheadD2);

                                                        if($subheadD2 > 0){ $count+=1;}
							$debit2 = 0;
							$credit2 = $sup_inv['payable_amount'];
							
							$fa_data2 = array(
										'transaction' => $result,
										'location' => $location,
										'activity' => $sup_inv['activity_id'],
										'head' => 98,
										'sub_head' =>  $subheadD2['id'],
										'bank_ref_type' => '',
										'cheque_no' => '',
										'debit' => (isset($debit2))? $debit2:'0.000',
										'credit' => (isset($credit2))? $credit2:'0.000',
										'ref_no'=> '',
										'type' => '2', //System Generated
										'author' =>$this->_author,
										'created' =>$this->_created,
										'modified' =>$this->_modified,
							);
							$fa_data2 = $this->_safedataObj->rteSafe($fa_data2);
							$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($fa_data2);
							
							if($count == 2){
								$this->_connection->commit(); // commit transaction on success
								$this->flashMessenger()->addMessage("success^ Successfully commited Supplier Invoice ".$sup_inv['invoice_no']." and Booking of Voucher ".$voucher_no);
							}else{
								$this->_connection->rollback(); // rollback transaction over failure
								$this->flashMessenger()->addMessage("error^ Subhead Missing for this Party, Unsuccessfull to commit Supplier Invoice");
							}
						endif;
				endif;
			}
		}
		return $this->redirect()->toRoute('supplier',array('action'=>'viewsupplierinv', 'id'=>$result1));
	}
	/**
	 * Cancel Supplier Invoice
	**/
	public function cancelsupplierinvAction()
	{
		$this->init();
		$data = array(
				'id'			=>$this->_id,
				'status' 		=> 4,
				'author'	    => $this->_author,
				'modified'      => $this->_modified,
		);
		$data   = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->save($data);
		$invoice_no = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->getColumn($this->_id,'invoice_no');
		if($result):
			$this->flashMessenger()->addMessage("success^ Successfully cancelled Supplier Invoice ".$invoice_no);
		else:
			$this->flashMessenger()->addMessage("error^ Unsuccessfully to cancell Supplier Invoice ".$invoice_no);
		endif;
		return $this->redirect()->toRoute('supplier');
	}
/**
	 * Rectify Supplier Invoice Action
	*/
	public function rectifysinvAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$activity_data = array(
					'process'    => '3',
					'process_id' => $form['sup_inv_id'],
					'status'     => '11',
					'remarks'    => $form['note'],
					'author'     => $this->_author,
					'created'    => $this->_created,
					'modified'   => $this->_modified,
			);
			$activityLogResult = $this->getDefinedTable(Administration\ActivityLogTable::class)->save($activity_data);
			$data1 = array(
					'id'				=> $form['sup_inv_id'],
					'location'			=> $form['location'],
					'activity'			=> $form['activity'],
					'invoice_no'		=> $form['invoice_no'],
					'supplier'			=> $form['supplier'],
					'inv_defn'			=> $form['inv_defn'],
					'inv_date'			=> $form['inv_date'],
					'inv_record_date'	=> $form['inv_record_date'],
					/*'inv_due_date'		=> $form['inv_due_date'],*/
					'purchase_amount'	=> $form['purchase_amount'],
					'freight_charge'	=> $form['freight_charge'],
					'deduction_amount'	=> $form['deduction_amount'],
					'net_inv_amount'	=> $form['net_inv_amount'],
					'payable_amount'	=> $form['payable_amount'],
					'paid_amount'		=> '',
					'note'              => $form['note'],
					'purchase_receipt'	=> $form['purchase_receipt'],
					'status'			=> 3,
					/*'costing'			=> '',*/
					'author'			=> $this->_author,
					'modified'			=> $this->_modified,
			);
			$data1   = $this->_safedataObj->rteSafe($data1);
			$result1 = $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->save($data1);

			if($result1 > 0):
				$sup_inv_details_id = $form['sup_inv_details_id'];
				$item		= $form['item'];
				$uom		= $form['uom'];
				//$unit_uom 	= $form['unit_uom'];
				$quantity	= $form['quantity'];
				$rate		= $form['rate'];
				$amount		= $form['amount'];
				$mrp        = $form['mrp'];
				
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
						$data2 = array(
								'id'				=> $sup_inv_details_id[$i],
								'supplier_invoice'	=> $result1,
								'item'				=> $item[$i],
								'uom'				=> $uom[$i],
								//'unit_uom'			=> $unit_uom[$i],
								'quantity'			=> $quantity[$i],
								'rate'				=> $rate[$i],
								'mrp'               => $mrp[$i],
								'author'			=> $this->_author,
								'modified'			=> $this->_modified,
						);
						$data2 	= $this->_safedataObj->rteSafe($data2);
						$result2 = $this->getDefinedTable(Purchase\SupInvDetailsTable::class)->save($data2);
						
						$deleterows = $this->getDefinedTable(Purchase\SupInvDefDataTable::class)->get(array('sinv_details'=>$result2));
						foreach($deleterows as $deleterow):
							$this->getDefinedTable(Purchase\SupInvDefDataTable::class)->remove($deleterow['id']);
						endforeach;
						if($form['inv_defn'] != '-1'):
							$inv_def_fields = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->get(array('d.inv_def' => $form['inv_defn']));
							foreach($inv_def_fields as $row):
								$data3 = array(
										'sinv_details'	=> $result2,
										'def_details'	=> $row['id'],
										'data'			=> $form[$row['id']][$i],
										'author'		=> $this->_author,
										'created'		=> $this->_created,
										'modified'		=> $this->_modified,
								);
								$data3 = $this->_safedataObj->rteSafe($data3);
								$result3 = $this->getDefinedTable(Purchase\SupInvDefDataTable::class)->save($data3);
							endforeach;
						endif;
					endif;
				endfor;
				
				$this->flashMessenger()->addMessage("success^ Successfully rectified supplier invoice ".$form['invoice_no']);
				return $this->redirect()->toRoute('supplier', array('action'=>'viewsupplierinv', 'id'=>$result1));
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to rectified supplier invoice");
				return $this->redirect()->toRoute('supplier', array('action' => 'editsupplierinv','id'=>$this->_id));
			endif;
		}
		
		$supplier_invoice	= $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)->get($this->_id);
		
		return new ViewModel( array(
				'title'			   => 'Rectify Supplier Invoice',
				'supplier_invoice' => $supplier_invoice,
				'regions'     	   => $this->getDefinedTable(Aministration\RegionTable::class)->getAll(),
				'locationObj' 	   => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'       => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'suppliers'        => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
				'inv_defObj'	   => $this->getDefinedTable(Purchase\InvDefinationTable::class),
				'inv_def_detailsObj' => $this->getDefinedTable(Purchase\InvDefDetailsTable::class),
				'sup_inv_detailsObj' => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
				'sup_inv_def_dataObj'=> $this->getDefinedTable(Purchase\SupInvDefDataTable::class),
				'itemObj'		=> $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'		=> $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj'	=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'prnObj'        => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'costingsheetObj' => $this->getDefinedTable(Stock\CostingsheetTable::class),
				'costingitemObj' => $this->getDefinedTable(Stock\CostingitemsTable::class),
		) );	
	}
}

