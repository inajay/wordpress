<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'viraldb' );

/** Database username */
define( 'DB_USER', 'ajayk' );

/** Database password */
define( 'DB_PASSWORD', 'Tiger1008@$' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'Co1]GI6-)rL,0Eg2R*p`4GD<v@c!6O&E=UG<:bP#M?:q_@bWa6yNQ._(=S9A(y5,' );
define( 'SECURE_AUTH_KEY',  '_;F<9YMdSJ3LCr4gv%d3/o<j(c/TwpV5$mSX`gzoHWDut$*(;&+Ejc[v?Q30`TW1' );
define( 'LOGGED_IN_KEY',    'J BdYgd5eyXj{N!E>d$#f>qGG7:>{>$ZuMLcAa<!A7} o@a?k-fg`jiqc`fi9yEr' );
define( 'NONCE_KEY',        'A]{bRsGD$M*%[Og7@jP{^Fe>e .+AUJ/()2^ga5J-ufc8M<dMQwsu/glAe>6?>,L' );
define( 'AUTH_SALT',        '7E^Fx7>e20%pro$C~;i?t,QmeY<fhI@8?*NO6AnbOY8K;;f E38$!41:qTOC8wsH' );
define( 'SECURE_AUTH_SALT', '6o-p/uu!6$<q1p-hwx`CBnAIE#Wx%^Ku4<..SnYPzXD#*fc-btPeMbZ1%R<0);QP' );
define( 'LOGGED_IN_SALT',   'Rk]h!mAY-pAI@NtdE]W/xK:! P!Rx-Rt!,:g39XMFG~UzP(]UN;/D&hef?VbbTUA' );
define( 'NONCE_SALT',       ':8W.~HlML+}*z:e-W>4rh6o6=^F5A9u!N/}m*-v7d[s|U_&NFZE{q+l,xz d7]t/' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
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
