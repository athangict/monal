<?php
namespace Accounts\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Accounts\Model As Accounts;
use Administration\Model As Administration;
class ClosingController extends AbstractActionController
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
	}
	/**
	 * get location by class
	**/
	public function getheadAction()
	{
		$this->init();
		$hd='';
		$form = $this->getRequest()->getPost();
		
		$group_id =$form['group'];
		//$region_id =1;
		$heads = $this->getDefinedTable(Accounts\HeadTable::class)->get(array('h.group' => $group_id));
		$hd.="<option value='-1'>All</option>";
		foreach($heads as $hds):
			$hd.= "<option value='".$hds['id']."'>".$hds['name']."</option>";
		endforeach;
		echo json_encode(array(
			'head' => $hd,
		));
		exit;
	}
	/**
	 *  index action
	 */
	public function indexAction()
	{
		$this->init();		
		$min_year = date('Y', strtotime($this->getDefinedTable(Accounts\TransactionTable::class)->getMin('voucher_date')));
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$head = $form['head'];
			$group = $form['group'];
			if($form['task']=='1'):
				$filter = array(
					'start_date'=> date('Y-m-d',strtotime('10-01-'.$year)),
					'end_date'  => date('Y-m-t',strtotime('01-12-'.$year)),
					'activity'  => -1,
					'region'    => -1,
					'location'  => -1,
				);
				if(!$this->getDefinedTable(Accounts\ClosingbalanceTable::class)->isPresent(array('year'=>$year))):
					foreach($this->getDefinedTable(Accounts\SubheadTable::class)->getAll() as $rows):				
						//$closing_balance = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->getClosingBalance($filter,$data['head'],$rows['id'], 1);
						$closing_cr = ($closing_balance < 0)? -$closing_balance:'0';
						$closing_dr = ($closing_balance > 0)? $closing_balance:'0';
						$data = array(
							'sub_head'   => $rows['id'],
							'year'       => $year,
							'closing_dr' => $closing_dr,
							'closing_cr' => $closing_cr,
							'author'     =>$this->_author,
							'created'    =>$this->_created,
							'modified'   =>$this->_modified,
						);
						$this->getDefinedTable(Accounts\ClosingbalanceTable::class)->save($data);
					endforeach;
				endif;
			endif;
		else:
			$year = date('Y');
			$head='';
			$group='';
		endif;
        // echo '<pre>';print_r($head.'-'.$group.'-'.$year);exit;
		return new ViewModel(array(
			'title'  => 'Closing Balance',
			'selected_year' => $year,
			'min_year'	=> $min_year,
			'head'=>$head,
			'group'=>$group,
			'closingbalanceObj' => $this->getDefinedTable(Accounts\ClosingbalanceTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'transactiondetailObj' => $this->getDefinedTable(Accounts\TransactiondetailTable::class),
			'groupObj' => $this->getDefinedTable(Accounts\GroupTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
   		));
	} 
	
	public function addclosingAction(){
		$this->init();
		$year=0;
		$min_year=0;
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
		    //echo"<pre>"; print_r($form); exit;
			$closing_id = $form['closing_id'];
			$closing_head = $form['head'];
			$closing_sub_head = $form['sub_head'];
			$closing_dr =$form['closing_dr'];
			$closing_cr =$form['closing_cr'];	
			$location =$form['location'];
			$reference_no =$form['reference'];
			$closing_sdr =$form['closing_sdr'];
            for($i=0;$i<sizeof($closing_sub_head);$i++){
			  //if($closing_dr[$i] != 0 || $closing_cr[$i] != 0):
				if($closing_id[$i] > 0 ){
					/* Update with year and subhead*/
					$data = array(
					   'id'  => $closing_id[$i],
					   'head'  =>$closing_head[$i],
					   'sub_head'  =>$closing_sub_head[$i],
					   'closing_sdr'  => $closing_sdr[$i],
					   'closing_dr'  => $closing_dr[$i],
					   'closing_cr'  => $closing_cr[$i],
					   'location'  => $location[$i],
					   'reference'  => $reference_no[$i],
					   'author' =>$this->_author,					
					   'modified' =>$this->_modified,
					);
				}
				else{
					/* Insert with year and subhead*/
					$data = array(					
					   'head'  =>$closing_head[$i],
					   'sub_head'  =>$closing_sub_head[$i],
					   'year'       => $form['year'],
					   'closing_sdr'  => $closing_sdr[$i],
					   'closing_dr'  => $closing_dr[$i],
					   'closing_cr'  => $closing_cr[$i],
					   'location'  => $location[$i],
					   'reference'  => $reference_no[$i],
					   'author' =>$this->_author,
					   'created' =>$this->_created,
					   'modified' =>$this->_modified,
					);					
				}
				//echo '<pre>';print_r($data);exit;
				$result = $this->getDefinedTable(Accounts\ClosingbalanceTable::class)->save($data);
              //endif; 				
			if($result > 0 ){
				$this->flashMessenger()->addMessage('success^ Successfully Added the closing balance !');
				$this->redirect()->toRoute('closing', array('action' => 'addclosing'));	
			}
		}		
		endif; 
		return new ViewModel(array(
			'title'  => 'Closing Balance',
			'selected_year' => $year,
			'min_year'	=> $min_year,
			'closingbalanceObj' => $this->getDefinedTable(Accounts\ClosingbalanceTable::class),
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			

   		));
	}
	
	public function sheadlistAction()
	{
		$this->init();		
		$param = explode('-',$this->_id);
		$head = $param['1'];
		$year = $param['0']; 
		$ViewModel = new ViewModel(array(
			'head'       => $head,
			'year'       => $year,
			'subheadObj' => $this->getDefinedTable(Accounts\SubheadTable::class),
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			'closingbalanceObj' => $this->getDefinedTable(Accounts\ClosingbalanceTable::class),	
			'cashaccountObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),			
			'headObj' => $this->getDefinedTable(Accounts\HeadTable::class),	
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),	
			'bankaccountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
			'partyObj'  => $this->getDefinedTable(Accounts\PartyTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
}
