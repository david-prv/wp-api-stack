<?php
// Last sync [WP20951]
/**
 * Main BackPress Formatting API.based on wp-includes/formatting.php
 *
 * Handles many functions for formatting output.
 * Excluded functions are indicated in comments
 *
 * @package BackPress
 **/

if ( !function_exists( 'wptexturize' ) ) :
/**
 * Replaces common plain text characters into formatted entities
 *
 * As an example,
 * <code>
 * 'cause today's effort makes it worth tomorrow's "holiday"...
 * </code>
 * Becomes:
 * <code>
 * &#8217;cause today&#8217;s effort makes it worth tomorrow&#8217;s &#8220;holiday&#8221;&#8230;
 * </code>
 * Code within certain html blocks are skipped.
 *
 * @since 0.71
 * @uses $wp_cockneyreplace Array of formatted entities for certain common phrases
 *
 * @param string $text The text to be formatted
 * @return string The string replaced with html entities
 */
function wptexturize($text) {
	global $wp_cockneyreplace;
	static $static_characters, $static_replacements, $dynamic_characters, $dynamic_replacements,
		$default_no_texturize_tags, $default_no_texturize_shortcodes;

	// No need to set up these static variables more than once
	if ( ! isset( $static_characters ) ) {
		/* translators: opening curly double quote */
		$opening_quote = _x( '&#8220;', 'opening curly double quote' );
		/* translators: closing curly double quote */
		$closing_quote = _x( '&#8221;', 'closing curly double quote' );

		/* translators: apostrophe, for example in 'cause or can't */
		$apos = _x( '&#8217;', 'apostrophe' );

		/* translators: prime, for example in 9' (nine feet) */
		$prime = _x( '&#8242;', 'prime' );
		/* translators: double prime, for example in 9" (nine inches) */
		$double_prime = _x( '&#8243;', 'double prime' );

		/* translators: opening curly single quote */
		$opening_single_quote = _x( '&#8216;', 'opening curly single quote' );
		/* translators: closing curly single quote */
		$closing_single_quote = _x( '&#8217;', 'closing curly single quote' );

		/* translators: en dash */
		$en_dash = _x( '&#8211;', 'en dash' );
		/* translators: em dash */
		$em_dash = _x( '&#8212;', 'em dash' );

		$default_no_texturize_tags = array('pre', 'code', 'kbd', 'style', 'script', 'tt');
		$default_no_texturize_shortcodes = array('code');

		// if a plugin has provided an autocorrect array, use it
		if ( isset($wp_cockneyreplace) ) {
			$cockney = array_keys($wp_cockneyreplace);
			$cockneyreplace = array_values($wp_cockneyreplace);
		} elseif ( "'" != $apos ) { // Only bother if we're doing a replacement.
			$cockney = array( "'tain't", "'twere", "'twas", "'tis", "'twill", "'til", "'bout", "'nuff", "'round", "'cause" );
			$cockneyreplace = array( $apos . "tain" . $apos . "t", $apos . "twere", $apos . "twas", $apos . "tis", $apos . "twill", $apos . "til", $apos . "bout", $apos . "nuff", $apos . "round", $apos . "cause" );
		} else {
			$cockney = $cockneyreplace = array();
		}

		$static_characters = array_merge( array( '---', ' -- ', '--', ' - ', 'xn&#8211;', '...', '``', '\'\'', ' (tm)' ), $cockney );
		$static_replacements = array_merge( array( $em_dash, ' ' . $em_dash . ' ', $en_dash, ' ' . $en_dash . ' ', 'xn--', '&#8230;', $opening_quote, $closing_quote, ' &#8482;' ), $cockneyreplace );

		$dynamic = array();
		if ( "'" != $apos ) {
			$dynamic[ '/\'(\d\d(?:&#8217;|\')?s)/' ] = $apos . '$1'; // '99's
			$dynamic[ '/\'(\d)/'                   ] = $apos . '$1'; // '99
		}
		if ( "'" != $opening_single_quote )
			$dynamic[ '/(\s|\A|[([{<]|")\'/'       ] = '$1' . $opening_single_quote; // opening single quote, even after (, {, <, [
		if ( '"' != $double_prime )
			$dynamic[ '/(\d)"/'                    ] = '$1' . $double_prime; // 9" (double prime)
		if ( "'" != $prime )
			$dynamic[ '/(\d)\'/'                   ] = '$1' . $prime; // 9' (prime)
		if ( "'" != $apos )
			$dynamic[ '/(\S)\'([^\'\s])/'          ] = '$1' . $apos . '$2'; // apostrophe in a word
		if ( '"' != $opening_quote )
			$dynamic[ '/(\s|\A|[([{<])"(?!\s)/'    ] = '$1' . $opening_quote . '$2'; // opening double quote, even after (, {, <, [
		if ( '"' != $closing_quote )
			$dynamic[ '/"(\s|\S|\Z)/'              ] = $closing_quote . '$1'; // closing double quote
		if ( "'" != $closing_single_quote )
			$dynamic[ '/\'([\s.]|\Z)/'             ] = $closing_single_quote . '$1'; // closing single quote

		$dynamic[ '/\b(\d+)x(\d+)\b/'              ] = '$1&#215;$2'; // 9x9 (times)

		$dynamic_characters = array_keys( $dynamic );
		$dynamic_replacements = array_values( $dynamic );
	}

	// Transform into regexp sub-expression used in _wptexturize_pushpop_element
	// Must do this everytime in case plugins use these filters in a context sensitive manner
	$no_texturize_tags = '(' . implode('|', apply_filters('no_texturize_tags', $default_no_texturize_tags) ) . ')';
	$no_texturize_shortcodes = '(' . implode('|', apply_filters('no_texturize_shortcodes', $default_no_texturize_shortcodes) ) . ')';

	$no_texturize_tags_stack = array();
	$no_texturize_shortcodes_stack = array();

	$textarr = preg_split('/(<.*>|\[.*\])/Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

	foreach ( $textarr as &$curl ) {
		if ( empty( $curl ) )
			continue;

		// Only call _wptexturize_pushpop_element if first char is correct tag opening
		$first = $curl[0];
		if ( '<' === $first ) {
			_wptexturize_pushpop_element($curl, $no_texturize_tags_stack, $no_texturize_tags, '<', '>');
		} elseif ( '[' === $first ) {
			_wptexturize_pushpop_element($curl, $no_texturize_shortcodes_stack, $no_texturize_shortcodes, '[', ']');
		} elseif ( empty($no_texturize_shortcodes_stack) && empty($no_texturize_tags_stack) ) {
			// This is not a tag, nor is the texturization disabled static strings
			$curl = str_replace($static_characters, $static_replacements, $curl);
			// regular expressions
			$curl = preg_replace($dynamic_characters, $dynamic_replacements, $curl);
		}
		$curl = preg_replace('/&([^#])(?![a-zA-Z1-4]{1,8};)/', '&#038;$1', $curl);
	}
	return implode( '', $textarr );
}
endif;

if ( !function_exists( '_wptexturize_pushpop_element' ) ) :
/**
 * Search for disabled element tags. Push element to stack on tag open and pop
 * on tag close. Assumes first character of $text is tag opening.
 *
 * @access private
 * @since 2.9.0
 *
 * @param string $text Text to check. First character is assumed to be $opening
 * @param array $stack Array used as stack of opened tag elements
 * @param string $disabled_elements Tags to match against formatted as regexp sub-expression
 * @param string $opening Tag opening character, assumed to be 1 character long
 * @param string $opening Tag closing  character
 * @return object
 */
function _wptexturize_pushpop_element($text, &$stack, $disabled_elements, $opening = '<', $closing = '>') {
	// Check if it is a closing tag -- otherwise assume opening tag
	if (strncmp($opening . '/', $text, 2)) {
		// Opening? Check $text+1 against disabled elements
		if (preg_match('/^' . $disabled_elements . '\b/', substr($text, 1), $matches)) {
			/*
			 * This disables texturize until we find a closing tag of our type
			 * (e.g. <pre>) even if there was invalid nesting before that
			 *
			 * Example: in the case <pre>sadsadasd</code>"baba"</pre>
			 *          "baba" won't be texturize
			 */

			array_push($stack, $matches[1]);
		}
	} else {
		// Closing? Check $text+2 against disabled elements
		$c = preg_quote($closing, '/');
		if (preg_match('/^' . $disabled_elements . $c . '/', substr($text, 2), $matches)) {
			$last = array_pop($stack);

			// Make sure it matches the opening tag
			if ($last != $matches[1])
				array_push($stack, $last);
		}
	}
}
endif;

// @todo: Deprecated in WP 3.4 can probably remove as was only used by autop and we never had that [WP20307]
if ( !function_exists( 'clean_pre' ) ) :
/**
 * Accepts matches array from preg_replace_callback in wpautop() or a string.
 *
 * Ensures that the contents of a <<pre>>...<</pre>> HTML block are not
 * converted into paragraphs or line-breaks.
 *
 * @since 1.2.0
 *
 * @param array|string $matches The array or string
 * @return string The pre block without paragraph/line-break conversion.
 */
function clean_pre($matches) {
	if ( is_array($matches) )
		$text = $matches[1] . $matches[2] . "</pre>";
	else
		$text = $matches;

	$text = str_replace('<br />', '', $text);
	$text = str_replace('<p>', "\n", $text);
	$text = str_replace('</p>', '', $text);

	return $text;
}
endif;

// ! function wpautop()
// ! function _autop_newline_preservation_helper()
// ! function shortcode_unautop()

if ( !function_exists('seems_utf8') ) :
/**
 * Checks to see if a string is utf8 encoded.
 *
 * NOTE: This function checks for 5-Byte sequences, UTF8
 *       has Bytes Sequences with a maximum length of 4.
 *
 * @author bmorel at ssi dot fr (modified)
 * @since 1.2.1
 *
 * @param string $str The string to be checked
 * @return bool True if $str fits a UTF-8 model, false otherwise.
 */
function seems_utf8($str) {
	$length = strlen($str);
	for ($i=0; $i < $length; $i++) {
		$c = ord($str[$i]);
		if ($c < 0x80) $n = 0; # 0bbbbbbb
		elseif (($c & 0xE0) == 0xC0) $n=1; # 110bbbbb
		elseif (($c & 0xF0) == 0xE0) $n=2; # 1110bbbb
		elseif (($c & 0xF8) == 0xF0) $n=3; # 11110bbb
		elseif (($c & 0xFC) == 0xF8) $n=4; # 111110bb
		elseif (($c & 0xFE) == 0xFC) $n=5; # 1111110b
		else return false; # Does not match any model
		for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
			if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80))
				return false;
		}
	}
	return true;
}
endif;

if ( !function_exists('_wp_specialchars') ) :
/**
 * Converts a number of special characters into their HTML entities.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to encode " to
 * &quot;, or ENT_QUOTES to do both. Default is ENT_NOQUOTES where no quotes are encoded.
 *
 * @since 1.2.2
 *
 * @param string $string The text which is to be encoded.
 * @param mixed $quote_style Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
 * @param string $charset Optional. The character encoding of the string. Default is false.
 * @param boolean $double_encode Optional. Whether to encode existing html entities. Default is false.
 * @return string The encoded text with HTML entities.
 */
function _wp_specialchars( $string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) )
		return '';

	// Don't bother if there are no specialchars - saves some processing
	if ( ! preg_match( '/[&<>"\']/', $string ) )
		return $string;

	// Account for the previous behaviour of the function when the $quote_style is not an accepted value
	if ( empty( $quote_style ) )
		$quote_style = ENT_NOQUOTES;
	elseif ( ! in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) )
		$quote_style = ENT_QUOTES;

	// Store the site charset as a static to avoid multiple calls to backpress_get_option()
	if ( !$charset ) {
		static $_charset;
		if ( !isset( $_charset ) ) {
			$_charset = backpress_get_option( 'charset' );
		}
		$charset = $_charset;
	}

	if ( in_array( $charset, array( 'utf8', 'utf-8', 'UTF8' ) ) )
		$charset = 'UTF-8';

	$_quote_style = $quote_style;

	if ( $quote_style === 'double' ) {
		$quote_style = ENT_COMPAT;
		$_quote_style = ENT_COMPAT;
	} elseif ( $quote_style === 'single' ) {
		$quote_style = ENT_NOQUOTES;
	}

	// Handle double encoding ourselves
	if ( $double_encode ) {
		$string = @htmlspecialchars( $string, $quote_style, $charset );
	} else {
		// Decode &amp; into &
		$string = wp_specialchars_decode( $string, $_quote_style );

		// Guarantee every &entity; is valid or re-encode the &
		$string = wp_kses_normalize_entities( $string );

		// Now re-encode everything except &entity;
		$string = preg_split( '/(&#?x?[0-9a-z]+;)/i', $string, -1, PREG_SPLIT_DELIM_CAPTURE );

		for ( $i = 0; $i < count( $string ); $i += 2 )
			$string[$i] = @htmlspecialchars( $string[$i], $quote_style, $charset );

		$string = implode( '', $string );
	}

	// Backwards compatibility
	if ( 'single' === $_quote_style )
		$string = str_replace( "'", '&#039;', $string );

	return $string;
}
endif;

if ( !function_exists( 'wp_specialchars_decode' ) ) :
/**
 * Converts a number of HTML entities into their special characters.
 *
 * Specifically deals with: &, <, >, ", and '.
 *
 * $quote_style can be set to ENT_COMPAT to decode " entities,
 * or ENT_QUOTES to do both " and '. Default is ENT_NOQUOTES where no quotes are decoded.
 *
 * @since 2.8
 *
 * @param string $string The text which is to be decoded.
 * @param mixed $quote_style Optional. Converts double quotes if set to ENT_COMPAT, both single and double if set to ENT_QUOTES or none if set to ENT_NOQUOTES. Also compatible with old _wp_specialchars() values; converting single quotes if set to 'single', double if set to 'double' or both if otherwise set. Default is ENT_NOQUOTES.
 * @return string The decoded text without HTML entities.
 */
function wp_specialchars_decode( $string, $quote_style = ENT_NOQUOTES ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) ) {
		return '';
	}

	// Don't bother if there are no entities - saves a lot of processing
	if ( strpos( $string, '&' ) === false ) {
		return $string;
	}

	// Match the previous behaviour of _wp_specialchars() when the $quote_style is not an accepted value
	if ( empty( $quote_style ) ) {
		$quote_style = ENT_NOQUOTES;
	} elseif ( !in_array( $quote_style, array( 0, 2, 3, 'single', 'double' ), true ) ) {
		$quote_style = ENT_QUOTES;
	}

	// More complete than get_html_translation_table( HTML_SPECIALCHARS )
	$single = array( '&#039;'  => '\'', '&#x27;' => '\'' );
	$single_preg = array( '/&#0*39;/'  => '&#039;', '/&#x0*27;/i' => '&#x27;' );
	$double = array( '&quot;' => '"', '&#034;'  => '"', '&#x22;' => '"' );
	$double_preg = array( '/&#0*34;/'  => '&#034;', '/&#x0*22;/i' => '&#x22;' );
	$others = array( '&lt;'   => '<', '&#060;'  => '<', '&gt;'   => '>', '&#062;'  => '>', '&amp;'  => '&', '&#038;'  => '&', '&#x26;' => '&' );
	$others_preg = array( '/&#0*60;/'  => '&#060;', '/&#0*62;/'  => '&#062;', '/&#0*38;/'  => '&#038;', '/&#x0*26;/i' => '&#x26;' );

	if ( $quote_style === ENT_QUOTES ) {
		$translation = array_merge( $single, $double, $others );
		$translation_preg = array_merge( $single_preg, $double_preg, $others_preg );
	} elseif ( $quote_style === ENT_COMPAT || $quote_style === 'double' ) {
		$translation = array_merge( $double, $others );
		$translation_preg = array_merge( $double_preg, $others_preg );
	} elseif ( $quote_style === 'single' ) {
		$translation = array_merge( $single, $others );
		$translation_preg = array_merge( $single_preg, $others_preg );
	} elseif ( $quote_style === ENT_NOQUOTES ) {
		$translation = $others;
		$translation_preg = $others_preg;
	}

	// Remove zero padding on numeric entities
	$string = preg_replace( array_keys( $translation_preg ), array_values( $translation_preg ), $string );

	// Replace characters according to translation table
	return strtr( $string, $translation );
}
endif;

if ( !function_exists( 'wp_check_invalid_utf8' ) ) :
/**
 * Checks for invalid UTF8 in a string.
 *
 * @since 2.8
 *
 * @param string $string The text which is to be checked.
 * @param boolean $strip Optional. Whether to attempt to strip out invalid UTF8. Default is false.
 * @return string The checked text.
 */
function wp_check_invalid_utf8( $string, $strip = false ) {
	$string = (string) $string;

	if ( 0 === strlen( $string ) ) {
		return '';
	}

	// Store the site charset as a static to avoid multiple calls to backpress_get_option()
	static $is_utf8;
	if ( !isset( $is_utf8 ) ) {
		$is_utf8 = in_array( backpress_get_option( 'charset' ), array( 'utf8', 'utf-8', 'UTF8', 'UTF-8' ) );
	}
	if ( !$is_utf8 ) {
		return $string;
	}

	// Check for support for utf8 in the installed PCRE library once and store the result in a static
	static $utf8_pcre;
	if ( !isset( $utf8_pcre ) ) {
		$utf8_pcre = @preg_match( '/^./u', 'a' );
	}
	// We can't demand utf8 in the PCRE installation, so just return the string in those cases
	if ( !$utf8_pcre ) {
		return $string;
	}

	// preg_match fails when it encounters invalid UTF8 in $string
	if ( 1 === @preg_match( '/^./us', $string ) ) {
		return $string;
	}

	// Attempt to strip the bad chars if requested (not recommended)
	if ( $strip && function_exists( 'iconv' ) ) {
		return iconv( 'utf-8', 'utf-8', $string );
	}

	return '';
}
endif;

if ( !function_exists('utf8_uri_encode') ) :
/**
 * Encode the Unicode values to be used in the URI.
 *
 * @since 1.5.0
 *
 * @param string $utf8_string
 * @param int $length Max length of the string
 * @return string String with Unicode encoded for URI.
 */
function utf8_uri_encode( $utf8_string, $length = 0 ) {
	$unicode = '';
	$values = array();
	$num_octets = 1;
	$unicode_length = 0;

	$string_length = strlen( $utf8_string );
	for ($i = 0; $i < $string_length; $i++ ) {

		$value = ord( $utf8_string[ $i ] );

		if ( $value < 128 ) {
			if ( $length && ( $unicode_length >= $length ) )
				break;
			$unicode .= chr($value);
			$unicode_length++;
		} else {
			if ( count( $values ) == 0 ) $num_octets = ( $value < 224 ) ? 2 : 3;

			$values[] = $value;

			if ( $length && ( $unicode_length + ($num_octets * 3) ) > $length )
				break;
			if ( count( $values ) == $num_octets ) {
				if ($num_octets == 3) {
					$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]) . '%' . dechex($values[2]);
					$unicode_length += 9;
				} else {
					$unicode .= '%' . dechex($values[0]) . '%' . dechex($values[1]);
					$unicode_length += 6;
				}

				$values = array();
				$num_octets = 1;
			}
		}
	}

	return $unicode;
}
endif;

if ( !function_exists('remove_accents') ) :
/**
 * Converts all accent characters to ASCII characters.
 *
 * If there are no accent characters, then the string given is just returned.
 *
 * @since 1.2.1
 *
 * @param string $string Text that might have accent characters
 * @return string Filtered string with replaced "nice" characters.
 */
function remove_accents($string) {
	if ( !preg_match('/[\x80-\xff]/', $string) )
		return $string;

	if (seems_utf8($string)) {
		$chars = array(
		// Decompositions for Latin-1 Supplement
		chr(194).chr(170) => 'a', chr(194).chr(186) => 'o',
		chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
		chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
		chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
		chr(195).chr(134) => 'AE',chr(195).chr(135) => 'C',
		chr(195).chr(136) => 'E', chr(195).chr(137) => 'E',
		chr(195).chr(138) => 'E', chr(195).chr(139) => 'E',
		chr(195).chr(140) => 'I', chr(195).chr(141) => 'I',
		chr(195).chr(142) => 'I', chr(195).chr(143) => 'I',
		chr(195).chr(144) => 'D', chr(195).chr(145) => 'N',
		chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
		chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
		chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
		chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
		chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
		chr(195).chr(158) => 'TH',chr(195).chr(159) => 's',
		chr(195).chr(160) => 'a', chr(195).chr(161) => 'a',
		chr(195).chr(162) => 'a', chr(195).chr(163) => 'a',
		chr(195).chr(164) => 'a', chr(195).chr(165) => 'a',
		chr(195).chr(166) => 'ae',chr(195).chr(167) => 'c',
		chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
		chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
		chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
		chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
		chr(195).chr(176) => 'd', chr(195).chr(177) => 'n',
		chr(195).chr(178) => 'o', chr(195).chr(179) => 'o',
		chr(195).chr(180) => 'o', chr(195).chr(181) => 'o',
		chr(195).chr(182) => 'o', chr(195).chr(184) => 'o',
		chr(195).chr(185) => 'u', chr(195).chr(186) => 'u',
		chr(195).chr(187) => 'u', chr(195).chr(188) => 'u',
		chr(195).chr(189) => 'y', chr(195).chr(190) => 'th',
		chr(195).chr(191) => 'y', chr(195).chr(152) => 'O',
		// Decompositions for Latin Extended-A
		chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
		chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
		chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
		chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
		chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
		chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
		chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
		chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
		chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
		chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
		chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
		chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
		chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
		chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
		chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
		chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
		chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
		chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
		chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
		chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
		chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
		chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
		chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
		chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
		chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
		chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
		chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
		chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
		chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
		chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
		chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
		chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
		chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
		chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
		chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
		chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
		chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
		chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
		chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
		chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
		chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
		chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
		chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
		chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
		chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
		chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
		chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
		chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
		chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
		chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
		chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
		chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
		chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
		chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
		chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
		chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
		chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
		chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
		chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
		chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
		chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
		chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
		chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
		chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
		// Decompositions for Latin Extended-B
		chr(200).chr(152) => 'S', chr(200).chr(153) => 's',
		chr(200).chr(154) => 'T', chr(200).chr(155) => 't',
		// Euro Sign
		chr(226).chr(130).chr(172) => 'E',
		// GBP (Pound) Sign
		chr(194).chr(163) => '',
		// Vowels with diacritic (Vietnamese)
		// unmarked
		chr(198).chr(160) => 'O', chr(198).chr(161) => 'o',
		chr(198).chr(175) => 'U', chr(198).chr(176) => 'u',
		// grave accent
		chr(225).chr(186).chr(166) => 'A', chr(225).chr(186).chr(167) => 'a',
		chr(225).chr(186).chr(176) => 'A', chr(225).chr(186).chr(177) => 'a',
		chr(225).chr(187).chr(128) => 'E', chr(225).chr(187).chr(129) => 'e',
		chr(225).chr(187).chr(146) => 'O', chr(225).chr(187).chr(147) => 'o',
		chr(225).chr(187).chr(156) => 'O', chr(225).chr(187).chr(157) => 'o',
		chr(225).chr(187).chr(170) => 'U', chr(225).chr(187).chr(171) => 'u',
		chr(225).chr(187).chr(178) => 'Y', chr(225).chr(187).chr(179) => 'y',
		// hook
		chr(225).chr(186).chr(162) => 'A', chr(225).chr(186).chr(163) => 'a',
		chr(225).chr(186).chr(168) => 'A', chr(225).chr(186).chr(169) => 'a',
		chr(225).chr(186).chr(178) => 'A', chr(225).chr(186).chr(179) => 'a',
		chr(225).chr(186).chr(186) => 'E', chr(225).chr(186).chr(187) => 'e',
		chr(225).chr(187).chr(130) => 'E', chr(225).chr(187).chr(131) => 'e',
		chr(225).chr(187).chr(136) => 'I', chr(225).chr(187).chr(137) => 'i',
		chr(225).chr(187).chr(142) => 'O', chr(225).chr(187).chr(143) => 'o',
		chr(225).chr(187).chr(148) => 'O', chr(225).chr(187).chr(149) => 'o',
		chr(225).chr(187).chr(158) => 'O', chr(225).chr(187).chr(159) => 'o',
		chr(225).chr(187).chr(166) => 'U', chr(225).chr(187).chr(167) => 'u',
		chr(225).chr(187).chr(172) => 'U', chr(225).chr(187).chr(173) => 'u',
		chr(225).chr(187).chr(182) => 'Y', chr(225).chr(187).chr(183) => 'y',
		// tilde
		chr(225).chr(186).chr(170) => 'A', chr(225).chr(186).chr(171) => 'a',
		chr(225).chr(186).chr(180) => 'A', chr(225).chr(186).chr(181) => 'a',
		chr(225).chr(186).chr(188) => 'E', chr(225).chr(186).chr(189) => 'e',
		chr(225).chr(187).chr(132) => 'E', chr(225).chr(187).chr(133) => 'e',
		chr(225).chr(187).chr(150) => 'O', chr(225).chr(187).chr(151) => 'o',
		chr(225).chr(187).chr(160) => 'O', chr(225).chr(187).chr(161) => 'o',
		chr(225).chr(187).chr(174) => 'U', chr(225).chr(187).chr(175) => 'u',
		chr(225).chr(187).chr(184) => 'Y', chr(225).chr(187).chr(185) => 'y',
		// acute accent
		chr(225).chr(186).chr(164) => 'A', chr(225).chr(186).chr(165) => 'a',
		chr(225).chr(186).chr(174) => 'A', chr(225).chr(186).chr(175) => 'a',
		chr(225).chr(186).chr(190) => 'E', chr(225).chr(186).chr(191) => 'e',
		chr(225).chr(187).chr(144) => 'O', chr(225).chr(187).chr(145) => 'o',
		chr(225).chr(187).chr(154) => 'O', chr(225).chr(187).chr(155) => 'o',
		chr(225).chr(187).chr(168) => 'U', chr(225).chr(187).chr(169) => 'u',
		// dot below
		chr(225).chr(186).chr(160) => 'A', chr(225).chr(186).chr(161) => 'a',
		chr(225).chr(186).chr(172) => 'A', chr(225).chr(186).chr(173) => 'a',
		chr(225).chr(186).chr(182) => 'A', chr(225).chr(186).chr(183) => 'a',
		chr(225).chr(186).chr(184) => 'E', chr(225).chr(186).chr(185) => 'e',
		chr(225).chr(187).chr(134) => 'E', chr(225).chr(187).chr(135) => 'e',
		chr(225).chr(187).chr(138) => 'I', chr(225).chr(187).chr(139) => 'i',
		chr(225).chr(187).chr(140) => 'O', chr(225).chr(187).chr(141) => 'o',
		chr(225).chr(187).chr(152) => 'O', chr(225).chr(187).chr(153) => 'o',
		chr(225).chr(187).chr(162) => 'O', chr(225).chr(187).chr(163) => 'o',
		chr(225).chr(187).chr(164) => 'U', chr(225).chr(187).chr(165) => 'u',
		chr(225).chr(187).chr(176) => 'U', chr(225).chr(187).chr(177) => 'u',
		chr(225).chr(187).chr(180) => 'Y', chr(225).chr(187).chr(181) => 'y',
		);

		$string = strtr($string, $chars);
	} else {
		// Assume ISO-8859-1 if not UTF-8
		$chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
			.chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
			.chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
			.chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
			.chr(211).chr(212).chr(213).chr(214).chr(216).chr(217).chr(218)
			.chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
			.chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
			.chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
			.chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
			.chr(252).chr(253).chr(255);

		$chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

		$string = strtr($string, $chars['in'], $chars['out']);
		$double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
		$double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
		$string = str_replace($double_chars['in'], $double_chars['out'], $string);
	}

	return $string;
}
endif;

// ! function sanitize_file_name()

if ( !function_exists('sanitize_user') ) :
/**
 * Sanitize username stripping out unsafe characters.
 *
 * Removes tags, octets, entities, and if strict is enabled, will only keep
 * alphanumeric, _, space, ., -, @. After sanitizing, it passes the username,
 * raw username (the username in the parameter), and the value of $strict as
 * parameters for the 'sanitize_user' filter.
 *
 * @since 2.0.0
 * @uses apply_filters() Calls 'sanitize_user' hook on username, raw username,
 *		and $strict parameter.
 *
 * @param string $username The username to be sanitized.
 * @param bool $strict If set limits $username to specific characters. Default false.
 * @return string The sanitized username, after passing through filters.
 */
function sanitize_user( $username, $strict = false ) {
	$raw_username = $username;
	$username = wp_strip_all_tags( $username );
	$username = remove_accents( $username );
	// Kill octets
	$username = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $username );
	$username = preg_replace( '/&.+?;/', '', $username ); // Kill entities

	// If strict, reduce to ASCII for max portability.
	if ( $strict )
		$username = preg_replace( '|[^a-z0-9 _.\-@]|i', '', $username );

	$username = trim( $username );
	// Consolidate contiguous whitespace
	$username = preg_replace( '|\s+|', ' ', $username );

	return apply_filters( 'sanitize_user', $username, $raw_username, $strict );
}
endif;

if ( !function_exists('sanitize_key') ) :
/**
 * Sanitize a string key.
 *
 * Keys are used as internal identifiers. Lowercase alphanumeric characters, dashes and underscores are allowed.
 *
 * @since 3.0.0
 *
 * @param string $key String key
 * @return string Sanitized key
 */
function sanitize_key( $key ) {
	$raw_key = $key;
	$key = strtolower( $key );
	$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
	return apply_filters( 'sanitize_key', $key, $raw_key );
}
endif;

if ( !function_exists('sanitize_title') ) :
/**
 * Sanitizes title or use fallback title.
 *
 * Specifically, HTML and PHP tags are stripped. Further actions can be added
 * via the plugin API. If $title is empty and $fallback_title is set, the latter
 * will be used.
 *
 * @since 1.0.0
 *
 * @param string $title The string to be sanitized.
 * @param string $fallback_title Optional. A title to use if $title is empty.
 * @param string $context Optional. The operation for which the string is sanitized
 * @return string The sanitized string.
 */
function sanitize_title($title, $fallback_title = '', $context = 'save') {
	$raw_title = $title;

	if ( 'save' == $context )
		$title = remove_accents($title);

	$title = apply_filters('sanitize_title', $title, $raw_title, $context);

	if ( '' === $title || false === $title )
		$title = $fallback_title;

	return $title;
}
endif;

//! function sanitize_title_for_query

if ( !function_exists('sanitize_title_with_dashes') ) :
/**
 * Sanitizes title, replacing whitespace and a few other characters with dashes.
 *
 * Limits the output to alphanumeric characters, underscore (_) and dash (-).
 * Whitespace becomes a dash.
 *
 * @since 1.2.0
 *
 * @param string $title The title to be sanitized.
 * @param string $raw_title Optional. Not used.
 * @param string $context Optional. The operation for which the string is sanitized.
 * @return string The sanitized title.
 */
function sanitize_title_with_dashes($title, $raw_title = '', $context = 'display') {
	$title = strip_tags($title);
	// Preserve escaped octets.
	$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
	// Remove percent signs that are not part of an octet.
	$title = str_replace('%', '', $title);
	// Restore octets.
	$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

	if (seems_utf8($title)) {
		if (function_exists('mb_strtolower')) {
			$title = mb_strtolower($title, 'UTF-8');
		}
		$title = utf8_uri_encode($title, 200);
	}

	$title = strtolower($title);
	$title = preg_replace('/&.+?;/', '', $title); // kill entities
	$title = str_replace('.', '-', $title);

	if ( 'save' == $context ) {
		// Convert nbsp, ndash and mdash to hyphens
		$title = str_replace( array( '%c2%a0', '%e2%80%93', '%e2%80%94' ), '-', $title );

		// Strip these characters entirely
		$title = str_replace( array(
			// iexcl and iquest
			'%c2%a1', '%c2%bf',
			// angle quotes
			'%c2%ab', '%c2%bb', '%e2%80%b9', '%e2%80%ba',
			// curly quotes
			'%e2%80%98', '%e2%80%99', '%e2%80%9c', '%e2%80%9d',
			'%e2%80%9a', '%e2%80%9b', '%e2%80%9e', '%e2%80%9f',
			// copy, reg, deg, hellip and trade
			'%c2%a9', '%c2%ae', '%c2%b0', '%e2%80%a6', '%e2%84%a2',
		), '', $title );

		// Convert times to x
		$title = str_replace( '%c3%97', 'x', $title );
	}

	$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
	$title = preg_replace('/\s+/', '-', $title);
	$title = preg_replace('|-+|', '-', $title);
	$title = trim($title, '-');

	return $title;
}
endif;

// ! function sanitize_sql_orderby()
// ! function sanitize_html_class()
// ! function convert_chars()
// ! function balanceTags()

if ( !function_exists( 'force_balance_tags' ) ) :
/**
 * Balances tags of string using a modified stack.
 *
 * @since 2.0.4
 *
 * @author Leonard Lin <leonard@acm.org>
 * @license GPL
 * @copyright November 4, 2001
 * @version 1.1
 * @todo Make better - change loop condition to $text in 1.2
 * @internal Modified by Scott Reilly (coffee2code) 02 Aug 2004
 *		1.1  Fixed handling of append/stack pop order of end text
 *			 Added Cleaning Hooks
 *		1.0  First Version
 *
 * @param string $text Text to be balanced.
 * @return string Balanced text.
 */
function force_balance_tags( $text ) {
	$tagstack = array();
	$stacksize = 0;
	$tagqueue = '';
	$newtext = '';
	$single_tags = array( 'br', 'hr', 'img', 'input' ); // Known single-entity/self-closing tags
	$nestable_tags = array( 'blockquote', 'div', 'span', 'q' ); // Tags that can be immediately nested within themselves

	// WP bug fix for comments - in case you REALLY meant to type '< !--'
	$text = str_replace('< !--', '<    !--', $text);
	// WP bug fix for LOVE <3 (and other situations with '<' before a number)
	$text = preg_replace('#<([0-9]{1})#', '&lt;$1', $text);

	while ( preg_match("/<(\/?[\w:]*)\s*([^>]*)>/", $text, $regex) ) {
		$newtext .= $tagqueue;

		$i = strpos($text, $regex[0]);
		$l = strlen($regex[0]);

		// clear the shifter
		$tagqueue = '';
		// Pop or Push
		if ( isset($regex[1][0]) && '/' == $regex[1][0] ) { // End Tag
			$tag = strtolower(substr($regex[1],1));
			// if too many closing tags
			if( $stacksize <= 0 ) {
				$tag = '';
				// or close to be safe $tag = '/' . $tag;
			}
			// if stacktop value = tag close value then pop
			else if ( $tagstack[$stacksize - 1] == $tag ) { // found closing tag
				$tag = '</' . $tag . '>'; // Close Tag
				// Pop
				array_pop( $tagstack );
				$stacksize--;
			} else { // closing tag not at top, search for it
				for ( $j = $stacksize-1; $j >= 0; $j-- ) {
					if ( $tagstack[$j] == $tag ) {
					// add tag to tagqueue
						for ( $k = $stacksize-1; $k >= $j; $k--) {
							$tagqueue .= '</' . array_pop( $tagstack ) . '>';
							$stacksize--;
						}
						break;
					}
				}
				$tag = '';
			}
		} else { // Begin Tag
			$tag = strtolower($regex[1]);

			// Tag Cleaning

			// If self-closing or '', don't do anything.
			if ( substr($regex[2],-1) == '/' || $tag == '' ) {
				// do nothing
			}
			// ElseIf it's a known single-entity tag but it doesn't close itself, do so
			elseif ( in_array($tag, $single_tags) ) {
				$regex[2] .= '/';
			} else {	// Push the tag onto the stack
				// If the top of the stack is the same as the tag we want to push, close previous tag
				if ( $stacksize > 0 && !in_array($tag, $nestable_tags) && $tagstack[$stacksize - 1] == $tag ) {
					$tagqueue = '</' . array_pop ($tagstack) . '>';
					$stacksize--;
				}
				$stacksize = array_push ($tagstack, $tag);
			}

			// Attributes
			$attributes = $regex[2];
			if( !empty($attributes) )
				$attributes = ' '.$attributes;

			$tag = '<' . $tag . $attributes . '>';
			//If already queuing a close tag, then put this tag on, too
			if ( !empty($tagqueue) ) {
				$tagqueue .= $tag;
				$tag = '';
			}
		}
		$newtext .= substr($text, 0, $i) . $tag;
		$text = substr($text, $i + $l);
	}

	// Clear Tag Queue
	$newtext .= $tagqueue;

	// Add Remaining text
	$newtext .= $text;

	// Empty Stack
	while( $x = array_pop($tagstack) )
		$newtext .= '</' . $x . '>'; // Add remaining tags to close

	// WP fix for the bug with HTML comments
	$newtext = str_replace("< !--","<!--",$newtext);
	$newtext = str_replace("<    !--","< !--",$newtext);

	return $newtext;
}
endif;

if ( !function_exists('format_to_edit') ) :
/**
 * Acts on text which is about to be edited.
 *
 * The $content is run through esc_textarea(), which uses htmlspecialchars()
 * to convert special characters to HTML entities. If $richedit is set to true,
 * it is simply a holder for the 'format_to_edit' filter.
 *
 * @since 0.71
 *
 * @param string $content The text about to be edited.
 * @param bool $richedit Whether the $content should not pass through htmlspecialchars(). Default false (meaning it will be passed).
 * @return string The text after the filter (and possibly htmlspecialchars()) has been run.
 */
function format_to_edit( $content, $richedit = false ) {
	$content = apply_filters( 'format_to_edit', $content );
	if ( ! $richedit )
		$content = esc_textarea( $content );
	return $content;
}
endif;

// !format_to_post()

if ( !function_exists( 'zeroise' ) ) :
/**
 * Add leading zeros when necessary.
 *
 * If you set the threshold to '4' and the number is '10', then you will get
 * back '0010'. If you set the threshold to '4' and the number is '5000', then you
 * will get back '5000'.
 *
 * Uses sprintf to append the amount of zeros based on the $threshold parameter
 * and the size of the number. If the number is large enough, then no zeros will
 * be appended.
 *
 * @since 0.71
 *
 * @param mixed $number Number to append zeros to if not greater than threshold.
 * @param int $threshold Digit places number needs to be to not have zeros added.
 * @return string Adds leading zeros to number if needed.
 */
function zeroise($number, $threshold) {
	return sprintf('%0'.$threshold.'s', $number);
}
endif;

if ( !function_exists( 'backslashit' ) ) :
/**
 * Adds backslashes before letters and before a number at the start of a string.
 *
 * @since 0.71
 *
 * @param string $string Value to which backslashes will be added.
 * @return string String with backslashes inserted.
 */
function backslashit($string) {
	$string = preg_replace('/^([0-9])/', '\\\\\\\\\1', $string);
	$string = preg_replace('/([a-z])/i', '\\\\\1', $string);
	return $string;
}
endif;

if ( !function_exists( 'trailingslashit' ) ) :
/**
 * Appends a trailing slash.
 *
 * Will remove trailing slash if it exists already before adding a trailing
 * slash. This prevents double slashing a string or path.
 *
 * The primary use of this is for paths and thus should be used for paths. It is
 * not restricted to paths and offers no specific path support.
 *
 * @since 1.2.0
 * @uses untrailingslashit() Unslashes string if it was slashed already.
 *
 * @param string $string What to add the trailing slash to.
 * @return string String with trailing slash added.
 */
function trailingslashit($string) {
	return untrailingslashit($string) . '/';
}
endif;

if ( !function_exists( 'untrailingslashit' ) ) :
/**
 * Removes trailing slash if it exists.
 *
 * The primary use of this is for paths and thus should be used for paths. It is
 * not restricted to paths and offers no specific path support.
 *
 * @since 2.2.0
 *
 * @param string $string What to remove the trailing slash from.
 * @return string String without the trailing slash.
 */
function untrailingslashit($string) {
	return rtrim($string, '/');
}
endif;

// ! function addslashes_gpc()

if ( !function_exists('stripslashes_deep') ) :
/**
 * Navigates through an array and removes slashes from the values.
 *
 * If an array is passed, the array_map() function causes a callback to pass the
 * value back to the function. The slashes from this value will removed.
 *
 * @since 2.0.0
 *
 * @param array|string $value The array or string to be stripped.
 * @return array|string Stripped array (or string in the callback).
 */
function stripslashes_deep($value) {
	if ( is_array($value) ) {
		$value = array_map('stripslashes_deep', $value);
	} elseif ( is_object($value) ) {
		$vars = get_object_vars( $value );
		foreach ($vars as $key=>$data) {
			$value->{$key} = stripslashes_deep( $data );
		}
	} else {
		$value = stripslashes($value);
	}

	return $value;
}
endif;

if ( !function_exists( 'rawurlencode_deep' ) ) :
/**
 * Navigates through an array and raw encodes the values to be used in a URL.
 *
 * @since 3.4.0
 *
 * @param array|string $value The array or string to be encoded.
 * @return array|string $value The encoded array (or string from the callback).
 */
function rawurlencode_deep( $value ) {
	return is_array( $value ) ? array_map( 'rawurlencode_deep', $value ) : rawurlencode( $value );
}
endif;
	
if ( !function_exists( 'urlencode_deep' ) ) :
/**
 * Navigates through an array and encodes the values to be used in a URL.
 *
 * Uses a callback to pass the value of the array back to the function as a
 * string.
 *
 * @since 2.2.0
 *
 * @param array|string $value The array or string to be encoded.
 * @return array|string $value The encoded array (or string from the callback).
 */
function urlencode_deep($value) {
	$value = is_array($value) ? array_map('urlencode_deep', $value) : urlencode($value);
	return $value;
}
endif;

// ! function antispambot()

if ( !function_exists( '_make_url_clickable_cb' ) ) :
/**
 * Callback to convert URI match to HTML A element.
 *
 * This function was backported from 2.5.0 to 2.3.2. Regex callback for {@link
 * make_clickable()}.
 *
 * @since 2.3.2
 * @access private
 *
 * @param array $matches Single Regex Match.
 * @return string HTML A element with URI address.
 */
function _make_url_clickable_cb($matches) {
	$url = $matches[2];

	if ( ')' == $matches[3] && strpos( $url, '(' ) ) {
		// If the trailing character is a closing parethesis, and the URL has an opening parenthesis in it, add the closing parenthesis to the URL.
		// Then we can let the parenthesis balancer do its thing below.
		$url .= $matches[3];
		$suffix = '';
	} else {
		$suffix = $matches[3];
	}

	// Include parentheses in the URL only if paired
	while ( substr_count( $url, '(' ) < substr_count( $url, ')' ) ) {
		$suffix = strrchr( $url, ')' ) . $suffix;
		$url = substr( $url, 0, strrpos( $url, ')' ) );
	}

	$url = esc_url($url);
	if ( empty($url) )
		return $matches[0];

	return $matches[1] . "<a href=\"$url\" rel=\"nofollow\">$url</a>" . $suffix;
}
endif;

if ( !function_exists( '_make_web_ftp_clickable_cb' ) ) :
/**
 * Callback to convert URL match to HTML A element.
 *
 * This function was backported from 2.5.0 to 2.3.2. Regex callback for {@link
 * make_clickable()}.
 *
 * @since 2.3.2
 * @access private
 *
 * @param array $matches Single Regex Match.
 * @return string HTML A element with URL address.
 */
function _make_web_ftp_clickable_cb($matches) {
	$ret = '';
	$dest = $matches[2];
	$dest = 'http://' . $dest;
	$dest = esc_url($dest);
	if ( empty($dest) )
		return $matches[0];

	// removed trailing [.,;:)] from URL
	if ( in_array( substr($dest, -1), array('.', ',', ';', ':', ')') ) === true ) {
		$ret = substr($dest, -1);
		$dest = substr($dest, 0, strlen($dest)-1);
	}
	return $matches[1] . "<a href=\"$dest\" rel=\"nofollow\">$dest</a>$ret";
}
endif;

if ( !function_exists( '_make_email_clickable_cb' ) ) :
/**
 * Callback to convert email address match to HTML A element.
 *
 * This function was backported from 2.5.0 to 2.3.2. Regex callback for {@link
 * make_clickable()}.
 *
 * @since 2.3.2
 * @access private
 *
 * @param array $matches Single Regex Match.
 * @return string HTML A element with email address.
 */
function _make_email_clickable_cb($matches) {
	$email = $matches[2] . '@' . $matches[3];
	return $matches[1] . "<a href=\"mailto:$email\">$email</a>";
}
endif;

if ( !function_exists( 'make_clickable' ) ) :
/**
 * Convert plaintext URI to HTML links.
 *
 * Converts URI, www and ftp, and email addresses. Finishes by fixing links
 * within links.
 *
 * @since 0.71
 *
 * @param string $text Content to convert URIs.
 * @return string Content with converted URIs.
 */
function make_clickable( $text ) {
	$r = '';
	$textarr = preg_split( '/(<[^<>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE ); // split out HTML tags
	foreach ( $textarr as $piece ) {
		if ( empty( $piece ) || ( $piece[0] == '<' && ! preg_match('|^<\s*[\w]{1,20}+://|', $piece) ) ) {
			$r .= $piece;
			continue;
		}

		// Long strings might contain expensive edge cases ...
		if ( 10000 < strlen( $piece ) ) {
			// ... break it up
			foreach ( _split_str_by_whitespace( $piece, 2100 ) as $chunk ) { // 2100: Extra room for scheme and leading and trailing paretheses
				if ( 2101 < strlen( $chunk ) ) {
					$r .= $chunk; // Too big, no whitespace: bail.
				} else {
					$r .= make_clickable( $chunk );
				}
			}
		} else {
			$ret = " $piece "; // Pad with whitespace to simplify the regexes

			$url_clickable = '~
				([\\s(<.,;:!?])                                        # 1: Leading whitespace, or punctuation
				(                                                      # 2: URL
					[\\w]{1,20}+://                                # Scheme and hier-part prefix
					(?=\S{1,2000}\s)                               # Limit to URLs less than about 2000 characters long
					[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+         # Non-punctuation URL character
					(?:                                            # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
						[\'.,;:!?)]                            # Punctuation URL character
						[\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
					)*
				)
				(\)?)                                                  # 3: Trailing closing parenthesis (for parethesis balancing post processing)
			~xS'; // The regex is a non-anchored pattern and does not have a single fixed starting character.
			      // Tell PCRE to spend more time optimizing since, when used on a page load, it will probably be used several times.

			$ret = preg_replace_callback( $url_clickable, '_make_url_clickable_cb', $ret );

			$ret = preg_replace_callback( '#([\s>])((www|ftp)\.[\w\\x80-\\xff\#$%&~/.\-;:=,?@\[\]+]+)#is', '_make_web_ftp_clickable_cb', $ret );
			$ret = preg_replace_callback( '#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', '_make_email_clickable_cb', $ret );

			$ret = substr( $ret, 1, -1 ); // Remove our whitespace padding.
			$r .= $ret;
		}
	}

	// Cleanup of accidental links within links
	$r = preg_replace( '#(<a( [^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i', "$1$3</a>", $r );
	return $r;
}
endif;

if ( !function_exists('_split_str_by_whitespace') ) :
/**
 * Breaks a string into chunks by splitting at whitespace characters.
 * The length of each returned chunk is as close to the specified length goal as possible,
 * with the caveat that each chunk includes its trailing delimiter.
 * Chunks longer than the goal are guaranteed to not have any inner whitespace.
 *
 * Joining the returned chunks with empty delimiters reconstructs the input string losslessly.
 *
 * Input string must have no null characters (or eventual transformations on output chunks must not care about null characters)
 *
 * <code>
 * _split_str_by_whitespace( "1234 67890 1234 67890a cd 1234   890 123456789 1234567890a    45678   1 3 5 7 90 ", 10 ) ==
 * array (
 *   0 => '1234 67890 ',  // 11 characters: Perfect split
 *   1 => '1234 ',        //  5 characters: '1234 67890a' was too long
 *   2 => '67890a cd ',   // 10 characters: '67890a cd 1234' was too long
 *   3 => '1234   890 ',  // 11 characters: Perfect split
 *   4 => '123456789 ',   // 10 characters: '123456789 1234567890a' was too long
 *   5 => '1234567890a ', // 12 characters: Too long, but no inner whitespace on which to split
 *   6 => '   45678   ',  // 11 characters: Perfect split
 *   7 => '1 3 5 7 9',    //  9 characters: End of $string
 * );
 * </code>
 *
 * @since 3.4.0
 * @access private
 *
 * @param string $string The string to split
 * @param    int $goal   The desired chunk length.
 * @return array Numeric array of chunks.
 */
function _split_str_by_whitespace( $string, $goal ) {
	$chunks = array();

	$string_nullspace = strtr( $string, "\r\n\t\v\f ", "\000\000\000\000\000\000" );

	while ( $goal < strlen( $string_nullspace ) ) {
		$pos = strrpos( substr( $string_nullspace, 0, $goal + 1 ), "\000" );

		if ( false === $pos ) {
			$pos = strpos( $string_nullspace, "\000", $goal + 1 );
			if ( false === $pos ) {
				break;
			}
		}

		$chunks[] = substr( $string, 0, $pos + 1 );
		$string = substr( $string, $pos + 1 );
		$string_nullspace = substr( $string_nullspace, $pos + 1 );
	}

	if ( $string ) {
		$chunks[] = $string;
	}

	return $chunks;
}
endif;

// ! function wp_rel_nofollow()
// ! function wp_rel_nofollow_callback()
// ! function translate_smiley()
// ! function convert_smilies()

if ( !function_exists('is_email') ) :
/**
 * Verifies that an email is valid.
 *
 * Does not grok i18n domains. Not RFC compliant.
 *
 * @since 0.71
 *
 * @param string $email Email address to verify.
 * @param boolean $deprecated Deprecated.
 * @return string|bool Either false or the valid email address.
 */
function is_email( $email, $deprecated = false ) {
	// Test for the minimum length the email can be
	if ( strlen( $email ) < 3 ) {
		return apply_filters( 'is_email', false, $email, 'email_too_short' );
	}

	// Test for an @ character after the first position
	if ( strpos( $email, '@', 1 ) === false ) {
		return apply_filters( 'is_email', false, $email, 'email_no_at' );
	}

	// Split out the local and domain parts
	list( $local, $domain ) = explode( '@', $email, 2 );

	// LOCAL PART
	// Test for invalid characters
	if ( !preg_match( '/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]+$/', $local ) ) {
		return apply_filters( 'is_email', false, $email, 'local_invalid_chars' );
	}

	// DOMAIN PART
	// Test for sequences of periods
	if ( preg_match( '/\.{2,}/', $domain ) ) {
		return apply_filters( 'is_email', false, $email, 'domain_period_sequence' );
	}

	// Test for leading and trailing periods and whitespace
	if ( trim( $domain, " \t\n\r\0\x0B." ) !== $domain ) {
		return apply_filters( 'is_email', false, $email, 'domain_period_limits' );
	}

	// Split the domain into subs
	$subs = explode( '.', $domain );

	// Assume the domain will have at least two subs
	if ( 2 > count( $subs ) ) {
		return apply_filters( 'is_email', false, $email, 'domain_no_periods' );
	}

	// Loop through each sub
	foreach ( $subs as $sub ) {
		// Test for leading and trailing hyphens and whitespace
		if ( trim( $sub, " \t\n\r\0\x0B-" ) !== $sub ) {
			return apply_filters( 'is_email', false, $email, 'sub_hyphen_limits' );
		}

		// Test for invalid characters
		if ( !preg_match('/^[a-z0-9-]+$/i', $sub ) ) {
			return apply_filters( 'is_email', false, $email, 'sub_invalid_chars' );
		}
	}

	// Congratulations your email made it!
	return apply_filters( 'is_email', $email, $email, null );
}
endif;

// ! function wp_iso_descrambler()
// ! function _wp_iso_convert()
// ! function get_gmt_from_date()
// ! function get_date_from_gmt()
// ! function iso8601_timezone_to_offset()
// ! function iso8601_to_datetime()
// ! popuplinks()

if ( !function_exists('sanitize_email') ) :
/**
 * Strips out all characters that are not allowable in an email.
 *
 * @since 1.5.0
 *
 * @param string $email Email address to filter.
 * @return string Filtered email address.
 */
function sanitize_email( $email ) {
	// Test for the minimum length the email can be
	if ( strlen( $email ) < 3 ) {
		return apply_filters( 'sanitize_email', '', $email, 'email_too_short' );
	}

	// Test for an @ character after the first position
	if ( strpos( $email, '@', 1 ) === false ) {
		return apply_filters( 'sanitize_email', '', $email, 'email_no_at' );
	}

	// Split out the local and domain parts
	list( $local, $domain ) = explode( '@', $email, 2 );

	// LOCAL PART
	// Test for invalid characters
	$local = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local );
	if ( '' === $local ) {
		return apply_filters( 'sanitize_email', '', $email, 'local_invalid_chars' );
	}

	// DOMAIN PART
	// Test for sequences of periods
	$domain = preg_replace( '/\.{2,}/', '', $domain );
	if ( '' === $domain ) {
		return apply_filters( 'sanitize_email', '', $email, 'domain_period_sequence' );
	}

	// Test for leading and trailing periods and whitespace
	$domain = trim( $domain, " \t\n\r\0\x0B." );
	if ( '' === $domain ) {
		return apply_filters( 'sanitize_email', '', $email, 'domain_period_limits' );
	}

	// Split the domain into subs
	$subs = explode( '.', $domain );

	// Assume the domain will have at least two subs
	if ( 2 > count( $subs ) ) {
		return apply_filters( 'sanitize_email', '', $email, 'domain_no_periods' );
	}

	// Create an array that will contain valid subs
	$new_subs = array();

	// Loop through each sub
	foreach ( $subs as $sub ) {
		// Test for leading and trailing hyphens
		$sub = trim( $sub, " \t\n\r\0\x0B-" );

		// Test for invalid characters
		$sub = preg_replace( '/^[^a-z0-9-]+$/i', '', $sub );

		// If there's anything left, add it to the valid subs
		if ( '' !== $sub ) {
			$new_subs[] = $sub;
		}
	}

	// If there aren't 2 or more valid subs
	if ( 2 > count( $new_subs ) ) {
		return apply_filters( 'sanitize_email', '', $email, 'domain_no_valid_subs' );
	}

	// Join valid subs into the new domain
	$domain = join( '.', $new_subs );

	// Put the email back together
	$email = $local . '@' . $domain;

	// Congratulations your email made it!
	return apply_filters( 'sanitize_email', $email, $email, null );
}
endif;

// ! function human_time_diff()
// ! function wp_trim_excerpt()
// ! function wp_trim_excerpt()

if ( !function_exists( 'ent2ncr' ) ) : // Current at [WP9840]
/**
 * Converts named entities into numbered entities.
 *
 * @since 1.5.1
 *
 * @param string $text The text within which entities will be converted.
 * @return string Text with converted entities.
 */
function ent2ncr($text) {

	// Allow a plugin to short-circuit and override the mappings.
	$filtered = apply_filters( 'pre_ent2ncr', null, $text );
	if( null !== $filtered )
		return $filtered;

	$to_ncr = array(
		'&quot;' => '&#34;',
		'&amp;' => '&#38;',
		'&frasl;' => '&#47;',
		'&lt;' => '&#60;',
		'&gt;' => '&#62;',
		'|' => '&#124;',
		'&nbsp;' => '&#160;',
		'&iexcl;' => '&#161;',
		'&cent;' => '&#162;',
		'&pound;' => '&#163;',
		'&curren;' => '&#164;',
		'&yen;' => '&#165;',
		'&brvbar;' => '&#166;',
		'&brkbar;' => '&#166;',
		'&sect;' => '&#167;',
		'&uml;' => '&#168;',
		'&die;' => '&#168;',
		'&copy;' => '&#169;',
		'&ordf;' => '&#170;',
		'&laquo;' => '&#171;',
		'&not;' => '&#172;',
		'&shy;' => '&#173;',
		'&reg;' => '&#174;',
		'&macr;' => '&#175;',
		'&hibar;' => '&#175;',
		'&deg;' => '&#176;',
		'&plusmn;' => '&#177;',
		'&sup2;' => '&#178;',
		'&sup3;' => '&#179;',
		'&acute;' => '&#180;',
		'&micro;' => '&#181;',
		'&para;' => '&#182;',
		'&middot;' => '&#183;',
		'&cedil;' => '&#184;',
		'&sup1;' => '&#185;',
		'&ordm;' => '&#186;',
		'&raquo;' => '&#187;',
		'&frac14;' => '&#188;',
		'&frac12;' => '&#189;',
		'&frac34;' => '&#190;',
		'&iquest;' => '&#191;',
		'&Agrave;' => '&#192;',
		'&Aacute;' => '&#193;',
		'&Acirc;' => '&#194;',
		'&Atilde;' => '&#195;',
		'&Auml;' => '&#196;',
		'&Aring;' => '&#197;',
		'&AElig;' => '&#198;',
		'&Ccedil;' => '&#199;',
		'&Egrave;' => '&#200;',
		'&Eacute;' => '&#201;',
		'&Ecirc;' => '&#202;',
		'&Euml;' => '&#203;',
		'&Igrave;' => '&#204;',
		'&Iacute;' => '&#205;',
		'&Icirc;' => '&#206;',
		'&Iuml;' => '&#207;',
		'&ETH;' => '&#208;',
		'&Ntilde;' => '&#209;',
		'&Ograve;' => '&#210;',
		'&Oacute;' => '&#211;',
		'&Ocirc;' => '&#212;',
		'&Otilde;' => '&#213;',
		'&Ouml;' => '&#214;',
		'&times;' => '&#215;',
		'&Oslash;' => '&#216;',
		'&Ugrave;' => '&#217;',
		'&Uacute;' => '&#218;',
		'&Ucirc;' => '&#219;',
		'&Uuml;' => '&#220;',
		'&Yacute;' => '&#221;',
		'&THORN;' => '&#222;',
		'&szlig;' => '&#223;',
		'&agrave;' => '&#224;',
		'&aacute;' => '&#225;',
		'&acirc;' => '&#226;',
		'&atilde;' => '&#227;',
		'&auml;' => '&#228;',
		'&aring;' => '&#229;',
		'&aelig;' => '&#230;',
		'&ccedil;' => '&#231;',
		'&egrave;' => '&#232;',
		'&eacute;' => '&#233;',
		'&ecirc;' => '&#234;',
		'&euml;' => '&#235;',
		'&igrave;' => '&#236;',
		'&iacute;' => '&#237;',
		'&icirc;' => '&#238;',
		'&iuml;' => '&#239;',
		'&eth;' => '&#240;',
		'&ntilde;' => '&#241;',
		'&ograve;' => '&#242;',
		'&oacute;' => '&#243;',
		'&ocirc;' => '&#244;',
		'&otilde;' => '&#245;',
		'&ouml;' => '&#246;',
		'&divide;' => '&#247;',
		'&oslash;' => '&#248;',
		'&ugrave;' => '&#249;',
		'&uacute;' => '&#250;',
		'&ucirc;' => '&#251;',
		'&uuml;' => '&#252;',
		'&yacute;' => '&#253;',
		'&thorn;' => '&#254;',
		'&yuml;' => '&#255;',
		'&OElig;' => '&#338;',
		'&oelig;' => '&#339;',
		'&Scaron;' => '&#352;',
		'&scaron;' => '&#353;',
		'&Yuml;' => '&#376;',
		'&fnof;' => '&#402;',
		'&circ;' => '&#710;',
		'&tilde;' => '&#732;',
		'&Alpha;' => '&#913;',
		'&Beta;' => '&#914;',
		'&Gamma;' => '&#915;',
		'&Delta;' => '&#916;',
		'&Epsilon;' => '&#917;',
		'&Zeta;' => '&#918;',
		'&Eta;' => '&#919;',
		'&Theta;' => '&#920;',
		'&Iota;' => '&#921;',
		'&Kappa;' => '&#922;',
		'&Lambda;' => '&#923;',
		'&Mu;' => '&#924;',
		'&Nu;' => '&#925;',
		'&Xi;' => '&#926;',
		'&Omicron;' => '&#927;',
		'&Pi;' => '&#928;',
		'&Rho;' => '&#929;',
		'&Sigma;' => '&#931;',
		'&Tau;' => '&#932;',
		'&Upsilon;' => '&#933;',
		'&Phi;' => '&#934;',
		'&Chi;' => '&#935;',
		'&Psi;' => '&#936;',
		'&Omega;' => '&#937;',
		'&alpha;' => '&#945;',
		'&beta;' => '&#946;',
		'&gamma;' => '&#947;',
		'&delta;' => '&#948;',
		'&epsilon;' => '&#949;',
		'&zeta;' => '&#950;',
		'&eta;' => '&#951;',
		'&theta;' => '&#952;',
		'&iota;' => '&#953;',
		'&kappa;' => '&#954;',
		'&lambda;' => '&#955;',
		'&mu;' => '&#956;',
		'&nu;' => '&#957;',
		'&xi;' => '&#958;',
		'&omicron;' => '&#959;',
		'&pi;' => '&#960;',
		'&rho;' => '&#961;',
		'&sigmaf;' => '&#962;',
		'&sigma;' => '&#963;',
		'&tau;' => '&#964;',
		'&upsilon;' => '&#965;',
		'&phi;' => '&#966;',
		'&chi;' => '&#967;',
		'&psi;' => '&#968;',
		'&omega;' => '&#969;',
		'&thetasym;' => '&#977;',
		'&upsih;' => '&#978;',
		'&piv;' => '&#982;',
		'&ensp;' => '&#8194;',
		'&emsp;' => '&#8195;',
		'&thinsp;' => '&#8201;',
		'&zwnj;' => '&#8204;',
		'&zwj;' => '&#8205;',
		'&lrm;' => '&#8206;',
		'&rlm;' => '&#8207;',
		'&ndash;' => '&#8211;',
		'&mdash;' => '&#8212;',
		'&lsquo;' => '&#8216;',
		'&rsquo;' => '&#8217;',
		'&sbquo;' => '&#8218;',
		'&ldquo;' => '&#8220;',
		'&rdquo;' => '&#8221;',
		'&bdquo;' => '&#8222;',
		'&dagger;' => '&#8224;',
		'&Dagger;' => '&#8225;',
		'&bull;' => '&#8226;',
		'&hellip;' => '&#8230;',
		'&permil;' => '&#8240;',
		'&prime;' => '&#8242;',
		'&Prime;' => '&#8243;',
		'&lsaquo;' => '&#8249;',
		'&rsaquo;' => '&#8250;',
		'&oline;' => '&#8254;',
		'&frasl;' => '&#8260;',
		'&euro;' => '&#8364;',
		'&image;' => '&#8465;',
		'&weierp;' => '&#8472;',
		'&real;' => '&#8476;',
		'&trade;' => '&#8482;',
		'&alefsym;' => '&#8501;',
		'&crarr;' => '&#8629;',
		'&lArr;' => '&#8656;',
		'&uArr;' => '&#8657;',
		'&rArr;' => '&#8658;',
		'&dArr;' => '&#8659;',
		'&hArr;' => '&#8660;',
		'&forall;' => '&#8704;',
		'&part;' => '&#8706;',
		'&exist;' => '&#8707;',
		'&empty;' => '&#8709;',
		'&nabla;' => '&#8711;',
		'&isin;' => '&#8712;',
		'&notin;' => '&#8713;',
		'&ni;' => '&#8715;',
		'&prod;' => '&#8719;',
		'&sum;' => '&#8721;',
		'&minus;' => '&#8722;',
		'&lowast;' => '&#8727;',
		'&radic;' => '&#8730;',
		'&prop;' => '&#8733;',
		'&infin;' => '&#8734;',
		'&ang;' => '&#8736;',
		'&and;' => '&#8743;',
		'&or;' => '&#8744;',
		'&cap;' => '&#8745;',
		'&cup;' => '&#8746;',
		'&int;' => '&#8747;',
		'&there4;' => '&#8756;',
		'&sim;' => '&#8764;',
		'&cong;' => '&#8773;',
		'&asymp;' => '&#8776;',
		'&ne;' => '&#8800;',
		'&equiv;' => '&#8801;',
		'&le;' => '&#8804;',
		'&ge;' => '&#8805;',
		'&sub;' => '&#8834;',
		'&sup;' => '&#8835;',
		'&nsub;' => '&#8836;',
		'&sube;' => '&#8838;',
		'&supe;' => '&#8839;',
		'&oplus;' => '&#8853;',
		'&otimes;' => '&#8855;',
		'&perp;' => '&#8869;',
		'&sdot;' => '&#8901;',
		'&lceil;' => '&#8968;',
		'&rceil;' => '&#8969;',
		'&lfloor;' => '&#8970;',
		'&rfloor;' => '&#8971;',
		'&lang;' => '&#9001;',
		'&rang;' => '&#9002;',
		'&larr;' => '&#8592;',
		'&uarr;' => '&#8593;',
		'&rarr;' => '&#8594;',
		'&darr;' => '&#8595;',
		'&harr;' => '&#8596;',
		'&loz;' => '&#9674;',
		'&spades;' => '&#9824;',
		'&clubs;' => '&#9827;',
		'&hearts;' => '&#9829;',
		'&diams;' => '&#9830;'
	);

	return str_replace( array_keys($to_ncr), array_values($to_ncr), $text );
}
endif;

// ! function wp_richedit_pre()
// ! function wp_htmledit_pre()

if ( !function_exists( '_deep_replace' ) ) :
/**
 * Perform a deep string replace operation to ensure the values in $search are no longer present
 *
 * Repeats the replacement operation until it no longer replaces anything so as to remove "nested" values
 * e.g. $subject = '%0%0%0DDD', $search ='%0D', $result ='' rather than the '%0%0DD' that
 * str_replace would return
 *
 * @since 2.8.1
 * @access private
 *
 * @param string|array $search
 * @param string $subject
 * @return string The processed string
 */
function _deep_replace($search, $subject){
	$found = true;
	while($found) {
		$found = false;
		foreach( (array) $search as $val ) {
			while(strpos($subject, $val) !== false) {
				$found = true;
				$subject = str_replace($val, '', $subject);
			}
		}
	}

	return $subject;
}
endif;

if ( !function_exists( 'esc_sql' ) ) :
/**
 * Escapes data for use in a MySQL query
 *
 * This is just a handy shortcut for $bpdb->escape(), for completeness' sake
 *
 * @since 2.8.0
 * @param string $sql Unescaped SQL data
 * @return string The cleaned $sql
 */
function esc_sql( $sql ) {
	global $bpdb;
	return $bpdb->escape( $sql );
}
endif;

// @todo: Deprecated function we should remove.
if ( !function_exists( 'clean_url' ) ) :
/**
 * Checks and cleans a URL.
 *
 * A number of characters are removed from the URL. If the URL is for displaying
 * (the default behaviour) ampersands are also replaced. The 'clean_url' filter
 * is applied to the returned cleaned URL.
 *
 * @since 1.2.0
 * @deprecated 3.0.0
 * @deprecated Use esc_url()
 * @see Alias for esc_url()
 *
 * @param string $url The URL to be cleaned.
 * @param array $protocols Optional. An array of acceptable protocols.
 * @param string $context Optional. How the URL will be used. Default is 'display'.
 * @return string The cleaned $url after the 'clean_url' filter is applied.
 */
function clean_url( $url, $protocols = null, $context = 'display' ) {
	return esc_url( $url, $protocols, $context );
}
endif;

if ( !function_exists( 'esc_url' ) ) :
/**
 * Checks and cleans a URL.
 *
 * A number of characters are removed from the URL. If the URL is for displaying
 * (the default behaviour) ampersands are also replaced. The 'clean_url' filter
 * is applied to the returned cleaned URL.
 *
 * @since 2.8.0
 * @uses wp_kses_bad_protocol() To only permit protocols in the URL set
 *		via $protocols or the common ones set in the function.
 *
 * @param string $url The URL to be cleaned.
 * @param array $protocols Optional. An array of acceptable protocols.
 *		Defaults to 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'svn' if not set.
 * @param string $_context Private. Use esc_url_raw() for database usage.
 * @return string The cleaned $url after the 'clean_url' filter is applied.
 */
function esc_url( $url, $protocols = null, $_context = 'display' ) {
	$original_url = $url;

	if ( '' == $url )
		return $url;
	$url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);
	$strip = array('%0d', '%0a', '%0D', '%0A');
	$url = _deep_replace($strip, $url);
	$url = str_replace(';//', '://', $url);
	/* If the URL doesn't appear to contain a scheme, we
	 * presume it needs http:// appended (unless a relative
	 * link starting with /, # or ? or a php file).
	 */
	if ( strpos($url, ':') === false && ! in_array( $url[0], array( '/', '#', '?' ) ) &&
		! preg_match('/^[a-z0-9-]+?\.php/i', $url) )
		$url = 'http://' . $url;

	// Replace ampersands and single quotes only when displaying.
	if ( 'display' == $_context ) {
		$url = wp_kses_normalize_entities( $url );
		$url = str_replace( '&amp;', '&#038;', $url );
		$url = str_replace( "'", '&#039;', $url );
	}

	// Todo: switch to wp_allowed_protocols() once it is merged to BackPress
	if ( ! is_array( $protocols ) )
		$protocols = array ('http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'svn');
	if ( wp_kses_bad_protocol( $url, $protocols ) != $url )
		return '';

	return apply_filters('clean_url', $url, $original_url, $_context);
}
endif;

if ( !function_exists( 'esc_url_raw' ) ) :
/**
 * Performs esc_url() for database usage.
 *
 * @since 2.8.0
 * @uses esc_url()
 *
 * @param string $url The URL to be cleaned.
 * @param array $protocols An array of acceptable protocols.
 * @return string The cleaned URL.
 */
function esc_url_raw( $url, $protocols = null ) {
	return esc_url( $url, $protocols, 'db' );
}
endif;

// ! function htmlentities2()

if ( !function_exists( 'esc_js' ) ) :
/**
 * Escape single quotes, htmlspecialchar " < > &, and fix line endings.
 *
 * Escapes text strings for echoing in JS, both inline (for example in onclick="...")
 * and inside <script> tag. Note that the strings have to be in single quotes.
 * The filter 'js_escape' is also applied here.
 *
 * @since 2.8.0
 *
 * @param string $text The text to be escaped.
 * @return string Escaped text.
 */
function esc_js( $text ) {
	$safe_text = wp_check_invalid_utf8( $text );
	$safe_text = _wp_specialchars( $safe_text, ENT_COMPAT );
	$safe_text = preg_replace( '/&#(x)?0*(?(1)27|39);?/i', "'", stripslashes( $safe_text ) );
	$safe_text = str_replace( "\r", '', $safe_text );
	$safe_text = str_replace( "\n", '\\n', addslashes( $safe_text ) );
	return apply_filters( 'js_escape', $safe_text, $text );
}
endif;

// @todo: Deprecated function we should remove.
if ( !function_exists( 'js_escape' ) ) :
/**
 * Escape single quotes, specialchar double quotes, and fix line endings.
 *
 * The filter 'js_escape' is also applied by esc_js()
 *
 * @since 2.0.4
 *
 * @deprecated 2.8.0
 * @see esc_js()
 *
 * @param string $text The text to be escaped.
 * @return string Escaped text.
 */
function js_escape( $text ) {
	return esc_js( $text );
}
endif;

if ( !function_exists( 'esc_html' ) ) :
/**
 * Escaping for HTML blocks.
 *
 * @since 2.8.0
 *
 * @param string $text
 * @return string
 */
function esc_html( $text ) {
	$safe_text = wp_check_invalid_utf8( $text );
	$safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
	return apply_filters( 'esc_html', $safe_text, $text );
	return $text;
}
endif;

// @todo: Deprecated function we should remove.
if ( !function_exists( 'wp_specialchars' ) ) :
/**
 * Escaping for HTML blocks
 * @deprecated 2.8.0
 * @see esc_html()
 */
function wp_specialchars( $string, $quote_style = ENT_NOQUOTES, $charset = false, $double_encode = false ) {
	if ( func_num_args() > 1 ) { // Maintain backwards compat for people passing additional args
		$args = func_get_args();
		return call_user_func_array( '_wp_specialchars', $args );
	} else {
		return esc_html( $string );
	}
}
endif;

if ( !function_exists( 'esc_attr' ) ) :
/**
 * Escaping for HTML attributes.
 *
 * @since 2.8.0
 *
 * @param string $text
 * @return string
 */
function esc_attr( $text ) {
	$safe_text = wp_check_invalid_utf8( $text );
	$safe_text = _wp_specialchars( $safe_text, ENT_QUOTES );
	return apply_filters( 'attribute_escape', $safe_text, $text );
}
endif;

if ( !function_exists( 'esc_textarea' ) ) :
/**
 * Escaping for textarea values.
 *
 * @since 3.1
 *
 * @param string $text
 * @return string
 */
function esc_textarea( $text ) {
	$safe_text = htmlspecialchars( $text, ENT_QUOTES );
	return apply_filters( 'esc_textarea', $safe_text, $text );
}
endif;

// @todo: Deprecated function we should remove.
if ( !function_exists( 'attribute_escape' ) ) :
/**
 * Escaping for HTML attributes.
 *
 * @since 2.0.6
 *
 * @deprecated 2.8.0
 * @see esc_attr()
 *
 * @param string $text
 * @return string
 */
function attribute_escape( $text ) {
	return esc_attr( $text );
}
endif;

// ! function tag_escape()

if ( !function_exists('like_escape') ) :
/**
 * Escapes text for SQL LIKE special characters % and _.
 *
 * @since 2.5.0
 *
 * @param string $text The text to be escaped.
 * @return string text, safe for inclusion in LIKE query.
 */
function like_escape($text) {
	return str_replace(array("%", "_"), array("\\%", "\\_"), $text);
}
endif;

// ! function wp_make_link_relative()
// ! function sanitize_option()

if ( !function_exists('wp_parse_str') ) :
/**
 * Parses a string into variables to be stored in an array.
 *
 * Uses {@link http://www.php.net/parse_str parse_str()} and stripslashes if
 * {@link http://www.php.net/magic_quotes magic_quotes_gpc} is on.
 *
 * @since 2.2.1
 * @uses apply_filters() for the 'wp_parse_str' filter.
 *
 * @param string $string The string to be parsed.
 * @param array $array Variables will be stored in this array.
 */
function wp_parse_str( $string, &$array ) {
	parse_str( $string, $array );
	if ( PHP_VERSION_ID < 50400 && get_magic_quotes_gpc() )
		$array = stripslashes_deep( $array );
	$array = apply_filters( 'wp_parse_str', $array );
}
endif;

if ( !function_exists('wp_pre_kses_less_than') ) :
/**
 * Convert lone less than signs.
 *
 * KSES already converts lone greater than signs.
 *
 * @uses wp_pre_kses_less_than_callback in the callback function.
 * @since 2.3.0
 *
 * @param string $text Text to be converted.
 * @return string Converted text.
 */
function wp_pre_kses_less_than( $text ) {
	return preg_replace_callback('%<[^>]*?((?=<)|>|$)%', 'wp_pre_kses_less_than_callback', $text);
}
endif;
if ( !function_exists('wp_pre_kses_less_than_callback') ) :
/**
 * Callback function used by preg_replace.
 *
 * @uses esc_html to format the $matches text.
 * @since 2.3.0
 *
 * @param array $matches Populated by matches to preg_replace.
 * @return string The text returned after esc_html if needed.
 */
function wp_pre_kses_less_than_callback( $matches ) {
	if ( false === strpos($matches[0], '>') )
		return esc_html($matches[0]);
	return $matches[0];
}
endif;
// ! function wp_sprintf()
// ! function wp_sprintf_l()

if ( !function_exists('wp_html_excerpt') ) :
/**
 * Safely extracts not more than the first $count characters from html string.
 *
 * UTF-8, tags and entities safe prefix extraction. Entities inside will *NOT*
 * be counted as one character. For example &amp; will be counted as 4, &lt; as
 * 3, etc.
 *
 * @since 2.5.0
 *
 * @param integer $str String to get the excerpt from.
 * @param integer $count Maximum number of characters to take.
 * @return string The excerpt.
 */
function wp_html_excerpt( $str, $count ) {
	$str = wp_strip_all_tags( $str, true );
	$str = mb_substr( $str, 0, $count );
	// remove part of an entity at the end
	$str = preg_replace( '/&[^;\s]{0,6}$/', '', $str );
	return $str;
}
endif;

// ! function links_add_base_url()
// ! function _links_add_base()
// ! function links_add_target()
// ! function _links_add_target()
// ! function normalize_whitespace()

if ( !function_exists('wp_strip_all_tags') ) :
/**
 * Properly strip all HTML tags including script and style
 *
 * @since 2.9.0
 *
 * @param string $string String containing HTML tags
 * @param bool $remove_breaks optional Whether to remove left over line breaks and white space chars
 * @return string The processed string.
 */
function wp_strip_all_tags($string, $remove_breaks = false) {
	$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
	$string = strip_tags($string);

	if ( $remove_breaks )
		$string = preg_replace('/[\r\n\t ]+/', ' ', $string);

	return trim( $string );
}
endif;

if ( !function_exists('sanitize_text_field') ) :
/**
 * Sanitize a string from user input or from the db
 *
 * check for invalid UTF-8,
 * Convert single < characters to entity,
 * strip all tags,
 * remove line breaks, tabs and extra white space,
 * strip octets.
 *
 * @since 2.9.0
 *
 * @param string $str
 * @return string
 */
function sanitize_text_field($str) {
	$filtered = wp_check_invalid_utf8( $str );

	if ( strpos($filtered, '<') !== false ) {
		$filtered = wp_pre_kses_less_than( $filtered );
		// This will strip extra whitespace for us.
		$filtered = wp_strip_all_tags( $filtered, true );
	} else {
		$filtered = trim( preg_replace('/[\r\n\t ]+/', ' ', $filtered) );
	}

	$match = array();
	$found = false;
	while ( preg_match('/%[a-f0-9]{2}/i', $filtered, $match) ) {
		$filtered = str_replace($match[0], '', $filtered);
		$found = true;
	}

	if ( $found ) {
		// Strip out the whitespace that may now exist after removing the octets.
		$filtered = trim( preg_replace('/ +/', ' ', $filtered) );
	}

	return apply_filters('sanitize_text_field', $filtered, $str);
}
endif;

if ( !function_exists('wp_basename') ) :
/**
 * i18n friendly version of basename()
 *
 * @since 3.1.0
 *
 * @param string $path A path.
 * @param string $suffix If the filename ends in suffix this will also be cut off.
 * @return string
 */
function wp_basename( $path, $suffix = '' ) {
	return urldecode( basename( str_replace( '%2F', '/', urlencode( $path ) ), $suffix ) );
}
endif;

// !function capital_P_dangit

if ( !function_exists('sanitize_mime_type') ) :
/**
 * Sanitize a mime type
 *
 * @since 3.1.3
 *
 * @param string $mime_type Mime type
 * @return string Sanitized mime type
 */
function sanitize_mime_type( $mime_type ) {
	$sani_mime_type = preg_replace( '/[^-+*.a-zA-Z0-9\/]/', '', $mime_type );
	return apply_filters( 'sanitize_mime_type', $sani_mime_type, $mime_type );
}
endif;

// !function sanitize_trackback_urls
