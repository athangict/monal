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
use Purchase\Model As Purchase;
use Laminas\EventManager\EventManagerInterface;
class BillingController extends AbstractActionController
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
	
	/**
	 * index Action
	 */
	public function indexAction()
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
		$payments = $this->getDefinedTable(Purchase\PaymentTable::class)->getDateWise('billing_date',$year,$month);
		return new ViewModel(array(
                'title'     => 'Payment',
				'payments'   => $payments,
				'minYear' => $this->getDefinedTable(Purchase\PaymentTable::class)->getMin('billing_date'),
				'data'    => $data,
				'partyObj'    => $this->getDefinedTable(Accounts\PartyTable::class),
				'userID'          => $this->_author,
		));
	}
	
	/**
	 * addpayment Action
	 */
	public function addbillAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
				$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
				$date = date('ym',strtotime($form['billing_date']));
				$machine_no = '0';
				$date = date('ym',strtotime($form['billing_date']));
				$tmp_PONo = $location_prefix."BL".$machine_no.$date;
				$results = $this->getDefinedTable(Purchase\PaymentTable::class)->getMonthlyPayment($tmp_PONo);	   
				if(sizeof($results) < 1 ):
				    $next_serial = "0001";
			    else:
				$sheet_no_list = array();
				foreach($results as $result):
						array_push($sheet_no_list, substr($result['bill_no'], -3));
				endforeach;
				$next_serial = max($sheet_no_list) + 1;
			    endif;
			  
			  switch(strlen($next_serial)){
				case 1: $next_py_serial = "000".$next_serial; break;
				case 2: $next_py_serial = "00".$next_serial;  break;
				case 3: $next_py_serial = "0".$next_serial;   break;
				default: $next_py_serial = $next_serial;      break;
			  }		  	
		   	$payment_no = $tmp_PONo.$next_py_serial;
			
		   	$payment = array(
			   		'bill_no'	=> $payment_no,
			   		'billing_date'	=> $form['billing_date'],
			   		'location'		=> $form['location'],
					'prn_no'		=> $form['prn_no'],
			   		'supplier'	    => $form['supplier'],	
		   			'net_amount'    => str_replace( ",", "",$form['net']),	
					'total'    		=> str_replace( ",", "",$form['total']),	
					'tax'    		=> $form['tax'],
					'freight'    		=> $form['freight'],
					'freight_party'	=> $form['freight_party'],	
					'freight_ref'	=> $form['freight_ref'],	
					'tax_party'		=> $form['tax_party'],
					'tax_ref'		=> $form['tax_ref'],
			   		'discount'		=> $form['discount'],
		   			'receipt_amt'	=>str_replace( ",", "",$form['receipt_amt']) ,
					'ref_no'        =>$form['ref_no'],
			   		'note'			=> $form['note'],
		   			'status'		=> 2,
		   			'author'		=> $this->_author,
		   			'created'		=> $this->_created,
		   			'modified'		=> $this->_modified,
		   	);
		   	$payment = $this->_safedataObj->rteSafe($payment);
		   	$result = $this->getDefinedTable(Purchase\PaymentTable::class)->save($payment);		   	
		   	 
		   if($result>0):
				$this->flashMessenger()->addMessage("success^ New Bill successfully added");
				else:
					$this->flashMessenger()->addMessage("error^ Failed to add new Bill");
			endif;
			return $this->redirect()->toRoute('billing',array('action' => 'viewbilling','id'=>$result));	
		}
		$source_locs	= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		return new ViewModel(array(
				'title'			 => 'Add Bill',
				'regions'     	 => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	 => $this->getDefinedTable(Administration\LocationTable::class),
				'source_locs'		=> $source_locs,
				'partyObj'  	    	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'userID'          => $this->_author,
				'userTable'      => $this->getDefinedTable(Administration\UsersTable::class),
				'shead'      	=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('head'=>[103,141,136])),
				'purreceipt' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getpr(array('r.location'=>$source_locs,'r.status'=>4)),
		));
	}
	/**
	 * Edit Bill Action                                                                                                                                                      
	 */
	public function editbillAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost(); 
			$payment = array(
					'id'			  => $this->_id,				
			   		'billing_date'	=> $form['billing_date'],
			   		'location'		=> $form['location'],
					'prn_no'		=> $form['prn_no'],
			   		'supplier'	    => $form['supplier'],	
		   			'net_amount'    => str_replace( ",", "",$form['net']),	
					'total'    		=> str_replace( ",", "",$form['total']),	
					'tax'    		=> $form['tax'],
					'freight'    		=> $form['freight'],
					'freight_party'	=> $form['freight_party'],	
					'freight_ref'	=> $form['freight_ref'],	
					'tax_party'		=> $form['tax_party'],
					'tax_ref'		=> $form['tax_ref'],
			   		'discount'		=> $form['discount'],
		   			'receipt_amt'	=>str_replace( ",", "",$form['receipt_amt']) ,
					'ref_no'        =>$form['ref_no'],
			   		'note'			=> $form['note'],
		   			'status'		=> 2,
		   			'author'		=> $this->_author,
		   			'created'		=> $this->_created,
		   			'modified'		=> $this->_modified,
			);		
			$payment = $this->_safedataObj->rteSafe($payment);
			$result = $this->getDefinedTable(Purchase\PaymentTable::class)->save($payment);
			if($result > 0):
					$this->flashMessenger()->addMessage("success^ Successfully updated payment ");
					return $this->redirect()->toRoute('billing',array('action'=>'viewbilling','id'=>$this->_id)); 			
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update payment");
				return $this->redirect()->toRoute('billing', array('action' => 'editbill', 'id' => $this->_id));
			endif;
		endif;
		$source_locs	= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		return new ViewModel(array(
				'title'		   		=> 'Edit Bill Details',
				'bills'				=> $this->getDefinedTable(Purchase\PaymentTable::class)->get($this->_id),
				'regions'     	    => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'recipt'     	    => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getAll(),
				'locationObj' 	    => $this->getDefinedTable(Administration\LocationTable::class),
				'source_locs'		=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
				'suppliers'  	    => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>1)),
				'partyObj'  	    	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'payment_types'   	=> $this->getDefinedTable(Purchase\PaymentTypeTable::class)->getAll(),
				'bankaccounts'      => $this->getDefinedTable(Accounts\BankaccountTable::class)->getAll(),
				'shead'      	=> $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('head'=>[103,141,136])),
				'purreceipt' => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getpr(array('r.location'=>$source_locs,'r.status'=>4)),
				
		));
	}
	/**
	 * view individual supplier payment
	 */
	public function viewbillingAction()
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
        $bill = $params['0'];
		return new ViewModel(array(
				'payment'	       => $this->getDefinedTable(Purchase\PaymentTable::class)->get($bill),
				'userTable'        	=> $this->getDefinedTable(Administration\UsersTable::class),
				'partyObj'	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'prObj'	=> $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
                'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'userID'          => $this->_author,
				'userTable'      => $this->getDefinedTable(Administration\UsersTable::class),
				'user_locs'		=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
		));
	}
	/**
	 * Process Bill Action
	 *
	 */
	public function processbillAction()
	{
		$this->init();	
		if($this->getRequest()->isPost()):		
			$form = $this->getRequest()->getPost()->toArray();		
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			/*Get users under destination location with sub role Depoy Manager*/
			$sourceLocation = $this->getDefinedTable(Purchase\PaymentTable::class)->getColumn($form['bill_id'], 'location');
			foreach($this->getDefinedTable(Purchase\PaymentTable::class)->get($form['bill_id']) as $bill);

			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($bill['location'], 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(11,'prefix');
			$date = date('ym',strtotime(date('Y-m-d')));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			
			$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], 13));
			endforeach;
			$next_serial = max($pltp_no_list) + 1;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;    break;
				default: $next_dc_serial = $next_serial;       break;
				}	
		$voucher_no = $tmp_VCNo.$next_dc_serial;
		$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($bill['location'],'region');
			if($form['action'] == "1")  
			    {    /* Send bill */
					$data = array(
						'id'			=> $form['bill_id'],
						'remarks'		=> $form['remarks'],
						'status' 		=> 6,
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
				    );
					if(!empty($bill['transaction'])){
						$data1 = array(
							'id'				=> $bill['transaction'],
							'voucher_date' 		=> $bill['billing_date'],
							'voucher_type' 		=> 11,
							'region'   			=>$region,
							'doc_id'   			=>"billing",
							'against'			=>0,
							'voucher_no' 		=> $voucher_no,
							'voucher_amount' 	=> str_replace( ",", "",$bill['net_amount']),
							'status' 			=> 6, // status initiated 
							'remark'			=> $bill['note'],
							'author' 			=>$this->_author,
							'created' 			=>$this->_created,  
							'modified' 			=>$this->_modified,
						);
					}
					else{
						$data1 = array(
							'voucher_date' 		=> $bill['billing_date'],
							'voucher_type' 		=> 11,
							'region'   			=>$region,
							'doc_id'   			=>"billing",
							'voucher_no' 		=> $voucher_no,
							'against'			=>0,
							'voucher_amount' 	=> str_replace( ",", "",$bill['net_amount']),
							'status' 			=> 6, // status initiated 
							'remark'			=> $bill['note'],
							'author' 			=>$this->_author,
							'created' 			=>$this->_created,  
							'modified' 			=>$this->_modified,
						);	
					}
					$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
					
					if($resultt >0){
						$flow=array(
							'flow' 				=> 2,
							'application' 		=> $resultt,
							'activity'			=>$bill['location'],
							'actor'   			=>3,
							'action' 			=> "2|4",
							'routing' 			=> 2,
							'status' 			=> 6, // status initiated 
							'routing_status'	=>2,
							'action_performed'	=>1,
							'description'		=>"expense",
							'author' 			=>$this->_author,
							'created' 			=>$this->_created,  
							'modified' 			=>$this->_modified,
						);
						$flow=$this->getDefinedTable(Administration\FlowTransactionTable::class)->save($flow);
						$pr=$this->getDefinedTable(Purchase\PaymentTable::class)->getColumn($form['bill_id'],'prn_no');
						$po=$this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->getColumn($pr,'purchase_order');
						foreach($this->getDefinedTable(Purchase\PODetailsTable::class)->get(array('purchase_order'=>$po)) as $pod);
						$tdetailsdata = array(
							'transaction' => $resultt,
							'voucher_dates' => $bill['billing_date'],
							'voucher_types' => 11,
							'location' => $bill['location'],
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($pod['subhead'],'head'),
							'sub_head' =>$pod['subhead'],
							'bank_ref_type' => '',
							'debit' =>$bill['total'],
							'credit' =>'0.00',
							'against' =>'0',
							'reconcile'=>'0',
							'ref_no'=> '', 
							'type' => '1',//user inputted  data  
							'status' => 6, // status appied
							'activity'=>$bill['location'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
						$subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$bill['supplier'],'type'=>2),'id');
						$head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$bill['supplier'],'type'=>2),'head');
						$tdetailsdata = array(
							'transaction' => $resultt,
							'voucher_dates' => $bill['billing_date'],
							'voucher_types' => 11,
							'location' => $bill['location'],
							'head' =>$head,
							'sub_head' =>$subhead,
							'bank_ref_type' => '',
							'debit' =>'0.00',
							'against' =>'0',
							'credit' =>$bill['net_amount'],
							'ref_no'=> $bill['ref_no'],
							'reconcile'=>'0', 
							'type' => '1',//user inputted  data
							'status' => 6, // status applied
							'activity'=>$bill['location'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					}
					//Transaction for freight charges
					if($bill['freight_party']!=-1){
					$tdetailsdata = array(
						'transaction' => $resultt,
						'voucher_dates' => $bill['billing_date'],
						'voucher_types' => 11,
						'location' => $bill['location'],
						'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($bill['freight_party'],'head'),
						'sub_head' => $bill['freight_party'],
						'bank_ref_type' => '',
						'debit' =>'0.00',
						'against' =>'0',
						'reconcile'=>'0',
						'credit' =>$bill['freight'],
						'ref_no'=> $bill['freight_ref'], 
						'type' => '1',//user inputted  data
						'status' => 6, // status applied
						'activity'=>$bill['location'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					}
					if($bill['tax_party']!=-1){
						$tdetailsdata = array(
							'transaction' => $resultt,
							'voucher_dates' => $bill['billing_date'],
							'voucher_types' => 11,
							'location' => $bill['location'],
							'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($bill['tax_party'],'head'),
							'sub_head' => $bill['tax_party'],
							'bank_ref_type' => '',
							'debit' =>'0.00',
							'against' =>'0',
							'reconcile'=>'0',
							'credit' =>$bill['tax'],
							'ref_no'=> $bill['tax_ref'], 
							'type' => '1',//user inputted  data
							'status' => 6, // status applied
							'activity'=>$bill['location'],
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					}
					$data2 = array(
						'id'			=> $form['bill_id'],
						'transaction'	=> $resultt,
					);
					$results = $this->getDefinedTable(Purchase\PaymentTable::class)->save($data2);	

				}
					$message = "Successfully Send Bill";
					$br = "New Bill send"; 
			//print_r($data);exit;	
			$result = $this->getDefinedTable(Purchase\PaymentTable::class)->save($data);	
			$bill_no = $this->getDefinedTable(Purchase\PaymentTable::class)->getColumn($form['bill_id'], 'bill_no');		
		
			if($result):
			    	$notification_data = array(
					    'route'         => 'transaction',
						'action'        => 'viewcredit',
						'key' 		    => $resultt,
						'description'   => $br,
						'author'	    => $this->_author,
						'created'       => $this->_created,
						'modified'      => $this->_modified,   
					);
					//print_r($notification_data);exit;
					$notificationResult = $this->getDefinedTable(Acl\NotificationTable::class)->save($notification_data);
					//echo $notificationResult; exit;
					if($notificationResult > 0 ){	
						if($form['action'] == "1"){
							$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('region'=>$region,'role'=>array('3')));
						}
						else if($form['action'] == "2"){
							$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('5')));
						}
						else if($form['action'] == "3"||$form['action'] == "4"){
							$user = $this->getDefinedTable(Administration\UsersTable::class)->get(array('role'=>array('2')));
						}
						foreach($user as $row):						    
						    $user_location_id = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($row['id'], 'location');
						    if($user_location_id == $sourceLocation ):						
							    $notify_data = array(
								    'notification' => $notificationResult,
									'user'    	   => $row['id'],
									'flag'    	 => '0',
									'desc'    	 => $br,
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
			    $this->flashMessenger()->addMessage("error^ Cannot send good request");			  
			endif;
			return $this->redirect()->toRoute('billing',array('action'=>'viewbilling','id'=>$form['bill_id']));
		endif; 		
		$viewModel =  new ViewModel(array(
			'title' => 'Billing',
			'id'  => $this->_id,
			'userID' => $this->_author,
		));	
		$viewModel->setTerminal('false');
        return $viewModel;	
	}
	
	
	/**
	 * Action for getting Purchase Receipt No
	 */
	public function getprnAction()
	{
		$form = $this->getRequest()->getPost();
		$location =$form['location'];
			$receipt = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get(array('location'=>$location));
			$receiptopt ="<option value=''></option>";
			foreach($receipt as $receipt):
				$receiptopt .="<option value='".$receipt['id']."'>".$receipt['prn_no']."</option>";
			endforeach;
		echo json_encode(array(
				'prn' => $receiptopt,
		));
		exit;
	}
	/**
	 * Action for getting Purchase Receipt No
	 */
	public function getsupplierAction()
	{
		$form = $this->getRequest()->getPost();
		$prn_no = $form['prn_no'];

		$pr = $this->getDefinedTable(Purchase\PurchaseReceiptTable::class)->get($prn_no);
		$selectedsup = $pr[0]['supplier'];
		$sum=$this->getDefinedTable(Purchase\PRDetailsTable::class)->getSum('rate','accept_qty',array('purchase_receipt'=>$prn_no));
		$sup = $this->getDefinedTable(Accounts\PartyTable::class)->get($selectedsup);
		$supplier = "<option value=''></option>";
		foreach ($sup as $sup) {
			$isSelected = ($sup['id'] == $selectedsup) ? ' selected' : '';
			$supplier .= "<option value='" . $sup['id'] . "'" . $isSelected . ">" . $sup['name'] . "</option>";
		}
			echo json_encode(array(
					'supplier' => $supplier,
					'sum'=>$sum,
			));
			exit;
	}
	/**
	 * Action to delete  Bill
	 */
	public function deletebillAction()
	{
		$this->init();	
		$bill_no = $this->_id;
		
		$tranid = $this->getDefinedTable(Accounts\TransactionTable::class)->getColumn(array('remark'=>$bill_no,'doc_id'=>"billing"),'id');
		foreach($this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction'=>$tranid)) as $td):
			$reult1=$this->getDefinedTable(Accounts\TransactiondetailTable::class)->remove($td['id']);
		endforeach;
		$result2= $this->getDefinedTable(Accounts\TransactionTable::class)->remove($tranid);
		$result3=$this->getDefinedTable(Purchase\PaymentTable::class)->remove($bill_no);
		if($result3 > 0):

			$this->flashMessenger()->addMessage("success^ Bill deleted successfully");
		else:
			$this->_connection->rollback(); // rollback transaction over failure
			$this->flashMessenger()->addMessage("error^ Failed to delete Bill");
		endif;
		//end			
	
		return $this->redirect()->toRoute('billing',array('action' => 'index'));	
	}
}

