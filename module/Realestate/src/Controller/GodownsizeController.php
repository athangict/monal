<?php
namespace Realestate\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Mail\Message;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Part as MimePart;
use Laminas\Mail\Transport\Sendmail;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Stock\Model As Stock;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Administration\Model As Administration;
use Realestate\Model As Realestate;
class GodownsizeController extends AbstractActionController
{   
	private $_container;
	protected $_table; 		// database table 
    protected $_user; 		// user detail
    protected $_highest_role; 	// highest_role
    protected $_login_role; // logined user role
    protected $_author; 	// logined user id
    protected $_created; 	// current date to be used as created dated
    protected $_modified; 	// current date to be used as modified date
    protected $_config; 	// configuration details
    protected $_dir; 		// default file directory
    protected $_id; 		// route parameter id, usally used by crude
    protected $_auth; 		// checking authentication
    protected $_safedataObj; //safedata controller plugin
    protected $_permissionObj; //permission controller plugin
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
		if(!isset($this->_highest_role)){
			$this->_highest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMax($column='id');  
		}

		if(!isset($this->_author)){
			$this->_author = $this->_user->id;  
		}

		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		$fileManagerDir = $this->_config['file_manager']['dir'];
		
		if(!is_dir($fileManagerDir)) {
			mkdir($fileManagerDir, 0777);
		}
		
		//$this->_dir =realpath($fileManagerDir);
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
	
	}
	//Real Estate Details view page
	public function indexAction()
	{
		$this->init();
		//print_r("hello");exit;
		return new ViewModel(array(
			'title' => 'Real Estate Details',
			'rsdetails' => $this->getDefinedTable(Realestate\GodownCapacityTable::class)->getAll(),
		));
	}
	
	//add leased 
	public function addestatedtlsAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$data = array(
				'location' => $form['location'],
				'storage_type' => $form['storage_type'],
				'capacity' => $form['capacity'],
				'commodity_stored' => $form['commodity'],
				'area' => $form['area'],
				'operator_name' => $form['operator_name'],
				'physical_condition' => $form['condition'],
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Realestate\GodownCapacityTable::class)->save($data);
			if($result){
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
			}
			return $this->redirect()->toRoute('capacity');
		}
		return new ViewModel(array(
			'title' => 'Add Real Estate Details.',
			'location' => $this->getDefinedTable(Realestate\EstateLocationTable::class)->getAll(),
			
		));
	}
	//add leased 
	public function editestatedtlsAction()
	{
		$this->init();
		$id = $this->_id;
		
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$data = array(
				'id' => $id,
				'location' => $form['location'],
				'storage_type' => $form['storage_type'],
				'capacity' => $form['capacity'],
				'commodity_stored' => $form['commodity'],
				'area' => $form['area'],
				'physical_condition' => $form['condition'],
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Realestate\GodownCapacityTable::class)->save($data);
			if($result){
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
			}
			return $this->redirect()->toRoute('capacity');
		}
		return new ViewModel(array(
			'title' => 'Add Leased Agreement Info.',
			'godowndtls' => $this->getDefinedTable(Realestate\GodownCapacityTable::class)->get($id),
			'locations' => $this->getDefinedTable(Realestate\EstateLocationTable::class)->getAll(),
			'storagetypeObj' => $this->getDefinedTable(Realestate\StorageTypeTable::class),
		));
	}
	
	//view rents monthly
	public function viewrentAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
				
			$year = $form['year'];
			$month = $form['month'];
		}
		$year = ($year == 0)? date('Y'):$year;
		$month = ($month == 0)? date('m'):$month;
		
		$data = array(
				'year' => $year,
				'month' => $month,
		);
		
		$minYear = $this->getDefinedTable(Accounts\TransactionTable::class)->getMin('voucher_date');
		
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		
		$rentreceivable = $this->getDefinedTable(Accounts\TransactionTable::class)->getMonthWiseData('voucher_date',$year,$month);
		//echo '<pre>'; print_r($rentreceivable); exit;
		return new ViewModel(array(
			'title' => 'Rent Received',
		    'rentreceivable' => $rentreceivable,
			'data'    => $data,
			'minYear' => $minYear,
		));
	}
	

}
?>
