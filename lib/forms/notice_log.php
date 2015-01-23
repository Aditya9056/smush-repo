<?php
global $log;
$date_fmt        = get_option( 'date_format' );
$date_fmt        = $date_fmt ? $date_fmt : 'Y-m-d';
$time_fmt        = get_option( 'time_format' );
$time_fmt        = $time_fmt ? $time_fmt : 'H:i:s';
$datetime_format = "{$date_fmt} {$time_fmt}";
$notices         = $log->get_all_notices();
?>
<div class="wrap">
	<div id="wp_smpro_notices">
		<h3>Notices</h3>
		<?php if ( $notices ) { ?>
			<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=wp-smpro-debug&action=purge' ), 'purge_notices' ); ?>"><?php _e( 'Purge Notices', WP_SMPRO_DOMAIN ); ?></a>
			<table class="widefat">
				<thead>
				<tr>
					<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'User', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Message', WP_SMPRO_DOMAIN ) ?></th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'User', WP_SMPRO_DOMAIN ) ?></th>
					<th><?php _e( 'Message', WP_SMPRO_DOMAIN ) ?></th>
				</tr>
				</tfoot>
				<tbody>
				<?php foreach ( $notices as $notice ) { ?>
					<?php $user = get_userdata( @$notice['user_id'] ); ?>
					<tr>
						<td><?php echo date( $datetime_format, $notice['date'] ); ?></td>
						<td><?php echo( ( isset( $user->user_login ) && $user->user_login ) ? $user->user_login : __( 'Unknown', WP_SMPRO_DOMAIN ) ); ?></td>
						<td><?php echo $notice['message']; ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		<?php } else { ?>
			<p><i>No notices.</i></p>
		<?php } ?>
	</div>

</div>