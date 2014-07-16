<?php

/**
 * Provides Bulk Smushing user interface
 *
 * @author Saurabh Shukla <contact.saurabhshukla@gmail.com>
 */
class WpSmProBulk {

    public function __construct() {
        add_action('admin_init', array(&$this, 'admin_init'));
        add_action('admin_menu', array(&$this, 'admin_menu'));
    }

    /**
     * Add Bulk option settings page
     */
    function admin_menu() {
        $bulk_page_suffix = add_media_page('Bulk Smush.it', 'Bulk Smush.it', 'edit_others_posts', 'wp-smpro-bulk', array(
            &$this,
            'bulk_ui'
        ));
        
        // enqueue js only on this screen
        add_action('admin_print_scripts-' . $bulk_page_suffix, array(&$this,'enqueue'));
    }
    
    /**
     * Register js
     */
    function admin_init() {
        /* Register our script. */
        wp_register_script( 'wp-smpro-queue', WP_SMPRO_URL.'js/wp-smpro-queue.js',array('jquery'),WP_SMPRO_VERSION );
        //@todo enqueue minified script if not debugging
        //wp_register_script( 'wp-smpro-queue-debug', trailingslashit(WP_SMPRO_DIR).'js/wp-smpro-queue.js' );
        wp_register_style('wp-smpro-queue', WP_SMPRO_URL.'css/wp-smpro-queue.css');
    }
    
    /**
     * enqueue js
     */
    function enqueue(){
        wp_enqueue_script( 'wp-smpro-queue' );
        wp_enqueue_style( 'wp-smpro-queue' );
    }
    
    /**
     * The images that still need to be smushed
     * 
     * @global type $wpdb
     * @return type
     */
    function to_smush_count() {
	global $wpdb;

	$cache_key = 'wp-smpro-to-smush-count';
        
        $query = "SELECT COUNT(p.ID) FROM {$wpdb->posts} as p "
        . "LEFT JOIN {$wpdb->postmeta} as pm ON (p.ID = pm.post_id) "
        . "WHERE (pm.meta_key='wp-smpro-is-smushed' AND pm.meta_value=%d) "
                . "AND p.post_type='attachment' "
                . "AND p.post_mime_type = 'image'";

	$count = wp_cache_get( $cache_key, 'count' );
	if ( false === $count ) {
		$count = $wpdb->get_var( $wpdb->prepare( $query, 1 ) );
		wp_cache_set( $cache_key, $count );
	}

	return $count;
    }
    
    
    function start_id(){
        global $wpdb;

	    $args = array(
		    'post_status'   => 'inherit',
		    'posts_per_page' => 1,
		    'post_type'=> 'attachment',
		    'post_mime_type' => 'image/jpeg,image/gif,image/jpg,image/png',
		    'fields'    =>  'ids'
	    );
	    $meta_query =  array(
		    'relation'  => 'OR',
		    array(
			    'key' => 'wp-smpro-is-smushed',
			    'value' => 1,
		    ),
		    array(
			    'key'   => 'wp-smpro-is-smushed',
			    'value' =>  'a',
			    'compare'   =>  'NOT EXISTS'
		    )
	    );
	    $args['meta_query'] = $meta_query;
        $posts = new WP_Query( $args );

        $id = $posts->posts[0];
        return $id;
    }

    /**
     * Display the UI
     */
    function bulk_ui() {
	    global $wpdb;

        $ids = isset($_REQUEST['ids'])?$_REQUEST['ids']:array();
        $idstr = '';
        $start_id = 'null';
        if (!empty($ids)) {
            $total = count($ids);
            $progress = 0;
            $idstr = explode($ids,',');
        } else {
            $total = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
            $progress = (int)$this->to_smush_count();
            $start_id = $this->start_id();
        }
        
        ?>
        <script type="text/javascript">
            var wp_smpro_total = <?php echo $total; ?>;
            var wp_smpro_progress = <?php echo $progress; ?>;
            var wp_smpro_ids = [<?php echo $idstr; ?>];
            var wp_smpro_start_id = "<?php echo $start_id; ?>";
        </script>

        <div class="wrap">
            <div id="icon-upload" class="icon32"><br/></div>
            <h2><?php _e('Bulk WP Smush.it Pro', WP_SMPRO_DOMAIN) ?></h2>
            <div class="bulk_queue_wrap">
                <div class="status-div"></div>
                <?php
                if ($total < 1) {
                    _e("<p>You don't appear to have uploaded any images yet.</p>", WP_SMPRO_DOMAIN);
                    ?>
                    <?php
                } else {
                    $this->progress_ui($progress);
                    ?>
                    <input type="submit" id="wp-sm-pro-begin" class="button button-primary" value="Start" />
                    <?php
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Show a progress bar
     * 
     * @param type $progress
     * @return string
     */
    function progress_ui($progress) {
        $progress_ui = '
            <div id="wp-smpro-progressbar">
                <div style="width:' . $progress . '%"></div>
            </div>
            ';
        echo $progress_ui;
    }
    /**
     * Calculate progress %age for progress bar
     * 
     * @param type $progress
     * @param type $total
     * @return int
     */
    function progress($progress, $total) {
        if ($total < 1) {
            return 100;
        }
        return ($progress / $total) * 100;
    }
}
