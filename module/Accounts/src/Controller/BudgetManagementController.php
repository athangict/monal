<?php
namespace Accounts\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Administration\Model As Administration;
use Sales\Model As Sales;

class BudgetManagementController extends AbstractActionController
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
		if(!isset($this->_login_location_type)){
			$this->_login_location_type = $this->_user->location_type; 
		}
		if(!isset($this->_userloc)){
			$this->_userloc = $this->_user->location;  
		}

		$this->_id = $this->params()->fromRoute('id');

		$this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	/**--VIEW BUDGET FORECASTING ------------------------------------------------------------------------------------------*/
    public function forecastingAction()
	{
		$this->init();		
		$min_year = date('Y', strtotime($this->getDefinedTable(Accounts\TransactionTable::class)->getMin('voucher_date')));
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$tier = $form['tier'];
			$year = $form['year'];
			$region = $form['region'];
            $loc = $form['location'];
		else:
		    $tier =0;
			$year = date('Y');
			$region = '-1';
            $loc = '-1';
		endif;
		$data = array(
		    'tier'   => $tier,
		    'year'=>$year,
			'region' => $region,
			'location' => $loc,
		);
		
		//echo '<pre>';print_r($data);exit;
		return new ViewModel(array(
			'title'  => 'Budget Forecasting',
			'selected_year' => $year,
			'min_year'	=> $min_year,
			'data' =>$data,
			'budgetforecastObj' => $this->getDefinedTable(Accounts\BudgetTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'classObj' => $this->getDefinedTable(Accounts\ClassTable::class), 
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
   		));
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
	/**ADD FORECASTING------------------------------------------------------------------------------------------------------------*/
	public function addforecastingAction(){
		
		
		$this->init();
		$year=0;
		$min_year=0;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
		    //echo"<pre>"; print_r($form); exit;
			$budget_id = $form['budget_id'];
			$budget_head = $form['head'];
			$budget_sub_head = $form['sub_head'];
			$budget_dr =$form['budget_dr'];
            for($i=0;$i<sizeof($budget_sub_head);$i++){
			  //if($closing_dr[$i] != 0 || $closing_cr[$i] != 0):
				if($budget_id[$i] > 0 ){
					/* Update with year and subhead*/
					$data = array(
					   'id'  => $budget_id[$i],
					   'head'  =>$budget_head[$i],
					   'sub_head'  =>$budget_sub_head[$i],
					   'january'  => $form['january'][$i],
					   'february'  => $form['february'][$i],
					   'march'  => $form['march'][$i],
					   'april'  => $form['april'][$i],
					   'may'  => $form['may'][$i],
					   'june'  => $form['june'][$i],
					   'july'  => $form['july'][$i],
					   'august'  => $form['august'][$i],
					   'september'  => $form['september'][$i],
					   'october'  => $form['october'][$i],
					   'november'  => $form['november'][$i],
					   'december'  => $form['december'][$i],
					   'budget_dr'  => $budget_dr[$i],
					   'author' =>$this->_author,					
					   'modified' =>$this->_modified,
					);
				}
				else{
					/* Insert with year and subhead*/
					$data = array(					
					   'head'  =>$budget_head[$i],
					   'sub_head'    =>$budget_sub_head[$i],
					   'location'    => $this->_userloc,
					   'year'        => $form['year'],
					   'january'  => $form['january'][$i],
					   'february'  => $form['february'][$i],
					   'march'  => $form['march'][$i],
					   'april'  => $form['april'][$i],
					   'may'  => $form['may'][$i],
					   'june'  => $form['june'][$i],
					   'july'  => $form['july'][$i],
					   'august'  => $form['august'][$i],
					   'september'  => $form['september'][$i],
					   'october'  => $form['october'][$i],
					   'november'  => $form['november'][$i],
					   'december'  => $form['december'][$i],
					   'budget_dr'  => $budget_dr[$i],
					   'author' =>$this->_author,
					   'created' =>$this->_created,
					   'modified' =>$this->_modified,
					);					
				}
				$result = $this->getDefinedTable(Accounts\BudgetTable::class)->save($data);
              //endif; 				
			if($result > 0 ){
				$this->flashMessenger()->addMessage('success^ Successfully Added the Budget Forecassting !');
				$this->redirect()->toRoute('budgetmanagement', array('action' => 'addforecasting'));	
			}
		}		
		endif; 
		return new ViewModel(array(
			'title'  => 'Budget Forecasting',
			'selected_year' => $year,
			'min_year'	=> $min_year,
			'budgetforecastObj' => $this->getDefinedTable(Accounts\BudgetTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	

   		));
	}
	/**GETTING THE SUBHEAD LIST--------------------------------------------------------------------------------------*/
	public function sheadlistAction()
	{
		$this->init();		
		$param = explode('-',$this->_id);
		$head = $param['1'];
		$year = $param['0']; 
		//echo '<pre>';print_r($param);exit;
		$ViewModel = new ViewModel(array(
			'head'       => $head,
			'year'       => $year,
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			'budgetforecastObj' => $this->getDefinedTable(Accounts\BudgetTable::class),	
			'cashaccountObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),			
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			'bankaccountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
			'partyObj'  => $this->getDefinedTable(Accounts\PartyTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	 
}