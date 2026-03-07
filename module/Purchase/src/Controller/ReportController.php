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
use Stock\Model As Stock;

class ReportController extends AbstractActionController
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
	
	public function podetailsAction()
	{
		$this->init();	
		$array_id = explode("_", $this->_id);
			$location = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$item = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$item_subgroup = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$item_class = (sizeof($array_id)>1)?$array_id[1]:'-1';
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$location       = $form['location'];
				$item    = $form['item'];
				$item_subgroup=$form['item_subgroup'];
				$start_date = $form['start_date'];
				$end_date   = $form['end_date'];
				$item_class   = $form['item_class'];
				$supplier   = $form['supplier'];
			}else{
				$location ='-1';
				$item = '-1';
				$item_subgroup='-1';
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
				$item_class   = '-1';
				$supplier='-1';
			}

			$data = array(
				'location'    => $location,
				'item'  => $item,
				'item_subgroup'=>$item_subgroup,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'supplier'=>$supplier,
				'item_class'  => $item_class,
			);
			$results = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getPODetails($data,$start_date, $end_date);
          //  echo sizeof($results); exit; 
		return new ViewModel( array(   
				'title'				=> 'Purchase Order '.$start_date." till ".$end_date,			    
				'partyObj'         	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'itemObj'		  	=> $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj' 		  	=> $this->getDefinedTable(Stock\UomTable::class),
				'locations' 	  	=> $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
				'results'   		=> $results,
				'data'				=> $data,
				'userLoc'         	=> $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author),
				'locationObj'     	=> $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj' 		=> $this->getDefinedTable(Acl\StatusTable::class),
				'itemgroups' 		=> $this->getDefinedTable(Stock\ItemGroupTable::class)-> getAll(),
				'itemclass' 		=> $this->getDefinedTable(Stock\ItemClassTable::class)-> getAll(),
				'supplier' 			=> $this->getDefinedTable(Accounts\PartyTable::class)-> get(array('p.role'=>1)),
				'supplierObj' 			=> $this->getDefinedTable(Accounts\PartyTable::class),
			  
		) );
	}
	
	public function prdetailsAction()
	{
		$this->init();		
		if($this->getRequest()->isPost())
		{	    
	        $form = $this->getRequest()->getPost();
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
			if($form['activity'] != "all" && $form['supplier'] != "all") { 
			    $param = array('pr.activity'=>$form['activity'],'pr.supplier'=>$form['supplier']);
			}elseif($form['activity'] == "all" && $form['supplier'] != "all"){
			    $param = array('pr.supplier'=>$form['supplier']);
			}elseif($form['activity'] != "all" && $form['supplier'] == "all") {
				$param = array('pr.activity'=>$form['activity']);
			}else{
				$param = array('1'=>'1');
			}
			$results = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getPRDetails($start_date, $end_date, $param, $column='prn_date');
    	}
		return new ViewModel( array(   
			   'title'            => 'PRN Details '.$start_date."- ".$end_date,			    
			   'partyObj'         => $this->getDefinedTable(Accounts\PartyTable::class),
			   'activities'      => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),
			   'activityObj'      => $this->getDefinedTable(Administration\ActivityTable::class),
			   'itemObj'		 => $this->getDefinedTable(Stock\ItemTable::class),
			   'uomObj' 		 => $this->getDefinedTable(Stock\UomTable::class),
			   'locationObj' 	=> $this->getDefinedTable(Administration\LocationTable::class),
			   'results'   		=> $results,
			   'start_date'     => $start_date,
			   'end_date'       => $end_date,
			   'supplier'       => $form['supplier'],
			   'activity'       => $form['activity'],
			   'POObj' 	      => $this->getDefinedTable(Purchase\PurchaseOrderTable::class),
			   'supInvDtlObj'    => $this->getDefinedTable(Purchase\SupInvDetailsTable::class),
			   'supInvObj'    => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class)
		) );
	}
/**
	 * PO Recieve Detail 
	 */
	public function poreceiptAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{	
			$form = $this->getRequest()->getPost();
			$po_no = $form['po'];
			$po_id = $this->getDefinedTable(Purchase\PurchaseOrderTable::class)->getColumn(array('po_no' => $po_no),'id');
		}
		return new ViewModel(array(
				'title'  => 'PO Receipt Report',
				'po_no'  => $po_no,
				'po_id'  => $po_id,
				'podtlObj'   => $this->getDefinedTable(Purchase\PODetailsTable::class),
				'prnObj'     => $this->getDefinedTable(Purchase\PurchaseReceiptTable::class),
				'prndtlsObj' => $this->getDefinedTable(Purchase\PRDetailsTable::class),
				'itemObj'    => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
		));
	}
	/**
	 * Supplier Invoice Report
	 */
	public function invoiceAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
	        $form = $this->getRequest()->getPost();
			$data = array(
				'activity'   => $form['activity'],
				'supplier'   => $form['supplier'],
				'start_date' => $form['start_date'],
				'end_date'   => $form['end_date'],
			);
    	}else{
			$data = array(
				'activity'   => '-1',
				'supplier'   => '-1',
				'start_date' => date('Y-m-d'),
				'end_date'   => date('Y-m-d'),
			);
		}
		return new ViewModel(array(
				'title'               => 'Invoice Report - '.$data['start_date']."- ".$data['end_date'],
				'data'                => $data,
				'activityObj'         => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj'            => $this->getDefinedTable(Accounts\PartyTable::class),
				'supinvObj'           => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
				'pur_receipt_dtlsObj' => $this->getDefinedTable(Purchase\PRDetailsTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
				'itemuomObj' => $this->getDefinedTable(Stock\ItemUomTable::class),
		));
	}
	/**
	 * Purchase Summary Report
	 */
	public function purchasesummaryAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
	        $form = $this->getRequest()->getPost();
			$data = array(
				'activity'   => $form['activity'],
				'supplier'   => $form['supplier'],
				'start_date' => $form['start_date'],
				'end_date'   => $form['end_date'],
			);
    	}else{
			$data = array(
				'activity'   => '-1',
				'supplier'   => '-1',
				'start_date' => date('Y-m-d'),
				'end_date'   => date('Y-m-d'),
			);
		}
		return new ViewModel(array(
				'title'               => 'Purchase Summary - '.$data['start_date']."- ".$data['end_date'],
				'data'                => $data,
				'activityObj'         => $this->getDefinedTable(Administration\ActivityTable::class),
				'partyObj'            => $this->getDefinedTable(Accounts\PartyTable::class),
				'supinvObj'           => $this->getDefinedTable(Purchase\SupplierInvoiceTable::class),
		));
	}
}
