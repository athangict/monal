<?php
namespace Stock\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Authentication\AuthenticationService;
use Interop\Container\ContainerInterface;
use Stock\Model As Stock;
use Acl\Model As Acl;
use Purchase\Model As Purchase;
use Accounts\Model As Accounts;
use Administration\Model As Administration;

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
		$this->_user_loc = $this->_user->location;
		
		$this->_id = $this->params()->fromRoute('id');
		
	    $this->_created = date('Y-m-d H:i:s');
		$this->_modified = date('Y-m-d H:i:s');
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
	public function indexAction()
	{
		$this->init();
		
		return new ViewModel( array(
				'module' => "Stock Inventory",
		) );
	}
	
	/**
	 * uom action
	 */
	public function uomAction()
	{
		$this->init();
		return new ViewModel( array(
				'title' => "Unit of measurement",
				'uoms' => $this->getDefinedTable(Stock\UomTable::class) -> getAll(),
				'uomtypeObj' => $this->getDefaultTable('st_uom_type'),
		) );
	}
	/**
	 * add uom action
	 */
	public function adduomAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'uom_type' => $form['uomtype'],
					'code' => $form['code'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\UomTable::class)->save($data);
	
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Unit of Measurement successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Unit of Measurement");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'uom'));
		}
		$ViewModel = new ViewModel(array(
				'uomtypeTable' => $this->getDefinedTable(Stock\UomTypeTable::class),
			));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit uom Action
	 **/
	public function edituomAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'uom_type' => $form->getPost('uomtype'),
					'code' => $form->getPost('code'),
					'description' => $form->getPost('description'),
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\UomTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Unit of Measurement successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to edit Unit of measurement");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'uom'));
		}
		$ViewModel = new ViewModel(array(
				'uoms' => $this->getDefinedTable(Stock\UomTable::class)->get($this->_id),
				'uomtypeTable' => $this->getDefinedTable(Stock\UomTypeTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * uom action
	 */
	public function uomtypeAction()
	{
		$this->init();
		return new ViewModel( array(
				'title' => "Unit of measurement Type",
				'uomtypes' => $this->getDefinedTable(Stock\UomTypeTable::class) -> getAll(),
		) );
	}
	/**
	 * add uomtype action
	 */
	public function adduomtypeAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'uom_type' => $form['uomtype'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\UomTypeTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ New Unit of Measurement Type successfully added");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to add new Unit of Measurement Type");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'uomtype'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * edit uom Action
	 **/
	public function edituomtypeAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'uom_type' => $form->getPost('uomtype'),
					'description' => $form->getPost('description'),
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\UomTypeTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Unit of Measurement Type successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to edit Unit of measurement Type");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'uomtype'));
		}
		$ViewModel = new ViewModel(array(
				'uomtypes' => $this->getDefinedTable(Stock\UomTypeTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * uom action
	 */
	public function scalarAction()
	{
		$this->init();
		return new ViewModel( array(
				'title' => "Scalar Conversion",
				'scalars' => $this->getDefinedTable(Stock\ScalarConversionTable::class) -> getAll(),
				'uomObj' => $this->getDefaultTable('st_uom'),
		) );
	}
	/**
	 * add scalar conversion action
	 */
	public function addscalarAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'from_uom' => $form['from'],
					'to_uom' => $form['to'],
					'conversion' => $form['conversion'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ScalarConversionTable::class)->save($data);
			
			if($result > 0):
			   $this->flashMessenger()->addMessage("success^ New Scalar Conversion successfully added");
			else:
			   $this->flashMessenger()->addMessage("Failed^ Failed to add new Scalar Conversion");
			endif;
			
			return $this->redirect()->toRoute('stmaster',array('action' => 'scalar'));
		}
		$ViewModel = new ViewModel(array(
				'uomTable' => $this->getDefinedTable(Stock\UomTable::class),
				));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	public function editscalarAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest();
			$data=array(
					'id' => $this->_id,
					'from_uom' => $form->getPost('from'),
					'to_uom' => $form->getPost('to'),
					'conversion' => $form->getPost('conversion'),
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			//print_r($data);exit;
			$result = $this->getDefinedTable(Stock\ScalarConversionTable::class)->save($data);
			if($result > 0):
			$this->flashMessenger()->addMessage("success^ Scalar Conversion successfully updated");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to edit Scalar Conversion");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'scalar'));
		}
		$ViewModel = new ViewModel(array(
				'scalars' => $this->getDefinedTable(Stock\ScalarConversionTable::class)->get($this->_id),
				'uomTable' => $this->getDefinedTable(Stock\UomTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * Charge Tax Action
	 */
	public function chargetaxAction()
	{
		$this->init();
		
		return new ViewModel( array(
				'title' => "Charge Tax",
				'chargeTaxs' => $this->getDefinedTable(Stock\ChargesTaxTable::class)->getAll(),
		) );
		
	}
	/**
	 * Add Charge Tax Action
	 */
	public function addchargetaxAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'charge_tax'  => $form['charge_tax'],
					'description' => $form['description'],
					'percentage'  => $form['percentage'],
					'value'		  => $form['value'],	
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ChargesTaxTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Charge Tax successfully added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Charge Tax");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'chargetax'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Edit Charge Tax Action
	 */
	public function editchargetaxAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'		  => $form['charge_tax_id'],
					'charge_tax'  => $form['charge_tax'],
					'description' => $form['description'],
					'percentage'  => $form['percentage'],
					'value'		  => $form['value'],	
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ChargesTaxTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Charge Tax successfully updated");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update Charge Tax");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'chargetax'));
		}
		$ViewModel = new ViewModel(array(
				'charge_taxs' => $this->getDefinedTable(Stock\ChargesTaxTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	/**
	 * Costing Head Action
	 */
	public function costingheadAction()
	{
		$this->init();
		
		return new ViewModel( array(
				'title' => "Costing Head",
				'costingHeads' => $this->getDefinedTable(Stock\CostingHeadTable::class)->getAll(),
		) );		
	}
	/**
	 * Add Costing Head Action
	 */
	public function addcostingheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
					'costing_head' => $form['costing_head'],
					'description'  => $form['description'],
					'display_sum'  => $form['display_sum'],
					'author'	   => $this->_author,
					'created'	   => $this->_created,
					'modified'	   => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\CostingHeadTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ New Costing Head successfully added");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new Costing Head");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'costinghead'));
		endif;
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * Edit Costing Head Action
	 */
	public function editcostingheadAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
					'id'		   => $form['costing_head_id'],
					'costing_head' => $form['costing_head'],
					'description'  => $form['description'],
					'display_sum'  => $form['display_sum'],
					'author'	   => $this->_author,
					'modified'	   => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\CostingHeadTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Costing Head successfully updated");
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to update Costing Head");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'costinghead'));
		endif;
		$ViewModel = new ViewModel(array(
				'costing_heads' => $this->getDefinedTable(Stock\CostingHeadTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * Trip Master 
	 */
	public function tripAction()
	{
		$this->init();
		return new ViewModel(array(
				'title' => 'Trip',
				'trips' => $this->getDefinedTable(Stock\TripTable::class)->getAll(),
		));
	}
	/**
	 * Add New Trip Action
	 */
	public function addtripAction()
	{
		$this->init();
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();	
			
			$checkbox = $form['checkbox'];
			
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($this->_user_loc,'prefix');	
			$date = date('y',strtotime(date('Y-m-d')));					
			$tmp_no = $location_prefix."TP".$date; 			
			$results = $this->getDefinedTable(Stock\TripTable::class)->getMonthlyNo($tmp_no);
			$no_list = array();
			foreach($results as $result):
				array_push($no_list, substr($result['trip_no'], 6)); 
			endforeach;
			$next_serial = max($no_list) + 1;
			switch(strlen($next_serial)){
				case 1: $next_serial_no = "00".$next_serial;  break;
				case 2: $next_serial_no = "0".$next_serial;   break;
				default: $next_serial_no = $next_serial;       break; 
			}	
			$trip_no = $tmp_no.$next_serial_no;
			
			$data = array(
					'trip_no'     => $trip_no,
					'trip_date'   => $form['trip_date'],
					'effect_date' => '',
					'status'      => '0',
					'note'        => $form['note'],
					'author'      => $this->_author,
					'created'     => $this->_created,
					'modified'    => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->_connection->beginTransaction(); //***Transaction begins here***//
			$result = $this->getDefinedTable(Stock\TripTable::class)->save($data);
			if($result):
				$selected_trip_id = (isset($form['trip_id']))?$form['trip_id']:$this->getDefinedTable('Stock\TripTable')->getColumn(array('status'=>'1'),'id');
				
				if($selected_trip_id > 0):
					$trip_dtls = $this->getDefinedTable(Stock\TripDtlsTable::class)->get(array('trip'=>$selected_trip_id));
					foreach($trip_dtls as $trip_dtl):
						$plain_distance = (in_array(1, $checkbox))?$trip_dtl['plain_distance']:'0.00';
						$hill_distance = (in_array(2, $checkbox))?$trip_dtl['hill_distance']:'0.00';
						$plain_rate = (in_array(3, $checkbox))?$trip_dtl['plain_rate']:'0.00';
						$hill_rate = (in_array(4, $checkbox))?$trip_dtl['hill_rate']:'0.00';
						$hop = (in_array(5, $checkbox))?$trip_dtl['hop']:'0.00';
						
						$trip_data = array(
								'trip'                 => $result,
								'source_location'      => $trip_dtl['source_location'],
								'destination_location' => $trip_dtl['destination_location'],
								'plain_distance'	   => $plain_distance,						
								'plain_rate'		   => $plain_rate,
								'hill_distance'		   => $hill_distance,						
								'hill_rate'			   => $hill_rate,
								'hop'				   => $hop,
								'remarks'			   => $trip_dtl['remarks'],							
								'author'		       => $this->_author,
								'created'		       => $this->_created,
								'modified'		       => $this->_modified,
						);
						$trip_data = $this->_safedataObj->rteSafe($trip_data);
						$this->getDefinedTable(Stock\TripDtlsTable::class)->save($trip_data);
					endforeach;
					$this->_connection->commit(); 
					$this->flashMessenger()->addMessage("success^ Successfully created new trip no.".$trip_no." Trip details also retrieved.");
				else:
					$this->_connection->commit(); 
					$this->flashMessenger()->addMessage("success^ Successfully created new trip no.".$trip_no);
				endif;
				return $this->redirect()->toRoute('stmaster', array('action' =>'edittrip','id' => $result));
			else:
				$this->_connection->rollback(); 
				$this->flashMessenger()->addMessage("error^ Failed to create new trip. Please try again");
				return $this->redirect()->toRoute('stmaster', array('action' =>'trip'));
			endif;
		endif;
		return new ViewModel(array(
				'title'    => 'Add New Trip',
				'tripObj'  => $this->getDefinedTable(Stock\TripTable::class),
		));
	}
	/**
	 * Edit/Manage Trip Action
	 */
	public function edittripAction()
	{
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();	
			$task = $form['btn'];
                        //echo $task; exit;
			if($task == "save_trip"):
				$trip_id = $form['id'];
				$source_location = $form['source_location'];
				$destination_location = $form['destination_location'];
				$plain_distance = $form['plain_distance'];
				$plain_rate = $form['plain_rate'];
				$hill_distance = $form['hill_distance'];
				$hill_rate = $form['hill_rate'];
				$hop = $form['hop'];
				$remarks = $form['remarks'];
				$this->_connection->beginTransaction(); //***Transaction begins here***//
				if(sizeof($trip_id) > 0 ){
					//update the existing record
					for($i=0;$i < sizeof($destination_location); $i++){
						if($trip_id[$i] > 0){
						  $data = array(
								'id'                   => $trip_id[$i],
								'trip'                 => $form['trip_id'],
								'source_location'      => $source_location,
								'destination_location' => $destination_location[$i],
								'plain_distance'	   => $plain_distance[$i],														'plain_rate'		   => $plain_rate[$i],
								'hill_distance'		   => $hill_distance[$i],						
								'hill_rate'			   => $hill_rate[$i],
								'hop'				   => $hop[$i],
								'remarks'			   => $remarks[$i],							
								'modified'		       => $this->_modified,
						   );					  
						}
						else{
						  $data = array(	
								'trip'                 => $form['trip_id'],
								'source_location'      => $source_location,
								'destination_location' => $destination_location[$i],
								'plain_distance'	   => $plain_distance[$i],						
								'plain_rate'		   => $plain_rate[$i],
								'hill_distance'		   => $hill_distance[$i],						
								'hill_rate'			   => $hill_rate[$i],
								'hop'				   => $hop[$i],
								'remarks'			   => $remarks[$i],
								'author'			   => $this->_author,
								'created'			   => $this->_created,
								'modified'		       => $this->_modified,
						   );
						}
					   $data = $this->_safedataObj->rteSafe($data);
					   $result = $this->getDefinedTable(Stock\TripDtlsTable::class)->save($data);
					}
				}
				else{
				  //add new trip details
				   for($i=0;$i < sizeof($destination_location); $i++){
					   $data = array(	
								'trip'                 => $form['trip_id'],
								'source_location'      => $source_location,
								'destination_location' => $destination_location[$i],
								'plain_distance'	   => $plain_distance[$i],						
								'plain_rate'		   => $plain_rate[$i],
								'hill_distance'		   => $hill_distance[$i],						
								'hill_rate'			   => $hill_rate[$i],
								'hop'				   => $hop[$i],
								'remarks'			   => $remarks[$i],
								'author'			   => $this->_author,
								'created'			   => $this->_created,
								'modified'		       => $this->_modified,
						   );
					   $data = $this->_safedataObj->rteSafe($data);
					   $result = $this->getDefinedTable(Stock\TripDtlsTable::class)->save($data);
				   }
				}
				if($result > 0):
					$remark_data = array(
							'id'       => $form['trip_id'],
							'note'  => $form['note'],
							'modified' => $this->_modified,
					);
					$remark_data = $this->_safedataObj->rteSafe($remark_data);
					$result2 = $this->getDefinedTable(Stock\TripTable::class)->save($remark_data);
					if($result2){
						$this->_connection->commit(); 
						$this->flashMessenger()->addMessage('success^ Successfully updated the trip details');
					}else{
						$this->_connection->rollback(); 
						$this->flashMessenger()->addMessage('error^ Failed to update the trip');
					}
				else:
					$this->_connection->rollback(); 
					$this->flashMessenger()->addMessage('error^ Failed to update the trip details');
				endif;
				return $this->redirect()->toRoute('stmaster', array('action'=>'edittrip','id'=>$form['trip_id']));
			else: //submit from select location
				$data = array(
					'source_location' => $form['source_location'],
					'location_type' => $form['location_type'],
					'trip_id'         => $form['trip_id'],
                                        'region'  => $form['region'],
				);
		    endif;
			$trip_id = $form['trip_id'];
		else:
			$trip_id = $this->_id;
		endif;
				$ViewModel = new ViewModel(array(

				'title'        => 'Manage Trip',
				'trips'        => $this->getDefinedTable(Stock\TripTable::class)->get($trip_id),
				'regions'      => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
				'locationObj'  => $this->getDefinedTable(Administration\LocationTable::class),
				'locationtypeObj'  => $this->getDefinedTable(Acl\LocationtypeTable::class),
				'tripdtlsObj'  => $this->getDefinedTable(Stock\TripDtlsTable::class),
				'data'         => $data,
		));
               $this->layout('layout/accreportlayout');
		return $ViewModel; 
	}
	/**
	 * Activate the Trip Table
	 */
	public function activatetripAction()
	{
		$this->init();
		$active_trip_id = $this->getDefinedTable(Stock\TripTable::class)->getColumn(array('status'=>1),'id');
		if($active_trip_id > 0):
			$data = array(
				'id'       => $active_trip_id,
				'status'   => '0',
				'author'   => $this->_author,
				'modified' => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$this->getDefinedTable(Stock\TripTable::class)->save($data);
		endif;
		$trip_data = array(
				'id'       => $this->_id,
				'effect_date' => $this->_created,
				'status'   => '1',
				'author'   => $this->_author,
				'modified' => $this->_modified,
		);
		$trip_data = $this->_safedataObj->rteSafe($trip_data);
		$result = $this->getDefinedTable(Stock\TripTable::class)->save($trip_data);
		if($result > 0):
			$trip_no = $this->getDefinedTable(Stock\TripTable::class)->getColumn($this->_id,'trip_no');
			$this->flashMessenger()->addMessage('success^ Successfully activated the trip no. '.$trip_no);
			return $this->redirect()->toRoute('stmaster', array('action'=>'trip'));
		else:
			$this->flashMessenger()->addMessage('error^ Failed to activate the trip details');
			return $this->redirect()->toRoute('stmaster', array('action'=>'trip'));
		endif;
	}
	/**
	 * invoice defination field
	 */
	public function invdeffieldAction(){
		$this->init();
		
		return new ViewModel(array(
				'title'			=> 'Invoice Defination Fields',
				'inv_def_fields'=> $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->getAll(),
		));
	}
	
	/**
	 * add purchase statusaction
	 */
	public function addinvdeffieldAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$data = array(
					'def_field' => $form['def_field'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->save($data);
	
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added new invoice defination field ".$form['def_field']);
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to add new invoice defination field");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'invdeffield'));
		}
		$ViewModel = new ViewModel();
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * edit purchase status Action
	 **/
	public function editinvdeffieldAction()
	{
		$this->init();
	
		if($this->getRequest()->isPost())
		{
			$form=$this->getRequest()->getPost();
			$data=array(
					'id' => $this->_id,
					'def_field' => $form['def_field'],
					'description' => $form['description'],
					'author' =>$this->_author,
					'modified' =>$this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->save($data);
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully updated invoice definaiton field ".$form['def_field']);
			else:
				$this->flashMessenger()->addMessage("Failed^ Failed to edit invoice defination field");
			endif;
			return $this->redirect()->toRoute('stmaster',array('action' => 'invdeffield'));
		}
		$ViewModel = new ViewModel(array(
				'inv_def_fields' => $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->get($this->_id),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * Supplier invoice Action
	 */
	public function supinvoiceAction()
	{
		$this->init();
		$selected_supplier='';
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$selected_supplier = $form['supplier'];
		else:
			if(isset($this->_id)):
				$selected_supplier = $this->_id;
			endif;
		endif;
		
		return new ViewModel( array(
				'title'      		=> "Purchase Supplier Invoice",
				'selected_supplier' => $selected_supplier,
				'suppliersObj' 	   	=> $this->getDefinedTable(Accounts\PartyTable::class),
				'inv_definations'	=> $this->getDefinedTable(Purchase\InvDefinationTable::class)->get(array('supplier' => $selected_supplier)),
				'inv_detailsObj'    => $this->getDefinedTable(Purchase\InvDefDetailsTable::class),
				'inv_fieldsObj'     => $this->getDefinedTable(Purchase\InvDefFieldsTable::class),
		) );
	}
	
	/**
	 * Add invoice field
	 */
	public function addinvfieldAction(){
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$supplier_id = $form['supplier_id'];
			$data = array(
					'inv_def'		=> $form['inv_def'],
					'inv_def_field' => $form['inv_def_fields'],
					'order_by'		=> $form['order_no'],
					'author'		=> $this->_author,
					'created'		=> $this->_created,
					'modified'		=> $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->save($data);
			$defination = $this->getDefinedTable(Purchase\InvDefinationTable::class)->getColumn($form['inv_def'],'defination');
			if($result>0):
				$this->flashMessenger()->addMessage("success^ Successfully added new field to the invoice defination <strong>".$defination."</strong>");
			else:
				$this->flashMessenger()->addMessage("failed^ Failed to add new field to the invoice defination <strong>".$defination."</strong>");
			endif;
			return $this->redirect()->toRoute('stmaster', array('action' => 'supinvoice', 'id' => $supplier_id));
		endif;
		
		$viewModel = new ViewModel(array(
			'title'				=> 'Add Invoice Field For',
			'inv_definations'	=> $this->getDefinedTable(Purchase\InvDefinationTable::class)->get($this->_id),
			'inv_def_fields'	=> $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->getAll(),
			'inv_def_detailObj' => $this->getDefinedTable(Purchase\InvDefDetailsTable::class),
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}
	
	/**
	 * save the order of the fields in inv_def_details
	 * pass inv_def id
	 */
	public function fieldorderAction(){
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$supplier_id = $form['supplier_id'];
			$detail = $form['detail'];
			$order = $form['order_by'];
			
			for($i=0; $i < sizeof($order);$i++):
				if(isset($order[$i]) && $order[$i]>0):
					$data = array(
							'id'	   => $detail[$i],
							'order_by' => $order[$i],
							'author'   => $this->_author,
							'modified' => $this->_modified,
					);
					$data = $this->_safedataObj->rteSafe($data);
					$result = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->save($data);
				endif;
			endfor;
			$defination = $this->getDefinedTable(Purchase\InvDefinationTable::class)->getColumn($form['defination'],'defination');
			if($result>0):
				$this->flashMessenger()->addMessage("success^ Successfully changed the order of the invoice fields of <strong>".$defination."</strong>");
			else:
				$this->flashMessenger()->addMessage("failed^ Failed to change the order of invoice fields of <strong>".$defination."</strong>");
			endif;
			return $this->redirect()->toRoute('stmaster', array('action' => 'supinvoice', 'id' => $supplier_id));
		endif;
	}
	
	/**
	 * add invoice defination for a supplier
	 */
	public function addinvdefAction(){
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$data = array(
					'supplier'	=> $form['supplier'],
					'defination'=> $form['defination'],
					'author'	=> $this->_author,
					'created'	=> $this->_created,
					'modified'	=> $this->_modified, 
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\InvDefinationTable::class)->save($data);
			if($result>0):
				$field = $form['status'];
				for($i=0; $i<sizeof($field);$i++):
					if(isset($field[$i]) && $field[$i]>0):
						$detail = array(
								'inv_def' 		=> $result,
								'inv_def_field' => $field[$i],
								'order_by'		=> '',
								'author'		=> $this->_author,
								'created'		=> $this->_created,
								'modified'		=> $this->_modified,
						);
					$detail = $this->_safedataObj->rteSafe($detail);
					$detail_result = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->save($detail);
					endif;
				endfor;
			endif;
			
			if($result > 0):
				$this->flashMessenger()->addMessage("success^ Successfully added new invoice defination <strong>".$form['defination']."</strong>");
			else:
				$this->flashMessenger()->addMessage("failed^ Unsuccessful to add new invoice defination");
			endif;
			return $this->redirect()->toRoute('stmaster', array('action' => 'supinvoice', 'id' => $form['supplier']));
		endif;
		
		$inv_definations = $this->getDefinedTable(Purchase\InvDefinationTable::class)->get(array('supplier' => $this->_id));
		foreach($inv_definations as $inv_defination):
			extract($inv_defination);
		endforeach;
		
		$inv_def_details = $this->getDefinedTable(Purchase\InvDefDetailsTable::class)->get(array('d.inv_def' => $id));
		
		return new ViewModel(array(
				'title'				=> 'New Invoice Defination',
				'selected_supplier'	=> $this->_id,
				'inv_def_fields'	=> $this->getDefinedTable(Purchase\InvDefFieldsTable::class)->getAll(),
				'inv_def_details'   => $inv_def_details,
				'suppliers'			=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>'1')),
		));
	}
	
	/**
	 * Edit invoice defination
	 */
	public function editinvdefAction(){
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$supplier_id = $form['supplier_id'];
			$data = array(
					'id'           => $form['defn_id'],
					'defination'   => $form['defination'],
					'author'	   => $this->_author,
					'modified'	   => $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Purchase\InvDefinationTable::class)->save($data);
			if($result>0):
				$this->flashMessenger()->addMessage("success^ Successfully updated invoice defination name from <strong>".$form['old_defn']."</strong> to <strong>".$form['defination']."</strong>");
			else:
				$this->flashMessenger()->addMessage("failed^ Failed to update invoice defination");
			endif;
			return $this->redirect()->toRoute('stmaster', array('action' => 'supinvoice', 'id' => $supplier_id));
		endif;
		
		$viewModel = new ViewModel(array(
			'title'				=> 'Edit Invoice Defination',
			'inv_definations'	=> $this->getDefinedTable(Purchase\InvDefinationTable::class)->get($this->_id),
		));
		$viewModel->setTerminal(True);
		return $viewModel;
	}
        /**
	* contractor agreements
	*
	*/
	public function contagreementAction(){
		$this->init();
		
		return new ViewModel(array(
			'title' => 'Contractor Agreement',
			'conagreements' => $this->getDefinedTable(Stock\ContractorAgreementTable::class)->getAll(),
			'contractorObj' => $this->getDefinedTable(Accounts\PartyTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		));
	}
	/**
	 * Add contractor agreement
	 */
	public function addcontagreementAction(){
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			$worktype = $form['work_type'];
			$charge = $form['charge'];
			$remark = $form['remarks'];
			
			$results = $this->getDefinedTable(Stock\ContractorAgreementTable::class)->getMaxSerial('CA');
			$pltp_no_list = array();
			foreach($results as $result):
				array_push($pltp_no_list, substr($result['agreement_no'], 2));
			endforeach;
			$next_serial = max($pltp_no_list) + 1;
				
			switch(strlen($next_serial)){
				case 1: $next_dc_serial = "00".$next_serial;  break;
				case 2: $next_dc_serial = "0".$next_serial;   break;
				default: $next_dc_serial = $next_serial;       break;
			}
			$agreement_no = "CA".$next_dc_serial;
			$data = array(
					'agreement_no'		=> $agreement_no,
					'agreement_date'	=> $form['agreement_date'],
					'contractor'	=> $form['contractor'],
					'location'      => $form['location'],
					'activity'      => $form['activity'],
					'from_date'		=> $form['from_date'],
					'to_date'		=> $form['to_date'],
					'remark'		=> $form['note'],
					'author'		=> $this->_author,
					'created'		=> $this->_created,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ContractorAgreementTable::class)->save($data);
			if($result>0):
				for($i=0; $i<sizeof($worktype); $i++):
					$data2 = array(
							'con_agreement' => $result,
							'work_type'	 	=> $worktype[$i],
							'charge' 		=> $charge[$i],
							'remark' 		=> $remark[$i],
							'author'		=> $this->_author,
							'created'		=> $this->_created,
					);
					$data2 = $this->_safedataObj->rteSafe($data2);
					$result2 = $this->getDefinedTable(Stock\ConAgreementDtlsTable::class)->save($data2);
				endfor;
			endif;
			
			if($result2):
				$this->flashMessenger()->addMessage("success^ Successfully added Contractor Agreement");
			else:
				$this->flashMessenger()->addMessage("failed^ Failed to add Contractor Agreement");
			endif;
			return $this->redirect()->toRoute('stmaster', array('action' => 'contagreement'));
		endif;
		
		return new ViewModel(array(
			'title'				=> 'Add Contractor Agreement',
			'contractors'	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role' => '2')),
			'locations'	=> $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'worktypes' => $this->getDefinedTable(Purchase\WorkTypeTable::class)->getAll(),
		));
	}
	/**
	 * Edit contractor agreement
	 */
	public function editconagreementAction(){
		$this->init();
		
		if($this->getRequest()->isPost()):
			$form = $this->getRequest()->getPost();
			//echo"<pre>";print_r($form);exit;
			$worktype = $form['work_type'];
			$charge = $form['charge'];
			$remark = $form['remarks'];
			$agreementdtls = $form['agreementdtls'];
			
			$data = array(
					'id'		=> $this->_id,
					'agreement_no'		=> $form['agreement_no'],
					'agreement_date'	=> $form['agreement_date'],
					'contractor'	=> $form['contractor'],
					'location'      => $form['location'],
					'activity'      => $form['activity'],
					'from_date'		=> $form['from_date'],
					'to_date'		=> $form['to_date'],
					'remark'		=> $form['note'],
					'author'		=> $this->_author,
					'modified'		=> $this->_modified,
			);
			$data = $this->_safedataObj->rteSafe($data);
			$result = $this->getDefinedTable(Stock\ContractorAgreementTable::class)->save($data);
			if($result>0):
				$delete_rows = $this->getDefinedTable(Stock\ConAgreementDtlsTable::class)->getNotIn($agreementdtls, array('con_agreement' => $result));
			
				$dtl_id = 0;
				for($i=0; $i<sizeof($worktype); $i++):
					if($dtl_id != $agreementdtls[$i]):
					
						$dtl_id = $agreementdtls[$i];
						$data2 = array(
								'id' => $agreementdtls[$i],
								'con_agreement' => $result,
								'work_type'	 	=> $worktype[$i],
								'charge' 		=> $charge[$i],
								'remark' 		=> $remark[$i],
								'author'		=> $this->_author,
								'modified'		=> $this->_modified,
						);
					else:
						$data2 = array(
								'con_agreement' => $result,
								'work_type'	 	=> $worktype[$i],
								'charge' 		=> $charge[$i],
								'remark' 		=> $remark[$i],
								'author'		=> $this->_author,
								'created'		=> $this->_created,
						);
					endif;
					$data2 = $this->_safedataObj->rteSafe($data2);
					$result2 = $this->getDefinedTable(Stock\ConAgreementDtlsTable::class)->save($data2);
				endfor;
				foreach($delete_rows as $delete_row):
					//echo $delete_row['id'];
				 	$this->getDefinedTable(Stock\ConAgreementDtlsTable::class)->remove($delete_row['id']);
				endforeach;
			endif;
			
			if($result2):
				$this->flashMessenger()->addMessage("success^ Successfully Edited Contractor Agreement");
			else:
				$this->flashMessenger()->addMessage("failed^ Failed to Edit Contractor Agreement");
			endif;
			return $this->redirect()->toRoute('stmaster', array('action' => 'contagreement'));
		endif;
		
		return new ViewModel(array(
			'title'				=> 'Edit Contractor Agreement',
			'conagreements'	=> $this->getDefinedTable(Stock\ContractorAgreementTable::class)->get(array('ca.id'=>$this->_id)),
			'conagreementdtlObj'	=> $this->getDefinedTable(Stock\ConAgreementDtlsTable::class),
			'contractors'	=> $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role' => '2')),
			'locations'	=> $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'worktypes' => $this->getDefinedTable(Purchase\WorkTypeTable::class)->getAll(),
			'chargesObj' => $this->getDefinedTable(Stock\ChargesTaxTable::class),
		));
	}
	/**
	 * view contractor agreement details
	 *
	 */
	 public function conagreementdetailsAction(){
		$this->init();
		//echo $this->_id; exit;
		return new ViewModel(array(
			'title'		=> 'Contractor Agreement',
			'conagreementrow'	=> $this->getDefinedTable(Stock\ContractorAgreementTable::class)->get(array('ca.id'=>$this->_id)),
			'conagreementdtl'	=> $this->getDefinedTable(Stock\ConAgreementDtlsTable::class)->get(array('con_agreement' => $this->_id)),
			'userObj' => $this->getDefinedTable(Acl\UsersTable::class),
		));
	 }
	/**
	 * getpoitemdetails Action
	 * get the po items details on change of item
	 */
	public function getchargesAction()
	{
		$form = $this->getRequest()->getPost();
		$worktype_id = $form['worktype'];
		$charge_id = $this->getDefinedTable(Purchase\WorkTypeTable::class)->getColumn(array('id' => $worktype_id),'charge_tax');
		$charge_details = $this->getDefinedTable(Stock\ChargesTaxTable::class)->get(array('id' => $charge_id));
		
		$charges.="<option value=''></option>";
		foreach($charge_details as $chdtls):
			$charges.="<option value='".$chdtls['id']."'>".$chdtls['value']."</option>";
		endforeach;
		
		echo json_encode(array(
				'charges'   => $charges,
				'charge'   => $charge_id,
		));
		exit;
	}
}
