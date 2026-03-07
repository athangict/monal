<?php
namespace Stock\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Stdlib\ArrayObject;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\Extension;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Stock\Model As Stock;
use Administration\Model As Administration;
use Purchase\Model As Purchase;
class FormulasheetController extends AbstractActionController
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
    protected $_connection; 
    
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
		$this->_user = $this->identity();		
		$this->_login_id = $this->_user->id;  
		$this->_login_role = $this->_user->role;  
		$this->_author = $this->_user->id;  		
		$this->_id = $this->params()->fromRoute('id');		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');		
		//$this->_dir =realpath($fileManagerDir);
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	public function indexAction()
	{
		$this->init();
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$activity = $form['activity'];			
		}else{
			$activity = -1;
		}
		
	    $formulae = $this->getDefinedTable(Stock\CostingFormulaTable::class)->getbyactivity($activity);
		return new ViewModel( array(
				'title'       => 'Costing Formula',
				'formulae'    => $formulae,
				'activityID'	  => $activity,
		        'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
		        'costingTypeObj' => $this->getDefinedTable(Stock\CostingTypeTable::class),
		) );
	}
	
	/**
	 * item group action
	 */
	public function viewAction()
	{
		$this->init();		
		return new ViewModel( array(
				'title' => "View Formula",
		        'costingHeadObj' => $this->getDefinedTable(Stock\CostingHeadTable::class),
    		    'activityObj' => $this->getDefinedTable(Administration\ActivityTable::class),
    		    'costingTypeObj' => $this->getDefinedTable(Stock\CostingTypeTable::class),
				'formula' => $this->getDefinedTable(Stock\CostingFormulaTable::class)->get($this->_id),
				'formula_details' => $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->get(array('costing_formula'=>$this->_id)),
		) );
	}
	
	/**
	 * add item action
	 * 
	 */	
	public function addAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
		  $form = $this->getRequest()->getpost();		
		  $this->_connection->beginTransaction();
		  $formula_data = array(
		        'costing_type'    => $form['costing_type'],
		        'activity'        => $form['activity'],
		        'formula_name'    => $form['formula_name'],
		        'description'     => $form['note'],
		        'status'          => '1',
		        'author'	      => $this->_author,
				'created'	      => $this->_created,
				'modified'	      => $this->_modified,
		  );
		  
		  $result = $this->getDefinedTable(Stock\CostingFormulaTable::class)->save($formula_data);
		  if($result > 0):
		       $serial = $form['order'];
		       $costing_head = $form['costing_head'];
		       $formula = $form['formula'];
		       $description = $form['description'];
		       $elc = $form['elc'];
    		     for($i=0; $i < sizeof($costing_head); $i++):
    		           $costing_head_label = $this->getDefinedTable(Stock\CostingHeadTable::class)->getColumn($costing_head[$i], 'costing_head');
        		       $formula_dtls = array(
        		            'costing_formula'=> $result,
        	                'serial'         => $serial[$i],
        	                'costing_head'   => $costing_head[$i],
        	                'formula'        => $formula[$i],
        	                'description'    => $costing_head_label,
        	                'elc'            => $elc[$i],
        		            'author'	     => $this->_author,
        		            'created'	     => $this->_created,
        		            'modified'	     => $this->_modified,
        		           );
        		       $result1 = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->save($formula_dtls);
    		       endfor;		       
		  endif;
		  if($result1 > 0){
		      $this->_connection->commit();
		      $this->flashMessenger()->addMessage("success^ New Formula successfully added");
		      return $this->redirect()->toRoute('formula', array('action' =>'view', 'id' => $result));
		  }
		  else{
		      $this->_connection->rollback();
		      $this->flashMessenger()->addMessage("error^ Not able to add formula.");
		      return $this->redirect()->toRoute('formula', array('action' =>'add'));
		  }
		endif;
		
		$charges = $this->getDefinedTable(Stock\ChargesTaxTable::class)->getAll();	

		return new ViewModel(array(
				'title'         => "Add Costing Formula",
		        'charges'       => $charges,
		        'activities'    => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),	
		        'costingTypes'  => $this->getDefinedTable(Stock\CostingTypeTable::class)->getAll(),	
		        'costing_heads' => $this->getDefinedTable(Stock\CostingHeadTable::class)->getAll(),
	            'invDefFields'  => $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->getAll(),				
		));
	}	
	
	/**
	 * Edit Formula
	 */
	public function editformulaAction()
	{
		$this->init();
	    $formula_id = $this->_id;
	    if($this->getRequest()->isPost()):
    	    $form = $this->getRequest()->getpost();
	       
    	    $this->_connection->beginTransaction();
    	    $formula_data = array(
    	            'id'              => $form['fs_id'], 
    	    		'costing_type'    => $form['costing_type'],
    	    		'activity'        => $form['activity'],
    	    		'formula_name'    => $form['formula_name'],
    	    		'description'     => $form['note'],
    	    		'status'          => '2',
    	    		'author'	      => $this->_author,
    	    		'modified'	      => $this->_modified,
    	    );
    	    
    	    $result = $this->getDefinedTable(Stock\CostingFormulaTable::class)->save($formula_data);
    	    if($result > 0):
        	    $serial = $form['order'];
        	    $costing_head = $form['costing_head'];
        	    $formula = $form['formula'];
        	    $description = $form['description'];
        	    $elc = $form['elc'];
        	    $details_id = $form['details_id'];
        	    $delete_rows = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->getNotIn($details_id, array('costing_formula' => $result));
        	    for($i=0; $i < sizeof($costing_head); $i++):
            	    $costing_head_label = $this->getDefinedTable(Stock\CostingHeadTable::class)->getColumn($costing_head[$i], 'costing_head');
            	    $formula_dtls = array(
            	            'id'             => $details_id[$i],
            	    		'costing_formula'=> $result,
            	    		'serial'         => $serial[$i],
            	    		'costing_head'   => $costing_head[$i],
            	    		'formula'        => $formula[$i],
            	    		'description'    => $costing_head_label,
            	    		'elc'            => $elc[$i],
            	    		'author'	     => $this->_author,
            	    		'created'	     => $this->_created,
            	    		'modified'	     => $this->_modified,
            	    );
            	    $result1 = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->save($formula_dtls);
    	        endfor;
    	        foreach($delete_rows as $delete_row):
    	            $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->remove($delete_row['id']);
    	        endforeach;
    	    endif;
    	    if($result1 > 0){
    	    	$this->_connection->commit();
    	    	$this->flashMessenger()->addMessage("success^ Editing Formula successful");
    	    	return $this->redirect()->toRoute('formula', array('action' =>'view', 'id' => $result));
    	    }
    	    else{
    	    	$this->_connection->rollback();
    	    	$this->flashMessenger()->addMessage("error^ Failed to edit formula.");
    	    	return $this->redirect()->toRoute('formula', array('action' =>'add'));
    	    }
	    else:
    		$formulaDtls = $this->getDefinedTable(Stock\CostingFormulaTable::class)->get($formula_id);
    		$formulaSheetDtls = $this->getDefinedTable(Stock\CostFormulaDtlsTable::class)->get(array('costing_formula'=>$formula_id));
    		$charges = $this->getDefinedTable(Stock\ChargesTaxTable::class)->getAll();	
		endif;
		return new ViewModel(array(
				'title'            => "Edit Costing Formula",
		        'charges'          => $charges,
		        'formulaDtls'      => $formulaDtls,
		        'formulaSheetDtls' => $formulaSheetDtls ,
		        'activities'       => $this->getDefinedTable(Administration\ActivityTable::class)->getAll(),	
		        'costingTypes'     => $this->getDefinedTable(Stock\CostingTypeTable::class)->getAll(),	
		        'costing_headsObj' => $this->getDefinedTable(Stock\CostingHeadTable::class),	
				'invDefFields'     => $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->getAll(),
		));
	}	
	
	public function getformulacodeAction()
	{
	    $this->init();
	    $form = $this->getRequest()->getPost();
	    
	    $costing_head_id = $form['costing_head'];
	    $costing_head = $this->getDefinedTable(Stock\CostingHeadTable::class)->getColumn($costing_head_id, 'costing_head');
	 
	    $costing_head_code = "[S.".$costing_head."]";	   
	    echo $costing_head_code;	  	  
	    exit;
	}
	public function commitformulaAction()
	{    $this->init();
		$formulaSheet = $this->getDefinedTable(Stock\CostingFormulaTable::class)->get($this->_id);
	    if(sizeof($formulaSheet)>0){
	      
	        $data = array(
	        		'id'			=>$this->_id,
	        		'status' 		=> 4,
	        		'author'	    => $this->_author,
	        		'modified'      => $this->_modified,
	        );
	        $data   = $this->_safedataObj->rteSafe($data);
	        $result = $this->getDefinedTable(Stock\CostingFormulaTable::class)->save($data);
	    }
		
		if($result):
			$this->flashMessenger()->addMessage("success^ Costing Formula Successfully commited");
			return $this->redirect()->toRoute('formula',array('action'=>'view','id'=>$this->_id));
		endif;
		
	}
}
