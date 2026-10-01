<?php
// Read-only Elementor runtime probes. The loaded WordPress request rolls back DB changes.
require dirname(__DIR__) . '/wp-runtime-probe.php';

function probe_value($label, $value) {
    echo "PROBE " . $label . " " . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

probe_value('mbstring', function_exists('mb_strlen'));
$manager = \Elementor\Plugin::$instance->widgets_manager;
$types = $manager->get_widget_types();
probe_value('widgets', array_values(array_intersect([
    'text-editor', 'jet-listing-dynamic-field', 'jet-listing-grid', 'ticker', 'marrison_steps',
], array_keys($types))));

$checks = [];
foreach ([
    'text-editor' => 'Marrison_Addon_Read_More',
    'jet-listing-dynamic-field' => 'Marrison_Addon_Read_More',
    'jet-listing-grid' => 'Marrison_Addon_Listing_Title',
    'ticker' => 'Marrison_Addon_Ticker',
    'marrison_steps' => 'Marrison_Addon_Steps',
] as $widget_name => $module_class) {
    $widget = $types[$widget_name] ?? null;
    $checks[$widget_name] = [
        'registered' => is_object($widget),
        'class' => is_object($widget) ? get_class($widget) : null,
        'module_loaded' => class_exists($module_class, false),
    ];
    if (is_object($widget) && method_exists($widget, 'get_controls')) {
        $controls = $widget->get_controls();
        $checks[$widget_name]['control_count'] = count($controls);
        $checks[$widget_name]['marrison_controls'] = array_values(array_filter(array_keys($controls), function ($key) {
            return strpos($key, 'marrison_') === 0;
        }));
    }
}
probe_value('widget_checks', $checks);

// Header animation selection probe using the actual module method through reflection.
$header = new ReflectionClass('Marrison_Addon_Header_Animations');
$header_object = $header->newInstanceWithoutConstructor();
$selected = $header->getMethod('get_selected_animation');
$selected->setAccessible(true);
probe_value('header_desktop_mobile_selection', [
    'desktop' => $selected->invoke($header_object, ['_animation' => 'marrisonLiftSoft', '_animation_tablet' => 'marrisonDropSoft', '_animation_mobile' => 'marrisonFocusIn']),
    'desktop_empty_tablet_mobile' => $selected->invoke($header_object, ['_animation' => '', '_animation_tablet' => 'marrisonDropSoft', '_animation_mobile' => 'marrisonFocusIn']),
]);

// Read More and Listing Title callback probes with minimal widget doubles.
class Audit_Elementor_Widget_Double {
    private $name; private $settings;
    public function __construct($name, array $settings) { $this->name = $name; $this->settings = $settings; }
    public function get_name() { return $this->name; }
    public function get_settings_for_display() { return $this->settings; }
    public function get_id() { return 'audit-widget'; }
    public function add_render_attribute($key, $attr, $value) { }
    public function get_render_attribute_string($key) { return 'class="marrison-listing-grid-title"'; }
}
$read_more = new Marrison_Addon_Read_More();
$rm = $read_more->render_read_more('<div class="elementor-widget-container"><p>Contenuto di test sufficientemente lungo</p></div>', new Audit_Elementor_Widget_Double('text-editor', [
    'marrison_read_more_enabled' => 'yes', 'marrison_read_more_lines' => 2,
    'marrison_read_more_label_more' => 'Altro', 'marrison_read_more_label_less' => 'Meno',
]));
probe_value('read_more_render', [
    'has_root' => strpos($rm, 'data-marrison-read-more=') !== false,
    'has_button' => strpos($rm, 'marrison-read-more-toggle') !== false,
]);

$listing = new Marrison_Addon_Listing_Title();
$lt = $listing->prepend_listing_title('<div class="jet-listing-grid__items"></div>', new Audit_Elementor_Widget_Double('jet-listing-grid', [
    'marrison_listing_title_enable' => 'yes', 'marrison_listing_title_text' => 'Titolo audit',
    'marrison_listing_title_html_tag' => 'h2', 'marrison_listing_title_link' => [],
]));
probe_value('listing_title_render', [
    'has_title' => strpos($lt, 'Titolo audit') !== false,
    'before_grid' => strpos($lt, 'Titolo audit') < strpos($lt, 'jet-listing-grid__items'),
]);

echo "AUDIT_RUNTIME_DONE\n";
