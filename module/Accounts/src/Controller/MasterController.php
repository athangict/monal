<?php
namespace Accounts\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Acl\Model As Acl;

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
		if(!isset($this->_highest_role)){
			$this->_highest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMax($column='id'); 	
		}
		if(!isset($this->_lowest_role)){
			$this->_lowest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMin($column='id'); 
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
	 *  Currency action
	 */
	public function currencyAction()
	{
		$this->init();
		$currencyTable = $this->getDefinedTable(Accounts\CurrencyTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($currencyTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Currency',
			'paginator'        => $paginator,
			'page'             => $page,
		)); 
	}
	/**
	 * addcurrency action
	 */
	public function addcurrencyAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'currency' => $form['currency'],
				'country' => $form['country'],
				'code' => $form['code'],
				'fraction' => $form['fraction'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\CurrencyTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New currency successfully added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new currency");
			endif;
			return $this->redirect()->toRoute('accmaster', array('action' => 'currency'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 *  Edit Currency action
	 */
	public function editcurrencyAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$currency_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		
		if($this->getRequest()->isPost()){
            $form = $this->getRequest()->getPost();
            $data = array(  
				'id'                 => $form['currency_id'],
				'currency'           => $form['currency'],
				'code'               => $form['code'],
				'fraction'           => $form['fraction'],
				'country'            => $form['country'],
				'status'             => $form['status'],
				'author'             => $this->_author,
				'modified'           => $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
			//echo '<pre>';print_r($data);exit;
			$this->_connection->beginTransaction();
            $result = $this->getDefinedTable(Accounts\CurrencyTable::class)->save($data);
            if($result > 0):
				$this->_connection->commit();
                $this->flashMessenger()->addMessage("success^ successfully edited Currency");
            else:
				$this->_connection->rollback();
                $this->flashMessenger()->addMessage("error^ Failed to edit Currency");
            endif;
			return $this->redirect()->toRoute('accmaster', array('action'=>'currency'));
        }		
		$ViewModel = new ViewModel([
			'title'        => 'Edit Currency',
			'page'         => $page,
			'currencies'   => $this->getDefinedTable(Accounts\CurrencyTable::class)->get($currency_id),
		]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Bank Ref type Action
	 */
	public function bankreftypeAction()
	{
		$this->init();
		$reftypeTable = $this->getDefinedTable(Accounts\BankreftypeTable::class)->getAll();
		$paginator = new \Laminas\Paginator\Paginator(new \Laminas\Paginator\Adapter\ArrayAdapter($reftypeTable));
			
		$page = 1;
		if ($this->params()->fromRoute('page')) $page = $this->params()->fromRoute('page');
		$paginator->setCurrentPageNumber((int)$page);
		$paginator->setItemCountPerPage(20);
		$paginator->setPageRange(8);
        return new ViewModel(array(
			'title'            => 'Bank Ref Type',
			'paginator'        => $paginator,
			'page'             => $page,
		)); 
	}
	/**
	 * Add bankreftype Action
	 */
	public function addbankreftypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'bank_ref_type' => $form['bankref'],
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
			);
			$data =  $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Accounts\BankreftypeTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Bank reference type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new bank reference type");
			endif;
			return $this->redirect()->toRoute('accmaster', array('action'=>'bankreftype'));
		}
		$ViewModel = new ViewModel(array(
			'bankref' => $this->getDefinedTable(Accounts\BankreftypeTable::class)->getAll(),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Edit bank ref type Action
	 */
	public function editbankreftypeAction()
	{
		$this->init();
		$id = $this->_id;
		$array_id = explode("_", $id);
		$block_id = $array_id[0];
		$page = (sizeof($array_id)>1)?$array_id[1]:'';
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
				'id'  => $form['bankref_id'],
				'bank_ref_type' => $form['bank_ref_type'],
				'author' =>$this->_author,
				'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result=$this->getDefinedTable(Accounts\BankreftypeTable::class)->save($data);
			if($result > 0):
			$this->flashmessenger()->addMessage("success^ Bank reference type successfully updated ");
			else:
			$this->flashmessenger()->addMessage("error^ Failed to update bank reference type");
			endif;
			return $this->redirect()->toRoute('accmaster', array('action'=>'bankreftype'));
		}
		$ViewModel = new ViewModel(array(
			'bank_ref_types' => $this->getDefinedTable(Accounts\BankreftypeTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
}
