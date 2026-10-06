<?php
/**
 * Laminas MVC Application Controller
 */

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Authentication\AuthenticationService;
use Laminas\Db\TableGateway\TableGateway;
use Interop\Container\ContainerInterface;
use Laminas\View\Model\JsonModel;
use Accounts\Model As Accounts;

class AjaxresponseController extends AbstractActionController
{
	private $_container;
	protected $_id; 		// route parameter id, usally used by crude
	protected $_table; 		// database table 
    protected $_permissionObj; //permission controller plugin
    
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
		$this->_id = $this->params()->fromRoute('id');
		$this->_permissionObj =  $this->PermissionPlugin();		
	}
   
    
	/**
	 *  Action to retrive locations
	 **/
	public function getlocationAction()
	{	
		$this->init();
		$locations = $this->_permissionObj->getLocation($this->_id);
		$viewModel =  new ViewModel(array(
				'locations'   => $locations,
		));	
		$viewModel->setTerminal(true);
			
		return  $viewModel;
	}
	/**
	 *  Action to retrive Banks location Wise
	 **/
	public function getbanksAction()
	{	
		$this->init();
		
		$viewModel =  new ViewModel(array(
			'banks'   => $this->getDefaultTable('fa_bank_account')->select(array('location' => $this->_id)),
		));
		 
		$viewModel->setTerminal(true);
		 
		return  $viewModel;
	}
	
	/**
	 *  Action to retrive locations
	 **/
	public function getlocforreportAction()
	{
		$this->init();
		$viewModel =  new ViewModel(array(
			'locations'   => $this->getDefaultTable('sys_location')->select(array('region'=>$this->_id)),
		));
	
		$viewModel->setTerminal(true);
			
		return  $viewModel;
	}
	
	/*
	 * Function to load gewogs
	*
	* */
	 
	public function getgewogAction()
	{
		$this->init();
		
		$viewModel =  new ViewModel(array(
				'gewogs'   => $this->getDefaultTable('hr_gewog')->select(array('dzongkhag' => $this->_id)),
		));
		 
		$viewModel->setTerminal(true);
		 
		return  $viewModel;
	}
	 
	 
	/*
	 * Function to load Villages
	*
	* */
	
	public function getvillageAction()
	{
		$this->init();
		
		$viewModel =  new ViewModel(array(
				'villages'   => $this->getDefaultTable('hr_village')->select(array('gewog' => $this->_id)),
		));
		 
		$viewModel->setTerminal(true);
		 
		return  $viewModel;
	}
	
	/**
	 * function/action to get head with given headtype
	 */
	public function getheadAction()
	{ 
		$this->init();
		//echo $this->_id;exit;
		$viewModel = new ViewModel(array(
			'heads' => $this->getDefaultTable("fa_head")->select(array('head_type'=>$this->_id)),
		));
		$viewModel->setTerminal(true);
			
		return  $viewModel;
	}
	
	/**
	 * function/action to get subhead with given head
	 */
	public function getsubheadAction()
	{	
		$this->init();
		$viewModel = new ViewModel(array(
			'subheads' => $this->getDefaultTable("fa_sub_head")->select(array('head'=>$this->_id)),
		));
		$viewModel->setTerminal(true);
			
		return  $viewModel;
	}
	/**
	 * PSWF-chart of Account-SUBHEAD
	 */
	public function getpswfsubheadAction()
	{	
		$this->init();
		$viewModel = new ViewModel(array(
			'subheads' => $this->getDefaultTable("ps_sub_head")->select(array('head'=>$this->_id)),
		));
		$viewModel->setTerminal(true);
			
		return  $viewModel;
	}
	/**
	 * function/action to get transactions with given head
	 */
	public function gettransactionsAction()
	{	//echo 'Hi';exit;
		$this->init();
		$subHead = $this->params()->fromPost('sub_head', $this->_id);
		$where = new \Laminas\Db\Sql\Where();
		$where->equalTo('sub_head', $subHead)
			->equalTo('status', 4)
			->isNotNull('ref_no')
			->notEqualTo('ref_no', '')
			->expression('TRIM(ref_no) <> ?', array(''));

		$transactiondtlsResult = $this->getDefaultTable("fa_transaction_details")->select(function($select) use ($where) {
			$select->where($where);
		});

		$transactiondtls = array();
		$remainingAmounts = array();
		foreach($transactiondtlsResult as $tdtls){
			$transactiondtls[] = $tdtls;
			$sourceId = (int) $tdtls->id;
			$sourceDebit = (float) str_replace(',', '', (string) $tdtls->debit);
			$sourceCredit = (float) str_replace(',', '', (string) $tdtls->credit);
			$sourceAmount = ($sourceDebit > 0) ? $sourceDebit : $sourceCredit;

			$usedAmount = 0.0;
			$usedRows = $this->getDefaultTable('fa_transaction_details')->select(array('against' => $sourceId, 'status' => 4));
			foreach($usedRows as $usedRow){
				$usedDebit = (float) str_replace(',', '', (string) $usedRow['debit']);
				$usedCredit = (float) str_replace(',', '', (string) $usedRow['credit']);
				$usedAmount += ($usedDebit > 0) ? $usedDebit : $usedCredit;
			}
			$remainingAmount = max($sourceAmount - $usedAmount, 0);

			if($sourceDebit > 0){
				$remainingDebit = $remainingAmount;
				$remainingCredit = 0;
			}else{
				$remainingCredit = $remainingAmount;
				$remainingDebit = 0;
			}

			$remainingAmounts[$sourceId] = array(
				'debit' => number_format($remainingDebit, 3, '.', ''),
				'credit' => number_format($remainingCredit, 3, '.', ''),
			);
		}

		$viewModel = new ViewModel(array(
			'transactiondtls' => $transactiondtls,
			'remainingAmounts' => $remainingAmounts,
		));
		$viewModel->setTerminal(true);
		return  $viewModel;
	}
	/*GET CREDIT/DEBIT AMOUNT BASED ON THE REF-NO*/
	public function getamountAction()
    {
		$referenceValue = $this->params()->fromRoute(
			'id',
			$this->params()->fromPost('id', $this->params()->fromPost('reference', ''))
		);
		$transactiondetailTable = $this->getDefinedTable(Accounts\TransactiondetailTable::class);

		$debitAmount = '0.000';
		$creditAmount = '0.000';

		if ($referenceValue !== '' && $referenceValue !== null) {
			$referenceId = 0;
			if (is_numeric($referenceValue)) {
				$referenceId = (int) $referenceValue;
			} else {
				$rows = $this->getDefaultTable('fa_transaction_details')->select(array('ref_no' => $referenceValue));
				foreach ($rows as $row) {
					$referenceId = (int) $row['id'];
					break;
				}
			}

			if($referenceId > 0){
				$sourceDebit = (float) str_replace(',', '', (string) $transactiondetailTable->getColumn($referenceId, 'debit'));
				$sourceCredit = (float) str_replace(',', '', (string) $transactiondetailTable->getColumn($referenceId, 'credit'));
				$sourceAmount = ($sourceDebit > 0) ? $sourceDebit : $sourceCredit;

				$usedAmount = 0.0;
				$usedRows = $this->getDefaultTable('fa_transaction_details')->select(array('against' => $referenceId, 'status' => 4));
				foreach($usedRows as $usedRow){
					$usedDebit = (float) str_replace(',', '', (string) $usedRow['debit']);
					$usedCredit = (float) str_replace(',', '', (string) $usedRow['credit']);
					$usedAmount += ($usedDebit > 0) ? $usedDebit : $usedCredit;
				}
				$remainingAmount = max($sourceAmount - $usedAmount, 0);

				if($sourceDebit > 0){
					$debitAmount = number_format($remainingAmount, 3, '.', '');
					$creditAmount = '0.000';
				}else{
					$creditAmount = number_format($remainingAmount, 3, '.', '');
					$debitAmount = '0.000';
				}
			}
		}

		return new JsonModel([
			'debit' => $debitAmount,
			'credit' => $creditAmount,
		]);

    }
}
