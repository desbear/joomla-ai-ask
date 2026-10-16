<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Agent;

\defined('_JEXEC') or die;

/**
 * A service that draws pictures for reports, offered by a plugin of the "mcpask" group through
 * the event onMcpaskImageProviders (append to its "providers" argument). Without one, reports
 * have no pictures made by AI, and the back end offers none.
 *
 * @since  0.14.0
 */
interface ImageProviderInterface
{
    /**
     * A short code, letters and digits, such as "openai"; part of the name pictures are kept under.
     */
    public function name(): string;

    /**
     * Whether it is set up and can draw now.
     */
    public function ready(): bool;

    /**
     * A picture for a prompt, landscape (about 3:2), as the bytes of a PNG, JPEG or WebP file.
     *
     * @throws  \RuntimeException  When none could be made
     */
    public function draw(string $prompt): string;
}
