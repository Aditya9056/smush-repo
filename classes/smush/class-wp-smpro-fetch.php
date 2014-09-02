<?php

/**
 * @package SmushItPro
 * @subpackage Receive
 * @version 1.0
 *
 * @author Saurabh Shukla <saurabh@incsub.com>
 * @author Umesh Kumar <umesh@incsub.com>
 *
 * @copyright (c) 2014, Incsub (http://incsub.com)
 */
if ( ! class_exists( 'WpSmProFetch' ) ) {

	/**
	 * Fetches files from the service
	 */
	class WpSmProFetch {

		/**
		 *
		 * @var string The base upload directory
		 */
		var $basedir = '';

		/**
		 * Constructor
		 */
		function __construct() {

			// set up the base path
			$pathbase      = wp_upload_dir();
			$this->basedir = trailingslashit( $pathbase['basedir'] );

			// hook the ajax call for fetching the attachment
			add_action( 'wp_ajax_wp_smpro_fetch', array( $this, 'fetch' ) );
		}

		/**
		 *
		 * @return type
		 */
		function fetch( $attachment_id = false ) {

			$output = array(
				'success' => false,
				'msg'     => __( 'Fetching failed. Please retry later.', WP_SMPRO_DOMAIN )
			);

			if ( ! $attachment_id ) {
				$attachment_id = $_GET['attachment_id'];
			}

			if ( ! $attachment_id ) {
				$output['msg'] = __( 'No attachment ID was provided.', WP_SMPRO_DOMAIN );
				echo json_encode( $output );
				die();
			}

			$smush_data = $this->update_smush_data( $attachment_id );
                        
                        $smushed_file = $this->save_zip( $attachment_id, $smush_data['file_url'] );
                        
			$result = $this->replace_files( $smushed_file );
                        
                        if ( ! $result ) {
                                unset( $smush_data );

				echo json_encode( $output );
				die();
			}

			$this->update_filenames( $attachment_id, $smush_data['filenames'] );
			$this->update_flags( $attachment_id );
                        update_post_meta( $attachment_id, WP_SMPRO_PREFIX . 'is-smushed', 1 );
			$output['success'] = true;
			$output['stats']   = $smush_data['stats'];
			$output['msg']     = '';

			unset( $smush_data );
                        
			echo json_encode( $output );
			die();
		}

		function save_zip( $attachment_id, $url ) {


			$zip = $this->_get( $url );

			$filename = $this->upload_zip( $attachment_id, $zip );
                        
			return $filename;
		}

		function upload_zip( $attachment_id, $zip ) {
                        $basepath = $this->basedir . WP_SMPRO_PREFIX . 'fetched/';
                        if(!is_dir($basepath)){
                                mkdir($basepath);
                        }
			$filename =  $basepath . $attachment_id . '.zip';

			$fp = fopen( $filename, 'w' );
			fwrite( $fp, $zip );
			fclose( $fp );

			unset( $zip );

			return $filename;
		}

		function update_flags( $attachment_id ) {
			$current_requests = get_option( WP_SMPRO_PREFIX . "current-requests", array() );
                        
			$remove = false;

			foreach ( $current_requests as $request_id => &$data ) {
                        	if ( in_array( $attachment_id, $data['sent_ids'] ) ) {
                        		$remove = $this->reset_flags( $attachment_id, $request_id, $data['sent_ids'] );
					continue;
				}

			}
                        
			if ( $remove ) {
				unset( $current_requests[ $remove ] );

				return update_option( WP_SMPRO_PREFIX . "current-requests", $current_requests );
			}

			return false;
		}

		function update_filenames( $attachment_id, $filenames ) {
                        $attachment_meta = wp_get_attachment_metadata( $attachment_id );
                        
			foreach ( $attachment_meta['sizes'] as $size => $details ) {
                                if(!in_array($size,$filenames)){
                                        continue;
                                }
				$attachment_meta['sizes'][$size]['file'] = $filenames[ $size ];
			}

			wp_update_attachment_metadata( $attachment_id, $attachment_meta );

		}

		function reset_flags( $attachment_id, $request_id, $sent_ids ) {
                        $sent_ids = array_diff( $sent_ids, array( $attachment_id ) );
                        
                        $sent_request_id = get_option( WP_SMPRO_PREFIX . "bulk-sent", 0 );
                        
			if ( $request_id === $sent_request_id
			     && empty( $sent_ids )
			) {
                        	delete_option( WP_SMPRO_PREFIX . "bulk-sent" );
				delete_option( WP_SMPRO_PREFIX . "bulk-received" );

				return $request_id;
			}
		}

		/**
		 *
		 * @param type $zip
		 *
		 * @return boolean
		 */
		private function replace_files( $zip ) {
			WP_Filesystem();
			if ( unzip_file( $zip, $this->basedir ) ) {
				// Now that the zip file has been used, destroy it
				unlink( $zip );

				return true;
			}

			return false;
		}

		/**
		 *
		 * @param type $url
		 *
		 * @return type
		 */
		private function _get( $url ) {
			$response = wp_remote_get( $url, array('sslverify'=>false) );
                        $zip      = wp_remote_retrieve_body( $response );
                        
			return $zip;
		}

		private function update_smush_data( $attachment_id ) {
			$smush_data = get_post_meta( $attachment_id, WP_SMPRO_PREFIX . 'smush-data', true );

			$stats                      = $smush_data['stats'];
			$stats['bytes'] = (int) $stats['size_before'] - (int) $stats['size_after'];
			global $wp_smpro;

			$stats['human'] = $wp_smpro->format_bytes( $stats['bytes'] );

			$stats['percent'] = number_format_i18n(
				( (int) $stats['bytes'] / (int) $stats['size_before'] ) * 100
			);

			$smush_data['stats'] = $stats;
                        
                        update_post_meta( $attachment_id, WP_SMPRO_PREFIX . 'smush-data', $smush_data );

			return $smush_data;
		}

	}

}