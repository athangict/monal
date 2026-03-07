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

class PaymentController extends AbstractActionController
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
    protected $_dbAdapter;
    
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
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
	
	/**
	 * index Action
	 */
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
		$payments = $this->getDefinedTable(Store\PaymentTable::class)->getDateWise('payment_date',$year,$month);
		return new ViewModel(array(
			'title'     => 'Payment',
			'payments'   => $payments,
			'minYear' => $this->getDefinedTable(Store\PaymentTable::class)->getMin('payment_date'),
			'data'    => $data,
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'groupObj'      => $this->getDefinedTable(Store\GroupTable::class),
		));
	}
	
	/**
	 * addpayment Action
	 */
	public function addpaymentAction()
	{
		$this->init();		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$preceipt_id = $form['preceipt'];
			$acc_type = ($form['bank'] == 3)?$form['bank']:$form['cash'];
			if($preceipt_id == "" || $preceipt_id == 0 ){
				$location = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($form['location'],'prefix');
				$date = date('ym',strtotime($form['payment_date']));
				$tmp_PONo = $location."PY".$date;
				$results = $this->getDefinedTable(Store\PaymentTable::class)->getMonthlyPayment($date);
				$sheet_no_list = array();
				foreach($results as $result):
						array_push($sheet_no_list, substr($result['payment_no'], 8));
				endforeach;
				$next_serial = max($sheet_no_list) + 1;
			  
				switch(strlen($next_serial)){
					case 1: $next_py_serial = "000".$next_serial; break;
					case 2: $next_py_serial = "00".$next_serial;  break;
					case 3: $next_py_serial = "0".$next_serial;   break;
					default: $next_py_serial = $next_serial;      break;
			    }		  	
		   	    $payment_no = $tmp_PONo.$next_py_serial;
			    $bank_charge = ($form['check_bank_charge'] == 1)?$form['bank_charge']:'';
		   	    $payment = array(
					'payment_no'	    => $payment_no,
					'purchase_order'	=> $form['purchase_order_no'],
					'purchase_receipt'	=> $form['purchase_receipt_no'],
					'payment_date'	    => $form['purchase_payment_date'],
					'location'		    => $form['location'],			   		
					'supplier'		    => $form['supplier'],	
					'cost_center'	    => $form['cost_center'],	
					'item_group'        => $form['item_group'],			   		
					'acc_type'		    => $acc_type,
					'account'		    => $form['bcaccount'],
					'bank_ref_type'		=> $form['acc_ref_type'],
					'cheque_no'		    => $form['cheque_no'],
					'payment_amount'    => $form['payment_amount'],
					'bank_charge'	    => $bank_charge,
					'transaction'	    => '',
					'note'			    => $form['note'],
					'status'		    => 1,
					'author'		    => $this->_author,
					'created'		    => $this->_created,
					'modified'		    => $this->_modified,
				);
		   	    $payment = $this->_safedataObj->rteSafe($payment);
		   	    $this->_dbAdapter->beginTransaction();
		     	$result = $this->getDefinedTable(Store\PaymentTable::class)->save($payment);		   	
				if($result > 0):
					$invoice_no = $form['invoice_no'];
                                                         
					$payable_amount	= $form['payable_amount'];
					//for($i=0; $i < sizeof($invoice_no); $i++):
						//if(isset($invoice_no[$i]) && $invoice_no[$i] > 0):
						$payment_details = array(
							'payment'       => $result,
							'invoice_no'	=> $invoice_no,
							'payment_amt'   => $payable_amount,
							'author'		=> $this->_author,
							'created'		=> $this->_created,
							'modified'		=> $this->_modified,
						);		  			
						$payment_details = $this->_safedataObj->rteSafe($payment_details);		   		
						$result2 = $this->getDefinedTable(Store\PaymentDtlTable::class)->save($payment_details);
						if($result2 > 0) {$commit = 1; }else{ $rollback = 1; }
						//endif;
					//endfor;	
					if($rollback == 1):
						$this->_dbAdapter->rollback();
						$this->flashMessenger()->addMessage("Failed^ Failed to add new payment:Rollback System");
						return $this->redirect()->toRoute('inpurpayment', array('action' => 'addpayment'));		   	
					elseif($commit == 1):
						$this->_dbAdapter->commit();
						$this->flashMessenger()->addMessage("success^ Successfully added new payment ".$payment_no);
						return $this->redirect()->toRoute('inpurpayment',array('action'=>'viewpayment','id'=>$result));
					endif; 	   		
				else:   
					$this->_dbAdapter->rollback();
					$this->flashMessenger()->addMessage("Failed^ Failed to add new payment:Rollback System");
					return $this->redirect()->toRoute('inpurpayment', array('action' => 'addpayment'));
				endif;
				}
		    else{
			$pur_receipt = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get(array('pr.id'=>$form['preceipt'],'status'=>3));
			foreach($pur_receipt as $row);
			$pur_order = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get(array('po.id'=>$row['purchase_order'],'status'=>array(2,3)));
			}
		endif;
		return new ViewModel(array(
				'title'			=> 'Add Supplier Payment',
				'pur_receipt'   => $pur_receipt, 
				'pur_order'     => $pur_order, 
				'regions'     	=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
				'activities'  	=> $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'uomObj'	    => $this->getDefinedTable(Stock\UomTable::class),
				'pur_receiptObj'=> $this->getDefinedTable(Store\PurchaseReceiptTable::class),
				'pur_ordertObj'=> $this->getDefinedTable(Store\PurchaseOrderTable::class),
				'activities'    => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'groupObj'      => $this->getDefinedTable(Store\GroupTable::class),
		));
	}
	/**
	 * Action for getting Accounts
	 */
	public function getaccountAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$acc_type = $form['bank_type'];
		if($acc_type ==3):
			$accountObj = $this->getDefinedTable(Accounts\BankaccountTable::class);
			$accounts = $accountObj->get(array('acc_type'=>$acc_type));
			$account .="<option value=''></option>";
			foreach($accounts as $acc):
				$account .="<option value='".$acc['id']."'>".$acc['code']."</option>";
			endforeach;
			$bankrfObj = $this->getDefinedTable(Accounts\BankreftypeTable::class);
			$bankrfs = $bankrfObj->getAll();
			$bankr .="<option value=''></option>";
			foreach($bankrfs as $bankrf):
			    $bankr .="<option value='".$bankrf['id']."'>".$bankrf['bank_ref_type']."</option>";
			endforeach;
		else:
			$accountObj = $this->getDefinedTable(Accounts\CashaccountTable::class);
			$accounts = $accountObj->get(array('acc_type'=>$acc_type));
			$account .="<option value=''></option>";
			foreach($accounts as $acc):
				$account .="<option value='".$acc['id']."'>".$acc['cash_account']."</option>";
			endforeach;
		endif;
		echo json_encode(array(
				'acc' => $account,
				'br' => $bankr,
		));
		exit;
	}
	/**
	 * Get Purchase Receipt for Payment
	 **/
	public function getpreceiptAction()
	{
		$this->init();
		$ViewModel = new ViewModel(array(
			'purchasereceipts' => $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get(array('status' =>3,'payment_status'=>0)),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * view payment
	 */
	public function viewpaymentAction()
	{
		$this->init();
		
		return new ViewModel(array(
				'payment'	       => $this->getDefinedTable(Store\PaymentTable::class)->get($this->_id),
				'userTable'        	=> $this->getDefinedTable(Acl\UsersTable::class),
				'paymentdetailsObj'	=> $this->getDefinedTable(Store\PaymentDtlTable::class),
				'purchasereceiptObj'	=> $this->getDefinedTable(Store\PurchaseReceiptTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
                'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
				'bankaccountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
				'groupObj' => $this->getDefinedTable(Store\GroupTable::class),

		));
	}
	/**
	 * Edit supplier payment Action
	 */
	public function editpaymentAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$acc_type = ($form['bank'] == 3)?$form['bank']:$form['cash'];
			$bank_charge = ($form['check_bank_charge'] == 1)?$form['bank_charge']:'';
			$payment = array(
					'id'			   =>  $form['payment_id'],				
					'location'		    => $form['location'],			   		
					'supplier'		    => $form['supplier'],	
					'cost_center'	    => $form['cost_center'],	
					'item_group'        => $form['item_group'],			   		
					'acc_type'		    => $acc_type,
					'account'		    => $form['bcaccount'],
					'bank_ref_type'		=> $form['acc_ref_type'],
					'cheque_no'		    => $form['cheque_no'],
					'payment_amount'    => $form['payment_amount'],
					'bank_charge'	    => $bank_charge,
					'transaction'	    => '',
					'note'			    => $form['note'],
					'status'		    => 1,
					'author'		    => $this->_author,
					'modified'		   => $this->_modified,
			);		
			$payment = $this->_safedataObj->rteSafe($payment);
			$this->_dbAdapter->beginTransaction();
			$result = $this->getDefinedTable(Store\PaymentTable::class)->save($payment);
			if($result > 0):
				$details_id = $form['details_id'];
				$invoice = $form['invoice_no'];
				$payable_amount	= $form['payable_amount'];
				 
				//for($i=0; $i < sizeof($invoice); $i++):
					//if(isset($invoice[$i]) && $invoice[$i] > 0):
						$payment_details = array(
								'id'               	=> $details_id[$i],
								'payment'		    => $result,
								'invoice_no'		=> $invoice,
								'payment_amt'	    => $payable_amount,
								'author'  			=> $this->_author,
								'modified'			=> $this->_modified,
						);
						$payment_details = $this->_safedataObj->rteSafe($payment_details);
						$result2 = $this->getDefinedTable(Store\PaymentDtlTable::class)->save($payment_details);
						if($result2  > 0 ){ $commit = 1; }else{ $rollback = 1; };
					//endif;					
				//endfor;			
				if($rollback == 1):
					$this->_dbAdapter->rollback();
					$this->flashMessenger()->addMessage("Failed^ Failed to update payment");
					return $this->redirect()->toRoute('inpurpayment', array('action' => 'editpayment', 'id' => $form['payment_id']));				
				elseif($commit == 1):
					$this->_dbAdapter->commit();
					$payment_no = $this->getDefinedTable(Store\PaymentTable::class)->getColumn($form['payment_id'],'payment_no');
					
					$this->flashMessenger()->addMessage("success^ Successfully updated payment ". $payment_no);
					return $this->redirect()->toRoute('inpurpayment',array('action'=>'viewpayment','id'=>$result));
			   endif; 			
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update payment");
				return $this->redirect()->toRoute('inpurpayment', array('action' => 'editpayment', 'id' => $form['payment_id']));
			endif;
		endif;
		$payments = $this->getDefinedTable(Store\PaymentTable::class)->get($this->_id);
		foreach ($payments as $paymentdtl):
		     extract($paymentdtl); 
		endforeach;	
			
		return new ViewModel(array(
				'title'		   		=> 'Edit Payment Details',
				'payments'			=> $payments,
				'regions'     	    => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' 	    => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj'  	    => $this->getDefinedTable(Administration\ActivityTable::class),
				'bankaccountObj'    => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashaccountObj'    => $this->getDefinedTable(Accounts\CashaccountTable::class),
				'paymentdetailsObj'	=> $this->getDefinedTable(Store\PaymentDtlTable::class),
			    'subheadObj'        => $this->getDefinedTable(Accounts\SubheadTable::class),
				'headObj'           => $this->getDefinedTable(Accounts\HeadTable::class),
				'groupObj'          => $this->getDefinedTable(Store\GroupTable::class),
				'bankreftypeObj'    => $this->getDefinedTable(Accounts\BankreftypeTable::class),
				
		));
	}
	
	/**
	 * booking of supplier payment
	 */
	public function bookpaymentAction()
	{
        $this->init();
		$payment_id = $this->_id;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			// booking to transaction
			if(isset($form['voucher_date']) && isset($form['voucher_amount'])):
				//generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
				$date = date('ym',strtotime($form['voucher_date']));
				$tmp_VcNo = $loc.$prefix.$date;	
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VcNo);
			
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
				$voucher_no = $tmp_VcNo.$next_dc_serial;
				$data1 = array(
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'doc_id' => $form['doc_id'],
						'doc_type' => $form['doc_type'],
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
						'bank_ref_type'  => $form['bank_ref_type'],
					    'cheque_no'  =>  $form['cheque'],
						'remark' => $form['remark'],
						'status' => 3,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				//echo "<pre>";print_r($data1); exit;
				$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			
				if($result > 0):
					//insert into transactiondetail table
					$location= $form['location'];
					$activity= $form['activity'];
					$head = $form['head'];
					$sub_head= $form['sub_head'];
					$debit= $form['debit'];
					$credit= $form['credit'];
					for($i=0; $i < sizeof($activity); $i++):
						if(isset($activity[$i]) && is_numeric($activity[$i])):
						$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $activity[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
								'ref_no'=> '',
								'author' =>$this->_author,
								'created' =>$this->_created,
								'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						//echo "<pre>"; print_r($tdetailsdata);
						$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
						endif;
					endfor;
					//change the status committed and Update the transaction id in payment and 
					$update_data = array(
						'id' => $this->_id,
						'transaction' => $result,
						'status' 		=> 3,
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
					);
					$result1 = $this->getDefinedTable(Store\PaymentTable::class)->save($update_data);
					$pur_receipts = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get(array('pr.id'=>$form['purchase_receipt'],'status'=>3));
					foreach($pur_receipts as $pur_receipt);
					$pur_receipt_update = array(
						'id'            =>$pur_receipt['id'],
						'payment_status' => 1,
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
					);
					$result1 = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->save($pur_receipt_update);
					
					$pur_orders = $this->getDefinedTable(Store\PurchaseOrderTable::class)->get(array('po.id'=>$form['purchase_order'],'status'=>3));
					foreach($pur_orders as $pur_order);
					$pur_order_update = array(
						'id'            => $pur_order['id'],
						'paid_amount'   => $form['voucher_amount'],
						'author'	    => $this->_author,
						'modified'      => $this->_modified,
					);
					$result1 = $this->getDefinedTable("Store\PurchaseOrderTable")->save($pur_order_update);
					
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('inpurpayment', array('action' =>'viewpayment', 'id' => $this->_id));
				else:
					$this->flashMessenger()->addMessage("Failed^ Failed to book supplier payment to transaction");
					$this->redirect()->toRoute('inpurpayment', array('action'=>'viewpayment', 'id'=>$this->_id));
				endif;
			else:
				$this->flashMessenger()->addMessage("Failed^ Not set voucher date and voucher amount");
			endif;
		endif;//end of isPost
		$pur_receipts = $this->getDefinedTable(Store\PurchaseReceiptTable::class)->get(array('status'=>10));
		return new ViewModel(array(
				'title'	=> 'Book Payment',
				'payments' => $this->getDefinedTable(Store\PaymentTable::class)->get($this->_id),
			    'paymentdtlObj'	=> $this->getDefinedTable(Store\PaymentDtlTable::class),
				'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
				'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
				'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
				'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
				'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
				'trans_inv_dtlObj' => $this->getDefinedTable(Stock\TranspInvDetailsTable::class),
				'bankaccountObj'  => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashaccountObj'  => $this->getDefinedTable(Accounts\CashaccountTable::class),
				'itemsubgroupObj' => $this->getDefinedTable(Store\SubGroupTable::class),
				'pur_receipts'    => $pur_receipts,
			    'pur_receiptdtlObj'	=> $this->getDefinedTable(Store\PRDetailsTable::class),
			    'itemObj'	=> $this->getDefinedTable(Store\ItemTable::class),
		));
	}
}

