<?php
namespace Fleet\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Fleet\Model As Fleet;
use Stock\Model As Stock;
class MasterController extends AbstractActionController
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
		
		//$this->_dir =realpath($fileManagerDir);

		//$this->_safedataObj =  $this->SafeDataPlugin();
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel( array(
				'module' => "Fleet Management",
		) );
	}
	
	/**
	 * fuel action
	 */
	public function fuelAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => "Vehicle Fuel",
			'fuels' => $this->getDefinedTable(Fleet\FuelTable::class) -> getAll(),
		));
	}
	/**
	 * vehicle class action
	 */
	public function vehicleclassAction()
	{
		$this->init();
		return new ViewModel( array(
				'title' => "Vehicle Class",
				'classes' => $this->getDefinedTable(Fleet\VehicleClassTable::class) -> getAll(),
		));
	}
	/**
	 * make action
	 */
	public function makeAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => "Vehicle Makes",
			'makes' => $this->getDefinedTable(Fleet\MakeTable::Class) -> getAll(),
		));
	}
	/**
	 * add uom action
	 */
	public function addfuelAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'fuel' => $form['fuel'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\FuelTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'fuel'));
		}
		$ViewModel = new ViewModel(array(
				'fuels' => $this->getDefinedTable(Fleet\FuelTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit uom Action
	 **/
	public function editfuelAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'fuel' => $form->getPost('fuel'),
					'description' => $form->getPost('description'),
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\FuelTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'fuel'));
		}
		$ViewModel = new ViewModel(array(
				'fuels' => $this->getDefinedTable(Fleet\FuelTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * add addvehicleclass action
	 */
	public function addvehicleclassAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'class' => $form['class'],
					'prefix' => $form['prefix'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\VehicleClassTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'vehicleclass'));
		}
		$ViewModel = new ViewModel(array(
				'classes' => $this->getDefinedTable(Fleet\VehicleClassTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit VC Action
	 **/
	public function editvehicleclassAction()
	{
		
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'transport_class' => $form->getPost('class'),
					'prefix' => $form->getPost('prefix'),
					'description' => $form->getPost('description'),
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\VehicleClassTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'vehicleclass'));
		}
		$ViewModel = new ViewModel(array(
				'classes' => $this->getDefinedTable(Fleet\VehicleClassTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * add make action
	 */
	public function addmakeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'make' => $form['make'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\MakeTable::Class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'make'));
		}
		$ViewModel = new ViewModel(array(
				'makes' => $this->getDefinedTable(Fleet\MakeTable::Class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit make Action
	 **/
	public function editmakeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'make' => $form->getPost('make'),
					'description' => $form->getPost('description'),
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\MakeTable::Class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'make'));
		}
		$ViewModel = new ViewModel(array(
				'makes' => $this->getDefinedTable(Fleet\MakeTable::Class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * vehiclegroup action
	 */
	public function vehiclegroupAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => "Vehicle Group ",
			'vehiclegroups' => $this->getDefinedTable(Fleet\VehicleGroupTable::class) -> getAll(),
		));
	}
	/**
	 * add make action
	 */
	public function addvehiclegroupAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'group' => $form['vehiclegroup'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\VehicleGroupTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'vehiclegroup'));
		}
		$ViewModel = new ViewModel(array(
				'vehiclegroups' => $this->getDefinedTable(Fleet\VehicleGroupTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit vehiclegroup Action
	 **/
	public function editvehiclegroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'group' => $form->getPost('group'),
					'description' => $form->getPost('description'),
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\VehicleGroupTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'vehiclegroup'));
		}
		$ViewModel = new ViewModel(array(
				'vehiclegroups' => $this->getDefinedTable(Fleet\VehicleGroupTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * vehiclepart action
	 */
	public function vehiclepartAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => "Vehicle Parts",
			'vehicleparts' => $this->getDefinedTable(Fleet\VehiclePartTable::class) -> getAll(),
			'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
			'vehiclegroupObj' => $this->getDefinedTable(Fleet\VehicleGroupTable::class),
		));
	}
	/**
	 * add make action
	 */
	public function addvehiclepartAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'group' => $form['group'],
					'code' => $form['code'],
					'uom' => $form['uom'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\VehiclePartTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'vehiclepart'));
		}
		$ViewModel = new ViewModel(array(
				'vehicleparts' => $this->getDefinedTable(Fleet\VehiclePartTable::class)->getAll(),
				'vehiclegroupObj' => $this->getDefinedTable(Fleet\VehicleGroupTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'uomtypeObj' => $this->getDefinedTable(Stock\UomTypeTable::class),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit vehicle parts action
	 */
	public function editvehiclepartAction()
	{
		
		$this->init();
		$id = $this->_id;
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data = array(
					'id' => $id,
					'group' => $form->getPost('group'),
					'code' => $form->getPost('code'),
					'uom' => $form->getPost('uom'),
					'description' => $form->getPost('description'),
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\VehiclePartTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'vehiclepart'));
		}
		$ViewModel = new ViewModel(array(
				'vehicleparts' => $this->getDefinedTable(Fleet\VehiclePartTable::class)->get($this->_id),
				'vehiclegroupObj' => $this->getDefinedTable(Fleet\VehicleGroupTable::class),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'uomtypeObj' => $this->getDefinedTable(Stock\UomTypeTable::class),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * requisitionitem action
	 */
	public function requisitionitemAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => "Requisition Item ",
			'requisition_items' => $this->getDefinedTable(Fleet\RequisitionItemTable::class) -> getAll(),
			'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	
	/**
	 * add make action
	 */
	public function addrequisitionitemAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'item' => $form['item'],
					'uom' => $form['uom'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\RequisitionItemTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'requisitionitem'));
		}
		$ViewModel = new ViewModel(array(
				'requisition_items' => $this->getDefinedTable(Fleet\RequisitionItemTable::class)->getAll(),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit vehicle parts action
	 */
	public function editrequisitionitemAction()
	{
		
		$this->init();
		$id = $this->_id;
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data = array(
					'id' => $id,
					'item' => $form->getPost('item'),
					'uom' => $form->getPost('uom'),
					'description' => $form->getPost('description'),
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Fleet\RequisitionItemTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'requisitionitem'));
		}
		$ViewModel = new ViewModel(array(
				'requisition_items' => $this->getDefinedTable(Fleet\RequisitionItemTable::class)->get($this->_id),
				'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
}
