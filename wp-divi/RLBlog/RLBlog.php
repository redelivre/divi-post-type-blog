<?php

class RLBlog {
	
	protected static $args = null;
	
	function __construct() {
		add_filter('et_pb_module_shortcode_attributes', array($this, 'et_pb_module_shortcode_attributes'), 10, 3 );
		add_filter('et_module_shortcode_output', array($this, 'et_module_shortcode_output') );
		add_filter('et_builder_module_fields_et_pb_blog', array($this, 'get_fields'), 10, 1 );
		add_filter('et_builder_module_fields_unprocessed_et_pb_blog', array($this, 'get_fields'), 10, 1 );
		DPTB::$modules_slugs[] = 'et_pb_blog';
	}
	
	function whitelisted_fields($whitelisted_fields) {
		$whitelisted_fields[] = 'post_types';
		$whitelisted_fields[] = 'include_custom_terms';
		return $whitelisted_fields;
		
	}
	
	function checkslug($slug) {
		return in_array($slug, array( 'et_pb_blog', 'et_pb_fullwidth_portfolio' ));
	}
	
	function et_pb_module_shortcode_attributes($props, $atts, $slug) {
		if ($this->checkslug($slug)) {
			if (
				isset($atts['post_types'])
				|| isset($atts['include_custom_terms'])
			) {
				add_action('pre_get_posts', array($this, 'pre_get_posts'));
				self::$args = $atts;
			}
		}
		return $props;
	}
	
	function et_module_shortcode_output($content) {
		if(!is_null(self::$args)) {
			$this->remove_post_filters();
			self::$args = null;
		}
		return $content;
	}
	
	/**
	 * Remove filter after module render
	 */
	function remove_post_filters() {
		remove_action('pre_get_posts', array($this, 'pre_get_posts'));
	}
	
	/**
	 * 
	 * @param WP_Query $query
	 */
	function pre_get_posts($query) {
		$args = self::$args;
		if(!is_null($args)) {
			if(isset($args['post_types'])) $query->set('post_type', $args['post_types']);
			if(isset($args['include_custom_terms'])) {
				$cats = $query->get('cat');
				$query->set('cat', '');
				$tax_querys = $query->get('tax_query');
				$new_tax_query = [
						[
								'taxonomy' => 'projeto',
								'field'    => 'id',
								'operator' => 'IN',
								'terms'    => explode(",", $args['include_custom_terms']),
						]
				];
				if(	( is_array($tax_querys) && !empty($tax_querys))
						|| ( is_array($cats) && !empty($cats)) ) {
						$new_tax_query['operator'] = 'AND';
				}
				if( !empty($cats) )  {
					$new_tax_query[] = 	[
							'taxonomy' => 'category',
							'field'    => 'id',
							'operator' => 'IN',
							'terms'    => $cats,
					];
				}
				if( is_array($tax_querys) ) {
					$new_tax_query[] = $tax_querys;
				}
				//var_dump($new_tax_query);var_dump($cats);
				$query->set('tax_query', $new_tax_query);
			}
		}
		//return $query;
	}
	
	public static function get_post_types($type = false) {
		
		$options = get_post_types(array('public' => true, 'publicly_queryable' => true, '_builtin' => true));
		$options = array_merge(get_post_types(array('public' => true, 'publicly_queryable' => true, '_builtin' => false)), $options);
		if( $type !== false ) {
			return $options[$type];
		}
		return $options;
	}
	
	function get_fields($fields) {
		$fields['post_types'] = array(
				'label'             => esc_html__( 'Post Types', 'et_builder' ),
				'type'              => 'select',
				'option_category'   => 'configuration',
				'options'           => self::get_post_types(),
				'description'       => esc_html__( 'List of post types to show on query', 'et_builder' ),
				'toggle_slug'       => 'main_content',
				'default'			=> 'post'
		);
		if(taxonomy_exists('projeto')) {
			$fields['include_custom_terms'] = array(
					'label'            => esc_html__( 'Include Taxonomies', 'et_builder' ),
					'type'             => 'categories',
					'meta_categories'  => array(
							'all'     => esc_html__( 'All Taxonomies', 'et_builder' ),
							'current' => esc_html__( 'Current Taxonomy', 'et_builder' ),
					),
					'option_category'   => 'basic_option',
					'renderer_options'  => array(
							'use_terms' => true,
							'term_name' => 'projeto'
					),
					'description'      => esc_html__( 'Choose which taxonomies you would like to include in the feed.', 'et_builder' ),
					'toggle_slug'      => 'main_content',
					'computed_affects' => array(
							'__posts',
					),
			);
		}
		return $fields;
	}
}

new RLBlog();