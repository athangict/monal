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

class RentController extends AbstractActionController
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

        $this->_safedataObj = $this->safedata();
        $this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

    }
    //Real Estate Details view page
    public function indexAction()   
    {
          $this->init();
        if($this->getRequest()->isPost()){
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
		}else{
			$year = date('Y');
			$month = (int)date('m');
        }
        $admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
	
        return new ViewModel(array( 
            'title' => 'Rent',
            'year' =>$year,
            'month' =>$month,
            'role'=>$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role'),
            'rent' => $this->getDefinedTable(Realestate\RentTable::class)->getRent($year),
            'rentlist' => $this->getDefinedTable(Realestate\LeasedRentTable::class)->getrentlist(array('bm.location'=> $admin_locs)),
			'admin_locs' => $admin_locs,
			'rentObj' => $this->getDefinedTable(Realestate\RentTable::class),
			'lesserentObj' => $this->getDefinedTable(Realestate\LeasedRentTable::class),
    
        ));
    }
  
    public function generaterentAction()
    {
        $this->init();
        $my = explode('-', $this->_id);
        $tenants = $this->getDefinedTable(Realestate\LeasedRentTable::class)->getTenant(array('lr.status'=>1,'b.location'=>$my[2]));
			foreach($tenants as $row):
			$floor=$this->getDefinedTable(Realestate\FlatTable::class)->getColumn($row['flat'],'floor');
			$flat_status=$this->getDefinedTable(Realestate\FlatTable::class)->getColumn($row['flat'],'flat_status');
			if($flat_status==1){
			   $data = array(
					'building' 	=> $row['building'],
					'floor' 	=> $floor,
					'flat' 		=> $row['flat'],
					'tenant' 	=> $row['lesse_name'],
					'location' 	=> $my[2],
					'rent' 		=> $row['rent'],
					'year' 		=> $my[0],
					'month' 	=> $my[1],
					'ref_no' 	=> $my[1].",".$my[0],
					'status' 	=> 2,
					'author' 	=> $this->_author,
					'created' 	=> $this->_created,
					'modified' 	=> $this->_modified,
				);
				//echo '<pre>';print_r($data);exit;
				$result = $this->getDefinedTable(Realestate\RentTable::class)->save($data);
			}
			endforeach;
            if ($result) {
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
            return $this->redirect()->toRoute('rent',array('action' => 'index'));

    }
    //edit building
    public function rentdetailsAction()
    {
        $this->init();
		if(isset($this->_id) & $this->_id!=0):
			$my = explode('-', $this->_id);
		endif;
		if(sizeof($my)==0):
			$my = array('1'); //default selection
		endif;
	
        $role=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role');
        $admin_locs=$this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
			if($my[2]=="" ){
                $my[2]='-1';
            }else{
                $my[2] = $my[2];
            }
       
        if($role==99 || $role==100){
            $locationlist=$this->getDefinedTable(Realestate\RentTable::class)->getDistinct('r.location');
        }
        else{
            
            $locationlist=$this->getDefinedTable(Administration\LocationTable::class)->get($admin_locs);
        }
       //print_r($locationlist);exit;
        return new ViewModel(array(
            'title' 			=> 'Details',
			'rent' 				=> $this->getDefinedTable(Realestate\RentTable::class)->getRentByMonth($my[0],$my[1],$my[2]),
            'admin_locs'        =>$admin_locs,
            'locationlist'      =>$locationlist,
			'month'				=> $my[1],
			'year'				=> $my[0],
            'location'          => $my[2],
            'building' 		    => $this->getDefinedTable(Realestate\BuildingTable::class),
            'asset' 			=> $this->getDefinedTable(Realestate\BuildingMasterTable::class),
            'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
            'block' 			=> $this->getDefinedTable(Asset\AssettypeTable::class),
			'floorObj' 			=> $this->getDefinedTable(Realestate\FloorMasterTable::class),
			'status' 			=> $this->getDefinedTable(Realestate\FlatstatusTable::class),
			'flat' 				=> $this->getDefinedTable(Realestate\FlatTable::class),
			'party' 			=> $this->getDefinedTable(Accounts\PartyTable::class),
      
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
    //edit rent details
    public function editrentdetailsAction()
    {
        $this->init();
        if ($this->getRequest()->isPost()) {
            $form = $this->getRequest()->getPost();
            $data = array(
				'id'		=> $this->_id,
                'rent' 		=> $form['rent'],
                'author' 	=> $this->_author,
                'created' 	=> $this->_created,
                'modified' 	=> $this->_modified,
            );
            $data = $this->_safedataObj->rteSafe($data);
            $result = $this->getDefinedTable(Realestate\RentTable::class)->save($data);
            if ($result) {
				$rentd=$this->getDefinedTable(Realestate\RentTable::class)->get($this->_id);
				foreach($rentd as $rent);
				$flat=$this->getDefinedTable(Realestate\FlatTable::class)->getColumn(array('id'=>$rent['flat'],'building'=>$rent['building']),'id');
				$data1=array(
					'id'		=> $flat,
					'rent'		=> $form['rent'],
					'author' 	=> $this->_author,
					'created' 	=> $this->_created,
					'modified' 	=> $this->_modified,
				);
				 $results = $this->getDefinedTable(Realestate\FlatTable::class)->save($data1);

				 $tenants=$this->getDefinedTable(Realestate\LeasedRentTable::class)->getColumn(array('flat'=>$rent['flat'],'lesse_name'=>$rent['tenant']),'id');
				$data1=array(
					'id'		=> $tenants,
					'rent'		=> $form['rent'],
					'author' 	=> $this->_author,
					'created' 	=> $this->_created,
					'modified' 	=> $this->_modified,
				);
				 $results = $this->getDefinedTable(Realestate\LeasedRentTable::class)->save($data1);
                $this->flashMessenger()->addMessage("success^ Action Successful");
            } else {

                $this->flashMessenger()->addMessage("error^ Failed to perform the Action, Try again");
            }
           
            return $this->redirect()->toRoute('rent',array('action' => 'rentdetails','id' => $form['year']."-".$form['month'] ));
            
        }
      
       //$bid=$this->getDefinedTable(Realestate\BuildingTable::class)->getColumn($this->_id,'building_id');
        $ViewModel = new ViewModel([
            'title' => 'Edit Rent Details.',
            'rent' => $this->getDefinedTable(Realestate\RentTable::class)->get($this->_id),
            'location' => $this->getDefinedTable(Administration\LocationTable::class)->getAll(),
            'building' => $this->getDefinedTable(Realestate\BuildingTable::class)->get($this->_id),
          'floor' => $this->getDefinedTable(Realestate\FloorMasterTable::class)->getAll(),
           // 'tenants_status' => $this->getDefinedTable(Realestate\StatusTable::class)->getAll(),
        
        
        ]);
		$ViewModel->setTerminal(True);
		return $ViewModel;
 
    }
	/**
	 * Delete flat action
	 */
public function submitAction()
	{
		$this->init(); 
		$my = explode('-', $this->_id);
		$rents=$this->getDefinedTable(Realestate\RentTable::Class)->getRentByMonth($my[0],$my[1],$my[2]);
        //print_r($rents);exit;
		foreach($rents as $rent):
			$data = array(
				'id'		=> $rent['id'],
				'status' 	=> 4,
                'author' 	=> $this->_author,
                'created' 	=> $this->_created,
                'modified' 	=> $this->_modified,
            );
          
            $data = $this->_safedataObj->rteSafe($data);

            $result = $this->getDefinedTable(Realestate\RentTable::class)->save($data);
			$day=date('d');
			$rent_date=$rent['year'].'-'.$rent['month'].'-'.$day;
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($rent['location'], 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(16,'prefix');
			$date = date('ym',strtotime(date('Y-m-d')));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], -3));
				endforeach;
				$next_serial = max($pltp_no_list) + 1;
					
				switch(strlen($next_serial)){
					case 1: $next_dc_serial = "0000".$next_serial; break;
					case 2: $next_dc_serial = "000".$next_serial;  break;
					case 3: $next_dc_serial = "00".$next_serial;   break;
					case 4: $next_dc_serial = "0".$next_serial;    break;
					default: $next_dc_serial = $next_serial;       break;
				}	
				$voucher_no = $tmp_VCNo.$next_dc_serial;
				$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($rent['location'],'region');
				if($rent['status']!=4){
					$data1 = array(
						'voucher_date' =>$rent_date,
						'voucher_type' => 16,
						'region'   =>$region,
						'doc_id'   =>"rent",
						'voucher_no' => $voucher_no,
						'voucher_amount' => str_replace( ",", "",$rent['rent']),
						'status' => 4, // status initiated 
						'author' =>$this->_author,
						'created' =>$this->_created,  
						'modified' =>$this->_modified,
					);
					$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
					$tdetailsdata = array(
						'transaction' => $resultt,
						'voucher_dates' =>$rent_date,
						'voucher_types' => 16,
						'location' => $rent['location'],
						'head' =>191,
						'sub_head' =>366,
						'bank_ref_type' => '',
						'debit' =>'0.000',
						'credit' =>$rent['rent'],
						'ref_no'=> $rent['ref_no'], 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'activity'=>$rent['location'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					$tdetailsdata = array(
						'transaction' => $resultt,
						'voucher_dates' => $rent_date,
						'voucher_types' => 16,
						'location' => $rent['location'],
						'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getSubheadfht(array('sh.ref_id'=>$rent['tenant'],'sh.type'=>2),'head'),
						'sub_head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$rent['tenant'],'type'=>2),'id'),
						'bank_ref_type' => '',
						'debit' =>$rent['rent'],
						'credit' =>'0.000',
						'against' =>'0',
						'ref_no'=> $rent['ref_no'], 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'activity'=>$rent['location'],
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				}
		endforeach;
		if($result):

				$this->flashMessenger()->addMessage("success^ rent submition successful");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to submitted rent");
			endif;
			//end			
		
			return $this->redirect()->toRoute('rent',array('action' => 'rentdetails','id'=>$this->_id));	
	}	
}
