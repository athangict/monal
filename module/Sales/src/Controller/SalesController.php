<?php
namespace Sales\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Sales\Model As Sales;
use Stock\Model As Stock;
class SalesController extends AbstractActionController
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
    protected $_safedataObj; // safedata controller plugin
	
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
		if(!isset($this->_user)) {
			$this->_user = $this->identity();
		}
		if(!isset($this->_login_id)){
			$this->_login_id = $this->_user->id;  
		}
		if(!isset($this->_login_role)){
			$this->_login_role = $this->_user->role;  
		}

		if(!isset($this->_author)){
			$this->_author = $this->_user->id;  
		}
                
		if(!isset($this->_userloc)){
			$this->_userloc = $this->_user->location;  
		}
		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}

	/*
	* sales booking action
	*/
	public function slactivityAction()
	{
		$this->init();
		//if param are passed from POST
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$activity = $form['activity'];
			$location = $form['location'];
		    $sales_date = $form['sales_date'];
		    $credit  = $form['credit'];
		}else{
			$activity = 1;
			$location = 1;
			$sales_date = date('Y-m-d');
			$credit = 0;
		}
		return new ViewModel(array(
			'title'   	  => 'Sales With Activity',
			'activity_id' => $activity,
			'location_id' => $location,
			'sales_date_id'=> $sales_date,
			'credit_id'   => $credit,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'itemObj' 	  => $this->getDefinedTable(Stock\ItemTable::class),
			'batchObj'    => $this->getDefinedTable(Stock\BatchTable::class),
			'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
			'saledetails' => $this->getDefinedTable(Sales\SalesDetailsTable::class)->getByAct(array('s.location' => $location, 'i.activity'=> $activity, 's.sales_date'=>$sales_date, 's.credit'=>$credit)),
		));
	}
	/**
	 * Get Transportation Charge 
	 * for Transporter Invoice
	 */
	public function getsaledetailsAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getpost();
	
		$sales_date = $form['sales_date'];
		$location  = $form['location'];
		$activity  = $form['activity'];
		$credit    = $form['credit'];
	
		$sales_data = array(
				'sales_date' => $sales_date,
				'location'  => $location,
				'activity'  => $activity,
				'credit'	=> $credit,
		);
		//echo "<pre>";print_r($sales_data); exit;
		$ViewModel = new ViewModel(array(
				'sales_data' => $sales_data,
				'saledetails' => $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('s.location' => $location, 'i.activity'=> $activity, 's.sales_date'=>$sales_date, 's.credit'=>$credit)),
				'batchObj'    => $this->getDefinedTable(Stock\BatchTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/*  
	 * sales booking 
	 * to Accounts
	 * */
	public function slbookingAction()
	{
	    $this->init();
		$location='';
		$eos_date='';
		$department_id='';
		$task='';
	    $location_id = $this->_user->location;
	    
	    if($this->getRequest()->isPost())
	    {
	    	$form = $this->getRequest()->getPost();
	    	$location = $form['location'];
	    	$eos_date = $form['eos_date'];
	        $department_id = $form['department'];
	    	$task = $form['task'];  
	    	
	    	if($task == '2'):	    	 
    	    	$voucher_amount = $form['voucher_amount'];
    	    	$activity = $form['activity'];
    	    	$debit = $form['debit'];
    	    	$credit = $form['credit'];
    	    	$sub_head = $form['subhead'];
    	    	$head = $form['head'];
    	    	
    	    	$allsalesID = $form['sales_id'];    	    	
    	    	
    	    	$voucher_amount1 = $form['voucher_amount1'];
    	    	$activity1 = $form['activity1'];
    	    	$debit1 = $form['debit1'];
    	    	$credit1 = $form['credit1'];
    	    	$sub_head1 = $form['subhead1'];
    	    	$head1 = $form['head1'];
    	    	
    	    	$allsalesID1 = $form['sales_id1'];	 
    	    	
    	    	$voucherType = '6';    	    	
    	    	//for Cash Sales
    	    	if($voucher_amount > 0 && sizeof($activity)>0){
    	    	    
    	    	    foreach($activity as $act);
    	    	    if($act['activity'] == '1' || $act['activity'] == '2'):
					$notes = "Sales of Agency and Food Grains";
					else:
					$notes = "RNR Sales";
					endif;
					
         	    	//generate voucher no    	    	    
        	    	$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
        	    	$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');
        	    	
        	    	$date = date('ym',strtotime($eos_date)); 
        	    	 $tmp_VCNo = $loc.$prefix.$date;       	    		
        	    	//$serial = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($date) + 1;
        	    	//$voucher_no = $loc.$prefix.$date.$serial;
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
        	    	
        	    	$data1 = array(
        	    			'voucher_date' => $eos_date,
        	    			'voucher_type' => $voucherType,
        	    			'doc_id' => '',
        	    			'doc_type' => '',
        	    			'voucher_no' => $voucher_no,
        	    			'voucher_amount' => str_replace( ",","",$form['voucher_amount']),
        	    			'remark' =>$notes,
        	    			'status' => 3, // status initiated
        	    			'author' =>$this->_author,
        	    			'created' =>$this->_created,
        	    			'modified' =>$this->_modified,
        	    	);
        	    	
        	    	//print_r($data1);
        	    	$data1 = $this->_safedataObj->rteSafe($data1);
        	    	$this->_connection->beginTransaction(); //***Transaction begins here***//
        	    	$resultc = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
        	    	
        	    	if($resultc > 0){
        	    	    for($i=0; $i < sizeof($activity); $i++):
            	    	    if(isset($activity[$i]) && is_numeric($activity[$i])):
                	    	    $tdetailsdata = array(
                	    	    		'transaction' => $resultc,
                	    	    		'location' => $location,
                	    	    		'activity' => $activity[$i],
                	    	    		'head' => $head[$i],
                	    	    		'sub_head' => $sub_head[$i],
                	    	    		'bank_ref_type' => '',
                	    	    		'cheque_no' => '',
                	    	    		'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
                	    	    		'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
                	    	    		'ref_no'=> '',
                	    	    		'type' => '2', //System Generated
                	    	    		'author' =>$this->_author,
                	    	    		'created' =>$this->_created,
                	    	    		'modified' =>$this->_modified,
                	    	    );
                	    	    //print_r($tdetailsdata );
                	    	    $tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
                	    	    $result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
                	    	    if($result1 <= 0):
                	    	      break;
                	    	    endif;
            	    	    endif;
        	    	    endfor;
        	    	
            	    	if($result1 > 0):
            	    	    // Booking for credit Sales
            	    	    //update the sales table with transaction ID;        	    	    
            	    	    $this->getDefinedTable(Sales\SalesTable::class)->updateRecords($allsalesID, $resultc); 
            	    	    $this->_connection->commit();
            	    	else:
                	    	$this->_connection->rollback(); // rollback transaction over failure
                	    	$this->flashMessenger()->addMessage("Failed^ Failed to book sales. Please Try Again");
                	    	return $this->redirect()->toRoute('sales');
            	    	endif;
        	    	}
        	    	else
        	    	{
        	    		$this->_connection->rollback(); // rollback transaction over failure
        	    		$this->flashMessenger()->addMessage("Failed^ Failed to book sales. Please Try Again");
        	    		return $this->redirect()->toRoute('sales');
        	    	} 
    	    	} // end of cash sales
        	    	
        	    if($voucher_amount1 > 0 && sizeof($activity1)>0){	// start credit sales    	    
            	    	//$serial = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($date) + 1;
            	    	//$voucher_no1 = $loc.$prefix.$date.$serial;
                        $loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');
		        $date = date('ym',strtotime($eos_date));
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
		        $voucher_no1 = $tmp_VCNo.$next_dc_serial;
            	    	
            	    	$data2 = array(
            	    			'voucher_date' => $eos_date,
            	    			'voucher_type' => $voucherType,
            	    			'doc_id' => '',
            	    			'doc_type' => '',
            	    			'voucher_no' => $voucher_no1,
            	    			'voucher_amount' => str_replace( ",","",$form['voucher_amount1']),
            	    			'remark' => '',
            	    			'status' => 3, // status initiated
            	    			'author' =>$this->_author,
            	    			'created' =>$this->_created,
            	    			'modified' =>$this->_modified,
            	    	);
            	    	//print_r($data2);
            	    	$data2 = $this->_safedataObj->rteSafe($data2);
            	    	$this->_connection->beginTransaction(); //***Transaction begins here***//
            	    	$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data2);
            	    	
            	    if($result2 > 0){
        	    		for($i=0; $i < sizeof($activity1); $i++):
            	    		if(isset($activity1[$i]) && is_numeric($activity1[$i])):
                	    		$tdetailsdata2 = array(
                	    				'transaction' => $result2,
                	    				'location' => $location,
                	    				'activity' => $activity1[$i],
                	    				'head' => $head1[$i],
                	    				'sub_head' => $sub_head1[$i],
                	    				'bank_ref_type' => '',
                	    				'cheque_no' => '',
                	    				'debit' => (isset($debit1[$i]))? $debit1[$i]:'0.000',
                	    				'credit' => (isset($credit1[$i]))? $credit1[$i]:'0.000',
                	    				'ref_no'=> '',
                	    				'type' => '2',//System Generated
                	    				'author' =>$this->_author,
                	    				'created' =>$this->_created,
                	    				'modified' =>$this->_modified,
                	    		);
                	    		//print_r($tdetailsdata2); 
                	    		$tdetailsdata2 = $this->_safedataObj->rteSafe($tdetailsdata2);
                	    		$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata2);
                	    		if($result3 <= 0):
                	    		   break;
                	    		endif;
            	    		endif;
        	    		endfor;
        	    		if($result3 > 0):  
        	    		    $this->getDefinedTable(Sales\SalesTable::class)->updateRecords($allsalesID1, $result2);
            	    		$this->_connection->commit(); // commit transaction on success
            	    		$this->flashMessenger()->addMessage("success^ Sales has been Booked with | ".$voucher_no." and ".$voucher_no1);
            	    		
            	    	else:
            	    		$this->_connection->rollback(); // rollback transaction over failure
            	    		$this->flashMessenger()->addMessage("Failed^ Failed to book sales. Please Try Again");
            	    	endif;            	    	
        	    	}
        	    	else
        	    	{      
        	    			$this->_connection->rollback(); // rollback transaction over failure
        	    			$this->flashMessenger()->addMessage("Failed^ Failed to book sales. Please Try Again");
        	    	} 
        	   }   
        	   //update in sl_booking
		    $location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location,'prefix');
		    $date = date('ym',strtotime($eos_date));
			$tmp_EOSNo = $location_prefix."EOS".$date;
			$results = $this->getDefinedTable(Sales\BookingTable::class)->getMonthlyEOS($tmp_EOSNo);
				
			$dc_no_list = array();
			foreach($results as $result):
				array_push($dc_no_list, substr($result['eos_voucher'], 9));
			endforeach;
			$next_serial = max($dc_no_list) + 1;
					
			switch(strlen($next_serial)){
			        case 1: $next_dc_serial = "000".$next_serial; break;
				case 2: $next_dc_serial = "00".$next_serial;  break;
				case 3: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;       break;
			}	
			$eos_voucher = $tmp_EOSNo.$next_dc_serial;
				
			$bookdata = array(
					'eos_date' => $eos_date,
					'eos_voucher' => $eos_voucher,
					'cash_voucher_id' => $resultc,
					'credit_voucher_id' => $result2,
					'location' => $location,
					'author' => $this->_author,
					'created' => $this->_created,
			);
			$this->_safedataObj->rteSafe($bookdata);
			$this->getDefinedTable(Sales\BookingTable::class)->save($bookdata);
				
        	   return $this->redirect()->toRoute('sales', array('action' =>'eosrecord'));
    	    endif; 		//end of task	
         }	    //end of post
         $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
        $admin_loc_array = explode(',',$admin_locs);
	    $viewModel =  new ViewModel(array(
	    	'title'       => 'End of Session and Sales Booking',
	        'locations'   => $this->getDefinedTable(Administration\LocationTable::class)->get($location_id),
	        //'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
	        'departments'  => $this->getDefinedTable(Administration\DepartmentTable::class)->get(array('id'=>array(1,2))),
	        'salesDtlObj' => $this->getDefinedTable(Sales\SalesDetailsTable::class),
	        'admin_locs'  => $this->getDefinedTable(Administration\LocationTable::class)->get(array('id'=>$admin_loc_array)),
	        'location'    => $location,
	        'user_location'    => $location_id,
	        'eos_date'    => $eos_date,
	        'cashAccObj'  => $this->getDefinedTable(Accounts\CashaccountTable::class),
	        'incomeHeadObj'  => $this->getDefinedTable(Accounts\IncomeheadTable::class),
	        'subHeadObj'   => $this->getDefinedTable(Accounts\SubheadTable::class),
	        'headObj'      => $this->getDefinedTable(Accounts\HeadTable::class),
	        'schemeObj'    => $this->getDefinedTable(Sales\SchemeTable::class),
	        'schemeDtlObj' => $this->getDefinedTable(Sales\SchemeDetailsTable::class),
	        'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
	        'itemObj'      => $this->getDefinedTable(Stock\ItemTable::class),
	        'movingItemObj'=> $this->getDefinedTable(Stock\MovingItemTable::class),
	        'batchDtlsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
	        'batchObj'     => $this->getDefinedTable(Stock\BatchTable::class),
	        'uomItemObj'   => $this->getDefinedTable(Stock\ItemUomTable::class),
	        'department_id'    => $department_id,
	        'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->get(array('department'=>$department_id)),
	        'fslocationObj' => $this->getDefinedTable(Administration\LocationTable::class),
	    ));	 
	    
	    if($task == '1'):
     	    $viewModel->setTemplate('sales/sales/slbookingview.phtml');
	    else:
	        $viewModel->setTemplate('sales/sales/slbooking.phtml');
	    endif;
	    return $viewModel;
	}
    /**
	 * view sales voucher booked
	 * 
	 **/
	public function eosrecordAction(){
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
		$subRoles = $this->getDefinedTable(Administration\UsersTable::class)->get(array('id'=>$this->_author, 'role'=>array('1','3','15')));  
		if(sizeof($subRoles) > 0){ $role_flag = true; } else{ $role_flag = false; }
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		$admin_loc_array = explode(',',$admin_locs);
		$slbooking = $this->getDefinedTable(sales\BookingTable::class)->getDateWise('eos_date',$year,$month,$this->_userloc, $admin_loc_array, $role_flag);
		return new ViewModel(array(
			'title' => 'EOS Record',
			'slbooking'		=> $slbooking,
			'minYear' => $this->getDefinedTable(sales\BookingTable::class)->getMin('eos_date'),
			'data' => $data,
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
			'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	/**
	 *
	 * details of end of session from sales tables
	**/
	public function viewendofsessionAction()
	{
		$this->init();
		return new ViewModel(array(
			'title'       => 'EOS Details',
			'slbooking'   => $this->getDefinedTable(sales\BookingTable::class)->get($this->_id),
	        'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
	        'salesDtlObj' => $this->getDefinedTable(Sales\SalesDetailsTable::class),
	        'cashAccObj'  => $this->getDefinedTable(Accounts\CashaccountTable::class),
	        'incomeHeadObj'  => $this->getDefinedTable(Accounts\IncomeheadTable::class),
	        'subHeadObj'   => $this->getDefinedTable(Accounts\SubheadTable::class),
	        'headObj'      => $this->getDefinedTable(Accounts\HeadTable::class),
	        'schemeObj'    => $this->getDefinedTable(Sales\SchemeTable::class),
	        'schemeDtlObj' => $this->getDefinedTable(Sales\SchemeDetailsTable::class),
	        'itemObj'      => $this->getDefinedTable(Stock\ItemTable::class),
	        'movingItemObj'=> $this->getDefinedTable(Stock\MovingItemTable::class),
	        'batchDtlsObj' => $this->getDefinedTable(Stock\BatchDetailsTable::class),
	        'batchObj'     => $this->getDefinedTable(Stock\BatchTable::class),
	        'uomItemObj'   => $this->getDefinedTable(Stock\ItemUomTable::class),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
			'userObj'          => $this->getDefinedTable(Administration\UsersTable::class),
		));
	}
	
    public function receiptAction()
     {
       $this->init();
	   $year = '';
	   $month = '';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			if(strlen($month)==1){
				$month = '0'.$month;
			}
			$location = $form['location'];
			$payment = $form['payment'];
		}else{
			$month = ($month == '')?date('m'):$month;
			$year = ($year == '')? date('Y'):$year;
			
			$location = $this->_userloc;
			$payment = '-1';
		}
		$month = ($month == '')?date('m'):$month;
		$year = ($year == '')? date('Y'):$year;
		
		$minYear = $this->getDefinedTable(Sales\ReceiptTable::class)->getMin('receipt_date');
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		$data = array(
				'year' => $year,
				'month' => $month,
				'minYear' => $minYear,
				'location' => $location,
		);
		$results = $this->getDefinedTable(Sales\ReceiptTable::class)->getLocDateWisePA('receipt_date',$year,$month,$location,array('status'=>array(1,2,3)));
		
		return new ViewModel(array(
				'title' 	  => 'Credit Receipt',
				'data'        => $data,
				'results' => $results,
				'customerObj' => $this->getDefinedTable(Accounts\PartyTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		));                
     }
	 
	  public function addreceiptAction()
     {
       	$this->init();
       	$location_id = $this->_user->location;       	
       	if($this->getRequest()->isPost())
       	{
       		$form = $this->getRequest()->getPost();	
			//echo"<pre>"; print_r($form); 			 			
            //echo "<pre>"; print_r($check_payment); 			
            		
       		if(sizeof($form['payment_check']) > 0 && $form['total_payable'] > 0 ){
           		//insert the receipt details and receipt_no generation				
				$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
           		$date = date('ym',strtotime($form['received_date']));
           		$tmp_No = $location_prefix."RE".$date;
           		$results = $this->getDefinedTable(Sales\ReceiptTable::class)->getMonthlyReceipt($tmp_No);           		
           		if(sizeof($results) > 0 ):
           		  $receipt_no_list = array();
           		  foreach($results as $result):
           		     array_push($receipt_no_list, substr($result['receipt_no'], 8));
           		  endforeach;
           		  $next_serial = max($receipt_no_list) + 1;
           		else:
           		  $next_serial = "0001";
           		endif;           		 
           		switch(strlen($next_serial)){
           			case 1: $next_re_serial = "000".$next_serial; break;
           			case 2: $next_re_serial = "00".$next_serial;  break;
           			case 3: $next_re_serial = "0".$next_serial;   break;
           			default: $next_re_serial = $next_serial;      break;
           		}           		
           		$receipt_no = $tmp_No.$next_re_serial; 				
				$receipt_data = array(
           				'receipt_no'    =>$receipt_no,
            				'receipt_date'  => $form['received_date'],
           				'customer'      => $form['customer'],
           				'location'      => $form['location'],
           				'sub_head'      => $form['sub_head'],
           				'bank_ref_type' => $form['bankreftype'],
           				'bank_ref_no'   => $form['cheque_no'],           			
           				'amount'        =>$form['total_payable'],
           		        'penalty'       => $form['total_penalty'],
           		        'total_tds'     => $form['total_tds'],
           				'note'          => $form['note'],
           				'transaction'   => '',
           				'status'        => '1',
           				'author'        =>$this->_author,
           				'created'       =>$this->_created,
           				'modified'      =>$this->_modified,
           		);
           		$receipt_data = $this->_safedataObj->rteSafe($receipt_data);			
            	$this->_connection->beginTransaction(); //***Transaction begins here***//
           	    $receiptResult = $this->getDefinedTable(Sales\ReceiptTable::class)->save($receipt_data); 	 
				if($receiptResult > 0){ 
                    $payment_check = $form['payment_check'];			
                    foreach($payment_check as $row):
					    $sales      = $form['sales_'.$row];	
                        $invoice    = $form['invoice_'.$row];					
                        $penalty    = $form['penalty_'.$row];					
                        $tds        = $form['tds_'.$row];					
                        $payable    = $form['payable_'.$row];
                        $due_amount = $form['due_amount_'.$row];
                        $penalty    = (!isset($penalty) || $penalty == '')?'0':$penalty;
                        $tds        = (!isset($tds) || $tds == '')?'0':$tds;
					    $receiptDtl_data = array(
									'receipt'       	=> $receiptResult,
									'sales'         	=> $sales,  
									'credit_amount' 	=> $invoice,
									'penalty'       	=> $penalty,
									'tds'       	    => $tds,
									'received_amount'   => $payable,
									'due_amount' 		=> $due_amount,									
									'author' 			=> $this->_author,
									'created' 			=> $this->_created,
									'modified' 			=> $this->_modified,
								);
						//print_r($receiptDtl_data); exit;
						$receiptDtlResult = $this->getDefinedTable(Sales\ReceiptDtlsTable::class)->save($receiptDtl_data);
					endforeach;
					if($receiptDtlResult > 0){
						$this->_connection->commit();
						$this->flashMessenger()->addMessage("success^ You have receipt the credit. Please Commit to confirm");
						return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt','id'=>$receiptResult));
					}					
				}else{
					$this->_connection->rollback();
					$this->flashMessenger()->addMessage("error^ You cannot book receipt. Please Contact Admin");
					return $this->redirect()->toRoute('sales', array('action' =>'addreceipt'));
				}				
         	}
			else{
			   $this->flashMessenger()->addMessage("error^ You cannot book receipt without an amount. Please Try Again");
			   return $this->redirect()->toRoute('sales', array('action' =>'addreceipt'));
			} //end of amount
       	} // endof post
         $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
         $admin_loc_array = explode(',',$admin_locs);
       	return new ViewModel(array(
       	    'location_id' => $location_id,
       	    'locations'   => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
       	    'customerObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
       	    'bankRefTypes'=> $this->getDefinedTable(Accounts\BankreftypeTable::class)->getAll(),   
       	    'subHeadObj'  => $this->getDefinedTable(Accounts\SubheadTable::class), 
            'admin_loc_array' =>$admin_loc_array,   	    
       	));       	 
     }    
  
      
      /**
       * function/action to get subhead with type
       */
      public function getsubheadbytypeAction()
      {
      	$this->init();
      	$param = explode("-",$this->_id);
      	$type= $param['0'];
      	$location = $param['1'];
      	
      	if($type == '3'){
      		//bank account
      		$results = $this->getDefinedTable(Accounts\BankaccountTable::class)->get(array('ba.location'=>$location));
      	}else{
      		//cash Account
      	    $results = $this->getDefinedTable(Accounts\CashaccountTable::class)->get(array('ca.location'=>$location));
      	}
      	foreach ($results as $row){ $account_id = $row['id'];}
      	$viewModel = new ViewModel(array(
      	        'account_id' => $account_id,
      			'subheads' => $this->getDefaultTable("fa_sub_head")->select(array('type'=>$type)),
      	));
      	$viewModel->setTerminal(true);      		
      	return  $viewModel;
      }
      
     /**
       * function/action to get sales memo
       */
      public function getsalesmemoAction()
      {
      	$this->init();
      	//retrieve sales memo whose id is not in credit details table
        $pendingCredit = $this->getDefinedTable(Sales\ReceiptTable::class)->get(array('r.customer'=>$this->_id, 'r.status'=>array('1','2')));
        $salesMemo = $this->getDefinedTable(Sales\SalesTable::class)->get(array('customer'=>$this->_id, 'status'=>'3'));             
      	$viewModel = new ViewModel(array(
      	        'creditDetails' =>$creditDetails,
      			'salesmemos'    => $salesMemo,
				'pendingCredit' => $pendingCredit,
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
      	));
      	
      	$viewModel->setTerminal(true);
      	return  $viewModel;
      }
      
       /**
       * Edit credit detail table form
       */
       public function editreceiptAction()
      {
        $this->init();
        if($this->getRequest()->isPost())
		{
		   $form = $this->getRequest()->getPost();						
       		if($form['total_payable'] > 0 ){
           		//insert the receipt details and receipt_no generation	
				$receipt_data = array(
				        'id'           => $form['receipt_id'],
           				'receipt_date' => $form['received_date'],
           				'location' => $form['location'],
           				'sub_head' => $form['sub_head'],
           				'bank_ref_type' => $form['bankreftype'],
           				'bank_ref_no' => $form['cheque_no'],           			
           				'amount'  => $form['total_payable'],
           		        'penalty'       => $form['total_penalty'],
           		        'total_tds'     => $form['total_tds'],
           				'note'  => $form['note'],           			
           				'status' => '2',           				
           				'modified' =>$this->_modified,
           		);
           		$receipt_data = $this->_safedataObj->rteSafe($receipt_data);			
            	$this->_connection->beginTransaction(); //***Transaction begins here***//
           	    $receiptResult = $this->getDefinedTable(Sales\ReceiptTable::class)->save($receipt_data); 	 
				if($receiptResult > 0){  
				    for ($i=0; $i<sizeof($form['id']);$i++){
				        $form['penalty'][$i]    = (!isset($form['penalty'][$i]) || $form['penalty'][$i] == '')?'0':$form['penalty'][$i];
                        $form['tds'][$i]        = (!isset($form['tds'][$i]) || $form['tds'][$i] == '')?'0':$form['tds'][$i];
					   $receiptDtl_data = array(
					                'id'                => $form['id'][$i],
									'credit_amount' 	=> $form['invoice'][$i],
									'penalty'       	=> $form['penalty'][$i],
									'received_amount'   => $form['payable'][$i],
									'due_amount' 		=> $form['due_amount'][$i],	
									'tds' 		        => $form['tds'][$i],
									'modified' 			=> $this->_modified,
								);
						$receiptDtlResult = $this->getDefinedTable(Sales\ReceiptDtlsTable::class)->save($receiptDtl_data);
				    }
					if($receiptDtlResult > 0){
						$this->_connection->commit();
						$this->flashMessenger()->addMessage("success^ You have receipt the credit. Please Commit to confirm");
						return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt','id'=>$form['receipt_id']));
					}					
				}else{
					$this->_connection->rollback();
					$this->flashMessenger()->addMessage("error^ You cannot book receipt. Please Contact Admin");
					return $this->redirect()->toRoute('sales', array('action' =>'editreceipt','id'=>$form['receipt_id']));
				}				
         	}
			else{
			   $this->flashMessenger()->addMessage("error^ You cannot book receipt without an amount. Please Try Again");
			  return $this->redirect()->toRoute('sales', array('action' =>'editreceipt','id'=>$form['receipt_id']));
			} //end of amount
		}
        return new ViewModel(array(
            'title'       => 'Edit Credit Receipt',
        	'receipts'    => $this->getDefinedTable(Sales\ReceiptTable::class)->get($this->_id),   
      	    'receiptDtls' => $this->getDefinedTable(Sales\ReceiptDtlsTable::class)->get(array('rd.receipt'=>$this->_id)), 
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),	
			'salesObj'    => $this->getDefinedTable(Sales\SalesTable::class),
			'bankRefTypes'=> $this->getDefinedTable(Accounts\BankreftypeTable::class)->getAll(),   
       	    'subHeadObj'  => $this->getDefinedTable(Accounts\SubheadTable::class),
        ));             
      }
      
      /**
       * Display credit receipt detail 
       */
      public function viewreceiptAction()
      {
      	$this->init();
      	
      	return new ViewModel(array(  
		    'title'       => 'Edit Credit Receipt',
      	    'receipts'    => $this->getDefinedTable(Sales\ReceiptTable::class)->get($this->_id),   
      	    'receiptDtls' => $this->getDefinedTable(Sales\ReceiptDtlsTable::class)->get(array('rd.receipt'=>$this->_id)), 
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),	
			'salesObj'    => $this->getDefinedTable(Sales\SalesTable::class),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
      	));
      }
      
  public function cancelreceiptAction()
      { 
      	$this->init();      
		$receipt_status = $this->getDefinedTable(Sales\ReceiptTable::class)->getColumn($this->_id,'status'); 
      	//change the status to cancel and revert the credit details table
		if($receipt_status != "3"){              
			$receipt_data = array(
				'id'  => $this->_id,
				'status' => '4',
				'modified' =>$this->_modified
			);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Sales\ReceiptTable::class)->save($receipt_data);
			if($result){
				$this->_connection->commit(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("success^ Receipt successfuly commited and booked");
				return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
			}
			else{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Not able to cancel the receipt");
				return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
			} 
        }
        else{
            	$this->flashMessenger()->addMessage("error^ Not able to cancel the receipt");
            	return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
        }              	
      	return new ViewModel(array(
      		  
      	));      	
      }
      
        public function commitreceiptAction()
        {
      $this->init();
      	//change the status to cancel and revert the credit details table
      	$receiptDtls = $this->getDefinedTable(Sales\ReceiptDtlsTable::class)->get(array('rd.receipt'=>$this->_id));
      	if(sizeof($receiptDtls) > 0)
      	{   
      	    $receiptResults = $this->getDefaultTable("sl_receipt")->select(array('id'=>$this->_id));
			//echo "<pre>";print_r($receiptResults); exit;
            foreach ($receiptResults as $row):
               $receipt_id = $row->id;
               $receipt_no = $row->receipt_no;
               $location = $row->location;
               $voucher_amount = $row->amount; //with penalty and after TDS
               $sub_head = $row->sub_head;
               $bank_ref_type = $row->bank_ref_type;
               $cheque_no = $row->bank_ref_no;
               $penalty_amt = $row->penalty;
               $tds_amt = $row->total_tds;
               $customer = $row->customer;
               $note   = $row->note;
            endforeach;
            $head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($sub_head, "head");
      	    $loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($location, 'prefix');
      	    $voucherType = '3'; //Receipt
      	    $prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($voucherType,'prefix');
      	     
      	    $date = date('ym',strtotime(date('Y-m-d')));
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
      	    $data1 = array(
      	    		'voucher_date' => date('Y-m-d'),
      	    		'voucher_type' => $voucherType,
      	    		'doc_id' => $receipt_no,
      	    		'doc_type' => '',
      	    		'voucher_no' => $voucher_no,
      	    		'voucher_amount' => $voucher_amount + $tds_amt,
      	    		'remark' => $note,
      	    		'status' => 3, // status commited
      	    		'author' =>$this->_author,
      	    		'created' =>$this->_created,
      	    		'modified' =>$this->_modified,
      	    );      	 
      	    $data1 = $this->_safedataObj->rteSafe($data1);
      	    $this->_connection->beginTransaction(); //***Transaction begins here***//
      	    $result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
      	    $sub_headResults = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'2', 'sh.head'=>207,'sh.ref_id'=>$customer,'ht.id'=>'1'));
			$sub_headfortds = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>'2', 'sh.head'=>236,'sh.ref_id'=>$customer,'ht.id'=>'13'));
			//print_r($sub_headfortds); exit;
			if($tds_amt > 0){
			if($result > 0 && sizeof($sub_headResults) > 0 && sizeof($sub_headfortds) > 0 ){
      	        foreach ($sub_headResults as $row1);
                foreach($sub_headfortds as $sub_headtds);				
          	        //Debit to FCB account           	           	   
              	    $tdetailsdata = array(
              	    		'transaction'  => $result,
              	    		'location'     => $location,
              	    		'activity'     => '5',
              	    		'head'         => $head,
              	    		'sub_head'     => $sub_head,
              	    		'bank_ref_type' => $bank_ref_type,
              	    		'cheque_no'    => $cheque_no,
              	    		'debit'       => $voucher_amount,
              	    		'credit'   => '0.00',
              	    		'ref_no'   => '',
              	    		'type'     => '2', //System Generated
              	    		'author'   => $this->_author,
              	    		'created'  => $this->_created,
              	    		'modified' => $this->_modified,
              	    );              	  
              	    $tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
              	    $result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata); 
              	    if($result1 > 0){
              	        //credit from customer's account
              	       $tdetailsdata1 = array(
              	        		'transaction' 	=> $result,
              	        		'location' 		=> $location,
              	        		'activity' 		=> '5',
              	        		'head' 			=> $row1['head_id'],
              	        		'sub_head' 		=> $row1['id'],
              	        		'bank_ref_type' => $bank_ref_type,
              	        		'cheque_no' 	=> $cheque_no,
              	        		'debit' 		=> '0.00',
              	        		'credit' 		=> $voucher_amount + $tds_amt - $penalty_amt,
              	        		'ref_no'		=> '',
              	        		'type' 			=> '2', //System Generated
              	        		'author' 		=>$this->_author,
              	        		'created' 		=>$this->_created,
              	        		'modified' 		=>$this->_modified,
              	        );
              	       $tdetailsdata1 = $this->_safedataObj->rteSafe($tdetailsdata1);
              	       $result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata1);
              	       if($result2 > 0){
                  	       if($penalty_amt > 0){
                  	           $tdetailsdata3 = array(
                  	           		'transaction' => $result,
                  	           		'location'    => $location,
                  	           		'activity'    => '5',
                  	           		'head'        => '56',   /* Ledger head : Miscelleneous Income */
                  	           		'sub_head'    => '1586', /* Sub Ledger : Penalty Income*/
                  	           		'bank_ref_type' => $bank_ref_type,
                  	           		'cheque_no'   => $cheque_no,
                  	           		'debit'       => '0.00',
                  	           		'credit'      => $penalty_amt,
                  	           		'ref_no'      => '',
                  	           		'type'        => '2', //System Generated
                  	           		'author' =>$this->_author,
                  	           		'created' =>$this->_created,
                  	           		'modified' =>$this->_modified,
                  	           );
                  	           $tdetailsdata3 = $this->_safedataObj->rteSafe($tdetailsdata3);
                  	           $result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata3);
						       }
                  	          if($tds_amt > 0){
                  	           $tdetailsdata4 = array(
                  	           		'transaction' => $result,
                  	           		'location'    => $location,
                  	           		'activity'    => '5',
                  	           		'head'        => '236',   /* Ledger head : Advance TDS */
                  	           		'sub_head'    => $sub_headtds['id'], /* Sub Ledger : TDS*/
                  	           		'bank_ref_type' => $bank_ref_type,
                  	           		'cheque_no'   => $cheque_no,
                  	           		'debit'       => $tds_amt,
                  	           		'credit'      => '0.00',
                  	           		'ref_no'      => '',
                  	           		'type'        => '2', //System Generated
                  	           		'author' =>$this->_author,
                  	           		'created' =>$this->_created,
                  	           		'modified' =>$this->_modified,
                  	           );
                  	           $tdetailsdata4 = $this->_safedataObj->rteSafe($tdetailsdata4);
                  	           $result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata4);
                  	       }
              
              	      }else{
              	       	  //rollback
              	          $this->_connection->rollback();
              	          $this->flashMessenger()->addMessage("error^Cannot trasaction details. The system has roll backed");
              	          return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
              	      }
              	    }
          	    if($result2 > 0){					
          	        //update the receipt status to commit
          	        $receiptData = array(
          	        	'id'          =>  $this->_id,
          	            'transaction' => $result,
          	            'status'      =>  '3',
          	            'modified'    =>$this->_modified,
          	        );
          	        $result3 = $this->getDefinedTable(Sales\ReceiptTable::class)->save($receiptData);
					if($result3){
						foreach($receiptDtls as $row):
						    $prevReceviedAmt = $this->getDefinedTable(Sales\SalesTable::class)->getColumn($row['sales'], 'received_amount');
					        $actualSalesAmt = $prevReceviedAmt + ($row['received_amount'] + $row['tds'] - $row['penalty']); 
							$sales_update = array(
							    'id' 			   => $row['sales'],
								'received_amount'  => $actualSalesAmt,
								'modified'         => $this->_modified,
							);						
							$result4 = $this->getDefinedTable(Sales\SalesTable::class)->save($sales_update);
					    endforeach;
					}					
                    //commit
          	        $this->_connection->commit();
          	        $this->flashMessenger()->addMessage("success^ Credit Receipt Booked with transaction No. : ".$voucher_no);
          	        return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
          	    }
				 $this->getDefinedTable(Sales\ReceiptTable::class)->updateRecords($receipt_id,$result);
      	    }  
      	    else{
      	        $this->_connection->rollback();
      	        $this->flashMessenger()->addMessage("error^ Credit receipt cannot be booked. Please contact Administrator to map the heads and sub heads");
      	        return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
      	    }			
        }		
		else{
			if($result > 0 && sizeof($sub_headResults) > 0 ){
      	        foreach ($sub_headResults as $row1);				
          	        //Debit to FCB account           	           	   
              	    $tdetailsdata = array(
              	    		'transaction'  => $result,
              	    		'location'     => $location,
              	    		'activity'     => '5',
              	    		'head'         => $head,
              	    		'sub_head'     => $sub_head,
              	    		'bank_ref_type' => $bank_ref_type,
              	    		'cheque_no'    => $cheque_no,
              	    		'debit'       => $voucher_amount,
              	    		'credit'   => '0.00',
              	    		'ref_no'   => '',
              	    		'type'     => '2', //System Generated
              	    		'author'   => $this->_author,
              	    		'created'  => $this->_created,
              	    		'modified' => $this->_modified,
              	    );              	  
              	    $tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
              	    $result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata); 
              	    if($result1 > 0){
              	        //credit from customer's account
              	       $tdetailsdata1 = array(
              	        		'transaction' 	=> $result,
              	        		'location' 		=> $location,
              	        		'activity' 		=> '5',
              	        		'head' 			=> $row1['head_id'],
              	        		'sub_head' 		=> $row1['id'],
              	        		'bank_ref_type' => $bank_ref_type,
              	        		'cheque_no' 	=> $cheque_no,
              	        		'debit' 		=> '0.00',
              	        		'credit' 		=> $voucher_amount + $tds_amt - $penalty_amt,
              	        		'ref_no'		=> '',
              	        		'type' 			=> '2', //System Generated
              	        		'author' 		=>$this->_author,
              	        		'created' 		=>$this->_created,
              	        		'modified' 		=>$this->_modified,
              	        );
              	       $tdetailsdata1 = $this->_safedataObj->rteSafe($tdetailsdata1);
              	       $result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata1);
              	       if($result2 > 0){
                  	       if($penalty_amt > 0){
                  	           $tdetailsdata3 = array(
                  	           		'transaction' => $result,
                  	           		'location'    => $location,
                  	           		'activity'    => '5',
                  	           		'head'        => '56',   /* Ledger head : Miscelleneous Income */
                  	           		'sub_head'    => '1586', /* Sub Ledger : Penalty Income*/
                  	           		'bank_ref_type' => $bank_ref_type,
                  	           		'cheque_no'   => $cheque_no,
                  	           		'debit'       => '0.00',
                  	           		'credit'      => $penalty_amt,
                  	           		'ref_no'      => '',
                  	           		'type'        => '2', //System Generated
                  	           		'author' =>$this->_author,
                  	           		'created' =>$this->_created,
                  	           		'modified' =>$this->_modified,
                  	           );
                  	           $tdetailsdata3 = $this->_safedataObj->rteSafe($tdetailsdata3);
                  	           $result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata3);
						       }
              
              	      }else{
              	       	  //rollback
              	          $this->_connection->rollback();
              	          $this->flashMessenger()->addMessage("error^Cannot trasaction details. The system has roll backed");
              	          return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
              	      }
              	    }
          	    if($result2 > 0){					
          	        //update the receipt status to commit
          	        $receiptData = array(
          	        	'id'          =>  $this->_id,
          	            'transaction' => $result,
          	            'status'      =>  '3',
          	            'modified'    =>$this->_modified,
          	        );
          	        $result3 = $this->getDefinedTable(Sales\ReceiptTable::class)->save($receiptData);
					if($result3){
						foreach($receiptDtls as $row):
						    $prevReceviedAmt = $this->getDefinedTable(Sales\SalesTable::class)->getColumn($row['sales'], 'received_amount');
					        $actualSalesAmt = $prevReceviedAmt + ($row['received_amount'] + $row['tds'] - $row['penalty']); 
							$sales_update = array(
							    'id' 			   => $row['sales'],
								'received_amount'  => $actualSalesAmt,
								'modified'         => $this->_modified,
							);						
							$result4 = $this->getDefinedTable(Sales\SalesTable::class)->save($sales_update);
					    endforeach;
					}					
                    //commit
          	        $this->_connection->commit();
          	        $this->flashMessenger()->addMessage("success^ Credit Receipt Booked with transaction No. : ".$voucher_no);
          	        return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
          	    }
				 $this->getDefinedTable(Sales\ReceiptTable::class)->updateRecords($receipt_id,$result);
      	    }  
      	    else{
      	        $this->_connection->rollback();
      	        $this->flashMessenger()->addMessage("error^ Credit receipt cannot be booked. Please contact Administrator to map the heads and sub heads");
      	        return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
      	    }
		}
		}
  	    else{
        	$this->flashMessenger()->addMessage("error^ Not able to commit and book the receipt");
        	return $this->redirect()->toRoute('sales', array('action' =>'viewreceipt', 'id'=>$this->_id));
        }       	
      	return new ViewModel(array(
      
      	));
      }
      /**
	* Market Action
	**/
	 public function marketAction()
     {
       $this->init();
	   $month = '';
	   $year = '';
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			if(strlen($month)==1){
				$month = '0'.$month;
			}
			$location = $form['location'];
			$payment = $form['payment'];
		}else{
			$month = ($month == '')?date('m'):$month;
			$year = ($year == '')? date('Y'):$year;
			
			$location = $this->_userloc;
			$payment = '-1';
		}
		$month = ($month == '')?date('m'):$month;
		$year = ($year == '')? date('Y'):$year;
		
		$minYear = $this->getDefinedTable(Sales\MarketTable::class)->getMin('document_date');
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		$data = array(
				'year' => $year,
				'month' => $month,
				'minYear' => $minYear,
				'location' => $location,
		);
		///echo '<pre>';print_r($data);exit;
		$results = $this->getDefinedTable(Sales\MarketTable::class)->getLocDateWise('document_date',$year,$month,$location,array('status'=>array(1,2,3)));
		
		return new ViewModel(array(
				'title' 	  => 'Market Document',
				'data'        => $data,
				'results' => $results,
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		));                
     }
	 /**
	 *  add market action
	 */
	public function addmarketAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();						
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');			
			$date = date('ym',strtotime($form['document_date']));					
			$tmp_DONo = $location_prefix."DO".$date; 			
			$results = $this->getDefinedTable(Sales\MarketTable::class)->getMonthlyDO($tmp_PONo);
			
			$do_no_list = array();
            foreach($results as $result):
	       		array_push($do_no_list, substr($result['document_no'], 8)); 
		   	endforeach;
            $next_serial = max($do_no_list) + 1;
               
			switch(strlen($next_serial)){
				case 1: $next_do_serial = "000".$next_serial; break;
			    case 2: $next_do_serial = "00".$next_serial;  break;
			    case 3: $next_do_serial = "0".$next_serial;   break;
			   	default: $next_do_serial = $next_serial;       break; 
			}					   
			
			$document_no = $tmp_DONo.$next_do_serial;
			$data = array(
				'document_no'      => $document_no, 
				'location'         => $form['location'],
				'activity'         => $form['activity'],
				'document_date'    => $form['do_date'],
				'note'             => $form['note'],
				'status' 			=> '1', 
				'author'           => $this->_author,
				'created'          => $this->_created,
				'modified'         => $this->_modified
			);
			$data   = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(sales\MarketTable::class)->save($data);
			if($result > 0){ 
				$item         = $form['item'];
				$uom          = $form['uom'];
				$rate         = $form['rate'];
				$batch_id     = $form['batch'];
				$remarks      = $form['remarks'];
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i]) && $item[$i] > 0):
						$dt_details = array(
							'market'       => $result,
							'item'         => $item[$i],
							'batch'        => $batch_id[$i],
							'uom'          => $uom[$i],
							'rate'         => $rate[$i],
							'remarks' 	   => $remarks[$i],
							'author'       => $this->_author,
							'created'      => $this->_created,
							'modified'     => $this->_modified
						);
		     		$dt_details   = $this->_safedataObj->rteSafe($dt_details);
			     	$this->getDefinedTable(Sales\MarketDtlsTable::class)->save($dt_details);		
				   	endif; 		     
				endfor;
				$this->flashMessenger()->addMessage("success^ Successfully added new Market Rate Dtls :". $document_no);
				return $this->redirect()->toRoute('sales', array('action' =>'viewmarket', 'id' => $result));
			}
			else{
				$this->flashMessenger()->addMessage("error^ Failed to add new Market Rate Dtls");
				return $this->redirect()->toRoute('addmarket');
			}		
		}		
		return new ViewModel( array(
				'user_location' => $this->_userloc,
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'  =>$this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
		));		
	}
	
	 /**
	 *  edit market action
	 */
	public function editmarketAction()
	{
		$this->init();	
		
		if($this->getRequest()->isPost()):
			$request= $this->getRequest();
			$form = $request->getPost();
			$data =array(
					'id' => $form['m_id'],
					'document_date' => $form['do_date'],
					'location' => $form['location'],
					'activity' => $form['activity'],
					'note' => $form['note'],
					'status' => 1,
					'author' =>$this->_author,
					'modified' =>$this->_modified
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\MarketTable::class)->save($data);
			if($result>0):
				$mdtls_id   = $form['mdtls_id'];
				$item= $form['item'];
				$batch= $form['batch'];
				$uom= $form['uom'];
				$rate= $form['rate'];
				$remarks = $form['remarks'];
				$delete_rows = $this->getDefinedTable(Sales\MarketDtlsTable::class)->getNotIn($mdtls_id, array('market' => $result));
				
				for($i=0; $i < sizeof($item); $i++):
					if(isset($item[$i])):
						$m_detail_data = array(
							'id' => $mdtls_id[$i],
							'market' => $result,
							'item' => $item[$i],
							'batch' => $batch[$i],
							'uom' => $uom[$i],
							'rate' => $rate[$i],
							'remarks' => $remarks[$i],
							'author' =>$this->_author,
							'modified' =>$this->_modified,
						);
						$m_detail_data = $this->_safedataObj->rteSafe($m_detail_data);
						$this->getDefinedTable(Sales\MarketDtlsTable::class)->save($m_detail_data);
					endif;
				endfor;
				
				//deleting deleted table rows form database table;
				//print_r($delete_rows);exit;
				foreach($delete_rows as $delete_row):
				//echo $delete_row['id'];
					$this->getDefinedTable(Sales\MarketDtlsTable::class)->remove($delete_row['id']);
				endforeach;
				
				$do_no = $this->getDefinedTable(Sales\MarketTable::class)->getColumn($form['m_id'],'document_no');
				$this->flashMessenger()->addMessage("success^ Successfully updated document no. ".$do_no);
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update market information");
			endif;
			return $this->redirect()->toRoute('sales',array('action'=>'viewmarket','id'=>$form['m_id']));
		endif;
		return new ViewModel( array(
				'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'activities'  =>$this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
				'batchObj'    => $this->getDefinedTable(Stock\BatchTable::class),   
				'movingitemObj'    => $this->getDefinedTable(Stock\MovingItemTable::class),   
				'itemObj'    => $this->getDefinedTable(Stock\ItemTable::class),   
				'itemuomObj'    => $this->getDefinedTable(Stock\ItemUomTable::class),   
				'uomObj'    => $this->getDefinedTable(Stock\UomTable::class),   
				'markets'    => $this->getDefinedTable(Sales\MarketTable::class)->get($this->_id),   
      	        'mtDtls' => $this->getDefinedTable(Sales\MarketDtlsTable::class)->get(array('mrd.market'=>$this->_id)), 
		));		
	}
	/**
	 * confirm dispatch goods received
	 */
	public function commitmarketAction()
	{
		$this->init();
		
		$m_id = $this->_id;		
		$mar_data = array(
			'id' => $m_id,
			'status' => 3,
			'author' => $this->_author,
			'modified' => $this->_modified,		
		);
		$mar_data   = $this->_safedataObj->rteSafe($mar_data);
		$result2 = $this->getDefinedTable(Sales\MarketTable::class)->save($mar_data);
		
		$do_no = $this->getDefinedTable(Sales\MarketTable::class)->getColumn($m_id,'document_no');
		if($result2){
			if($result)
				$this->flashMessenger()->addMessage("success^ Successfully Committed Document No. ".$do_no);
		}else{
			$this->flashMessenger()->addMessage("error^ Failed to Commit Market Information");
		}
		return $this->redirect()->toRoute('sales',array('action'=>'viewmarket','id'=>$m_id));
	}
	/**
	 * Get Item List According to Activity
	 */
	public function getitemactivityAction()
	{	$this->init();
		
		$form = $this->getRequest()->getPost();
		$activity_id = $form['activity_id'];
		if($activity_id == '1'):
			$items = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.activity' => $activity_id));
		else:
			$items = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.activity' => $activity_id));
		endif;
		$stock_item.="<option value=''></option>";
		foreach($items as $item):
		      $item_id =  $item['id'];
				if($item_id != 1150002 || $item_id == 2600122 || $item_id == 102001 || $item_id != 387002):
				    $stock_item .="<option value='".$item_id."'>".$item['code']."</option>";
				endif;
		endforeach;
		echo json_encode(array(
				'stock_item' => $stock_item,
		));
		exit;
	}
	/**
	 * get details of dispatch
	 */
	public function getitemdetailsAction()
	{
		$this->init();		
		$form = $this->getRequest()->getpost();		
		$item_id = $form['item_id'];
		$source_loc = $form['source_loc'];
	
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
			$batch = "<option value=''>N/A</option>";
			$movingitemObj = $this->getDefinedTable(Stock\MovingItemTable::class);
			$batch_uom = $movingitemObj->getColumn(array('item'=>$item_id,'location'=>$source_loc),'uom');
			echo json_encode(array(
					'batch' => $batch,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
					
			));
		else: 
			$batchs = $batchObj->getSalesBatch(array('b.item'=>$item_id,'d.location'=>$source_loc));
			$select_batch .="<option value=''></option>";
			foreach($batchs as $batch):
				if($batch['end_date'] == "0000-00-00" || $batch['end_date'] == ""):
					$select_batch .="<option value='".$batch['id']."'>".$batch['batch']."</option>";
				endif;
			endforeach;
			$max_batch_id = $batchObj->getMaxbat('b.id',array('b.item'=>$item_id,'d.location'=>$source_loc));
			$batch_uom = $batchObj->getColumn(array('id'=>$max_batch_id,'item'=>$item_id),'uom');
			echo json_encode(array(
					'batch' => $select_batch,
					'latest_batch' => $max_batch_id,
					'uom' => $select_uom,
					'batch_uom' => $batch_uom,
			));
		endif;
		exit;
	}
	/**
       * Display market Infromation
       */
      public function viewmarketAction()
      {
      	$this->init();
      	
      	return new ViewModel(array(  
		    'title'    => 'view Market Information',
      	    'markets'    => $this->getDefinedTable(Sales\MarketTable::class)->get($this->_id),   
      	    'mtDtls' => $this->getDefinedTable(Sales\MarketDtlsTable::class)->get(array('mrd.market'=>$this->_id)), 
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'batchObj' => $this->getDefinedTable(Stock\BatchTable::class),
			'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
      	));
      }
}
