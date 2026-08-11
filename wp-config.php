<?php
define( 'WP_CACHE', true );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', '2richdb' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'METAAPI_TOKEN',          'eyJhbGciOiJSUzUxMiIsInR5cCI6IkpXVCJ9.eyJfaWQiOiIxNmUzYjJmMTg2ZmNmYTE0ZWY2N2YxZWQzOWI2MDRlZiIsImFjY2Vzc1J1bGVzIjpbeyJpZCI6InRyYWRpbmctYWNjb3VudC1tYW5hZ2VtZW50LWFwaSIsIm1ldGhvZHMiOlsidHJhZGluZy1hY2NvdW50LW1hbmFnZW1lbnQtYXBpOnJlc3Q6cHVibGljOio6KiJdLCJyb2xlcyI6WyJyZWFkZXIiLCJ3cml0ZXIiXSwicmVzb3VyY2VzIjpbIio6JFVTRVJfSUQkOioiXX0seyJpZCI6Im1ldGFhcGktcmVzdC1hcGkiLCJtZXRob2RzIjpbIm1ldGFhcGktYXBpOnJlc3Q6cHVibGljOio6KiJdLCJyb2xlcyI6WyJyZWFkZXIiLCJ3cml0ZXIiXSwicmVzb3VyY2VzIjpbIio6JFVTRVJfSUQkOioiXX0seyJpZCI6Im1ldGFhcGktcnBjLWFwaSIsIm1ldGhvZHMiOlsibWV0YWFwaS1hcGk6d3M6cHVibGljOio6KiJdLCJyb2xlcyI6WyJyZWFkZXIiLCJ3cml0ZXIiXSwicmVzb3VyY2VzIjpbIio6JFVTRVJfSUQkOioiXX0seyJpZCI6Im1ldGFhcGktcmVhbC10aW1lLXN0cmVhbWluZy1hcGkiLCJtZXRob2RzIjpbIm1ldGFhcGktYXBpOndzOnB1YmxpYzoqOioiXSwicm9sZXMiOlsicmVhZGVyIiwid3JpdGVyIl0sInJlc291cmNlcyI6WyIqOiRVU0VSX0lEJDoqIl19LHsiaWQiOiJtZXRhc3RhdHMtYXBpIiwibWV0aG9kcyI6WyJtZXRhc3RhdHMtYXBpOnJlc3Q6cHVibGljOio6KiJdLCJyb2xlcyI6WyJyZWFkZXIiLCJ3cml0ZXIiXSwicmVzb3VyY2VzIjpbIio6JFVTRVJfSUQkOioiXX0seyJpZCI6InJpc2stbWFuYWdlbWVudC1hcGkiLCJtZXRob2RzIjpbInJpc2stbWFuYWdlbWVudC1hcGk6cmVzdDpwdWJsaWM6KjoqIl0sInJvbGVzIjpbInJlYWRlciIsIndyaXRlciJdLCJyZXNvdXJjZXMiOlsiKjokVVNFUl9JRCQ6KiJdfSx7ImlkIjoiY29weWZhY3RvcnktYXBpIiwibWV0aG9kcyI6WyJjb3B5ZmFjdG9yeS1hcGk6cmVzdDpwdWJsaWM6KjoqIl0sInJvbGVzIjpbInJlYWRlciIsIndyaXRlciJdLCJyZXNvdXJjZXMiOlsiKjokVVNFUl9JRCQ6KiJdfSx7ImlkIjoibXQtbWFuYWdlci1hcGkiLCJtZXRob2RzIjpbIm10LW1hbmFnZXItYXBpOnJlc3Q6ZGVhbGluZzoqOioiLCJtdC1tYW5hZ2VyLWFwaTpyZXN0OnB1YmxpYzoqOioiXSwicm9sZXMiOlsicmVhZGVyIiwid3JpdGVyIl0sInJlc291cmNlcyI6WyIqOiRVU0VSX0lEJDoqIl19LHsiaWQiOiJiaWxsaW5nLWFwaSIsIm1ldGhvZHMiOlsiYmlsbGluZy1hcGk6cmVzdDpwdWJsaWM6KjoqIl0sInJvbGVzIjpbInJlYWRlciJdLCJyZXNvdXJjZXMiOlsiKjokVVNFUl9JRCQ6KiJdfV0sImlnbm9yZVJhdGVMaW1pdHMiOmZhbHNlLCJ0b2tlbklkIjoiMjAyMTAyMTMiLCJpbXBlcnNvbmF0ZWQiOmZhbHNlLCJyZWFsVXNlcklkIjoiMTZlM2IyZjE4NmZjZmExNGVmNjdmMWVkMzliNjA0ZWYiLCJpYXQiOjE3ODI3MzE5NDB9.fB_AGI4-cLbSv1mZAs8EzH5McZG0Vjca0CPFVTSwcifbM6YBeyN64Y1XnwVHFefIDUz5mvxIJW5M5n9LfmB_WjyYzad4x25xJ-wrbpMXMTlhAZBbXKliCBHnMpLtX7bpzknapoNRjUK7PyhiC5rhXw52Gl5xtK7Ovr_TKuY4FHCl4Wx8_oi91nEmMG_ZSLbLNyhC1CS1ybt8o_i_AmThbbchcWsiYYSMdkdkuPRKaZh5avAjfHW-q21TDkH-GxQrEOgurP24m01rxiMpiLqO1Lmk9t9Rco3ttTo1G2c-T5dSECbayS089SOMLRbH-JbjnQN3JtVef0MYRGw-J48FfqA9iVAPar6uep2xBMSIGy9jwYy1f2x2ARXTqGKgecZH_MMK8Gfc3PJvdoRNYQkBaDlIwpGNjadycyT35UzXQzQkQvt7L7kBYQop3GDwJw5bejxGigQkQUw9NdEcad-ZRnkrmPJUntWkgCZEXJ7hdGtIhDOYv3RdJcnQFwAHctauqFN5BjhjbxuPzQt1QOgsO4Vt78PefbnFuRQ6KMK2aO1Fg6LtFNtaib4HUWXdZuynh_21rKe7uMa-bUZhHyzmtkKxZVzY4TLRmKWvpiiody3caZ62x2CwzMiRUdHjeIIEBlk2Ztd_4nMH60tpHBGpGpXOmxiUeRqEXDCwk5IjVWU' );
define( 'AUTH_KEY',          'zR:N>uPf%mjFP29D-3L4X)|N7jzE[s:1`N3Ck=B{h|rC]6#Ihv?M?IRpCShU:FW,' );
define( 'SECURE_AUTH_KEY',   '];hXA}y9S[8{$v%8Kj*hlym%#|JiRHQOt^p8nFWSa,>/7EJ8.c4uO*yvjjI+/zC}' );
define( 'LOGGED_IN_KEY',     'EtQzPSci9adu.c%0F(,QNWm_h6cd~!9#%YI_rhY8|O&Qq,3T[>;Ka7lGw?s_2Z/`' );
define( 'NONCE_KEY',         '#t}3P0pujT.<kA/IJSpfFVOZe58WUmA>Gu,zN_.8TSZUOdMa$RrF|GnFJa2/Ai8i' );
define( 'AUTH_SALT',         '+eF-6%}Yj0~]!ACKP{[UnIs&8D{mEPyIL2$!Q=;wT5^TP]jmE[W7Ct2|gF;r6^JM' );
define( 'SECURE_AUTH_SALT',  'ov< 6@m[Zx/ ^J.APu[B)G8rk;NpZ.F#Y~<7G8jY{%rzlu|f<OBAV#BbFgGB-q:S' );
define( 'LOGGED_IN_SALT',    'oWrX=K:r]4U[Y?o</j0gefFrdrU6K%--#43$c6%[U2<joo_q&4B8rD(NaNUk&)4b' );
define( 'NONCE_SALT',        '+J_HigOX)b^kn+Vh|)`zy9yvj%8wh,Gv`ZT>M;-T|9Igi_boA9TlzXA5yVI9OHOs' );
define( 'WP_CACHE_KEY_SALT', 'y[!ea$@h1Yxbe[c]#5(F9vvXL7Cv)hRs6zB1wt/n*)tVTta(sKjV O*_YiCMjat6' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

// Twelve Data API key for 2Rich Research Technical Engine
define( 'TWORICH_RESEARCH_TWELVEDATA_KEY', '29068e4ea6a44948b3aa61ebf5ea222a' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
