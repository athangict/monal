<?php
namespace Sales\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface; 
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Sales\Model As Sales;
use Administration\Model As Administration;
class FundController extends AbstractActionController
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

		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		$this->_userloc = $this->_user->location;
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
		
	/**
	 * fund transfer index Action
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
		$minYear = $this->getDefinedTable(Sales\FundTransferTable::class)->getMin('','transfer_date');
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		$data = array(
				'year' => $year,
				'month' => $month,
				'minYear' => $minYear
		);
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		$admin_loc_array = explode(',',$admin_locs);
		$fundtransfers = $this->getDefinedTable(Sales\FundTransferTable::class)->getAdminlocWise($this->_userloc, $admin_loc_array);

		return new ViewModel(array(
			   //'statusObj'      => $this->getDefinedTable('Acl\StatusTable'),
			   'data'             => $data,
			   'fundtransfers'    => $fundtransfers,
			   'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
			   'activityObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
			   'subheadObj'       => $this->getDefinedTable(Accounts\SubheadTable::class),
		));
	}
	
	/**
	 * Add fund transfer Action
	 */
	public function addfundtransfer_oldAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['from_location'],'prefix');			
			$date = date('ym',strtotime($form['transfer_date']));					
			$tmp_No = $location_prefix."FT".$date; 			
			$results = $this->getDefinedTable(Sales\FundTransferTable::class)->getMonthlyFT($tmp_No);
			
			$ft_no_list = array();
            foreach($results as $result):
	       		array_push($ft_no_list, substr($result['transfer_no'], 8)); 
		   	endforeach;
            $next_serial = max($ft_no_list) + 1;
               
			switch(strlen($next_serial)){
				case 1: $next_ft_serial = "000".$next_serial; break;
			    case 2: $next_ft_serial = "00".$next_serial;  break;
			    case 3: $next_ft_serial = "0".$next_serial;   break;
			   	default: $next_ft_serial = $next_serial;       break; 
			}					   
			
			$transfer_no = $tmp_No.$next_ft_serial;
			//echo $transfer_no; exit;
			$data = array(
					'transfer_no'   => $transfer_no,
					'transfer_date' => $form['transfer_date'],
					'from_location' => $form['from_location'],
					'to_location'   => $form['to_location'],
					'from_activity' => $form['from_activity'],
					'to_activity'   => $form['to_activity'],
					'from_sub_head' => $form['from_sub_head'],
					'to_sub_head'   => $form['to_sub_head'],
					'transfer_amount' => $form['transfer_amount'],
					'transaction' => '',
					'status' => '1', 
					'remark' => $form['remark'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$result = $this->getDefinedTable(Sales\FundTransferTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Fund Transfer successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new Fund Transfer");
			endif;
			return $this->redirect()->toRoute('fund', array('action'=>'index'));
		}
		
		return new ViewModel(array(
					'title' 	  => 'Add Fund Transfer',
					'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
					'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
					'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
					'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
		));
	}
        public function addfundtransferAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location,'prefix');			
			$date = date('ym',strtotime($form['transfer_date']));					
			$tmp_No = $location_prefix."FT".$date; 			
			$results = $this->getDefinedTable(Sales\FundTransferTable::class)->getMonthlyFT($tmp_No);
			
			$ft_no_list = array();
            foreach($results as $result):
	       		array_push($ft_no_list, substr($result['transfer_no'], 8)); 
		   	endforeach;
            $next_serial = max($ft_no_list) + 1;
               
			switch(strlen($next_serial)){
				case 1: $next_ft_serial = "000".$next_serial; break;
			    case 2: $next_ft_serial = "00".$next_serial;  break;
			    case 3: $next_ft_serial = "0".$next_serial;   break;
			   	default: $next_ft_serial = $next_serial;       break; 
			}					   
			
			$transfer_no = $tmp_No.$next_ft_serial;
			//echo $transfer_no; exit;
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$data = array(
					'transfer_no'   => $transfer_no,
					'transfer_date' => $form['transfer_date'],
					'from_location' => $form['from_location'],
					'to_location'   => $form['to_location'],
					'from_activity' => $form['from_activity'],
					'to_activity'   => $form['to_activity'],
					'from_sub_head' => $form['from_sub_head'],
					'to_sub_head'   => $form['to_sub_head'],
					'transfer_amount' => $form['transfer_amount'],
					'transaction' => '',
					'status' => '3', 
					'remark' => $form['remark'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$ftresult = $this->getDefinedTable(Sales\FundTransferTable::class)->save($data);
			if($ftresult > 0):
				// booking to transaction
				$vouchertype = 10;
				//generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($vouchertype,'prefix');
				$date = date('ym',strtotime($form['transfer_date']));
				$temp_No = $loc.$prefix.$date;
	
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($temp_No);
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
				$voucher_no = $temp_No.$next_dc_serial;
				$data1 = array(
						'voucher_date' => $form['transfer_date'],
						'voucher_type' => $vouchertype,
						'doc_id' => $transfer_no,
						'doc_type' => 'Fund Transfer',
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$form['transfer_amount']),
						'remark' => $form['remark'],
						'status' => '3',
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				//echo "<pre>";print_r($data1); exit;
				$tresult = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			
				if($tresult > 0):
					//insert into transactiondetail table
					//$from_head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($form['from_sub_acc'],'head');
					$from_tdetails = array(
							'transaction' => $tresult,
							'location' => $form['from_location'],
							'activity' => $form['from_activity'],
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($form['from_sub_head'],'head'),
							'sub_head' => $form['from_sub_head'],
							'debit' => '0.000',
							'credit' => $form['transfer_amount'],
							'ref_no'=> $ftresult,
							'type'=> 2, //sys generated data
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					$from_tdetails = $this->_safedataObj->rteSafe($from_tdetails);
					//echo "<pre>"; print_r($from_tdetails);
					$result = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($from_tdetails);
					
					//$to_head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($form['to_sub_acc'],'head');
					$to_tdetails = array(
							'transaction' => $tresult,
							'location' => $form['to_location'],
							'activity' => $form['to_activity'],
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($form['to_sub_head'],'head'),
							'sub_head' => $form['to_sub_head'],
							'debit' => $form['transfer_amount'],
							'credit' => '0.000',
							'ref_no'=> $ftresult,
							'type'=> 2, //sys generated data
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					$to_tdetails = $this->_safedataObj->rteSafe($to_tdetails);
					//echo "<pre>"; print_r($to_tdetails);
					$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($to_tdetails);
					
					if($result > 0 && $result2 > 0):
                                                $update_data = array(
								'id' => $ftresult,
								'transaction' => $tresult,
						);
						$this->_safedataObj->rteSafe($update_data);
						$this->getDefinedTable(Sales\FundTransferTable::class)->save($update_data);

						$this->_connection->commit(); // commit transaction on success
						$this->flashMessenger()->addMessage("success^ New Fund Transfer ".$transfer_no." And Transaction ".$voucher_no."  successfully added");
						return $this->redirect()->toRoute('fund', array('action'=>'viewfundtransfer', 'id' =>$ftresult));
					else:
						$this->_connection->rollback(); // rollback transaction over failure
						$this->flashMessenger()->addMessage("Failed^ Subhead Missing");
					endif;
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to book fund transfer to transaction");
					return $this->redirect()->toRoute('fund', array('action'=>'index'));
				endif;
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("notice^ Failed to add new Fund Transfer");
			endif;
			return $this->redirect()->toRoute('fund', array('action'=>'index'));
		}
		
		return new ViewModel(array(
					'title' 	  => 'Add Fund Transfer',
					'regions'     => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
					'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
					'activities'  => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
					'subheadObj'  => $this->getDefinedTable(Accounts\SubheadTable::class),
		));
	}
	
	/**
	* View Fund Transfer
	*/
	public function viewfundtransferAction()
	{
		$this->init();
		
		return new ViewModel(array(
					'title'			   => 'View Fund Transfer Details',
					'fundtransfers'    => $this->getDefinedTable(Sales\FundTransferTable::class)->get($this->_id),
				    'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				    'activityObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
				    'subheadObj'       => $this->getDefinedTable(Accounts\SubheadTable::class),
					'headObj'          => $this->getDefinedTable(Accounts\HeadTable::class),
					'userObj'          => $this->getDefinedTable(Acl\UsersTable::class),
		));
	}
	
	/**
	* Edit Fund Transfer
	*/
	public function editfundtransferAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			
			$data = array(
					'id' => $form['transfer_id'],
					'transfer_no'   => $form['transfer_no'],
					'transfer_date' => $form['transfer_date'],
					'from_location' => $form['from_location'],
					'to_location'   => $form['to_location'],
					'from_activity' => $form['from_activity'],
					'to_activity'   => $form['to_activity'],
					'from_sub_head' => $form['from_sub_head'],
					'to_sub_head'   => $form['to_sub_head'],
					'transfer_amount' => $form['transfer_amount'],
					'transaction' => '',
					'status' => '1', 
					'remark' => $form['remark'],
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			//echo "<pre>"; print_r($data); exit;
			$result = $this->getDefinedTable(Sales\FundTransferTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated fund transfer");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to update fund transfer");
			endif;
			return $this->redirect()->toRoute('fund', array('action'=>'viewfundtransfer','id'=>$form['transfer_id']));
		}
		return new ViewModel(array(
					'title'			   => 'Edit Fund Transfer Details',
					'fundtransfers'    => $this->getDefinedTable(Sales\FundTransferTable::class)->get($this->_id),
					'regions'		   => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				    'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				    'activityObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
				    'subheadObj'       => $this->getDefinedTable(Accounts\SubheadTable::class),
					'headObj'          => $this->getDefinedTable(Accounts\HeadTable::class),
		));
	}
	
	/**
	* Book Fund Transfer
	**/
	public function bookfundtransferAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			// booking to transaction
			if(isset($form['voucher_date']) && isset($form['voucher_amount'])):
				//generate voucher no
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
				$date = date('ym',strtotime($form['voucher_date']));
				$tmp_VNo = $loc.$prefix.$date;
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VNo);
			
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
				$voucher_no = $tmp_VNo.$next_dc_serial;	
				$data1 = array(
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'doc_id' => $form['doc_id'],
						'doc_type' => $form['doc_type'],
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
						'remark' => $form['remark'],
						'status' => '1',
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
				);
				$data1 = $this->_safedataObj->rteSafe($data1);
				//echo "<pre>";print_r($data1); exit;
				$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			
				if($result > 0):
					//insert into transactiondetail table
					$to_location= $form['location'];
					$to_activity= $form['activity'];
					$to_head = $form['head'];
					$to_sub_head= $form['sub_head'];
					$to_debit= $form['debit'];
					$to_credit= $form['credit'];
					
					$from_tdetails = array(
							'transaction' => $result,
							'location' => $form['from_location'],
							'activity' => $form['from_activity'],
							'head' => $form['from_head'],
							'sub_head' => $form['from_sub_head'],
							'debit' => (isset($form['from_debit']))? $form['from_debit']:'0.000',
							'credit' => (isset($form['from_credit']))? $form['from_credit']:'0.000',
							'ref_no'=> '',
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					$from_tdetails = $this->_safedataObj->rteSafe($from_tdetails);
					//echo "<pre>"; print_r($from_tdetails);
					$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($from_tdetails);
					
					$to_tdetails = array(
							'transaction' => $result,
							'location' => $form['to_location'],
							'activity' => $form['to_activity'],
							'head' => $form['to_head'],
							'sub_head' => $form['to_sub_head'],
							'debit' => (isset($form['to_debit']))? $form['to_debit']:'0.000',
							'credit' => (isset($form['to_credit']))? $form['to_credit']:'0.000',
							'ref_no'=> '',
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
					);
					$to_tdetails = $this->_safedataObj->rteSafe($to_tdetails);
					//echo "<pre>"; print_r($to_tdetails);
					$this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($to_tdetails);
					
					/** Change the Status in fund trnasfer as commit **/
					$data_fund = array(
							'id' => $this->_id,
							'status' => '3',
							'author' => $this->_author,
							'modified' => $this->_modified,
					);
					$data_fund = $this->_safedataObj->rteSafe($data_fund);
					//echo "<pre>"; print_r($data_fund);
					$this->getDefinedTable(Sales\FundTransferTable::class)->save($data_fund);
					
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('fund', array('action' =>'viewfundtransfer', 'id' => $this->_id));
				else:
					$this->flashMessenger()->addMessage("Failed^ Failed to book fund transfer to transaction");
					$this->redirect()->toRoute('fund', array('action'=>'viewfundtransfer', 'id'=>$this->_id));
				endif;
			else:
				$this->flashMessenger()->addMessage("Failed^ Not set voucher date and voucher amount");
			endif;
		endif;//end of isPost
		
		return new ViewModel(array(
					'title'			   => 'Book Fund Transfer',
					'fundtransfers'    => $this->getDefinedTable(Sales\FundTransferTable::class)->get($this->_id),
					'regions'		   => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				    'locationObj'      => $this->getDefinedTable(Administration\LocationTable::class),
				    'activityObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
				    'subheadObj'       => $this->getDefinedTable(Accounts\SubheadTable::class),
					'headObj'          => $this->getDefinedTable(Accounts\HeadTable::class),
					'journals'         => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
		));
	}
}
