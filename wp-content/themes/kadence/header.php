<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package kadence
 */

namespace Kadence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js" <?php kadence()->print_microdata( 'html' ); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1">

	<!-- Explicit favicon for 2rich.capital -->
	<link rel="icon" href="/favicon.ico" sizes="any">

	<?php wp_head(); ?>

	<!-- Luxy.js for inertia smooth scroll -->
	<script src="https://min30327.github.io/luxy.js/dist/js/luxy.js"></script>
	<script>
	document.addEventListener("DOMContentLoaded", function () {
	  // Respect users who prefer reduced motion
	  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
	    return;
	  }

	  // Optional: disable Luxy on mobile devices
	  var isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
	  if (isMobile) {
	    return;
	  }

	  // Initialize Luxy
	  if (typeof luxy !== "undefined") {
	    luxy.init({
	      wrapper: '#luxy',     // main scroll wrapper
	      targets: '.luxy-el',  // optional parallax elements
	      wrapperSpeed: 0.08    // inertia strength (tweak 0.06–0.12)
	    });
	  }
	});
	</script>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
/**
 * Kadence before wrapper hook.
 */
do_action( 'kadence_before_wrapper' );
?>
<div id="luxy" class="site wp-site-blocks">
	<?php
	/**
	 * Kadence before header hook.
	 *
	 * @hooked kadence_do_skip_to_content_link - 2
	 */
	do_action( 'kadence_before_header' );

	/**
	 * Kadence header hook.
	 *
	 * @hooked Kadence/header_markup - 10
	 */
	do_action( 'kadence_header' );

	do_action( 'kadence_after_header' );
	?>

	<main id="inner-wrap" class="wrap kt-clear" role="main">
		<?php
		/**
		 * Hook for top of inner wrap.
		 */
		do_action( 'kadence_before_content' );
		?>