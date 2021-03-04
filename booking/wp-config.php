<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '(>4hxd/#vWOhFK_ji,7N+u-M6jw&+%=F)}2rog[!?B%N{nLfiS8,r{JwjxdF+$3d' );
define( 'SECURE_AUTH_KEY',  '=o1ex_+4pll*52#:+aCs3~EZHf/#2mJJ=9w15=iyb{U 7y8{|$,Y2)7R^u)3~PEn' );
define( 'LOGGED_IN_KEY',    'z+pb&m?BljRrSH!`B0)tr%rkPN>);>.GM_L/8dUZJP,xA0zTGb~{m2G#&&7I$NGn' );
define( 'NONCE_KEY',        '}:pQlgct3x>NBce$$[t9I kb@G~CbCJ^en0CK5__>[A{nY_/US.+?#pi,]ccDnpl' );
define( 'AUTH_SALT',        '+VB@G|Xd-!8w4JoWr.|xNk%E ,1Mxr,]1C@hocF^/dWw:Ed:y&x<kh3?W_RNLLV4' );
define( 'SECURE_AUTH_SALT', 'L@ST#?Xu-iCFSN`kNBmLHsowwYaugrIROu(T67{nzSKOe[fs>/}wHR0>tKvApRfW' );
define( 'LOGGED_IN_SALT',   'HsMcKB,.,IIfFb;{VvU[-MPUl2V$ ~O(Zf#D7dD]{8CY%u6BX-m.?HcW6o&W;tUl' );
define( 'NONCE_SALT',       'L*kv/n0Gc<~$1?{8YzD!=]DW?fa>916(+HUHM;-<.nw#;6HPyoQ[T32vnK*p0V=T' );

/**#@-*/

/**
 * WordPress Database Table prefix.
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

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
