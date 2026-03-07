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
class ContractorawardController extends AbstractActionController
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
	//leased godown view page
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Contractor Award',
			'contractoraward' => $this->getDefinedTable(Realestate\ContractorAwardTable::class)->getAll(),
		));
	}
	
	//add contractor award records
	public function addcontawardAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			
			$data = array(
				'contractor' => $form['contractor'],
				'project' => $form['project'],
				'award_date' => $form['award_date'],
				'cdb_no' => $form['cdb_no'],
				'location' => $form['location'],
				'actual_amt' => $form['project_cost'],
				'amount' => $form['project_cost'],
				'security_deposit' => $form['security_amt'],
				'start_date' => $form['start_date'],
				'end_date' => $form['end_date'],
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Realestate\ContractorAwardTable::class)->save($data);
			if($result){
				$this->_connection->commit(); // commit transaction on success
				$this->flashMessenger()->addMessage("success^ Action Successful");
				return $this->redirect()->toRoute('contaward');
			}else{
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
				return $this->redirect()->toRoute('contaward', array('action'=>'addcontaward'));
			}
		}
		return new ViewModel(array(
			'title' => 'Add Contractor Award',
			'contractor' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role' => '2')),
			'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
		));
	}
	
	//advance work
	public function advanceAction()
	{
		$this->init();
		$contractor_award = $this->_id;
		
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			
			$data = array(
					'contractor_award'	=> $contractor_award,
					'advance_type'	=> $form['advance'],
					'advance_amt'	=> $form['adv_amt'],
					'date'	=> $form['adv_date'],
					'cdb_no'	=> $form['cdb_no'],
					'author'	=> $this->_author,
					'created'	=> $this->_created,
					'modified'	=> $this->_modified,
				);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //Transaction begins here
			$result = $this->getDefinedTable(Realestate\ContractorAdvanceTable::class)->save($data);
			if($result){
				$flag = $this->getDefinedTable(Realestate\ContractorAwardTable::class)->getColumn($contractor_award,'flag');
				if($flag == 0){
					//update flag to 1, to mark that advance is taken
					$flag = array(
						'id' => $contractor_award,
						'flag' => 1,
						'author' => $this->_author,
						'modified' => $this->_modified,
					);
					$this->getDefinedTable(Realestate\ContractorAwardTable::class)->save($flag);
				}
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to perform Aciton, Try Again");
			}
			return $this->redirect()->toRoute('contaward');
		}
		$ViewModel = new ViewModel(array(
			'title' => 'Advance',
			'id'	=> $contractor_award,
			'contract_adv'	=> $this->getDefinedTable(Realestate\ContractorAdvanceTable::class)->get(array('contractor_award' => $contractor_award)),
		));
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}
	
	//runnning bill action
	public function runningamtAction()
	{
		$this->init();
		$contractor_award = $this->_id;
		if($this->getRequest()->isPost()){
			$form=$this->getRequest()->getPost();
			
			if($form['bill'] == '1'){
				//RunningBill_1
				$results = $this->getDefinedTable(Realestate\ContractorRunningAmtTable::class)->getMaxSerial('RunningBill',array('contractor_award' => $contractor_award));
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['bill_no'], 12));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
				
				$bill_no = 'RunningBill_'.$next_serial;
			}else{
				$bill_no = 'Final Bill';
			}
			
			$adj_against = ($form['adjustment'] == 0)?'':$form['adj_against'];
			
			$data = array(
				'contractor_award'	=> $contractor_award,
				'bill_no'	=> $bill_no,
				'bill_type'	=> $form['bill'],
				'bill_date'	=> $form['bill_date'],
				'cdb_no'	=> $form['cdb_no'],
				'running_amt'	=> $form['running_amt'],
				'retaining_amt'	=> $form['retaining_amt'],
				'tds'	=> $form['tds'],
				'adjustment_amt'	=> $form['adjustment'],
				'adjustment_against'	=> $adj_against,
				'payable_amt'	=> $form['actual_amt'],
				'author'	=> $this->_author,
				'created'	=> $this->_created,
				'modified'	=> $this->_modified,
			);
			
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //Transaction begins here
			$result = $this->getDefinedTable(Realestate\ContractorRunningAmtTable::class)->save($data);
			if($result){
				$this->_connection->commit();
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
				$this->_connection->rollback();
				$this->flashMessenger()->addMessage("error^ Failed to Perform Action, Try Again.");
			}
			return $this->redirect()->toRoute('contaward');
		}
		$final_bill = $this->getDefinedTable(Realestate\ContractorRunningAmtTable::class)->getColumn(array('contractor_award' => $contractor_award,'bill_no' => 'Final Bill'),'bill_no');
		return new ViewModel(array(
			'title'	=> 'Running Bill',
			'id'	=> $contractor_award,
			'final_bill'	=> $final_bill,
			'retention_percent' => $this->getDefinedTable(Realestate\RetentionTdsTable::class)->getColumn(1,'percent'),
			'tds_percent' => $this->getDefinedTable(Realestate\RetentionTdsTable::class)->getColumn(2,'percent'),
		));
	}
	
	//Overall Report
	public function overallreportAction()
	{
		$this->init();
		$contractor_award = $this->_id;
		
		return new ViewModel(array(
			'title' => 'Overall Report',
			'contractor_award' => $contractor_award,
			'cont_award' => $this->getDefinedTable(Realestate\ContractorAwardTable::class)->get($contractor_award),
			'cont_advance'	=> $this->getDefinedTable(Realestate\ContractorAdvanceTable::class)->get(array('contractor_award' => $contractor_award)),
			'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
			'cont_runningObj' => $this->getDefinedTable(Realestate\ContractorRunningAmtTable::class),
		));
	}
}
?>
