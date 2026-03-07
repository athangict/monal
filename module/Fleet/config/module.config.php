<?php
namespace Fleet;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(  
        		'fleet' => array(
        				'type'    => 'Segment',
        				'options' => array(
        						'route'       => '/ft[/:action[/:id]]',
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
	            'ftmaster' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/ftm[/:action[/:id]]',
						'constraints' => array(
							'action'   => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'       => '[a-zA-Z0-9_-]*',
						),
						
						'defaults' => array(
							'controller' => Controller\MasterController::class,
							'action'     => 'vehiclegroup',
						),
					),
	            ),  
	            'vehicle' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/vh[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\VehicleController::class,
							'action'     => 'vehicle',
						),
					),
	            ),
				'pol' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/pol[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\PolController::class,
							'action'     => 'pol',
						),
					),
	            ),	
				'ftreport' => array(
					'type'    => 'Segment',
					'options' => array(
						'route'       => '/ftrpt[/:action[/:id]]',
						'constraints' => array(
							'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
							'id'     	 => '[a-zA-Z0-9_-]*',
						),
						'defaults' => array(
							'controller' => Controller\ReportController::class,
							'action'     => 'rmreport',
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
