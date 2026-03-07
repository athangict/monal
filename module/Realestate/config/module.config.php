<?php
namespace Realestate;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(        
            'estate' => array(
            		'type'    => 'Segment',
            		'options' => array(
            				'route'    => '/estate[/:action[/:id]]',
            				'constraints' => array(
            						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            						'id'     	 => '[a-zA-Z0-9_-]*',
            				),
            				'defaults' => array(
            						'controller' => Controller\IndexController::class,
            						'action'   => 'index',
            				),
            		),
            ),            
            'realestate' => array(
            		'type'    => 'Segment',
            		'options' => array(
            				'route'    => '/realestate[/:action[/:id]]',
            				'constraints' => array(
            						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            						'id'     	 => '[a-zA-Z0-9_-]*',
            				),
            				'defaults' => array(
            						'controller' => Controller\EstateController::class,
            						'action'   => 'index',
            				),
            		),
            ),
			'contaward' => array(
            		'type'    => 'Segment',
            		'options' => array(
            				'route'    => '/contaward[/:action[/:id]]',
            				'constraints' => array(
            						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            						'id'     	 => '[a-zA-Z0-9_-]*',
            				),
            				'defaults' => array(
            						'controller' => Controller\ContractorawardController::class,
            						'action'   => 'index',
            				),
            		),
            ),
			'rsdata' => array(
            		'type'    => 'Segment',
            		'options' => array(
            				'route'    => '/realestatedata[/:action[/:id]]',
            				'constraints' => array(
            						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            						'id'     	 => '[a-zA-Z0-9_-]*',
            				),
            				'defaults' => array(
            						'controller' => Controller\MaindataController::class,
            						'action'   => 'index',
            				),
            		),
            ),
			'capacity' => array(
            		'type'    => 'Segment',
            		'options' => array(
            				'route'    => '/capacity[/:action[/:id]]',
            				'constraints' => array(
            						'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            						'id'     	 => '[a-zA-Z0-9_-]*',
            				),
            				'defaults' => array(
            						'controller' => Controller\GodownsizeController::class,
            						'action'   => 'index',
            				),
            		),
            ),
			'building' => array(
				'type'    => 'Segment',
				'options' => array(
						'route'    => '/building[/:action[/:id]]',
						'constraints' => array(
								'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
								'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
								'controller' => Controller\BuildingController::class,
								'action'   => 'building',
						),
				),
				
		),
		


	'estatereport' => array(
		'type'    => 'Segment',
		'options' => array(
			'route'       => '/estatereport[/:action[/:id]]',
			'constraints' => array(
				'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
				'id'     	 => '[a-zA-Z0-9_-]*',
			),
			'defaults' => array(
				'controller' => Controller\EstatereportController::class,
				'action'     => 'estatereport',
			),
		),
	),
	'rent' => array(
		'type'    => 'Segment',
		'options' => array(
			'route'       => '/rent[/:action[/:id]]',
			'constraints' => array(
				'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
				'id'     	 => '[a-zA-Z0-9_-]*',
			),
			'defaults' => array(
				'controller' => Controller\RentController::class,
				'action'     => 'index',
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
