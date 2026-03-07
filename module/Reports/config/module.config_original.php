<?php
return array(
	'controllers' => array(
        'invokables' => array(
        		'Reports\Controller\Index'    => 'Reports\Controller\IndexController',
				'Reports\Controller\Stockreport'    => 'Reports\Controller\StockreportController',
				'Reports\Controller\Salesreport'    => 'Reports\Controller\SalesreportController',
				'Reports\Controller\Claimsreport'   => 'Reports\Controller\ClaimsreportController',
                                'Reports\Controller\Stockreconcilreport'    => 'Reports\Controller\StockreconcilreportController',
            ),
	),
	
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
        								'controller' => 'Reports\Controller\Index',
        								'action'     => 'index',
        						),
        				),
        		),
        		
        		'stockreport' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/str[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => 'Reports\Controller\Stockreport',
        								'action'     => 'stockmovement',
        						),
        				),
        		),
				'salesreport' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/slr[/:action[/:id]]',
        						'constraints' => array(
        								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        								'id'     	 => '[a-zA-Z0-9_-]*',
        						),
        						'defaults' => array(
        								'controller' => 'Reports\Controller\Salesreport',
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
        								'controller' => 'Reports\Controller\Claimsreport',
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
        								'controller' => 'Reports\Controller\Stockreconcilreport',
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
