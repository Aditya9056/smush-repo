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
                'numberposts' => -1,
                'include' => explode(',', $_REQUEST['ids']),
                'post_type' => 'attachment',
                'post_mime_type' => 'image'
            ));
        } else {
            $attachments = get_posts(array(
                'numberposts' => 10,
                'post_type' => 'attachment',
                'post_mime_type' => 'image'
            ));
        }
        
        $total = count($attachments);
        ?>
        <div class="wrap">
            <div id="icon-upload" class="icon32"><br/></div>
            <h2><?php _e('Bulk WP Smush.it Pro', WP_SMUSHIT_PRO_DOMAIN) ?></h2>
            <div class="bulk_queue_wrap">
                <div class="status-div"></div>
                <?php
                if ($total < 1) {
                    _e("<p>You don't appear to have uploaded any images yet.</p>", WP_SMUSHIT_PRO_DOMAIN);
                    ?>
                    <?php
                } else {
                    $this->progress_ui($progress);
                }
                ?>
                <input type="hidden" id="wp-sm-pro-total" val="<?php echo $total; ?>" />
                <input type="hidden" id="wp-sm-pro-done" val="<?php echo $progress; ?>" />
                <input type="submit" id="wp-sm-pro-begin" class="button button-primary" value="Start" />
            </div>
        </div>
        <?php
    }
    
    function progress_ui($progress, $echo = true) {
        $progress_ui = '
            <div id="wp-smpro-progressbar">
                <div style="width:' . $progress . '%"></div>
            </div>
            ';
        if ($echo)
            echo $progress_ui;
        else
            return $progress_ui;
    }

    function progress($progress, $total) {
        if ($total < 1) {
            return 100;
        }
        return ($progress / $total) * 100;
    }
}