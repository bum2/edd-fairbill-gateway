<?php

function fairbill_text_callback ( $args, $post_id ) {
	$value = get_post_meta( $post_id, $args['id'], true );
	if ( $value != "" ) {
		$value = get_post_meta( $post_id, $args['id'], true );
	}else{
		$value = isset( $args['std'] ) ? $args['std'] : '';
	}

	$output = "<tr valign='top'> \n".
		" <th scope='row'> " . $args['name'] . " </th> \n" .
		" <td><input type='text' class='regular-text' id='" . $args['id'] . "'" .
		" name='" . $args['id'] . "' value='" .  $value   . "' />\n" .
		" <label for='" . $args['id'] . "'> " . $args['desc'] . "</label>" .
		"</td></tr>";

	return $output;
}

function fairbill_rich_editor_callback ( $args, $post_id ) {
	$value = get_post_meta( $post_id, $args['id'], true );
	if ( $value != "" ) {
		$value = get_post_meta( $post_id, $args['id'], true );
	}else{
		$value = isset( $args['std'] ) ? $args['std'] : '';
	}
	$output = "<tr valign='top'> \n".
		" <th scope='row'> " . $args['name'] . " </th> \n" .
		" <td>";
		ob_start();
		wp_editor( stripslashes( $value ) , $args['id'], array( 'textarea_name' => $args['id'] ) );
	$output .= ob_get_clean();

	$output .= " <label for='" . $args['id'] . "'> " . $args['desc'] . "</label>" .
		"</td></tr>\n";

	return $output;
}


/**
 * Updates when saving post
 *
 */
function fairbill_edd_wp_post_save( $post_id ) {

	if ( ! isset( $_POST['post_type']) || 'download' !== $_POST['post_type'] ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return $post_id;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;

	$fields = fairbill_wp_edd_fields();

	foreach ($fields as $field) {
		update_post_meta( $post_id, $field['id'],  $_REQUEST[$field['id']] );
	}
}
add_action( 'save_post', 'fairbill_edd_wp_post_save' );


/**
 * Display sidebar metabox in saving post
 *
 */
function fairbill_edd_wp_print_meta_box ( $post ) {

	if ( get_post_type( $post->ID ) != 'download' ) return;

	?>
	<div class="wrap">
		<div id="tab_container_local">
			<table class="form-table">
				<?php
					$fields = fairbill_wp_edd_fields();
					foreach ($fields as $field) {
						if ( $field['type'] == 'text'){
							echo fairbill_text_callback( $field, $post->ID );
						}elseif ( $field['type'] == 'rich_editor' ) {
							echo fairbill_rich_editor_callback( $field, $post->ID ) ;
						}
					}
				?>

			</table>
		</div><!-- #tab_container-->
	</div><!-- .wrap -->
	<?php
}

function fairbill_edd_wp_show_post_fields ( $post) {
	//print_r($post);
	add_meta_box( 'fairbill_'.$post->ID, __( "Fairbill Settings", 'edd-fairbill'), "fairbill_edd_wp_print_meta_box", 'download', 'normal', 'high');

}
add_action( 'submitpost_box', 'fairbill_edd_wp_show_post_fields' );

function fairbill_wp_edd_fields () {

	$fairbill_gateway_settings = array(
		// bumbum
		/*array(
			'id' => 'fairbill_edd_wp_post_receipt',
			'name' => __( 'Fairbill Receipt Text', 'edd-fairbill' ),
			'desc' => __('The html to add to the Receipt page, once registered the payment via Fairbill', 'edd-fairbill'),// . '<br/>' . edd_get_emails_tags_list()  ,
			'type' => 'rich_editor',
		),*/
		//
		array(
			'id' => 'fairbill_edd_wp_post_from_email',
			'name' => __( 'Fairbill Email From', 'edd-fairbill' ),
			'desc' => __( 'The remitent email for the notification to the user', 'edd-fairbill' ),
			'type' => 'text',
			'size' => 'regular',
			'std'  => get_bloginfo( 'admin_email' )
		),
		array(
			'id' => 'fairbill_edd_wp_post_subject_mail',
			'name' => __( 'Fairbill Email Subject', 'edd-fairbill' ),
			'desc' => __( 'The subject of the email sended to the user (can use email tags)', 'edd-fairbill' ),//  . '<br/>' . edd_get_emails_tags_list(),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'fairbill_edd_wp_post_body_mail',
			'name' => __( 'Fairbill Email Body', 'edd-fairbill' ),
			'desc' => __('The email body when using Fairbill (using the email tags below)', 'edd-fairbill') . '<br/>' . edd_get_emails_tags_list()  ,
			'type' => 'rich_editor',
		),
	);

	return $fairbill_gateway_settings;
}

?>