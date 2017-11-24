<?php

if (!defined('ABSPATH')) exit;

class AsgarosForumInsert {
    private static $action = false;
    private static $dataSubject;
    private static $dataContent;

    public static function doInsertion() {
        if (self::prepareExecution()) {
            self::insertData();
        }
    }

    private static function getAction() {
        // If no action is set, try to determine one.
        if (!self::$action && ($_POST['submit_action'] === 'add_topic' || $_POST['submit_action'] === 'add_post' || $_POST['submit_action'] === 'edit_post')) {
            self::$action = $_POST['submit_action'];
        }

        return self::$action;
    }

    private static function setData() {
        if (isset($_POST['subject'])) {
            self::$dataSubject = apply_filters('asgarosforum_filter_subject_before_insert', trim($_POST['subject']));
        }

        if (isset($_POST['message'])) {
            self::$dataContent = apply_filters('asgarosforum_filter_content_before_insert', trim($_POST['message']));
        }
    }

    private static function prepareExecution() {
        global $asgarosforum;

        // Cancel if there is already an error.
        if (!empty($asgarosforum->error)) {
            return false;
        }

        // Cancel if the current user is not logged-in and guest postings are disabled.
        if (!is_user_logged_in() && !$asgarosforum->options['allow_guest_postings']) {
            $asgarosforum->error = __('You are not allowed to do this.', 'asgaros-forum');
            return false;
        }

        // Cancel if the current user is banned.
        if (AsgarosForumPermissions::isBanned('current')) {
            $asgarosforum->error = __('You are banned!', 'asgaros-forum');
            return false;
        }

        // Cancel if parents are not set. Prevents the creation of hidden content caused by spammers.
        if (!$asgarosforum->parents_set) {
            $asgarosforum->error = __('You are not allowed to do this.', 'asgaros-forum');
            return false;
        }

        // Cancel when no action could be determined.
        if (!self::getAction()) {
            $asgarosforum->error = __('You are not allowed to do this.', 'asgaros-forum');
            return false;
        }

        // Set the data.
        self::setData();

        // Cancel if the current user is not allowed to edit that post.
        if (self::getAction() === 'edit_post' && !AsgarosForumPermissions::isModerator('current') && AsgarosForumPermissions::$currentUserID != $asgarosforum->get_post_author($asgarosforum->current_post)) {
            $asgarosforum->error = __('You are not allowed to do this.', 'asgaros-forum');
            return false;
        }

        // Cancel if subject is empty.
        if ((self::getAction() === 'add_topic' || (self::getAction() === 'edit_post' && $asgarosforum->is_first_post($asgarosforum->current_post))) && empty(self::$dataSubject)) {
            $asgarosforum->info = __('You must enter a subject.', 'asgaros-forum');
            return false;
        }

        // Cancel if content is empty.
        if (empty(self::$dataContent)) {
            $asgarosforum->info = __('You must enter a message.', 'asgaros-forum');
            return false;
        }

        // Cancel when the file extension of uploads are not allowed.
        if (!AsgarosForumUploads::checkUploadsExtension()) {
            $asgarosforum->info = __('You are not allowed to upload files with that file extension.', 'asgaros-forum');
            return false;
        }

        // Cancel when the file size of uploads is too big.
        if (!AsgarosForumUploads::checkUploadsSize()) {
            $asgarosforum->info = __('You are not allowed to upload files with that file size.', 'asgaros-forum');
            return false;
        }

        // Do custom insert validation checks.
        $custom_check = apply_filters('asgarosforum_filter_insert_custom_validation', true);
        if (!$custom_check) {
            return false;
        }

        return true;
    }

    private static function insertData() {
        global $asgarosforum;

        $redirect = '';

        $date = $asgarosforum->current_time();
        $uploadList = AsgarosForumUploads::getUploadList();

        if (self::getAction() === 'add_topic') {
            // Create the topic.
            $asgarosforum->current_topic = self::insertTopic($asgarosforum->current_forum, self::$dataSubject);

            // Create the post.
            $asgarosforum->current_post = self::insertPost($asgarosforum->current_topic, self::$dataContent, $uploadList);

            // Upload files.
            AsgarosForumUploads::uploadFiles($asgarosforum->current_post, $uploadList);

            // Create redirect link.
            $redirect = html_entity_decode($asgarosforum->getLink('topic', $asgarosforum->current_topic, false, '#postid-'.$asgarosforum->current_post));

            // Send notification about new topic to global subscribers.
            AsgarosForumNotifications::notifyGlobalTopicSubscribers(self::$dataSubject, self::$dataContent, $redirect, AsgarosForumPermissions::$currentUserID);
        } else if (self::getAction() === 'add_post') {
            // Create the post.
            $asgarosforum->current_post = self::insertPost($asgarosforum->current_topic, self::$dataContent, $uploadList);

            AsgarosForumUploads::uploadFiles($asgarosforum->current_post, $uploadList);

            $redirect = html_entity_decode($asgarosforum->get_postlink($asgarosforum->current_topic, $asgarosforum->current_post));

            // Send notification about new post to subscribers
            AsgarosForumNotifications::notifyTopicSubscribers(self::$dataContent, $redirect, AsgarosForumPermissions::$currentUserID);
        } else if (self::getAction() === 'edit_post') {
            $uploadList = AsgarosForumUploads::uploadFiles($asgarosforum->current_post, $uploadList);
            $asgarosforum->db->update($asgarosforum->tables->posts, array('text' => self::$dataContent, 'uploads' => maybe_serialize($uploadList), 'date_edit' => $date, 'author_edit' => AsgarosForumPermissions::$currentUserID), array('id' => $asgarosforum->current_post), array('%s', '%s', '%s', '%d'), array('%d'));

            if ($asgarosforum->is_first_post($asgarosforum->current_post) && !empty(self::$dataSubject)) {
                $asgarosforum->db->update($asgarosforum->tables->topics, array('name' => self::$dataSubject), array('id' => $asgarosforum->current_topic), array('%s'), array('%d'));
            }

            $redirect = html_entity_decode($asgarosforum->get_postlink($asgarosforum->current_topic, $asgarosforum->current_post, $_POST['part_id']));
        }

        AsgarosForumNotifications::updateSubscriptionStatus();

        do_action('asgarosforum_after_'.self::getAction().'_submit', $asgarosforum->current_post, $asgarosforum->current_topic);

        wp_redirect($redirect);
        exit;
    }

    // Inserts a new topic.
    public static function insertTopic($forumID, $name) {
        global $asgarosforum;

        // Get a slug for the new topic.
        $topic_slug = AsgarosForumRewrite::createUniqueSlug($name, $asgarosforum->tables->topics, 'topic');

        // Insert the topic.
        $asgarosforum->db->insert($asgarosforum->tables->topics, array('name' => $name, 'parent_id' => $forumID, 'slug' => $topic_slug), array('%s', '%d', '%s'));

        // Return the ID of the inserted topic.
        return $asgarosforum->db->insert_id;
    }

    // Inserts a new post.
    public static function insertPost($topicID, $text, $uploads) {
        global $asgarosforum;

        // Get the current time.
        $date = $asgarosforum->current_time();

        // Insert the post.
        $asgarosforum->db->insert($asgarosforum->tables->posts, array('text' => $text, 'parent_id' => $topicID, 'date' => $date, 'author_id' => AsgarosForumPermissions::$currentUserID, 'uploads' => maybe_serialize($uploads)), array('%s', '%d', '%s', '%d', '%s'));

        // Return the ID of the inserted post.
        return $asgarosforum->db->insert_id;
    }
}

?>
