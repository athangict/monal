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
use Sales\Model As Sales;

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
		
		//$this->_safedataObj = $this->SafeDataPlugin();
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
		
	/**
	 * Scheme type Action
	 */
	public function schemetypeAction()
	{
		$this->init();
		return new ViewModel(array(
				'title' => 'Scheme Type',
				'schemetypes' => $this->getDefinedTable(Sales\SchemeTypeTable::class)->getAll(),
		));
	}
	/**
	 * Add schemetype Action
	 */
	public function addschemetypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'scheme_type' => $form['scheme_type'],
					'description' => $form['description'],
					'type'     => $form['type'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\SchemeTypeTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Scheme type successfully added");
			else:
			$this->flashMessenger()->addMessage("notice^ Failed to add new Scheme type");
			endif;
			return $this->redirect()->toRoute('slmaster', array('action'=>'schemetype'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	

	/**
	 * Edit Scheme type Action
	 */
	public function editschemetypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id' => $this->_id,
					'scheme_type' => $form['scheme_type'],
					'description' => $form['description'],
					'type'     => $form['type'],
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result=$this->getDefinedTable(Sales\SchemeTypeTable::class)->save($data);
			if($result > 0):
			$this->flashmessenger()->addMessage("success^ Scheme type successfully updated ");
			else:
			$this->flashmessenger()->addMessage("error^ Failed to update scheme type");
			endif;
			return $this->redirect()->toRoute('slmaster', array('action'=>'schemetype'));
		}
		$ViewModel = new ViewModel(array(
				'schemetypes' => $this->getDefinedTable(Sales\SchemeTypeTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	* Claim type Action
	*/
	public function claimtypeAction()
	{
		$this->init();

		return new ViewModel(array(
				'title' => 'Claim Types',
				'claimtypes' =>$this->getDefinedTable(Sales\ClaimTypeTable::class)->getAll(),
		));
	}

	/**
	* Add Claim type Action
	*/
	public function addclaimtypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'claim_type' => $form['claim_type'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\ClaimTypeTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Claim type successfully added");
			else:
			$this->flashMessenger()->addMessage("notice^ Failed to add new Claim type");
			endif;
			return $this->redirect()->toRoute('slmaster', array('action'=>'claimtype'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}

	/**
	* Edit Claim type Action
	*/
	public function editclaimtypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id' => $this->_id,
					'claim_type' => $form['claim_type'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result=$this->getDefinedTable(Sales\ClaimTypeTable::class)->save($data);
			if($result > 0):
			$this->flashmessenger()->addMessage("success^ Claim type successfully updated ");
			else:
			$this->flashmessenger()->addMessage("error^ Failed to update claim type");
			endif;
			return $this->redirect()->toRoute('slmaster', array('action'=>'claimtype'));
		}
		$ViewModel = new ViewModel(array(
				'claimtypes' => $this->getDefinedTable(Sales\ClaimTypeTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Credit Authority Action
	 */
	public function creditauthorityAction()
	{
		$this->init();
		return new ViewModel(array(
				'title' => 'Credit Authority',
				'creditauthoritys' => $this->getDefinedTable(Sales\CreditAuthorityTable::class)->getAll(),
		));
	}
	/**
	 * Add Credit authority Action
	 */
	public function addcreditauthorityAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'authority' 	 => $form['authority'],
					'approval_limit' => $form['approval_limit'],
					'remark'     	 => $form['remark'],
					'author' 	=>$this->_author,
					'created'   =>$this->_created,
					'modified'  =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Sales\CreditAuthorityTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Credit Authority successfully added");
			else:
			$this->flashMessenger()->addMessage("notice^ Failed to add new Credit authority");
			endif;
			return $this->redirect()->toRoute('slmaster', array('action'=>'creditauthority'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}	
	/**
	 * Edit Credit authority Action
	 */
	public function editcreditauthorityAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'id' => $this->_id,
					'authority' 	 => $form['authority'],
					'approval_limit' => $form['approval_limit'],
					'remark'     	 => $form['remark'],
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result=$this->getDefinedTable(Sales\CreditAuthorityTable::class)->save($data);
			if($result > 0):
			$this->flashmessenger()->addMessage("success^Credit Authority successfully updated");
			else:
			$this->flashmessenger()->addMessage("error^ Failed to update Credit Authority");
			endif;
			return $this->redirect()->toRoute('slmaster', array('action'=>'creditauthority'));
		}
		$ViewModel = new ViewModel(array(
				'creditauthoritys' => $this->getDefinedTable(Sales\CreditAuthorityTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
     *  creditceiling action
     */
    public function creditceilingAction()
    {
        $this->init();
        return new ViewModel(array(
            'title' => 'Credit Ceiling',
            'creditceilings' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'8')),
        ));
    } 
    // Add credit ceiling to the customers
	public function updateceilingAction()
	{
		$this->init();
		
			$post = $this->getRequest()->getPost();
		    //echo $post['check']; 
			if($post['check'] == 1):
				//add
				$data = array(
					'id' => $post['id'],
					'credit_ceiling' => '1',
					'author'  => $this->_author,
					'modified'=> $this->_modified	
				);
				$result = $this->getDefinedTable(Accounts\PartyTable::class)->save($data);
				echo $res = ($result > 0)? 1:0; 
			else:
				//add
				$data = array(
					'id' => $post['id'],
					'credit_ceiling' => '0',
					'author'  => $this->_author,
					'modified'=> $this->_modified	
				);
				$result = $this->getDefinedTable(Accounts\PartyTable::class)->save($data);
				echo $res = ($result > 0)? 1:0;
			endif;
		exit;
	}
}
