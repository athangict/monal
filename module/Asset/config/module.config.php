<?php
namespace Asset;

use Laminas\Router\Http\Segment;

return array(
    'router' => array(
        'routes' => array(
		    'mastera' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/asty[/:action[/:id]]',
					'constraints' => array(
						'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'      => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\MasterController::class,
						'action'        => 'assettype',
					),
				),
			),
			'assets' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/astt[/:action[/:id]]',
					'constraints' => array(
						'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'      => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\AssetController::class,
						'action'        => 'asset',
					),
				),
			),
			'schedule' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/sch[/:action[/:id]]',
					'constraints' => array(
						'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'      => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\ScheduleController::class,
						'action'        => 'schedule',
					),
				),
			),
			'areport' => array(
				'type'    => 'Segment',
				'options' => array(
					'route'    => '/areport[/:action[/:id]]',
					'constraints' => array(
						'action'  => '[a-zA-Z][a-zA-Z0-9_-]*',
						'id'      => '[a-zA-Z0-9_-]*',
					),
					'defaults' => array(
						'controller' => Controller\AreportController::class,
						'action'        => 'report',
					),
				),
			),
		),
	),	
	'view_manager' => array(
        'template_path_stack' => array(
            'asset'=>__DIR__ . '/../view/',
        ),
		'display_exceptions' => true,
    ),
);