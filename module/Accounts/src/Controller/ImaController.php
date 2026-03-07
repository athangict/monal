<?php
namespace Accounts\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Application\Model As Application;
use Accounts\Model As Accounts;
use Hr\Model As Hr;

class ImaController extends AbstractActionController
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
	protected $_permission; // permission plugin
    
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
	 *  Index 
	 */
	public function indexAction()
	{
		$this->init();		
		return new ViewModel(array(
			));
		
	} 
	
	/**
	 * Inceme action
	 */
	public function incomeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Accounts\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Accounts\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
	
		$user_id=$this->_login_id;
		//echo $user_id; exit;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title' => 'Income',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'transTable'     => $transTable,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Accounts\JournalTable::class),
		));
	}
	
	/**
	 *Add Income Action
	 **/
	public function addincomeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
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
			$region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'   =>$region,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				if($cheque_no!=NULL){
					$cheque_no=$cheque_no;
				}else{
					$cheque_no='0';
				}
				$debit= $form['debit'];
				$credit= $form['credit'];
				$reference= $form['reference'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'currency' =>$form['currency'],
							'rate' =>$form['rate'],
							'debit_sdr' =>($debit[$i]>0)? $form['amount_usd']:'0.000',
							'credit_sdr' => ($credit[$i]>0)? $form['amount_usd']:'0.000',
							'against'=>0,
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> $reference[$i], 
							'type' => '1',
							'status' => 2,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				/**ADDED FOR SDR TRANSACTION */
					$sdr_details=array(
						'transaction' => $result,
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'currency' => 2,
						'item' => $form['item'],
						'weight' => $form['weight'],
						'sdr' => $form['sdr'],
						'item_no' => $form['item1'],
						'nos' => $form['no'],
						'sdr_no' => $form['sdrno'],
						'totalsdr'=>$form['amount_usd'],
						'rate' => $form['rate'],
						'location' => $location[0],
						'status' => 1,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);  
					$this->getDefinedTable(Accounts\SdrTable::class)->save($sdr_details);
				/**END OF SDR TRANSACTION */
				if($result1 > 0):
					$this->_connection->commit();
					$this->flashMessenger()->addMessage("success^ New Income successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('ima', array('action' =>'viewincome', 'id' => $result));
				else:
					$this->_connection->rollback();
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Income");		
					return $this->redirect()->toRoute('income');
				endif;				
			}
			else
			{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Income");		
				return $this->redirect()->toRoute('income');
			}
		}	
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==8|| $this->_login_role==6|| $role==array(2,17)|| $role==array(5,6)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title' => 'Add Income',
			'todaydate' =>$today_date,
			'userlocation'=>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'items' => $this->getDefinedTable(Accounts\ItemTable::class)->getAll(),
			'regions' => $regions,
			'currencies' => $this->getDefinedTable(Accounts\CurrencyTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>[153,105,243])),
		));
	}
	/**
	 *Edit Travel Action
	 **/
	public function editincomeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'   =>$region,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction();
			$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				if($cheque_no!=NULL){
					$cheque_no=$cheque_no;
				}else{
					$cheque_no='0';
				}
				$debit= $form['debit'];
				$credit= $form['credit'];
				$reference= $form['reference'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
						$tdetailsdata = array(
							'id'			=>$tdetails_id[$i],
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'currency' =>$form['currency'],
							'rate' =>$form['rate'],
							'debit_sdr' =>($debit[$i]>0)? $form['amount_usd']:'0.000',
							'credit_sdr' => ($credit[$i]>0)? $form['amount_usd']:'0.000',
							'against'=>0,
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> $reference[$i], 
							'type' => '1',
							'status' => 2,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
					else:
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'currency' =>$form['currency'],
							'rate' =>$form['rate'],
							'debit_sdr' =>(isset($debit[$i]))? $form['amount_usd']:'0.000',
							'credit_sdr' => (isset($credit[$i]))? $form['amount_usd']:'0.000',
							'against'=>0,
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> $reference[$i], 
							'type' => '1',
							'status' => 2,
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					endif;
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				/**ADDED FOR SDR TRANSACTION */
					$sdr_details=array(
						'id'			=> $form['sdrid'],
						'transaction' => $result,
						'voucher_date' => $form['voucher_date'],
						'voucher_type' => $form['voucher_type'],
						'currency' => 2,
						'item' => $form['item'],
						'weight' => $form['weight'],
						'sdr' => $form['sdr'],
						'item_no' => $form['item1'],
						'nos' => $form['no'],
						'sdr_no' => $form['sdrno'],
						'totalsdr'=>$form['amount_usd'],
						'rate' => $form['rate'],
						'location' => $location[0],
						'status' => 1,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);  
					$this->getDefinedTable(Accounts\SdrTable::class)->save($sdr_details);
				/**END OF SDR TRANSACTION */
				if($result1 > 0):
					$this->_connection->commit();
					$this->flashMessenger()->addMessage("success^ New Income successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('ima', array('action' =>'viewincome', 'id' => $result));
				else:
					$this->_connection->rollback();
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Income");		
					return $this->redirect()->toRoute('income');
				endif;				
			}
			else
			{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Income");		
				return $this->redirect()->toRoute('income');
			}
		}	
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==8|| $this->_login_role==6|| $role==array(2,17)|| $role==array(5,6)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		return new ViewModel(array(
			'title' => 'Edit Income',
			'userlocation'=>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'items' => $this->getDefinedTable(Accounts\ItemTable::class)->getAll(),
			'regions' => $regions,
			'currencies' => $this->getDefinedTable(Accounts\CurrencyTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactions' => $this->getDefinedTable(Accounts\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'subhead' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>[153,105])),
			'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>[153,105,243])),
			'tdetailsObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'sdrdetails' => $this->getDefinedTable(Accounts\SdrTable::class)->get(array('transaction' => $this->_id)),
		));
	}
	/**
	 *  View Income action
	 */
	public function viewincomeAction()
	{
		$this->init();
		if($this->_login_role==99 || $this->_login_role==100):
		    $edit_option = 1;
		else:
		    $edit_option = 0;
		endif;
		return new ViewModel(array(
	    	'login_id'     =>$this->_login_id,
			'edit_option'  =>$edit_option,
			'transactionrow' => $this->getDefinedTable(Accounts\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'sdrdetails' => $this->getDefinedTable(Accounts\SdrTable::class)->get(array('transaction' => $this->_id)),
			'currency' => $this->getDefinedTable(Accounts\CurrencyTable::class),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
			'items' => $this->getDefinedTable(Accounts\ItemTable::class),
			'currencies' => $this->getDefinedTable(Accounts\CurrencyTable::class),
		));
		
	} 
	/**
	 * commitTransaction action
	 **/
	public function commitincomeAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' => 4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Accounts\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' => 4, // status committed 
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ Transaction Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("ima", array("action"=>"viewincome", "id" => $this->_id));
	}
	 /* Delete ta details action
	 */
	public function deleteAction()
	{
		$this->init(); 
		foreach($this->getDefinedTable(Hr\TADetailsTable::Class)->get($this->_id) as $tadetails);
		$result = $this->getDefinedTable(Hr\TADetailsTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ Item deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete item");
			endif;
			//end			
		
			return $this->redirect()->toRoute('travel',array('action' => 'edittravel','id'=>$tadetails['ta']));	
	}
	/**
	 * Ima Expense action
	 */
	public function expenseAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
		);
		
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Accounts\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Accounts\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==8|| $this->_login_role==6|| $role==array(2,17)|| $role==array(5,6)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		$user_id=$this->_login_id;
		//echo $user_id; exit;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title' => 'Expense',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'transTable'     => $transTable,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Accounts\JournalTable::class),
		));
	}
	/**
	 *Add Expense Action
	 **/
	public function addexpenseAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Accounts\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'],-5));
			endforeach;
			$next_serial = max($pltp_no_list) + 1; 
			//echo '<pre>';print_r($next_serial);exit;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;    break;
				default: $next_dc_serial = $next_serial;       break;
			}	
			$voucher_no = $tmp_VCNo.$next_dc_serial;
			//echo '<pre>';print_r($form['currency']);exit;
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'     =>$region,
				'currency' =>$form['currency'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $voucher_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status initiated 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);exit;
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			if($result > 0){
				$location= $form['location'];
				//$activity= $form['activity']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				$reference= $form['reference'];
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' =>$location[$i],
							'against' =>0,
							'currency' =>$form['currency'],
							'rate' =>$form['rate'],
							'debit_sdr' =>($debit[$i]>0)? $form['amount_usd']:'0.000',
							'credit_sdr' => ($credit[$i]>0)? $form['amount_usd']:'0.000',
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' => $cheque_no[$i],
							'debit' => (isset($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (isset($credit[$i]))? $credit[$i]:'0.000',
							'ref_no'=> $reference[$i], 
							'type' => '1',//user inputted  data
							'status' => 2, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
						);
						//echo '<pre>';print_r($tdetailsdata);exit;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				/**ADDED FOR SDR TRANSACTION */
				$sdr_details=array(
					'transaction' => $result,
					'voucher_date' => $form['voucher_date'],
					'voucher_type' => $form['voucher_type'],
					'currency' => 2,
					'item' => $form['item'],
					'weight' => $form['weight'],
					'sdr' => $form['sdr'],
					'item_no' => $form['item1'],
					'nos' => $form['no'],
					'sdr_no' => $form['sdrno'],
					'totalsdr'=>$form['amount_usd'],
					'rate' => $form['rate'],
					'location' => $location[0],
					'status' => 1,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);  
				$this->getDefinedTable(Accounts\SdrTable::class)->save($sdr_details);
			/**END OF SDR TRANSACTION */
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Requisition successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('ima', array('action' =>'viewexpense', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Requisition");		
					return $this->redirect()->toRoute('expense');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Requisition");		
				return $this->redirect()->toRoute('expense');
			}
		}
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $role==array(2,17)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add IMA Expense',
			'todaydate'=>$today_date,
			'userlocation' =>$user_admin_location,
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'regions' => $regions,
			'items' => $this->getDefinedTable(Accounts\ItemTable::class)->getAll(),
			'currencies' => $this->getDefinedTable(Accounts\CurrencyTable::class)->getAll(),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>[172,143,243])),
		));
	}
	/* Delete Claim details action
	 */
	public function deleteclaimAction()
	{
		$this->init(); 
		foreach($this->getDefinedTable(Hr\TAClaimDetailsTable::Class)->get($this->_id) as $claimdetails);
		$result = $this->getDefinedTable(Hr\TAClaimDetailsTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete");
			endif;
			//end			
		
			return $this->redirect()->toRoute('travel',array('action' => 'editclaim','id'=>$claimdetails['claim']));	
	}
	/**
	 *  View ima expense action
	 */
	public function viewexpenseAction()
	{
		$this->init();
		$application_id = $this->_id;
		/**--Disapearing the notification--*/
		$params = explode("-", $this->_id);
		if (isset($params['1']) && $params['1'] == '1' && isset($params['2']) && $params['2'] > 0) {
			$flag = $this->getDefinedTable(Acl\NotifyTable::class)->getColumn($params['2'], 'flag'); 
				if($flag == "0") {
					$notify = array('id' => $params['2'], 'flag'=>'1','priority'=>'0');//priority red dot(==0....remove)
					$this->getDefinedTable(Acl\NotifyTable::class)->save($notify); 	
				}				
		}
		/*end--*/
		return new ViewModel(array(
		    'login_id'     =>$this->_login_id,
		    'role'          =>$this->_login_role,
			'transactionrow' => $this->getDefinedTable(Accounts\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
			'flowtransactionObj'  => $this->getDefinedTable(Administration\FlowTransactionTable::class),
			'flowactionObj'       => $this->getDefinedTable(Administration\FlowActionTable::class),   
			'login_role'          => $this->_login_role,
			'application'        => $this->getDefinedTable(Accounts\TransactionTable::class)->get($application_id),
			'sdrdetails' => $this->getDefinedTable(Accounts\SdrTable::class)->get(array('transaction' => $this->_id)),
			'currencies' => $this->getDefinedTable(Accounts\CurrencyTable::class),
			'items' => $this->getDefinedTable(Accounts\ItemTable::class),
		));
		
	} 
	/**
	 * commitTransaction action
	 **/
	public function commitexpenseAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' => 4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Accounts\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' => 4, // status committed 
					'modified' => $this->_modified,
				);	
			    $result = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data);
		    endforeach;
		}
		if($result):
			$this->flashMessenger()->addMessage("success^ Transaction Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("ima", array("action"=>"viewexpense", "id" => $this->_id));
	}
	/**---------------------------RECEIPT------------------------------------------------------------- */
	/**
	 *  ima net action
	 */
	public function netAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			$date = $form['date'];
			$voucher = $form['voucher'];
		}else{
            $month = date('m');
			$year =date('Y');
			$date =date('d');
			$voucher='';
		}	
		$data = array(
			'year' => $year,
			'month' => $month,
			'date' => $date,
			'voucher' => $voucher,
		);
		//echo '<pre>';print_r($data);exit;
    	//work to get total no. of days, accord. to year n month  date('L', strtotime($data['year']))
		if($data['month'] > 7){
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 31; break;
			default:
				$maxdate = 30; break;
		}
		}else{
			switch($data){
			case (date ('L', mktime(1,1,1,1,1, $data['year'])) && $data['month'] == 2):
				$maxdate = 29; break;
			case $data['month'] == 2:
				$maxdate = 28; break;
			case $data['month'] % 2 == 0:
				$maxdate = 30; break;
			default:
				$maxdate = 31; break;
		}
		}
		$user_id=$this->_login_id;
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role==100||$this->_login_role==99):
		    $transTable = $this->getDefinedTable(Accounts\TransactionTable::class)->getDateWiseA('voucher_date',$year,$month,$date);
		else:
		    $transTable = $this->getDefinedTable(Accounts\TransactionTable::class)->getDateWise('voucher_date',$year,$month,$date,$user_region);
		endif;
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($transTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(10000);
		$paginator->setPageRange(8);
		
		//echo $maxdate; exit;
		$user_id=$this->_login_id;
		$user_role=$this->_login_role;
		return new ViewModel(array(
			'title'         => 'IMA Net',
			'user'          =>$user_id,
			'role'          =>$user_role,
			'paginator'     => $paginator,
			'page'          => $page,
			'data'          => $data,
			'maxdate'       => $maxdate,
			'voucherObj'    =>$this->getDefinedTable(Accounts\JournalTable::class),
			'vouchers'      =>$this->getDefinedTable(Accounts\TransactionTable::class)->get(array('against'=>0)),
			
   		));
	}
	/**
	 * Add net action
	 */
	public function addnetAction()
	{
		$this->init();
		$application_id = $this->_id;
		$voucher='';
		//echo '<pre>';print_r($application_id);exit;
		$getvouceher=$this->getDefinedTable(Accounts\TransactionTable::class)->get($application_id);
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$tmp_VCNo = $loc.'-'.$prefix.$date;
			//$serial = $this->getDefinedTable("Accounts\TransactionTable")->getSerial($tmp_VCNo);
			
			$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
			
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['voucher_no'], -5));
			endforeach;
			$next_serial = max($pltp_no_list) + 1; 
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "0000".$next_serial; break;
				case 2: $next_dc_serial = "000".$next_serial;  break;
				case 3: $next_dc_serial = "00".$next_serial;   break;
				case 4: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;      break;
			}	
			$v_no = $tmp_VCNo.$next_dc_serial;
			$location=$form['location'][0];
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($location,'region');
			$getvouceher=$this->getDefinedTable(Accounts\TransactionTable::class)->get($application_id);
            foreach($getvouceher as $voucher);
			$against_vid= implode(',',$form['reference']);
			
			$data1 = array(
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'region'     =>$region,
				'against'    =>1,
				'against_vid' =>$against_vid,
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				'voucher_no' => $v_no,
				'cheque_no' => $form['cheque_no1'],
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'remark' => $form['remark'],
				'status' => 2, // status 
				'author' =>$this->_author,
				'created' =>$this->_created,  
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data1);
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			if($result > 0){
				//$flow_result = $this->flowinitiation('422', $result);
				$location= $form['location'];
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no']; 
				$debit= $form['debit'];
				$credit= $form['credit'];
				$against= $form['reference'];
				$currency= $form['currency'];
				$rate= $form['rate'];
				$amount_usd= $form['amount_usd'];
				$ref= $form['ref'];
			
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
					    /**FOR AGAINST UPDATE-*/
						 $against_st[$i]=0;
					    if($credit[$i]!='0.000'){
							$validity[$i]= $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getColumn($against[$i],'debit');
					     	$against_st[$i] = ($validity[$i] != $credit[$i]) ? '3' : 0; 
						}
						elseif($debit[$i]!='0.000'){
							$validity[$i]= $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getColumn($against[$i],'credit');
					     	$against_st[$i] = ($validity[$i] != $debit[$i]) ? '3' : 0; 
						}
						else{
							$against_st[$i]=null;
						}
						// print_r($validity[$i]);exit;
						$tdetailsdata = array(
							'transaction' => $result,
							'voucher_dates' => $form['voucher_date'],
							'voucher_types' => $form['voucher_type'],
							'location' => $location[$i],
							'activity' => $location[$i],
							'against' =>($against[$i]!=null)? $against[$i]:0,
							'rate' =>$rate[$i],
							'debit_sdr' =>($debit[$i]>0 && $currency[$i]!=1)? $amount_usd[$i]:'0.000',
							'credit_sdr' => ($credit[$i]>0 && $currency[$i]!=1)? $amount_usd[$i]:'0.000',
							'head' => $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($sub_head[$i], $column='head'),
							'sub_head' => $sub_head[$i],
							'bank_ref_type' => '',
							'cheque_no' =>(isset($cheque_no[$i]))? $cheque_no[$i]:'DFT20052024',
							'debit' => (!empty($debit[$i]))? $debit[$i]:'0.000',
							'credit' => (!empty($credit[$i]))? $credit[$i]:'0.000',
							'currency'	=> $currency[$i],
							'ref_no'=> $ref[$i], 
							'type' => '1',//user inputted  data
							'against_status' => $against_st[$i], // status against directly commit 
							'status' => 2, // status against directly commit 
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,  
						);
						//echo '<pre>';print_r($tdetailsdata);exit;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
						if($result1 <= 0):
							break;
						endif;
					endif;
				endfor;
				    $location= $form['location'];
					for($i=0; $i < sizeof($against); $i++):
						if(isset($against[$i]) && is_numeric($against[$i])):
							$deta=$this->getDefinedTable(Accounts\TransactiondetailTable::class)->getColumn($against[$i],'transaction');
							$sdr_data=$this->getDefinedTable(Accounts\SdrTable::class)->get(array('transaction'=>$deta));
							$currency= $form['currency'];
							//echo '<pre>';print_r($sdr_data);exit;
							foreach($sdr_data as $dat):
							$sdr_details=array(
								'transaction' => $result,
								'against'=>$against[$i],
								'voucher_date' => $form['voucher_date'],
								'voucher_type' => $form['voucher_type'],
								'currency' => $currency[$i],
								'item' => $dat['item'],
								'weight' => (isset($dat['weight']))? $dat['weight']:0,
								'sdr' => $dat['sdr'],
								//'totalsdr'=>(!empty($amount_usd[$i]))? $amount_usd[$i]:$dat['totalsdr'],
								//'rate' =>(!empty($rate[$i]))? $rate[$i]:$dat['rate'],
								'location' => (!empty($form['location'][$i]))? $form['location'][$i]:0,
								'status' => 1,
								'author' =>$this->_author,
								'created' =>$this->_created,
								'modified' =>$this->_modified,//
							);  
							//echo '<pre>';print_r($sdr_details);
							$sdr_details = $this->_safedataObj->rteSafe($sdr_details);
							$result1 = $this->getDefinedTable(Accounts\SdrTable::class)->save($sdr_details);
							endforeach;
						endif;
					endfor;
				//exit;
				if($result1 > 0):
					$this->_connection->commit(); // commit transaction on success
					$this->flashMessenger()->addMessage("success^ New Transaction successfully added | ".$voucher_no);
					return $this->redirect()->toRoute('ima', array('action' =>'viewnet', 'id' => $result));
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
					return $this->redirect()->toRoute('ima');
				endif;				
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
		 		$this->flashMessenger()->addMessage("Failed^ Failed to add new Transaction");		
				return $this->redirect()->toRoute('ima');
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
	    /* role=8-Western Union User
           role=6-casher*/	
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==5|| $this->_login_role==8 || $this->_login_role==6):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;//exit;
		//echo '<pre>';print_r($voucher);exit;
		$user_admin_location=$this->getDefinedTable(Administration\UsersTable::class)->get($this->_login_id,'admin_location');
		$today_date   = date('Y-m-d');
		return new ViewModel(array(
			'title'  => 'Add Net',
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			//'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'todaydate' =>$today_date,
			'userlocation' => $user_admin_location,
			'voucher'   =>$getvouceher,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.id'=>[11,105,143,153,172,243])),
			'currencies' => $this->getDefinedTable(Accounts\CurrencyTable::class)->getAll(),
		));
	}
	
	/**
	 *  edit transaction action
	 */
	public function editnetAction()
	{
		$this->init();
		$status = $this->getDefinedTable(Accounts\TransactionTable::class)->getColumn($this->_id,'status');
		if($status >= 3):
			$this->redirect()->toRoute('transaction');
		endif;
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getpost();
			//generate voucher no
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getColumn($this->_user->location, 'location_code');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getColumn($form['voucher_type'],'prefix');
			$date = date('ym',strtotime($form['voucher_date']));
			$serial = $this->getDefinedTable(Accounts\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
			preg_match('/([\d]+)/', $serial, $match );
			$serial = substr($match[0],4);
			$voucher_no = $loc.$prefix.$date.$serial;
			
			$data1 = array(
				'id' => $this->_id,
				'voucher_date' => $form['voucher_date'],
				'voucher_type' => $form['voucher_type'],
				'doc_id' => $form['doc_id'],
				'doc_type' => $form['doc_type'],
				/*'voucher_no' => $voucher_no,*/
				'voucher_amount' => str_replace( ",", "",$form['voucher_amount']),
				'cheque_no' => $form['cheque_no1'],
				'remark' => $form['remark'],
				'status' => 2, // status pending 
				'modified' =>$this->_modified,
			);			
			$data1 = $this->_safedataObj->rteSafe($data1);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
			if($result > 0){
				$tdetails_id = $form['id'];
				$location= $form['location'];
				//$activity= $form['activity'];
				$head= $form['head']; 
				$sub_head= $form['sub_head'];
				$cheque_no= $form['cheque_no1'];
				$debit= $form['debit'];
				$credit= $form['credit'];
				$delete_rows = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getNotInDtl($tdetails_id, array('transaction' => $result));
				for($i=0; $i < sizeof($location); $i++):
					if(isset($location[$i]) && is_numeric($location[$i])):
						if($tdetails_id[$i]>0):
							$tdetailsdata = array(
								'id' => $tdetails_id[$i],
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (!empty($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (!empty($credit[$i]))? $credit[$i]:'0.00',
								'debit_sdr' =>(isset($debit[$i]))? $form['amount_usd']:'0.000',
								'credit_sdr' => (isset($credit[$i]))? $form['amount_usd']:'0.000',
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'modified' =>$this->_modified,
							);
						else:
							$tdetailsdata = array(
								'transaction' => $result,
								'location' => $location[$i],
								'activity' => $location[$i],
								'head' => $head[$i],
								'sub_head' => $sub_head[$i],
								'bank_ref_type' => '',
								'cheque_no' => $cheque_no[$i],
								'debit' => (isset($debit[$i]))? $debit[$i]:'0.00',
								'credit' => (isset($credit[$i]))? $credit[$i]:'0.00',
								'debit_sdr' =>(isset($debit[$i]))? $form['amount_usd']:'0.000',
								'credit_sdr' => (isset($credit[$i]))? $form['amount_usd']:'0.000',
								'ref_no'=> '', 
								'type' => '1',//user inputted  data
								'status' => 2, // status pending 
								'author' => $this->_author,
								'created' => $this->_created,//user inputted  data
								'modified' =>$this->_modified,
							);  
						endif;
						$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
						$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);				
					endif;
				endfor;
				//deleting deleted table rows form database table
				foreach($delete_rows as $delete_row):
					$this->getDefinedTable(Accounts\TransactiondetailTable::class)->remove($delete_row['id']);
				endforeach;
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Transaction successfully updated | ".$voucher_no);
				return $this->redirect()->toRoute('transaction', array('action' =>'viewagainst', 'id' => $this->_id));
			}
			else
			{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to modify  Transaction");	
				return $this->redirect()->toRoute('transaction', array('action' =>'viewagainst', 'id' => $this->_id));
			}
		}
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99||$this->_login_role==5):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		return new ViewModel(array(
			'title'  => 'Update Against',
			'login_id'   =>$this->_login_id,
			'transactions' => $this->getDefinedTable(Accounts\TransactionTable::class)->get($this->_id),
			'tdetails' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction'=>$this->_id)),
			'activities' => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			'locations' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' => $regions,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'journals' => $this->getDefinedTable(Accounts\JournalTable::class)->getAll(),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'heads' => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
			'tdetailsObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)
		));
	}
	/**
	 * get net view
	 * 
	 **/
	public function viewnetAction(){
		$this->init();
		return new ViewModel(array(
		    'login_id'  =>$this->_login_id,
			'transactionrow' => $this->getDefinedTable(Accounts\TransactionTable::class)->get($this->_id),
			'transactiondetails' => $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction' => $this->_id)),
			'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
            'bank_ref_typeObj' => $this->getDefinedTable(Accounts\BankreftypeTable::class),
			'currencyObj' => $this->getDefinedTable(Accounts\CurrencyTable::class),
		));
	}
	/**
	 * commit addagainstcredit action
	 **/
	public function commitnetAction(){
		$this->init();
		//echo "This is my testing"; exit;
		$data = array(
			'id' => $this->_id,
			'status' =>4, // status committed 
			'modified' => $this->_modified,
		);		
		$result = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data);
		$voucher_no = $this->getDefinedTable(Accounts\TransactionTable::class)->getColumn($this->_id, 'voucher_no');
		if($result>0){
			$ids = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('transaction'=>$this->_id));
			//echo '<pre>';print_r($ids);exit;
			foreach($ids as $tasid):
				$data = array(
					'id' => $tasid['id'],
					'status' =>4, // status committed 
					'modified' => $this->_modified,
				);	
			   $results = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data);
			endforeach;
	}
		if($results):
			foreach($ids as $tasid):
				$againstID =  $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getColumn($tasid['id'], 'against');
				$creditAmount =  $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getColumn($tasid['id'], 'credit');
				$debitAmount =  $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getColumn($tasid['id'], 'debit');
				if($creditAmount!='0.00'){
					$amount=$creditAmount;
					$sum=$this->getDefinedTable(Accounts\TransactiondetailTable::class)->getsum('debit',array('against'=>$againstID));
				}
				else{
					$amount=$debitAmount;
					$sum=$this->getDefinedTable(Accounts\TransactiondetailTable::class)->getsum('credit',array('against'=>$againstID));
				}
				
				
				if($againstID!=null && $againstID!=0):
					if($tasid['against_status']==3 && $sum!=$amount):
							$against=0;
						else:
							$against=$tasid['id'];
						endif;
						$update_against = array(
							'id' => $againstID,
							'against'=>$against,
							'modified' => $this->_modified,
						);
						//echo '<pre>';print_r($update_against);
						$results1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($update_against);
				endif;
		    endforeach;
			$this->flashMessenger()->addMessage("success^  Transaction Commited Successfully | ".$voucher_no);
		endif;
		return $this->redirect()->toRoute("ima", array("action"=>"viewnet", "id" => $this->_id));
	}
	/**---------------------------IMA REPORT------------------------------------------------------------- */
	public function reportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$activity = $form['location'];
			$currency = $form['currency'];
			$head = $form['head'];
			$sub_head = $form['sub_head'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			$currency=$form['currency'];
		else:
			$location='-1';
			$activity='-1';
			$head='-1';
			$sub_head='-1';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
			$currency=1;
		endif;
		$data = array(
			'location' => $location,
			'activity' => $activity,
			'head' => $head,
			'sub_head' => $sub_head,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'currency' => $currency,
		);
		$group_id = $this->getDefinedTable(Accounts\HeadTable::class)->getColumn($head,'group');
		$class_id = $this->getDefinedTable(Accounts\GroupTable::class)->getColumn($group_id,'class');
		$role=explode(",",$this->_login_role);//Multiple Role
		$user_region= $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_login_id,'region');
		if($this->_login_role ==100|| $this->_login_role==99 || $this->_login_role==8|| $this->_login_role==6|| $role==array(2,17)|| $role==array(5,6)):
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->getAll();
		else:
		   $regions=$this->getDefinedTable(Administration\RegionTable::class)->get(array('id'=>$user_region));
		endif;
		$ViewModel =  new ViewModel(array(
			'title' => "Ledger & Sub-Ledger",
			'data' => $data,
			'region' => $regions,
			'class' => $class_id,
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactionObj' => $this->getDefinedTable(Accounts\TransactionTable::class),
			'journalObj' => $this->getDefinedTable(Accounts\JournalTable::class),
			'transactiondetailObj'=> $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'closingbalanceObj'=> $this->getDefinedTable(Accounts\ClosingbalanceTable::class),
		    //'userRoleObj'  => $this->getDefinedTable(Acl\UserroleTable::class),
			'userID' => $this->_author,
			'currencyObj'   =>$this->getDefinedTable(Accounts\CurrencyTable::class),
		));
		return $ViewModel; 
	}

	public function getexpenseAction()
	{
		$form = $this->getRequest()->getPost();
		$taid =$form['taId'];
		$talist=$this->getDefinedTable(Hr\TATable::class)->get($taid);
		$transaction=$this->getDefinedTable(Accounts\TransactionTable::class)->getColumn(array('remark'=>$taid),'voucher_amount');
		
		foreach($talist as $row):
			//$advance=$row['advance'];
			$estimated_expense=$row['estimated_expense'];
			if($row['type']==1){
			$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>203));
			}
			else{
				$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>204));
			}
				$subheadlist ="<option value=''></option>";
			foreach($sub_head as $row):
				$subheadlist .="<option value='".$row['id']."'>".$row['name']."</option>";
			endforeach;
		endforeach;
		echo json_encode(array(
			'advance' => $transaction,
			'estimated_expense' => $estimated_expense,
			'subheadlist'=>$subheadlist ,
		));
		exit;
	}
	/*get Subhead based on head */
	public function getsubheadAction()
	{
		$form = $this->getRequest()->getPost();
		$headid =$form['headId'];
		$sub_head = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>$headid));
		
		$subheads ="<option value='-1'>select</option>";
			foreach($sub_head as $row):
				$subheads .="<option value='".$row['id']."'>".$row['code'].'-'.$row['name']."</option>";
			endforeach;
		
		echo json_encode(array(
			'subheads'=>$subheads ,
		));
		exit;
	}
	/*get reference based on head */
	public function getreferenceAction()
	{
		$form = $this->getRequest()->getPost();
		$sheadid =$form['sheadId'];
		$transaction = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get(array('td.sub_head'=>$sheadid,'td.against'=>0));
		
		$reference ="<option value='0'>none</option>";
			foreach($transaction as $row):
				if($row['against']==0 || $row['against_status']==3){
					$reference .="<option value='".$row['id']."'>".$row['ref_no']."</option>";
				}
				
			endforeach;
		
		echo json_encode(array(
			'references'=>$reference ,
		));
		exit;
	}
	/*get credit and debit amount  based on sub head */
	public function getcdamountAction()
{
    $form = $this->getRequest()->getPost();
    $ref = $form['ref'];
    $transaction = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->get($ref);

    $credit = '0.00';
    $debit = '0.00';
    $totalsdr = '0.00';
    $rate = '0.00';

    foreach ($transaction as $row) {
        if ($row['debit'] > 0) {
            $credit = $row['debit'];
        } else {
            $debit = $row['credit'];
        }
		if ($row['debit_sdr'] > 0) {
            $totalsdr = $row['debit_sdr'];
        } 
		if(($row['credit_sdr'] > 0)) {
            $totalsdr = $row['credit_sdr'];
        }
        $sdr = $this->getDefinedTable(Accounts\SdrTable::class)->get(array('transaction' => $row['transaction']));
        if (!empty($sdr)) {
            foreach ($sdr as $sdrs) {
                $totalsdr = $sdrs['totalsdr'];
                $rate = $sdrs['rate'];
            }
        }
    }

    echo json_encode(array(
        'debit' => $debit,
        'credit' => $credit,
        'totalsdr' => $totalsdr,
        'rate' => $rate,
    ));
    exit;
}
}



