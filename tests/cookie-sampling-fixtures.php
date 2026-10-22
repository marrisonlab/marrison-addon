<?php
/** Native WordPress service shapes for representative-route integration tests. */
$fixture_sample_posts = array(); $fixture_sample_meta = array(); $fixture_sample_terms = array();
function get_post_types($args = array(), $output = 'names') {
    global $fixture_sample_posts;
    $types = array();
    foreach ($fixture_sample_posts as $post) { $types[$post->post_type] = (object) array('name' => $post->post_type, 'label' => $post->post_type); }
    return $types;
}
function get_posts($args = array()) {
    global $fixture_sample_posts;
    $ids = array(); foreach ($fixture_sample_posts as $post) { if ($post->post_type === $args['post_type'] && (!isset($args['post_status']) || $post->post_status === $args['post_status'])) { $ids[] = $post->ID; } }
    rsort($ids); return array_slice($ids, 0, $args['numberposts'] ?? 1);
}
function get_permalink($id) { global $fixture_sample_posts; return $fixture_sample_posts[is_object($id) ? $id->ID : $id]->url ?? false; }
function get_post_type_archive_link($type) { return $type === 'annunci-lavoro' ? home_url('/annunci/') : false; }
function url_to_postid($url) {
    global $fixture_sample_posts;
    foreach ($fixture_sample_posts as $post) { if ($post->url === $url) { return $post->ID; } }
    return 0;
}
function get_post($id) { global $fixture_sample_posts; return $fixture_sample_posts[$id] ?? null; }
function get_post_meta($id, $key = '', $single = false) {
    global $fixture_sample_meta;
    if ($key === '') { return array_map(function($value) { return array($value); }, $fixture_sample_meta[$id] ?? array()); }
    return $fixture_sample_meta[$id][$key] ?? '';
}
function get_page_template_slug($id) { return get_post_meta($id, '_wp_page_template', true); }
function locate_template($names, $load = false, $once = true) {
    foreach ($names as $name) { if (in_array($name, array('single.php', 'taxonomy.php', 'archive.php', 'taxonomy-topic-b.php'), true)) { return '/theme/' . $name; } }
    return '';
}
function get_taxonomies($args = array(), $output = 'names') {
    global $fixture_sample_terms;
    return $fixture_sample_terms ? array('topic' => (object) array('label' => 'Argomenti')) : array();
}
function get_terms($args) { global $fixture_sample_terms; return array_slice(array_values($fixture_sample_terms), 0, $args['number'] ?? 1); }
function get_term_link($term) { return $term->url; }
function get_term_by($field, $value, $taxonomy) {
    global $fixture_sample_terms;
    foreach ($fixture_sample_terms as $term) { if ($term->slug === $value && $term->taxonomy === $taxonomy) { return $term; } }
    return false;
}
