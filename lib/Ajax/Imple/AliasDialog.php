<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Attach the passphrase dialog to the page.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Ajax_Imple_AliasDialog extends Horde_Core_Ajax_Imple
{
    /**
     * @param array $params  Configuration parameters.
     *   - onload: (boolean) [OPTIONAL] If set, will trigger action on page
     *             load.
     *   - params: (array) [OPTIONAL] Any additional parameters to pass to
     *             AJAX action.
     *   - type: (string) The dialog type.
     */
    public function __construct(array $params = [])
    {
        parent::__construct($params);
    }

    /**
     */
    protected function _attach($init)
    {
        global $page_output;

        if ($init) {
            $page_output->addScriptPackage('Horde_Core_Script_Package_Dialog');
            $page_output->addScriptFile('alias.js', 'imp');
        }

        $params = $this->_params['params']
        ?? [];
        if (isset($params['reload'])) {
            $params['reload'] = strval($params['reload']);
        }


        $js_params = [
            'hidden' => array_merge($params, ['keyid' => $this->_params['keyid']]),
            'text' => _('Enter the alias for your certificate.'),
        ];

        $js = 'ImpAliasDialog.display(' . Horde::escapeJson($js_params, ['nodelimit' => true]) . ')';
        if (!empty($this->_params['onload'])) {
            $page_output->addInlineScript([$js], true);
            return false;
        }

        return $js;
    }

    /**
     */
    protected function _handle(Horde_Variables $vars)
    {
        return false;
    }
}
