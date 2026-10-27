<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

\defined('_JEXEC') or die;

/**
 * The console of the people answering is the component's one page.
 *
 * @since  0.1.0
 */
class DisplayController extends BaseController
{
    protected $default_view = 'console';

    public function display($cachable = false, $urlparams = [])
    {
        // The console, or the visitor's tickets (0.6.2)
        $this->input->set('view', $this->input->get('view') === 'tickets' ? 'tickets' : 'console');

        return parent::display(false, $urlparams);
    }
}
