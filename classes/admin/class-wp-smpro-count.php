<?php

/**
 * @package SmushItPro
 * @subpackage Admin
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if (!class_exists('WpSmProCount')) {

        /**
         * Methods for bulk processing
         */
        class WpSmProCount {
                
                public $counts = array(
                    'total'     => 0,
                    'sent'      => 0,
                    'smushed'   => 0
                );
                
                function init(){
                        $this->counts = array(
                                'total'=> $this->total_count(),
                                'sent' => $this->sent_count(),
                                'smushed' => $this->smushed_count(),
                        );
                }
                
                function sent_count(){
                        $sent_ids = get_site_option(WP_SMPRO_PREFIX.'sent-ids', array());
                        return count($sent_ids);
                }
                
                function total_count(){
                        $query = array(
                            'fields' => 'ids',
                            'post_type' => 'attachment',
                            'post_status' => 'any',
                            'post_mime_type' => array('image/jpeg', 'image/gif', 'image/png'),
                            'order' => 'ASC',
                            'posts_per_page' => - 1
                        );
                        $results = new WP_Query($query);
                        $count = !empty($results->post_count) ? $results->post_count : 0;

                        // send the count
                        return $count;
                }
                
                function smushed_count(){
                        $query = array(
                            'fields' => 'ids',
                            'post_type' => 'attachment',
                            'post_status' => 'any',
                            'post_mime_type' => array('image/jpeg', 'image/gif', 'image/png'),
                            'order' => 'ASC',
                            'posts_per_page' => - 1,
                            'meta_query' => array(
                                    array(
                                        'key' => "wp-smpro-is-smushed",
                                        'value' => 1
                                    )
                                )
                        );

                        $results = new WP_Query($query);
                        $count = !empty($results->post_count) ? $results->post_count : 0;

                        // send the count
                        return $count;
                }

        }

}
