<?php
/**
 * DokuWiki Plugin userpagecreate (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Adrian Lang <lang@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_userpagecreate extends DokuWiki_Action_Plugin {
    function register(&$controller) {
       $controller->register_hook('AUTH_LOGIN_CHECK', 'AFTER', $this, 'handle_auth_login_check');

    }

    function handle_auth_login_check(&$event, $param) {
        if (!$event->result) {
            // No successful login
            return;
        }

        // Check if userpage already exists.
        $userpage_name = sprintf($this->getConf('target'), $_SERVER['REMOTE_USER']);
        if (page_exists($userpage_name)) {
            return;
        }

        // Get userpage template.
        $tpl_name = $this->getConf('pagetemplate');
        if ($tpl_name !== '' && page_exists($tpl_name)) {
            $tpl = io_readFile(wikiFN($tpl_name));
            $userpage = parsePageTemplate($tpl, $userpage_name);
        }
        if ($userpage === '') {
            $userpage = pageTemplate(array($userpage_name));
        }
        if ($userpage === '') {
            return;
        }

        // Get additional user data from auth backend.
        global $USERINFO;
        $data = $USERINFO;
        foreach(array('grps', 'pass', // Secret data
                      'name', 'mail' // Already replaced by parsePageTemplate
                ) as $hidden) {
            if (isset($data[$hidden])) {
                unset($data[$hidden]);
            }
        }
        foreach($data as $k => $v) {
            $userpage = str_replace('@' . strtoupper($k) . '@', $v, $userpage);
        }

        saveWikiText($userpage_name, $userpage, $this->getConf('create_summary'));
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
