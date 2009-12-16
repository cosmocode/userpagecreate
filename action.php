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

    function getInfo() {
        return confToHash(dirname(__FILE__).'/plugin.info.txt');
    }

    function register(&$controller) {
       $controller->register_hook('AUTH_LOGIN_CHECK', 'AFTER', $this, 'handle_auth_login_check');

    }

    function handle_auth_login_check(&$event, $param) {
        if (!$event->result) {
            // No successful login
            return;
        }

        $userpage = sprintf($this->getConf('pagename_tpl'), $_SERVER['REMOTE_USER']);
        if (page_exists($userpage)) {
            return;
        }

        // $INFO is not yet available at this stage
        global $INFO;
        $INFO = pageinfo();

        $data = $INFO['userinfo'];

        foreach(array('grps', 'pass') as $hidden) {
            if (isset($data[$hidden])) {
                unset($data[$hidden]);
            }
        }

        $wikipage = pageTemplate(array($userpage));

        if ($wikipage === '') {
            return;
        }

        foreach($data as $k => $v) {
            $wikipage = str_replace('@@' . strtoupper($k) . '@@', $v, $wikipage);
        }

        saveWikiText($userpage, $wikipage, $this->getConf('create_summary'));
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
