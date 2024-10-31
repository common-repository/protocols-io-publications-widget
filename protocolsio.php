<?php
/*
Plugin Name: protocols.io Publications Widget
Plugin URI: https://protocols.io
Description: The protocols.io Wordpress Publications Widget displays completely customizable and responsive publications from protocols.io, any user or any group.
Author: protocols.io team
Version: 1.0
Author URI: https: //www.protocols.io/developers
License: GPLv2 or later
 */
class Protocolsio_Widget extends WP_Widget
{
    /**
     * Default instance.
     */
    protected $default_instance;
    /**
     * Form Options
     */
    protected $protocolsio_type;
    /**
     * Sets up a new widget instance.
     */
    public function __construct()
    {
        // Initialize Default Instance
        $this->default_instance = array(
            'title'                         => 'Publications on protocols.io',
            'protocolsio_token'             => '',
            'protocolsio_type'              => 'all',
            'protocolsio_username'          => '',
            'order_field'                   => 'publish_date',
            'protocolsio_widget_advance'    => array(),
            'title_color'                   => '#40474C',
            'authors_color'                 => '#40474C',
            'date_color'                    => '#40474C',
            'doi_color'                     => '#40474C',
            'link_color'                    => '#2c71d6',
            'page_size'                     => '10'
        );
        // Initialize Form Options
        $this->set_form_options();
        // Widget Options
        $widget_ops = array(
            'classname'     => 'protocolsio_widget',
            'description'   => 'Display publications from protocols.io');
        // Constructor
        parent::__construct('Protocolsio_Widget', 'protocols.io Publications Widget', $widget_ops);
    }
    /**
     * Set Form Options
     *
     * @returns void
     */
    public function set_form_options()
    {
        // Type
        $this->protocolsio_type = array(
            'all'       => 'All publications',
            'user'      => 'Researcher',
            'group'     => 'Group');
        // Sort by
        $this->protocolsio_order_field = array(
            'publish_date'  => 'Date',
            'activity'      => 'Activity',
            'name'          => 'Name',
            'views'         => 'Views');
    }
    /**
     * Get data from protocols.io.
     *
     * @param array $instance Current instance.
     */
    private function get_protocols($instance)
    {
        $url = 'https://www.protocols.io/api/v3';
        if ($instance['protocolsio_type'] === 'user') {
            $url .= '/researchers/' . $instance['protocolsio_username'] . '/protocols';
        } else if ($instance['protocolsio_type'] === 'group') {
            $url .= '/groups/' . $instance['protocolsio_username'] . '/protocols';
        } else {
            $url .= '/protocols/tops';
        }
        $url .= '?page_size=' . $instance['page_size'];
        $url .= '&order_field=' . $instance['order_field'];
        if ($instance['order_field'] === 'views') {
            $url .= '&order_dir=desc';
        }
        $url .= '&source=wordpress_widget&hostname=' . esc_url(home_url());
        $response = wp_remote_get($url, array('timeout' => 10, 'headers' => array('Authorization' => 'Bearer ' . $instance['protocolsio_token'])));
        return $response;
    }
    /**
     * Remove the current widget transient.
     */
    public function remove_transient($instance)
    {
        delete_transient('protocolsio-widget-transient-'.$instance['protocolsio_type'].'-'.$instance['protocolsio_username']);
    }
    /**
     * Outputs the content for the current widget instance.
     *
     * @param array $args     Display arguments including 'before_title', 'after_title',
     *                        'before_widget', and 'after_widget'.
     * @param array $instance Settings for the current Custom HTML widget instance.
     */
    public function widget($args, $instance)
    {
        // Merge the instance arguments with the defaults.
        $instance = wp_parse_args((array) $instance, $this->default_instance);
        /** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
        $title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
        // Open the output of the widget.
        echo $args['before_widget'];
        echo $args['before_title'] . '<img class="protocolsio-widget-logo" src="' . plugin_dir_url(__FILE__) . 'img/protocolsio.png" alt="" />' . $title . $args['after_title'];
        if (!empty($instance['protocolsio_token'])) {
            $data = get_transient('protocolsio-widget-transient-'.$instance['protocolsio_type'].'-'.$instance['protocolsio_username']);
            if (empty($data)) {
                $data = $this->get_protocols($instance);
                set_transient('protocolsio-widget-transient-'.$instance['protocolsio_type'].'-'.$instance['protocolsio_username'], $data, 30 * MINUTE_IN_SECONDS);
            }
            if (empty($data)) {
                return;
            }
            $body = wp_remote_retrieve_body($data);
            $data = json_decode($body);
            if (!empty($data)) {
                if (is_array($data->items)) {
                    echo '<ul class="protocolsio-widget-list">';
                    foreach ($data->items as $item) {
                        echo '<li class="protocolsio-widget-item' . (in_array('noimage', $instance['protocolsio_widget_advance']) ? ' protocolsio-widget-item-noimg' : '') . '">';
                        if (!in_array('noimage', $instance['protocolsio_widget_advance'])) {
                            echo '<a class="protocolsio-widget-title" href="' . esc_url('https://protocols.io/view/' . $item->uri) . '"><img class="protocolsio-widget-img" src="' . $item->image->source . '" width="50" height="50" alt="" /></a>';
                        }
                        echo '<a class="protocolsio-widget-title" href="' . esc_url('https://protocols.io/view/' . $item->uri) . '?source=wordpress_widget&hostname=' . esc_url(home_url()) . '" style="color: ' . $instance['title_color'] . ';" target="_blank">' . $item->title . '</a>';
                        if (!in_array('noauthors', $instance['protocolsio_widget_advance'])) {
                            if (is_array($item->authors)) {
                                $authors = '';
                                $authors_length = count($item->authors);
                                foreach ($item->authors as $key => $author) {
                                    if (($key < 2) || ($authors_length > 3 && $key === 3) || ($authors_length < 4)) {
                                        if ($author->username) {
                                            $authors .= '<a href="https://protocols.io/researchers/' . $author->username . '" style="color: ' . $instance['link_color'] . ';" target="_blank">' . $author->name . '</a>';
                                        } else {
                                            $authors .= $author->name;
                                        }
                                        if ($key < $authors_length - 1 && key !== 3) {
                                            $authors .= ', ';
                                        }
                                    } else {
                                        if ($authors_length > 3) {
                                            if ($key === 2) {
                                                $authors .= '..., ';
                                            }
                                        }
                                    }
                                }
                                if (!empty($authors)) {
                                    echo '<div class="protocolsio-widget-authors" style="color: ' . $instance['authors_color'] . ';">' . $authors . '</div>';
                                }
                            }
                        }
                        if (!in_array('nodate', $instance['protocolsio_widget_advance'])) {
                            $date = '<div class="protocolsio-widget-date" style="color: ' . $instance['date_color'] . ';">' . gmdate("M d, Y", $item->published_on) . '</div>';
                            echo $date;
                        }
                        if (!in_array('nodoi', $instance['protocolsio_widget_advance'])) {
                            // fix DOI status
                            if ($item->doi_status == 0) {
                                echo '<div class="protocolsio-widget-doi"><a href="https://' . $item->doi . '" style="color: ' . $instance['doi_color'] . ';" target="_blank">https://' . $item->doi . '</a><div>';
                            }
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                    if (!in_array('noviewall', $instance['protocolsio_widget_advance'])) {
                        $url = 'https://www.protocols.io';
                        if ($instance['protocolsio_type'] === 'user') {
                            $url .= '/researchers/' . $instance['protocolsio_username'];
                        }
                        if ($instance['protocolsio_type'] === 'group') {
                            $url .= '/groups/' . $instance['protocolsio_username'];
                        }
                        $url .= '/publications';
                        $url .= '?source=wordpress_widget&hostname=' . esc_url(home_url());
                        echo '<div class="protocolsio-widget-viewall"><a href="' . $url . '" target="_blank">View all on protocols.io</a></div>';
                    }
                }
            }
        } else {
            echo 'No publications found';
        }
        echo $args['after_widget'];
    }
    /**
     * Outputs the options form on admin
     *
     * @param array $instance The widget options
     *
     * @link https://developer.wordpress.org/reference/classes/wp_widget/form/
     */
    public function form($instance)
    {
        // Merge the instance arguments with the defaults.
        $instance = wp_parse_args((array) $instance, $this->default_instance);
        ?>

        <p>
			<label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Title:</label>
			<input type="text" class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" value="<?php echo esc_attr($instance['title']); ?>" />
		</p>
        <p>
			<label for="<?php echo esc_attr($this->get_field_id('protocolsio_token')); ?>">Access token:</label>
			<input type="text" class="widefat" id="<?php echo esc_attr($this->get_field_id('protocolsio_token')); ?>" name="<?php echo esc_attr($this->get_field_name('protocolsio_token')); ?>" value="<?php echo esc_attr($instance['protocolsio_token']); ?>" />
            <br />
            <small>You can get access token on <a href="https://www.protocols.io/developers" target="_blank">https://www.protocols.io/developers</a></small>
        </p>
        <p>
			<label for="<?php echo esc_attr($this->get_field_id('protocolsio_type')); ?>">Type:</label>
            <select class="widefat protocolsio-widget-type" id="<?php echo esc_attr($this->get_field_id('protocolsio_type')); ?>" name="<?php echo esc_attr($this->get_field_name('protocolsio_type')); ?>" data-toggle-id="<?php echo $this->get_field_id('protocolsio_username'); ?>">
              <?php foreach ($this->protocolsio_type as $key => $val): ?>
			    <option value="<?php echo esc_attr($key); ?>" <?php selected($instance['protocolsio_type'], $key);?>><?php echo esc_html($val); ?></option>
			  <?php endforeach; ?>
            </select>
		</p>
        <p <?php echo ' style="display: ' . ($instance['protocolsio_type'] !== 'all' ? 'block' : 'none') . ';"'; ?>>
			<label for="<?php echo esc_attr($this->get_field_id('protocolsio_username')); ?>">Username:</label>
			<input type="text" class="widefat" id="<?php echo esc_attr($this->get_field_id('protocolsio_username')); ?>" name="<?php echo esc_attr($this->get_field_name('protocolsio_username')); ?>" value="<?php echo esc_attr($instance['protocolsio_username']); ?>" />
        </p>
        <p>
			<label for="<?php echo esc_attr($this->get_field_id('order_field')); ?>">Sort by:</label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('order_field')); ?>" name="<?php echo esc_attr($this->get_field_name('order_field')); ?>" data-toggle-id="<?php echo $this->get_field_id('order_field'); ?>">
              <?php foreach ($this->protocolsio_order_field as $key => $val): ?>
			    <option value="<?php echo esc_attr($key); ?>" <?php selected($instance['order_field'], $key);?>><?php echo esc_html($val); ?></option>
			  <?php endforeach; ?>
            </select>
        </p>
        <a class="protocolio-widget-advanced-toggle">
            Advanced options<span><span class="dashicons dashicons-arrow-down"></span></span>
        </a>
        <div class="protocolio-widget-advanced-block" style="display: none;">
            <p>
                <label for="<?php echo $this->get_field_id('protocolsio_widget_advance'); ?>">
                    <strong>Layout Options</strong>
                </label>
                <br />
                <input type="checkbox" <?php checked(in_array('noimage', $instance['protocolsio_widget_advance']));?> id="<?php echo $this->get_field_id('protocolsio_widget_advance_image'); ?>" name="<?php echo $this->get_field_name('protocolsio_widget_advance'); ?>[]" value="noimage" />
                <label for="<?php echo $this->get_field_id('protocolsio_widget_advance_image'); ?>">
                    No image
                </label>
                <br />
                <input type="checkbox" <?php checked(in_array('noauthors', $instance['protocolsio_widget_advance']));?> id="<?php echo $this->get_field_id('protocolsio_widget_advance_noauthors'); ?>" name="<?php echo $this->get_field_name('protocolsio_widget_advance'); ?>[]" value="noauthors" />
                <label for="<?php echo $this->get_field_id('protocolsio_widget_advance_authors'); ?>">
                    No authors
                </label>
                <br />
                <input type="checkbox" <?php checked(in_array('nodate', $instance['protocolsio_widget_advance']));?> id="<?php echo $this->get_field_id('protocolsio_widget_advance_nodate'); ?>" name="<?php echo $this->get_field_name('protocolsio_widget_advance'); ?>[]" value="nodate" />
                <label for="<?php echo $this->get_field_id('protocolsio_widget_advance_date'); ?>">
                    No date
                </label>
                <br />
                <input type="checkbox" <?php checked(in_array('nodoi', $instance['protocolsio_widget_advance']));?> id="<?php echo $this->get_field_id('protocolsio_widget_advance_nodoi'); ?>" name="<?php echo $this->get_field_name('protocolsio_widget_advance'); ?>[]" value="nodoi" />
                <label for="<?php echo $this->get_field_id('protocolsio_widget_advance_doi'); ?>">
                    No DOI
                </label>
                <br />
                <input type="checkbox" <?php checked(in_array('noviewall', $instance['protocolsio_widget_advance']));?> id="<?php echo $this->get_field_id('protocolsio_widget_advance_noviewall'); ?>" name="<?php echo $this->get_field_name('protocolsio_widget_advance'); ?>[]" value="noviewall" />
                <label for="<?php echo $this->get_field_id('protocolsio_widget_advance_viewall'); ?>">
                    No "View all" link
                </label>
                <br />
            </p>

            <p>
                <label for="<?php echo esc_attr($this->get_field_id('page_size')); ?>">Publications count:</label>
                <input type="number" class="widefat" id="<?php echo esc_attr($this->get_field_id('page_size')); ?>" name="<?php echo esc_attr($this->get_field_name('page_size')); ?>" value="<?php echo esc_attr($instance['page_size']); ?>" min="0" max="10" />
                <br />
                <small>Maximum 10 publications</small>
            </p>

            <div class="protocolsio-widget-picker-block">
                <label for="<?php echo esc_attr($this->get_field_id('title_color')); ?>">Title color:</label>
                <div class="protocolsio-widget-color-picker"></div>
                <input type="hidden" class="protocolsio-color-input" id="<?php echo esc_attr($this->get_field_id('title_color')); ?>" name="<?php echo esc_attr($this->get_field_name('title_color')); ?>" value="<?php echo esc_attr($instance['title_color']); ?>" />
            </div>

            <div class="protocolsio-widget-picker-block">
                <label for="<?php echo esc_attr($this->get_field_id('authors_color')); ?>">Authors color:</label>
                <div class="protocolsio-widget-color-picker"></div>
                <input type="hidden" class="protocolsio-color-input" id="<?php echo esc_attr($this->get_field_id('authors_color')); ?>" name="<?php echo esc_attr($this->get_field_name('authors_color')); ?>" value="<?php echo esc_attr($instance['authors_color']); ?>" />
            </div>

            <div class="protocolsio-widget-picker-block">
                <label for="<?php echo esc_attr($this->get_field_id('date_color')); ?>">Date color:</label>
                <div class="protocolsio-widget-color-picker"></div>
                <input type="hidden" class="protocolsio-color-input" id="<?php echo esc_attr($this->get_field_id('date_color')); ?>" name="<?php echo esc_attr($this->get_field_name('date_color')); ?>" value="<?php echo esc_attr($instance['date_color']); ?>" />
            </div>

            <div class="protocolsio-widget-picker-block">
                <label for="<?php echo esc_attr($this->get_field_id('doi_color')); ?>">DOI color:</label>
                <div class="protocolsio-widget-color-picker"></div>
                <input type="hidden" class="protocolsio-color-input" id="<?php echo esc_attr($this->get_field_id('doi_color')); ?>" name="<?php echo esc_attr($this->get_field_name('doi_color')); ?>" value="<?php echo esc_attr($instance['doi_color']); ?>" />
            </div>

            <div class="protocolsio-widget-picker-block">
                <label for="<?php echo esc_attr($this->get_field_id('link_color')); ?>">Link color:</label>
                <div class="protocolsio-widget-color-picker"></div>
                <input type="hidden" class="protocolsio-color-input" id="<?php echo esc_attr($this->get_field_id('link_color')); ?>" name="<?php echo esc_attr($this->get_field_name('link_color')); ?>" value="<?php echo esc_attr($instance['link_color']); ?>" />
            </div>

            <p>
                <small>
                    <strong>Important:</strong> Important: We update data every 30 minutes, but you can also do it manually. <a href="#" class="protocolsio-widget-reset" data-type="<?php echo $instance['protocolsio_type']; ?>" data-username="<?php echo $instance['protocolsio_username']; ?>">Update</a>.
                </small>
            </p>
        </div>
		<?php
    }
    /**
     * Handles updating settings for the current widget instance.
     *
     * @param array $new_instance New settings for this instance as input by the user via
     *                            WP_Widget::form().
     * @param array $old_instance Old settings for this instance.
     * @return array Settings to save or bool false to cancel saving.
     */
    public function update($new_instance, $old_instance)
    {
        // Instance
        $instance = $old_instance;
        // Sanitization
        $instance['title'] = sanitize_text_field($new_instance['title']);
        if (
            $instance['protocolsio_token'] != $new_instance['protocolsio_token'] ||
            $instance['protocolsio_type'] != $new_instance['protocolsio_type'] ||
            $instance['protocolsio_username'] != $new_instance['protocolsio_username'] ||
            $instance['order_field'] != $new_instance['order_field'] ||
            $instance['page_size'] != $new_instance['page_size']
        ) {
            $this->remove_transient($instance);
        }
        $instance['protocolsio_token'] = sanitize_text_field($new_instance['protocolsio_token']);
        $instance['protocolsio_type'] = $new_instance['protocolsio_type'];
        $instance['protocolsio_username'] = (!empty($new_instance['protocolsio_username'])) ? sanitize_text_field($new_instance['protocolsio_username']) : '';
        $instance['order_field'] = $new_instance['order_field'];
        $page_size = $new_instance['page_size'];
        $page_size = (int) $page_size;
        if ($page_size < 1) {
            $page_size = 1;
        }

        if ($page_size > 10) {
            $page_size = 10;
        }

        $instance['page_size'] = $page_size;
        $instance['protocolsio_widget_advance'] = array();
        $adv_settings = array(
            'noimage',
            'noauthors',
            'nodate',
            'nodoi',
            'noviewall'
        );
        if (isset($new_instance['protocolsio_widget_advance'])) {
            foreach ($new_instance['protocolsio_widget_advance'] as $adv) {
                if (in_array($adv, $adv_settings)) {
                    $instance['protocolsio_widget_advance'][] = $adv;
                }
            }
        }
        $instance['title_color'] = $new_instance['title_color'];
        $instance['authors_color'] = $new_instance['authors_color'];
        $instance['date_color'] = $new_instance['date_color'];
        $instance['doi_color'] = $new_instance['doi_color'];
        $instance['link_color'] = $new_instance['link_color'];
        return $instance;
    }
}

function admin_widget_scripts()
{
    wp_enqueue_style('protocolsio_pickr_css', plugin_dir_url(__FILE__) . '/css/pickr.min.css', array(), '1.0.0', 'all');
    wp_enqueue_style('protocolsio_admin_css', plugin_dir_url(__FILE__) . '/css/admin.css', array(), '1.0.0', 'all');
    wp_enqueue_script('protocolsio_pickr_js', plugin_dir_url(__FILE__) . '/js/pickr.min.js', array('jquery'), '1.0.0', true);
    wp_enqueue_script('protocolsio_script', plugin_dir_url(__FILE__) . '/js/script.js', array('jquery'), '1.0.0', true);
    wp_localize_script('protocolsio_script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}

function widget_scripts()
{
    wp_enqueue_style('protocolsio_styles', plugin_dir_url(__FILE__) . '/css/styles.css', array(), '1.0.0', 'all');
}

function protocolsio_reset_transient()
{
    $deleted = delete_transient('protocolsio-widget-transient-'.$_POST['type'].'-'.$_POST['username']);
    if ($deleted) {
        wp_send_json_success('Transient deleted: protocolsio-widget-transient-'.$_POST['type'].'-'.$_POST['username']);
    } else {
        wp_send_json_error('Transient deletion failed: protocolsio-widget-transient-'.$_POST['type'].'-'.$_POST['username']);
    }
}

add_action('wp_ajax_protocolsio_reset_transient', 'protocolsio_reset_transient');
add_action('wp_ajax_nopriv_protocolsio_reset_transient', 'protocolsio_reset_transient');
add_action('admin_enqueue_scripts', 'admin_widget_scripts');
add_action('wp_enqueue_scripts', 'widget_scripts');

// register protocolsio_Widget
add_action('widgets_init', function () {
    register_widget('Protocolsio_Widget');
});
