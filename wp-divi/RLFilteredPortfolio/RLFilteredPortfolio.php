<?php

class ET_Builder_Module_Extended_Filterable_Portfolio extends ET_Builder_Module_Filterable_Portfolio {
	
	function __construct() {
		parent::__construct();
		remove_shortcode( 'et_pb_filterable_portfolio' );
		add_shortcode( 'et_pb_filterable_portfolio', array($this, '_shortcode_callback') );
	}
	
	function init() {
		parent::init();
		$this->whitelisted_fields[] = 'post_types';
		$this->whitelisted_fields[] = 'order';
		$this->whitelisted_fields[] = 'orderBy';
		$this->whitelisted_fields[] = 'tax_query_op';
		
		$this->fields_defaults['post_types'] = array('project');
		$this->fields_defaults['order'] = array('DESC');
		$this->fields_defaults['orderBy'] = array('date');
		$this->fields_defaults['tax_query_op'] = array('OR');
	}
	
	function get_post_types($type = false) {
		
		$options = get_post_types(array('public' => true, 'publicly_queryable' => true, '_builtin' => true));
		$options = array_merge(get_post_types(array('public' => true, 'publicly_queryable' => true, '_builtin' => false)), $options);
		if( $type !== false ) {
			return $options[$type];
		}
		return $options;
	}
	
	function get_fields() {
		$fields = parent::get_fields();
		$fields['__projects'] = array(
				'type'                => 'computed',
				'computed_callback'   => array( 'ET_Builder_Module_Extended_Filterable_Portfolio', 'get_portfolio_item' ),
				'computed_depends_on' => array(
					'show_pagination',
					'posts_number',
					'include_categories',
					'fullwidth',
				),
		);
		$fields['__project_terms'] = array(
				'type'                => 'computed',
				'computed_callback'   => array( 'ET_Builder_Module_Extended_Filterable_Portfolio', 'get_portfolio_terms' ),
				'computed_depends_on' => array(
						'include_categories',
				),
		);
		$fields['post_types'] = array(
				'label'             => esc_html__( 'Post Types', 'et_builder' ),
				'type'              => 'select',
				'option_category'   => 'configuration',
				'options'           => $this->get_post_types(),
				'description'       => esc_html__( 'List of post types to show on query', 'et_builder' ),
				'toggle_slug'       => 'main_content',
		);
		$fields['order'] = array(
				'label'             => esc_html__( 'Order', 'et_builder' ),
				'type'              => 'select',
				'option_category'   => 'configuration',
				'options'         => array(
						'DESC'  => esc_html__( 'Descending', 'et_builder' ),
						'ASC' => esc_html__( 'Ascending', 'et_builder' ),
				),
				'description'       => esc_html__( 'Order of items', 'et_builder' ),
				'toggle_slug'       => 'main_content',
		);
		$fields['orderBy'] = array(
				'label'             => esc_html__( 'Sort retrieved projects by parameter', 'et_builder' ),
				'type'              => 'select',
				'option_category'   => 'configuration',
				'options'         => array(
						'ID'  => esc_html__( 'Order by post id. Note the capitalization.', 'et_builder' ),
						'author'  => esc_html__( 'Order by author.', 'et_builder' ),
						'title'  => esc_html__( 'Order by title.', 'et_builder' ),
						'name'  => esc_html__( 'Order by post name (post slug).', 'et_builder' ),
						//'type'  => esc_html__( 'Order by post type (available since version 4.0).', 'et_builder' ),
						'date'  => esc_html__( 'Order by date.', 'et_builder' ),
						'modified'  => esc_html__( 'Order by last modified date.', 'et_builder' ),
						'parent'  => esc_html__( 'Order by post/page parent id.', 'et_builder' ),
						'rand'  => esc_html__( 'Random order.', 'et_builder' ),
						'comment_count'  => esc_html__( 'Order by number of comments (available since version 2.9).', 'et_builder' ),
						'relevance'  => esc_html__( 'Order by search terms.', 'et_builder' )
				),
				'description'       => esc_html__( 'Order of items by selected option', 'et_builder' ),
				'toggle_slug'       => 'main_content',
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
		);
		return $fields;
	}
	
	static function get_portfolio_terms( $args = array(), $conditional_tags = array(), $current_page = array() ) {
		$portfolio = self::get_portfolio_item( $args, $conditional_tags, $current_page );
		$terms = array();
		
		if ( ! empty( $portfolio->posts ) ) {
			foreach ( $portfolio->posts as $post ) {
				if ( ! empty( $post->post_categories ) ) {
					foreach ( $post->post_categories as $category ) {
						$terms[ $category['slug'] ] = $category;
					}
				}
			}
		}
		
		return $terms;
	}
	
	/**
	 * 
	 * @param array $args
	 * @param array $conditional_tags
	 * @param array $current_page
	 * @return WP_Query|string[]
	 */
	static function get_portfolio_item( $args = array(), $conditional_tags = array(), $current_page = array() ) {
		global $et_fb_processing_shortcode_object;
		$global_processing_original_value = $et_fb_processing_shortcode_object;
		
		$defaults = array(
				'show_pagination'    => 'on',
				'include_categories' => '',
				'fullwidth'          => 'on',
				'nopaging'           => true,
		);
		
		$query_args = array();
		if(isset($args['post_type']) ) $query_args['post_type'] = $args['post_type'];
		if(isset($args['order']) ) $query_args['order'] = $args['order'];
		if(isset($args['orderBy']) ) $query_args['orderBy'] = $args['orderBy'];
		
		$args = wp_parse_args( $args, $defaults );
		
		$include_categories = self::filter_invalid_term_ids( explode( ',', $args['include_categories'] ), 'project_category' );
		
		if ( ! empty( $include_categories ) ) {
			$query_args['tax_query'] = array(
					array(
							'taxonomy' => 'project_category',
							'field'    => 'id',
							'terms'    => $include_categories,
							'operator' => $args['tax_query_op'],
					)
			);
		}
		
		$default_query_args = array(
				'post_type'   => 'project',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'order' => 'DESC',
				'orderBy' => 'date'
		);
		
		$query_args = wp_parse_args( $query_args, $default_query_args );
		
		// Get portfolio query
		$query = new WP_Query( $query_args );
		
		// Format portfolio output, and add supplementary data
		$width     = 'on' === $args['fullwidth'] ?  1080 : 400;
		$width     = (int) apply_filters( 'et_pb_portfolio_image_width', $width );
		$height    = 'on' === $args['fullwidth'] ?  9999 : 284;
		$height    = (int) apply_filters( 'et_pb_portfolio_image_height', $height );
		$classtext = 'on' === $args['fullwidth'] ? 'et_pb_post_main_image' : '';
		$titletext = get_the_title();
		
		// Loop portfolio item and add supplementary data
		if( $query->have_posts() ) {
			$post_index = 0;
			while ( $query->have_posts() ) {
				$query->the_post();
				
				$categories = array();
				
				$category_classes = array( 'et_pb_portfolio_item' );
				
				if ( 'on' !== $args['fullwidth'] ) {
					$category_classes[] = 'et_pb_grid_item';
				}
				
				$categories_object = get_the_terms( get_the_ID(), 'project_category' );
				if ( ! empty( $categories_object ) ) {
					foreach ( $categories_object as $category ) {
						// Update category classes which will be used for post_class
						$category_classes[] = 'project_category_' . urldecode( $category->slug );
						
						// Push category data
						$categories[] = array(
								'id'        => $category->term_id,
								'slug'      => $category->slug,
								'label'     => $category->name,
								'permalink' => get_term_link( $category ),
						);
					}
				}
				
				// need to disable processnig to make sure get_thumbnail() doesn't generate errors
				$et_fb_processing_shortcode_object = false;
				
				// Get thumbnail
				$thumbnail = get_thumbnail( $width, $height, $classtext, $titletext, $titletext, false, 'Blogimage' );
				
				$et_fb_processing_shortcode_object = $global_processing_original_value;
				
				// Append value to query post
				$query->posts[ $post_index ]->post_permalink 	= get_permalink();
				$query->posts[ $post_index ]->post_thumbnail 	= print_thumbnail( $thumbnail['thumb'], $thumbnail['use_timthumb'], $titletext, $width, $height, '', false, true );
				$query->posts[ $post_index ]->post_categories 	= $categories;
				$query->posts[ $post_index ]->post_class_name 	= array_merge( get_post_class( '', get_the_ID() ), $category_classes );
				
				// Append category classes
				$category_classes = implode( ' ', $category_classes );
				
				$post_index++;
			}
		} else if ( wp_doing_ajax() || et_core_is_fb_enabled() ) {
			// This is for the VB
			$query = array( 'posts' => self::get_no_results_template() );
		}
		
		wp_reset_postdata();
		
		return $query;
	}
	
	function render( $attrs, $content = null, $render_slug ) {
		$fullwidth                       = $this->props['fullwidth'];
		$posts_number                    = $this->props['posts_number'];
		$include_categories              = $this->props['include_categories'];
		$show_title                      = $this->props['show_title'];
		$show_categories                 = $this->props['show_categories'];
		$show_pagination                 = $this->props['show_pagination'];
		$background_layout               = $this->props['background_layout'];
		$background_layout_hover         = et_pb_hover_options()->get_value( 'background_layout', $this->props, 'light' );
		$background_layout_hover_enabled = et_pb_hover_options()->is_enabled( 'background_layout', $this->props );
		$hover_icon                      = $this->props['hover_icon'];
		$zoom_icon_color                 = $this->props['zoom_icon_color'];
		$hover_overlay_color             = $this->props['hover_overlay_color'];
		$header_level                    = $this->props['title_level'];
		$post_type						 = $this->props['post_types'];
		$order							 = $this->props['order'];
		$orderBy						 = $this->props['orderBy'];
		$tax_query_op					 = $this->props['tax_query_op'];
		
		wp_enqueue_script( 'hashchange' );
		
		if ( '' !== $zoom_icon_color ) {
			ET_Builder_Element::set_style( $render_slug, array(
					'selector'    => '%%order_class%% .et_overlay:before',
					'declaration' => sprintf(
							'color: %1$s !important;',
							esc_html( $zoom_icon_color )
							),
			) );
		}
		
		if ( '' !== $hover_overlay_color ) {
			ET_Builder_Element::set_style( $render_slug, array(
					'selector'    => '%%order_class%% .et_overlay',
					'declaration' => sprintf(
							'background-color: %1$s;
					border-color: %1$s;',
							esc_html( $hover_overlay_color )
							),
			) );
		}
		
		$projects = self::get_portfolio_item( array(
				'show_pagination'    => $show_pagination,
				'posts_number'       => $posts_number,
				'include_categories' => $include_categories,
				'fullwidth'          => $fullwidth,
				'post_type'			 => $post_type,
				'order' 			 => $order,
				'orderBy'			 => $orderBy,
				'tax_query_op'		 => $tax_query_op
		) );
		
		$categories_included = array();
		ob_start();
		if( $projects->post_count > 0 ) {
			while ( $projects->have_posts() ) {
				$projects->the_post();
				
				$category_classes = array();
				$categories = get_the_terms( get_the_ID(), 'project_category' );
				if ( $categories ) {
					foreach ( $categories as $category ) {
						$category_classes[] = 'project_category_' . urldecode( $category->slug );
						$categories_included[] = $category->term_id;
					}
				}
				
				$category_classes = implode( ' ', $category_classes );
				
				$main_post_class = sprintf(
						'et_pb_portfolio_item%1$s %2$s',
						( 'on' !== $fullwidth ? ' et_pb_grid_item' : '' ),
						$category_classes
						);
				
				?>
				<div id="post-<?php the_ID(); ?>" <?php post_class( $main_post_class ); ?>>
				<?php
					$thumb = '';

					$width = 'on' === $fullwidth ?  1080 : 400;
					$width = (int) apply_filters( 'et_pb_portfolio_image_width', $width );

					$height = 'on' === $fullwidth ?  9999 : 284;
					$height = (int) apply_filters( 'et_pb_portfolio_image_height', $height );
					$classtext = 'on' === $fullwidth ? 'et_pb_post_main_image' : '';
					$titletext = get_the_title();
					$permalink = get_permalink();
					$post_meta = get_the_term_list( get_the_ID(), 'project_category', '', ', ' );
					$thumbnail = get_thumbnail( $width, $height, $classtext, $titletext, $titletext, false, 'Blogimage' );
					$thumb = $thumbnail["thumb"];


					if ( '' !== $thumb ) : ?>
						<a href="<?php echo esc_url( $permalink ); ?>">
							<span class="et_portfolio_image">
								<?php print_thumbnail( $thumb, $thumbnail["use_timthumb"], $titletext, $width, $height ); ?>
						<?php if ( 'on' !== $fullwidth ) :

								$data_icon = '' !== $hover_icon
									? sprintf(
										' data-icon="%1$s"',
										esc_attr( et_pb_process_font_icon( $hover_icon ) )
									)
									: '';

								printf( '<span class="et_overlay%1$s"%2$s></span>',
									( '' !== $hover_icon ? ' et_pb_inline_icon' : '' ),
									et_core_esc_previously( $data_icon )
								);

						?>
						<?php endif; ?>
							</span>
						</a>
				<?php
					endif;
				?>

				<?php if ( 'on' === $show_title ) : ?>
					<<?php echo et_pb_process_header_level( $header_level, 'h2' ) ?> class="et_pb_module_header">
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo et_core_intentionally_unescaped( $titletext, 'html' ); ?></a>
					</<?php echo et_pb_process_header_level( $header_level, 'h2' ) ?>>
				<?php endif; ?>

				<?php if ( 'on' === $show_categories ) : ?>
					<p class="post-meta"><?php echo et_core_esc_wp( $post_meta ); ?></p>
				<?php endif; ?>

				</div><!-- .et_pb_portfolio_item -->
				<?php
			}
		}

		wp_reset_postdata();

		if ( ! $posts = ob_get_clean() ) {
			$posts            = self::get_no_results_template();
			$category_filters = '';
		} else {
			$categories_included = explode ( ',', $include_categories );
			$terms_args = array(
				'include' => $categories_included,
				'orderby' => 'name',
				'order' => 'ASC',
			);
			$terms = get_terms( 'project_category', $terms_args );

			$category_filters = '<ul class="clearfix">';
			$category_filters .= sprintf( '<li class="et_pb_portfolio_filter et_pb_portfolio_filter_all"><a href="#" class="active" data-category-slug="all">%1$s</a></li>',
				esc_html__( 'All', 'et_builder' )
			);
			foreach ( $terms as $term  ) {
				$category_filters .= sprintf( '<li class="et_pb_portfolio_filter"><a href="#" data-category-slug="%1$s">%2$s</a></li>',
					esc_attr( urldecode( $term->slug ) ),
					esc_html( $term->name )
				);
			}
			$category_filters .= '</ul>';
		}

		$video_background = $this->video_background();
		$parallax_image_background = $this->get_parallax_image_background();

		// Images: Add CSS Filters and Mix Blend Mode rules (if set)
		if ( isset( $this->advanced_fields['image']['css'] ) ) {
			$this->add_classname( $this->generate_css_filters(
				$render_slug,
				'child_',
				self::$data_utils->array_get( $this->advanced_fields['image']['css'], 'main', '%%order_class%%' )
			) );
		}

		// Module classnames
		$this->add_classname( array(
			'et_pb_portfolio',
			"et_pb_bg_layout_{$background_layout}",
			$this->get_text_orientation_classname(),
		) );

		if ( 'on' === $fullwidth ) {
			$this->add_classname( 'et_pb_filterable_portfolio_fullwidth' );
		} else {
			$this->add_classname( array(
				'et_pb_filterable_portfolio_grid',
				'clearfix',
			) );
		}

		$data_background_layout       = '';
		$data_background_layout_hover = '';

		if ( $background_layout_hover_enabled ) {
			$data_background_layout = sprintf(
				' data-background-layout="%1$s"',
				esc_attr( $background_layout )
			);
			$data_background_layout_hover = sprintf(
				' data-background-layout-hover="%1$s"',
				esc_attr( $background_layout_hover )
			);
		}

		$output = sprintf(
			'<div%4$s class="%1$s" data-posts-number="%5$d"%8$s%11$s%12$s>
				%10$s
				%9$s
				<div class="et_pb_portfolio_filters clearfix">%2$s</div><!-- .et_pb_portfolio_filters -->

				<div class="et_pb_portfolio_items_wrapper %6$s">
					<div class="et_pb_portfolio_items">%3$s</div><!-- .et_pb_portfolio_items -->
				</div>
				%7$s
			</div> <!-- .et_pb_filterable_portfolio -->',
			$this->module_classname( $render_slug ),
			$category_filters,
			$posts,
			$this->module_id(),
			esc_attr( $posts_number), // #5
			('on' === $show_pagination ? 'clearfix' : 'no_pagination' ),
			('on' === $show_pagination ? '<div class="et_pb_portofolio_pagination"></div>' : '' ),
			is_rtl() ? ' data-rtl="true"' : '',
			$video_background,
			$parallax_image_background, // #10
			et_core_esc_previously( $data_background_layout ),
			et_core_esc_previously( $data_background_layout_hover )
		);

		return $output;
	}
	
}

new ET_Builder_Module_Extended_Filterable_Portfolio();