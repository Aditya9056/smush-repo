<?php
$date_fmt        = get_option( 'date_format' );
$date_fmt        = $date_fmt ? $date_fmt : 'Y-m-d';
$time_fmt        = get_option( 'time_format' );
$time_fmt        = $time_fmt ? $time_fmt : 'H:i:s';
$datetime_format = "{$date_fmt} {$time_fmt}";
?>
<div class="wrap">
	<h2><?php _e( 'Error Log', WP_SMPRO_DOMAIN ); ?></h2>

	<h3>Errors</h3>
	<?php if ( $errors ) { ?>
		<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=wp-smpro-errorlog&action=purge' ), 'purge_log' ); ?>"><?php _e('Purge log', WP_SMPRO_DOMAIN ); ?></a>
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

	<?php if ( current_user_can( 'manage_network_options' ) ) { ?>
		<p><a href="#notices" class="wp_smpro_toggle_notices">Show/Hide notices</a></p>
		<div id="wp_smpro_notices" style="display:none">
			<h3>Notices</h3>
			<?php if ( $notices ) { ?>
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

		<script type="text/javascript">
			(function ($) {
				$(function () {

					$(".wp_smpro_toggle_notices").click(function () {
						if ($("#wp_smpro_notices").is(":visible")) $("#wp_smpro_notices").hide();
						else $("#wp_smpro_notices").show();
						return false;
					});

				});
			})(jQuery);
		</script>
	<?php } ?>

</div>