<?php

if (!defined('ABSPATH')) exit;

class AsgarosForumApproval {
    private $asgarosforum = null;

    public function __construct($object) {
        $this->asgarosforum = $object;

        add_action('asgarosforum_breadcrumbs_unapproved', array($this, 'add_breadcrumbs'));
    }

    public function add_breadcrumbs() {
        $element_link = $this->asgarosforum->get_link('unapproved');
        $element_title = __('Unapproved Topics', 'asgaros-forum');
        $this->asgarosforum->breadcrumbs->add_breadcrumb($element_link, $element_title);
    }

    // Checks if a topic is approved.
    private $is_topic_approved_cache = array();
    public function is_topic_approved($topic_id) {
        // Return false if no topic_id is set.
        if (!$topic_id) {
            return false;
        }

        if (!isset($this->is_topic_approved_cache[$topic_id])) {
            $approved = $this->asgarosforum->db->get_var("SELECT approved FROM {$this->asgarosforum->tables->topics} WHERE id = {$topic_id};");

            if ($approved === '1') {
                $this->is_topic_approved_cache[$topic_id] = true;
            } else {
                $this->is_topic_approved_cache[$topic_id] = false;
            }
        }

        return $this->is_topic_approved_cache[$topic_id];
    }

    // Approves a topic.
    public function approve_topic($topic_id) {
        if ($this->asgarosforum->permissions->isModerator('current')) {
            // Change the status of the topic.
            $this->asgarosforum->db->update($this->asgarosforum->tables->topics, array('approved' => 1), array('id' => $topic_id), array('%d'), array('%d'));

            // Update the timestamp of posts inside the topic.
            $this->asgarosforum->db->update($this->asgarosforum->tables->posts, array('date' => $this->asgarosforum->current_time()), array('parent_id' => $topic_id), array('%s'), array('%d'));

            // Update the cache.
            $this->is_topic_approved_cache[$topic_id] = true;

            // Get first post inside the topic.
            $first_post = $this->asgarosforum->content->get_first_post($topic_id);

            // Send notifications about new topic.
            $this->asgarosforum->notifications->notify_about_new_topic($topic_id);

            // Send notifications about mentionings.
            $this->asgarosforum->mentioning->mention_users($first_post->id);
        }
    }

    // Returns all unapproved topics from all or a specific forum.
    public function get_unapproved_topics($forum_id = 'all') {
        if ($forum_id === 'all') {
            return $this->asgarosforum->db->get_results("SELECT * FROM {$this->asgarosforum->tables->topics} WHERE approved = 0 ORDER BY id DESC;");
        } else {
            return $this->asgarosforum->db->get_results("SELECT * FROM {$this->asgarosforum->tables->topics} WHERE approved = 0 AND parent_id = {$forum_id} ORDER BY id DESC;");
        }
    }

    // Sends a notification about a new unapproved topic.
    public function notify_about_new_unapproved_topic($topic_id) {
        // Load required data.
        $post = $this->asgarosforum->content->get_first_post($topic_id);
        $topic = $this->asgarosforum->content->get_topic($post->parent_id);

        // Get more data.
        $topic_link = $this->asgarosforum->rewrite->get_link('topic', $topic_id);
        $topic_name = esc_html(stripslashes($topic->name));
        $author_name = $this->asgarosforum->getUsername($post->author_id);
        $notification_subject = __('New unapproved topic', 'asgaros-forum');

        // Prepare message-template.
        $replacements = array(
            '###AUTHOR###'  => $author_name,
            '###LINK###'    => '<a href="'.$topic_link.'">'.$topic_link.'</a>',
            '###TITLE###'   => $topic_name,
            '###CONTENT###' => wpautop(stripslashes($post->text))
        );

        $notification_message = __('Hello ###USERNAME###,<br><br>You received this message because there is a new unapproved forum-topic.<br><br>Topic:<br>###TITLE###<br><br>Author:<br>###AUTHOR###<br><br>Text:<br>###CONTENT###<br><br>Link:<br>###LINK###', 'asgaros-forum');

        $admin_mail = get_bloginfo('admin_email');

        $this->asgarosforum->notifications->send_notifications($admin_mail, $notification_subject, $notification_message, $replacements);
    }

    // Checks if a topic requires approval for a specific forum and user.
    public function topic_requires_approval($forum_id, $user_id) {
        // If the current user is at least a moderator, no approval is needed.
        if ($this->asgarosforum->permissions->isModerator($user_id)) {
            return false;
        }

        // Check if the forum requires approval for new topics.
        $approval = $this->asgarosforum->db->get_var("SELECT approval FROM {$this->asgarosforum->tables->forums} WHERE id = {$forum_id};");

        // Additional checks if forum requires approval.
        if ($approval === '1') {
            // If the current user is a guest, approval is needed for sure.
            if (!is_user_logged_in()) {
                return true;
            }

            // If approval is needed for normal users as well, approval is needed because we already know the current user is not even a moderator.
            if ($this->asgarosforum->options['approval_for'] == 'normal') {
                return true;
            }
        }

        // Otherwise no approval is needed.
        return false;
    }

    // Shows an info-message when a new unapproved topic got created.
    public function notice_for_topic_creator() {
        if (!empty($_GET['new_unapproved_topic'])) {
            echo '<div class="unapproved-notice unapproved-notice-topic-creator">';
                echo '<span class="dashicons-before dashicons-visibility">'.__('Thank you for your topic. Your topic will be visible as soon as it gets approved.', 'asgaros-forum').'</span>';
            echo '</div>';
        }
    }

    // Shows an info-message when there are unapproved topics.
    public function notice_for_moderators() {
        // Ensure that the current user is at least a moderator.
        if (!$this->asgarosforum->permissions->isModerator('current')) {
            return;
        }

        // Ensure that we are not already inside the unapproved view.
        if ($this->asgarosforum->current_view === 'unapproved') {
            return;
        }

        $unapproved_topics = $this->get_unapproved_topics();

        if (!empty($unapproved_topics)) {
            echo '<div class="unapproved-notice unapproved-notice-moderators">';
                echo '<span class="dashicons-before dashicons-visibility">';
                    echo '<a href="'.$this->asgarosforum->rewrite->get_link('unapproved').'">'.__('There are unapproved topics.', 'asgaros-forum').'</a>';
                echo '</span>';
            echo '</div>';
        }
    }

    // Renders a view with all unapproved topics.
    public function show_unapproved_topics() {
        // Load unread topics.
        $unapproved_topics = $this->get_unapproved_topics();
        $unapproved_topics_counter = count($unapproved_topics);

        // Render pagination.
        $pagination_rendering = $this->asgarosforum->pagination->renderPagination('unapproved', false, $unapproved_topics_counter);

        if ($pagination_rendering) {
            echo '<div class="pages-and-menu">';
                echo $pagination_rendering;
                echo '<div class="clear"></div>';
            echo '</div>';
        }

        echo '<div class="title-element"></div>';
        echo '<div class="content-element">';

        if ($unapproved_topics_counter > 0) {
            $page_elements = 50;
            $page_start = $this->asgarosforum->current_page * $page_elements;
            $data_sliced = array_slice($unapproved_topics, $page_start, $page_elements);

            foreach ($data_sliced as $topic) {
                $topic_title = esc_html(stripslashes($topic->name));
                $first_post = $this->asgarosforum->content->get_first_post($topic->id);

                echo '<div class="unapproved-topic topic-normal">';
                    echo '<div class="topic-status dashicons-before dashicons-visibility unread"></div>';
                    echo '<div class="topic-name">';
                        echo '<a href="'.$this->asgarosforum->rewrite->get_link('topic', $topic->id).'" title="'.$topic_title.'">'.$topic_title.'</a>';
                        echo '<small>';
                        echo __('By', 'asgaros-forum').'&nbsp;'.$this->asgarosforum->getUsername($first_post->author_id);
                        echo '&nbsp;&middot;&nbsp;';
                        echo sprintf(__('%s ago', 'asgaros-forum'), human_time_diff(strtotime($first_post->date), current_time('timestamp')));
                        echo '</small>';
                    echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<div class="notice">'.__('There are no unapproved topics.', 'asgaros-forum').'</div>';
        }

        echo '</div>';

        if ($pagination_rendering) {
            echo '<div class="pages-and-menu">';
                echo $pagination_rendering;
                echo '<div class="clear"></div>';
            echo '</div>';
        }
    }
}