<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Controller;

use Gss\Component\Mcpask\Administrator\Agent\Holidays;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/**
 * Public holidays of a country and year, as JSON, for the days an agent's people do not answer.
 *
 * @since  0.17.0
 */
class HolidaysController extends BaseController
{
    public function fetch(): void
    {
        if (!Session::checkToken('get') || !$this->app->getIdentity()->authorise('core.manage', 'com_mcpask')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $result = (new Holidays())->fetch($this->input->getCmd('country', ''), $this->input->getInt('year', 0));
        } catch (\Throwable $e) {
            $result = ['error' => Text::sprintf('COM_MCPASK_HOLIDAYS_FAILED', $e->getMessage())];
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }
}
