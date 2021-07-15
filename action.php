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

        if ($this->getConf('delete')) {
            $controller->register_hook('AUTH_USER_CHANGE', 'AFTER', $this, 'handleUserDeletion');
        }
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
        if (!$user) return;

        $data = $this->userSpaceData($user);
        if ($data === null) return;
        if ($data['exists']) return;

        // trigger custom Event
        Event::createAndTrigger(
            'USERPAGECREATE_PAGE_CREATE',
            $data,
            array($this, 'createUserSpace'),
            true
        );
    }

    /**
     * @param $user
     * @return array|null The appropriate data or null if configuration failed
     */
    protected function userSpaceData($user)
    {
        global $conf;

        // prepare info
        $res = $this->getConf('target') . $user;
        $tpl = $this->getConf('template');
        $do_ns = (strlen($tpl) > 0) && substr($tpl, -1, 1) === ':';

        // no ressource, nothing to do
        if ($res === '') return null;

        // Check if userpage or usernamespace already exists.
        $exists = page_exists($res . ($do_ns ? (':' . $conf['start']) : ''));

        // prepare Event Data
        return array(
            'do_ns' => $do_ns,
            'tpl' => $tpl,
            'res' => $res,
            'exists' => $exists,
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

    /**
     * Handle the deletion of the user page when the user is deleted
     *
     * @param Doku_Event $event
     * @param $param
     */
    public function handleUserDeletion(Doku_Event $event, $param)
    {
        if ($event->data['type'] !== 'delete') return;

        foreach ($event->data['params'] as $info) {
            $user = $info[0];
            $data = $this->userSpaceData($user);
            if ($data === null) continue;
            if (!$data['exists']) continue;
            if ($data['do_ns']) {
                $this->deleteNamespace($data['res']);
            } else {
                $this->deletePage($data['res']);
            }
        }
    }

    /**
     * Delete the given namespace and all old revisions
     *
     * @param string $ns
     */
    protected function deleteNamespace($ns)
    {
        global $conf;

        $ns = str_replace(':', '/', $ns);

        // pages
        $dir = $conf['datadir'] . '/' . utf8_encodeFN($ns);
        if (is_dir($dir)) {
            io_rmdir($dir, true);
        }

        // attic
        $dir = $conf['olddir'] . '/' . utf8_encodeFN($ns);
        if (is_dir($dir)) {
            io_rmdir($dir, true);
        }
    }

    /**
     * Delete the given page and all old revisions
     *
     * @param string $page
     */
    protected function deletePage($page)
    {
        global $conf;

        $page = str_replace(':', '/', $page);

        // page
        $file = $conf['datadir'] . '/' . utf8_encodeFN($page) . '.txt';
        if (is_file($file)) {
            unlink($file);
        }

        // attic
        $files = glob($conf['olddir'] . '/' . utf8_encodeFN($page) . '.*.txt.*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
