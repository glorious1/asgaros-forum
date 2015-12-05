<?php
$this->options_editor['textarea_rows'] = 12;
$thread = "";
$post = "";
$t = "";
$q = "";
$error = false;

if (!$user_ID) {
    $error = true;
    echo '<div class="notice">'.__("Sorry, you don't have permission to post.", "asgarosforum").'</div>';
}

if (!$error) {
    if ($_GET['forumaction'] == "addthread") {
        if (!$this->forum_exists($_GET['forum'])) {
            $error = true;
            echo '<div class="notice">'.__("Sorry, this forum does not exist.", "asgarosforum").'</div>';
        }
    } else if ($_GET['forumaction'] == "postreply") {
        if (!$this->thread_exists($_GET['thread'])) {
            $error = true;
            echo '<div class="notice">'.__("Sorry, this thread does not exist.", "asgarosforum").'</div>';
        }
        if (!$error) {
            $thread = $_GET['thread'];

            if (isset($_GET['quote'])) {
                $quote_id = $_GET['quote'];
                $text = $wpdb->get_row($wpdb->prepare("SELECT text, author_id, date FROM {$this->table_posts} WHERE id = %d", $quote_id));
                $display_name = $this->get_userdata($text->author_id, $this->options['forum_display_name']);
                $q = "<blockquote><div class='quotetitle'>" . __("Quote from", "asgarosforum") . " " . $display_name . " " . __("on", "asgarosforum") . " " . $this->format_date($text->date) . "</div>" . $text->text . "</blockquote><br />";
            }
        }
    } else if ($_GET['forumaction'] == "editpost") {
        if (!$this->thread_exists($_GET['thread'])) {
            $error = true;
            echo '<div class="notice">'.__("Sorry, this thread does not exist.", "asgarosforum").'</div>';
        }
        if (!$error) {
            $id = (isset($_GET['id']) && !empty($_GET['id'])) ? (int)$_GET['id'] : 0;
            $thread = $_GET['thread'];
            $t = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$this->table_threads} WHERE id = %d", $thread));
            $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_posts} WHERE id = %d", $id));

            if (!($user_ID == $post->author_id && $user_ID) && !$this->is_moderator($user_ID)) {
                $error = true;
                echo '<div class="notice">'.__("Sorry, you are not allowed to edit this post.", "asgarosforum").'</div>';
            }
        }
    }
}

if (!$error) { ?>

<form name='addform' method='post' enctype='multipart/form-data'>
    <div class='title-element'>
        <?php
        if ($_GET['forumaction'] == "addthread") {
            _e("Post new Topic", "asgarosforum");
        } else if ($_GET['forumaction'] == "postreply") {
            echo __("Post Reply:", "asgarosforum") . ' ' . $this->get_name($thread, $this->table_threads);
        } else if ($_GET['forumaction'] == "editpost") {
            echo __("Edit Post:", "asgarosforum") . ' ' . stripslashes($t->name);
        }
        ?>
    </div>
    <div class='content-element editor'>
        <table>
            <?php if ($_GET['forumaction'] == "addthread") { ?>
            <tr>
                <td><?php _e("Subject:", "asgarosforum"); ?></td>
                <td><input type='text' name='add_thread_subject' /></td>
            </tr>
            <?php } ?>
            <?php
            /*if(false) //Need to enable this eventually if we're editing the first post in the thread
            echo "<tr>
            <td>" . __("Subject:", "asgarosforum") . "</td>
            <td><input size='50%' type='text' name='edit_post_subject' value='" . stripslashes($t->name) . "'/></td>
            </tr>";*/
            ?>
            <tr>
                <td><?php _e("Message:", "asgarosforum"); ?></td>
                <td>
                    <?php
                    if ($_GET['forumaction'] == "editpost") {
                        wp_editor(stripslashes($post->text), 'message', $this->options_editor);
                    } else {
                        wp_editor($q, 'message', $this->options_editor);
                    }
                    ?>
                </td>
            </tr>
            <?php
            if ($_GET['forumaction'] != "editpost" && $this->options['forum_allow_image_uploads']) { ?>
    		<tr>
    			<td><?php _e("Images:", "asgarosforum"); ?></td>
    			<td>
    				<input type='file' name='mfimage1' /><br/>
    				<input type='file' name='mfimage2' /><br/>
    				<input type='file' name='mfimage3' />
    			</td>
    		</tr>
            <?php } ?>
            <tr>
                <td></td>
                <?php if ($_GET['forumaction'] == "addthread") { ?>
                    <td>
                        <input type='submit' name='add_thread_submit' value='<?php _e("Submit", "asgarosforum"); ?>' />
                        <input type='hidden' name='add_thread_forumid' value='<?php echo $_GET['forum']; ?>' />
                    </td>
                <?php } else if ($_GET['forumaction'] == "postreply") { ?>
                    <td>
                        <input type='submit' name='add_post_submit' value='<?php _e("Submit", "asgarosforum"); ?>' />
                        <input type='hidden' name='add_post_forumid' value='<?php echo $thread; ?>' />
                    </td>
                <?php } else if ($_GET['forumaction'] == "editpost") { ?>
                    <td>
                        <input type='submit' name='edit_post_submit' value='<?php _e("Submit", "asgarosforum"); ?>' />
                        <input type='hidden' name='edit_post_id' value='<?php echo $post->id; ?>' />
                        <input type='hidden' name='thread_id' value='<?php echo $thread; ?>' />
                        <input type='hidden' name='page_id' value='<?php echo $this->current_page; ?>' />
                    </td>
                <?php } ?>
            </tr>
        </table>
    </div>
</form>

<?php } ?>