<?php
namespace Purchase;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(  
        		'purchase' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/p[/:action[/:id]]',
        						'constraints' => array(
        								'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'      => '[a-zA-Z0-9_-]*',
        						),
        		
        						'defaults' => array(
        								'controller' => Controller\IndexController::class,
        								'action'     => 'index',
        						),
        				),
        		),
        		
        		/*'purmaster' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/purmaster[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => 'Purchase\Controller\Master',
        								'action'     => 'index',
        						),
        				),
        		),*/
        		
        		'purorder' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/purorder[/:action[/:id]]',
        						'constraints' => array(
        								'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'      => '[a-zA-Z0-9_-]*',
        						),
        		
        						'defaults' => array(
        								'controller' => Controller\PurchaseController::class,
        								'action'     => 'index',
        						),
        				),
        		),		
	            'receipt' => array(
	            		'type'    => 'Segment',
	            		'options' => array(
	            				'route'       => '/receipt[/:action[/:id]]',
	            				'constraints' => array(
	            						'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
	            						'id'       => '[a-zA-Z0-9_-]*',
	            				),
	            				
	            				'defaults' => array(
	            						'controller' => Controller\ReceiptController::class,
	            						'action'     => 'index',
	            				),
	            		),
	            ),
        		'supplier' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/supinv[/:action[/:id]]',
        						'constraints' => array(
        								'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'       => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\SupplierController::class,
        								'action'     => 'index',
        						),
        				),
        		),
        		'contractor' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/coninv[/:action[/:id]]',
        						'constraints' => array(
        								'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'       => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\ContractorController::class,
        								'action'     => 'contractorinvoice',
        						),
        				),
        		),
        		'billing' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/bill[/:action[/:id]]',
        						'constraints' => array(
        								'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'       => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\BillingController::class,
        								'action'     => 'index',
        						),
        				),
        		),
				'purreport' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/purreport[/:action[/:id]]',
        						'constraints' => array(
        								'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'       => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\ReportController::Class,
        								'action'     => 'podetails',
        						),
        				),
        		),            					
		),
	),	
	'view_manager' => array(
        'template_path_stack' => array(
            'purchase'=>__DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);
