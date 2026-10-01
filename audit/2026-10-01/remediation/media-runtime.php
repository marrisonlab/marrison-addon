<?php
/** Recheck patched Image Sizes/Cursor using WordPress and rollback. */
ob_start();
require dirname(__DIR__) . '/wp-runtime-probe.php';
ob_end_clean();
$results = [];
function audit_actual_module($class) {
    foreach ($GLOBALS['wp_filter'] as $hook) {
        foreach ($hook->callbacks ?? [] as $callbacks) {
            foreach ($callbacks as $callback) {
                $fn = $callback['function'];
                if (is_array($fn) && is_object($fn[0]) && get_class($fn[0]) === $class) return $fn[0];
            }
        }
    }
    throw new RuntimeException('Module instance missing: ' . $class);
}
$cursor = audit_actual_module('Marrison_Addon_Cursor');
foreach (['#ff0000', 'rgb(255,0,0)', 'rgba(255,0,0,0.5)', 'invalid'] as $color) {
    $sanitized = $cursor->sanitize_settings(['hover_color'=>$color]);
    $results['cursor_hover_colors'][] = ['input'=>$color, 'saved'=>$sanitized['hover_color'], 'preserved'=>$color === $sanitized['hover_color']];
}

$module = audit_actual_module('Marrison_Addon_Image_Sizes');
$dir = wp_normalize_path(__DIR__) . '/images';
wp_mkdir_p($dir);
$file = $dir . '/metadata-source.png';
$image = imagecreatetruecolor(320, 240);
$color = imagecolorallocate($image, 40, 130, 220);
imagefill($image, 0, 0, $color);
imagepng($image, $file);
imagedestroy($image);
$id = wp_insert_attachment(['post_title'=>'Audit phase2 image metadata', 'post_mime_type'=>'image/png', 'post_status'=>'inherit'], $file);
update_attached_file($id, $file);
// Fixture files live outside uploads. Map only this disposable attachment to its real file.
add_filter('get_attached_file', function($attached, $attachment) use ($id, $file) { return $attachment === $id ? $file : $attached; }, 10, 2);
$definition = ['slug'=>'audit_small','width'=>120,'height'=>90,'crop'=>false,'webp'=>true,'avif'=>true,'upscale'=>false];
add_filter('pre_option_marrison_addon_image_sizes', function() use ($definition) { return [$definition]; });
add_filter('pre_option_marrison_addon_disabled_sizes', function() { return []; });
$metadata = ['file'=>'metadata-source.png','width'=>320,'height'=>240,'sizes'=>[]];
$generated = $module->add_modern_formats_to_generated_metadata($metadata, $id, 'update');
wp_update_attachment_metadata($id,$generated);
$results['image_size_lookup']=image_get_intermediate_size($id,'audit_small');
if(empty($results['image_size_lookup'])||$results['image_size_lookup']['width']!==120||$results['image_size_lookup']['height']!==90){throw new RuntimeException('Core image size lookup failed');}
$definitions = new ReflectionMethod($module, 'get_image_size_generation_definitions');
$definitions->setAccessible(true);
$results['image_debug'] = ['is_image'=>wp_attachment_is_image($id), 'attached_file'=>get_attached_file($id), 'attached_file_exists'=>file_exists(get_attached_file($id)), 'definitions'=>$definitions->invoke($module)];
$files = [];
foreach (glob($dir . '/*') as $path) {
    $dimensions = @getimagesize($path);
    $files[] = ['name'=>basename($path), 'width'=>$dimensions[0] ?? null, 'height'=>$dimensions[1] ?? null];
}
$results['image_size_missing_metadata'] = ['attachment_id'=>$id, 'requested_size'=>[120,90], 'returned_metadata'=>$generated, 'physical_files'=>$files];

// The same pipeline when an intermediate image is present in core metadata.
$method = new ReflectionMethod($module, 'generate_single_size');
$method->setAccessible(true);
$small = $method->invoke($module, $file, 'audit_small', $definition);
if (!is_wp_error($small)) {
    $dimensions = getimagesize($small);
    $metadata['sizes']['audit_small'] = ['file'=>basename($small),'width'=>$dimensions[0],'height'=>$dimensions[1],'mime-type'=>'image/png'];
    $generatedExisting = $module->add_modern_formats_to_generated_metadata($metadata, $id, 'update');
    $results['image_size_existing_metadata'] = $generatedExisting['sizes']['audit_small'];
}
$results['plugin_errors'] = $GLOBALS['audit_errors'];
file_put_contents(__DIR__ . '/media-runtime-results.json', json_encode($results, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo json_encode($results, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";

