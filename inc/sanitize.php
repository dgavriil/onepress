<?php
/**
 * OnePress Sanitize Functions.
 *
 * @package OnePress
 */

/**
 * Sanitize CSS code
 *
 * @param $string
 * @return string
 */
function onepress_sanitize_css( $string ) {
	$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
	$string = strip_tags( $string );
	return trim( $string );
}

function onepress_sanitize_color_alpha( $color ) {
	$color = str_replace( '#', '', $color );
	if ( '' === $color ) {
		return '';
	}

	// 3 or 6 hex digits, or the empty string.
	if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', '#' . $color ) ) {
		// convert to rgb
		$colour = $color;
		if ( strlen( $colour ) == 6 ) {
			list( $r, $g, $b ) = array( $colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5] );
		} elseif ( strlen( $colour ) == 3 ) {
			list( $r, $g, $b ) = array( $colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2] );
		} else {
			return false;
		}
		$r = hexdec( $r );
		$g = hexdec( $g );
		$b = hexdec( $b );
		return 'rgba(' . join(
			',', array(
				'r' => $r,
				'g' => $g,
				'b' => $b,
				'a' => 1,
			)
		) . ')';

	}

	return strpos( trim( $color ), 'rgb' ) !== false ? $color : false;
}

/**
 * Sanitize repeatable data
 *
 * @param $input
 * @param $setting object $wp_customize
 * @return bool|mixed|string|void
 */
function onepress_sanitize_repeatable_data_field( $input, $setting ) {
	$control = $setting->manager->get_control( $setting->id );

	$fields = $control->fields;
	if ( is_string( $input ) ) {
		$input = json_decode( wp_unslash( $input ), true );
	}
	$data = wp_parse_args( $input, array() );

	if ( ! is_array( $data ) ) {
		return false;
	}
	if ( ! isset( $data['_items'] ) ) {
		return false;
	}
	$data = $data['_items'];

	foreach ( $data as $i => $item_data ) {
		foreach ( $item_data as $id => $value ) {

			if ( isset( $fields[ $id ] ) ) {
				switch ( strtolower( $fields[ $id ]['type'] ) ) {
					case 'text':
						$data[ $i ][ $id ] = sanitize_text_field( $value );
						break;
					case 'textarea':
					case 'editor':
						$data[ $i ][ $id ] = wp_kses_post( $value );
						break;
					case 'color':
						$data[ $i ][ $id ] = sanitize_hex_color_no_hash( $value );
						break;
					case 'coloralpha':
						$data[ $i ][ $id ] = onepress_sanitize_color_alpha( $value );
						break;
					case 'checkbox':
						$data[ $i ][ $id ] = onepress_sanitize_checkbox( $value );
						break;
					case 'select':
						$data[ $i ][ $id ] = '';
						if ( is_array( $fields[ $id ]['options'] ) && ! empty( $fields[ $id ]['options'] ) ) {
							// if is multiple choices
							if ( is_array( $value ) ) {
								foreach ( $value as $k => $v ) {
									if ( isset( $fields[ $id ]['options'][ $v ] ) ) {
										$value [ $k ] = $v;
									}
								}
								$data[ $i ][ $id ] = $value;
							} else { // is single choice
								if ( isset( $fields[ $id ]['options'][ $value ] ) ) {
									$data[ $i ][ $id ] = $value;
								}
							}
						}
						break;
					case 'radio':
						$data[ $i ][ $id ] = sanitize_text_field( $value );
						break;
					case 'media':
						$value                    = wp_parse_args(
							$value,
							array(
								'url' => '',
								'id'  => false,
							)
						);
						$value['id']              = absint( $value['id'] );
						$data[ $i ][ $id ]['url'] = sanitize_text_field( $value['url'] );

						if ( $url = wp_get_attachment_url( $value['id'] ) ) {
							$data[ $i ][ $id ]['id']  = $value['id'];
							$data[ $i ][ $id ]['url'] = $url;
						} else {
							$data[ $i ][ $id ]['id'] = '';
						}

						break;
					default:
						$data[ $i ][ $id ] = wp_kses_post( $value );
				}
			} else {
				$data[ $i ][ $id ] = wp_kses_post( $value );
			}

			if ( count( $data[ $i ] ) != count( $fields ) ) {
				foreach ( $fields as $k => $f ) {
					if ( ! isset( $data[ $i ][ $k ] ) ) {
						$data[ $i ][ $k ] = '';
					}
				}
			}
		}
	}

	return $data;
}


function onepress_sanitize_file_url( $file_url ) {
	$output   = '';
	$filetype = wp_check_filetype( $file_url );
	if ( $filetype['ext'] ) {
		$output = esc_url( $file_url );
	}
	return $output;
}

/**
 * Conditional to show more hero settings
 *
 * @param $control
 * @return bool
 */
function onepress_hero_fullscreen_callback( $control ) {
	if ( '' == $control->manager->get_setting( 'onepress_hero_fullscreen' )->value() ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Sanitize select choices
 *
 * @param mixed                $input   The value to sanitize.
 * @param WP_Customize_Setting $setting WP_Customize_Setting instance.
 *
 * @return string
 */
function onepress_sanitize_select( $input, $setting = null ) {

	// input must be a slug: lowercase alphanumeric characters, dashes and underscores are allowed only
	$input = sanitize_key( $input );

	// get the list of possible select options
	if ( $setting ) {
		$choices = $setting->manager->get_control( $setting->id )->choices;

		// return input if valid or return default option
		return ( array_key_exists( $input, $choices ) ? $input : $setting->default );
	} else {
		return $input;
	}

}

function onepress_sanitize_number( $input ) {
	return balanceTags( $input );
}

function onepress_sanitize_checkbox( $input ) {
	if ( 1 == $input ) {
		return 1;
	} else {
		return 0;
	}
}

function onepress_sanitize_text( $string ) {
	return wp_kses_post( balanceTags( $string ) );
}

function onepress_sanitize_html_input( $string ) {
	return wp_kses_allowed_html( $string );
}

function onepress_showon_frontpage() {
	return is_page_template( 'template-frontpage.php' );
}

function onepress_gallery_source_validate( $validity, $value ) {
	if ( ! class_exists( 'OnePress_Plus' ) ) {
		if ( 'page' != $value ) {
			$validity->add( 'notice', sprintf( esc_html__( 'Upgrade to %1s to unlock this feature.', 'onepress' ), '<a target="_blank" href="https://www.famethemes.com/plugins/onepress-plus/?utm_source=theme_customizer&utm_medium=text_link&utm_campaign=onepress_customizer#gallery">OnePress Plus</a>' ) );
		}
	}
	return $validity;
}

/**
 * Sanizite positive integer.
 *
 * @param mixed $value Value of the setting.
 * @return int|void A positve integer.
 */
function onepress_sanitize_posint( $value ) {
	return ! empty( absint( $value ) ) ? absint( $value ) : '';
}

/**
 * Validate positive integer.
 *
 * @param WP_Error $validity Filtered from `true` to `WP_Error` when invalid.
 * @param mixed    $value    Value of the setting.
 * @return WP_error|true
 */
function onepress_validate_posint( $validity, $value ) {
	if ( '' === ( trim( $value ) ) ) {
		return $validity;
	}
	if ( is_numeric( $value ) ) {
		if ( ! filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
			$validity->add( 'napi', esc_html__( 'Not a positive integer.', 'onepress' ) );
		}
	} else {
		$validity->add( 'nan', esc_html__( 'Not a number.', 'onepress' ) );
	}
	return $validity;
}

/**
 * Validate email.
 *
 * @param WP_Error $validity Filtered from `true` to `WP_Error` when invalid.
 * @param mixed    $value    Value of the setting.
 * @return WP_error|true
 */
function onepress_validate_email( $validity, $value ) {
	if ( '' === ( trim( $value ) ) ) {
		return $validity;
	}
	if ( false === is_email( $value ) ) {
		$validity->add( 'nae', esc_html__( 'Not a valid email address.', 'onepress' ) );
	}
	return $validity;
}

/**
 * Validate shortcode.
 *
 * @param WP_Error $validity Filtered from `true` to `WP_Error` when invalid.
 * @param mixed    $value    Value of the setting.
 * @return WP_error|true
 */
function onepress_validate_shortcode( $validity, $value ) {
	$value = trim( $value );

	if ( '' === ( trim( $value ) ) ) {
		return $validity;
	}
	if ( '[' !== $value[0] || ']' !== substr( $value, -1 ) ) {
		$validity->add( 'nasc', esc_html__( 'Not a valid shortcode.', 'onepress' ) );
	} else {
		$shortcode = explode( ' ', str_replace( array( '[', ']' ), '', $value ) );
		if ( ! shortcode_exists( $shortcode[0] ) ) {
			$validity->add( 'shortcode_existence', esc_html__( 'Shortcode does not exist.', 'onepress' ) );
		}
	}
	return $validity;
}
