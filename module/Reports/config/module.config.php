<?php
namespace Reports;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(  
        		'reports' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/r[/:action[/:id]]',
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
        		
        		'stockreport' => array(
						'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/str[/:action[/:id]]',
        						'constraints' => array(
        								'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'      => '[a-zA-Z0-9_-]*',
        						),        		
        						'defaults' => array(
        								'controller' => Controller\StockreportController::class,
        								'action'     => 'stockmovement',
        						),
        				),
						'may_terminate' => true,
						'child_routes'  => array(
							'defaults' => array(
								'type'      => 'Segment',
								'options'   => array(
									'route' => '/[:controller[/:action[/:id]]]',
									'constraints' => array(
										'controller'  => '[a-zA-Z][a-zA-Z0-9_-]*',
										'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
										'id'      => '[0-9]*',
									),
									'defaults' => array(
									),
								),
							),
							'paginator' => array(
								'type' => 'Segment',
								'options' => array(
									'route' => '/[page/:page]',
									'constraints' => array(
										'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
										'id'      => '[0-9]*', 
									),
									'defaults' => array(
										'__NAMESPACE__' => 'Reports\Controller',
										'controller' => 'Stockreport',
										'action' => 'stockmovement',
									),
								),
							),
						),


        		),
				'salesreport' => array(
        				'type'    => 'Segment',
        				'options'  => array(
        						'route'       => '/slr[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\SalesreportController::class,
        								'action'     => 'customeroutstanding',
        						),
        				),
        		),	
				'claimsreport' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/sr[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\ClaimsreportController::class,
        								'action'     => 'freeitemclsrp',
        						),
        				),
        		),
                        'stockreconcilreport' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/ste[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => Controller\StockreconcilreportController::class,
        								'action'     => 'stockreconcil',
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
