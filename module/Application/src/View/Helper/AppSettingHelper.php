<?php
namespace Application\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class AppSettingHelper extends AbstractHelper
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function __invoke($key = null, $default = null)
    {
        $defaults = [
            'app_name' => 'Monal-ERP',
            'app_logo' => 'images/logo.png',
            'app_template' => 'ace',
        ];

        $settings = $defaults;
        try {
            $table = $this->container->get(\Administration\Model\AppSettingTable::class);
            $dbSettings = $table->getSettings();
            if (is_array($dbSettings)) {
                $settings = array_merge($defaults, $dbSettings);
            }
        } catch (\Throwable $e) {
            $settings = $defaults;
        }

        if ($key === null) {
            return $settings;
        }

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }
}