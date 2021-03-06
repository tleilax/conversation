<?php

require_once 'app/controllers/studip_controller.php';

class EverywhereController extends StudipController {

    const MESSAGES_LOAD = 50; //how many messages should be loaded on first open and on backscroll
    const CONVERSATION_PURGE = 51840000; // After how many days of inactivity should a conversation disapear (initial 60 days)

    public function before_filter(&$action, &$args) {
        parent::before_filter($action, $args);
        if (Request::isXhr()) {
            $this->set_content_type('text/html;Charset=windows-1252');
            $this->set_layout(null);
        } else {
            $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
        }
    }

    /**
     * Actual interface
     */
    public function index_action($start = null) {

        Navigation::activateItem('/messaging/conversations');

        //clear session savings
        $_SESSION['conversations']['online'] = array();
        $_SESSION['conversations']['conversations'] = array();
        $_SESSION['conversations']['last_onlinecheck'] = 0;
        $this->setInfoBox();

        // Set the starting point
        $this->start = $start ? : 0;
    }

    public function contacts_action() {
        foreach (Conversation::updates(time() - self::CONVERSATION_PURGE) as $conv) {
            $conv->decode($conversations);
//$conversations[$conv->conversation_id] = array('name' => $conv->name, 'update' => $conv->update->chdate);
        }
        $this->render_json($conversations);
    }

    public function loadMessages_action() {
        if ($last = Request::get('lastMessage')) {
            $where = "AND message_id < '$last'";
        }
        $messages = ConversationMessage::findBySQL('conversation_id = ? ' . $where . ' ORDER BY message_id DESC LIMIT ?', array(Request::get('conversation'), self::MESSAGES_LOAD));
        $messages = SimpleORMapCollection::createFromArray($messages);
        foreach ($messages->orderBy('mkdate ASC') as $msg) {
            $msg->decode($result);
        }
        $this->render_json($result);
    }

}
