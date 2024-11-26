<?php
/*
Plugin Name: Easy Changelog Manager
Plugin URI: https://yourwebsite.com
Description: Detailed changelog management with version tracking and categorized entries
Version: 1.0.0
Author: Ashim
Text Domain: easy-change-log-manager
*/

class EasyChangeLogManager {
    public function __construct() {
        add_action('init', [$this, 'register_changelog_post_type']);
        add_action('add_meta_boxes', [$this, 'add_changelog_meta_boxes']);
        add_action('save_post', [$this, 'save_changelog_entries'], 10, 2);
        add_shortcode('display_version_changelog', [$this, 'render_version_changelog']);
        
        // Admin menu and settings
        add_action('admin_menu', [$this, 'add_changelog_settings_page']);
        add_action('admin_init', [$this, 'register_changelog_settings']);
        add_action('wp_head', [$this, 'add_changelog_custom_styles']);
    }


    public function register_changelog_post_type() {
        register_post_type('version_changelog', [
            'labels' => [
                'name' => __('Easy Changelogs'),
                'singular_name' => __('Easy Changelog'),
                'add_new' => __('Add New Changelog'),
                'add_new_item' => __('Add New Changelog')
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'custom-fields']
        ]);
    }

    public function add_changelog_meta_boxes() {
        add_meta_box(
            'changelog_entries',
            'Changelog Entries',
            [$this, 'changelog_entries_callback'],
            'version_changelog',
            'normal',
            'high'
        );
    }

    public function changelog_entries_callback($post) {
        wp_nonce_field('changelog_entries_nonce', 'changelog_entries_nonce');
        $entries = get_post_meta($post->ID, '_changelog_entries', true);
        ?>
        <div id="changelog-entries-container">
            <?php if (!empty($entries)) {
                foreach ($entries as $index => $entry) { ?>
                    <div class="changelog-entry">
                        <select name="changelog_type[]">
                            <option value="Added" <?php selected($entry['type'], 'Added'); ?>>
                                <?php echo esc_html__('Added', 'easy-change-log-manager'); ?>
                            </option>
                            <option value="Fixed" <?php selected($entry['type'], 'Fixed'); ?>>
                                <?php echo esc_html__('Fixed', 'easy-change-log-manager'); ?>
                            </option>
                            <option value="Tweak" <?php selected($entry['type'], 'Tweak'); ?>>
                                <?php echo esc_html__('Tweak', 'easy-change-log-manager'); ?>
                            </option>
                            <option value="Enhanced" <?php selected($entry['type'], 'Enhanced'); ?>>
                                <?php echo esc_html__('Enhanced', 'easy-change-log-manager'); ?>
                            </option>
                        </select>
                        <input type="text" name="changelog_description[]" 
                            value="<?php echo esc_attr($entry['description']); ?>" 
                            placeholder="<?php echo esc_attr__('Enter changelog description', 'easy-change-log-manager'); ?>" 
                            style="width: 70%;">
                        <button type="button" class="remove-changelog-entry">
                            <?php echo esc_html__('Remove', 'easy-change-log-manager'); ?>
                        </button>
                    </div>
                <?php }
            } ?>
        </div>
        <button type="button" id="add-changelog-entry" class="button">
            <?php echo esc_html__('Add Changelog Entry', 'easy-change-log-manager'); ?>
        </button>

        <script>
        jQuery(document).ready(function($) {
            $('#add-changelog-entry').on('click', function() {
                var newEntry = `
                    <div class="changelog-entry">
                        <select name="changelog_type[]">
                            <option value="Added">Added</option>
                            <option value="Fixed">Fixed</option>
                            <option value="Tweak">Tweak</option>
                            <option value="Enhanced">Enhanced</option>
                        </select>
                        <input type="text" name="changelog_description[]" 
                               placeholder="Enter changelog description" 
                               style="width: 70%;">
                        <button type="button" class="remove-changelog-entry">Remove</button>
                    </div>
                `;
                $('#changelog-entries-container').append(newEntry);
            });

            $(document).on('click', '.remove-changelog-entry', function() {
                $(this).closest('.changelog-entry').remove();
            });
        });
        </script>
        <?php
    }    

    public function save_changelog_entries($post_id, $post) {
        if (!isset($_POST['changelog_entries_nonce']) || 
            !wp_verify_nonce($_POST['changelog_entries_nonce'], 'changelog_entries_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type == 'version_changelog') {
            $changelog_types = isset($_POST['changelog_type']) ? 
                array_map('sanitize_text_field', $_POST['changelog_type']) : [];
            $changelog_descriptions = isset($_POST['changelog_description']) ? 
                array_map('sanitize_text_field', $_POST['changelog_description']) : [];

            $entries = [];
            for ($i = 0; $i < count($changelog_types); $i++) {
                if (!empty($changelog_descriptions[$i])) {
                    $entries[] = [
                        'type' => $changelog_types[$i],
                        'description' => $changelog_descriptions[$i]
                    ];
                }
            }

            update_post_meta($post_id, '_changelog_entries', $entries);
        }
    }    

    public function render_version_changelog($atts) {
        $args = [
            'post_type' => 'version_changelog',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        $query = new WP_Query($args);

        $output = '<div class="version-changelog-container">';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $entries = get_post_meta(get_the_ID(), '_changelog_entries', true);
                
                $output .= '<div class="version-changelog">';
                $output .= '<h2>' . get_the_title() . '</h2>';
                $output .= '<span class="changelog-date">' . get_the_date() . '</span>';
                
                if (!empty($entries)) {
                    $output .= '<ul class="changelog-entries">';
                    foreach ($entries as $entry) {
                        $output .= sprintf(
                            '<li><span class="changelog-type %s">%s:</span> %s</li>',
                            strtolower($entry['type']),
                            $entry['type'],
                            $entry['description']
                        );
                    }
                    $output .= '</ul>';
                }
                
                $output .= '</div>';
            }
            wp_reset_postdata();
        }
        $output .= '</div>';

        return $output;
    }    

    // New method to add settings page
    public function add_changelog_settings_page() {
        add_submenu_page(
            'edit.php?post_type=version_changelog', 
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
add_action('plugins_loaded', 'initialize_easy_changelog_manager');