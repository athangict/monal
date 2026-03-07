<?php
namespace Realestate\Controller;

use Acl\Model as Acl;
use Administration\Model as Administration;
use Interop\Container\ContainerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Realestate\Model as Realestate;
use Accounts\Model as Accounts;
use Asset\Model as Asset;
use Hr\Model as Hr;

class BuildingController extends AbstractActionController
{
    private $_container;
    protected $_table; // database table
    protected $_user; // user detail
    protected $_highest_role; // highest_role
    protected $_login_role; // logined user role
    protected $_author; // logined user id 
    protected $_created; // current date to be used as created dated
    protected $_modified; // current date to be used as modified date
    protected $_config; // configuration details
    protected $_dir; // default file directory
    protected $_id; // route parameter id, usally used by crude
    protected $_auth; // checking authentication
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
        if (!$this->_auth->hasIdentity()):
            $this->flashMessenger()->addMessage('error^ You dont have right to access this page!');
            $this->redirect()->toRoute('auth', array('action' => 'login'));
        endif;
        if (!isset($this->_config)) {
            $this->_config = $this->_container->get('Config');
        }
        if (!isset($this->_user)) {
            $this->_user = $this->identity();
        }
        if (!isset($this->_login_id)) {
            $this->_login_id = $this->_user->id;
        }
        if (!isset($this->_login_role)) {
            $this->_login_role = $this->_user->role;
        }
        if (!isset($this->_highest_role)) {
            $this->_highest_role = $this->getDefinedTable(Acl\RolesTable::class)->getMax($column = 'id');
        }

        if (!isset($this->_author)) {
            $this->_author = $this->_user->id;
        }

        $this->_id = $this->params()->fromRoute('id');

        $this->_created = date('Y-m-d H:i:s');
        $this->_modified = date('Y-m-d H:i:s');

        $fileManagerDir = $this->_config['file_manager']['dir'];

        if (!is_dir($fileManagerDir)) {
            mkdir($fileManagerDir, 0777);
        }

        //$this->_dir =realpath($fileManagerDir);
		if(!isset($this->_userloc)){
			$this->_userloc = $this->_user->location;  
		}
        $this->_safedataObj = $this->safedata();
        $this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

    }
    //Real Estate Details view page
    public function indexAction()   
    {
        $this->init();
        //print_r($this->_id);exit;
        return new ViewModel(array(
            'title' => 'Real Estate Details',
            'buildings' => $this->getDefinedTable(Realestate\BuildingTable::class)->get($this->_id),
            'leasedrent' => $this->getDefinedTable(Realestate\LeasedRentTable::class)->get(array('building_id'=>$this->_id)),
            'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
            'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
    
        ));
    }
    public function buildingAction()
    {
        $this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$location = $form['location'];
		}else{
			$location = $this->_userloc;
			$location = (in_array($location,$admin_loc_array))?$location:'-1'; 
		}
		$data = array(
				'location' => $location,
		);
        return new ViewModel(array(
            'title' 			=> " Building ",
            'buildings' 		=> $this->getDefinedTable(Realestate\BuildingTable::class)->getData($data),
            'block' 			=> $this->getDefinedTable(Asset\AssettypeTable::class),
			'data'				=> $data,
			'buildingObj' 		=> $this->getDefinedTable(Realestate\BuildingMasterTable::class),
			'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
			'admin_location'	=> $admin_loc_array,

        ));
    }

    public function addbuildingAction()
    {
        $this->init();
        $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();

            $data = array(
                'block' 		=> $form['block'],
                'asset' 		=> $form['building'],
				'house_no' 		=> $form['house_no'],
				'tot_floor' 	=> $form['tot_floor'],
                'plot_no' 		=> $form['plot_no'],
                'thram_no'	 	=> $form['thram_no'],
                'location' 		=> $form['location'],
                'author' 		=> $this->_author,
                'created' 		=> $this->_created,
                'modified' 		=> $this->_modified,
            );
            //echo '<pre>';print_r($data);exit;
            $data = $this->_safedataObj->rteSafe($data);

            $result = $this->getDefinedTable(Realestate\BuildingTable::class)->save($data);
            if ($result>0) {
				$floor        = $form['floor'];
				$area         = $form['area'];
				$flat_no     = $form['flat_no'];
				$rent     = $form['rent'];
				$status     = $form['flat_status'];
				$remarks      = $form['remarks'];
				for($i=0; $i < sizeof($floor); $i++):
						$floor_details = array(
		      					'building' 		=> $result,
					     		'floor'    		=> $floor[$i],
								'area'     		=> $area[$i],
					     		'flat_no' 		=> $flat_no[$i],
								'rent' 			=> $rent[$i],
					     		'flat_status'  	 	=> $status[$i],
								'remarks' 	 	=> $remarks[$i],
					      		'author'    	=> $this->_author,
					      		'created'   	=> $this->_created,
					      		'modified'  	=> $this->_modified
						);
		     		$floor_details   = $this->_safedataObj->rteSafe($floor_details);
			     	$this->getDefinedTable(Realestate\FlatTable::class)->save($floor_details);	
				endfor;
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
             return $this->redirect()->toRoute('building', array('action' =>'viewbuilding', 'id' => $result));
        }
        return new ViewModel(array(
            'title' => 'Add building.',
            'buildings' => $this->getDefinedTable(Realestate\BuildingTable::class)->getAll(),
            'region' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
            'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
            'source_locs' => $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
            'admin_location' => $admin_loc_array,
            'block' => $this->getDefinedTable(Asset\AssettypeTable::class)->get(array('id'=>[6,7])),
			'floor' => $this->getDefinedTable(Realestate\FloorMasterTable::class)->getAll(),
			'status' => $this->getDefinedTable(Realestate\FlatstatusTable::class)->getAll(),
      
        ));

    }
    //edit building
    public function editbuildingAction()
    {
        $this->init();

        $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
				'id'		=>$this->_id,
                'block' 	=> $form['block'],
                'asset' 	=> $form['building'],
				'house_no' 	=> $form['house_no'],
				'tot_floor' => $form['tot_floor'],
                'plot_no' 	=> $form['plot_no'],
                'thram_no' 	=> $form['thram_no'],
                'location' 	=> $form['location'],
                'author' 	=> $this->_author,
                'created' 	=> $this->_created,
                'modified' 	=> $this->_modified,
            );
            //echo '<pre>';print_r($data);exit;
            $data = $this->_safedataObj->rteSafe($data);

            $result = $this->getDefinedTable(Realestate\BuildingTable::class)->save($data);
			
				$floor     = $form['floor'];
				$id        = $form['flat_id'];
				$area      = $form['area'];
				$flat_no   = $form['flat_no'];
				$rent      = $form['rent'];
				$status    = $form['flat_status'];
				$remarks   = $form['remarks'];
				
				for($i=0; $i < sizeof($id); $i++):
						$floor_details = array(
		      					'building' 		=> $result,
					     		'id'    		=> $id[$i],
								'floor'    		=> $floor[$i],
								'area'     		=> $area[$i],
					     		'flat_no' 		=> $flat_no[$i],
								
								'rent' 			=> $rent[$i],
					     		'flat_status'  	 	=> $status[$i],
								'remarks' 	 	=> $remarks[$i],
					      		'author'    	=> $this->_author,
					      		'created'   	=> $this->_created,
					      		'modified'  	=> $this->_modified
						);
		     		$floor_details   = $this->_safedataObj->rteSafe($floor_details);
			     	$this->getDefinedTable(Realestate\FlatTable::class)->save($floor_details);	
				endfor;
				
				if(sizeof($id)!=sizeof($flat_no)){
				for($i=sizeof($id); $i < sizeof($flat_no); $i++):
					
						$floor_details1 = array(
		      					'building' 		=> $result,
								'floor'    		=> $floor[$i],
								'area'     		=> $area[$i],
					     		'flat_no' 		=> $flat_no[$i],
								'rent' 			=> $rent[$i],
					     		'flat_status'  	 	=> $status[$i],
								'remarks' 	 	=> $remarks[$i],
					      		'author'    	=> $this->_author,
					      		'created'   	=> $this->_created,
					      		'modified'  	=> $this->_modified
						);
		     		$floor_details1   = $this->_safedataObj->rteSafe($floor_details1);
					$this->getDefinedTable(Realestate\FlatTable::class)->save($floor_details1);				     
				endfor;
				}
            if ($result>0) {
			
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
           return $this->redirect()->toRoute('building', array('action' =>'viewbuilding', 'id' => $this->_id));
        }
        return new ViewModel(array(
            'title' 			=> 'Edit building.',
            'buildings' 		=> $this->getDefinedTable(Realestate\BuildingTable::class)->get($this->_id),
            'assetlist' 			=> $this->getDefinedTable(Realestate\BuildingMasterTable::class)->getAll(),
            'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
            'source_locs' 		=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location'),
            'admin_location' 	=> $admin_loc_array,
            'block' 			=> $this->getDefinedTable(Asset\AssettypeTable::class)->get(array('id'=>[6,7])),
			'floor' 			=> $this->getDefinedTable(Realestate\FloorMasterTable::class)->getAll(),
			'flat_status' 			=> $this->getDefinedTable(Realestate\FlatstatusTable::class)->getAll(),
			'flat' 				=> $this->getDefinedTable(Realestate\FlatTable::class)->get(array('building'=>$this->_id)),
      
        ));
    }
	
/**
	 * Delete flat action
	 */
	public function deleteAction()
	{
		$this->init(); 
		$building=$this->getDefinedTable(Realestate\FlatTable::Class)->getColumn($this->_id,'building');
		$result = $this->getDefinedTable(Realestate\FlatTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ Flat deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete flat");
			endif;
			//end			
		
			return $this->redirect()->toRoute('building',array('action' => 'editbuilding','id'=>$building));	
	}
   //view Building details
    public function viewbuildingAction()   
    {
        $this->init();
        
        return new ViewModel(array(
            'title' => 'Real Estate Details',
            'buildings' => $this->getDefinedTable(Realestate\BuildingTable::class)->get($this->_id),
			'flat' => $this->getDefinedTable(Realestate\FlatTable::class),
			'floorObj' => $this->getDefinedTable(Realestate\FloorMasterTable::class),
			'status' => $this->getDefinedTable(Realestate\FlatstatusTable::class),
			'buildingsmaster' => $this->getDefinedTable(Realestate\BuildingMasterTable::class),
			'block' => $this->getDefinedTable(Asset\AssettypeTable::class),
            'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
            'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'employeeObj' =>$this->getDefinedTable(HR\EmployeeTable::class),
    
        ));
    }
    //add details
    public function adddetailsAction()
    {
        $this->init();
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
   
            $data = array(
                'building_id'=> $this->_id,
                'floor' => $form['floor'],
                'region'=> $form['region'],
                'location'=> $form['location'],
                'assetid'=> $form['assetid'],
                'unit_no' => $form['unit_no'],
                'areasqt' => $form['areasqt'],
                //'tenants_status' => $form['tenants_status'],
                'remark' => $form['remark'],
                //'totalrent' => $form['totalrent'],
                'author' => $this->_author,
                'created' => $this->_created,
                'modified' => $this->_modified,
            );
          
            $data = $this->_safedataObj->rteSafe($data);

            $result = $this->getDefinedTable(Realestate\LeasedRentTable::class)->save($data);
            if ($result) {

                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
           
            return $this->redirect()->toRoute('building',array('action' => 'index','id' => $this->_id ));
            
        }
      
       //$bid=$this->getDefinedTable(Realestate\BuildingTable::class)->getColumn($this->_id,'building_id');
        return new ViewModel(array(
            'title' => 'Add buildingDetails.',
            'region' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
            'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
            'building' => $this->getDefinedTable(Realestate\BuildingTable::class)->get($this->_id),
          'floor' => $this->getDefinedTable(Realestate\FloorMasterTable::class)->getAll(),
           // 'tenants_status' => $this->getDefinedTable(Realestate\StatusTable::class)->getAll(),
        
        
        ));
 
    }
    //editdetails
    public function editdetailsAction()
    {
        $this->init();
        //echo '<pre>';print_r($this->_id);exit;
        $id = $this->_id;
        
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
                 'id'  => $id,
                 'building_id'=> $form['building_id'],
                 'floor' => $form['floor'],
                 'region'=> $form['region'],
                 'location'=> $form['location'],
                 'assetid'=> $form['assetid'],
                 'unit_no' => $form['unit_no'],
                 'areasqt' => $form['areasqt'],
                 'remark' => $form['remark'],
                 'author' => $this->_author,
                 'created' => $this->_created,
                 'modified' => $this->_modified,
             );
            //  echo '<pre>';print_r($data);exit;
             $data = $this->_safedataObj->rteSafe($data);

             $result = $this->getDefinedTable(Realestate\LeasedRentTable::class)->save($data);
             if ($result) {
 
                 $this->flashMessenger()->addMessage("success^ Action Successful");
             } else {
 
                 $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
             }
             return $this->redirect()->toRoute('building',array('action' =>  'index','id' => $form['bid']));
         }
        //  echo '<pre>';print_r($data);exit;
         return new ViewModel(array(
             'title' => 'Add buildingDetails.',
              'region' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
             'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
             'leasedrent' => $this->getDefinedTable(Realestate\LeasedRentTable::class)->get($this->_id),
             'floor' => $this->getDefinedTable(Realestate\FloorTable::class)->getAll(),
             'tenants_status' => $this->getDefinedTable(Realestate\FloorTable::class)->getAll(),
            
         ));
    }

    public function landAction()
	{
		$this->init();

        
		return new ViewModel( array(
			'title' => " Land ",
			'lands' => $this->getDefinedTable(Realestate\LandTable::class)->getAll(),
			'regionObj' => $this->getDefinedTable(Administration\RegionTable::class),
			'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
			
		));
	}
	//add leased 
	public function addlandAction()
	{
		$this->init();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$data = array(
				'region' => $form['region'],
				'location' => $form['location'],
				'land_type' => $form['land_type'],
				'exact_location' => $form['exact_location'],
				'area_in_acres' => $form['area_in_acres'],
				'area_in_sqaureft' => $form['area_in_sqaureft'],
				'plot_number' => $form['plot_number'],
				'thram_no' => $form['thram_no'],
				'land' => $form['land'],
				'land_tax' => $form['land_tax'],
                'land_income' => $form['land_income'],
                'pp' => $form['pp'],
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,
			);
			//echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);
			
			$result = $this->getDefinedTable(Realestate\LandTable::class)->save($data);
			if($result){
				$this->flashMessenger()->addMessage("success^ Action Successful");
			}else{
			
				$this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
			}
			return $this->redirect()->toRoute('building',array('action'=>'land'));
		}
		return new ViewModel( array(
			'title' => 'Add land Details.',
			'land' => $this->getDefinedTable(Realestate\LandTable::class)->getAll(),
			'region'       => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),	
			'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),	
			
			
		));
	
	}

   
	//add leased 
	public function editlandAction()
	{
		$this->init();	
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$data = array(
                'id' => $this->_id,
				'region' => $form['region'],
				'location' => $form['location'],
				'land_type' => $form['land_type'],
				'exact_location' => $form['exact_location'],
				'area_in_acres' => $form['area_in_acres'],
				'area_in_sqaureft' => $form['area_in_sqaureft'],
				'plot_number' => $form['plot_number'],
				'thram_no' => $form['thram_no'],
				'land' => $form['land'],
				'land_tax' => $form['land_tax'],
                'land_income' => $form['land_income'],
                'pp' => $form['pp'],
				'author' => $this->_author,
				'created' => $this->_created,
				'modified' => $this->_modified,
			);
			
            //echo '<pre>';print_r($data);exit;
			$data = $this->_safedataObj->rteSafe($data);

			$result = $this->getDefinedTable(Realestate\LandTable::class)->save($data);
			if($result){
			
				$this->flashMessenger()->addMessage("success^ Edit Successful");
			}else{
			
				$this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
			}
			return $this->redirect()->toRoute('building',array('action'=>'land'));
		}
		
		return new ViewModel(array(
			'title' => 'Add Leased Agreement Info.',
			'land' => $this->getDefinedTable(Realestate\LandTable::class)->get($this->_id,),
			'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
			'region' => $this->getDefinedTable(Administration\RegionTable::class)->getAll(),
            
		
	));		
		$ViewModel->setTerminal(true);
		return $ViewModel;
	}

     
    //add tenant
    public function addtenantAction()
    {
        $this->init();
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
                'flat'				=> $form['flat_id'],
				'building' 			=> $form['building'],
				'cid' 				=> $form['cid'],
                'lesse_name' 		=> $form['tenants'],
                'contact' 			=> $form['contact'],
				'agreement_date' 	=> $form['agreement_date'],
				'start_date' 		=> $form['start_date'],
				'end_date' 			=> $form['end_date'],
				'rent'				=> $form['rent'],
				'status' 			=> 1,
                'remarks' 			=> $form['remarks'],
                'author' 			=> $this->_author,
                'created'			=> $this->_created,
                'modified'			=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Realestate\LeasedRentTable::class)->save($data);
            if ($result) {
				$data = array(
                'flat'				=> $form['flat_id'],
				'building' 			=> $form['building'],
				'cid' 				=> $form['cid'],
                'tenant' 			=> $form['tenants'],
                'contact' 			=> $form['contact'],
				'agreement_date' 	=> $form['agreement_date'],
				'start_date' 		=> $form['start_date'],
				'end_date' 			=> $form['end_date'],
				'rent'				=> $form['rent'],
				'status' 			=> 1,
                'remarks' 			=> $form['remarks'],
                'author' 			=> $this->_author,
                'created'			=> $this->_created,
                'modified'			=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Realestate\TenantHistoryTable::class)->save($data);
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {
                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
            return $this->redirect()->toRoute('building',array('action' =>  'view','id' => $form['flat_id']));
        }
        return new ViewModel(array(
            'title' => 'Add Tenents',
            'flat_id' =>$this->_id,
            'flat' => $this->getDefinedTable(Realestate\FlatTable::class)->get($this->_id),
            'tenants' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>17)),
        ));

    }
	//edit tenant
    public function edittenantAction()
    {
        $this->init();
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
				'id'				=>$this->_id,
                'flat' 				=> $form['flat_id'],
				'building' 			=> $form['building'],
                'lesse_name' 		=> $form['tenants'],
                'contact'			=> $form['contact'],
				'agreement_date'	=> $form['agreement_date'],
				'start_date'		=> $form['start_date'],
				'end_date'			=> $form['end_date'],
				'rent'				=> $form['rent'],
				'status'			=> $form['status'],
                'remarks'			=> $form['remarks'],
                'author'			=> $this->_author,
                'created'			=> $this->_created,
                'modified'			=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Realestate\LeasedRentTable::class)->save($data);
            if ($result) {
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {
                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
            return $this->redirect()->toRoute('building',array('action' =>  'view','id' => $form['flat_id']));
        }
        return new ViewModel(array(
            'title' => 'Edit Tenents',
            'flat_id' =>$this->_id,
            'flat' => $this->getDefinedTable(Realestate\FlatTable::class),
			'statu' => $this->getDefinedTable(Acl\StatusTable::class)->get(array('id'=>[20,1])),
			'tenant' => $this->getDefinedTable(Realestate\LeasedRentTable::class)->get($this->_id),
            'party' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>17)),
        ));

    }
	//edit tenant
    public function renewAction()
    {
        $this->init();
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
				'id'				=>$this->_id,
                'flat' 				=> $form['flat_id'],
				'building' 			=> $form['building'],
                'lesse_name' 		=> $form['tenants'],
                'contact'			=> $form['contact'],
				'agreement_date'	=> $form['agreement_date'],
				'start_date'		=> $form['start_date'],
				'end_date'			=> $form['end_date'],
				'rent'				=> $form['rent'],
				'status'			=> 1,
                'remarks'			=> $form['remarks'],
                'author'			=> $this->_author,
                'created'			=> $this->_created,
                'modified'			=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Realestate\LeasedRentTable::class)->save($data);
            if ($result) {
				$data = array(
                'flat'				=> $form['flat_id'],
				'building' 			=> $form['building'],
				'cid' 				=> $form['cid'],
                'tenant' 			=> $form['tenants'],
                'contact' 			=> $form['contact'],
				'agreement_date' 	=> $form['agreement_date'],
				'start_date' 		=> $form['start_date'],
				'end_date' 			=> $form['end_date'],
				'rent'				=> $form['rent'],
				'status' 			=> 1,
                'remarks' 			=> $form['remarks'],
                'author' 			=> $this->_author,
                'created'			=> $this->_created,
                'modified'			=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Realestate\TenantHistoryTable::class)->save($data);
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {
                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
            return $this->redirect()->toRoute('building',array('action' =>  'view','id' => $form['flat_id']));
        }
        return new ViewModel(array(
            'title' => 'Renew Tenents',
            'flat_id' =>$this->_id,
            'flat' => $this->getDefinedTable(Realestate\FlatTable::class),
			'tenant' => $this->getDefinedTable(Realestate\LeasedRentTable::class)->get($this->_id),
            'party' => $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>17)),
        ));

    }

    public function viewAction()   
    {
        $this->init();
       
        return new ViewModel(array(
            'title' => 'Real Estate Details',
            'flat_id' =>$this->_id,
            'flat' => $this->getDefinedTable(Realestate\FlatTable::class)->get($this->_id),
			'status' => $this->getDefinedTable(Realestate\FlatstatusTable::class),
			'floorObj' => $this->getDefinedTable(Realestate\FloorMasterTable::class),
            'leasedrent' => $this->getDefinedTable(Realestate\LeasedRentTable::class),
			'tenant' => $this->getDefinedTable(Accounts\PartyTable::class),
        ));
    }


	public function getlocationAction()
	{		
		$form = $this->getRequest()->getPost();
		$regId = $form['regId'];
		$region = $this->getDefinedTable(Administration\LocationTable::class)->get(array('region'=>$regId));

       
		
		$location = "<option value=''></option>";
		foreach($region as $regions):
			$location.="<option value='".$regions['id']."'>".$regions['location']."</option>";
		endforeach;
		
		echo json_encode(array(
				'location' => $location,
		));
		exit;
    }
   /**
	 * getacount - Get item based on location
	 * **/
	public function getbuildingAction()
	{
		$form = $this->getRequest()->getPost();
		$location=$form['location_id'];
        $type=$form['type'];
		$building= $this->getDefinedTable(Realestate\BuildingMasterTable::class)->get(array('location'=>$location,'type'=>$type));	
		
		$buildinglist = "<option value='-1'></option>";
		foreach($building as $building):
			$buildinglist.="<option value='".$building['id']."'>".$building['name']."</option>";
		endforeach;
		echo json_encode(array(
				'building' => $buildinglist,
		));
		exit;
	}

	
}
