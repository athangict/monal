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
use Stock\Model As Stock;
use Sales\Model As Sales;
class PostageController extends AbstractActionController
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
	protected $_userloc; //location of the current user
	

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
		if(!isset($this->_login_location_type)){
			$this->_login_location_type = $this->_user->location_type; 
		}
		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
	
		if(!isset($this->_userloc)){
			$this->_userloc = $this->_user->location;  
		}
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	/*
	* Index
	*/
	public function indexAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'index',
	));
	}

	/*
	*  Postage  
	*/
	public function postageAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Postage',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->getAll(),
			'serviceObj' => $this->getDefinedTable(Sales\ServiceTable::class),
			'subserviceObj' => $this->getDefinedTable(Sales\SubserviceTable::class),
			'countryObj' => $this->getDefinedTable(Administration\CountryTable::class),
			'cityObj' => $this->getDefinedTable(Administration\CityTable::class),
			
		));
	}
	/*
	*  Postage  
	*/
	public function viewpostageAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'View Postage',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id),
			'bulk' => $this->getDefinedTable(Sales\PostageTable::class),
			'serviceObj' => $this->getDefinedTable(Sales\ServiceTable::class),
			'subserviceObj' => $this->getDefinedTable(Sales\SubserviceTable::class),
			'countryObj' => $this->getDefinedTable(Administration\CountryTable::class),
			'cityObj' => $this->getDefinedTable(Administration\CityTable::class),
			'postofficeObj' => $this->getDefinedTable(Sales\PostofficeTable::class),
			'bulkbook' => $this->getDefinedTable(Sales\PostageTable::class)->get(array('bulkid'=>$this->_id)),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			
		));
	}
	/*
	*  View individual even if bulk booked  
	*/
	public function viewindividulpostAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'View Postage',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id),
			'serviceObj' => $this->getDefinedTable(Sales\ServiceTable::class),
			'subserviceObj' => $this->getDefinedTable(Sales\SubserviceTable::class),
			'countryObj' => $this->getDefinedTable(Administration\CountryTable::class),
			'cityObj' => $this->getDefinedTable(Administration\CityTable::class),
			'postofficeObj' => $this->getDefinedTable(Sales\PostofficeTable::class),
			'bulkbook' => $this->getDefinedTable(Sales\PostageTable::class)->get(array('bulkid'=>$this->_id)),
			
			
		));
	}
	/**
	 * Add Postage Action
	 */
	public function addpostageAction()
	{
		$this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
        $admin_loc_array = explode(',',$admin_locs);
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			
			if(empty($form['country'])):$country=0;else:$country=$form['country'];endif;
			if(empty($form['city'])):$city=0;else:$city=$form['city'];endif;
			if($form['btn']==1):$advice_of_delvery=$form['advice_delivery_fee'];else:$advice_of_delvery=0;endif;
			if(empty($form['btn1'])):$cheque_cash=0;else:$cheque_cash=$form['btn1'];endif;
			if(empty($form['prepaid_mode'])):$prepaid_mode=0;else:$prepaid_mode=$form['prepaid_mode'];endif;
			if(empty($form['number'])):$mo_number=0;else:$mo_number=$form['number'];endif;
			if(empty($form['from_mobile_number'])):$from_mobile_number=0;else:$from_mobile_number=$form['from_mobile_number'];endif;
			if(empty($form['to_mobile_number'])):$to_mobile_number=0;else:$to_mobile_number=$form['to_mobile_number'];endif;
			if(empty($form['insurance_amt'])):$insurance_amt=0;else:$insurance_amt=$form['insurance_amt'];endif;
			if(empty($form['po_name'])):$po_name=0;else:$po_name=$form['po_name'];endif;
			if(empty($form['post_code'])):$post_code=0;else:$post_code=$form['post_code'];endif;
			if(empty($form['zip_code'])):$zip_code=0;else:$zip_code=$form['zip_code'];endif;
			$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'location');
			if(empty($form['ref_no'])):$ref_no=0;else:$ref_no=$form['ref_no'];endif;
			if(empty($form['bulk_no'])):$bulk_no=0;else:$bulk_no=$form['bulk_no'];endif;
		//$articlePresent = $this->getDefinedTable(Sales\PostageTable::class)->isPresent('article_no', $form['article_no']);
		$articlePresent = $this->getDefinedTable(Sales\PostageTable::class)->get(array('article_no'=>$form['article_no']));
		foreach($articlePresent as $articlePresents);
		if(sizeof($articlePresent)>0 && $articlePresents['status']!=5):return $this->redirect()->toRoute('postage', array('action'=>'addpostage'));else:
			$data = array(
					'service' 	  	=> $form['service'],
					'country' 	  	=> $country,
					'city' 		  	=> $city,
					'scope'       	=> $form['scope'],
					'article_no'  	=> $form['article_no'],
					'prepaid_mode'	=> $prepaid_mode,
					'weight'     	=> $form['weight'],
					'date'     		=>date('Y-m-d'),
					//'money_value'         => $form['money_value'],
					//'mo_cheque_cash'      => $cheque_cash,
					//'mo_number'           => $mo_number,
					//'mo_cheque_date'      => $form['cheque_date'],
					//'mo_bank'             => $form['bank'],
					//'mo_transmission_fee' => $form['transmission_fee'],
					'from_name'     	 => strtoupper($form['from_name']),
					'from_address'     	 => strtoupper($form['from_address']),
					'from_email_address' => $form['from_email_address'],
					'from_mobile_number' => $from_mobile_number,
					'to_name'     		 => strtoupper($form['to_name']),
					'to_address'     	 => strtoupper($form['to_address']),
					'po_name'     		 => $po_name,
					'post_code'     	 => $post_code,
					'zip_code'     	 	 => $zip_code,
					'to_mobile_number'   => $to_mobile_number,
					'bulk_booking'     	 => $form['btn3'],
					'postage_rate'     	 => $form['postage_rate'],
					'total_amt'     	 => $form['total_amt'],
					'net_amt'     		 => $form['total_amt'],
					'insurance_amount' 	 => $insurance_amt,
					'registration_fee' 	 => $form['registration_fee'],
					'advice_delivery_fee'=> $advice_of_delvery,
					'ad'				 => $form['btn'],
					'pm'				 => $form['btn1'],
					'in'				 => $form['btn2'],
					'bb'				 => $form['btn3'],
					'cash'				 => 0,
					'journal_no'		 => 0,
					'phone'		 		 => 0,
					'party'		 		 => 0,
					'content'		 	 => $form['content'],
					'location'			 => $location,
					'bulk_no'		     => $bulk_no,
					'merchandise'		 => $form['merchandise'],
					'status'			 => 2,
					'author' 			 =>$this->_author,
					'created' 			 =>$this->_created,
					'modified' 			 =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data =  $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$postageresult = $this->getDefinedTable(Sales\PostageTable::class)->save($data);
			endif;
			
				if($postageresult ):
					$this->_connection->commit(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("success^ successfully added new data");
				else:
					$this->_connection->rollback(); // rollback transaction over failure
					$this->flashMessenger()->addMessage("notice^ Failed to add new data");
				endif;
				if($form['btn3'] == 1):
				$bulkid = array(
					'id' 	=> $postageresult,
					'bulkid' => $postageresult,
					'status'=>2,
				);
				$bulkid =  $this->_safedataObj->rteSafe($bulkid);
				$resultbulkid = $this->getDefinedTable(Sales\PostageTable::class)->save($bulkid);
				if($postageresult > 0):
					$this->flashMessenger()->addMessage("success^ successfully added new data");
				else:
					$this->flashMessenger()->addMessage("notice^ Failed to add new data");
				endif;
			endif;
			if($form['btn3']==1):
				return $this->redirect()->toRoute('postage', array('action'=>'addpostagebulk','id'=>$postageresult));
			else:
				return $this->redirect()->toRoute('postage', array('action'=>'viewpostage','id'=>$postageresult));
			endif;
		}
		return new ViewModel(array(
			'title' => 'Add Postage',
			'subservicetarriff' => $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get($this->_id),
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
			'country' => $this->getDefinedTable(Administration\CountryTable::class)->getAll(),
			'poffice' => $this->getDefinedTable(Sales\PostofficeTable::class)->getAll(),
			'party' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.location'=>$admin_loc_array,'p.role'=>[11,12,13,14,15,16,17,18,19])),
		));
	}	
	
	
	/**
	 * Add Postage bulk Action
	 */
	public function addpostagebulkAction()
	{
		$this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
        $admin_loc_array = explode(',',$admin_locs);
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			if(empty($form['country'])):$country=0;else:$country=$form['country'];endif;
			if(empty($form['city'])):$city=0;else:$city=$form['city'];endif;
			if($form['btn']==1):$advice_of_delvery=$form['advice_delivery_fee'];else:$advice_of_delvery=0;endif;
			if(empty($form['btn1'])):$cheque_cash=0;else:$cheque_cash=$form['btn1'];endif;
			if(empty($form['prepaid_mode'])):$prepaid_mode=0;else:$prepaid_mode=$form['prepaid_mode'];endif;
			if(empty($form['number'])):$mo_number=0;else:$mo_number=$form['number'];endif;
			if(empty($form['from_mobile_number'])):$from_mobile_number=0;else:$from_mobile_number=$form['from_mobile_number'];endif;
			if(empty($form['to_mobile_number'])):$to_mobile_number=0;else:$to_mobile_number=$form['to_mobile_number'];endif;
			if(empty($form['insurance_amt'])):$insurance_amt=0;else:$insurance_amt=$form['insurance_amt'];endif;
			if(empty($form['po_name'])):$po_name=0;else:$po_name=$form['po_name'];endif;
			if(empty($form['post_code'])):$post_code=0;else:$post_code=$form['post_code'];endif;
			if(empty($form['zip_code'])):$zip_code=0;else:$zip_code=$form['zip_code'];endif;
			$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'location');
			$postageid= $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id);
			if(empty($form['cash'])):$cash=0;else:$cash=$form['cash'];endif;
			if(empty($form['phone'])):$phone=0;else:$phone=$form['phone'];endif;
			if(empty($form['party'])):$party=0;else:$party=$form['party'];endif;
			$articlePresent = $this->getDefinedTable(Sales\PostageTable::class)->get(array('article_no'=>$form['article_no'],'status'=>4));
			if(sizeof($articlePresent)>0):return $this->redirect()->toRoute('postage', array('action'=>'addpostage'));endif;
			$data = array( 
					'service' 	  	=> $form['service'],
					'country' 	  	=> $country,
					'city' 		  	=> $city,
					'scope'       	=> $form['scope'],
					'article_no'  	=> $form['article_no'],
					'prepaid_mode'	=> $prepaid_mode,
					'weight'     	=> $form['weight'],
					'date'     		=>date('Y-m-d'),
					//'money_value'     => $form['money_value'],
					//'mo_cheque_cash'     => $cheque_cash,
					//'mo_number'     => $mo_number,
					//'mo_cheque_date'     => $form['cheque_date'],
					//'mo_bank'     => $form['bank'],
					//'mo_transmission_fee'     => $form['transmission_fee'],
					'from_name'      => strtoupper($form['from_name']),
					'from_address'     	 => strtoupper($form['from_address']),
					'from_email_address' => $form['from_email_address'],
					'from_mobile_number' => $from_mobile_number,
					'to_name'     		 => strtoupper($form['to_name']),
					'to_address'     	 => strtoupper($form['to_address']),
					'po_name'     		 => $po_name,
					'post_code'     	 => $post_code,
					'zip_code'     	 	 => $zip_code,
					'to_mobile_number'   => $to_mobile_number,
					'bulk_booking'     	 => $form['btn3'],
					'postage_rate'     	 => $form['postage_rate'],
					'total_amt'     	 => $form['total_amt'],
					'net_amt'     		 => $form['total_amt'],
					'insurance_amount' 	 => $insurance_amt,
					'registration_fee' 	 => $form['registration_fee'],
					'advice_delivery_fee'=> $advice_of_delvery,
					'ad'				 => $form['btn'],
					'pm'				 => $form['btn1'],
					'in'				 => $form['btn2'],
					'bb'				 => $form['btn3'],
					'bulkid'		     => $form['bulkid'],
					'cash'				 => $cash,
					'journal_no'		 => $form['journal_no'],
					'phone'		 		 => $phone,
					'party'		 		 => $party,
					'content'		 	 => $form['content'],
					'location'			 => $location,
					'bulk_no'		     => $form['bulk_no'],
					'merchandise'		 => $form['merchandise'],
					'status'			 => 2,
					'author' 			 =>$this->_author,
					'created' 			 =>$this->_created,
					'modified' 			 =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data =  $this->_safedataObj->rteSafe($data);
			$postageresult = $this->getDefinedTable(Sales\PostageTable::class)->save($data);
		if($postageresult > 0):
				$this->flashMessenger()->addMessage("success^ successfully added new data");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			if($form['print']==1):
				return $this->redirect()->toRoute('postage', array('action'=>'viewpostage','id'=>$form['bulkid']));
			else:
				return $this->redirect()->toRoute('postage', array('action'=>'addpostagebulk','id'=>$form['bulkid']));
			endif;
		}
		return new ViewModel(array(
			'title'   => 'Commit Bulk Booking',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id),
			'postageObj' => $this->getDefinedTable(Sales\PostageTable::class),
			'bulk_no' => $this->getDefinedTable(Sales\PostageTable::class)->getMax('id','id'),
			'scope'   => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'country' => $this->getDefinedTable(Administration\CountryTable::class)->getAll(),
			'poffice' => $this->getDefinedTable(Sales\PostofficeTable::class)->getAll(),
			//'party' => $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('head'=>[76,79,90,91,92,93,94,95,96,97,112,118])),
			
		));
	}	
	/**
	 * Add Postage bulk Action
	 */
	public function commitbulkAction()
	{
		$this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
        $admin_loc_array = explode(',',$admin_locs);
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$postageresult = $this->_id;
			$bulk=$this->getDefinedTable(Sales\PostageTable::class)->getColumn($this->_id,'bulkid');
			if(!empty($bulk)){
				$postage = $this->getDefinedTable(Sales\PostageTable::class)->get(array('bulkid'=>$this->_id));
			}
			else{
				$postage = $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id);
			}
		/*	foreach($postage as $postages):
				echo '<pre>';print_r($postages);
			endforeach;exit;*/
			$location = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'location');
			$region = $this->getDefinedTable(Administration\UsersTable::class)->getColumn(array('id'=>$this->_author),'region');
			
			if(empty($form['ref_no'])):$ref_no=0;else:$ref_no=$form['ref_no'];endif;
			//generate voucher no to be pushed into Transaction Table
			foreach($postage as $postages):
				$sersubhead = $this->getDefinedTable(Sales\ServiceTable::class)->getColumn(array('id'=>$postages['service']),'sub_head');
				$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user->location, 'prefix');
				$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(8,'prefix');
				$date = date('ym',strtotime(date('Y-m-d')));
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
				if($form['cash']==0):
					if($location==2){
						$bankacc = $this->getDefinedTable(Accounts\BankaccountTable::class)->get(69);
					}
					else{
						$bankacc = $this->getDefinedTable(Accounts\BankaccountTable::class)->get(array('ba.location'=>$location));
					}
					$subhead=0;
					foreach($bankacc as $row):
						$cash_subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id'=>$row['id']));
						//echo '<pre>';print_r($cash_subhead);	
						foreach($cash_subhead  as $cash_subheads):
							if(($cash_subheads['head_id']==24)||($cash_subheads['head_id']==25)||($cash_subheads['head_id']==26)||($cash_subheads['head_id']==27)||($cash_subheads['head_id']==28)){
								$subhead=$cash_subheads['id'];	
								$head=$cash_subheads['head_id'];	
							}
						endforeach;	
					endforeach;
				elseif($form['cash']==1):/**If paid deposited into Bank Account  */
					$cashacc = $this->getDefinedTable(Accounts\CashaccountTable::class)->getCash(array('ca.location'=>$location));
					$subhead=0;
					$head=0;
					foreach($cashacc as $row):
						$cash_subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('ref_id'=>$row['id']));
						foreach($cash_subhead  as $cash_subheads):
							if(($cash_subheads['head_id']==43)||($cash_subheads['head_id']==47)||($cash_subheads['head_id']==51)||($cash_subheads['head_id']==55)){
								$subhead=$cash_subheads['id'];	
								$head=$cash_subheads['head_id'];	
							}
							
						endforeach;	
					endforeach;
				else:
				$ref=$form['party'];
				$subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$ref,'type'=>2),'id');
				$head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('id'=>$subhead),'head');
				$location=$this->getDefinedTable(Accounts\PartyTable::class)->getColumn(array('id'=>$ref),'location');
				endif;
				$data = array(
					'id' 	             => $postages['id'],
					'cash'				 => $form['cash'],
					'party'				 => $subhead,
					'journal_no'		 => $form['journal_no'],
					'phone'		 		 => $form['phone'],
					'status'			 => 4,
				);
				$data =  $this->_safedataObj->rteSafe($data);
				//***Transaction begins here***//
				$this->_connection->beginTransaction();
				$result = $this->getDefinedTable(Sales\PostageTable::class)->save($data);
				if($result):
					$data2 = array(
						'voucher_date' 	  	=> $postages['date'],
						'voucher_type' 	  	=> 8,
						'region' 	  		=> $region,
						'voucher_no' 	  	=> $voucher_no,
						'voucher_amount' 	=> $postages['total_amt'],
						'status' 	  		=> 4,
						'doc_id' 	  		=> "service",
						'doc_type' 	  		=> " ",
						'remark' 	  		=> $postages['article_no'],
						'author' 			 =>$this->_author,
						'created' 			 =>$this->_created,
						'modified' 			 =>$this->_modified,
					);
					$data2 =  $this->_safedataObj->rteSafe($data2);
					$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data2);
					$data3 = array(
						'transaction' 	=> $result2,
						'voucher_dates' => $data2['voucher_date'],
						'voucher_types' 	=> 8,
						'location' 	  	=> $this->_user->location,
						'head' 	  		=> '152',
						'sub_head' 	  	=> $sersubhead,
						'activity'		=>$this->_user->location,
						'debit' 	  	=> 0,
						'credit' 	  	=> $data2['voucher_amount'],
						'ref_no' 	  	=> 0,
						'status' 	  		=> 4,
						'against' 	  	=> 0,
						'author' 			 =>$this->_author,
						'created' 			 =>$this->_created,
						'modified' 			 =>$this->_modified,
					);
					$data3 =  $this->_safedataObj->rteSafe($data3);
					$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data3);
					
						
						$data4 = array(
							'transaction' 	=> $result2,
							'voucher_dates' => $data2['voucher_date'],
							'voucher_types' 	=> 8,
							'location' 	  	=>$this->_user->location,
							'activity'		=>$this->_user->location,
							'head' 	  		=> $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn($subhead,'head'),
							'sub_head' 	  	=> $subhead,
							'debit' 	  	=> $data2['voucher_amount'],
							'credit' 	  	=> 0,
							'status' 	  	=> 4,
							'against' 	  	=> 0,
							'bank_trans_journal'=>$form['journal_no'],
							'author' 		=>$this->_author,
							'created' 		=>$this->_created,
							'modified' 		=>$this->_modified,
						);
					$data4 =  $this->_safedataObj->rteSafe($data4);
					$result4 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($data4);
					/**Push the transaction id from Transaction Table */
				$data5 = array(
					'id' 	             => $postages['id'],
					'transaction_id'     => $result2,
					
				);
				$data5 =  $this->_safedataObj->rteSafe($data5);
				$result5 = $this->getDefinedTable(Sales\PostageTable::class)->save($data5);
		
				$this->_connection->commit(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("success^ successfully added new data");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
		endforeach;
		return $this->redirect()->toRoute('postage', array('action'=>'viewpostage','id'=>$postageresult));
			
		}
		$ViewModel = new ViewModel(array(
			'title'   => 'Add Postage',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id),
			'postageObj' => $this->getDefinedTable(Sales\PostageTable::class),
			'bulk_no' => $this->getDefinedTable(Sales\PostageTable::class)->getMax('id','id'),
			'scope'   => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'country' => $this->getDefinedTable(Administration\CountryTable::class)->getAll(),
			'poffice' => $this->getDefinedTable(Sales\PostofficeTable::class)->getAll(),
			'party' => $this->getDefinedTable(Accounts\PartyTable::class)->getforCB(array('p.location'=>$admin_loc_array,'p.role'=>[11,12,13,14,15,16,17,18,19])),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * Delete Postage Action
	 */
	public function deletepostageAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id' => $this->_id,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$result = $this->getDefinedTable(Sales\PostageTable::class)->remove($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted new data");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  data");
			endif;
			return $this->redirect()->toRoute('postage', array('action'=>'postage'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Postage',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id),
			
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Delete Postage Action
	 */
	public function deletebulkbookingAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$bulk_booking = $form['bulk_booking'];
			$bulk_ids = $this->getDefinedTable(Sales\PostageTable::class)->get(array('bulk_booking'=>$form['bulk_booking']));
			print_r($bulk_ids);exit;
			foreach($bulk_ids as $bulk_id):
			$data = array(
					'id' => $bulk_id[''],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			//print_r($data);exit;
			$result = $this->getDefinedTable(Sales\PostageTable::class)->remove($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted new data");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  data");
			endif;
		endforeach;
			return $this->redirect()->toRoute('postage', array('action'=>'postage'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Postage',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	* Service Sales  Report 
	*/
	public function servicesalereportAction()
	{ 
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
			$scope = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$service = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$location = (sizeof($array_id)>1)?$array_id[2]:'-1';
			$userlocreport = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$scope       = $form['scope'];
				$service     = $form['service'];
				$status      = $form['status'];
				$location    = $form['location'];
				$start_date = $form['start_date'];
				$end_date   = $form['end_date'];
			}else{
				$scope ='-1';
				$service = '-1';
				$status = '-1';
				$location = $userlocreport;
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
			}

			$data = array(
				'scope'    => $scope,
				'service'  => $service,
				'status'  => $status,
				'location'  => $location,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
			//echo'<pre>';print_r($data);exit;
			if($this->_login_role ==99 || $this->_login_role ==100 ):
			    $userloc = $this->getDefinedTable(Administration\LocationTable::class)->getAll();
			else:
			    $userloc = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			endif;
			
			//echo'<pre>';print_r($userloc);exit;
			$paginator = $this->getDefinedTable(Sales\PostageTable::class)->getServiceSales($data,$start_date,$end_date);
			
			
			return new ViewModel(array(
				'title' => 'Service Tarriff',
				'paginator'       => $paginator,
				'data'            => $data,
				'serviceObj'      => $this->getDefinedTable(Sales\ServiceTable::class),
				'scopeObj'        => $this->getDefinedTable(Sales\ScopeTable::class),
				'countryObj'      => $this->getDefinedTable(Administration\CountryTable::class),
				'cityObj'         => $this->getDefinedTable(Administration\CityTable::class),
				'userLoc'         => $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author),
				'locations'       => $this->getDefinedTable(Administration\LocationTable::class)->getlocation($userloc),
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj'       => $this->getDefinedTable(Acl\StatusTable::class),
			)); 
		} 

		
	}
	/**
	 * cancel service sales Action
	 */ 
	public function cancelservicesalesAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$postdata = array(
					'id' => $form['post_id'],
					'status' => 5,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$result = $this->getDefinedTable(Sales\PostageTable::class)->save($postdata);
			$transaction_id = $this->getDefinedTable(Sales\PostageTable::class)->getColumn($form['post_id'],'transaction_id');
			/**Delete from Transaction Table */
			$transac_data = array(
				'id' => $transaction_id,
			);
			$result2 = $this->getDefinedTable(Accounts\TransactionTable::class)->remove($transac_data);
			$transactiondetails_id = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getTransaction(array('transaction'=>$transaction_id));
			/**Delete from Transaction Details Table */	
			foreach($transactiondetails_id as $transactiondetails_ids):
				$transacdtls_data = array(
					'id'=>$transactiondetails_ids['id'],
					'transaction' => $transaction_id,
				);
				$result3 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->remove($transacdtls_data);
			endforeach;
			if($result3 > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted the transactions");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  the transactions");
			endif;
			return $this->redirect()->toRoute('postage', array('action'=>'servicesalereport'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Postage',
			'postage' => $this->getDefinedTable(Sales\PostageTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	* Service Sales  Report 
	*/
	public function postreportAction()
	{
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
			$scope = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$service = (sizeof($array_id)>1)?$array_id[1]:'-1';
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$scope       = $form['scope'];
				$service     = $form['service'];
				$user     	= $form['user'];
				$start_date = $form['start_date'];
				$end_date   = $form['end_date'];
				$location   = $form['location'];
			}else{
				$scope ='-1';
				$service = '-1';
				$user = '-1';
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
				$location='-1';
			}

			$data = array(
				'scope'    => $scope,
				'service'  => $service,
				'user'  => $user,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'location'	=>$location,
			);
			$userloc = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			$serviceTarriffTable = $this->getDefinedTable(Sales\PostageTable::class)->getServiceReportByUsers($data,$start_date,$end_date,array('status'=>4));
			$bankSum=$this->getDefinedTable(Sales\PostageTable::class)->getSum('total_amt',$data,$start_date,$end_date,array('cash'=>0));
			$cashSum=$this->getDefinedTable(Sales\PostageTable::class)->getSum('total_amt',$data,$start_date,$end_date,array('cash'=>1));
			$creditSum=$this->getDefinedTable(Sales\PostageTable::class)->getSum('total_amt',$data,$start_date,$end_date,array('cash'=>2));
			$total=$this->getDefinedTable(Sales\PostageTable::class)->getSum('total_amt',$data,$start_date,$end_date);
			//echo'<pre>';print_r($bankSum);exit;
			
			
			return new ViewModel(array(
				'title' => 'Service Tarriff',
				'paginator'       => $serviceTarriffTable,
				'data'            => $data,
				'bankSum'            => $bankSum,
				'cashSum'            => $cashSum,
				'creditSum'          => $creditSum,
				'total'          => $total,
				'serviceObj' => $this->getDefinedTable(Sales\ServiceTable::class),
				'scopeObj' => $this->getDefinedTable(Sales\ScopeTable::class),
				'countryObj' => $this->getDefinedTable(Administration\CountryTable::class),
				'cityObj' => $this->getDefinedTable(Administration\CityTable::class),
				'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj' => $this->getDefinedTable(Acl\StatusTable::class),
				'postageObj' => $this->getDefinedTable(Sales\PostageTable::class),
			)); 
		} 

		
	}
	/*
	* Service Sales  Report 
	*/
	public function invoicedueAction()
	{
		{	
			$this->init();
			$userloc = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$head       = $form['head'];
				$subhead     = $form['subhead'];
				$start_date = $form['start_date'];
				$end_date   = $form['end_date'];
				$location   = $form['location'];
			}else{
				$head ='-1';
				$subhead = '-1';
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
				$location=$userloc;
			}

			$data = array(
				'head'    => $head,
				'subhead'  => $subhead,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'location'	=>$location,
			);
			
			$serviceTarriffTable = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getInvoiceDue($data,$start_date,$end_date,array('status'=>4));
			
			return new ViewModel(array(
				'title' => 'Invoice for due',
				'paginator'       => $serviceTarriffTable,
				'data'            => $data,
				'serviceObj' => $this->getDefinedTable(Sales\ServiceTable::class),
				'head' => $this->getDefinedTable(Accounts\HeadTable::class)->getAll(),
				'subhead' => $this->getDefinedTable(Accounts\SubheadTable::class)->getAll(),
				'scopeObj' => $this->getDefinedTable(Sales\ScopeTable::class),
				'countryObj' => $this->getDefinedTable(Administration\CountryTable::class),
				'cityObj' => $this->getDefinedTable(Administration\CityTable::class),
				'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj' => $this->getDefinedTable(Acl\StatusTable::class),
				'postageObj' => $this->getDefinedTable(Sales\PostageTable::class),
				'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
				'voucherObj' => $this->getDefinedTable(Accounts\JournalTable::class),
				'trandtlObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
				'party' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>[11,12,13,14,15,16,17,18,19])),
			)); 
		} 

		
	}
	/**
	 * Get Postage Rate
	 */
	public function getpostagerateAction()
	{		
		$form = $this->getRequest()->getPost();
		$subserviceId =$form['service'];
		$packageWeight=$form['weight'];
		$subservice = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get(array('service'=>$subserviceId));
		foreach($subservice as $subservices);
		if(count($this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id'])))>0):
			$fixedslabs = $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
			$maxFixedId= $this->getDefinedTable(Sales\FixedslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
			$maxFixedSlab = $this->getDefinedTable(Sales\FixedslabTable::class)->getColumn(array('id'=>$maxFixedId),'to');
		endif;
		if(count($this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id'])))>0):
			$firstpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMin(array('tarrif_for_services_id'=>$subservices['id']),'id');
			$lastpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
			$firstslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$firstpropotionateslab),'upto');
			$lastslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$lastpropotionateslab),'upto');
			$propotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
		endif;
		$rate = 0; 
		$rate2 = 0; // Variable to store the final rate
		//calculation from fixed slab
		if($packageWeight<$maxFixedSlab):
			foreach ($fixedslabs as $fixedSlab) {
				if ($packageWeight >= $fixedSlab['from'] && $packageWeight <= $fixedSlab['to']) {
					$rate = $fixedSlab['rate'];
					break;
				}
			}
		else:
			foreach ($fixedslabs as $fixedSlab) {
				if ($packageWeight >= $fixedSlab['from'] && $packageWeight >= $fixedSlab['to']) {
					$rate = $fixedSlab['rate'];
					break;
				}
			}
		endif;
		if($packageWeight>$maxFixedSlab){
			//Calculation from the Array[0] in the proptionate Slab
			$slab1 = $propotionateslab[0];
			if($packageWeight>$firstslab_weightlimit):
				$remainingWeight=$firstslab_weightlimit-$maxFixedSlab;
				$additionalRate = ceil($remainingWeight / $slab1['for_every']) * $slab1['rate'];
				//print_r($additionalRate);
				$rate2 += $additionalRate;
				$reamiainingWeightFromFirstSlab=$packageWeight-$slab1['upto'];
			else:
				$remainingWeight=$packageWeight-$maxFixedSlab;
				$additionalRate = ceil($remainingWeight / $slab1['for_every']) * $slab1['rate'];
				$rate2 += $additionalRate;
				$reamiainingWeightFromFirstSlab=0;
			endif;
			//print_r($rate2);
			//Calculation for rest of the Array[$i] in the proptionate Slab
			$count = count($propotionateslab);
			//print_r($count);
			for ($i = 1; $i <= $count; $i++) {
			if ($reamiainingWeightFromFirstSlab <= 0) :
				$rate2=$rate2;
			elseif($reamiainingWeightFromFirstSlab > 0 && $reamiainingWeightFromFirstSlab <=$lastslab_weightlimit):
				
				$slab = $propotionateslab[$i];
				if($reamiainingWeightFromFirstSlab > 0):
					if ($reamiainingWeightFromFirstSlab <= $slab['upto']) {
						$additionalRate = ceil($reamiainingWeightFromFirstSlab / $slab['for_every']) * $slab['rate'];
					$rate2 += $additionalRate;
					break;
				} else {
					
					$additionalWeight = $reamiainingWeighreamiainingWeightFromFirstSlabtFromFirstSlab - $slab['upto'];
					$additionalRate = ceil($slab['upto'] / $slab['for_every']) * $slab['rate'];
					$rate2 += $additionalRate;
					$reamiainingWeightFromFirstSlab = $additionalWeight;
				}
				endif;
			else:
				$rate2=0;
			endif;
		}
   
	}
	
	$postage_rate=$rate+$rate2;
	echo json_encode(array(
				'postage_rate' => $postage_rate,
		));
		exit;
	}	
	/**
	 * Get Postage Rate
	 */
	public function getpostagerate1Action()
	{		
		$form = $this->getRequest()->getPost();
		$subserviceId =$form['service'];
		$packageWeight=$form['weight'];
		$country=$form['country'];
		$city=$form['city'];
		$subservice = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get(array('service'=>$subserviceId,'country'=>$country,'city'=>$city));
		if(!empty($subservice)):
			foreach($subservice as $subservices);
			if(count($this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id'])))>0):
				$fixedslabs = $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
				$maxFixedId= $this->getDefinedTable(Sales\FixedslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
				$maxFixedSlab = $this->getDefinedTable(Sales\FixedslabTable::class)->getColumn(array('id'=>$maxFixedId),'to');
			endif;
			if(count($this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id'])))>0):
				$firstpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMin(array('tarrif_for_services_id'=>$subservices['id']),'id');
				$lastpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
				$firstslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$firstpropotionateslab),'upto');
				$lastslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$lastpropotionateslab),'upto');
				$propotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
			endif;
			$rate = 0; 
			$rate2 = 0; // Variable to store the final rate
			//calculation from fixed slab
			if($packageWeight<$maxFixedSlab):
					foreach ($fixedslabs as $fixedSlab) {
						if ($packageWeight >= $fixedSlab['from'] && $packageWeight <= $fixedSlab['to']) {
							$rate = $fixedSlab['rate'];
							break;
						}
					}
				else:
					foreach ($fixedslabs as $fixedSlab) {
						if ($packageWeight >= $fixedSlab['from'] && $packageWeight >= $fixedSlab['to']) {
							$rate = $fixedSlab['rate'];
							break;
						}
					}
				endif;
			if($packageWeight>$maxFixedSlab){	
				//Calculation from the Array[0] in the proptionate Slab
				$slab1 = $propotionateslab[0];
				if($packageWeight>$firstslab_weightlimit):
					$remainingWeight=$firstslab_weightlimit-$maxFixedSlab;
					$additionalRate = ceil($remainingWeight / $slab1['for_every']) * $slab1['rate'];
					$rate2 += $additionalRate;
					$reamiainingWeightFromFirstSlab=$packageWeight-$slab1['upto'];
				else:
					$remainingWeight=$packageWeight-$maxFixedSlab;
					$additionalRate = ceil($remainingWeight / $slab1['for_every']) * $slab1['rate'];
					$rate2 += $additionalRate;
					$reamiainingWeightFromFirstSlab=0;
				endif;
				//Calculation for rest of the Array[$i] in the proptionate Slab
				$count = count($propotionateslab);
				for ($i = 1; $i < $count; $i++) {
				if ($reamiainingWeightFromFirstSlab <= 0) :
						break; // No more weight left to calculate rate
				elseif($reamiainingWeightFromFirstSlab > 0 && $reamiainingWeightFromFirstSlab <=$lastslab_weightlimit):
					
					$slab = $propotionateslab[$i];

					if($reamiainingWeightFromFirstSlab > 0):
						if ($reamiainingWeightFromFirstSlab <= $slab['upto']) {
							$additionalRate = ceil($reamiainingWeightFromFirstSlab / $slab['for_every']) * $slab['rate'];
						$rate2 += $additionalRate;
						break;
					} else {
						
						$additionalWeight = $reamiainingWeighreamiainingWeightFromFirstSlabtFromFirstSlab - $slab['upto'];
						$additionalRate = ceil($slab['upto'] / $slab['for_every']) * $slab['rate'];
						$rate2 += $additionalRate;
						$reamiainingWeightFromFirstSlab = $additionalWeight;
					}
					endif;
				else:
					$rate2=0;
				endif;
	
				}
			}
		$postage_rate=$rate+$rate2;
	else:$postage_rate=0;
	endif;
		
	echo json_encode(array(
				'postage_rate' => $postage_rate,
		));
		exit;
	}	
	/**
	 * Get Postage Rate
 *
	public function getpostagerate2Action()
	{		
		$form = $this->getRequest()->getPost();
		$subserviceId = $form['service'];
		$packageWeight=$form['weight'];
		$country=$form['country'];
		$city=$form['city'];
		$subservice = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get(array('service'=>$subserviceId,'country'=>$country,'city'=>$city));
		if(!empty($subservice)):
			foreach($subservice as $subservices);
			$fixedslabs = $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
			$maxFixedId= $this->getDefinedTable(Sales\FixedslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
			$maxFixedSlab = $this->getDefinedTable(Sales\FixedslabTable::class)->getColumn(array('id'=>$maxFixedId),'to');
			$firstpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMin(array('tarrif_for_services_id'=>$subservices['id']),'id');
			$lastpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
			$firstslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$firstpropotionateslab),'upto');
			$lastslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$lastpropotionateslab),'upto');
			$propotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
			$rate = 0; 
			$rate2 = 0; // Variable to store the final rate
			//calculation from fixed slab
			if($packageWeight<$maxFixedSlab):
					foreach ($fixedslabs as $fixedSlab) {
						if ($packageWeight >= $fixedSlab['from'] && $packageWeight <= $fixedSlab['to']) {
							$rate = $fixedSlab['rate'];
							break;
						}
					}
				else:
					foreach ($fixedslabs as $fixedSlab) {
						if ($packageWeight >= $fixedSlab['from'] && $packageWeight >= $fixedSlab['to']) {
							$rate = $fixedSlab['rate'];
							break;
						}
					}
				endif;
			if($packageWeight>$maxFixedSlab){
				//Calculation from the Array[0] in the proptionate Slab
				$slab1 = $propotionateslab[0];
				if($packageWeight>$firstslab_weightlimit):
					$remainingWeight=$firstslab_weightlimit-$maxFixedSlab;
					$additionalRate = ceil($remainingWeight / $slab1['for_every']) * $slab1['rate'];
					$rate2 += $additionalRate;
					$reamiainingWeightFromFirstSlab=$packageWeight-$slab1['upto'];
				else:
					$remainingWeight=$packageWeight-$maxFixedSlab;
					$additionalRate = ceil($remainingWeight / $slab1['for_every']) * $slab1['rate'];
					$rate2 += $additionalRate;
					$reamiainingWeightFromFirstSlab=0;
				endif;
				//Calculation for rest of the Array[$i] in the proptionate Slab
				$count = count($propotionateslab);
				for ($i = 1; $i < $count; $i++) {
				if ($reamiainingWeightFromFirstSlab <= 0) :
						break; // No more weight left to calculate rate
				elseif($reamiainingWeightFromFirstSlab > 0 && $reamiainingWeightFromFirstSlab <=$lastslab_weightlimit):
					
					$slab = $propotionateslab[$i];

					if($reamiainingWeightFromFirstSlab > 0):
						if ($reamiainingWeightFromFirstSlab <= $slab['upto']) {
							$additionalRate = ceil($reamiainingWeightFromFirstSlab / $slab['for_every']) * $slab['rate'];
						$rate2 += $additionalRate;
						break;
					} else {
						
						$additionalWeight = $reamiainingWeighreamiainingWeightFromFirstSlabtFromFirstSlab - $slab['upto'];
						$additionalRate = ceil($slab['upto'] / $slab['for_every']) * $slab['rate'];
						$rate2 += $additionalRate;
						$reamiainingWeightFromFirstSlab = $additionalWeight;
					}
					endif;
				else:
					$rate2=0;
				endif;
	
				}
			}
		$postage_rate=$rate+$rate2;
	else:$postage_rate=0;
	endif;
		
	echo json_encode(array(
				'postage_rate' => $postage_rate,
		));
		exit;
	}*/	
	/**
	 * Get fixed  factors
	 */
	public function getfixedfactorAction()
	{		
		$form = $this->getRequest()->getPost();
		$service =$form['service'];
		$sub = $this->getDefinedTable(Sales\FixedFactorServicesTable::class)->get(array('service'=>$service));
		$fee = $this->getDefinedTable(Sales\FeesTarriffTable::class)->get(array('service'=>$service,'fees'=>1));
		$country=0;
		$city=0;
		foreach($sub as $subs):
			$country=$subs['country'];
			$city=$subs['city'];
		endforeach;
		if(!empty($fee)):
			foreach($fee as $fees):
				$advice=$fees['fees'];
			endforeach;
		else:
			$advice=0;
		endif;
		echo json_encode(array(
				'country' => $country,
				'city' => $city,
				'advice' => $advice,
		));
		exit;
	}	
	/**
	 * Get fees tarriff
	 */
	public function getfeesAction()
	{		
		$form = $this->getRequest()->getPost();
		$subservice =$form['service'];
		$ad_fees = $this->getDefinedTable(Sales\FeesTarriffTable::class)->get(array('service'=>$subservice,'fees'=>1));
		$reg_fees = $this->getDefinedTable(Sales\FeesTarriffTable::class)->get(array('service'=>$subservice,'fees'=>2));
		$trans_fees = $this->getDefinedTable(Sales\FeesTarriffTable::class)->get(array('service'=>$subservice,'fees'=>4));
		if(!empty($ad_fees)):
			foreach($ad_fees as $ad_fee):
				$advice_delivery_fee=$ad_fee['charges'];
			endforeach;
		else:$advice_delivery_fee=0;
		endif;
		if(!empty($reg_fees)):
			foreach($reg_fees as $reg_fee):
				$registration_fee=$reg_fee['charges'];
			endforeach;
			else:$registration_fee=0;
		endif;
		if(!empty($trans_fees)):
			foreach($trans_fees as $trans_fee):
				$transmission_fee=$trans_fee['charges'];
			endforeach;
			else:$transmission_fee=0;
		endif;
		echo json_encode(array(
				'advice_delivery_fee' => $advice_delivery_fee,
				'registration_fee' => $registration_fee,
				'transmission_fee' => $transmission_fee,
		));
		exit;
	}	
	/**
	 * Get Weight Limit tarriff
	 */
	public function getchecklimitAction()
	{		
		$form = $this->getRequest()->getPost();
		$subserviceId =$form['service'];
		$weight =$form['weight'];
		$subservice = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get(array('service'=>$subserviceId));
		foreach($subservice as $subservices);
		$fixedslab = $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
		if(!empty($fixedslab)):
		$maxFixedId= $this->getDefinedTable(Sales\FixedslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
		$maxFixedSlab = $this->getDefinedTable(Sales\FixedslabTable::class)->getColumn(array('id'=>$maxFixedId),'to');
		endif;
		$propotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
		if(!empty($propotionateslab)):
		$lastpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
		$lastslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$lastpropotionateslab),'upto');
		endif;
		if(count($fixedslab) > 0 && (count($propotionateslab) < 0 || empty($propotionateslab))):
			if($weight > $maxFixedSlab):
				$message="Exceeded The Limit";
				else:
				$message="";
			endif;
		elseif(count($propotionateslab) > 0 && (count($fixedslab) < 0 || empty($fixedslab))):
			if($weight > $lastslab_weightlimit):
				$message="Exceeded The Limit";
				else:
				$message="";
			endif;
		elseif(count($propotionateslab) > 0 && count($fixedslab) > 0 ):
			if($weight > $lastslab_weightlimit):
				$message="Exceeded The Limit";
				else:
				$message="";
			endif;
		else:
		$message="";
		endif;
			echo json_encode(array(
				'message' => $message,
				'fontColor' => "purple",
				'disableSaveButton' => ($message != "")
		));
		exit;
	}	
	/**
	 * Get Weight Limit tarriff
	 */
	public function getchecklimit1Action()
	{		
		$form = $this->getRequest()->getPost();
		$subserviceId =$form['service'];
		$country =$form['country'];
		$weight =$form['weight'];
		$city =$form['city'];
		$subservice = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get(array('service'=>$subserviceId,'country'=>$country,'city'=>$city));
		foreach($subservice as $subservices);
		$fixedslab = $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
		if(!empty($fixedslab)):
		$maxFixedId= $this->getDefinedTable(Sales\FixedslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
		$maxFixedSlab = $this->getDefinedTable(Sales\FixedslabTable::class)->getColumn(array('id'=>$maxFixedId),'to');
		endif;
		$propotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
		if(!empty($propotionateslab)):
		$lastpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
		$lastslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$lastpropotionateslab),'upto');
		endif;
		if(count($fixedslab) > 0 && (count($propotionateslab) < 0 || empty($propotionateslab))):
			if($weight > $maxFixedSlab):
				$message="Exceeded The Limit";
				else:
				$message="";
			endif;
		elseif(count($propotionateslab) > 0 && (count($fixedslab) < 0 || empty($fixedslab))):
			if($weight > $lastslab_weightlimit):
				$message="Exceeded The Limit";
				else:
				$message="";
			endif;
		elseif(count($propotionateslab) > 0 && count($fixedslab) > 0 ):
			if($weight > $lastslab_weightlimit):
				$message="Exceeded The Limit";
				else:
				$message="";
			endif;
		else:
		$message="";
		endif;
			echo json_encode(array(
				'message' => $message,
				'fontColor' => "purple",
				'disableSaveButton' => ($message != "")
		));
		exit;
	}	
	/**
	 * Get Weight Limit tarriff
	 */
	public function getchecklimit2Action()
	{		
		$form = $this->getRequest()->getPost();
		$subserviceId =$form['service'];
		$country =$form['country'];
		$city =$form['city'];
		$weight =$form['weight'];
		$subservice = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get(array('service'=>$subserviceId,'country'=>$country,'city'=>$city));
		foreach($subservice as $subservices);
		$fixedslab = $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
		if(!empty($fixedslab)):
		$maxFixedId= $this->getDefinedTable(Sales\FixedslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
		$maxFixedSlab = $this->getDefinedTable(Sales\FixedslabTable::class)->getColumn(array('id'=>$maxFixedId),'to');
		endif;
		$propotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$subservices['id']));
		if(!empty($propotionateslab)):
		$lastpropotionateslab = $this->getDefinedTable(Sales\PropotionateslabTable::class)->getMax(array('tarrif_for_services_id'=>$subservices['id']),'id');
		$lastslab_weightlimit=$this->getDefinedTable(Sales\PropotionateslabTable::class)->getColumn(array('id'=>$lastpropotionateslab),'upto');
		endif;
		if(count($fixedslab) > 0 && (count($propotionateslab) < 0 || empty($propotionateslab))):
			if($weight > $maxFixedSlab):
				$message="Exceeded The Limit";
			endif;
		elseif(count($propotionateslab) > 0 && (count($fixedslab) < 0 || empty($fixedslab))):
			if($weight > $lastslab_weightlimit):
				$message="Exceeded The Limit";
			endif;
		elseif(count($propotionateslab) > 0 && count($fixedslab) > 0 ):
			if($weight > $lastslab_weightlimit):
				$message="Exceeded The Limit";
			endif;
		endif;
			echo json_encode(array(
				'message' => $message,
				'fontColor' => "purple",
				'disableSaveButton' => ($message !== "")
		));
		exit;
	}	
	/***
	 * Get Post office and code
	 */
	/**
	 * Get Sub service
	 */
	public function getpostofficeAction()
	{		
		$form = $this->getRequest()->getPost();
		$po_name = $form['po_name'];
		$poffice = $this->getDefinedTable(Sales\PostofficeTable::class)->get(array('id'=>$po_name));
		foreach($poffice as $poffices):
			$post_code=$poffices['post_code'];
		endforeach;
		echo json_encode(array(
				'post_code' => $post_code,
		));
		exit;
	}
	public function getponameAction()
	{		
		$form = $this->getRequest()->getPost();
		$polist =  $this->getDefinedTable(Sales\PostofficeTable::class)->getAll();
		$po = "<option value=''>Select</option>";
		foreach($polist as $polists):
			$po.="<option value='".$polists['id']."'>".$polists['post_office']."</option>";
		endforeach;
		echo json_encode(array(
				'po' => $po,
		));
		exit;
	}
	public function getcountryAction()
	{		
		$form = $this->getRequest()->getPost();
		$countrylist =$this->getDefinedTable(Administration\CountryTable::class)->getAll();
		$country = "<option value=''>Select</option>";
		foreach($countrylist as $countrylists):
			$country.="<option value='".$countrylists['id']."'>".$countrylists['name']."</option>";
		endforeach;
		echo json_encode(array(
				'country' => $country,
		));
		exit;
	}
	/**
	 * Get  service
	 */
	public function getserviceAction()
	{		
		$form = $this->getRequest()->getPost();
		$scopeId = $form['scopeId'];
		$scope = $this->getDefinedTable(Sales\ServiceTable::class)->get(array('scope'=>$scopeId));
		
		$service = "<option value=''></option>";
		foreach($scope as $scopes):
			$service.="<option value='".$scopes['id']."'>".$scopes['service']."</option>";
		endforeach;
		echo json_encode(array(
				'service' => $service,
		));
		exit;
	}
		/**
	 * Get  User
	 */
	public function getuserAction()
	{		
		$form = $this->getRequest()->getPost();
		$locId = $form['location'];
		$userlist = $this->getDefinedTable(Administration\UsersTable::class)->get(array('location'=>$locId));
		
		$users = "<option value='-1'>All</option>";
		foreach($userlist as $userlists):
			$users.="<option value='".$userlists['id']."'>".$userlists['name']."</option>";
		endforeach;
		echo json_encode(array(
				'users' => $users,
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
			case 'article_no':
				$article_no =$form['article_no'];
				$result = $this->getDefinedTable(Sales\PostageTable::class)->isPresent('article_no', $article_no);
				break;
		}
		
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}
	public function getaccountAction()
	{		
		$form = $this->getRequest()->getPost();
		$payment=$form['cash'];
		$locationID=$form['location'];
		if($payment==1){
			$accountlist= $this->getDefinedTable(Accounts\CashaccountTable::class)->get(array('ca.location'=>$locationID));
		}
		elseif($payment==0){
			$accountlist= $this->getDefinedTable(Accounts\BankaccountTable::class)->get(array('ba.location'=>$locationID));	
		
		}
		else{
			$accountlist= $this->getDefinedTable(Accounts\PartyTable::class)->getAll();	
		}
		$acc = "<option value=''>Select</option>";
		foreach($accountlist as $accountlists):
			if($payment==1){
			$acc.="<option value='".$accountlists['id']."'>".$accountlists['cash_account_name']."</option>";
			}
			elseif($payment==0){
				$acc.="<option value='".$accountlists['id']."'>".$accountlists['code'].'-'.$accountlists['account']."</option>";
			}
			else{
				$acc.="<option value='".$accountlists['id']."'>".$accountlists['name']."</option>";
			}
		endforeach;
		echo json_encode(array(
				'acc' => $acc,
		));
		exit;
	}
	/**
	 * Get Subhead
	 */
	public function getsubheadAction()
	{		
		$form = $this->getRequest()->getPost();
		$headId = $form['headId'];
		$subhead_list = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.head'=>$headId));
		$subhead = "<option value=''></option>";
		foreach($subhead_list as $subhead_lists):
			$subhead.="<option value='".$subhead_lists['id']."'>".$subhead_lists['name']."</option>";
		endforeach;
		echo json_encode(array(
				'subhead' => $subhead,
		));
		exit;
	}
		
}
