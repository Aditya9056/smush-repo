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
    
    function to_smush_count() {
	global $wpdb;

	$cache_key = 'wp-smpro-to-smush-count';
        
        $query = "SELECT COUNT(p.ID) FROM {$wpdb->posts} p "
        . "LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id) "
        . "WHERE (pm.metakey='wp-smpro-is-smushed' AND pm.metavalue=1) "
                . "AND p.post_type='attachment' "
                . "AND p.post_mime_type = 'image'";

	$count = wp_cache_get( $cache_key, 'count' );
	if ( false === $count ) {
		$count = $wpdb->get_var( $wpdb->prepare( $query ) );
		wp_cache_set( $cache_key, $count );
	}

	return $count;
    }

    /**
     * Allows user to Bulk Smush the images
     */
    function bulk_ui() {

        $ids = isset($_REQUEST['ids'])?$_REQUEST['ids']:array();
        if (!empty($ids)) {
            $total = count($ids);
            $progress = 0;
        } else {
            $total = wp_count_attachments('image');
            $progress = (int)$this->to_smush_count();
        }
        
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
                <input type="hidden" id="wp-sm-pro-ids" val ="<?php echo explode($ids,','); ?>" />
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