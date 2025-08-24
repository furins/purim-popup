<?php

/**
 * Plugin Name: Purim Popup
 * Description: Inserisce shortcode [purim_popup id="123" text="Apri popup"] che apre un popup con contenuto di una pagina della tassonomia purim-popup.
 * Version: 1.0
 * Author: Stefano
 */

if (! defined('ABSPATH')) {
    exit;
}

class Purim_Popup_Plugin
{

    public function __construct()
    {
        // Registrazione tassonomia
        add_action('init', [$this, 'register_popup_taxonomy']);

        // Shortcode
        add_shortcode('purim_popup', [$this, 'render_shortcode']);

        // AJAX handlers
        add_action('wp_ajax_purim_get_popup', [$this, 'ajax_get_popup']);
        add_action('wp_ajax_nopriv_purim_get_popup', [$this, 'ajax_get_popup']);

        // Script & CSS
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Opzionale: mostra lo shortcode nelle pagine della tassonomia
        add_filter('the_content', [$this, 'show_shortcode_in_popup_page']);
    }

    public function register_popup_taxonomy()
    {
        register_taxonomy(
            'purim-popup',   // slug unico della tassonomia
            'page',          // associata alle pagine
            [
                'labels' => [
                    'name'              => 'Popup',
                    'singular_name'     => 'Popup',
                    'search_items'      => 'Cerca Popup',
                    'all_items'         => 'Tutti i Popup',
                    'edit_item'         => 'Modifica Popup',
                    'update_item'       => 'Aggiorna Popup',
                    'add_new_item'      => 'Aggiungi nuovo Popup',
                    'new_item_name'     => 'Nuovo Popup',
                    'menu_name'         => 'Popup',
                ],
                'public'       => true,
                'show_ui'      => true,   // mostra la UI in admin
                'show_in_menu' => true,   // aggiunge la voce di menu laterale
                'show_admin_column' => true, // colonna nella lista pagine
                'rewrite'      => ['slug' => 'popup'],
                'hierarchical' => false,
                'show_in_rest' => true, // utile per l’editor a blocchi
            ]
        );
    }


    public function render_shortcode($atts)
    {
        $atts = shortcode_atts([
            'id' => '',
            'text' => 'Apri popup'
        ], $atts);

        if (empty($atts['id'])) {
            return '';
        }

        $id = esc_attr($atts['id']);
        $text = esc_html($atts['text']);

        return '<a href="#" class="purim-popup-trigger" data-popup-id="' . $id . '">' . $text . '</a>';
    }

    public function ajax_get_popup()
    {
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            wp_send_json_error('ID non valido');
        }

        $post = get_post($id);

        if (!$post) {
            wp_send_json_error('Popup non trovato');
        }

        // Verifica che appartenga alla tassonomia "purim-popup"
        if (!has_term('', 'purim-popup', $post)) {
            wp_send_json_error('Il contenuto non appartiene alla tassonomia purim-popup');
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
            'ajax_url' => admin_url('admin-ajax.php')
        ]);
    }

    public function show_shortcode_in_popup_page($content)
    {
        if (is_singular('page') && has_term('', 'purim-popup')) {
            $shortcode = '[purim_popup id="' . get_the_ID() . '" text="Apri popup"]';
            $content .= '<div class="purim-popup-shortcode"><strong>Shortcode per questo popup:</strong><br><code>' . esc_html($shortcode) . '</code></div>';
        }
        return $content;
    }
}

new Purim_Popup_Plugin();
