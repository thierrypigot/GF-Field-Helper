<?php
/**
 * Plugin Name: GF Field Helper - Help Tooltips
 * Plugin URI: https://www.wearewp.pro
 * Description: Adds help tooltips to Gravity Forms fields with short text + long text
 * Version: 1.0.1
 * Author: WeAre[WP]
 * Author URI: https://www.wearewp.pro
 * Text Domain: gf-field-helper
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * 
 *
 * © 2025 WeAre[WP] – Thierry Pigot
 *
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GF_FIELD_HELPER_VERSION', '1.0.1');
define('GF_FIELD_HELPER_PATH', plugin_dir_path(__FILE__));
define('GF_FIELD_HELPER_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class GF_Field_Helper {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Check that Gravity Forms is active
        add_action('admin_init', [$this, 'check_gravity_forms']);
        
        // Hooks for form editor (backend)
        add_action('gform_field_standard_settings', [$this, 'add_field_settings'], 10, 2);
        add_action('gform_editor_js', [$this, 'editor_script']);
        add_filter('gform_tooltips', [$this, 'add_tooltips']);
        
        // Hook for frontend
        add_filter('gform_field_content', [$this, 'render_field_help'], 10, 5);
        add_action('gform_enqueue_scripts', [$this, 'enqueue_frontend_assets'], 10, 2);
        
        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Translations
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    /**
     * Checks that Gravity Forms is installed
     */
    public function check_gravity_forms() {
        if (!class_exists('GFForms')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('GF Field Helper requires Gravity Forms to work.', 'gf-field-helper');
                echo '</p></div>';
            });
        }
    }

    /**
     * Loads translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('gf-field-helper', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Valide et nettoie une URL de manière stricte
     * 
     * @param string $url URL à valider
     * @return string URL validée ou chaîne vide si invalide
     */
    private function validate_url($url) {
        if (empty($url)) {
            return '';
        }
        
        // Échapper d'abord
        $url = esc_url_raw($url);
        
        // Vérifier que l'URL utilise uniquement http:// ou https://
        $allowed_protocols = ['http', 'https'];
        $parsed_url = wp_parse_url($url);
        
        if (empty($parsed_url['scheme'])) {
            return '';
        }
        
        if (!in_array(strtolower($parsed_url['scheme']), $allowed_protocols, true)) {
            return '';
        }
        
        // Validation supplémentaire avec wp_http_validate_url si disponible
        if (function_exists('wp_http_validate_url')) {
            $validated_url = wp_http_validate_url($url);
            if ($validated_url === false) {
                return '';
            }
            $url = $validated_url;
        }
        
        // Échapper pour l'affichage
        return esc_url($url);
    }

    /**
     * Filtre HTML plus restrictif pour le contenu long
     * 
     * @param string $content Contenu à filtrer
     * @return string Contenu filtré
     */
    private function kses_long_text($content) {
        $allowed_tags = [
            'strong' => [],
            'em' => [],
            'br' => [],
            'p' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'a' => [
                'href' => [],
                'target' => ['_blank'],
                'rel' => ['noopener', 'noreferrer'],
            ],
        ];
        
        // Filtrer le contenu
        $filtered = wp_kses($content, $allowed_tags);
        
        // Validation supplémentaire : vérifier que les liens sont valides
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $filtered, $matches)) {
            foreach ($matches[1] as $link_url) {
                $validated_url = $this->validate_url($link_url);
                if (empty($validated_url)) {
                    // Retirer le lien invalide
                    $filtered = preg_replace(
                        '/<a[^>]+href=["\']' . preg_quote($link_url, '/') . '["\'][^>]*>.*?<\/a>/i',
                        '',
                        $filtered
                    );
                }
            }
        }
        
        return $filtered;
    }

    /**
     * Valide la longueur des textes d'aide
     * 
     * @param string $text Texte à valider
     * @param string $type 'short' ou 'long'
     * @return string Texte tronqué si nécessaire
     */
    private function validate_text_length($text, $type = 'short') {
        $max_lengths = [
            'short' => 200,  // 200 caractères pour le texte court
            'long' => 2000,  // 2000 caractères pour le texte long
        ];
        
        $max_length = isset($max_lengths[$type]) ? $max_lengths[$type] : 200;
        
        if (mb_strlen($text) > $max_length) {
            return mb_substr($text, 0, $max_length);
        }
        
        return $text;
    }

    /**
     * Adds settings fields in form editor
     */
    public function add_field_settings($position, $form_id) {
        // Position 25 = after label, before description
        if ($position == 25) {
            ?>
            <li class="field_help_setting field_setting" style="display: list-item;">
                <div style="background: #f0f6fc; border: 1px solid #c3d9ed; border-radius: 4px; padding: 15px; margin: 10px 0;">
                    <h4 style="margin: 0 0 15px 0; color: #1e3a5f; display: flex; align-items: center; gap: 8px;">
                        <span style="background: #0073aa; color: white; width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px;">?</span>
                        <?php esc_html_e('Contextual Help (Tooltip)', 'gf-field-helper'); ?>
                    </h4>
                    
                    <div style="margin-bottom: 12px;">
                        <label for="field_help_short" class="section_label" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e('Short text (visible)', 'gf-field-helper'); ?>
                            <?php gform_tooltip('field_help_short_tooltip'); ?>
                        </label>
                        <input type="text" 
                               id="field_help_short" 
                               class="fieldwidth-3"
                               onchange="SetFieldProperty('fieldHelpShort', this.value);"
                               placeholder="<?php esc_attr_e('Ex: Where to find this information?', 'gf-field-helper'); ?>"
                               style="width: 100%;">
                    </div>
                    
                    <div style="margin-bottom: 12px;">
                        <label for="field_help_type" class="section_label" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e('Help type', 'gf-field-helper'); ?>
                        </label>
                        <select id="field_help_type" 
                                onchange="SetFieldProperty('fieldHelpType', this.value); toggleHelpLinkField(this.value);"
                                style="width: 100%;">
                            <option value="tooltip"><?php esc_html_e('Tooltip (bubble on click)', 'gf-field-helper'); ?></option>
                            <option value="link"><?php esc_html_e('Link to a page', 'gf-field-helper'); ?></option>
                        </select>
                    </div>
                    
                    <div id="field_help_long_wrapper" style="margin-bottom: 12px;">
                        <label for="field_help_long" class="section_label" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e('Long text (in bubble)', 'gf-field-helper'); ?>
                            <?php gform_tooltip('field_help_long_tooltip'); ?>
                        </label>
                        <textarea id="field_help_long" 
                                  class="fieldwidth-3" 
                                  rows="4"
                                  onchange="SetFieldProperty('fieldHelpLong', this.value);"
                                  placeholder="<?php esc_attr_e('Detailed explanation that will appear in the help bubble...', 'gf-field-helper'); ?>"
                                  style="width: 100%;"></textarea>
                        <p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">
                            <?php esc_html_e('Basic HTML allowed: <strong>, <em>, <a href="">, <br>', 'gf-field-helper'); ?>
                        </p>
                    </div>
                    
                    <div id="field_help_link_wrapper" style="margin-bottom: 0; display: none;">
                        <label for="field_help_link" class="section_label" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php esc_html_e('Link URL', 'gf-field-helper'); ?>
                        </label>
                        <input type="url" 
                               id="field_help_link" 
                               class="fieldwidth-3"
                               onchange="SetFieldProperty('fieldHelpLink', this.value);"
                               placeholder="https://"
                               style="width: 100%;">
                    </div>
                </div>
            </li>
            <?php
        }
    }

    /**
     * JavaScript for form editor
     */
    public function editor_script() {
        ?>
        <script type="text/javascript">
        // Add our properties to all field types
        jQuery(document).ready(function($) {
            // List of supported field types
            var supportedFields = [
                'text', 'textarea', 'select', 'multiselect', 'number', 'phone', 
                'email', 'website', 'name', 'address', 'date', 'time', 
                'radio', 'checkbox', 'hidden', 'list', 'fileupload',
                'post_title', 'post_content', 'post_excerpt', 'post_tags',
                'post_category', 'post_custom_field', 'consent'
            ];
            
            // Add the setting to all supported types
            supportedFields.forEach(function(fieldType) {
                if (typeof fieldSettings !== 'undefined') {
                    fieldSettings[fieldType] = (fieldSettings[fieldType] || '') + ', .field_help_setting';
                }
            });
        });

        // Binding to load values when a field is selected
        jQuery(document).on('gform_load_field_settings', function(event, field, form) {
            jQuery('#field_help_short').val(field.fieldHelpShort || '');
            jQuery('#field_help_long').val(field.fieldHelpLong || '');
            jQuery('#field_help_type').val(field.fieldHelpType || 'tooltip');
            jQuery('#field_help_link').val(field.fieldHelpLink || '');
            toggleHelpLinkField(field.fieldHelpType || 'tooltip');
        });

        // Show/hide URL field and long text field based on type
        function toggleHelpLinkField(type) {
            if (type === 'link') {
                jQuery('#field_help_link_wrapper').show();
                jQuery('#field_help_long_wrapper').hide();
            } else {
                jQuery('#field_help_link_wrapper').hide();
                jQuery('#field_help_long_wrapper').show();
            }
        }

        // Validation côté client pour le texte court (max 200 caractères)
        jQuery(document).on('input', '#field_help_short', function() {
            var maxLength = 200;
            var currentLength = jQuery(this).val().length;
            if (currentLength > maxLength) {
                jQuery(this).val(jQuery(this).val().substring(0, maxLength));
                alert('<?php echo esc_js(__('Le texte court est limité à 200 caractères.', 'gf-field-helper')); ?>');
            }
        });

        // Validation côté client pour le texte long (max 2000 caractères)
        jQuery(document).on('input', '#field_help_long', function() {
            var maxLength = 2000;
            var currentLength = jQuery(this).val().length;
            if (currentLength > maxLength) {
                jQuery(this).val(jQuery(this).val().substring(0, maxLength));
                alert('<?php echo esc_js(__('Le texte long est limité à 2000 caractères.', 'gf-field-helper')); ?>');
            }
        });

        // Validation de l'URL (doit commencer par http:// ou https://)
        jQuery(document).on('input blur', '#field_help_link', function() {
            var url = jQuery(this).val();
            var urlWrapper = jQuery(this).closest('#field_help_link_wrapper');
            var errorMsg = urlWrapper.find('.url-error-message');
            
            if (url && !url.match(/^https?:\/\//i)) {
                jQuery(this).css('border-color', '#dc3232');
                if (errorMsg.length === 0) {
                    jQuery('<p class="url-error-message" style="color: #dc3232; font-size: 12px; margin-top: 5px;"><?php echo esc_js(__('L\'URL doit commencer par http:// ou https://', 'gf-field-helper')); ?></p>').insertAfter(jQuery(this));
                }
            } else {
                jQuery(this).css('border-color', '');
                errorMsg.remove();
            }
        });
        </script>
        <?php
    }

    /**
     * Tooltips for admin interface
     */
    public function add_tooltips($tooltips) {
        $tooltips['field_help_short_tooltip'] = sprintf(
            '<h6>%s</h6>%s',
            esc_html__('Short text', 'gf-field-helper'),
            esc_html__('This short text will be displayed next to the field label, with a "?" icon. It should encourage the user to click to learn more.', 'gf-field-helper')
        );
        $tooltips['field_help_long_tooltip'] = sprintf(
            '<h6>%s</h6>%s',
            esc_html__('Long text', 'gf-field-helper'),
            esc_html__('This detailed text will appear in a bubble (tooltip) when the user clicks on the help. You can use basic HTML.', 'gf-field-helper')
        );
        return $tooltips;
    }

    /**
     * Renders help on frontend
     */
    public function render_field_help($content, $field, $value, $lead_id, $form_id) {
        // Do nothing if no short text defined
        if (empty($field->fieldHelpShort)) {
            return $content;
        }

        // Valider et nettoyer le texte court
        $short_text_raw = isset($field->fieldHelpShort) ? $field->fieldHelpShort : '';
        $short_text_raw = $this->validate_text_length($short_text_raw, 'short');
        $short_text = wp_kses($short_text_raw, [
            'strong' => [],
            'em' => [],
            'br' => [],
        ]);

        // Valider et nettoyer le texte long
        $long_text_raw = isset($field->fieldHelpLong) ? $field->fieldHelpLong : '';
        $long_text_raw = $this->validate_text_length($long_text_raw, 'long');
        $long_text = $this->kses_long_text($long_text_raw);

        // Valider le type d'aide
        $help_type = !empty($field->fieldHelpType) ? $field->fieldHelpType : 'tooltip';
        if (!in_array($help_type, ['tooltip', 'link'], true)) {
            $help_type = 'tooltip';
        }

        // Valider l'URL de manière stricte
        $help_link = '';
        if ($help_type === 'link' && !empty($field->fieldHelpLink)) {
            $help_link = $this->validate_url($field->fieldHelpLink);
            // Si l'URL est invalide, basculer vers tooltip
            if (empty($help_link)) {
                $help_type = 'tooltip';
            }
        }

        // Build help HTML
        if ($help_type === 'link' && $help_link) {
            $help_html = sprintf(
                '<a href="%s" class="gf-field-help-link" target="_blank" rel="noopener noreferrer">
                    <span class="gf-field-help-icon" aria-hidden="true">?</span>
                    <span class="gf-field-help-text">%s</span>
                </a>',
                $help_link,
                $short_text
            );
        } else {
            $help_html = sprintf(
                '<button type="button" 
                        class="gf-field-help-trigger" 
                        data-tippy-content="%s"
                        aria-label="%s"
                        aria-expanded="false">
                    <span class="gf-field-help-icon" aria-hidden="true">?</span>
                    <span class="gf-field-help-text">%s</span>
                </button>',
                esc_attr($long_text),
                esc_attr(sprintf(__('Help: %s', 'gf-field-helper'), $short_text)),
                $short_text
            );
        }

        // Inject help after label avec validation
        $pattern = '/(<label[^>]*class=["\'][^"\']*gfield_label[^"\']*["\'][^>]*>.*?<\/label>)/is';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, '$1' . $help_html, $content, 1);
        } else {
            // Fallback : ajouter avant le champ si le label n'est pas trouvé
            $content = $help_html . $content;
        }

        return $content;
    }

    /**
     * Assets for frontend
     */
    public function enqueue_frontend_assets($form, $is_ajax) {
        // Check if at least one field has help
        $has_help = false;
        foreach ($form['fields'] as $field) {
            if (!empty($field->fieldHelpShort)) {
                $has_help = true;
                break;
            }
        }

        if (!$has_help) {
            return;
        }

        // Popper.js (local)
        wp_enqueue_script(
            'popper-js',
            GF_FIELD_HELPER_URL . 'assets/js/popper.min.js',
            [],
            '2.11.8',
            true
        );

        // Tippy.js (local)
        wp_enqueue_script(
            'tippy-js',
            GF_FIELD_HELPER_URL . 'assets/js/tippy.min.js',
            ['popper-js'],
            '6.3.7',
            true
        );

        // Tippy CSS (local)
        wp_enqueue_style(
            'tippy-css',
            GF_FIELD_HELPER_URL . 'assets/css/tippy.css',
            [],
            '6.3.7'
        );

        // Our custom CSS
        wp_add_inline_style('tippy-css', $this->get_frontend_css());

        // Our initialization JS
        wp_add_inline_script('tippy-js', $this->get_frontend_js());
    }

    /**
     * Frontend CSS
     */
    private function get_frontend_css() {
        return '
        /* Help container */
        .gf-field-help-trigger,
        .gf-field-help-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 10px;
            padding: 4px 8px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #0073aa;
            font-size: 0.85em;
            font-family: inherit;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.2s ease;
            vertical-align: middle;
        }

        .gf-field-help-trigger:hover,
        .gf-field-help-trigger:focus,
        .gf-field-help-link:hover,
        .gf-field-help-link:focus {
            background: rgba(0, 115, 170, 0.1);
            color: #005177;
            outline: none;
        }

        .gf-field-help-trigger:focus-visible,
        .gf-field-help-link:focus-visible {
            outline: 2px solid #0073aa;
            outline-offset: 2px;
        }

        /* ? Icon */
        .gf-field-help-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            min-width: 20px;
            border-radius: 50%;
            background: #0073aa;
            color: white;
            font-size: 12px;
            font-weight: 700;
            font-style: normal;
            line-height: 1;
        }

        .gf-field-help-trigger:hover .gf-field-help-icon,
        .gf-field-help-link:hover .gf-field-help-icon {
            background: #005177;
        }

        /* Short text */
        .gf-field-help-text {
            text-decoration: underline;
            text-decoration-style: dotted;
            text-underline-offset: 3px;
        }

        /* Custom Tippy theme */
        .tippy-box[data-theme~="gf-help"] {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: #ffffff;
            font-size: 14px;
            line-height: 1.6;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
            max-width: 380px;
        }

        .tippy-box[data-theme~="gf-help"] .tippy-content {
            padding: 0;
        }

        .tippy-box[data-theme~="gf-help"] .tippy-arrow {
            color: #1e3a5f;
        }

        .tippy-box[data-theme~="gf-help"] a {
            color: #90cdf4;
            text-decoration: underline;
        }

        .tippy-box[data-theme~="gf-help"] a:hover {
            color: #ffffff;
        }

        .tippy-box[data-theme~="gf-help"] strong {
            color: #fbbf24;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .gf-field-help-trigger,
            .gf-field-help-link {
                margin-left: 5px;
                padding: 3px 6px;
            }
            
            .gf-field-help-text {
                font-size: 0.8em;
            }
            
            .tippy-box[data-theme~="gf-help"] {
                max-width: calc(100vw - 40px);
                font-size: 13px;
            }
        }

        /* Opening animation */
        .tippy-box[data-theme~="gf-help"][data-animation="shift-away"][data-state="hidden"] {
            opacity: 0;
        }

        .tippy-box[data-theme~="gf-help"][data-animation="shift-away"][data-state="hidden"][data-placement^="top"] {
            transform: translateY(10px);
        }

        .tippy-box[data-theme~="gf-help"][data-animation="shift-away"][data-state="hidden"][data-placement^="bottom"] {
            transform: translateY(-10px);
        }

        .tippy-box[data-theme~="gf-help"][data-animation="shift-away"][data-state="hidden"][data-placement^="left"] {
            transform: translateX(10px);
        }

        .tippy-box[data-theme~="gf-help"][data-animation="shift-away"][data-state="hidden"][data-placement^="right"] {
            transform: translateX(-10px);
        }
        ';
    }

    /**
     * Frontend JavaScript
     */
    private function get_frontend_js() {
        return "
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Tippy on all help triggers
            if (typeof tippy !== 'undefined') {
                tippy('.gf-field-help-trigger', {
                    theme: 'gf-help',
                    trigger: 'click',
                    interactive: true,
                    placement: 'right-start',
                    animation: 'shift-away',
                    arrow: true,
                    maxWidth: 380,
                    appendTo: function() { return document.body; },
                    allowHTML: true,
                    onShow: function(instance) {
                        // Update aria-expanded
                        instance.reference.setAttribute('aria-expanded', 'true');
                        
                        // Close with Escape
                        var escHandler = function(e) {
                            if (e.key === 'Escape') {
                                instance.hide();
                                document.removeEventListener('keydown', escHandler);
                            }
                        };
                        document.addEventListener('keydown', escHandler);
                        
                        // Close other tooltips
                        document.querySelectorAll('.gf-field-help-trigger').forEach(function(el) {
                            if (el !== instance.reference && el._tippy) {
                                el._tippy.hide();
                            }
                        });
                    },
                    onHide: function(instance) {
                        instance.reference.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });

        // Reinitialize after AJAX (pagination, etc.)
        jQuery(document).on('gform_post_render', function(event, formId) {
            if (typeof tippy !== 'undefined') {
                // Destroy existing instances
                document.querySelectorAll('.gf-field-help-trigger').forEach(function(el) {
                    if (el._tippy) {
                        el._tippy.destroy();
                    }
                });
                
                // Reinitialize
                tippy('.gf-field-help-trigger', {
                    theme: 'gf-help',
                    trigger: 'click',
                    interactive: true,
                    placement: 'right-start',
                    animation: 'shift-away',
                    arrow: true,
                    maxWidth: 380,
                    appendTo: function() { return document.body; },
                    allowHTML: true
                });
            }
        });
        ";
    }

    /**
     * Assets for admin
     */
    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, ['toplevel_page_gf_edit_forms', 'forms_page_gf_edit_forms'])) {
            return;
        }

        wp_add_inline_style('gform_admin', '
            .field_help_setting {
                display: list-item !important;
            }
            .field_help_setting h4 {
                font-size: 14px;
            }
            .field_help_setting .description {
                color: #666;
            }
        ');
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    GF_Field_Helper::get_instance();
}, 20);

// Activation hook
register_activation_hook(__FILE__, function() {
    if (!class_exists('GFForms')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('This plugin requires Gravity Forms. Please install and activate Gravity Forms first.', 'gf-field-helper'),
            __('Plugin not activated', 'gf-field-helper'),
            ['back_link' => true]
        );
    }
});
