<?php
/*
Plugin Name: Easy Changelog Manager
Plugin URI: https://yourwebsite.com
Description: Comprehensive changelog management with version tracking and detailed entries
Version: 1.1.0
Author: Ashim
Text Domain: easy-change-log-manager
*/

class EasyChangeLogManager {
    public function __construct() {
        add_action('init', [$this, 'register_changelog_post_type']);
        add_action('add_meta_boxes', [$this, 'add_changelog_version_meta_boxes']);
        add_action('save_post', [$this, 'save_changelog_version_entries'], 10, 2);
        add_shortcode('display_changelog', [$this, 'render_full_changelog']);
        
        // Admin menu and settings
        add_action('admin_menu', [$this, 'add_changelog_settings_page']);
        add_action('admin_init', [$this, 'register_changelog_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_changelog_scripts']);
        add_action('wp_head', [$this, 'add_changelog_custom_styles']);
    }

    public function register_changelog_post_type() {
        register_post_type('easy_changelog', [
            'labels' => [
                'name' => __('Easy Changelogs', 'easy-change-log-manager'),
                'singular_name' => __('Easy Changelog', 'easy-change-log-manager'),
                'add_new' => __('Add New Changelog', 'easy-change-log-manager'),
                'add_new_item' => __('Add New Easy Changelog', 'easy-change-log-manager'),
                'edit_item' => __('Edit Easy Changelog', 'easy-change-log-manager')
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'menu_icon' => 'dashicons-list-view'
        ]);
    }

    public function add_changelog_version_meta_boxes() {
        add_meta_box(
            'changelog_versions',
            'Changelog Versions',
            [$this, 'changelog_versions_callback'],
            'easy_changelog',
            'normal',
            'high'
        );
    }

    public function enqueue_changelog_scripts($hook) {
        $screen = get_current_screen();
        if ($screen->post_type === 'easy_changelog' && 
            (in_array($hook, ['post.php', 'post-new.php']))) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('easy-changelog-script', plugin_dir_url(__FILE__) . 'assets/js/changelog-script.js', ['jquery'], '1.1.0', true);
            wp_enqueue_style('easy-changelog-style', plugin_dir_url(__FILE__) . 'assets/css/changelog-style.css');
        }
    }

    public function changelog_versions_callback($post) {
        wp_nonce_field('changelog_versions_nonce', 'changelog_versions_nonce');
        $versions = get_post_meta($post->ID, '_changelog_versions', true);
        ?>
        <div id="changelog-versions-container">
            <div id="versions-list">
                <?php 
                if (!empty($versions)) {
                    foreach ($versions as $index => $version) { 
                        $this->render_version_entry_html($index, $version);
                    }
                }
                ?>
            </div>
            <button type="button" id="add-changelog-version" class="button button-primary">
                <?php echo esc_html__('Add New Version', 'easy-change-log-manager'); ?>
            </button>
        </div>

        <script id="version-entry-template" type="text/html">
            <?php $this->render_version_entry_html('{{INDEX}}'); ?>
        </script>
        <?php
    }

    private function render_version_entry_html($index, $version = null) {
        $version_number = $version ? esc_attr($version['version_number']) : '';
        $release_date = $version ? esc_attr($version['release_date']) : date('Y-m-d');
        $release_note = $version ? esc_textarea($version['release_note']) : '';
        $release_url = $version ? esc_url($version['release_url']) : '';
        ?>
        <div class="changelog-version-entry" data-index="<?php echo $index; ?>">
            <div class="version-header">
                <input 
                    type="text" 
                    name="changelog_version_number[<?php echo $index; ?>]" 
                    placeholder="Version Number (e.g., 1.0.1)" 
                    value="<?php echo $version_number; ?>"
                    class="version-number-input"
                >
                <input 
                    type="date" 
                    name="changelog_release_date[<?php echo $index; ?>]" 
                    value="<?php echo $release_date; ?>"
                    class="version-date-input"
                    required
                >
                <button type="button" class="remove-version-entry button">Remove Version</button>
            </div>
                
            <div class="version-details">
                <textarea 
                    name="changelog_release_note[<?php echo $index; ?>]" 
                    placeholder="Optional Release Notes (Supports Markdown)" 
                    rows="3"
                ><?php echo $release_note; ?></textarea>
                
                <input 
                    type="url" 
                    name="changelog_release_url[<?php echo $index; ?>]" 
                    placeholder="Optional Release URL" 
                    value="<?php echo $release_url; ?>"
                >
            </div>
                
            <div class="changelog-entries-container">
                <?php 
                if ($version && !empty($version['entries'])) {
                    foreach ($version['entries'] as $entry_index => $entry) {
                        $this->render_changelog_entry_html($index, $entry_index, $entry);
                    }
                }
                ?>
                <button 
                    type="button" 
                    class="add-changelog-entry button" 
                    data-version-index="<?php echo $index; ?>"
                >
                    Add Changelog Entry
                </button>
            </div>
        </div>
        <?php
    }

    private function render_changelog_entry_html($version_index, $entry_index = null, $entry = null) {
        $entry_type = $entry ? esc_attr($entry['type']) : 'Added';
        $entry_description = $entry ? esc_attr($entry['description']) : '';
        ?>
        <div class="changelog-entry" data-version-index="<?php echo $version_index; ?>">
            <select name="changelog_type[<?php echo $version_index; ?>][]">
                <option value="Added" <?php selected($entry_type, 'Added'); ?>>Added</option>
                <option value="Fixed" <?php selected($entry_type, 'Fixed'); ?>>Fixed</option>
                <option value="Changed" <?php selected($entry_type, 'Changed'); ?>>Changed</option>
                <option value="Deprecated" <?php selected($entry_type, 'Deprecated'); ?>>Deprecated</option>
                <option value="Removed" <?php selected($entry_type, 'Removed'); ?>>Removed</option>
                <option value="Security" <?php selected($entry_type, 'Security'); ?>>Security</option>
            </select>
            <input 
                type="text" 
                name="changelog_description[<?php echo $version_index; ?>][]" 
                placeholder="Enter changelog description" 
                value="<?php echo $entry_description; ?>"
            >
            <button type="button" class="remove-changelog-entry button">Remove</button>
        </div>
        <?php
    }

    public function save_changelog_version_entries($post_id, $post) {

        // Verify nonce
        if (!isset($_POST['changelog_versions_nonce']) || 
            !wp_verify_nonce($_POST['changelog_versions_nonce'], 'changelog_versions_nonce')) {
            return;
        }
    
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
    
        // Check post type
        if ($post->post_type !== 'easy_changelog') {
            return;
        }
    
        $versions = [];
        if (isset($_POST['changelog_version_number']) && is_array($_POST['changelog_version_number'])) {
            foreach ($_POST['changelog_version_number'] as $index => $version_number) {
                // Sanitize version number
                $version_number = sanitize_text_field($version_number);
                
                // Skip if no version number
                if (empty($version_number)) continue;
    
                // Validate and sanitize release date
                $release_date = isset($_POST['changelog_release_date'][$index]) ? 
                    sanitize_text_field($_POST['changelog_release_date'][$index]) : 
                    date('Y-m-d');
    
                // Prepare version entry
                $version_entry = [
                    'version_number' => $version_number,
                    'release_date' => $release_date, // New field
                    'release_note' => isset($_POST['changelog_release_note'][$index]) ? 
                        sanitize_textarea_field($_POST['changelog_release_note'][$index]) : '',
                    'release_url' => isset($_POST['changelog_release_url'][$index]) ? 
                        esc_url_raw($_POST['changelog_release_url'][$index]) : '',
                    'entries' => []
                ];
    
                // Process changelog entries for this version
                if (isset($_POST['changelog_type'][$index]) && 
                    isset($_POST['changelog_description'][$index])) {
                    
                    $types = array_map('sanitize_text_field', $_POST['changelog_type'][$index]);
                    $descriptions = array_map('sanitize_text_field', $_POST['changelog_description'][$index]);
    
                    foreach ($descriptions as $entry_index => $description) {
                        if (!empty($description)) {
                            $version_entry['entries'][] = [
                                'type' => $types[$entry_index],
                                'description' => $description
                            ];
                        }
                    }
                }
    
                $versions[] = $version_entry;
            }
        }
    
        // Update post meta
        update_post_meta($post_id, '_changelog_versions', $versions);    
    
    }

    public function render_full_changelog($atts) {
        $args = [
            'post_type' => 'easy_changelog',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        $query = new WP_Query($args);

        $output = '<div class="full-changelog-container">';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                $output .= '<div class="easy-changelog">';
                $output .= '<h1 class="changelog-title">' . get_the_title() . '</h1>';
                
                // Display main content if any
                $content = get_the_content();
                if (!empty($content)) {
                    $output .= '<div class="changelog-description">' . wpautop($content) . '</div>';
                }

                // Get versions
                $versions = get_post_meta(get_the_ID(), '_changelog_versions', true);
                
                if (!empty($versions)) {
                    $output .= '<div class="changelog-versions">';
                    foreach ($versions as $version) {
                        $output .= '<div class="changelog-version">';
                        $output .= '<h2 class="version-number">' . esc_html($version['version_number']) . 
                       ' <span class="version-date">(' . date('F j, Y', strtotime($version['release_date'])) . ')</span></h2>';
                        
                        // Release note
                        if (!empty($version['release_note'])) {
                            $output .= '<div class="release-note">' . 
                                wp_kses_post(apply_filters('the_content', $version['release_note'])) . 
                                '</div>';
                        }
                        
                        // Release URL
                        if (!empty($version['release_url'])) {
                            $output .= '<div class="release-url">' . 
                                '<a href="' . esc_url($version['release_url']) . '" target="_blank">Release Details</a>' . 
                                '</div>';
                        }

                        // Changelog entries
                        if (!empty($version['entries'])) {
                            $output .= '<ul class="changelog-entries">';
                            foreach ($version['entries'] as $entry) {
                                $output .= sprintf(
                                    '<li><span class="changelog-type %s">%s:</span> %s</li>',
                                    strtolower($entry['type']),
                                    esc_html($entry['type']),
                                    esc_html($entry['description'])
                                );
                            }
                            $output .= '</ul>';
                        }
                        
                        $output .= '</div>'; // End changelog-version
                    }
                    $output .= '</div>'; // End changelog-versions
                }
                
                $output .= '</div>'; // End easy-changelog
            }
            wp_reset_postdata();
        }
        $output .= '</div>'; // End full-changelog-container

        return $output;
    }

    public function add_changelog_settings_page() {
        add_submenu_page(
            'edit.php?post_type=easy_changelog', 
            'Changelog Styling', 
            'Styling Options', 
            'manage_options', 
            'changelog-styling', 
            [$this, 'changelog_styling_page']
        );
    }
    // Register settings
    public function register_changelog_settings() {
        register_setting('changelog_styling_options', 'changelog_styling_options');

        add_settings_section(
            'changelog_styling_main', 
            'Global Changelog Styling', 
            [$this, 'styling_section_callback'], 
            'changelog-styling'
        );

        // Color settings for different log types
        $log_types = ['Added', 'Fixed', 'Tweak', 'Enhanced'];
        foreach ($log_types as $type) {
            add_settings_field(
                'changelog_' . strtolower($type) . '_color',
                $type . ' Entries Color',
                [$this, 'color_picker_callback'],
                'changelog-styling',
                'changelog_styling_main',
                ['type' => strtolower($type)]
            );
        }

        // Additional global styling options
        add_settings_field(
            'changelog_font_family',
            'Font Family',
            [$this, 'font_family_callback'],
            'changelog-styling',
            'changelog_styling_main'
        );

        add_settings_field(
            'changelog_background_color',
            'Background Color',
            [$this, 'background_color_callback'],
            'changelog-styling',
            'changelog_styling_main'
        );
    }

    // Styling section description
    public function styling_section_callback() {
        echo '<p>Customize the appearance of your changelog entries.</p>';
    }

    // Color picker callback
    public function color_picker_callback($args) {
        $options = get_option('changelog_styling_options');
        $value = isset($options[$args['type'] . '_color']) ? 
            $options[$args['type'] . '_color'] : '#000000';
        ?>
        <input 
            type="color" 
            name="changelog_styling_options[<?php echo $args['type']; ?>_color]" 
            value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }

    // Font family callback
    public function font_family_callback() {
        $options = get_option('changelog_styling_options');
        $value = isset($options['font_family']) ? 
            $options['font_family'] : 'Arial, sans-serif';
        ?>
        <input 
            type="text" 
            name="changelog_styling_options[font_family]" 
            value="<?php echo esc_attr($value); ?>" 
            placeholder="Enter font family"
        />
        <?php
    }

    // Background color callback
    public function background_color_callback() {
        $options = get_option('changelog_styling_options');
        $value = isset($options['background_color']) ? 
            $options['background_color'] : '#f4f4f4';
        ?>
        <input 
            type="color" 
            name="changelog_styling_options[background_color]" 
            value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }

    // Settings page rendering
    public function changelog_styling_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Changelog Styling Options', 'easy-change-log-manager'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('changelog_styling_options');
                do_settings_sections('changelog-styling');
                submit_button(esc_html__('Save Changes', 'easy-change-log-manager'));
                ?>
            </form>
        </div>
        <?php
    }

    // Add custom styles to frontend
    public function add_changelog_custom_styles() {
        $options = get_option('changelog_styling_options');
        
        // Default colors if not set
        $default_colors = [
            'added_color' => '#28a745',
            'fixed_color' => '#dc3545',
            'tweak_color' => '#17a2b8',
            'enhanced_color' => '#ffc107'
        ];

        $font_family = isset($options['font_family']) ? 
            $options['font_family'] : 'Arial, sans-serif';
        $background_color = isset($options['background_color']) ? 
            $options['background_color'] : '#f4f4f4';

        ?>
        <style>
            .version-changelog-container {
                font-family: <?php echo $options['font_family'] ?? 'Arial, sans-serif'; ?>;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background-color: <?php echo $background_color; ?>;
            }

            .version-changelog {
                margin-bottom: 20px;
                padding: 15px;
                border-radius: 5px;
                background-color: white;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }

            .version-changelog h2 {
                margin-bottom: 10px;
                color: #333;
            }

            .changelog-date {
                display: block;
                color: #666;
                margin-bottom: 10px;
                font-style: italic;
            }

            .changelog-entries {
                list-style-type: none;
                padding: 0;
            }

            .changelog-entries li {
                margin-bottom: 8px;
                padding: 8px;
                border-radius: 3px;
                background-color: #f9f9f9;
            }

            .changelog-type {
                font-weight: bold;
                margin-right: 10px;
                text-transform: uppercase;
            }

            .changelog-type.added { color: <?php echo $options['added_color'] ?? $default_colors['added_color']; ?>; }
            .changelog-type.fixed { color: <?php echo $options['fixed_color'] ?? $default_colors['fixed_color']; ?>; }
            .changelog-type.tweak { color: <?php echo $options['tweak_color'] ?? $default_colors['tweak_color']; ?>; }
            .changelog-type.enhanced { color: <?php echo $options['enhanced_color'] ?? $default_colors['enhanced_color']; ?>; }
        </style>
        <?php
    }

}

function initialize_easy_changelog_manager() {
    new EasyChangeLogManager();
}

// Initialize the plugin
add_action('plugins_loaded', 'initialize_easy_changelog_manager');