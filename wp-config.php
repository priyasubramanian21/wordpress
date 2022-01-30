<?php
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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'WooCommerce' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'priya' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

define( 'FS_METHOD', 'direct' );

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
define( 'AUTH_KEY',         'G#gNuQG^,Xy;=L^s[T%J5P{7K7Bh}<xwlCLZ%gYd&Ruseb`xq=` HG$8Gw%pp_a&' );
define( 'SECURE_AUTH_KEY',  '7gEK4t>[Mwc-QO[Ku+&yQejbI~A4u*LsHGska1WW5AH.Dz^bhW`W%&Tu_YG[VC_B' );
define( 'LOGGED_IN_KEY',    '.$Qar 6Qp{lQZz9V2b@j]x{W*69^?pRBsc<qcEjV:1%WJ)1X59H9t)@)O)v/y(I+' );
define( 'NONCE_KEY',        'ii!9+{GiOA{jAnRiVxlJc ,+2eT~835,|dJZ+mcn.DLG^,Yg?X_WS hnoDbdX0.W' );
define( 'AUTH_SALT',        '&qo74aJ>9 }d0;`>qjqF_9I*7S*0GLotQs|<5<|f15z(V<3s//Jbbn47>(GNi1Ib' );
define( 'SECURE_AUTH_SALT', 'U-c<?M<#}u#}kTHK<P7w;$vkEV(b o)P_lFJ4.>{JQ5Ef-,4D$nzj#G:zvvysqg~' );
define( 'LOGGED_IN_SALT',   ')4q^980WERcVms_*T+34P?[~MqE[2)3 )A!QxubV()RP^I%:vY`xU`<DlC!|-1Qa' );
define( 'NONCE_SALT',       's+7P^nTLi%ylBl8ThbVAJ6y]3sWJrprYcJm|1{4q2qt-V]# &Icx<a{ncL<bf_q*' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
