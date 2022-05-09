<?php

class RL_Extended_Filterable_Portfolio {
	
	protected static $args = null;
	
	function __construct() {
		add_filter('et_pb_module_shortcode_attributes', array($this, 'et_pb_module_shortcode_attributes'), 10, 3 );
		add_filter('et_module_shortcode_output', array($this, 'et_module_shortcode_output') );
		add_filter('et_builder_module_fields_et_pb_filterable_portfolio', array($this, 'get_fields'), 10, 1 );
		add_filter('et_builder_module_fields_unprocessed_et_pb_filterable_portfolio', array($this, 'get_fields'), 10, 1 );
		DPTB::$modules_slugs[] = 'et_pb_filterable_portfolio';
	}
	
	function whitelisted_fields($whitelisted_fields) {
		$whitelisted_fields[] = 'post_types';
		$whitelisted_fields[] = 'order';
		$whitelisted_fields[] = 'order_by';
		$whitelisted_fields[] = 'tax_query_op';
		return $whitelisted_fields;
		
	}
	
	function checkslug($slug) {
		return in_array($slug, array('et_pb_portfolio', 'et_pb_filterable_portfolio', 'et_pb_fullwidth_portfolio'));
	}
	
	function et_pb_module_shortcode_attributes($props, $atts, $slug) {
		if ($this->checkslug($slug)) {
			if (
				isset($atts['post_types']) ||
				isset($atts['order']) ||
				isset($atts['order_by']) ||
				isset($atts['tax_query_op'])
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
			if(isset($args['order'])) $query->set('order', $args['order']);
			if(isset($args['order_by'])) $query->set('orderby', $args['order_by']);
			//var_dump($query->get('tax_query') ); die('3333333');
			if(isset($args['tax_query_op'])) {
				$tax_querys = $query->get('tax_query');
				$op = $args['tax_query_op'];
				foreach ($tax_querys as $key => $tax_query) {
					$tax_query['operator'] = $op;
					$tax_querys[$key] = $tax_query;
				}
				$query->set('tax_query', $tax_querys);
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
				'default'			=> 'project'
		);
		$fields['order'] = array(
				'label'             => esc_html__( 'Order', 'et_builder' ),
				'type'              => 'select',
				'option_category'   => 'configuration',
				'options'         	=> array(
						'DESC'  		=> esc_html__( 'Descending', 'et_builder' ),
						'ASC' 			=> esc_html__( 'Ascending', 'et_builder' ),
				),
				'description'       => esc_html__( 'Order of items', 'et_builder' ),
				'toggle_slug'       => 'main_content',
				'default'			=> 'DESC',
		);
		$fields['order_by'] = array(
				'label'             => esc_html__( 'Sort retrieved projects by parameter', 'et_builder' ),
				'type'              => 'select',
				'option_category'   => 'configuration',
				'options'         => array(
						'ID'  => esc_html__( 'Order by post id. Note the capitalization.', 'et_builder' ),
						'author'  => esc_html__( 'Order by author.', 'et_builder' ),
						'title'  => esc_html__( 'Order by title.', 'et_builder' ),
						'name'  => esc_html__( 'Order by post name (post slug).', 'et_builder' ),
						'type'  => esc_html__( 'Order by post type (available since version 4.0).', 'et_builder' ),
						'date'  => esc_html__( 'Order by date.', 'et_builder' ),
						'modified'  => esc_html__( 'Order by last modified date.', 'et_builder' ),
						'parent'  => esc_html__( 'Order by post/page parent id.', 'et_builder' ),
						'rand'  => esc_html__( 'Random order.', 'et_builder' ),
						'comment_count'  => esc_html__( 'Order by number of comments (available since version 2.9).', 'et_builder' ),
						'relevance'  => esc_html__( 'Order by search terms.', 'et_builder' )
				),
				'description'       => esc_html__( 'Order of items by selected option', 'et_builder' ),
				'toggle_slug'       => 'main_content',
				'default'				=> 'date',
		);
		$fields['tax_query_op'] = array(
				'label'             => esc_html__( 'Tax query operator', 'et_builder' ),
				'type'              => 'select',
				'option_category'   => 'configuration',
				'options'         => array(
						'OR'  => esc_html__( 'Operator OR', 'et_builder' ),
						'AND' => esc_html__( 'Operator AND', 'et_builder' ),
				),
				'description'       => esc_html__( 'Query tax usign selected operator', 'et_builder' ),
				'toggle_slug'       => 'main_content',
				'default'				=> 'OR',
		);
		return $fields;
	}
}

new RL_Extended_Filterable_Portfolio();