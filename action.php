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
require_once DOKU_INC.'inc/search.php';

class action_plugin_userpagecreate extends DokuWiki_Action_Plugin {
    function register(&$controller) {
       $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess');
    }

    function handle_action_act_preprocess(&$event, $param) {
        if (!isset($_SERVER['REMOTE_USER'])) {
            // No successful login
            return;
        }

        global $conf;

        $res = $this->getConf('target') . $_SERVER['REMOTE_USER'];
        $tpl = $this->getConf('template');
        $do_ns = (strlen($tpl) > 0) && substr($tpl, -1, 1) === ':';

        if ($res === '') {
            return;
        }

        // Check if userpage or usernamespace already exists.
        if (page_exists($res . ($do_ns ? (':' . $conf['start']) : ''))) {
            return;
        }

        // Get templates and target page names.
        $parsed = false;
        $pages = array();
        if ($do_ns) {
            $t_pages = array();
            search($t_pages, $conf['datadir'], 'search_universal',
                   array('depth' => 0, 'listfiles' => true),
                   str_replace(':', '/', getNS($tpl)));
            foreach($t_pages as $t_page) {
                $tpl_name = cleanID($t_page['id']);
                $pages[$res . ':' . substr($tpl_name, strlen(getNS($tpl)) + 1)] = rawWiki($tpl_name);
            }
        } else {
            if ($tpl === '') {
                $pages[$res] = pageTemplate(array($res));
                $parsed = true;
            } elseif (page_exists($tpl)) {
                $pages[$res] = rawWiki($tpl);
            }
        }

        if (count($pages) === 0) {
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

        // Parse templates and write pages.
        foreach ($pages as $name => &$content) {
            if (!$parsed) {
                $data = array('tpl' => $content, 'id' => $name);
                $content = parsePageTemplate($data);
            }
            foreach($data as $k => $v) {
                $content = str_replace('@' . strtoupper($k) . '@', $v, $content);
            }

            saveWikiText($name, $content, $this->getConf('create_summary'));
        }
    }
}

// vim:ts=4:sw=4:et:enc=utf-8:
