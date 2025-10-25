<?php

/**
 * Plugin Name: Purim Popup
 * Description: Gestisce popup caricati da contenuti dedicati e fornisce shortcode e blocchi per aprirli.
 * Version: 1.0
 * Author: Stefano
 */

if (! defined('ABSPATH')) {
    exit;
}

class Purim_Popup_Plugin
{
    /**
     * Post type used for popup content items.
     *
     * @var string
     */
    private $popup_post_type = 'purim-popup';

    /**
     * Post type used for image maps with hotspots.
     *
     * @var string
     */
    private $map_post_type = 'purim-image-map';

    /**
     * Meta key storing image map configuration.
     *
     * @var string
     */
    private $map_meta_key = '_purim_map_settings';

    public function __construct()
    {
        add_action('init', [$this, 'register_popup_post_type']);
        add_action('init', [$this, 'register_image_map_post_type']);
        add_action('init', [$this, 'register_image_map_block']);

        add_shortcode('purim_popup', [$this, 'render_shortcode']);

        add_action('wp_ajax_purim_get_popup', [$this, 'ajax_get_popup']);
        add_action('wp_ajax_nopriv_purim_get_popup', [$this, 'ajax_get_popup']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_filter('the_content', [$this, 'show_shortcode_in_popup_page']);

        add_action('add_meta_boxes', [$this, 'register_image_map_metabox']);
        add_action('save_post_' . $this->map_post_type, [$this, 'save_image_map_meta'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'localize_block_editor_assets']);
    }

    public function register_image_map_post_type()
    {
        $labels = [
            'name'               => 'Mappe Popup',
            'singular_name'      => 'Mappa Popup',
            'menu_name'          => 'Mappe Popup',
            'name_admin_bar'     => 'Mappa Popup',
            'add_new'            => 'Aggiungi nuova',
            'add_new_item'       => 'Aggiungi nuova mappa',
            'new_item'           => 'Nuova mappa',
            'edit_item'          => 'Modifica mappa',
            'view_item'          => 'Visualizza mappa',
            'all_items'          => 'Tutte le mappe',
            'search_items'       => 'Cerca mappe',
            'parent_item_colon'  => null,
            'not_found'          => 'Nessuna mappa trovata',
            'not_found_in_trash' => 'Nessuna mappa nel cestino',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'supports'           => ['title'],
            'capability_type'    => 'page',
            'map_meta_cap'       => true,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-location-alt',
        ];

        register_post_type($this->map_post_type, $args);
    }

    public function register_image_map_block()
    {
        if (! function_exists('register_block_type')) {
            return;
        }

        $script_handle = 'purim-popup-image-map-block';
        $style_handle = 'purim-popup-image-map';

        if (! wp_script_is($script_handle, 'registered')) {
            wp_register_script(
                $script_handle,
                plugins_url('js/blocks/image-map-block.js', __FILE__),
                ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n'],
                '1.0',
                true
            );
        }

        if (! wp_style_is($style_handle, 'registered')) {
            wp_register_style(
                $style_handle,
                plugins_url('css/purim-image-map.css', __FILE__),
                [],
                '1.0'
            );
        }

        register_block_type('purim/popup-image-map', [
            'editor_script' => $script_handle,
            'style' => $style_handle,
            'render_callback' => [$this, 'render_image_map_block'],
            'attributes' => [
                'mapId' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ]);
    }

    public function register_image_map_metabox($post_type = '', $post = null)
    {
        add_meta_box(
            'purim-image-map-editor',
            __('Configurazione mappa popup', 'purim'),
            [$this, 'render_image_map_metabox'],
            $this->map_post_type,
            'normal',
            'high'
        );
    }

    public function render_image_map_metabox($post)
    {
        wp_nonce_field('purim_image_map_save', 'purim_image_map_nonce');

        $data = $this->get_image_map_data($post->ID);
        $json = wp_json_encode($data);
        if (! is_string($json)) {
            $json = '';
        }

        $json_attr = esc_attr($json);
?>
        <div id="purim-image-map-editor"
            class="purim-image-map-editor"
            data-config="<?php echo $json_attr; ?>">
            <p class="description">
                <?php esc_html_e('Seleziona un’immagine, aggiungi punti cliccando sopra di essa e collega ciascun punto a un popup esistente.', 'purim'); ?>
            </p>

            <div class="purim-image-map-editor__image">
                <div class="purim-image-map-editor__canvas" data-preview-area></div>
                <div class="purim-image-map-editor__image-actions">
                    <button type="button" class="button button-secondary" data-select-image>
                        <?php esc_html_e('Scegli immagine', 'purim'); ?>
                    </button>
                    <button type="button" class="button button-link-delete" data-remove-image>
                        <?php esc_html_e('Rimuovi immagine', 'purim'); ?>
                    </button>
                </div>
                <label class="purim-image-map-editor__alt-label">
                    <?php esc_html_e('Testo alternativo immagine', 'purim'); ?>
                    <input type="text" class="widefat" data-image-alt />
                </label>
            </div>

            <div class="purim-image-map-editor__hotspots">
                <div class="purim-image-map-editor__hotspots-header">
                    <strong><?php esc_html_e('Punti interattivi', 'purim'); ?></strong>
                    <button type="button" class="button button-secondary" data-add-hotspot>
                        <?php esc_html_e('Aggiungi punto', 'purim'); ?>
                    </button>
                </div>
                <div class="purim-image-map-editor__list" data-hotspot-list></div>
            </div>

            <input type="hidden" name="purim_image_map_data" data-map-input value="<?php echo $json_attr; ?>">
        </div>
    <?php
    }

    public function register_popup_post_type()
    {
        $labels = [
            'name'               => 'Popup',
            'singular_name'      => 'Popup',
            'menu_name'          => 'Popup',
            'name_admin_bar'     => 'Popup',
            'add_new'            => 'Aggiungi nuovo',
            'add_new_item'       => 'Aggiungi nuovo Popup',
            'new_item'           => 'Nuovo Popup',
            'edit_item'          => 'Modifica Popup',
            'view_item'          => 'Visualizza Popup',
            'all_items'          => 'Tutti i Popup',
            'search_items'       => 'Cerca Popup',
            'parent_item_colon'  => null,
            'not_found'          => 'Nessun popup trovato',
            'not_found_in_trash' => 'Nessun popup nel cestino',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'has_archive'        => false,
            'rewrite'            => ['slug' => 'purim-popup'],
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
            'capability_type'    => 'page',
            'map_meta_cap'       => true,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-welcome-view-site',
        ];

        register_post_type($this->popup_post_type, $args);
    }


    public function render_shortcode($atts, $content = null)
    {
        $atts = shortcode_atts([
            'id' => '',
            'slug' => '',
            'text' => '',
        ], $atts, 'purim_popup');

        $post_id = 0;

        if (! empty($atts['id'])) {
            $post_id = intval($atts['id']);
        } elseif (! empty($atts['slug'])) {
            $slug = sanitize_text_field($atts['slug']);
            $page = get_page_by_path($slug, OBJECT, $this->popup_post_type);
            if ($page instanceof WP_Post) {
                $post_id = (int) $page->ID;
            }
        }

        if (! $post_id) {
            return '';
        }

        if (get_post_type($post_id) !== $this->popup_post_type) {
            return '';
        }

        $link_text = $atts['text'];
        if ($link_text === '' && is_string($content)) {
            $link_text = $content;
        }

        if ($link_text === '') {
            $link_text = 'Apri popup';
        }

        $link_text = esc_html($link_text);

        return sprintf(
            '<a href="#" class="purim-popup-trigger" data-popup-id="%d">%s</a>',
            $post_id,
            $link_text
        );
    }

    public function ajax_get_popup()
    {
        check_ajax_referer('purim-popup', 'nonce');

        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error('ID non valido');
        }

        $post = get_post($id);

        if (!$post) {
            wp_send_json_error('Popup non trovato');
        }

        if ($post->post_type !== $this->popup_post_type) {
            wp_send_json_error('Il contenuto richiesto non è un popup valido');
        }

        wp_send_json_success([
            'title' => $post->post_title,
            'content' => apply_filters('the_content', $post->post_content),
        ]);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('purim-popup-css', plugins_url('css/purim-popup.css', __FILE__), [], '1.0');
        wp_enqueue_script('purim-popup-js', plugins_url('js/purim-popup.js', __FILE__), ['jquery'], '1.0', true);

        wp_localize_script('purim-popup-js', 'PurimPopup', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('purim-popup'),
        ]);
    }

    public function enqueue_admin_assets($hook_suffix)
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || $screen->post_type !== $this->map_post_type) {
            return;
        }

        wp_enqueue_media();

        if (! wp_style_is('purim-popup-image-map', 'registered')) {
            wp_register_style(
                'purim-popup-image-map',
                plugins_url('css/purim-image-map.css', __FILE__),
                [],
                '1.0'
            );
        }

        wp_enqueue_style('purim-popup-image-map');
        wp_enqueue_style(
            'purim-popup-admin-image-map',
            plugins_url('css/purim-image-map-admin.css', __FILE__),
            ['purim-popup-image-map'],
            '1.0'
        );

        wp_register_script(
            'purim-popup-admin-image-map',
            plugins_url('js/admin-image-map.js', __FILE__),
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('purim-popup-admin-image-map', 'PurimImageMapAdmin', [
            'popups' => $this->get_popup_choices(),
            'strings' => [
                'addHotspot' => __('Aggiungi un punto cliccabile', 'purim'),
                'hotspotLabel' => __('Etichetta del punto', 'purim'),
                'selectPopup' => __('Popup associato', 'purim'),
                'remove' => __('Rimuovi', 'purim'),
                'noPopup' => __('— Nessun popup —', 'purim'),
                'positionX' => __('Posizione X (%)', 'purim'),
                'positionY' => __('Posizione Y (%)', 'purim'),
                'imageMissing' => __('Seleziona prima un’immagine per poter aggiungere punti.', 'purim'),
                'chooseImage' => __('Scegli immagine', 'purim'),
            ],
        ]);

        wp_enqueue_script('purim-popup-admin-image-map');
    }

    public function localize_block_editor_assets()
    {
        if (! wp_script_is('purim-popup-image-map-block', 'registered')) {
            return;
        }

        wp_enqueue_script('purim-popup-image-map-block');
        wp_localize_script('purim-popup-image-map-block', 'PurimImageMapBlock', [
            'maps' => $this->get_image_map_choices(),
        ]);
    }

    public function show_shortcode_in_popup_page($content)
    {
        if (is_singular($this->popup_post_type)) {
            $shortcode = '[purim_popup id="' . get_the_ID() . '" text="Apri popup"]';
            $content .= '<div class="purim-popup-shortcode"><strong>Shortcode per questo popup:</strong><br><code>' . esc_html($shortcode) . '</code></div>';
        }
        return $content;
    }

    public function save_image_map_meta($post_id, $post)
    {
        if (! isset($_POST['purim_image_map_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['purim_image_map_nonce'])), 'purim_image_map_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $raw = isset($_POST['purim_image_map_data']) ? wp_unslash($_POST['purim_image_map_data']) : '';
        if (! is_string($raw) || $raw === '') {
            delete_post_meta($post_id, $this->map_meta_key);
            return;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            delete_post_meta($post_id, $this->map_meta_key);
            return;
        }

        $sanitized = $this->sanitize_image_map_data($decoded);

        if ($sanitized === null) {
            delete_post_meta($post_id, $this->map_meta_key);
            return;
        }

        update_post_meta($post_id, $this->map_meta_key, $sanitized);
    }

    private function sanitize_image_map_data(array $data)
    {
        $image_defaults = [
            'id' => 0,
            'url' => '',
            'alt' => '',
            'width' => 0,
            'height' => 0,
        ];

        $image = isset($data['image']) && is_array($data['image']) ? array_merge($image_defaults, $data['image']) : $image_defaults;

        $image['id'] = isset($image['id']) ? absint($image['id']) : 0;
        $image['url'] = isset($image['url']) ? esc_url_raw($image['url']) : '';
        $image['alt'] = isset($image['alt']) ? sanitize_text_field($image['alt']) : '';
        $image['width'] = isset($image['width']) ? absint($image['width']) : 0;
        $image['height'] = isset($image['height']) ? absint($image['height']) : 0;

        if ($image['id']) {
            $image['url'] = wp_get_attachment_url($image['id']);
            $meta = wp_get_attachment_metadata($image['id']);
            if (is_array($meta)) {
                $image['width'] = isset($meta['width']) ? absint($meta['width']) : $image['width'];
                $image['height'] = isset($meta['height']) ? absint($meta['height']) : $image['height'];
            }
            if ($image['alt'] === '') {
                $image['alt'] = get_post_meta($image['id'], '_wp_attachment_image_alt', true);
            }
        }

        if (! $image['id'] || ! $image['url']) {
            return null;
        }

        $hotspots = [];
        if (isset($data['hotspots']) && is_array($data['hotspots'])) {
            foreach ($data['hotspots'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $popup_id = isset($item['popup_id']) ? absint($item['popup_id']) : 0;
                if ($popup_id && get_post_type($popup_id) !== $this->popup_post_type) {
                    $popup_id = 0;
                }
                $x = isset($item['x']) ? floatval($item['x']) : 0;
                $y = isset($item['y']) ? floatval($item['y']) : 0;
                $x = max(0, min(100, $x));
                $y = max(0, min(100, $y));

                $hotspots[] = [
                    'uid' => isset($item['uid']) ? sanitize_key((string) $item['uid']) : uniqid('hs_', true),
                    'label' => isset($item['label']) ? sanitize_text_field($item['label']) : '',
                    'popup_id' => $popup_id,
                    'x' => $x,
                    'y' => $y,
                ];
            }
        }

        return [
            'image' => $image,
            'hotspots' => $hotspots,
        ];
    }

    private function get_image_map_data($post_id)
    {
        $stored = get_post_meta($post_id, $this->map_meta_key, true);
        $defaults = [
            'image' => [
                'id' => 0,
                'url' => '',
                'alt' => '',
                'width' => 0,
                'height' => 0,
            ],
            'hotspots' => [],
        ];

        if (! is_array($stored)) {
            return $defaults;
        }

        $stored = array_merge($defaults, $stored);
        if (! is_array($stored['hotspots'])) {
            $stored['hotspots'] = [];
        }

        return $stored;
    }

    private function get_popup_choices()
    {
        $posts = get_posts([
            'post_type' => $this->popup_post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        $choices = [];
        foreach ($posts as $post_id) {
            $choices[] = [
                'id' => (int) $post_id,
                'title' => get_the_title($post_id),
            ];
        }

        return $choices;
    }

    private function get_image_map_choices()
    {
        $posts = get_posts([
            'post_type' => $this->map_post_type,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'draft'],
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        $choices = [];
        foreach ($posts as $post_id) {
            $choices[] = [
                'id' => (int) $post_id,
                'title' => get_the_title($post_id),
            ];
        }

        return $choices;
    }

    public function render_image_map_block($attributes)
    {
        $map_id = isset($attributes['mapId']) ? absint($attributes['mapId']) : 0;

        if (! $map_id || get_post_type($map_id) !== $this->map_post_type) {
            return '';
        }

        $data = $this->get_image_map_data($map_id);

        if (empty($data['image']['url'])) {
            return '';
        }

        wp_enqueue_style('purim-popup-image-map');

        $image = $data['image'];
        $hotspots = $data['hotspots'];

        $ratio = 0;
        if (! empty($image['width']) && ! empty($image['height'])) {
            $ratio = ($image['height'] / $image['width']) * 100;
        }
        $ratio_attr = $ratio > 0 ? number_format($ratio, 6, '.', '') : '';

        ob_start();
    ?>
        <div class="purim-image-map" data-map-id="<?php echo esc_attr($map_id); ?>">
            <div class="purim-image-map__inner" <?php echo $ratio_attr !== '' ? 'style="--purim-map-ratio:' . esc_attr($ratio_attr) . '%;"' : ''; ?>>
                <img
                    src="<?php echo esc_url($image['url']); ?>"
                    alt="<?php echo esc_attr($image['alt']); ?>"
                    class="purim-image-map__image"
                    loading="lazy">
                <?php if (! empty($hotspots)): ?>
                    <?php foreach ($hotspots as $index => $hotspot):
                        $popup_id = isset($hotspot['popup_id']) ? (int) $hotspot['popup_id'] : 0;
                        if ($popup_id <= 0) {
                            continue;
                        }
                        $label = isset($hotspot['label']) ? $hotspot['label'] : '';
                        $x = isset($hotspot['x']) ? floatval($hotspot['x']) : 0;
                        $y = isset($hotspot['y']) ? floatval($hotspot['y']) : 0;
                        $tooltip = $label !== '' ? $label : sprintf(__('Punto %d', 'purim'), $index + 1);
                    ?>
                        <button
                            type="button"
                            class="purim-image-hotspot"
                            data-popup-id="<?php echo esc_attr($popup_id); ?>"
                            style="--purim-hotspot-left: <?php echo esc_attr($x); ?>%; --purim-hotspot-top: <?php echo esc_attr($y); ?>%;"
                            aria-label="<?php echo esc_attr($tooltip); ?>">
                            <span class="purim-image-hotspot__inner"></span>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
<?php

        return ob_get_clean();
    }
}

new Purim_Popup_Plugin();
