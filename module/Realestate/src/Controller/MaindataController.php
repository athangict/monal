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
use Hr\Model As Hr;
use Asset\Model As Asset;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Administration\Model As Administration;
use Realestate\Model As Realestate;
class MaindataController extends AbstractActionController
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
    protected $_id; 		// route parameter perid, usally used by crude
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
	//Retention and TDS percent data
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Retention And TDS',
			'building' => $this->getDefinedTable(Realestate\BuildingMasterTable::class)->getAll(),
			'region' => $this->getDefinedTable(Administration\RegionTable::class),
			'location' => $this->getDefinedTable(Administration\LocationTable::class),
			'employee' => $this->getDefinedTable(Hr\EmployeeTable::class),
			'type' => $this->getDefinedTable(Asset\AssettypeTable::class)
		));
	}
	//add Percent 
	public function addbuildingmasterAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$data = array(
				'building_id'		=> $form['buildingid'],
				'name' 				=> $form['name'],
				'code' 				=> $form['code'],
				'type' 				=> $form['type'],
				'region' 			=> $form['region'],
				'location' 			=> $form['location'],
				'custodian' 		=> $form['custodian'],
				'purchase_date' 	=> $form['purchase_date'],
				'putin_date' 		=> $form['putin_date'],
				'building_value'	=> $form['building_value'],
				'status'			=> 1,
				'created'			=> $this->_created,
				'author' 			=> $this->_author,
				'modified' 			=> $this->_modified,
			);
			$data=$this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //transaction begins here
			$result = $this->getDefinedTable(Realestate\BuildingMasterTable::class)->save($data);
			if($result){
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Perform Action, Try Again");
			}
				return $this->redirect()->toRoute('rsdata',array('action' => 'index'));	
		}
		return new ViewModel(array(
			'title' 		=> 'Add Building',
			'region'		=>$this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'employees'		=>$this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
			'assettypes'		=>$this->getDefinedTable(Asset\AssettypeTable::class)->get(array('id'=>[6,7])),
	
		));
		
	}

	//edit Retention and TDS data
	public function editbuildingmasterAction()
	{
		$this->init();
		$id = $this->_id;
		
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$data = array(
				'id' => $id,
				'building_id'		=> $form['buildingid'],
				'name' 				=> $form['name'],
				'code' 				=> $form['code'],
				'type' 				=> $form['type'],
				'region' 			=> $form['region'],
				'location' 			=> $form['location'],
				'custodian' 		=> $form['custodian'],
				'purchase_date' 	=> $form['purchase_date'],
				'putin_date' 		=> $form['putin_date'],
				'building_value'	=> $form['building_value'],
				'status'			=> 1,
				'created'			=> $this->_created,
				'author' 			=> $this->_author,
				'modified' 			=> $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //transaction begins here
			$result = $this->getDefinedTable(Realestate\BuildingMasterTable::class)->save($data);
			if($result){
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Perform Action, Try Again");
			}
			return $this->redirect()->toRoute('rsdata',array('action' => 'index'));
		}
		return new ViewModel(array(
			'title' => 'Edit Data',
			'datas' => $this->getDefinedTable(Realestate\BuildingMasterTable::class)->get($id),
			'assettypes' => $this->getDefinedTable(Asset\AssettypeTable::class)->get(array('id'=>[6,7])),
			'locations' =>$this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'regions' =>$this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
		));
	}
	
	//Floor
	public function floorAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Floor',
			'floor' => $this->getDefinedTable(Realestate\FloorMasterTable::class)->getAll(),
		));
	}
	
	//add floor
	public function addfloorAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$data = array(
				'floor' => $form['floor'],
				'status' => 1,
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,
			);
			$data=$this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //transaction begins here
			$result = $this->getDefinedTable(Realestate\FloorMasterTable::class)->save($data);
			if($result){
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Perform Action, Try Again");
			}
			return $this->redirect()->toRoute('rsdata',array('action'=>'floor'));
		}
		$ViewModel = new ViewModel(array(
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	
	//edit floor
	public function editfloorAction()
	{
		$this->init();
		$id = $this->_id;
		
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$data = array(
				'id' => $id,
				'floor' => $form['floor'],
				'status' => 1,
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,
			);
			$data=$this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //transaction begins here
			$result = $this->getDefinedTable(Realestate\FloorMasterTable::class)->save($data);
			if($result){
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Perform Action, Try Again");
			}
			return $this->redirect()->toRoute('rsdata',array('action'=>'floor'));
		}
		$ViewModel = new ViewModel(array(
			'regions' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
			'datas' => $this->getDefinedTable(Realestate\FloorMasterTable::class)->get($id),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	/**
	 * get location by class
	**/
	public function getlocationAction()
	{
		$this->init();
		$lc='';
		$form = $this->getRequest()->getPost();
		
		$region_id = $form['region'];
		//$region_id =1;
		$locations = $this->getDefinedTable(Administration\LocationTable::class)->get(array('region' => $region_id));
		
		$lc.="<option value='-1'>All</option>";
		foreach($locations as $loc):
			$lc.= "<option value='".$loc['id']."'>".$loc['location']."</option>";
		endforeach;
		echo json_encode(array(
			'location' => $lc,
		));
		exit;
	}
}
?>
