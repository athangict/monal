<?php
namespace Reports\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Accounts\Model As Accounts;
use Sales\Model As Sales;
use Stock\Model As Stock;
use Purchase\Model As Purchase;
class ClaimsreportController extends AbstractActionController
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
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();


	}
	
	public function indexAction()
	{
		$this->init();
		return new ViewModel();
	}
	
	/**
	 * Free item claims report
	 */
	public function freeitemclsrpAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$supplier = $form['supplier'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$location = '';
			$supplier = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		
		$saledtls = $this->getDefinedTable(Sales\SalesDetailsTable::class)->getByLocDate($location, $supplier, $start_date, $end_date);
		
		return new ViewModel(array(
				'title' 	=> "Cash claims report",
				'location'		=> $location,
				'supplier'		=> $supplier,
				'start_date'	=> $start_date,				
				'end_date'		=> $end_date,
				'regions' 		=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'supplierObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'saledtls'		=> $saledtls,
				'itemObj'  		=> $this->getDefinedTable(Stock\ItemTable::class),
				'batchObj'  => $this->getDefinedTable(Stock\BatchTable::class),
				'uomObj'    => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	
	/**
	 * Cash claims report
	 */
	public function cashclsrpAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$supplier = $form['supplier'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$location = '';
			$supplier = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		
		$saledtls = $this->getDefinedTable(Sales\SalesDetailsTable::class)->getByLocDate($location, $supplier, $start_date, $end_date);
		
		return new ViewModel(array(
				'title' => "Cash claims report",
				'location'		=> $location,
				'supplier'		=> $supplier,
				'start_date'	=> $start_date,				
				'end_date'		=> $end_date,
				'regions' 		=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'supplierObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'saledtls'		=> $saledtls,
				'itemObj'  		=> $this->getDefinedTable(Stock\ItemTable::class),
		));
	}
	
	/**
	 * Shortage PRN claims report
	 */
	public function shortageclsrpAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$supplier = $form['supplier'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$location = '';
			$supplier = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		$prnDtls = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getBySupDate($location, $supplier, $start_date, $end_date);
		
		return new ViewModel(array(
				'title' 		=> 'Shortage PRN claims report',
				'location'		=> $location,
				'supplier'		=> $supplier,
				'start_date'	=> $start_date,				
				'end_date'		=> $end_date,
				'regions' 		=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'supplierObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'prnDtls'		=> $prnDtls,
				'itemObj'  		=> $this->getDefinedTable(Stock\ItemTable::class),
				'batchObj'  	=> $this->getDefinedTable(Stock\BatchTable::class),
				'podtlsObj' 	=> $this->getDefinedTable(Purchase\PODetailsTable::class),
				'unititemObj' 	=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'uomObj' 		=> $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	
	/**
	 * Damage PRN claims report
	 */
	public function damageprnclsAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$supplier = $form['supplier'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$location = '';
			$supplier = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		
		$prnDtls = $this->getDefinedTable(Purchase\PRDetailsTable::class)->getBySupDate($location, $supplier, $start_date, $end_date);
		
		return new ViewModel(array(
				'title' 		=> 'Damage claims report',
				'location'		=> $location,
				'supplier'		=> $supplier,
				'start_date'	=> $start_date,				
				'end_date'		=> $end_date,
				'regions' 		=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'supplierObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'prnDtls'		=> $prnDtls,
				'itemObj'  		=> $this->getDefinedTable(Stock\ItemTable::class),
				'batchObj'  	=> $this->getDefinedTable(Stock\BatchTable::class),
				'podtlsObj' 	=> $this->getDefinedTable(Purchase\PODetailsTable::class),
				'unititemObj' 	=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'uomObj' 		=> $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	
	/**
	 * Freight claims report
	 */
	public function freightclsrpAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$from_location = $form['from_location'];
			$to_location = $form['to_location'];
			$supplier = $form['supplier'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$from_location = '';
			$to_location = '';
			$supplier = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		
		$dispatchDtls = $this->getDefinedTable(Stock\DispatchDetailsTable::class)->getfreightBySupDate($from_location, $to_location, $supplier, $start_date, $end_date);
		
		return new ViewModel(array(
				'title' 		=> 'Freight claims report',
				'from_location'	=> $from_location,
				'to_location'	=> $to_location,
				'supplier'		=> $supplier,
				'start_date'	=> $start_date,				
				'end_date'		=> $end_date,
				'regions' 		=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'supplierObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'dispatchDtls'	=> $dispatchDtls,
				'itemObj'  		=> $this->getDefinedTable(Stock\ItemTable::class),
				'unititemObj' 	=> $this->getDefinedTable(Stock\ItemUomTable::class),
		));
	}
	
	/**
	 * Expiry dump claims report
	 */
	public function dumpreportAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
			$supplier = $form['supplier'];
			$samType = $form['samType'];
			$start_date = $form['start_date'];
			$end_date = $form['end_date'];
		else:
			$location = '';
			$supplier = '';
			$samType = '';
			$start_date = date('Y-m-d');
			$end_date  = date('Y-m-d');
		endif;
		
		$dumpdtls = $this->getDefinedTable(Stock\SamDetailsTable::class)->getBySupDate($location, $supplier, $samType, $start_date, $end_date);
		
		return new ViewModel(array(
				'title' 	=> "Expiry Dump claims report",
				'location'		=> $location,
				'supplier'		=> $supplier,
				'samType'		=> $samType,
				'start_date'	=> $start_date,				
				'end_date'		=> $end_date,
				'regions' 		=> $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'supplierObj'   => $this->getDefinedTable(Accounts\PartyTable::class),
				'dumpdtls'		=> $dumpdtls,
				'itemObj'  		=> $this->getDefinedTable(Stock\ItemTable::class),
				'batchObj'      => $this->getDefinedTable(Stock\BatchTable::class),
				'uomObj'    	=> $this->getDefinedTable(Stock\UomTable::class),
				'samtypeObj'  	=> $this->getDefinedTable(Stock\SamTypeTable::class),
		));		
	}
}
