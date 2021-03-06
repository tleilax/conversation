<?php

require_once 'bootstrap.php';

/**
 * Raumbelegung - Plugin zur Anzeige aller Raumbelegungen an einem Tag
 *
 * Das Raumbelegungsplugin zeigt alle Termine geornet nach Raum und Zeit in
 * einer Liste oder einer Tabelle an. Root verfügt über die 
 * Einstellungsmöglichkeit, Raume und deren Oberkategorien auszublenden, bzw
 * diese zu ordnen.
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Florian Bieringer <florian.bieringer@uni-passau.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */
class Conversations extends StudipPlugin implements SystemPlugin {

    //Delay between onlinechecks
    const ONLINE_CHECK_DELAY = 10;

    function __construct() {
        parent::__construct();
        if (UpdateInformation::isCollecting()) {
            $this->update();
        } else {
            $navigation = Navigation::getItem('/messaging');
            $conversation_navi = new AutoNavigation(_('Gespräche'), PluginEngine::getUrl('Conversations/index'));
            $navigation->addSubNavigation('conversations', $conversation_navi);
        }

        // if conversations is everywhere load it everywhere
        if (Config::get()->CONVERSATIONS_EVERYWHERE && $conversation_navi) {
            $this->addStylesheet('assets/everywhere.less');
            PageLayout::addScript($this->getPluginURL() . "/assets/conversations.js");
            PageLayout::addScript($this->getPluginURL() . "/assets/everywhere.js");
            PageLayout::addHeadElement('script', array(), 'STUDIP.conversations.myId = "' . $GLOBALS['user']->username . '"');
            $this->setupAutoload();
            $this->loadStyle();
            // This needs to be removed 
            PageLayout::addHeadElement('script', array(), 'myId = "' . $GLOBALS['user']->username . '"');

            // Last online
            $_SESSION['conversations']['last_onlinecheck'] = time();
            PageLayout::addHeadElement('script', array(), 'STUDIP.conversations.lastUpdate = "' . time() . '"');

            // Load contacts
            $template_factory = new Flexi_TemplateFactory($this->getPluginPath() . '/templates');
            $template = $template_factory->open('contacts');
            $template->set_attribute('newMessages', Conversation::hasUnread($GLOBALS['user']->id));
            $template->set_attribute('conversations', Conversation::updates());
            $template->set_attribute('url', PluginEngine::getLink($this, array(), null), '/');
            PageLayout::addBodyElements($template->render());
            
            // Add conversations container
            PageLayout::addBodyElements('<div id="conversations_container"></div>');
        }
    }

    function perform($unconsumed_path) {
        $this->addStylesheet('assets/style.less');
        PageLayout::addScript($this->getPluginURL() . "/assets/conversations.js");
        PageLayout::addScript($this->getPluginURL() . "/assets/full_conversation.js");
        PageLayout::addScript($this->getPluginURL() . "/assets/dragndrop.js");

        // Remove everywhere
        PageLayout::removeScript($this->getPluginURL() . "/assets/everywhere.js");
        PageLayout::removeStylesheet($this->getPluginURL() . "/assets/everywhere.css");
        $this->loadStyle();

        $this->setupAutoload();
        $dispatcher = new Trails_Dispatcher(
                $this->getPluginPath(), rtrim(PluginEngine::getLink($this, array(), null), '/'), 'index'
        );
        $dispatcher->plugin = $this;
        $dispatcher->dispatch($unconsumed_path);
    }

    private function setupAutoload() {
        if (class_exists("StudipAutoloader")) {
            StudipAutoloader::addAutoloadPath(__DIR__ . '/models');
        } else {
            spl_autoload_register(function ($class) {
                include_once __DIR__ . $class . '.php';
            });
        }
    }

    private function loadStyle() {
        $styles = glob(__DIR__ . '/styles/*.less');
        if (!$styles) {
            throw new Exception("No style found");
        }
        foreach ($styles as $style) {
            $this->addStylesheet('styles/' . basename($style));
        }
    }

    private function update() {
        if (Config::get()->CONVERSATIONS_EVERYWHERE || stripos(Request::get("page"), "plugins.php/conversations") !== false) {
            $this->setupAutoload();

            // Load parameters
            $params = Request::getArray("page_info");
            $lastUpdateTime = $params['conversations']['lastUpdate'];

            if ($updated = Conversation::updates($lastUpdateTime - 1)) {
                foreach ($updated as $updatedConv) {
                    $updatedConv->activate();
                    $updatedConv->decode($result);
                    $lastUpdate = min(array($lastUpdateTime, $updatedConv->update->chdate));

                    // Decode our messages into the result
                    $messages = ConversationMessage::findBySQL('conversation_id = ? AND mkdate >= ?', array($updatedConv->conversation_id, $lastUpdate));
                    foreach ($messages as $message) {
                        $message->decode($result);
                    }
                }
                // update the send of the last update
                $result['lastUpdate'] = time();
            }
            if ($_SESSION['conversations']['last_onlinecheck'] < time() - self::ONLINE_CHECK_DELAY) {
                $_SESSION['conversations']['last_onlinecheck'] = time();
                $_SESSION['conversations']['last_online_cache'] = Conversation::getOnlineConversations();
            }
            $result['online'] = $_SESSION['conversations']['last_online_cache'];
            UpdateInformation::setInformation("conversations.update", $result);
        }
    }

}
