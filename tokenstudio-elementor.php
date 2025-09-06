<?php

/**
 * Plugin Name: Token Studio → Elementor Sync
 * Plugin URI:  https://localhost96.net
 * Description: (Beta) Sync design tokens with Elementor global styles. Imports reference colors and system typography. Resolves references to literal values. Updates the active Elementor Kit.
 * Version:     0.1.0
 * Author:      Konrad Krawczyk
 * Author URI:  https://localhost96.net
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tokenstudio-elementor
 */

// Add submenu under Elementor
add_action('admin_menu', function () {
    add_submenu_page(
        'elementor',
        'Token Studio Sync',
        'Token Studio Sync',
        'manage_options',
        'token-studio-sync',
        'render_tokenstudio_page'
    );
}, 99);

// Admin page renderer
function render_tokenstudio_page() {
    if (isset($_POST['reference_json'], $_POST['system_json']) && check_admin_referer('save_tokenstudio')) {
        update_option('tokenstudio_reference_json', wp_unslash($_POST['reference_json']));
        update_option('tokenstudio_system_json', wp_unslash($_POST['system_json']));
        update_option('tokenstudio_reference_key', sanitize_text_field($_POST['reference_key'] ?? ''));
        update_option('tokenstudio_system_key', sanitize_text_field($_POST['system_key'] ?? ''));

        $refJson = get_option('tokenstudio_reference_json', '{}');
        $sysJson = get_option('tokenstudio_system_json', '{}');
        $refKey  = get_option('tokenstudio_reference_key', '');
        $sysKey  = get_option('tokenstudio_system_key', '');

        $res = sync_tokenstudio_to_elementor($refJson, $sysJson, $refKey, $sysKey);
        if (is_wp_error($res)) {
            echo '<div class="error"><p>❌ ' . esc_html($res->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="updated"><p>✅ Tokens saved and synced into Elementor Kit.</p></div>';
        }
    }

    $refVal = get_option('tokenstudio_reference_json', '');
    $sysVal = get_option('tokenstudio_system_json', '');
    $refKey = get_option('tokenstudio_reference_key', '');
    $sysKey = get_option('tokenstudio_system_key', '');
    ?>
    <div class="wrap">
        <h1>Token Studio → Elementor Sync</h1>
        <form method="post">
            <?php wp_nonce_field('save_tokenstudio'); ?>

            <h2>Reference JSON</h2>
            <textarea id="reference_json" name="reference_json" rows="15" style="width:100%;font-family:monospace;"><?php 
                echo esc_textarea($refVal); 
            ?></textarea>
            <p>
                <label>Root key: 
                    <select name="reference_key" id="reference_key"></select>
                </label>
            </p>

            <h2>System JSON</h2>
            <textarea id="system_json" name="system_json" rows="15" style="width:100%;font-family:monospace;"><?php 
                echo esc_textarea($sysVal); 
            ?></textarea>
            <p>
                <label>Root key: 
                    <select name="system_key" id="system_key"></select>
                </label>
            </p>

            <p><input type="submit" class="button-primary" value="Save & Sync"></p>
        </form>
    </div>

    <script>
    function populateKeys(textareaId, selectId, currentValue) {
        let textarea = document.getElementById(textareaId);
        let select = document.getElementById(selectId);

        function updateKeys() {
            let val = textarea.value.trim();
            let keys = [];
            try {
                let parsed = JSON.parse(val);
                if (typeof parsed === 'object' && parsed !== null) {
                    keys = Object.keys(parsed);
                }
            } catch(e) {}

            select.innerHTML = '';
            if (keys.length) {
                keys.forEach(k => {
                    let opt = document.createElement('option');
                    opt.value = k;
                    opt.textContent = k;
                    if (k === currentValue) opt.selected = true;
                    select.appendChild(opt);
                });
                if (!currentValue && keys.length === 1) {
                    select.value = keys[0]; // auto-select if only one
                }
            }
        }

        textarea.addEventListener('input', updateKeys);
        updateKeys();
    }

    document.addEventListener('DOMContentLoaded', function() {
        populateKeys('reference_json', 'reference_key', '<?php echo esc_js($refKey); ?>');
        populateKeys('system_json', 'system_key', '<?php echo esc_js($sysKey); ?>');
    });
    </script>
    <?php
}

// --- Helpers ---

// Flatten reference tokens into dict: "reference.blue500" => "#hex"
function build_reference_dict($array, $prefix = '') {
    $dict = [];
    foreach ($array as $k => $v) {
        $path = $prefix ? $prefix . '.' . $k : $k;
        if (is_array($v) && isset($v['$type']) && isset($v['$value'])) {
            $dict[$path] = $v['$value'];
        } elseif (is_array($v)) {
            $dict = array_merge($dict, build_reference_dict($v, $path));
        }
    }
    return $dict;
}

// Resolve "{reference.something}" → literal
function resolve_value($raw, $refDict) {
    if (is_string($raw) && preg_match('/^{(.+)}$/', $raw, $m)) {
        return $refDict[$m[1]] ?? null;
    }
    return $raw;
}

// Convert system.typography → Elementor presets
function system_typography_to_elementor($systemTokens, $refDict) {
    if (!isset($systemTokens['typography'])) return [];
    $presets = [];
    foreach ($systemTokens['typography'] as $name => $rules) {
        $preset = [
            '_id' => substr(md5($name), 0, 7),
            'title' => ucfirst($name),
            'typography_typography' => 'custom'
        ];
        foreach ($rules as $prop => $def) {
            $val = resolve_value($def['$value'] ?? null, $refDict);
            if (!$val) continue;
            switch ($prop) {
                case 'fontSize':
                    $preset['typography_font_size'] = ['unit' => 'px', 'size' => (float) filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)];
                    break;
                case 'fontFamily': $preset['typography_font_family'] = $val; break;
                case 'fontWeight': $preset['typography_font_weight'] = (int) $val; break;
                case 'lineHeight':
                    if (str_contains($val, '%')) {
                        $preset['typography_line_height'] = ['unit'=>'percent','size'=>(int)$val];
                    } else {
                        $preset['typography_line_height'] = ['unit'=>'px','size'=>(float)$val];
                    }
                    break;
                case 'letterSpacing':
                    if (str_contains($val, '%')) {
                        $preset['typography_letter_spacing'] = ['unit'=>'percent','size'=>(int)$val];
                    } else {
                        $preset['typography_letter_spacing'] = ['unit'=>'px','size'=>(float)$val];
                    }
                    break;
                case 'color': $preset['color'] = $val; break;
            }
        }
        $presets[] = $preset;
    }
    return $presets;
}

// Main sync
function sync_tokenstudio_to_elementor($refJson, $sysJson, $refKey = '', $sysKey = '') {
    if (!class_exists('\Elementor\Plugin')) {
        return new WP_Error('no_elementor', 'Elementor not active.');
    }

    $refs = json_decode($refJson, true);
    $sys  = json_decode($sysJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'Invalid JSON: ' . json_last_error_msg());
    }

    if ($refKey && isset($refs[$refKey])) $refs = $refs[$refKey];
    if ($sysKey && isset($sys[$sysKey])) $sys = $sys[$sysKey];

    $refDict = build_reference_dict($refs);

    // Colors: only from reference tokens
    $colors = [];
    foreach ($refDict as $path => $val) {
        if (str_contains($path, '.') && isset($refs)) {
            // crude check: only keep type=color
            $parts = explode('.', $path);
            $last  = end($parts);
            if (isset($refs[$last]['$type']) && $refs[$last]['$type'] === 'color') {
                $colors[$last] = $val;
            }
        }
    }

    // Typography: from system.typography resolved via $refDict
    $typographyPresets = system_typography_to_elementor($sys ?? [], $refDict);

    $kit_id   = \Elementor\Plugin::$instance->kits_manager->get_active_id();
    $kit      = \Elementor\Plugin::$instance->documents->get($kit_id, false);
    $settings = $kit->get_settings();

    // Merge colors
    if (!isset($settings['custom_colors'])) $settings['custom_colors'] = [];
    $existingColors = [];
    foreach ($settings['custom_colors'] as $i => $c) {
        $existingColors[strtolower($c['title'])] = $i;
    }
    foreach ($colors as $name => $hex) {
        $title = ucfirst($name);
        if (isset($existingColors[strtolower($title)])) {
            $settings['custom_colors'][$existingColors[strtolower($title)]]['color'] = $hex;
        } else {
            $settings['custom_colors'][] = [
                '_id'   => substr(md5($name.$hex), 0, 7),
                'title' => $title,
                'color' => $hex
            ];
        }
    }

    // Merge typography
    if (!isset($settings['custom_typography'])) $settings['custom_typography'] = [];
    $existingTypography = [];
    foreach ($settings['custom_typography'] as $i => $t) {
        $existingTypography[strtolower($t['title'])] = $i;
    }
    foreach ($typographyPresets as $preset) {
        $title = strtolower($preset['title']);
        if (isset($existingTypography[$title])) {
            $settings['custom_typography'][$existingTypography[$title]] = array_replace(
                $settings['custom_typography'][$existingTypography[$title]],
                $preset
            );
        } else {
            $settings['custom_typography'][] = $preset;
        }
    }

    $kit->save(['settings' => $settings]);
    return true;
}
