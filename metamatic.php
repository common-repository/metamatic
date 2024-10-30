<?php
/*
  Plugin Name: Metamatic
  Plugin URI: http://metamatic.net/soft/metamatic-wp-en
  Description: Simple friend list and friend feed functionality.
  Version: 1.0.2
  Author: metamatic.net
  Author URI: http://metamatic.net/
  License: GPL2
 */

/*  Copyright 2009-2010    Metamatic team  (email : wp-plugin [at] metamatic [dot] net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if (!function_exists('register_activation_hook')) {
    return;
}
define('METAMATIC_DIR', dirname(__FILE__));

require_once(METAMATIC_DIR . '/common.php');
require_once(METAMATIC_DIR . '/client.php');
require_once(METAMATIC_DIR . '/metamatic-wp.php');
require_once(METAMATIC_DIR . '/tasks.php');
require_once(METAMATIC_DIR . '/ui.php');

// Add an activation hook for this plugin
register_activation_hook(__FILE__, 'metamatic_activate');
register_deactivation_hook(__FILE__, 'metamatic_deactivate');

load_plugin_textdomain('metamatic', false, dirname(plugin_basename(__FILE__)) . '/translations');

add_action('wp_head', 'metamatic_wp_head');
add_action('metamatic_feed_task', 'metamatic_feed_task');
add_action('metamatic_hub_update_check', 'metamatic_hub_update_check');
add_filter('plugin_action_links', 'metamatic_filter_plugin_actions', 10, 2);
if (is_admin ()) { // admin actions
    add_action('admin_menu', 'metamatic_menus');
    add_action('admin_init', 'metamatic_register_settings');

    // If hub usage is enabled, we need these hooks to send updates to server:
    add_action('update_option_metamatic_key', 'metamatic_hub_config_updated');
    add_action('update_option_metamatic_use_hub', 'metamatic_hub_config_updated');

} else {
    // non-admin enqueues, actions, and filters
}
add_action('widgets_init', create_function('', 'return register_widget("Metamatic_Widget_Favorites");'));

metamatic_enable_page_filters();
add_filter('get_the_excerpt', 'metamatic_disable_page_filters', -1000001);
add_filter('get_the_excerpt', 'metamatic_enable_page_filters',   1000001);

add_action( 'init', create_function('', 'metamatic_add_admin_js();'));
function metamatic_add_admin_js() {
    if(metamatic_is_admin()) {
        metamatic_add_admin_javascript();
    }
}

function metamatic_hub_config_updated($new) {
    static $updateQueued = false;

    // Execute action after all options are saved
    if (!$updateQueued) {
        add_action('shutdown', 'metamatic_after_hub_config_updated');
    }
    $updateQueued = true;
}

function metamatic_enable_page_filters($excerpt = '') {
    metamatic_toggle_page_filters(true);
    return $excerpt;
}
function metamatic_disable_page_filters($excerpt = '') {
    metamatic_toggle_page_filters(false);
    return $excerpt;
}
function metamatic_toggle_page_filters($enable) {
    $filter = 'metamatic_page_filter';
    if($enable) {
        add_filter('the_content', $filter, 12);
    } else {
        remove_filter('the_content', $filter);
    }
}

function metamatic_page_filter($content = '') {
    $prefix = '[metamatic_';
    $suffix = ']';
    $prefixLen = strlen($prefix);
    $suffixLen = strlen($suffix);
    $contentLen = strlen($content);

    $pos = 0;
    while($pos < $contentLen) {
        $start = strpos($content, $prefix, $pos);
        if($start > 0) {
            $tagStart = $start;
            $start += $prefixLen;
            $end = strpos($content, $suffix, $start);
            if($end > $start) {
                $tagEnd = $end + $suffixLen;
                $pos = $tagEnd;

                $what = substr($content, $start, $end - $start);
                $file = METAMATIC_DIR . "/pages/$what.php";
                $output = null;
                if(is_file($file)) {
                    ob_start();
                    include($file);
                    $output = ob_get_contents();
                    ob_end_clean();
                } else {
                    $output = "?$what";
                }

                $content = substr($content, 0, $tagStart) . $output . substr($content, $tagEnd);
                $pos += strlen($output) - ($tagEnd - $tagStart);
            } else {
                break;
            }
        } else {
            break;
        }
    }
    return $content;
}

function metamatic_activate() {
    require_once(METAMATIC_DIR . '/install-update.php');
    metamatic_install_or_update();
    wp_schedule_event(time() + 1800, 'hourly', 'metamatic_feed_task');
}

function metamatic_deactivate() {
    wp_clear_scheduled_hook('metamatic_hub_update');
    wp_clear_scheduled_hook('metamatic_feed_task');
}

function metamatic_reschedule_hub_operations() {
    wp_clear_scheduled_hook('metamatic_hub_update');
    wp_schedule_event(time() + 3600, 'daily', 'metamatic_hub_update_check');
}
function metamatic_hub_update_check() {
    if (metamatic_hub_enabled ()) {
        metamatic_hub_update();
    }
}

function metamatic_wp_head() {
    // Advertise this Blog's support of ISite stuff
?>
    <meta name="metamatic" content="<?php echo plugins_url('interface.php', __FILE__) ?>"/>
    <link href="<?php echo plugins_url('foaf.php', __FILE__) ?>" title="FOAF" type="application/rdf+xml" rel="meta"/>
    <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('style/metamatic-style.css', __FILE__) ?>"/>
<?php
}

function metamatic_register_settings() {
    register_setting('metamatic-settings', 'metamatic_poweredby_enabled');
    register_setting('metamatic-settings', 'metamatic_about');
    register_setting('metamatic-settings', 'metamatic_interests', 'metamatic_cleanup_words_csv');

    register_setting('metamatic-settings', 'metamatic_key');
    register_setting('metamatic-settings', 'metamatic_use_hub');
    register_setting('metamatic-settings', 'metamatic_hub_url');
}

function metamatic_options_page() {
    $action = @$_POST['action'];
    if($action == 'loadFeeds') {
        metamatic_feed_task();
    } else if($action == 'updateHub') {
        metamatic_hub_update();
    }
?>
<div class="wrap">
    <?php screen_icon(); ?>
    <h2><?php metamatic_e('Metamatic settings') ?></h2>
    <form method="post" action="options.php">
        <?php settings_fields('metamatic-settings'); ?>

        <h3><?php metamatic_e('Display settings') ?></h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php metamatic_e('"Powered by Metamatic"') ?>
                </th>
                <td>
                    <input type="checkbox" id="metamatic_poweredby_enabled" name="metamatic_poweredby_enabled" <?php echo metamatic_is_poweredby_enabled() ? 'checked' : '' ?> />
                    <label for="metamatic_poweredby_enabled">
                        <?php metamatic_e('Show appreciation to plugin authors by showing "Powered by Metamatic" link') ?>
                    </label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php metamatic_e('Quick Links') ?>
                </th>
                <td><?php
                    printf(metamatic_t('Metamatic provides <a href="%s">Favorites</a> and <a href="%s">Friends feed</a> pages.'),
                        metamatic_get_page_url('favorites'),
                        metamatic_get_page_url('friendfeed')) ?>
                </td>
            </tr>
        </table>
        
        <h3><?php metamatic_e('Personal information') ?></h3>
        <p><?php metamatic_e('Place here information that will be accessible to your site visitors.') ?></p>
        <table class="form-table">
            
            <tr valign="top">
                <th scope="row"><?php metamatic_e('About yourself') ?>
                </th>
                <td><textarea rows="5" cols="60" name="metamatic_about"><?php form_option('metamatic_about') ?></textarea>
                    <br>
                    <span class="description">
                        <?php metamatic_e('Write something about yourself. This will describe your blog to Metamatic visitors.') ?>
                    </span>
                </td>
            </tr>
            

            <tr valign="top">
                <th scope="row"><?php metamatic_e('Interests') ?>
                </th>
                <td><textarea style="height: 60px;" rows="2" cols="60" name="metamatic_interests"><?php
                    $interests = metamatic_get_interests();
                    echo metamatic_esc($interests);
                ?></textarea>
                    <br>
                    <span class="description">
                        <?php metamatic_e('List your interests, comma-separated, for example: <strong>computers, music, sports</strong>. Interests are displayed at Metamatic site.') ?>
                    </span>
                </td>
            </tr>
        </table>

        <h3><?php metamatic_e('Communication settings') ?></h3>
        <p><?php metamatic_e('These settings are used for interaction between your browser plugin and this blog.') ?></p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php metamatic_e('Public accessibility') ?>
                </th>
                <td><input type="checkbox" id="metamatic_use_hub" name="metamatic_use_hub" <?php echo get_option('metamatic_use_hub', true) ? 'checked' : '' ?> />
                    <label for="metamatic_use_hub">
                        <?php metamatic_e('List my blog in Metamatic network and receive friend notifications.') ?>
                    </label>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php metamatic_e('Access Key') ?>
                </th>
                <td><input maxlength="80" style="width:300px;" type="text" name="metamatic_key" value="<?php form_option('metamatic_key'); ?>" />
                    <span class="description">
                        <?php metamatic_e('This value must match key value configured in browser plugin.') ?>
                    </span>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row"><?php metamatic_e('Server URL') ?>
                </th>
                <td><input style="width:300px;" type="text" name="metamatic_hub_url" value="<?php form_option('metamatic_hub_url'); ?>" />
                    <span class="description">
                        <?php metamatic_e('Leave thes field empty to use main Metamatic server.') ?>
                    </span>
                </td>
            </tr>
        </table>
        <br>
        
        <p class="submit">
            <input type="submit" class="button-primary" value="<?php metamatic_e('Save Changes') ?>" />
        </p>
    </form>

    <table>
        <tr>
            <td>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="loadFeeds"/>
                    <button type="submit"><img alt="*" src="<?php echo metamatic_get_style_dir() ?>/refresh.png"> <?php metamatic_e('Refresh friends feed') ?></button>
                </form>
            </td>
            <td>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="updateHub"/>
                    <button type="submit" xonclick="updateHub()"><img alt="*" src="<?php echo metamatic_get_style_dir() ?>/refresh.png"> <?php metamatic_e('Synchronize with Metamatic Hub') ?></button>
                    <script type="text/javascript">
                        function updateHub() {
                            var params = {
                                method: 'synchronize',
                                args: JSON.stringify({
                                    key: '<?php echo metamatic_get_key() ?>',
                                    source: '<?php echo metamatic_get_isite_url() ?>'
                                })
                            };
                            jQuery.ajax({
                                url: '<?php echo metamatic_get_hub_url() ?>',
                                type: 'post',
                                dataType: 'json',
                                data: params,
                                //beforeSend: function(xhr){ xhr.withCredentials = true; },
                                success: function() {
                                    alert("OK");
                                }
                            });
                        }
                    </script>

                </form>
            </td>
        </tr>
    </table>
</div><?php
}

function metamatic_filter_plugin_actions($links, $file) {
    //Static so we don't call plugin_basename on every plugin row.
    static $this_plugin;
    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }
    if ($file == $this_plugin) {
        $settings_link = '<a href="options-general.php?page=metamatic/metamatic.php">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link); // before other links
    }
    return $links;
}

function metamatic_menus() {
    if (function_exists('add_options_page')) {
        add_options_page(
            metamatic_t('Metamatic Settings'),
            metamatic_t('Metamatic'),
            'manage_options', __FILE__,
            'metamatic_options_page');
    }
}

function metamatic_widget_favorites($args) {
    extract($args);
    echo $before_widget;
    echo $before_title;
    echo metamatic_t('Favorite sites');
    echo $after_title;
    metamatic_favorites_body();
    echo $after_widget;
}

class Metamatic_Widget_Favorites extends WP_Widget {
    function __construct() {
        //parent::WP_Widget(false, $name = 'Metamatic_Widget');
        parent::__construct('metamatic-favorites', metamatic_t('Metamatic: Favorites'), array (
            'description' => metamatic_t('Displays list of favorite sites.')
            ));
    }

    function widget($args, $instance) {
        extract( $args );
        $title = apply_filters('widget_title', @$instance['title']);
        echo $before_widget;
        echo $before_title;
        echo $title? $title: metamatic_t('Favorite sites');
        echo $after_title;
        metamatic_favorites_body();
        echo $after_widget;
    }

    function update($new_instance, $old_instance) {
	$instance = $old_instance;
	$instance['title'] = strip_tags(@$new_instance['title']);
        return $instance;
    }

    function form($instance) {
        $title = esc_attr(@$instance['title']);
        ?><p><label for="<?php echo $this->get_field_id('title'); ?>"><?php metamatic_e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
        <?php
    }
}