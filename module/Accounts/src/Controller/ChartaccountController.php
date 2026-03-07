<?php
namespace Accounts\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Acl\Model As Acl;
use Hr\Model As Hr;
use Accounts\Model As Accounts;

class ChartaccountController extends AbstractActionController
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

		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();
  
	}	
	/**
	 *  index action
	 */
	public function indexAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Chart of Account',
			'classes' => $this->getDefinedTable(Accounts\ClassTable::class)->getAll(),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
		));
	}
	/**
	 *  class action
	 */
	public function classAction()
	{
		$this->init();
		$classTable = $this->getDefinedTable(Accounts\ClassTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($classTable));
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);	
		return new ViewModel(array(
			'title'     => 'Class',
			'paginator' =>$paginator,
			'page'      => $page,
		));		
	} 
	/**
	 *  function/action to add class
	 */
	public function addclassAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'code' => $form['code'],
				'name' => $form['name'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\ClassTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Class successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Class");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'class'));
		}
		$ViewModel = new ViewModel(array(
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
		
	}
	/**
	 *  function/action to edit class
	 */
	public function editclassAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$class_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
				'id' => $form['class_id'],
				'code' => $form['code'],
				'name' => $form['name'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($class);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\ClassTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Class successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update Class");
			endif;
			return $this->redirect()->toRoute('chartaccount');
		}
		$ViewModel = new ViewModel(array(
			'class' => $this->getDefinedTable(Accounts\ClassTable::class)->get($class_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  group action
	 */
	public function groupAction()
	{
		$this->init();
		$groupTable = $this->getDefinedTable(Accounts\GroupTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($groupTable));
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(1000);
		$paginator->setPageRange(8);	
		return new ViewModel(array(
			'title'       => 'Group',
			'paginator'   => $paginator,
			'page'        => $page,
			//'class'       => $this->getDefinedTable(Accounts\ClassTable::class)->getAll(),
		));
	} 
	/**
	 *  function/action to add group
	 */
	public function addgroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'code' => $form['code'],
				'name' => $form['name'],
				'class' => $form['class'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\GroupTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Group successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Group");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'group'));
		}
		$ViewModel = new ViewModel(array(
			'class' => $this->getDefinedTable(Accounts\ClassTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  function/action to edit group
	 */
	public function editgroupAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
				'id' => $this->_id,
				'code' => $form['code'],
				'name' => $form['name'],
				'class' => $form['class'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\GroupTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Group successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update Group");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'group'));
		}
		$ViewModel = new ViewModel(array(
			'group' => $this->getDefinedTable(Accounts\GroupTable::class)->get($this->_id),
			'class' => $this->getDefinedTable(Accounts\ClassTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  headtype action
	 */
	public function headtypeAction()
	{
		$this->init();
		$headtypeTable = $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($headtypeTable));
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);	
		return new ViewModel(array(
			'title'        => 'Head Type',
			'paginator'    => $paginator,
			'page'         => $page,	

		));
	} 
	/**
	 *  function/action to add head type
	 */
	public function addheadtypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'head_type' => $form['head_type'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\HeadtypeTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Headtype successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Headtype");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'headtype'));
		}
		$ViewModel = new ViewModel(array(
			
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * editheadtype action
	 **/
	public function editheadtypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
				'id' => $this->_id,
				'head_type' => $form['head_type'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\HeadtypeTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Headtype successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update Headtype");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'headtype'));
		}
	
		$ViewModel = new ViewModel(array(
			'headtype' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  head action
	 */
	public function headAction()
	{
		$this->init();
		$headTable = $this->getDefinedTable(Accounts\HeadTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($headTable));
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(500);
		$paginator->setPageRange(8);	
		return new ViewModel(array(
			'title'       => 'Head',	
			'paginator'   => $paginator,
			'page'        => $page,	
			//'headtype'    => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
	}
	/**
	 *  function/action to add head
	 **/
	public function addheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'code' => $form['code'],
				'name' => $form['name'],
				'group' => $form['group'],
				'head_type' => $form['head_type'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\HeadTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Head successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Head");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'head'));
		}
		$ViewModel = new ViewModel(array(
			'group' => $this->getDefinedTable(Accounts\GroupTable::class)->getAll(),
			'headtype' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *   function/action to edit head
	 **/
	public function editheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
				'id' => $this->_id,
				'code' => $form['code'],
				'name' => $form['name'],
				'group' => $form['group'],
				'head_type' => $form['head_type'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\HeadTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Head successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update Head");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'head'));
		}
		$ViewModel = new ViewModel(array(
			'head' => $this->getDefinedTable(Accounts\HeadTable::class)->gethead($this->_id),
			'group' => $this->getDefinedTable(Accounts\GroupTable::class)->getAll(),
			'headtype' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  patyrole action
	 */
	public function partyroleAction()
	{
		$this->init();
		$partyroleTable = $this->getDefinedTable(Accounts\PartyroleTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($partyroleTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title' => 'Party Role',
            'paginator'        => $paginator,
			'page'             => $page,
		));
	}
	/**
	 *  function/action to add Party Role
	 */
	public function addpartyroleAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'role' => $form['role'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\PartyroleTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Partyrole successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new partyrole");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'partyrole'));
		}
		$ViewModel = new ViewModel(array(
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit party role action
	 **/
	public function editpartyroleAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
				'id' => $this->_id,
				'role' => $form['role'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\PartyroleTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Partyrole successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to update partyrole");
			endif;
			return $this->redirect()->toRoute('chartaccount', array('action'=>'partyrole'));
		}
		$ViewModel = new ViewModel(array(
			'partyrole' => $this->getDefinedTable(Accounts\PartyroleTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	} 
	/**
	 *  subhead action
	 */
	public function subheadAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$class = $form['class'];
			$group = $form['group'];
			$head = $form['head'];
		else:
			$class = '-1';
			$group = '-1';
			$head = '-1';
		endif;
		$data = array(
			'class' => $class,
			'group' => $group,
			'head' => $head,
		);
		$subheadTable = $this->getDefinedTable(Accounts\SubheadTable::class)->getSubhead($data);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($subheadTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(5000);
		$paginator->setPageRange(8);

		//$subheads = $this->getDefinedTable(Accounts\SubheadTable::class)->getSubhead($data);
		return new ViewModel(array(
			'title'       => 'SubHead',
			'data'        => $data,
			'paginator'   => $paginator,
			'page'        => $page,
			'classObj'    => $this->getDefinedTable(Accounts\ClassTable::class),
			'groupObj'    => $this->getDefinedTable(Accounts\GroupTable::class),
			'headObj'     => $this->getDefinedTable(Accounts\HeadTable::class),
			//'subheads'    => $subheads,
		));
	}
	/**
	 * get group by class
	**/
	public function getgroupAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$class_id = $form['class'];
		$groups = $this->getDefinedTable(Accounts\GroupTable::class)->get(array('class' => $class_id));
		
		$grp = "<option value='-1'>All</option>";
		foreach($groups as $group):
			$grp.= "<option value='".$group['id']."'>".$group['code']."</option>";
		endforeach;
		
		$hd = "<option value='-1'>All</option>";
		
		echo json_encode(array(
			'group' => $grp,
			'head' => $hd,
		));
		exit;
	}
	
	/**
	 * get group by class
	**/
	public function getheadAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$group_id = $form['group'];
		//$head_id=1;
		//echo '<pre>';print_r($group_id);//exit;
		$heads = $this->getDefinedTable(Accounts\HeadTable::class)->get(array('group' => $group_id));
		
		$hd = "<option value='-1'>All</option>";
		foreach($heads as $head):
			$hd .="<option value='".$head['id']."'>".$head['code']."</option>";
		endforeach;
		//echo '<pre>';print_r($hd);
		echo json_encode(array(
		    'head' => $hd,
	    ));
		exit;
	}
	/**
	 *  function/action to add subhead
	 **/
	public function addsubheadAction()
	{ //echo'hellow';exit;
		$this->init();
		$subheadID='';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			//if($form['code'] > 0){
				//$subheads = $this->getDefinedTable(Accounts\SubheadTable::class)->get(array('sh.type'=>$form['type'], 'sh.head'=>$form['head'], 'ref_id'=>$form['code']));
				//foreach($subheads as $subhead):				
					//$subheadID = $subhead['id'];
				//endforeach; 
			//}else{ $subheadID = "0"; }
			//if($subheadID < 1): 
				$type = $form['type'];
				$Ref_id = $form['code'];
				$data = array(
					'head' => $form['head'],
					'type' => $type,
					'ref_id' => $Ref_id,
					'code' => $form['shcode'],
					'name' => $form['shname'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				//echo '<pre>';print_r($data);exit;
				$data = $this->_safedataObj->rteSafe($data);
				$result = $this->getDefinedTable(Accounts\SubheadTable::class)->save($data);
		
				if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Subhead successfully added");
				else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Subhead");
				endif;
			//else:				
				//$this->flashMessenger()->addMessage("error^ Sub Head has been already added, Please Check!");
			//endif;			
		return $this->redirect()->toRoute('chartaccount', array('action'=>'subhead'));
		}
		$ViewModel = new ViewModel(array(
			'headtype' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'types'	=> $this->getDefinedTable(Accounts\TypeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
		
       	/**
	 *  function/action to edit subhead
	 **/
	public function editsubheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			if(!empty($form['code'])){
				$code=$form['code'];
			}else{
				$code='-1';
			}
			$data = array(
				'id'   => $this->_id,
				'head' => $form['head'],
				'type' => $form['type'],
				'ref_id'  => $code,
				'code' => $form['shcode'],
				'name' => $form['shname'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\SubheadTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Subhead successfully updated");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update Subhead");
			endif;
		  return $this->redirect()->toRoute('chartaccount', array('action'=>'subhead'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Subhead',
			'headtype' => $this->getDefinedTable(Accounts\HeadtypeTable::class)->getAll(),
			'subhead' => $this->getDefinedTable(Accounts\SubheadTable::class)->get($this->_id),
			'headObj' =>$this->getDefinedTable(Accounts\HeadTable::class),
			'types'	 => $this->getDefinedTable(Accounts\TypeTable::class)->getAll(),
			'assets' => $this->getDefinedTable(Accounts\AssetsTable::class)->getAll(),
			'partys' => $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
			'bankaccounts' => $this->getDefinedTable(Accounts\BankaccountTable::class)->getAll(),
			'funds' =>  $this->getDefinedTable(Accounts\FundTable::class)->getAll(),
			'payheads' => $this->getDefinedTable(Hr\PayheadTable::class)->getAll(),
			'cashaccounts' => $this->getDefinedTable(Accounts\CashaccountTable::class)->getAll(),
			'incomeheads' => $this->getDefinedTable(Accounts\IncomeheadTable::class)->getAll(),
			'employees' => $this->getDefinedTable(Hr\EmployeeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * get Code and Name for Sub Heads 
	 */
	public function getCodeNameAction()
	{
		$this->init();
		$code='';
		$form = $this->getRequest()->getPost();
		
		$type = $form['type'];
		//echo $type;
		//$type=3;
		$code.="<option value='-1'>other</option>";
		switch ($type){
			case 1: // Sub Head for Assets
				    $codeAlls = $this->getDefinedTable(Accounts\AssetsTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['code']."</option>";
					endforeach;
					break;
			case 2: // Sub Head for Party
				    $codeAlls = $this->getDefinedTable(Accounts\PartyTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['code']."</option>";
					endforeach;
					break;
			case 3: // Sub Head for Bank Accounts
				    $codeAlls = $this->getDefinedTable(Accounts\BankaccountTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['code']."</option>";
					endforeach;
					break;
			case 4: // Sub Head for Fund
			      	$codeAlls = $this->getDefinedTable(Accounts\FundTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['code']."</option>";
					endforeach;
					break;
			case 5: // Sub Head for Pay Head
				    $codeAlls = $this->getDefinedTable(Hr\PayheadTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['pay_head']."</option>";
					endforeach;
					break;
			case 6: // Sub Head for Cash Accounts
				    $codeAlls = $this->getDefinedTable(Accounts\CashaccountTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['cash_account']."</option>";
					endforeach;
					break;
			case 7: // Sub Head for Income Head
				    $codeAlls = $this->getDefinedTable(Accounts\IncomeheadTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['income_head']."</option>";
					endforeach;
					break;
			case 8: // Sub head for Employee
			      	$codeAlls = $this->getDefinedTable(Hr\EmployeeTable::class)->getAll();
				    foreach($codeAlls as $codeAll):
						$code .="<option value='".$codeAll['id']."'>".$codeAll['full_name']."(".$codeAll['designation'].")</option>";
					endforeach;
					break;
			//default: 	
		}
		echo json_encode(array(
			'code' => $code,
		));
		//echo '<pre>';print_r($code);
		exit;
	}
	
	/**
	 * get Code and Name for Sub Head using selected Sub head
	 */
	public function getSHCodeNameAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();		
		$subheadID = $form['sub_head'];
		$type = $form['type'];
		switch ($type){
			case 1: // Sub Head for Assets
				    $shcode = $this->getDefinedTable(Accounts\AssetsTable::class)->getColumn($subheadID, 'code');
				    break;
			case 2: // Sub Head for Party
				    $shcode = $this->getDefinedTable(Accounts\PartyTable::class)->getColumn($subheadID, 'code');	
				    break;			    
			case 3: // Sub Head for Bank Accounts
				    $shcode = $this->getDefinedTable(Accounts\BankaccountTable::class)->getColumn($subheadID, 'code');
				    break;
			case 4: // Sub Head for Fund
			      	$shcode = $this->getDefinedTable(Accounts\FundTable::class)->getColumn($subheadID, 'code');
				    break;
			case 5: // Sub Head for Pay Head
				    $shcode = $this->getDefinedTable(Hr\PayheadTable::class)->getColumn($subheadID, 'pay_head');
				    break;
			case 6: // Sub Head for Cash Accounts
				    $shcode = $this->getDefinedTable(Accounts\CashaccountTable::class)->getColumn($subheadID, 'cash_account');
				    break;
			case 7: // Sub Head for Income Head
				    $shcode = $this->getDefinedTable(Accounts\IncomeheadTable::class)->getColumn($subheadID, 'income_head');
				    break;
			case 8: // Sub head for Employee
			      	$shcode = $this->getDefinedTable(Hr\EmployeeTable::class)->getColumn($subheadID, 'full_name');
				    break;
		}
		$scode = substr($shcode, 0, 14);
		echo json_encode(array(
			'scode' => $scode,
			'shname' => $shcode,
		));
		exit;
	}
	
    /**
     *  journal action
     */
    public function journalAction()
    {
        $this->init();
		$journalTable = $this->getDefinedTable(Accounts\JournalTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($journalTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
            'title'       => 'Journal',
            'paginator'   => $paginator,
			'page'        => $page,
			'voucherObj'  => $this->getDefinedTable(Accounts\VoucherTable::class),
        ));
    }  
     /**
     *  function/action to add Party Role
     */
     public function addjournalAction()
    {
       	$this->init();
        if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array( 
				'code' => $form['code'],
				'journal' => $form['journal'],
				'prefix' => $form['prefix'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Accounts\JournalTable::class)->save($data);
            if($result > 0):
                $this->flashMessenger()->addMessage("success^ New Journal successfully added");
            else:
                $this->flashMessenger()->addMessage("Failed^ Failed to add new Journal");
            endif;
            return $this->redirect()->toRoute('chartaccount', array('action'=>'journal'));             
        }
       $ViewModel = new ViewModel(array(
	       'vouchers' => $this->getDefinedTable(Accounts\VoucherTable::class)->getAll(),
        ));      
        $ViewModel->setTerminal(True);
        return $ViewModel;            
    }
     /**
     * edit journal action
     **/
    public function editjournalAction()
    {
       $this->init();
        if($this->getRequest()->isPost())
        {
            $form=$this->getRequest()->getPost();
            $data=array(
                'id' => $this->_id,
                'code' => $form['code'],
                'journal' => $form['journal'],
                'prefix' => $form['prefix'],
                'author' =>$this->_author,
                'modified' =>$this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Accounts\JournalTable::class)->save($data);
            if($result > 0):
                $this->flashMessenger()->addMessage("success^ Journal successfully updated");
            else:
                $this->flashMessenger()->addMessage("Failed^ Failed to update Journal");
            endif;
            return $this->redirect()->toRoute('chartaccount', array('action'=>'journal')); 
        }
        $ViewModel = new ViewModel(array(
        	'journal' => $this->getDefinedTable(Accounts\JournalTable::class)->get($this->_id),
			'vouchers' => $this->getDefinedTable(Accounts\VoucherTable::class)->getAll(),
        ));             
        $ViewModel->setTerminal(True);
        return $ViewModel;
    }
}
