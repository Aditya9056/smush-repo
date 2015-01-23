<?php
global $log;
$date_fmt        = get_option( 'date_format' );
$date_fmt        = $date_fmt ? $date_fmt : 'Y-m-d';
$time_fmt        = get_option( 'time_format' );
$time_fmt        = $time_fmt ? $time_fmt : 'H:i:s';
$datetime_format = "{$date_fmt} {$time_fmt}";
$errors          = $log->get_all_errors();
?>
<div class="wrap">

	<h3>Errors</h3>
	<?php if ( $errors ) { ?>
		<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=wp-smpro-debug&action=purge' ), 'purge_log' ); ?>"><?php _e( 'Purge log', WP_SMPRO_DOMAIN ); ?></a>
		<table class="widefat">
			<thead>
			<tr>
				<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'User', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'Area', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'Type', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'Info', WP_SMPRO_DOMAIN ) ?></th>
			</tr>
			</thead>
			<tfoot>
			<tr>
				<th><?php _e( 'Date', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'User', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'Area', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'Type', WP_SMPRO_DOMAIN ) ?></th>
				<th><?php _e( 'Info', WP_SMPRO_DOMAIN ) ?></th>
			</tr>
			</tfoot>
			<tbody>
			<?php foreach ( $errors as $error ) { ?>
				<?php $user = get_userdata( @$error['user_id'] ); ?>
				<tr>
					<td><?php echo date( $datetime_format, $error['date'] ); ?></td>
					<td><?php echo( ( isset( $user->user_login ) && $user->user_login ) ? $user->user_login : __( 'Unknown', WP_SMPRO_DOMAIN ) ); ?></td>
					<td><?php echo $error['area']; ?></td>
					<td><?php echo $error['type']; ?></td>
					<td><?php echo $error['info']; ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	<?php } else { ?>
		<p><i>Your error log is empty.</i></p>
	<?php } ?>

</div>