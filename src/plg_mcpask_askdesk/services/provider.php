<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  plg_mcpask_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

\defined('_JEXEC') or die;

use Gss\Plugin\Mcpask\Askdesk\Extension\Askdesk;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Askdesk($container->get(DispatcherInterface::class), (array) PluginHelper::getPlugin('mcpask', 'askdesk'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
