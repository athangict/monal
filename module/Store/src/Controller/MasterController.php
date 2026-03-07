<?php
namespace Store\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Auth\Model\Auth;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Mail;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;
use Administration\Model As Administration;
use Hr\Model As Hr;
use Store\Model As Store;

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
				'module' => "Store Managment",
		) );
	}
	/**
	 * group action
	 */
	public function groupAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => " Group ",
			'groups' => $this->getDefinedTable(Store\GroupTable::class) -> getAll(),
		));
	}
	/**
	 * add group action
	 */
	public function addgroupAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'name' => $form['name'],
					'code' => $form['code'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\GroupTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('inmaster',array('action' => 'group'));
		}
		$ViewModel = new ViewModel(array(
				'groups' => $this->getDefinedTable(Store\GroupTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * edit group Action
	 **/
	public function editgroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'name' => $form->getPost('name'),
					'code' => $form->getPost('code'),
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\GroupTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('inmaster',array('action' => 'group'));
		}
		$ViewModel = new ViewModel(array(
				'groups' => $this->getDefinedTable(Store\GroupTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * subgroup action
	 */
	public function subgroupAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => "Sub Group ",
			'subgroups' => $this->getDefinedTable(Store\SubGroupTable::class) -> getAll(),
			'groupObj' => $this->getDefinedTable(Store\GroupTable::class),
		));
	}
	
	/**
	 * add subgroup action
	 */
	public function addsubgroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'name' => $form['name'],
				'code' => $form['code'],
				'prefix' => $form['prefix'],
				'item_group' => $form['item_group'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\SubGroupTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('inmaster',array('action' => 'subgroup'));
		}
		$ViewModel = new ViewModel(array(
			'subgroups' => $this->getDefinedTable(Store\SubGroupTable::class)->get($this->_id),
			'groupObj' => $this->getDefinedTable(Store\GroupTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * edit subgroup Action
	 **/
	public function editsubgroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data=array(
				'id' => $form['group_id'],
				'name' => $form['name'],
				'code' => $form['code'],
				'prefix' => $form['prefix'],
				'item_group' => $form['item_group'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Store\SubGroupTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('inmaster',array('action' => 'subgroup'));
		}
		$ViewModel = new ViewModel(array(
			'subgroups' => $this->getDefinedTable(Store\SubGroupTable::class)->get($this->_id),
			'groupObj' => $this->getDefinedTable(Store\GroupTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * checkavailability Action
	 * check if the cid no is already used
	**/
	public function checkavailabilityAction()
	{
		//$this->init(); //including $this->init() will check the PermissionPlugin
		$form = $this->getRequest()->getPost();
		
		if($form['group_id']):
			$old_prefix = $this->getDefinedTable(Store\SubGroupTable::class)->getColumn($form['group_id'],'prefix');
			if($form['prefix'] == $old_prefix):
				$result = TRUE;
			else:
				$prefix = $form['prefix'];
				$result = $this->getDefinedTable(Store\SubGroupTable::class)->checkAvailability('prefix', $prefix);
			endif;
		else:
			$prefix = $form['prefix'];
			$result = $this->getDefinedTable(Store\SubGroupTable::class)->checkAvailability('prefix', $prefix);
		endif;
		echo json_encode(array(
					'valid' => $result,
		));
		exit;
	}
	/**
	 * viewitem action
	 */
	public function viewitemAction()
	{
		$this->init();
		return new ViewModel( array(
			'title' => "Vehicle Group ",
			'vehiclegroups' => $this->getDefinedTable(Fleet\VehicleGroupTable::class) -> getAll(),
		));
	}
	/**
	 * add item action
	 */
	public function additemAction()
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
			$result = $this->getDefinedTable(Fleet\MakeTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'make'));
		}
		$ViewModel = new ViewModel(array(
				'makes' => $this->getDefinedTable(Fleet\MakeTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit item Action
	 **/
	public function edititemAction()
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
			$result = $this->getDefinedTable(Fleet\MakeTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update");
			endif;
			return $this->redirect()->toRoute('ftmaster',array('action' => 'make'));
		}
		$ViewModel = new ViewModel(array(
				'makes' => $this->getDefinedTable(Fleet\MakeTable::class)->get($this->_id),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
}
