<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of WpSmProBulk
 *
 * @author Saurabh Shukla <contact.saurabhshukla@gmail.com>
 */
class WpSmProBulk {

    public function __construct() {
        add_action('admin_menu', array(&$this, 'admin_menu'));
    }

    /**
     * Add Bulk option settings page
     */
    function admin_menu() {
        add_media_page('Bulk Smush.it', 'Bulk Smush.it', 'edit_others_posts', 'wp-smpro-bulk', array(
            &$this,
            'bulk_ui'
        ));
    }

    /**
     * Allows user to Bulk Smush the images
     */
    function bulk_ui() {

        $attachments = null;
        $auto_start = false;

        if (isset($_REQUEST['ids'])) {
            $attachments = get_posts(array(
                'numberposts' => - 1,
                'include' => explode(',', $_REQUEST['ids']),
                'post_type' => 'attachment',
                'post_mime_type' => 'image'
            ));
            $auto_start = true;
        } else {
            $attachments = get_posts(array(
                'numberposts' => 10,
                'post_type' => 'attachment',
                'post_mime_type' => 'image'
            ));
        }
        ?>
        <div class="wrap">
            <div id="icon-upload" class="icon32"><br/></div>
            <h2><?php _e('Bulk WP Smush.it Pro', WP_SMUSHIT_PRO_DOMAIN) ?></h2>
            <div class="bulk_queue_wrap">
                <div class="status-div"></div>
                <?php
                if (sizeof($attachments) < 1) {
                    _e("<p>You don't appear to have uploaded any images yet.</p>", WP_SMUSHIT_PRO_DOMAIN);
                } else {
                    ?>
                    <ul class="bulk_queue">
                        <?php
                        foreach($attachments as $attachment){
                            ?>
                            <li>
                                <input type="hidden" class="id-input" value="<?php echo $attachment_id; ?>"
                                <img src="<?php echo $attachment_id; ?>" />
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                    <?php
                }
                ?>
                <input type="submit" class="button button-primary" name="beginsmush" value="Start" />
            </div>
        </div>
        <?php
    }

}