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
class MasterposController extends AbstractActionController
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
	* Services
	*/
	public function scopeAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Scope',
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
	));
	}
	/**
	 *Add Sevices
	 */
	public function addscopeAction()
	{
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$p_type = $form['pt_type'];
		    $customerObj = $this->getDefinedTable(Accounts\PartyTable::class);
			$cus_type_id = $customerObj->getColumn(array('id'=>$p_type,'role'=>8),'p_type');
			$custypeObj = $this->getDefinedTable(Accounts\PartyTypeTable::class);
			$ptypes = $custypeObj->get(array('id'=>$cus_type_id));
			$ptype.="<option value=''></option>";
			foreach($ptypes as $ptype):
				$ptype.="<option value='".$ptype['id']."'>".$ptype['code']."</option>";
			endforeach;
		echo json_encode(array(
				'fp_type' => $ptype,
		));
		exit;
	}
	/**
	 *Add Sevices 
	 */
	public function addserviceAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'service' => $form['service'],
					'scope'   => $form['scope'],
					'status'  => 1,           
					'author'  =>$this->_author,
					'created' =>$this->_created,
					'modified'=>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ServiceTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'service'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Add Service',
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *Edit Sevices
	 */
	public function editserviceAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'      => $this->_id,
					'service' => $form['service'],
					'scope'   => $form['scope'],
					'status'  => 1,
					'author'  => $this->_author,
					'created' => $this->_created,
					'modified'=> $this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ServiceTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^Data edited  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to edit data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'service'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Service',
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->get($this->_id),
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	* Sub Services
	*/
	public function serviceAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Sub Services',
			'scopeObj' => $this->getDefinedTable(Sales\ScopeTable::class),
			'service'   => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'subheadObj'   => $this->getDefinedTable(Accounts\SubheadTable::class)
	));
	}
	/**
	 *Add Sub Sevices
	 */
	public function addsubserviceAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'sub_service' => $form['sub_service'],
					'service'     => $form['service'],
					'status'      => 1,
					'author'      => $this->_author,
					'created'     => $this->_created,
					'modified'    => $this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\SubserviceTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'subservice'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Add Sub Service',
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *Edit sub Sevices ....
	 */
	public function editsubserviceAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'      => $this->_id,
					'service' => $form['service'],
					'scope'   => $form['scope'],
					'status'  => 1,
					'author'  => $this->_author,
					'created' => $this->_created,
					'modified'=> $this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ServiceTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'service'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Sub Service',
			'subservice' => $this->getDefinedTable(Sales\SubserviceTable::class)->get($this->_id),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	* Fees
	*/
	public function feesAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Fees',
			'fees' => $this->getDefinedTable(Sales\FeesTable::class)->getAll(),
	));
	}
	/**
	 *Edit fees
	 */
	public function editfeesAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'      => $this->_id,
					'fees' => $form['fees'],
					'status'  => 1,
					'author'  => $this->_author,
					'created' => $this->_created,
					'modified'=> $this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\FeesTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fees'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Fees',
			'fees' => $this->getDefinedTable(Sales\FeesTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	* Fees Tarriff
	*/
	public function feestarriffAction()
	{
		{
			$this->init();
			$array_id = explode("_", $this->_id);
			$scope = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$service = (sizeof($array_id)>1)?$array_id[1]:'-1';
		
			if($this->getRequest()->isPost())
			{
				$form      		= $this->getRequest()->getPost();
				$scope          = $form['scope'];
				$service   = $form['service'];
				
			}else{
				$scope = '-1';
				$service = '-1';
			
			}
			$data = array(
				'scope'  	    => $scope,
				'service' => $service,
			);
			$feesTarriffTable = $this->getDefinedTable(Sales\FeesTarriffTable::class)->getReport($data);
			$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($feesTarriffTable));
				
			$page = 1;
			if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
			$paginator->setCurrentPageNumber((int)$page);
			$paginator->setItemCountPerPage(20);
	
			$paginator->setPageRange(8);
			return new ViewModel(array(
				'title' => 'Fees Tarriff',
				'paginator'       	=> $paginator,
				'data'            	=> $data,
				'page'           	 => $page,
				'services'			=> $this->getDefinedTable(Sales\ServiceTable::class),
				'scope' 			=> $this->getDefinedTable(Sales\ScopeTable::class),
				'fees' 				=> $this->getDefinedTable(Sales\FeesTable::class),
				'serviceObj' 		=> $this->getDefinedTable(Sales\ServiceTable::class),
			    'scopeObj' 			=> $this->getDefinedTable(Sales\ScopeTable::class),
		));
		}
	}
	/**
	 * Add fees tarriff Actiob
	 */
	public function addfeestarriffAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'service' => $form['service'],
					'scope' => $form['scope'],
					'fees'     => $form['fees'],
					'charges'     => $form['charges'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\FeesTarriffTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'feestarriff'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Add Fees Tarriff',
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
			'fees' => $this->getDefinedTable(Sales\FeesTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * Edit Fees Tarriff Action
	 */
	public function editfeestarriffAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'		=>$this->_id,
					'service' 	=> $form['service'],
					'scope' 	=> $form['scope'],
					'fees'		=> $form['fees'],
					'charges'   => $form['charges'],
					'author' 	=>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\FeesTarriffTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'feestarriff'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Fees Tarriff',
			'feestarriff' => $this->getDefinedTable(Sales\FeesTarriffTable::class)->get($this->_id),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
			'fees' => $this->getDefinedTable(Sales\FeesTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/*
	* Fixed Factors 
	*/
	public function fixedfactorAction()
{
	{
		$this->init();
		$array_id = explode("_", $this->_id);
		$scope = (sizeof($array_id)>1)?$array_id[0]:'-1';
		$service = (sizeof($array_id)>1)?$array_id[1]:'-1';
		if($this->getRequest()->isPost())
		{
			$form      		 = $this->getRequest()->getPost();
			$scope          = $form['scope'];
			$service   = $form['service'];
			
		}else{
			$scope = '-1';
			$service = '-1';
		
		}
		$data = array(
			'scope'  	    => $scope,
			'service' => $service,
		); 
		$fixedFactorServicesTable = $this->getDefinedTable(Sales\FixedFactorServicesTable::class)->getReport($data);
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($fixedFactorServicesTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);

		$paginator->setPageRange(8);
		return new ViewModel(array(
			'title' => 'Fixed Factor',
			'paginator'       => $paginator,
			'data'            => $data,
			'page'            => $page,
			'factors' => $this->getDefinedTable(Sales\FixedFactorServicesTable::class),
			'serviceObj' => $this->getDefinedTable(Sales\ServiceTable::class),
			'scopeObj' => $this->getDefinedTable(Sales\ScopeTable::class),
			
			
	  ));
	}
}
	/**
	 * Add fixed factor Action
	 */
	public function addfixedfactorAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			
			$data = array(
					'scope'     => $form['scope'],
					'service' => $form['service'],
					'country' => $form['country'],
					'city'     => $form['city'],
					'service_scope'    => $form['service_scope'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\FixedFactorServicesTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedfactor'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Fixed Factors',
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * Edit fixed factor Action
	 */
	public function editfixedfactorAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id' => $this->_id,
					'scope'     => $form['scope'],
					'service' => $form['service'],
					'country' => $form['country'],
					'city'     => $form['city'],
					'service_scope'    => $form['service_scope'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\FixedFactorServicesTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
				
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedfactor'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Fixed Factors',
			'fixfactor' => $this->getDefinedTable(Sales\FixedFactorServicesTable::class)->get($this->_id),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/*
	* Service tarriff Factors 
	*/
	public function servicetarriffAction()
	{
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
			$scope = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$service = (sizeof($array_id)>1)?$array_id[1]:'-1';
			if($this->getRequest()->isPost())
			{
				$form      		 = $this->getRequest()->getPost();
				$scope          = $form['scope'];
				$service   = $form['service'];
				
			}else{
				$scope ='-1';
				$service = '-1';
			
			}

			$data = array(
				'scope'  	    => $scope,
				'service' => $service,
			);
			$serviceTarriffTable = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->getReport($data);
			$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($serviceTarriffTable));
				
			$page = 1;
			if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
			$paginator->setCurrentPageNumber((int)$page);
			$paginator->setItemCountPerPage(1000);
			$paginator->setPageRange(8);
			return new ViewModel(array(
			'title' => 'Service Tarriff',
			'paginator'       => $paginator,
			'data'            => $data,
			'page'            => $page,
			'serviceObj' => $this->getDefinedTable(Sales\ServiceTable::class),
			'scopeObj' => $this->getDefinedTable(Sales\ScopeTable::class),
			'countryObj' => $this->getDefinedTable(Administration\CountryTable::class),
			'cityObj' => $this->getDefinedTable(Administration\CityTable::class),
			
			
			)); 
		} 

		
	}
	/**
	 * Add service tarriff Action
	 */
	public function addservicetarriffAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			if(empty($form['country'])):echo $country="0"; else:$country=$form['country'];endif;
			if(empty($form['city'])):echo $city="0"; else:$city=$form['city'];endif;
			$data = array(
					'service' => $form['service'],
					'scope' => $form['scope'],
					'country' => $country,
					'city'     => $city,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'servicetarriff'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Service Tarriff',
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
			'country' => $this->getDefinedTable(Administration\CountryTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Edit service tarriff Action
	 */
	public function editservicetarriffAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			if(empty($form['country'])):echo $country="0"; else:$country=$form['country'];endif;
			if(empty($form['city'])):echo $city="0"; else:$city=$form['city'];endif;
			$data = array(
					'id' => $this->_id,
					'service' => $form['service'],
					'scope' => $form['scope'],
					'country' => $country,
					'city'     => $city,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ServiceTarriffTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New data  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'servicetarriff'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Service Tarriff',
			'servicetarriff' => $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get($this->_id),
			'scope' => $this->getDefinedTable(Sales\ScopeTable::class)->getAll(),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'country' => $this->getDefinedTable(Administration\CountryTable::class)->getAll(),
			'city' => $this->getDefinedTable(Administration\CityTable::class),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Get  service
	 */
	public function getserviceAction()
	{		
		$form = $this->getRequest()->getPost();
		$scopeId = $form['scopeId'];
		$sub = $this->getDefinedTable(Sales\ServiceTable::class)->get(array('scope'=>$scopeId));
		
		$service = "<option value=''></option>";
		foreach($sub as $subs):
			$service.="<option value='".$subs['id']."'>".$subs['service']."</option>";
		endforeach;
		echo json_encode(array(
				'service' => $service,
		));
		exit;
	}	
	/**
	 * Get city 
	 */
	public function getcityAction()
	{		
		$form = $this->getRequest()->getPost();
		$countryId = $form['countryId'];
		$country = $this->getDefinedTable(Administration\CityTable::class)->get(array('country'=>$countryId));
		
		$city = "<option value=''></option>";
		foreach($country as $countrys):
			$city.="<option value='".$countrys['id']."'>".$countrys['city']."</option>";
		endforeach;
		echo json_encode(array(
				'city' => $city,
		));
		exit;
	}
	/*
	*  Fixed Slabs  
	*/
	public function fixedslabAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Subservice Tarriff',
			'servicetarriff' => $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get($this->_id),
			'fixedslab' => $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$this->_id)),
			'propotionateslab' => $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$this->_id)),
			
	));
	}	
	/**
	 * Add fixed slab Action
	 */
	public function addfixedslabAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'tarrif_for_services_id' => $form['servicetarriff'],
					'from' => $form['from'],
					'to' => $form['to'],
					'rate'     => $form['rate'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\FixedslabTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New fixed slab  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new fixed slab");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedslab','id'=>$form['servicetarriff']));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Fixed Factors',
			'servicetarriff' => $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get($this->_id),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'country' => $this->getDefinedTable(Administration\CountryTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Edit fixed slab Action
	 */
	public function editfixedslabAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'                     =>$this->_id,
					'tarrif_for_services_id' => $form['servicetarriff'],
					'from'                   => $form['from'],
					'to'                     => $form['to'],
					'rate'                   => $form['rate'],
					'author'                 =>$this->_author,
					'created'                =>$this->_created,
					'modified'               =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\FixedslabTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New fixed slab  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new fixed slab");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedslab','id'=>$form['servicetarriff']));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Fixed Factors',
			'fixedslab' => $this->getDefinedTable(Sales\FixedslabTable::class)->get($this->_id),

		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Add propotionate slab Action
	 */
	public function addpropotionateslabAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'tarrif_for_services_id' => $form['servicetarriff'],
					'for_every' => $form['forevery'],
					'rate' => $form['rate'],
					'upto'     => $form['upto'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\PropotionateslabTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New propotionate slab  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new propotionate slab");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedslab','id'=>$form['servicetarriff']));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Fixed Factors',
			'servicetarriff' => $this->getDefinedTable(Sales\ServiceTarriffTable::class)->get($this->_id),
			'service' => $this->getDefinedTable(Sales\ServiceTable::class)->getAll(),
			'country' => $this->getDefinedTable(Administration\CountryTable::class)->getAll(),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Edit propotionate slab Action
	 */
	public function editpropotionateslabAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'                     =>$this->_id,
					'tarrif_for_services_id' => $form['servicetarriff'],
					'for_every'              => $form['forevery'],
					'rate'                   => $form['rate'],
					'upto'                   => $form['upto'],
					'author'                 => $this->_author,
					'created'                => $this->_created,
					'modified'               => $this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\PropotionateslabTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New propotionate slab  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new propotionate slab");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedslab','id'=>$form['servicetarriff']));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Propotionate Slab',
			'propotionateslab' => $this->getDefinedTable(Sales\PropotionateslabTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/*
	*  Post Office  
	*/
	public function postofficeAction()
	{
		$this->init();
		return new ViewModel(array(
			'title' => 'Post Office',
			'postoff' => $this->getDefinedTable(Sales\PostofficeTable::class)->getAll(),
			'fixedslab' => $this->getDefinedTable(Sales\FixedslabTable::class)->get(array('tarrif_for_services_id'=>$this->_id)),
			'propotionateslab' => $this->getDefinedTable(Sales\PropotionateslabTable::class)->get(array('tarrif_for_services_id'=>$this->_id)),
			
	));
	}	
	/**
	 * Add post office Action
	 */
	public function addpostofficeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'post_office' => $form['post_office'],
					'post_code' => $form['post_code'],
					'status'     => 1,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\PostofficeTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Post Office  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new Post Office");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'postoffice'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Add Post Office',
		
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Edit post office Action
	 */
	public function editpostofficeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'          =>$this->_id,
					'post_office' => $form['post_office'],
					'post_code'   => $form['post_code'],
					'status'      => 1,
					'author'      =>$this->_author,
					'created'     =>$this->_created,
					'modified'    =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\PostofficeTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Post Office  successfully added");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to add new Post Office");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'postoffice'));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Edit Post Office',
			'postoffice' => $this->getDefinedTable(Sales\PostofficeTable::class)->get($this->_id),
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Delete fix slab Action
	 */
	public function deletefixslabAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$tarrif_for_services_id=$form['tarrif_for_services_id'];
			$data = array(
					'id' => $this->_id,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$result = $this->getDefinedTable(Sales\FixedslabTable::class)->remove($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted  data");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedslab','id'=>$tarrif_for_services_id));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Fixed Slab',
			'fixslab' => $this->getDefinedTable(Sales\FixedslabTable::class)->get($this->_id),
			
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Delete Propotionate slab Action
	 */
	public function deletepropslabAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$tarrif_for_services_id=$form['tarrif_for_services_id'];
			$data = array(
					'id' => $this->_id,
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$result = $this->getDefinedTable(Sales\PropotionateslabTable::class)->remove($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ successfully deleted  data");
			else:
				$this->flashMessenger()->addMessage("notice^ Failed to delete  data");
			endif;
			return $this->redirect()->toRoute('masterpos', array('action'=>'fixedslab','id'=>$tarrif_for_services_id));
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Delete Propotionate Slab',
			'proplab' => $this->getDefinedTable(Sales\PropotionateslabTable::class)->get($this->_id),
			
		));
			$ViewModel->setTerminal(True);
		return $ViewModel;
	}
}
