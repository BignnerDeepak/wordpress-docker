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
define( 'DB_NAME', 'demoenab_wp313' );

/** Database username */
define( 'DB_USER', 'demoenab_wp313' );

/** Database password */
define( 'DB_PASSWORD', 'p]d)[22[yQR)2MSU' );

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
define( 'AUTH_KEY',         'vxldzr1ehwsjnwp1gu0xglktm98ftcvzh8wjtzuozuntcuqe2sktz9vbzep7pdzz' );
define( 'SECURE_AUTH_KEY',  'uxoc8grk73aishznbf7ykmk9ibgiq4lavnzfkrrplckitx4jjlpf79gd25aklr1j' );
define( 'LOGGED_IN_KEY',    'gmnzzrwf1hsclfv233dhlwmjlac0abe8yuadbbb224hzborbe2jhbwvmzazpuvri' );
define( 'NONCE_KEY',        'rarvbeftlevtjl9qi7dmvyzx7xtvpvgftxgrxmaqbodej0n9sjpphcoyb1z6vdgv' );
define( 'AUTH_SALT',        'bw67vtnm7dvn0coampjkesx2r4jch2qvscmdtnhkamgomfyogh2r25dfsefoynyl' );
define( 'SECURE_AUTH_SALT', 'df2sldjiz7poijpxm09wkiuktvxedw23ghfkhmslhbivdpm48smblzbniicmn6iw' );
define( 'LOGGED_IN_SALT',   'wk7fozaao86dhmjk9senzs5dha0bzgzcudvvnlmdebuajnmorilnmf2zrwrszjpp' );
define( 'NONCE_SALT',       'ovowcmikcscrprr64oxtbxgm85r64sjoabmuuxiygeudthmzxvzht9waktpccetv' );

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
$table_prefix = 'wpg2_';

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
