<?php

use dokuwiki\Extension\Event;

/**
 * DokuWiki Plugin userpagecreate (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Adrian Lang <lang@cosmocode.de>
 */
class action_plugin_userpagecreate extends DokuWiki_Action_Plugin
{
    /** @inheritDoc */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_action_act_preprocess');
    }

    /**
     * Check and if necessary trigger the page creation
     *
     * @triggers USERPAGECREATE_PAGE_CREATE
     * @param Doku_Event $event
     * @param $param
     */
    public function handle_action_act_preprocess(Doku_Event $event, $param)
    {
        global $INPUT;
        global $conf;

        $user = $INPUT->server->str('REMOTE_USER');

        // no user, nothing to do
        if (!$user) return;

        // prepare info
        $res = $this->getConf('target') . $user;
        $tpl = $this->getConf('template');
        $do_ns = (strlen($tpl) > 0) && substr($tpl, -1, 1) === ':';

        // no ressource, nothing to do
        if ($res === '') return;

        // Check if userpage or usernamespace already exists.
        if (page_exists($res . ($do_ns ? (':' . $conf['start']) : ''))) {
            return;
        }

        // prepare Event Data
        $data = array(
            'do_ns' => $do_ns,
            'tpl' => $tpl,
            'res' => $res,
        );

        // trigger custom Event
        Event::createAndTrigger(
            'USERPAGECREATE_PAGE_CREATE',
            $data,
            array($this, 'createUserSpace'),
            true
        );
    }

    /**
     * @param $data
     */
    public function createUserSpace($data)
    {
        global $conf;

        $do_ns = $data['do_ns'];
        $tpl = $data['tpl'];
        $res = $data['res'];

        // Get templates and target page names.
        $parsed = false;
        $pages = array();
        if ($do_ns) {
            $t_pages = array();
            search($t_pages, $conf['datadir'], 'search_universal',
                array('depth' => 0, 'listfiles' => true),
                str_replace(':', '/', getNS($tpl)));
            foreach ($t_pages as $t_page) {
                $tpl_name = cleanID($t_page['id']);
                $pages[$res . ':' . substr($tpl_name, strlen(getNS($tpl)) + 1)] = rawWiki($tpl_name);
            }
        } else {
            if ($tpl === '') {
                $pages[$res] = pageTemplate($res);
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
        $auth_replaces = $USERINFO;
        foreach (array(
                     'grps',
                     'pass', // Secret data
                     'name',
                     'mail' // Already replaced by parsePageTemplate
                 ) as $hidden) {
            if (isset($auth_replaces[$hidden])) {
                unset($auth_replaces[$hidden]);
            }
        }

        // Parse templates and write pages.
        foreach ($pages as $name => &$content) {
            if (!$parsed) {
                $byref_data = array('tpl' => $content, 'id' => $name);
                $content = parsePageTemplate($byref_data);
            }
            foreach ($auth_replaces as $k => $v) {
                $content = str_replace('@' . strtoupper($k) . '@', $v, $content);
            }

            saveWikiText($name, $content, $this->getConf('create_summary'));
        }
    }
}
