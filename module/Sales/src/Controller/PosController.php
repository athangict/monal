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
use Stock\Model As Stock;
use Sales\Model As Sales;
use Hr\Model As Hr;
class PosController extends AbstractActionController
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
	protected $_userloc; //location of the current user
	

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
		
	
		if(!isset($this->_userloc)){
			$this->_userloc = $this->_user->location;  
		}
		
		$this->_safedataObj = $this->safedata();
		$this->_connection = $this->_container->get('Laminas\Db\Adapter\Adapter')->getDriver()->getConnection();

	}
	
        //check if eos or not
	public function eoscheckdateAction()
	{
		$this->init();
		$form = $this->getRequest()->getPost();
		
		$dated = $form['date_val'];
		$location = $form['sales_loc'];
		
		$date_add = $this->getDefinedTable(Sales\SalesTable::class)->getMaxTranDate(array('location' => $location),'sales_date');
		$date_add = strtotime($date_add);
		$date_add = strtotime("+1 day", $date_add);
		$date_add = date('Y-m-d', $date_add);
		
		$check = $this->getDefinedTable(Sales\SalesTable::class)->getColumn(array('sales_date' => $dated,'location' => $location),'transaction');
		$date = ($check > 0)?$date_add:$dated;
		$booln = ($check > 0)?'1':'0';
		echo json_encode(array(
					'dated' => $date,
					'booln' => $booln,
			));
		exit;
	}
	/**
	 * Action for getting Accounts
	 */
	public function getptypeAction()
	{
		//$this->init();
		
		$this->init();
		
		$form = $this->getRequest()->getPost();
		$p_type = $form['pt_type'];
		    $customerObj = $this->getDefinedTable(Accounts\PartyTable::class);
			$cus_type_id = $customerObj->getColumn(array('id'=>$p_type,'role'=>8),'p_type');
			$custypeObj = $this->getDefinedTable(Accounts\PartyTypeTable::class);
			$ptypes = $custypeObj->get(array('id'=>$cus_type_id));
			$ptype.="<option value=''></option>";
			foreach($ptypes as $ptype):
				$ptype.="<option value='".$ptype['id']."'>".$ptype['code']."</option>";
			endforeach;
		echo json_encode(array(
				'fp_type' => $ptype,
		));
		exit;
	}
	/**
	 * index action of Sales
	 */
	public function indexAction()
	{
		$this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			
			$year = $form['year'];
			$month = $form['month'];
			if(strlen($month)==1){
				$month = '0'.$month;
			}
			$location = $form['location'];
		}else{
			$month = date('m');
			$year = date('Y');
			
			$location = $this->_userloc;
			$location = (in_array($location,$admin_loc_array))?$location:'-1'; 
		}
		$minYear = $this->getDefinedTable(Sales\SalesTable::class)->getMin('sales_date');
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		$data = array(
				'year' => $year,
				'month' => $month,
				'minYear' => $minYear,
				'location' => $location,
		);
		$salesrecord = $this->getDefinedTable(Sales\SalesTable::class)->getDateWise('sales_date',$year,$month,$location);
		return new ViewModel(array(
				'title' 	  		=> 'Sales',
				'data'        		=> $data,
				'salesrecord' 		=> $salesrecord,
		        'customerObj' 		=> $this->getDefinedTable(Accounts\PartyTable::class),
				'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
				'admin_location'	=> $admin_loc_array,
				'account'			=>$this->getDefinedTable(Accounts\BankaccountTable::class),
				'cash'				=>$this->getDefinedTable(Accounts\CashaccountTable::class),
				'admin_role' 		=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role'),
				
		));
	}
	/**
	 * Add Sale action of Sales
	 */
	public function addsalesAction()
	{
		$this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		$sales_no = $this->_id;
		$source_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		if($sales_no != "" || $sales_no != 0 ){
			$sales = $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$sales_no));
			$sales_dtls = $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$sales_no));
			$source_loc = $this->getDefinedTable(Sales\SalesTable::class)->getColumn(array('sales_no'=>$sales_no),'location');
		}
		if($this->getRequest()->isPost()):
    		$form = $this->getRequest()->getPost();
			$source_loc = $form['location']; 
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$machine_no = '0';
    		$date = date('ym',strtotime($form['sales_date']));
			$tmp_SLNo = $location_prefix."SL".$machine_no.$date;
						$results = $this->getDefinedTable(Sales\SalesTable::class)->getMonthlySL($tmp_SLNo);
					
						if(sizeof($results) < 1 ):
							$next_serial = "0001";
						else:
							$sheet_no_list = array();
							foreach($results as $result):
								array_push($sheet_no_list, substr($result['sales_no'], -3));
							endforeach;
							//print_r(max($sheet_no_list));exit;
							$next_serial = max($sheet_no_list) + 1;
						endif;
						switch(strlen($next_serial)){
							case 1: $next_sl_serial = "0000".$next_serial; break;
							case 2: $next_sl_serial = "000".$next_serial;  break;
							case 3: $next_sl_serial = "00".$next_serial;   break;
							case 4: $next_sl_serial = "0".$next_serial;   break;
							default: $next_sl_serial = $next_serial;      break;
						}            			
						$sales_no = $tmp_SLNo.$next_sl_serial;
						//print_r($sales_no);exit;
			$customer = ($form['credit']==1)?$form['customer']:0;
			if($form['credit']=="y"){
				$due_date=$form['due_date'];
			}
			else{
				$due_date='0000-00-00';
			}
			//$due_date = ($form['credit']=="y")?$form['due_date']:'0000-00-00';
			//$payment_type = ($form['credit']==0)?$form['payment_type']:'';
			$account_no = ($form['payment_type']==2 || $form['credit']==0)?$form['account_no']:0;
			if($form['credit']=="n" && $form['payment_type']==0){
				$this->flashMessenger()->addMessage("error^ Failed to add new Sales. Please select Payment type or credit");
				return $this->redirect()->toRoute('pos',array('action' => 'addsales'));
			}
			if($form['payment_type']==2 && ($form['jrnl_no']=="" || $form['phone']==0)){
				$this->flashMessenger()->addMessage("error^ Failed to add new Sales. Please Enter Phone number or jrnl_no");
				return $this->redirect()->toRoute('pos',array('action' => 'addsales'));
			}
			$data = array(			
				'sales_no'=>$sales_no,		
				'sales_date' => $form['sales_date'],
				'location' => $form['location'],
				'credit'=>$form['credit'],
				'customer'=>$form['customer'],
				'payment_type'=>$form['payment_type'],
				'discount'=>$form['discount'],
				'due_date'=>$due_date,
				'account_no'=>$account_no,
				'jrnl_no'=>$form['jrnl_no'],
				'phone'=>$form['phone'],
				'salesperson'=>$form['sales_person'],
				'ref_no'=>$form['ref_no'],
				'status'=>2,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
		);
		$data = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
		if($result > 0):
			$item=$form['item'];
			$uom=$form['basic_uom'];
			$rate=$form['rate'];
			$quantity=$form['quantity'];
			$basic_qty=$form['stock_qty'];
			$amount=$form['amount'];
			for($i=0; $i < sizeof($item); $i++):
				if(isset($item[$i]) && is_numeric($item[$i])):
					$data1 = array(
						'sales' => $result,
						'item' => $item[$i],
						'uom' => $uom[$i],
						'rate' => $rate[$i],
						'quantity' => $quantity[$i],
						'basic_quantity' => $basic_qty[$i],
						'scheme_dtls'	=>1,
						'batch'=>1,
						'free_item'		=>0,
						'free_item_uom'	=>1,
						'discount_qty'	=>0,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
					if($result1 <= 0):
						break;
					endif;
				endif;
			endfor;
			$this->flashMessenger()->addMessage("success^ New Class successfully added");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to add new Class");
		endif;
		return $this->redirect()->toRoute('pos',array('action' => 'viewsales','id'=>$sales_no));			 
			
		endif;
		
		return new ViewModel( array(
				'title'         => 'Sales Entry',
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'employees' 	=> $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author),
				'admin_location' => $admin_loc_array,
				'source_locs'=>$source_locs,
				'group'			=> $this->getDefinedTable(Stock\OpeningStockTable::class),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)-> getAll(),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'accountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),
		));
	}
		/**
	 * Edit Sale action of Sales
	 */
	public function editsalesAction()
	{
		$this->init();
		$employees='';
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		$assigned_act = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'name');//assigned_activity
		$assigned_act_array = explode(',',$assigned_act);
		$employees = $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author);
		
		if($this->getRequest()->isPost()):
    		$form = $this->getRequest()->getPost();
		//print_r($sales_no);exit;
			$customer = ($form['credit']==1)?$form['customer']:0;
			$due_date = ($form['credit']=="y")?$form['due_date']:'0000-00-00';
			//$payment_type = ($form['credit']==0)?$form['payment_type']:'';
			$account_no = ($form['payment_type']==2 || $form['credit']==0)?$form['account_no']:0;

			$quantity=$form['quantity'];
			$basic_qty=$form['stock_qty'];
			
			$ql=0;
			for($q=0;$q<sizeof($basic_qty);$q++):
				if($basic_qty[$q]-$quantity[$q]<0){
					$ql++;
				}
			endfor;
			if($ql>0){
				$this->flashMessenger()->addMessage("error^ Please Check You Quantity and Stock Quantity");
				return $this->redirect()->toRoute('pos',array('action' => 'editsales','id'=>$this->_id));
			}
		
			$data = array(	
				'id'			=>$this->_id,		
				'sales_no'		=>$form['sales_no'],		
				'sales_date' 	=>$form['sales_date'],
				'location' 		=>$form['location'],
				'credit'		=>$form['credit'],
				'customer'		=>$customer,
				'payment_type'	=>$form['payment_type'],
				'jrnl_no'		=>$form['jrnl_no'],
				'phone'			=>$form['phone'],
				'due_date'		=>$due_date,
				'account_no'	=>$account_no,
				'salesperson'	=>$form['sales_person'],
				'ref_no'		=>$form['ref_no'],
				'status'		=>2,
				'discount'		=>$form['discount'],
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
		);
		$data = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
		if($result > 0):
			$id=$form['id'];
			$item=$form['item'];
			$uom=$form['basic_uom'];
			$rate=$form['rate'];
			$quantity=$form['quantity'];
			$basic_qty=$form['stock_qty'];
			$amount=$form['amount'];
			for($i=0; $i < sizeof($id); $i++):
				if(isset($item[$i]) && is_numeric($item[$i])):
					$data1 = array(
						'id'				=> $id[$i],
						'sales' 			=> $result,
						'item' 				=> $item[$i],
						'uom' 				=> $uom[$i],
						'rate' 				=> $rate[$i],
						'quantity' 			=> $quantity[$i],
						'basic_quantity' 	=> $basic_qty[$i],
						'scheme_dtls'		=>1,
						'batch'				=>1,
						'free_item'			=>0,
						'free_item_uom'		=>1,
						'discount_qty'		=>0,
						'author' 			=>$this->_author,
						'created' 			=>$this->_created,
						'modified' 			=>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
					if($result1 <= 0):
						break;
					endif;
				endif;
			endfor;
			if(sizeof($id)<sizeof($item)){
				for($i=sizeof($id); $i < sizeof($item); $i++):
						$data1 = array(
							'sales' 			=> $result,
							'item' 				=> $item[$i],
							'uom' 				=> $uom[$i],
							'rate' 				=> $rate[$i],
							'quantity' 			=> $quantity[$i],
							'basic_quantity' 	=> $basic_qty[$i],
							'scheme_dtls'		=>1,
							'batch'				=>1,
							'free_item'			=>0,
							'free_item_uom'		=>1,
							'discount_qty'		=>0,
							'author' 			=>$this->_author,
							'created' 			=>$this->_created,
							'modified' 			=>$this->_modified,
						);
						$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
					endfor;
			}
			$this->flashMessenger()->addMessage("success^ New Class successfully added");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to add new Class");
		endif;
		return $this->redirect()->toRoute('pos',array('action' => 'index'));			 
			
		endif;
		
		return new ViewModel( array(
				'title'         => 'Edit Sales',
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj' 		=> $this->getDefinedTable(Stock\ItemTable::class),
				'item' 			=> $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'uomObj' 		=> $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' 	=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'customers'		=> $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
				'sales' 		=> $this->getDefinedTable(Sales\SalesTable::class)->get($this->_id),
				'salesdtl' 		=> $this->getDefinedTable(Sales\SalesDetailsTable::class),
				'accounts' 		=> $this->getDefinedTable(Accounts\BankaccountTable::class)->get(array('ba.location'=>[$this->_userloc,1])),
				'cash' 			=> $this->getDefinedTable(Accounts\CashaccountTable::class)->get(array('ca.location'=>[$this->_userloc,1])),
				'employees' 	=> $employees,
				'admin_location' => $admin_loc_array,
				'assigned_act_array' => $assigned_act_array,
				'group'			=> $this->getDefinedTable(Stock\OpeningStockTable::class),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)-> getAll(),
				'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	/**
	 * view sale action
	 */
	public function deleteAction()
	{
		$this->init(); 
		foreach($this->getDefinedTable(Sales\SalesDetailsTable::Class)->get($this->_id) as $salesd);
		foreach($this->getDefinedTable(Sales\SalesTable::Class)->get($salesd['sales']) as $sales);
		$result = $this->getDefinedTable(Sales\SalesDetailsTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ Item deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete item");
			endif;
			//end			
		
			return $this->redirect()->toRoute('pos',array('action' => 'editsales','id'=>$sales['id']));	
	}
	/**
	 * delete sale details action
	 */
	 public function deletesalesdAction()
	{
		$this->init(); 
		foreach($this->getDefinedTable(Sales\SalesDetailsTable::Class)->get($this->_id) as $salesd);
		foreach($this->getDefinedTable(Sales\SalesTable::Class)->get($salesd['sales']) as $sales);
		$result = $this->getDefinedTable(Sales\SalesDetailsTable::Class)->remove($this->_id);
		if($result > 0):

				$this->flashMessenger()->addMessage("success^ Item deleted successfully");
			else:
				$this->_connection->rollback(); // rollback transaction over failure
				$this->flashMessenger()->addMessage("error^ Failed to delete item");
			endif;
			//end			
		
			return $this->redirect()->toRoute('pos',array('action' => 'viewsales','id'=>$sales['sales_no']));	
	}
	/**
	 * view sale action
	 */
	public function viewsalesAction()
	{
		$this->init();
		$locale = 'en_US'; // Change to the desired locale
		$fmt = new \NumberFormatter($locale, \NumberFormatter::SPELLOUT);
		$sales  = $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$this->_id));
		foreach($sales as $sale);
	//	print_r($sale['id']);
		return new ViewModel(array(
				'title' 	  => 'View Sales',
				'wordformat'  =>$fmt,
				'sales' 	  => $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$this->_id)),
				'saledetails' => $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$sale['id'])),
				'itemObj' 	  => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'	  => $this->getDefinedTable(Stock\BatchTable::class),
		        'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		        'customerObj' => $this->getDefinedTable(Accounts\PartyTable::class),
		        'userObj'   => $this->getDefinedTable(Administration\UsersTable::class),
				'userRoleObj'  => $this->getDefinedTable(Acl\RolesTable::class),
				'userID' => $this->_author,
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'accountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),
				'partyObj' => $this->getDefinedTable(Accounts\PartyTable::class),
		));
	}
	/**
	 * confirm Sale Action
	 */
	public function confirmAction()
	{
		$this->init();
		$sale_no=$this->_id;
		$sales = $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$sale_no));
		foreach($sales as $row);
		$this->_connection->beginTransaction();
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$salesen=$this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$form['sales']));
			/**
			 * Generating voucher no
			 */
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'], 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(7,'prefix');
			$date = date('ym',strtotime($row['sales_date']));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 13));
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
			/**
			 * Generating voucher no ended
			 */
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($form['location'],'region');

			foreach($salesen as $sale):
				$openingdtls=$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('location'=>$form['location'],'item'=>$sale['item']));
				$salestable=$this->getDefinedTable(Sales\SalesTable::class)->get($form['sales']);
				foreach($salestable as $salestables);
					$total=$salestables['payment_amount']+($sale['rate']*$sale['quantity']);
				foreach($openingdtls as $openingdtl);
				$item_group=$this->getDefinedTable(Stock\ItemTable::class)->getColumn($sale['item'],'item_group');
				
				if($item_group==64 || $item_group==67){
					$price_id=$this->getDefinedTable(Stock\PriceTable::class)->getMax('id',array('opening'=>$openingdtl['opening_stock']));
					$cp=$this->getDefinedTable(Stock\PriceTable::class)->getColumn($price_id,'weighted_price');
				}
				else{
					$cp=$openingdtl['cost_price'];
				}
				
						$quantity=$openingdtl['quantity']-$sale['quantity'];
						if($openingdtl['sales']==0||$openingdtl['sales']==""):
							$totalsale=$sale['quantity'];
						else:
							$totalsale=$sale['quantity']+$openingdtl['sales'];
						endif;
						$cost=$sale['quantity']*$cp;
					$cost_price=$cost+$salestables['cost_price'];

					$os=$this->getDefinedTable(Stock\OpeningStockTable::class)->get(array('id'=>$openingdtl['opening_stock']));
					foreach($os as $os);
					$squantity=$os['quantity']-$sale['quantity'];

				$data=array(
					'id'=>$form['sales'],
					'payment_amount'=>$total,
					'cost_price'=>$cost_price,
					'received_amount'=>$total,
					'status'=>4,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
				$data=array(
					'id'=>$openingdtl['id'],
					'sales'=>$totalsale,
					'quantity'=>$quantity,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result= $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data);
				$data=array(
					'id'=>$os['id'],
					'quantity'=>$squantity,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result = $this->getDefinedTable(Stock\OpeningStockTable::class)->save($data);
			endforeach;
				foreach($this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$form['sales_no'])) as $sales);
				$data1 = array(
					'voucher_date' => $form['sales_date'],
					'voucher_type' => 7,
					'region'   =>$region,
					'doc_id'   =>"sales",
					'voucher_no' => $voucher_no,
					'remark' => $form['sales_no'],
					'voucher_amount' => str_replace( ",", "",$sales['payment_amount']),
					'status' => 4, // status initiated 
					'author' =>$this->_author,
					'created' =>$this->_created,  
					'modified' =>$this->_modified,
				);
				$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				foreach($this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$sale['item'])) as $itemg);
				if($itemg['item_group']==64){
					$subheadinventory=2136;
					$headincome=152;
					$subheadincome=328;
					$headexpense =188;
					$subheadexpense =1536;
				}
				elseif($itemg['item_group']==69){
					$headincome=189;
					$subheadincome=2805;
					$headpayable=140;
					$subheadpayable=1961;
				}
				elseif($itemg['item_group']==68){
					$headincome=189;
					$subheadincome=2806;
					$headpayable=140;
					$subheadpayable=1964;
				}
				else{
					$subheadinventory=2137;
					$headincome=189;
					$subheadincome=379;
					$headexpense = 178;
					$subheadexpense = 1565;
				}
				
				/* checks if sale entry has discounts or not*/
				if($sales['discount']==0){
					$netamount=$sales['payment_amount'];
					}
					
				else{
						$discountam=$sales['payment_amount']*($sales['discount']/100);
						if($itemg['item_group']==64){
							$tdetailsdata2 = array(
							'transaction' => $resultt,
							'voucher_dates' => $form['sales_date'],
							'voucher_types' => 7,
							'location' => $form['location'],
							'head' =>177,
							'sub_head' =>1698,
							'bank_ref_type' => '',
							'debit' =>$discountam,
							'credit' =>'0.000',
							'ref_no'=> $ref, 
							'activity'=>$form['location'],
							'type' => '1',//user inputted  data
							'status' => 4, // status initiated
							'author' =>$this->_author,
							'created' =>$this->_created,
							'modified' =>$this->_modified,
							);
						}
					else{
						$tdetailsdata2 = array(
						'transaction' => $resultt,
						'voucher_dates' => $form['sales_date'],
						'voucher_types' => 7,
						'location' => $form['location'],
						'head' =>178,
						'sub_head' =>1535,
						'bank_ref_type' => '',
						'debit' =>$discountam,
						'credit' =>'0.000',
						'ref_no'=> $ref, 
						'activity'=>$form['location'],
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
						);
					}
					
				$tdetailsdata2 = $this->_safedataObj->rteSafe($tdetailsdata2);
				$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata2);
									
					$netamount=$sales['payment_amount']-$discountam;
				}
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $form['sales_date'],
					'voucher_types' => 7,
					'location' => $form['location'],
					'head' =>$headincome,
					'sub_head' =>$subheadincome,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>($itemg['item_group']==68 || $itemg['item_group']==69)?$sales['payment_amount']*0.2:$sales['payment_amount'],
					'ref_no'=> '', 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$form['location'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
				$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
					foreach($this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$form['sales_no'])) as $sdd);
					if($sdd['credit']=="n"){
						$ref_no="";
						$ref=$sdd['account_no'];
							if($sdd['payment_type']==1){
								$type=6;
							}
							else if($sdd['payment_type']==2){
								$type=3;
							}
						$subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$ref,'type'=>$type),'id');
						$head = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$ref,'type'=>$type),'head');
					}
					else{
						$ref=$sdd['customer'];
						if($sdd['credit']=="y"){$headtype=10;}else{$headtype=19;}
						$head = $this->getDefinedTable(Accounts\SubheadTable::class)->getSubheadfht(array('sh.ref_id'=>$ref,'h.head_type'=>$headtype),'head');
						$subhead = $this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(array('ref_id'=>$ref,'head'=>$head),'id');
						$ref_no=$salestables['ref_no'];
					}
				$tdetailsdata1 = array(
					'transaction' => $resultt,
					'voucher_dates' => $form['sales_date'],
					'voucher_types' => 7,
					'location' => $form['location'],
					'head' => $head,
					'sub_head' => $subhead,
					'bank_ref_type' => '',
					'bank_trans_journal'=>$sales['jrnl_no'],
					'debit' =>$netamount,
					'credit' => '0.00',
					'activity'=>$form['location'],
					'ref_no'=> $ref_no, 
					'against'=>0,
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata1 = $this->_safedataObj->rteSafe($tdetailsdata1);
				$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata1);
				if($itemg['item_group']==68 || $itemg['item_group']==69){
					$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $form['sales_date'],
					'voucher_types' => 7,
					'location' => $form['location'],
					'head' =>$headpayable,
					'sub_head' =>$subheadpayable,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>$sales['payment_amount']*0.8,
					'ref_no'=> '', 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$form['location'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
					$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
					$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				}
				else{
					$tdetailsdata2 = array(
						'transaction' => $resultt,
						'voucher_dates' => $form['sales_date'],
						'voucher_types' => 7,
						'location' => $form['location'],
						'head' =>$headexpense,
						'sub_head' =>$subheadexpense,
						'bank_ref_type' => '',
						'debit' =>$sales['cost_price'],
						'credit' =>'0.000',
						'ref_no'=> $ref, 
						'activity'=>$form['location'],
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$tdetailsdata2 = $this->_safedataObj->rteSafe($tdetailsdata2);
					$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata2);
					$tdetailsdata2 = array(
						'transaction' => $resultt,
						'voucher_dates' => $form['sales_date'],
						'voucher_types' => 7,
						'location' => $form['location'],
						'head' =>9,
						'sub_head' =>$subheadinventory,
						'bank_ref_type' => '', 
						'debit' =>'0.000',
						'activity'=>$form['location'],
						'credit' =>$sales['cost_price'],
						'ref_no'=> $ref, 
						'type' => '1',//user inputted  data
						'status' => 4, // status initiated
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$tdetailsdata2 = $this->_safedataObj->rteSafe($tdetailsdata2);
					$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata2);
						/**Push the transaction id from Transaction Table */
				$data5 = array(
					'id' 	             => $form['sales'],
					'transaction'     => $resultt,
					
				);
				$data5 =  $this->_safedataObj->rteSafe($data5);
				$result5 = $this->getDefinedTable(Sales\SalesTable::class)->save($data5);
				}
			if($result2>0):
			$this->_connection->commit();
			$this->flashMessenger()->addMessage("success^ Confirmed sale successfully ");
			else:
			$this->flashMessenger()->addMessage("Failed^ Failed to Confirm Sale");	
				$this->flashMessenger()->addMessage("error^ Failed to add new Item");
			endif;
			return $this->redirect()->toRoute('pos',array('action' => 'viewsales','id'=>$form['sales_no']));	
	}
		$ViewModel = new ViewModel(array(
			'title' 	  => 'Confirm Sales',
				'sales'       =>$sales,
		        'stocks' => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
	));

	$ViewModel->setTerminal(True);
	return $ViewModel;
	}
	/**
	 * consumable action of consumable item out
	 */
	public function consumableAction()
	{
		$this->init();
		$year = '';
		$month = '';
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			if(strlen($month)==1){
				$month = '0'.$month;
			}
			$location = $form['location'];
		}else{
			$month = ($month == '')?date('m'):$month;
			$year = ($year == '')? date('Y'):$year;
			
			$location = $this->_userloc;
			$location = (in_array($location,$admin_loc_array))?$location:'-1'; 
		}
		$minYear = $this->getDefinedTable(Sales\SalesTable::class)->getMin('sales_date');
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		$data = array(
				'year' => $year,
				'month' => $month,
				'minYear' => $minYear,
				'location' => $location,
		);
		$salesrecord = $this->getDefinedTable(Sales\SalesTable::class)->getDateWiseConsumable('sales_date',$year,$month,$location,array('i.item_group'=>67));
		return new ViewModel(array(
				'title' 	  		=> 'Consumable Item',
				'data'        		=> $data,
				'salesrecord' 		=> $salesrecord,
		        'customerObj' 		=> $this->getDefinedTable(Accounts\PartyTable::class),
				'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
				'admin_location'	=> $admin_loc_array,
				'account'			=>$this->getDefinedTable(Accounts\BankaccountTable::class),
				'cash'				=>$this->getDefinedTable(Accounts\CashaccountTable::class),
				'itemObj'			=>$this->getDefinedTable(Stock\ItemTable::class),
				'admin_role' 		=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role'),
				
		));
	}
	/**
	 * Add Consumable action of consumable item out
	 */
	public function addconsumableAction()
	{
		$this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		$sales_no = $this->_id;
		$source_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		if($this->getRequest()->isPost()):
    		$form = $this->getRequest()->getPost();
			$source_loc = $form['location']; 
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$machine_no = '0';
    		$date = date('ym',strtotime($form['sales_date']));
			$tmp_SLNo = $location_prefix."CI".$machine_no.$date;
						$results = $this->getDefinedTable(Sales\SalesTable::class)->getMonthlySL($tmp_SLNo);
					
						if(sizeof($results) < 1 ):
							$next_serial = "0001";
						else:
							$sheet_no_list = array();
							foreach($results as $result):
								array_push($sheet_no_list, substr($result['sales_no'], -3));
							endforeach;
							//print_r(max($sheet_no_list));exit;
							$next_serial = max($sheet_no_list) + 1;
						endif;
						switch(strlen($next_serial)){
							case 1: $next_sl_serial = "0000".$next_serial; break;
							case 2: $next_sl_serial = "000".$next_serial;  break;
							case 3: $next_sl_serial = "00".$next_serial;   break;
							case 4: $next_sl_serial = "0".$next_serial;   break;
							default: $next_sl_serial = $next_serial;      break;
						}            			
						$sales_no = $tmp_SLNo.$next_sl_serial;
						//print_r($sales_no);exit;
			$data = array(			
				'sales_no'=>$sales_no,		
				'sales_date' => $form['sales_date'],
				'location' => $form['location'],
				'credit'=>"n",
				'customer'=>0,
				'payment_type'=>0,
				'discount'=>0,
				'due_date'=>"0000-00-00",
				'account_no'=>0,
				'jrnl_no'=>0,
				'phone'=>0,
				'salesperson'=>$form['sales_person'],
				'ref_no'=>0,
				'status'=>2,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
		);
		$data = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
		if($result > 0):
			$item=$form['item'];
			$uom=$form['basic_uom'];
			$rate=$form['rate'];
			$quantity=$form['quantity'];
			$basic_qty=$form['stock_qty'];
			$amount=$form['amount'];
			for($i=0; $i < sizeof($item); $i++):
				if(isset($item[$i]) && is_numeric($item[$i])):
					$data1 = array(
						'sales' => $result,
						'item' => $item[$i],
						'uom' => $uom[$i],
						'rate' => $rate[$i],
						'quantity' => $quantity[$i],
						'basic_quantity' => $basic_qty[$i],
						'scheme_dtls'	=>1,
						'batch'=>1,
						'free_item'		=>0,
						'free_item_uom'	=>1,
						'discount_qty'	=>0,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
					if($result1 <= 0):
						break;
					endif;
				endif;
			endfor;
			$this->flashMessenger()->addMessage("success^ New Class successfully added");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to add new Class");
		endif;
		return $this->redirect()->toRoute('pos',array('action' => 'viewconsumable','id'=>$sales_no));			 
			
		endif;
		
		return new ViewModel( array(
				'title'         => 'Consumable Item ',
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'employees' 	=> $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author),
				'admin_location' => $admin_loc_array,
				'source_locs'=>$source_locs,
				'group'			=> $this->getDefinedTable(Stock\OpeningStockTable::class),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)-> get(67),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'accountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),
		));
	}
	/**
	 * Edit Consumable action for consumbale item out
	 */
	public function editconsumableAction()
	{
		$this->init();
		$employees='';
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		$assigned_act = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'name');//assigned_activity
		$assigned_act_array = explode(',',$assigned_act);
		$employees = $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author);
		if($this->getRequest()->isPost()):
    		$form = $this->getRequest()->getPost();
			$data = array(	
				'id'			=>$this->_id,		
				'sales_no'		=>$form['sales_no'],		
				'sales_date' 	=>$form['sales_date'],
				'location' 		=>$form['location'],
				'salesperson'	=>$form['sales_person'],
				'status'		=>2,
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
		);
		$data = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
		if($result > 0):
			$id=$form['id'];
			$item=$form['item'];
			$uom=$form['basic_uom'];
			$rate=$form['rate'];
			$quantity=$form['quantity'];
			$basic_qty=$form['stock_qty'];
			$amount=$form['amount'];
			for($i=0; $i < sizeof($id); $i++):
					$data1 = array(
						'id' 	=> $id[$i],
						'sales' => $result,
						'item' => $item[$i],
						'uom' => $uom[$i],
						'rate' => $rate[$i],
						'quantity' => $quantity[$i],
						'basic_quantity' => $basic_qty[$i],
						'scheme_dtls'	=>1,
						'batch'=>1,
						'free_item'		=>0,
						'free_item_uom'	=>1,
						'discount_qty'	=>0,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
					if($result1 <= 0):
						break;
				endif;
			endfor;
			if(sizeof($id)<sizeof($item)){
				for($i=$id; $i < sizeof($item); $i++):
					$data1 = array(
						'sales' => $result,
						'item' => $item[$i],
						'uom' => $uom[$i],
						'rate' => $rate[$i],
						'quantity' => $quantity[$i],
						'basic_quantity' => $basic_qty[$i],
						'scheme_dtls'	=>1,
						'batch'=>1,
						'free_item'		=>0,
						'free_item_uom'	=>1,
						'discount_qty'	=>0,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
					if($result1 <= 0):
						break;
				endif;
			endfor;
			}
			$this->flashMessenger()->addMessage("success^ New Class successfully added");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to add new Class");
		endif;
		foreach($this->getDefinedTable(Sales\SalesTable::class)->get($result) as $sales);
		return $this->redirect()->toRoute('pos',array('action' => 'viewconsumable','id'=>$sales['sales_no']));			 
			
		endif;
		
		return new ViewModel( array(
				'title'         => 'Edit Consumable',
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj' 		=> $this->getDefinedTable(Stock\ItemTable::class),
				'item' 			=> $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'uomObj' 		=> $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' 	=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'customers'		=> $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
				'sales' 		=> $this->getDefinedTable(Sales\SalesTable::class)->get($this->_id),
				'salesdtl' 		=> $this->getDefinedTable(Sales\SalesDetailsTable::class),
				'employees' 	=> $employees,
				'admin_location' => $admin_loc_array,
				'assigned_act_array' => $assigned_act_array,
				'group'			=> $this->getDefinedTable(Stock\OpeningStockTable::class),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)-> get(67),
				'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	/**
	 * view consumable action
	 */
	public function viewconsumableAction()
	{
		$this->init();
		$sales  = $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$this->_id));
		foreach($sales as $sale);
	//	print_r($sale['id']);
		return new ViewModel(array(
				'title' 	  => 'View Sales',
				'sales' 	  => $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$this->_id)),
				'saledetails' => $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$sale['id'])),
				'itemObj' 	  => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'	  => $this->getDefinedTable(Stock\BatchTable::class),
		        'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		        'customerObj' => $this->getDefinedTable(Accounts\PartyTable::class),
		        'userObj'   => $this->getDefinedTable(Administration\UsersTable::class),
				'userRoleObj'  => $this->getDefinedTable(Acl\RolesTable::class),
				'userID' => $this->_author,
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'accountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),
		));
	}
	/**
	 * confirm consumable Action
	 */
	public function confirmconsumableAction()
	{
		$this->init();
		$sale_no=$this->_id;
		$sales = $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$sale_no));
		foreach($sales as $row);
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$salesen=$this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$form['sales']));
			/**
			 * Generating voucher no
			 */
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'], 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(7,'prefix');
			$date = date('ym',strtotime(date('Y-m-d')));
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 13));
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
			/**
			 * Generating voucher no ended
			 */
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($form['location'],'region');
			foreach($salesen as $sale):
				$openingdtls=$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('location'=>$form['location'],'item'=>$sale['item']));
				$salestable=$this->getDefinedTable(Sales\SalesTable::class)->get($form['sales']);
				foreach($salestable as $salestables);
					$total=$salestables['payment_amount']+($sale['rate']*$sale['quantity']);
				foreach($openingdtls as $openingdtl);
						$quantity=$openingdtl['quantity']-$sale['quantity'];
						if($openingdtl['sales']==0||$openingdtl['sales']==""):
							$totalsale=$sale['quantity'];
						else:
							$totalsale=$sale['quantity']+$openingdtl['sales'];
						endif;
						$cost=$sale['quantity']*$openingdtl['cost_price'];
					$cost_price=$cost+$salestables['cost_price'];

					$os=$this->getDefinedTable(Stock\OpeningStockTable::class)->get(array('id'=>$openingdtl['opening_stock']));
					foreach($os as $os);
					$squantity=$os['quantity']-$sale['quantity'];

				$data=array(
					'id'=>$form['sales'],
					'payment_amount'=>$total,
					'cost_price'=>$cost_price,
					'status'=>4,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
				$data=array(
					'id'=>$openingdtl['id'],
					'sales'=>$totalsale,
					'quantity'=>$quantity,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result= $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data);
				$data=array(
					'id'=>$os['id'],
					'quantity'=>$squantity,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result = $this->getDefinedTable(Stock\OpeningStockTable::class)->save($data);
			endforeach;
				foreach($this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$form['sales_no'])) as $sales);
				$data1 = array(
					'voucher_date' => $form['sales_date'],
					'voucher_type' => 7,
					'region'   =>$region,
					'doc_id'   =>"consumable item",
					'voucher_no' => $voucher_no,
					'remark' => $form['sales_no'],
					'voucher_amount' => str_replace( ",", "",$sales['payment_amount']),
					'status' => 4, // status initiated 
					'author' =>$this->_author,
					'created' =>$this->_created,  
					'modified' =>$this->_modified,
				);
				$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $form['sales_date'],
					'voucher_types' => 7,
					'location' => $form['location'],
					'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(2138,'head'),
					'sub_head' =>2138,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>$sales['payment_amount'],
					'ref_no'=> '', 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$form['location'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
				$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				$tdetailsdata2 = array(
					'transaction' => $resultt,
					'voucher_dates' => $form['sales_date'],
					'voucher_types' => 7,
					'location' => $form['location'],
					'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(1727,'head'),
					'sub_head' =>1727,
					'bank_ref_type' => '',
					'debit' =>str_replace( ",", "",$sales['payment_amount']),
					'activity'=>$form['location'],
					'credit' =>'0.000',
					'ref_no'=> $ref, 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata2 = $this->_safedataObj->rteSafe($tdetailsdata2);
				$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata2);
			if($result2>0):
			$this->flashMessenger()->addMessage("success^ New Item Category successfully added");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to add new Item");
			endif;
			return $this->redirect()->toRoute('pos',array('action' => 'viewconsumable','id'=>$form['sales_no']));	
	}
		$ViewModel = new ViewModel(array(
			'title' 	  => 'Confirm Consumable',
				'sales'       =>$sales,
		        'stocks' => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
	));

	$ViewModel->setTerminal(True);
	return $ViewModel;
	}
	/**
	 * jointstamp action of joint stamp out
	 */
	public function jointstampAction()
	{
		$this->init();
		$year = '';
		$month = '';
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$year = $form['year'];
			$month = $form['month'];
			if(strlen($month)==1){
				$month = '0'.$month;
			}
			$location = $form['location'];
		}else{
			$month = ($month == '')?date('m'):$month;
			$year = ($year == '')? date('Y'):$year;
			
			$location = $this->_userloc;
			$location = (in_array($location,$admin_loc_array))?$location:'-1'; 
		}
		$minYear = $this->getDefinedTable(Sales\SalesTable::class)->getMin('sales_date');
		$minYear = ($minYear == "")?date('Y-m-d'):$minYear;
		$minYear = date('Y', strtotime($minYear));
		$data = array(
				'year' => $year,
				'month' => $month,
				'minYear' => $minYear,
				'location' => $location,
		);
		$salesrecord = $this->getDefinedTable(Sales\SalesTable::class)->getDateWiseConsumable('sales_date',$year,$month,$location,array('s.type'=>1));
		return new ViewModel(array(
				'title' 	  		=> 'Joint Stamp',
				'data'        		=> $data,
				'salesrecord' 		=> $salesrecord,
		        'customerObj' 		=> $this->getDefinedTable(Accounts\PartyTable::class),
				'locationObj' 		=> $this->getDefinedTable(Administration\LocationTable::class),
				'admin_location'	=> $admin_loc_array,
				'account'			=>$this->getDefinedTable(Accounts\BankaccountTable::class),
				'cash'				=>$this->getDefinedTable(Accounts\CashaccountTable::class),
				'itemObj'			=>$this->getDefinedTable(Stock\ItemTable::class),
				'admin_role' 		=> $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'role'),
				
		));
	}
	/**
	 * Add joint stamp action of Joint stamp out
	 */
	public function addjointstampAction()
	{
		$this->init();
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		$sales_no = $this->_id;
		$source_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
		if($this->getRequest()->isPost()):
    		$form = $this->getRequest()->getPost();
			$source_loc = $form['location']; 
			$location_prefix = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'],'prefix');
			$machine_no = '0';
    		$date = date('ym',strtotime($form['sales_date']));
			$tmp_SLNo = $location_prefix."JS".$machine_no.$date;
						$results = $this->getDefinedTable(Sales\SalesTable::class)->getMonthlySL($tmp_SLNo);
					
						if(sizeof($results) < 1 ):
							$next_serial = "0001";
						else:
							$sheet_no_list = array();
							foreach($results as $result):
								array_push($sheet_no_list, substr($result['sales_no'], -3));
							endforeach;
							//print_r(max($sheet_no_list));exit;
							$next_serial = max($sheet_no_list) + 1;
						endif;
						switch(strlen($next_serial)){
							case 1: $next_sl_serial = "0000".$next_serial; break;
							case 2: $next_sl_serial = "000".$next_serial;  break;
							case 3: $next_sl_serial = "00".$next_serial;   break;
							case 4: $next_sl_serial = "0".$next_serial;   break;
							default: $next_sl_serial = $next_serial;      break;
						}            			
						$sales_no = $tmp_SLNo.$next_sl_serial;
						//print_r($sales_no);exit;
			$data = array(			
				'sales_no'=>$sales_no,		
				'sales_date' => $form['sales_date'],
				'location' => $form['location'],
				'credit'=>"n",
				'customer'=>0,
				'payment_type'=>0,
				'discount'=>0,
				'due_date'=>"0000-00-00",
				'account_no'=>0,
				'jrnl_no'=>0,
				'phone'=>0,
				'salesperson'=>$form['sales_person'],
				'ref_no'=>0,
				'status'=>2,
				'type'=>1,
				'author' =>$this->_author,
				'created' =>$this->_created,
				'modified' =>$this->_modified,
		);
		$data = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
		if($result > 0):
			$item=$form['item'];
			$uom=$form['basic_uom'];
			$rate=$form['rate'];
			$quantity=$form['quantity'];
			$basic_qty=$form['stock_qty'];
			$amount=$form['amount'];
			for($i=0; $i < sizeof($item); $i++):
				if(isset($item[$i]) && is_numeric($item[$i])):
					$data1 = array(
						'sales' => $result,
						'item' => $item[$i],
						'uom' => $uom[$i],
						'rate' => $rate[$i],
						'quantity' => $quantity[$i],
						'basic_quantity' => $basic_qty[$i],
						'scheme_dtls'	=>1,
						'batch'=>1,
						'free_item'		=>0,
						'free_item_uom'	=>1,
						'discount_qty'	=>0,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
					if($result1 <= 0):
						break;
					endif;
				endif;
			endfor;
			$this->flashMessenger()->addMessage("success^ New Class successfully added");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to add new Class");
		endif;
		return $this->redirect()->toRoute('pos',array('action' => 'viewjointstamp','id'=>$sales_no));			 
			
		endif;
		
		return new ViewModel( array(
				'title'         => 'Consumable Item ',
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'employees' 	=> $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author),
				'admin_location' => $admin_loc_array,
				'source_locs'=>$source_locs,
				'group'			=> $this->getDefinedTable(Stock\OpeningStockTable::class),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)-> get(array('id'=>[69,68,66,65,63])),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'accountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),
		));
	}
		/**
	 * Edit Joint stamp action for joint stamp out
	 */
	public function editjointstampAction()
	{
		$this->init();
		$employees='';
		$admin_locs = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'admin_location');
		$admin_loc_array = explode(',',$admin_locs);
		$assigned_act = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'name');//assigned_activity
		$assigned_act_array = explode(',',$assigned_act);
		$employees = $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author);
		if($this->getRequest()->isPost()):
    		$form = $this->getRequest()->getPost();
			$data = array(	
				'id'			=>$this->_id,		
				'sales_no'		=>$form['sales_no'],		
				'sales_date' 	=>$form['sales_date'],
				'location' 		=>$form['location'],
				'salesperson'	=>$form['sales_person'],
				'status'		=>2,
				'type'=>1,
				'author' 		=>$this->_author,
				'created' 		=>$this->_created,
				'modified' 		=>$this->_modified,
		);
		$data = $this->_safedataObj->rteSafe($data);
		$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
		if($result > 0):
			$id=$form['id'];
			$item=$form['item'];
			$uom=$form['basic_uom'];
			$rate=$form['rate'];
			$quantity=$form['quantity'];
			$basic_qty=$form['stock_qty'];
			$amount=$form['amount'];
			for($i=0; $i <sizeof($id); $i++):
					$data1 = array(
						'id' 	=> $id[$i],
						'sales' => $result,
						'item' => $item[$i],
						'uom' => $uom[$i],
						'rate' => $rate[$i],
						'quantity' => $quantity[$i],
						'basic_quantity' => $basic_qty[$i],
						'scheme_dtls'	=>1,
						'batch'=>1,
						'free_item'		=>0,
						'free_item_uom'	=>1,
						'discount_qty'	=>0,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
				endfor;
				
			if(sizeof($id)<sizeof($item)){
				for($i=sizeof($id); $i < sizeof($item); $i++):
					$data1 = array(
						'sales' => $result,
						'item' => $item[$i],
						'uom' => $uom[$i],
						'rate' => $rate[$i],
						'quantity' => $quantity[$i],
						'basic_quantity' => $basic_qty[$i],
						'scheme_dtls'	=>1,
						'batch'=>1,
						'free_item'		=>0,
						'free_item_uom'	=>1,
						'discount_qty'	=>0,
						'author' =>$this->_author,
						'created' =>$this->_created,
						'modified' =>$this->_modified,
					);
					$result1 = $this->getDefinedTable(Sales\SalesDetailsTable::class)->save($data1);
			endfor;
			}
			exit;
			$this->flashMessenger()->addMessage("success^ New Class successfully added");
		else:
			$this->flashMessenger()->addMessage("error^ Failed to add new Class");
		endif;
		foreach($this->getDefinedTable(Sales\SalesTable::class)->get($result) as $sales);
		return $this->redirect()->toRoute('pos',array('action' => 'viewjointstamp','id'=>$sales['sales_no']));			 
			
		endif;
		
		return new ViewModel( array(
				'title'         => 'Edit Joint stamp',
				'locationObj'   => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj' 		=> $this->getDefinedTable(Stock\ItemTable::class),
				'item' 			=> $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'uomObj' 		=> $this->getDefinedTable(Stock\UomTable::class),
				'itemuomObj' 	=> $this->getDefinedTable(Stock\ItemUomTable::class),
				'customers'		=> $this->getDefinedTable(Accounts\PartyTable::class)->getAll(),
				'sales' 		=> $this->getDefinedTable(Sales\SalesTable::class)->get($this->_id),
				'salesdtl' 		=> $this->getDefinedTable(Sales\SalesDetailsTable::class),
				'employees' 	=> $employees,
				'admin_location' => $admin_loc_array,
				'assigned_act_array' => $assigned_act_array,
				'group'			=> $this->getDefinedTable(Stock\OpeningStockTable::class),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)-> get(array('item_class'=>31)),
				'itemgroupsObj' => $this->getDefinedTable(Stock\ItemGroupTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
		));
	}
	/**
	 * view Joint stamp action
	 */
	public function viewjointstampAction()
	{
		$this->init();
		$sales  = $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$this->_id));
		foreach($sales as $sale);
	//	print_r($sale['id']);
		return new ViewModel(array(
				'title' 	  => 'View Joint Stamp',
				'sales' 	  => $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$this->_id)),
				'saledetails' => $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$sale['id'])),
				'itemObj' 	  => $this->getDefinedTable(Stock\ItemTable::class),
				'uomObj'	  => $this->getDefinedTable(Stock\UomTable::class),
				'batchObj'	  => $this->getDefinedTable(Stock\BatchTable::class),
		        'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
		        'customerObj' => $this->getDefinedTable(Accounts\PartyTable::class),
		        'userObj'   => $this->getDefinedTable(Administration\UsersTable::class),
				'userRoleObj'  => $this->getDefinedTable(Acl\RolesTable::class),
				'userID' => $this->_author,
				'employeeObj' => $this->getDefinedTable(Hr\EmployeeTable::class),
				'accountObj' => $this->getDefinedTable(Accounts\BankaccountTable::class),
				'cashObj' => $this->getDefinedTable(Accounts\CashaccountTable::class),
		));
	}
	/**
	 * confirm consumable Action
	 */
	public function confirmjointstampAction()
	{
		$this->init();
		$sale_no=$this->_id;
		$sales = $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$sale_no));
		foreach($sales as $row);
		if($this->getRequest()->isPost())
		{
			$form = $this->getRequest()->getPost();
			$salesen=$this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$form['sales']));
			/**
			 * Generating voucher no
			 */
			$loc = $this->getDefinedTable(Administration\LocationTable::class)->getcolumn($form['location'], 'prefix');
			$prefix = $this->getDefinedTable(Accounts\JournalTable::class)->getcolumn(7,'prefix');
			$date = date('ym',$row['sales_date']);
				$tmp_VCNo = $loc.'-'.$prefix.$date;
				
				$results = $this->getDefinedTable(Accounts\TransactionTable::class)->getSerial($tmp_VCNo);
				
				$pltp_no_list = array();
				foreach($results as $result):
					array_push($pltp_no_list, substr($result['voucher_no'], 13));
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
			/**
			 * Generating voucher no ended
			 */
			$region=$this->getDefinedTable(Administration\LocationTable::class)->getColumn($form['location'],'region');
			foreach($salesen as $sale):
				$openingdtls=$this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('location'=>$form['location'],'item'=>$sale['item']));
				$salestable=$this->getDefinedTable(Sales\SalesTable::class)->get($form['sales']);
				foreach($salestable as $salestables);
					$total=$salestables['payment_amount']+($sale['rate']*$sale['quantity']);
				foreach($openingdtls as $openingdtl);
						$quantity=$openingdtl['quantity']-$sale['quantity'];
						if($openingdtl['sales']==0||$openingdtl['sales']==""):
							$totalsale=$sale['quantity'];
						else:
							$totalsale=$sale['quantity']+$openingdtl['sales'];
						endif;
						$cost=$sale['quantity']*$openingdtl['cost_price'];
					$cost_price=$cost+$salestables['cost_price'];

					$os=$this->getDefinedTable(Stock\OpeningStockTable::class)->get(array('id'=>$openingdtl['opening_stock']));
					foreach($os as $os);
					$squantity=$os['quantity']-$sale['quantity'];

				$data=array(
					'id'=>$form['sales'],
					'payment_amount'=>$total,
					'cost_price'=>$cost_price,
					'status'=>4,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result = $this->getDefinedTable(Sales\SalesTable::class)->save($data);	
				$data=array(
					'id'=>$openingdtl['id'],
					'sales'=>$totalsale,
					'quantity'=>$quantity,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result= $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->save($data);
				$data=array(
					'id'=>$os['id'],
					'quantity'=>$squantity,
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				$result = $this->getDefinedTable(Stock\OpeningStockTable::class)->save($data);
			endforeach;
				foreach($this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=>$form['sales_no'])) as $sales);
				$data1 = array(
					'voucher_date' => $form['sales_date'],
					'voucher_type' => 7,
					'region'   =>$region,
					'doc_id'   =>"Joint Stamp Out",
					'voucher_no' => $voucher_no,
					'remark' => $form['sales_no'],
					'voucher_amount' => str_replace( ",", "",$sales['payment_amount']),
					'status' => 4, // status initiated 
					'author' =>$this->_author,
					'created' =>$this->_created,  
					'modified' =>$this->_modified,
				);
				$resultt = $this->getDefinedTable(Accounts\TransactionTable::class)->save($data1);
				$tdetailsdata = array(
					'transaction' => $resultt,
					'voucher_dates' => $form['sales_date'],
					'voucher_types' => 7,
					'location' => $form['location'],
					'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(2137,'head'),
					'sub_head' =>2137,
					'bank_ref_type' => '',
					'debit' =>'0.000',
					'credit' =>$sales['payment_amount'],
					'ref_no'=> '', 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'activity'=>$form['location'],
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata = $this->_safedataObj->rteSafe($tdetailsdata);
				$result1 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata);
				$tdetailsdata2 = array(
					'transaction' => $resultt,
					'voucher_dates' => $form['sales_date'],
					'voucher_types' => 7,
					'location' => $form['location'],
					'head' =>$this->getDefinedTable(Accounts\SubheadTable::class)->getColumn(1565,'head'),
					'sub_head' =>1565,
					'bank_ref_type' => '',
					'debit' =>str_replace( ",", "",$sales['payment_amount']),
					'activity'=>$form['location'],
					'credit' =>'0.000',
					'ref_no'=> $ref, 
					'type' => '1',//user inputted  data
					'status' => 4, // status initiated
					'author' =>$this->_author,
					'created' =>$this->_created,
					'modified' =>$this->_modified,
				);
				$tdetailsdata2 = $this->_safedataObj->rteSafe($tdetailsdata2);
				$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::class)->save($tdetailsdata2);
			if($result2>0):
			$this->flashMessenger()->addMessage("success^ successfully Confirmed Joint Stamp Out");
			else:
				$this->flashMessenger()->addMessage("error^ Failed to Confirm Joint Stamp Out");
			endif;
			return $this->redirect()->toRoute('pos',array('action' => 'viewjointstamp','id'=>$form['sales_no']));	
	}
		$ViewModel = new ViewModel(array(
			'title' 	  => 'Confirm Joint Stamp Out',
				'sales'       =>$sales,
		        'stocks' => $this->getDefinedTable(Stock\OpeningStockTable::class),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
	));

	$ViewModel->setTerminal(True);
	return $ViewModel;
	}
	/**
	 * recipt for the sales made
	 */
	public function salesreciptAction()
	{
	    $this->init();
        
	    $sales_no = $this->_id;
	 
		$ViewModel = new ViewModel(array(
				'title' => 'Sales Receipt',
				'sales' => $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=> $sales_no)),
				'sales_details' => $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$sales_no)),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
		        'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
				'customerObj' => $this->getDefinedTable(Accounts\PartyTable::class),
		));
		$ViewModel->setTerminal(True);
		return $ViewModel;
	}
	
	/**
	 * pos recipt for the sales made
	 */
	public function posreciptAction()
	{
	    $this->init();
        
	    $sales_no = $this->_id;
	 
		return new ViewModel(array(
				'title' => 'Sales Receipt',
				'sales' => $this->getDefinedTable(Sales\SalesTable::class)->get(array('sales_no'=> $sales_no)),
				'sales_details' => $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$sales_no)),
				'locationObj' => $this->getDefinedTable(Administration\LocationTable::class),
				'itemObj' => $this->getDefinedTable(Stock\ItemTable::class),
		        'uomObj' => $this->getDefinedTable(Stock\UomTable::class),
		));
	}

	/**
	 * delete sale action
	 */
		public function deletesaleAction()
	{
		
		$this->init();
		if($this->getRequest()->isPost())
		{
		foreach($this->getDefinedTable(Sales\SalesTable::Class)->get($this->_id) as $sales);
		foreach($this->getDefinedTable(Sales\SalesDetailsTable::Class)->get(array('sales'=>$this->_id)) as $salesd):
			foreach($this->getDefinedTable(Stock\OpeningStockDtlsTable::Class)->get(array('location'=>$sales['location'],'item'=>$salesd['item'])) as $opening);
			if($sales['status']==4){
				$data=array(
					'id'=>$opening['id'],
					'sales'=>$opening['sales']-$salesd['quantity'],
					'quantity'=>$opening['quantity']+$salesd['quantity'],
					'author' => $this->_author,
					'created' => $this->_created,
					'modified' => $this->_modified,	
				);
				//print_r($data);exit;
				$result = $this->getDefinedTable(Stock\OpeningStockDtlsTable::Class)->save($data);	
			}
			$result1= $this->getDefinedTable(Sales\SalesDetailsTable::Class)->remove($salesd['id']);
			endforeach;
			
			$transId=$this->getDefinedTable(Accounts\TransactionTable::Class)->getColumn(array('remark'=>$sales['sales_no']),'id');
		
		foreach($this->getDefinedTable(Accounts\TransactiondetailTable::Class)->get(array('td.transaction'=>$transId)) as $trand){
			$result2 = $this->getDefinedTable(Accounts\TransactiondetailTable::Class)->remove($trand['id']);
		}
		$result3=$this->getDefinedTable(Accounts\TransactionTable::Class)->remove($transId);
		$result4 = $this->getDefinedTable(Sales\SalesTable::Class)->remove($this->_id);
		if($result4 > 0):
				$this->flashMessenger()->addMessage("success^ sale deleted successfully");
			else:
				
				$this->flashMessenger()->addMessage("error^ Failed to delete sale");
			endif;
			//end			
		
			return $this->redirect()->toRoute('pos',array('action' => 'index'));	
		}
		$ViewModel = new ViewModel(array(
				'title' => 'Delete Sales',
				'salesdetls' => $this->getDefinedTable(Sales\SalesTable::Class)->get($this->_id),
			));	
			$ViewModel->setTerminal(True);
			 return $ViewModel;
	}
	/**
	 * cancel sales
	 */
	public function cancelsalesAction()
	{
		$this->init();
	
		$suspended_sales_no = $this->_id;
	
		$sales_id = $this->getDefinedTable(Sales\SalesTable::class)->getColumn(array('sales_no'=>$suspended_sales_no),'id');
		$sales_dtls = $this->getDefinedTable(Sales\SalesDetailsTable::class)->get(array('sales'=>$suspended_sales_no));
		
		foreach($sales_dtls as $sd):
			$this->getDefinedTable(Sales\SalesDetailsTable::class)->remove($sd['id']);
		endforeach;
		
		$result = $this->getDefinedTable(Sales\SalesTable::class)->remove($sales_id);
		if($result > 0):
			$this->flashMessenger()->addMessage("success^ Successfully deleted Sales No. :". $suspended_sales_no);
			return $this->redirect()->toRoute('pos', array('action' =>'draftsales'));
		else:
			$this->flashMessenger()->addMessage("error^ Unsuccessful to delete Sales No. :". $suspended_sales_no);
			return $this->redirect()->toRoute('pos', array('action' =>'draftsales'));
		endif;
	}
	
	/**
	 * getitem - Get item based on location
	 * **/
	public function getItemChangeAction()
	{
		//echo("Hello");
		$form = $this->getRequest()->getPost();
		$locationId = $form['locationId'];
		$subgroup = $form['item_subgroup'];

		$itemOptions = "<option value='-1'>All</option>";
		if($locationId==-1){
			$itemlist = $this->getDefinedTable(Stock\ItemTable::class)->get(array('item_group'=>$subgroup));
		}
		else{
		$itemlist = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->getitems(array('osd.location'=>$locationId,'i.item_group'=>$subgroup));
		}
		//echo($itemlist);
		foreach($itemlist as $item):
			$itemOptions .= "<option value='".$item['id']."'>".$item['name']."</option>";
		endforeach;

		echo json_encode(array(
			'items' => $itemOptions,
		));

		exit;
	}
	/**
	 * getrate - Get rate based on item and location
	 * **/
	public function getrateAction()
	{
		$form = $this->getRequest()->getPost();
		$item =$form['item'];
		$location=$form['location'];
		//console.log($location);
		$itemdetails = $this->getDefinedTable(Stock\OpeningStockDtlsTable::class)->get(array('item'=>$item,'location'=>$location));
		foreach($itemdetails as $items):
		$itemgroup=$this->getDefinedTable(Stock\ItemTable::class)->getColumn($items['item'],'item_group');
		if($itemgroup==67){
			$rate=$items['cost_price'];
		}
		else{
		$rate=$items['selling_price'];
		}
			$quantity=$items['quantity'];
			$cost_price=$items['cost_price'];
			
		endforeach;
		echo json_encode(array(
			'rate' => $rate,
			'quantity' => $quantity,
			'cost_price' => $cost_price,
		));
		exit;
	}
/**
	 * getcustomer - Get customer based on credit and advance
	 * **/
	public function getcustomerAction()
	{
		$form = $this->getRequest()->getPost();
		$id =$form['id'];
		if($id=="y"){
			$customer_list = $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>[11,15,12],'p.location'=>[282,2]));
		}
		 else if($id=="1"){
			$customer_list = $this->getDefinedTable(Accounts\PartyTable::class)->get(array('p.role'=>[6,8,9,10]));
		}
		$customer = "<option value='-1'>None</option>";
		foreach($customer_list as $customer_lists):
			$customer .= "<option value='".$customer_lists['id']."'>".$customer_lists['name']."</option>";
		endforeach;
		echo json_encode(array(
			'customer' => $customer,
		));
		exit;
	}
	/**
	 * getitem - Get item based on location
	 * **/
	public function getuomAction()
	{
		$form = $this->getRequest()->getPost();
		$itemId =$form['itemId'];
		$item = $this->getDefinedTable(Stock\ItemTable::class)->get(array('i.id'=>$itemId));
		$selectedUomId = $item[0]['uom'];
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id' => $selectedUomId));
		foreach($item as $items);
		$uom = $this->getDefinedTable(Stock\UomTable::class)->get(array('id'=>$items['uom']));
		foreach($uom as $uoms):
			$buom=$uoms['code'];
		endforeach;
		echo json_encode(array(
				'uom' => $buom,
		));
		exit;
	}
	public function getamountAction()
	{
		$form = $this->getRequest()->getPost();
		$rate =$form['rate'];
		$quantity=$form['quantity'];
		$amount=$rate*$quantity;
		echo json_encode(array(
			'amount' => $amount,
		));
		exit;
	}
	/**
	 * getacount - Get item based on location
	 * **/
	public function getBCAccountAction()
	{
		$form = $this->getRequest()->getPost();
		$payment =$form['paymentId'];
		$locationID=$form['locationId'];
		if($payment==1){
			$accountlist= $this->getDefinedTable(Accounts\CashaccountTable::class)->get(array('ca.location'=>$locationID));
		}
		else{
			$accountlist= $this->getDefinedTable(Accounts\BankaccountTable::class)->get(array('ba.location'=>$locationID));	
		
		}
		$acc = "<option value='-1'>None</option>";
		foreach($accountlist as $accountlists):
			if($payment==1){
			$acc.="<option value='".$accountlists['id']."'>".$accountlists['cash_account_name']."</option>";
			}
			elseif($payment==2){
				$acc.="<option value='".$accountlists['id']."'>".$accountlists['code'].'-'.$accountlists['account']."</option>";
			}
			else{
				$acc = "<option value='0'>Select</option>";
			}
		endforeach;
		echo json_encode(array(
				'account' => $acc,
		));
		exit;
	}
	/**
	 * getacount - Get item based on location
	 * **/
	public function getUsersAction()
	{
		$form = $this->getRequest()->getPost();
		$location=$form['locationId'];
		$users= $this->getDefinedTable(Administration\UsersTable::class)->get(array('location'=>$location));	
		
		$userlist = "<option value='-1'>All</option>";
		foreach($users as $users):
			$userlist.="<option value='".$users['id']."'>".$users['name']."</option>";
		endforeach;
		echo json_encode(array(
				'users' => $userlist,
		));
		exit;
	}
	/**
	 * getitemsubgroup - Get item based on item class
	 * **/
	public function getItemgroupAction()
	{
		$form = $this->getRequest()->getPost();
		$itemclass=$form['item_class'];
		$itemgroup= $this->getDefinedTable(Stock\ItemGroupTable::class)->get(array('item_class'=>$itemclass));	
		
		$group = "<option value='-1'>All</option>";
		foreach($itemgroup as $itemgroup):
			$group.="<option value='".$itemgroup['id']."'>".$itemgroup['item_group']."</option>";
		endforeach;
		echo json_encode(array(
				'subgroup' => $group,
		));
		exit;
	}
	/*
	* Service Sales  Report 
	*/
	public function salesreportAction()
	{
		{	
			$this->init();
			$array_id = explode("_", $this->_id);
			$user = (sizeof($array_id)>1)?$array_id[0]:'-1';
			$location = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$item = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$item_subgroup = (sizeof($array_id)>1)?$array_id[1]:'-1';
			$item_class = (sizeof($array_id)>1)?$array_id[1]:'-1';
			if($this->getRequest()->isPost())
			{
				$form      	 = $this->getRequest()->getPost();
				$location       = $form['location'];
				$item    = $form['item'];
				$item_subgroup=$form['item_subgroup'];
				$user     	= $form['user'];
				$start_date = $form['start_date'];
				$end_date   = $form['end_date'];
				$item_class   = $form['item_class'];
			}else{
				$location ='-1';
				$item = '-1';
				$user = '-1';
				$item_subgroup='-1';
				$start_date =date('Y-m-d');
				$end_date   = date('Y-m-d');
				$item_class   = '-1';
			}

			$data = array(
				'location'    => $location,
				'item'  => $item,
				'user'  => $user,
				'item_subgroup'=>$item_subgroup,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'item_class'  => $item_class,
			);
			$userloc = $this->getDefinedTable(Administration\UsersTable::class)->getColumn($this->_author,'location');
			$salesTable = $this->getDefinedTable(Sales\SalesDetailsTable::class)->getSalesReportByUsers($data,$start_date,$end_date);
			
			return new ViewModel(array(
				'title' => 'Sales Report',
				'paginator'       => $salesTable,
				'data'            => $data,
				'item' => $this->getDefinedTable(Stock\ItemTable::class),
				'items' => $this->getDefinedTable(Stock\ItemTable::class)->getAll(),
				'userObj' => $this->getDefinedTable(Administration\UsersTable::class),
				'users' => $this->getDefinedTable(Administration\UsersTable::class)->getUsers(),
				'userLoc'         => $this->getDefinedTable(Administration\UsersTable::class)->get($this->_author),
				'locations'       => $this->getDefinedTable(Administration\LocationTable::class)->getlocation($userloc),
				'locationObj'     => $this->getDefinedTable(Administration\LocationTable::class),
				'statusObj' => $this->getDefinedTable(Acl\StatusTable::class),
				'itemgroups' => $this->getDefinedTable(Stock\ItemGroupTable::class)-> getAll(),
				'itemclass' => $this->getDefinedTable(Stock\ItemClassTable::class)-> getAll(),
			)); 
		} 

		
	}
}
