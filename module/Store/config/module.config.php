<?php
namespace Store;

use Laminas\Router\Http\Segment;

return array(	
    'router' => array(
        'routes' => array(  
        		'stores' => array(
					'type'    => 'Segment',
					'options' => array(
							'route'       => '/in[/:action[/:id]]',
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
				'store' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/ins[/:action[/:id]]',
						'constraints' => array(
							'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'       => '[a-zA-Z0-9_-]*',
						),
						
						'defaults' => array(
							'controller' => Controller\StoreController::class,
							'action'     => 'index',
						),
					),
	            ),
	            'inmaster' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/inm[/:action[/:id]]',
						'constraints' => array(
							'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'       => '[a-zA-Z0-9_-]*',
						),
						
						'defaults' => array(
							'controller' => Controller\MasterController::class,
							'action'     => 'group',
						),
					),
	            ), 				
	           'inpurorder' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/inpurorder[/:action[/:id]]',
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
				'inpurreceipt' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/inpurreceipt[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\ReceiptController::class,
							'action'     => 'index',
						),
					),
	            ),
				'inpurpayment' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/inpurpayment[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\PaymentController::class,
							'action'     => 'index',
						),
					),
	            ),		
                'inissue' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/inissue[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\IssueController::class,
							'action'     => 'index',
						),
					),
	            ),				
				'inreport' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/strreport[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\ReportController::class,
							'action'     => 'stockreport',
						),
					),
	            ),	
			),
		),	
	'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);
