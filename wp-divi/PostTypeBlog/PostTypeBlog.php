<?php

class ET_Builder_Module_Post_Type_Blog extends ET_Builder_Module_Blog {
	function init() {
		parent::init();
		$this->name       = esc_html__( 'Post Type Blog', 'et_builder' );
		$this->slug       = 'et_pb_pt_blog';

		$this->whitelisted_fields[] = 'post_types';

		$this->fields_defaults['post_types'] = array('post');

	}

	function get_fields() {
		$fields = parent::get_fields();
		$fields['post_types'] = array(
			'label'             => esc_html__( 'Post Types', 'et_builder' ),
			'type'              => 'select',
			'option_category'   => 'configuration',
			'options'           => $this->get_post_types(),
			'description'       => esc_html__( 'List of post types to show on query', 'et_builder' ),
			'toggle_slug'       => 'main_content',
		);
		$fields['__posts'] = array(
			'type' => 'computed',
			'computed_callback' => array( 'ET_Builder_Module_Post_Type_Blog', 'get_blog_posts' ),
			'computed_depends_on' => array(
				'fullwidth',
				'posts_number',
				'include_categories',
				'meta_date',
				'show_thumbnail',
				'show_content',
				'show_more',
				'show_author',
				'show_date',
				'show_categories',
				'show_comments',
				'show_pagination',
				'offset_number',
				'use_overlay',
				'hover_icon',
				'header_level',
				'__page',
				'post_types',
			),
			'computed_minimum' => array(
				'posts_number',
			),
		);
		$fields['__page']          = array(
			'type'              => 'computed',
			'computed_callback' => array( 'ET_Builder_Module_Post_Type_Blog', 'get_blog_posts' ),
			'computed_affects'  => array(
				'__posts',
			),
		);
		return $fields;
	}

	/**
	 * Get blog posts for blog module
	 *
	 * @param array   arguments that is being used by et_pb_blog
	 * @return string blog post markup
	 */
	static function get_blog_posts( $args = array(), $conditional_tags = array(), $current_page = array() ) {
		global $paged, $post, $wp_query, $et_fb_processing_shortcode_object, $et_pb_rendering_column_content;

		$global_processing_original_value = $et_fb_processing_shortcode_object;

		// Default params are combination of attributes that is used by et_pb_blog and
		// conditional tags that need to be simulated (due to AJAX nature) by passing args
		$defaults = array(
			'fullwidth'                     => '',
			'posts_number'                  => '',
			'include_categories'            => '',
			'meta_date'                     => '',
			'show_thumbnail'                => '',
			'show_content'                  => '',
			'show_author'                   => '',
			'show_date'                     => '',
			'show_categories'               => '',
			'show_comments'                 => '',
			'show_pagination'               => '',
			'background_layout'             => '',
			'show_more'                     => '',
			'offset_number'                 => '',
			'masonry_tile_background_color' => '',
			'overlay_icon_color'            => '',
			'hover_overlay_color'           => '',
			'hover_icon'                    => '',
			'use_overlay'                   => '',
			'header_level'                  => 'h2',
		);

		// WordPress' native conditional tag is only available during page load. It'll fail during component update because
		// et_pb_process_computed_property() is loaded in admin-ajax.php. Thus, use WordPress' conditional tags on page load and
		// rely to passed $conditional_tags for AJAX call
		$is_front_page               = et_fb_conditional_tag( 'is_front_page', $conditional_tags );
		$is_search                   = et_fb_conditional_tag( 'is_search', $conditional_tags );
		$is_single                   = et_fb_conditional_tag( 'is_single', $conditional_tags );
		$et_is_builder_plugin_active = et_fb_conditional_tag( 'et_is_builder_plugin_active', $conditional_tags );

		$container_is_closed = false;

		// remove all filters from WP audio shortcode to make sure current theme doesn't add any elements into audio module
		remove_all_filters( 'wp_audio_shortcode_library' );
		remove_all_filters( 'wp_audio_shortcode' );
		remove_all_filters( 'wp_audio_shortcode_class' );

		$args = wp_parse_args( $args, $defaults );

		$processed_header_level = et_pb_process_header_level( $args['header_level'], 'h2' );

		$overlay_output = '';
		$hover_icon = '';

		if ( 'on' === $args['use_overlay'] ) {
			$data_icon = '' !== $args['hover_icon']
				? sprintf(
					' data-icon="%1$s"',
					esc_attr( et_pb_process_font_icon( $args['hover_icon'] ) )
				)
				: '';

			$overlay_output = sprintf(
				'<span class="et_overlay%1$s"%2$s></span>',
				( '' !== $args['hover_icon'] ? ' et_pb_inline_icon' : '' ),
				$data_icon
			);
		}

		$overlay_class = 'on' === $args['use_overlay'] ? ' et_pb_has_overlay' : '';

		$query_args = array(
			'posts_per_page' => intval( $args['posts_number'] ),
			'post_status'    => 'publish',
		);

		if ( defined( 'DOING_AJAX' ) && isset( $current_page['paged'] ) ) {
			$paged = intval( $current_page['paged'] );
		} else {
			$paged = $is_front_page ? get_query_var( 'page' ) : get_query_var( 'paged' );
		}

		// support pagination in VB
		if ( isset( $args['__page'] ) ) {
			$paged = $args['__page'];
		}

		if ( '' !== $args['include_categories'] ) {
			$query_args['cat'] = $args['include_categories'];
		}

		if ( ! $is_search ) {
			$query_args['paged'] = $paged;
		}

		if ( '' !== $args['offset_number'] && ! empty( $args['offset_number'] ) ) {
			/**
			 * Offset + pagination don't play well. Manual offset calculation required
			 * @see: https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination
			 */
			if ( $paged > 1 ) {
				$query_args['offset'] = ( ( $paged - 1 ) * intval( $args['posts_number'] ) ) + intval( $args['offset_number'] );
			} else {
				$query_args['offset'] = intval( $args['offset_number'] );
			}
		}

		if ( $is_single ) {
			$query_args['post__not_in'][] = get_the_ID();
		}
		
		if( is_array($args['post_types'])) {
			$query_args['post_type'] = $args['post_types'];
		}

		// Get query
		$query = new WP_Query( $query_args );

		// Keep page's $wp_query global
		$wp_query_page = $wp_query;

		// Turn page's $wp_query into this module's query
		$wp_query = $query;

		ob_start();

		if ( $query->have_posts() ) {
			if ( 'on' !== $args['fullwidth'] ) {
				echo '<div class="et_pb_salvattore_content" data-columns>';
			}

			while( $query->have_posts() ) {
				$query->the_post();
				global $et_fb_processing_shortcode_object;

				$global_processing_original_value = $et_fb_processing_shortcode_object;

				// reset the fb processing flag
				$et_fb_processing_shortcode_object = false;

				$thumb          = '';
				$width          = 'on' === $args['fullwidth'] ? 1080 : 400;
				$width          = (int) apply_filters( 'et_pb_blog_image_width', $width );
				$height         = 'on' === $args['fullwidth'] ? 675 : 250;
				$height         = (int) apply_filters( 'et_pb_blog_image_height', $height );
				$classtext      = 'on' === $args['fullwidth'] ? 'et_pb_post_main_image' : '';
				$titletext      = get_the_title();
				$thumbnail      = get_thumbnail( $width, $height, $classtext, $titletext, $titletext, false, 'Blogimage' );
				$thumb          = $thumbnail["thumb"];
				$no_thumb_class = '' === $thumb || 'off' === $args['show_thumbnail'] ? ' et_pb_no_thumb' : '';

				$post_format = et_pb_post_format();
				if ( in_array( $post_format, array( 'video', 'gallery' ) ) ) {
					$no_thumb_class = '';
				}

				// Print output
				?>
					<article id="" <?php post_class( 'et_pb_post clearfix' . $no_thumb_class . $overlay_class ) ?>>
						<?php
							et_divi_post_format_content();

							if ( ! in_array( $post_format, array( 'link', 'audio', 'quote' ) ) ) {
								if ( 'video' === $post_format && false !== ( $first_video = et_get_first_video() ) ) :
									$video_overlay = has_post_thumbnail() ? sprintf(
										'<div class="et_pb_video_overlay" style="background-image: url(%1$s); background-size: cover;">
											<div class="et_pb_video_overlay_hover">
												<a href="#" class="et_pb_video_play"></a>
											</div>
										</div>',
										$thumb
									) : '';

									printf(
										'<div class="et_main_video_container">
											%1$s
											%2$s
										</div>',
										$video_overlay,
										$first_video
									);
								elseif ( 'gallery' === $post_format ) :
									et_pb_gallery_images( 'slider' );
								elseif ( '' !== $thumb && 'on' === $args['show_thumbnail'] ) :
									if ( 'on' !== $args['fullwidth'] ) echo '<div class="et_pb_image_container">'; ?>
										<a href="<?php esc_url( the_permalink() ); ?>" class="entry-featured-image-url">
											<?php print_thumbnail( $thumb, $thumbnail["use_timthumb"], $titletext, $width, $height ); ?>
											<?php if ( 'on' === $args['use_overlay'] ) {
												echo $overlay_output;
											} ?>
										</a>
								<?php
									if ( 'on' !== $args['fullwidth'] ) echo '</div> <!-- .et_pb_image_container -->';
								endif;
							}
						?>

						<?php if ( 'off' === $args['fullwidth'] || ! in_array( $post_format, array( 'link', 'audio', 'quote' ) ) ) { ?>
							<?php if ( ! in_array( $post_format, array( 'link', 'audio' ) ) ) { ?>
								<<?php echo $processed_header_level; ?> class="entry-title"><a href="<?php esc_url( the_permalink() ); ?>"><?php the_title(); ?></a></<?php echo $processed_header_level; ?>>
							<?php } ?>

							<?php
								if ( 'on' === $args['show_author'] || 'on' === $args['show_date'] || 'on' === $args['show_categories'] || 'on' === $args['show_comments'] ) {
									printf( '<p class="post-meta">%1$s %2$s %3$s %4$s %5$s %6$s %7$s</p>',
										(
											'on' === $args['show_author']
												? et_get_safe_localization( sprintf( __( 'by %s', 'et_builder' ), '<span class="author vcard">' .  et_pb_get_the_author_posts_link() . '</span>' ) )
												: ''
										),
										(
											( 'on' === $args['show_author'] && 'on' === $args['show_date'] )
												? ' | '
												: ''
										),
										(
											'on' === $args['show_date']
												? et_get_safe_localization( sprintf( __( '%s', 'et_builder' ), '<span class="published">' . esc_html( get_the_date( $args['meta_date'] ) ) . '</span>' ) )
												: ''
										),
										(
											(( 'on' === $args['show_author'] || 'on' === $args['show_date'] ) && 'on' === $args['show_categories'] )
												? ' | '
												: ''
										),
										(
											'on' === $args['show_categories']
												? get_the_category_list(', ')
												: ''
										),
										(
											(( 'on' === $args['show_author'] || 'on' === $args['show_date'] || 'on' === $args['show_categories'] ) && 'on' === $args['show_comments'])
												? ' | '
												: ''
										),
										(
											'on' === $args['show_comments']
												? sprintf( esc_html( _nx( '%s Comment', '%s Comments', get_comments_number(), 'number of comments', 'et_builder' ) ), number_format_i18n( get_comments_number() ) )
												: ''
										)
									);
								}

								$post_content = et_strip_shortcodes( et_delete_post_first_video( get_the_content() ), true );

								// reset the fb processing flag
								$et_fb_processing_shortcode_object = false;
								// set the flag to indicate that we're processing internal content
								$et_pb_rendering_column_content = true;
								// reset all the attributes required to properly generate the internal styles
								ET_Builder_Element::clean_internal_modules_styles();

								echo '<div class="post-content">';

								if ( 'on' === $args['show_content'] ) {
									global $more;

									// page builder doesn't support more tag, so display the_content() in case of post made with page builder
									if ( et_pb_is_pagebuilder_used( get_the_ID() ) ) {
										$more = 1;

										echo apply_filters( 'the_content', $post_content );

									} else {
										$more = null;
										echo apply_filters( 'the_content', et_delete_post_first_video( get_the_content( esc_html__( 'read more...', 'et_builder' ) ) ) );
									}
								} else {
									if ( has_excerpt() ) {
										the_excerpt();
									} else {
										if ( '' !== $post_content ) {
											// set the $et_fb_processing_shortcode_object to false, to retrieve the content inside truncate_post() correctly
											$et_fb_processing_shortcode_object = false;
											echo wpautop( et_delete_post_first_video( strip_shortcodes( truncate_post( 270, false, '', true ) ) ) );
											// reset the $et_fb_processing_shortcode_object to its original value
											$et_fb_processing_shortcode_object = $global_processing_original_value;
										} else {
											echo '';
										}
									}
								}

								$et_fb_processing_shortcode_object = $global_processing_original_value;
								// retrieve the styles for the modules inside Blog content
								$internal_style = ET_Builder_Element::get_style( true );
								// reset all the attributes after we retrieved styles
								ET_Builder_Element::clean_internal_modules_styles( false );
								$et_pb_rendering_column_content = false;
								// append styles to the blog content
								if ( $internal_style ) {
									printf(
										'<style type="text/css" class="et_fb_blog_inner_content_styles">
											%1$s
										</style>',
										$internal_style
									);
								}

								echo '</div>';

								if ( 'on' !== $args['show_content'] ) {
									$more = 'on' == $args['show_more'] ? sprintf( ' <a href="%1$s" class="more-link" >%2$s</a>' , esc_url( get_permalink() ), esc_html__( 'read more', 'et_builder' ) )  : '';
									echo $more;
								}
								?>
						<?php } // 'off' === $fullwidth || ! in_array( $post_format, array( 'link', 'audio', 'quote', 'gallery' ?>
					</article>
				<?php

				$et_fb_processing_shortcode_object = $global_processing_original_value;
			} // endwhile

			if ( 'on' !== $args['fullwidth'] ) {
				echo '</div>';
			}

			if ( 'on' === $args['show_pagination'] && ! $is_search ) {
				// echo '</div> <!-- .et_pb_posts -->'; // @todo this causes closing tag issue

				$container_is_closed = true;

				if ( function_exists( 'wp_pagenavi' ) ) {
					wp_pagenavi( array(
						'query' => $query
					) );
				} else {
					if ( $et_is_builder_plugin_active ) {
						include( ET_BUILDER_PLUGIN_DIR . 'includes/navigation.php' );
					} else {
						get_template_part( 'includes/navigation', 'index' );
					}
				}
			}

			wp_reset_query();
		}

		wp_reset_postdata();

		// Reset $wp_query to its origin
		$wp_query = $wp_query_page;

		if ( ! $posts = ob_get_clean() ) {
			$posts = self::get_no_results_template();
		}

		return $posts;
	}

	function render( $atts, $content = null, $function_name ) {
		global $post;

		// Stored current global post as variable so global $post variable can be restored
		// to its original state when et_pb_blog shortcode ends to avoid incorrect global $post
		// being used on the page (i.e. blog + shop module in backend builder)
		$post_cache = $post;

		/**
		 * Cached $wp_filter so it can be restored at the end of the callback.
		 * This is needed because this callback uses the_content filter / calls a function
		 * which uses the_content filter. WordPress doesn't support nested filter
		 */
		global $wp_filter;
		$wp_filter_cache = $wp_filter;

		$module_id           = $this->props['module_id'];
		$module_class        = $this->props['module_class'];
		$fullwidth           = $this->props['fullwidth'];
		$posts_number        = $this->props['posts_number'];
		$include_categories  = $this->props['include_categories'];
		$meta_date           = $this->props['meta_date'];
		$show_thumbnail      = $this->props['show_thumbnail'];
		$show_content        = $this->props['show_content'];
		$show_author         = $this->props['show_author'];
		$show_date           = $this->props['show_date'];
		$show_categories     = $this->props['show_categories'];
		$show_comments       = $this->props['show_comments'];
		$show_pagination     = $this->props['show_pagination'];
		$background_layout   = $this->props['background_layout'];
		$show_more           = $this->props['show_more'];
		$offset_number       = $this->props['offset_number'];
		$masonry_tile_background_color = $this->props['masonry_tile_background_color'];
		$overlay_icon_color  = $this->props['overlay_icon_color'];
		$hover_overlay_color = $this->props['hover_overlay_color'];
		$hover_icon          = $this->props['hover_icon'];
		$use_overlay         = $this->props['use_overlay'];
		$header_level        = $this->props['header_level'];
		$post_types          = $this->props['post_types'];

		global $paged;

		$module_class              = ET_Builder_Element::add_module_order_class( $module_class, $function_name );
		$video_background          = $this->video_background();
		$parallax_image_background = $this->get_parallax_image_background();

		$container_is_closed = false;

		$processed_header_level = et_pb_process_header_level( $header_level, 'h2' );

		// some themes do not include these styles/scripts so we need to enqueue them in this module to support audio post format
		wp_enqueue_style( 'wp-mediaelement' );
		wp_enqueue_script( 'wp-mediaelement' );

		// include easyPieChart which is required for loading Blog module content via ajax correctly
		wp_enqueue_script( 'easypiechart' );

		// include ET Shortcode scripts
		wp_enqueue_script( 'et-shortcodes-js' );

		// remove all filters from WP audio shortcode to make sure current theme doesn't add any elements into audio module
		remove_all_filters( 'wp_audio_shortcode_library' );
		remove_all_filters( 'wp_audio_shortcode' );
		remove_all_filters( 'wp_audio_shortcode_class' );

		if ( '' !== $masonry_tile_background_color ) {
			ET_Builder_Element::set_style( $function_name, array(
				'selector'    => '%%order_class%% .et_pb_blog_grid .et_pb_post',
				'declaration' => sprintf(
					'background-color: %1$s;',
					esc_html( $masonry_tile_background_color )
				),
			) );
		}

		if ( '' !== $overlay_icon_color ) {
			ET_Builder_Element::set_style( $function_name, array(
				'selector'    => '%%order_class%% .et_overlay:before',
				'declaration' => sprintf(
					'color: %1$s !important;',
					esc_html( $overlay_icon_color )
				),
			) );
		}

		if ( '' !== $hover_overlay_color ) {
			ET_Builder_Element::set_style( $function_name, array(
				'selector'    => '%%order_class%% .et_overlay',
				'declaration' => sprintf(
					'background-color: %1$s;',
					esc_html( $hover_overlay_color )
				),
			) );
		}

		if ( 'on' === $use_overlay ) {
			$data_icon = '' !== $hover_icon
				? sprintf(
					' data-icon="%1$s"',
					esc_attr( et_pb_process_font_icon( $hover_icon ) )
				)
				: '';

			$overlay_output = sprintf(
				'<span class="et_overlay%1$s"%2$s></span>',
				( '' !== $hover_icon ? ' et_pb_inline_icon' : '' ),
				$data_icon
			);
		}

		$overlay_class = 'on' === $use_overlay ? ' et_pb_has_overlay' : '';

		if ( 'on' !== $fullwidth ){
			wp_enqueue_script( 'salvattore' );

			$background_layout = 'light';
		}

		$args = array( 'posts_per_page' => (int) $posts_number );

		$et_paged = is_front_page() ? get_query_var( 'page' ) : get_query_var( 'paged' );

		if ( is_front_page() ) {
			$paged = $et_paged;
		}

		if ( '' !== $include_categories ) {
			$args['cat'] = $include_categories;
		}

		if ( ! is_search() ) {
			$args['paged'] = $et_paged;
		}
		
		//if ( is_array($post_types) && count($post_types) > 0 ) {
		if( '' !== $post_types ) {
			$args['post_type'] = $this->get_post_types($post_types);
		}
		//wp_die(print_r($post_types, true).print_r($args, true));

		if ( '' !== $offset_number && ! empty( $offset_number ) ) {
			/**
			 * Offset + pagination don't play well. Manual offset calculation required
			 * @see: https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination
			 */
			if ( $paged > 1 ) {
				$args['offset'] = ( ( $et_paged - 1 ) * intval( $posts_number ) ) + intval( $offset_number );
			} else {
				$args['offset'] = intval( $offset_number );
			}
		}

		if ( is_single() && ! isset( $args['post__not_in'] ) ) {
			$args['post__not_in'] = array( get_the_ID() );
		}

		// Images: Add CSS Filters and Mix Blend Mode rules (if set)
		if ( array_key_exists( 'image', $this->advanced_options ) && array_key_exists( 'css', $this->advanced_options['image'] ) ) {
			$module_class .= $this->generate_css_filters(
				$function_name,
				'child_',
				self::$data_utils->array_get( $this->advanced_options['image']['css'], 'main', '%%order_class%%' )
			);
		}

		ob_start();

		query_posts( $args );

		if ( have_posts() ) {
			if ( 'off' === $fullwidth ) {
				echo '<div class="et_pb_salvattore_content" data-columns>';
			}

			while ( have_posts() ) {
				the_post();

				global $post;

				$post_format = et_pb_post_format();

				$thumb = '';

				$width = 'on' === $fullwidth ? 1080 : 400;
				$width = (int) apply_filters( 'et_pb_blog_image_width', $width );

				$height = 'on' === $fullwidth ? 675 : 250;
				$height = (int) apply_filters( 'et_pb_blog_image_height', $height );
				$classtext = 'on' === $fullwidth ? 'et_pb_post_main_image' : '';
				$titletext = get_the_title();
				$thumbnail = get_thumbnail( $width, $height, $classtext, $titletext, $titletext, false, 'Blogimage' );
				$thumb = $thumbnail['thumb'];

				$no_thumb_class = '' === $thumb || 'off' === $show_thumbnail ? ' et_pb_no_thumb' : '';

				if ( in_array( $post_format, array( 'video', 'gallery' ) ) ) {
					$no_thumb_class = '';
				}
				?>

			<article id="post-<?php the_ID(); ?>" <?php post_class( 'et_pb_post clearfix' . $no_thumb_class . $overlay_class  ); ?>>

			<?php
				et_divi_post_format_content();

				if ( ! in_array( $post_format, array( 'link', 'audio', 'quote' ) ) || post_password_required( $post ) ) {
					if ( 'video' === $post_format && false !== ( $first_video = et_get_first_video() ) ) :
						$video_overlay = has_post_thumbnail() ? sprintf(
							'<div class="et_pb_video_overlay" style="background-image: url(%1$s); background-size: cover;">
								<div class="et_pb_video_overlay_hover">
									<a href="#" class="et_pb_video_play"></a>
								</div>
							</div>',
							$thumb
						) : '';

						printf(
							'<div class="et_main_video_container">
								%1$s
								%2$s
							</div>',
							$video_overlay,
							$first_video
						);
					elseif ( 'gallery' === $post_format ) :
						et_pb_gallery_images( 'slider' );
					elseif ( '' !== $thumb && 'on' === $show_thumbnail ) :
						if ( 'on' !== $fullwidth ) {
							echo '<div class="et_pb_image_container">';
						}
						?>
							<a href="<?php esc_url( the_permalink() ); ?>" class="entry-featured-image-url">
								<?php print_thumbnail( $thumb, $thumbnail["use_timthumb"], $titletext, $width, $height ); ?>
								<?php if ( 'on' === $use_overlay ) {
									echo $overlay_output;
								} ?>
							</a>
					<?php
						if ( 'on' !== $fullwidth ) echo '</div> <!-- .et_pb_image_container -->';
					endif;
				} ?>

			<?php if ( 'off' === $fullwidth || ! in_array( $post_format, array( 'link', 'audio', 'quote' ) ) || post_password_required( $post ) ) { ?>
				<?php if ( ! in_array( $post_format, array( 'link', 'audio' ) ) || post_password_required( $post ) ) { ?>
					<<?php echo $processed_header_level; ?> class="entry-title"><a href="<?php esc_url( the_permalink() ); ?>"><?php the_title(); ?></a></<?php echo $processed_header_level; ?>>
				<?php } ?>

				<?php
					if ( 'on' === $show_author || 'on' === $show_date || 'on' === $show_categories || 'on' === $show_comments ) {
						printf( '<p class="post-meta">%1$s %2$s %3$s %4$s %5$s %6$s %7$s</p>',
							(
								'on' === $show_author
									? et_get_safe_localization( sprintf( __( 'by %s', 'et_builder' ), '<span class="author vcard">' .  et_pb_get_the_author_posts_link() . '</span>' ) )
									: ''
							),
							(
								( 'on' === $show_author && 'on' === $show_date )
									? ' | '
									: ''
							),
							(
								'on' === $show_date
									? et_get_safe_localization( sprintf( __( '%s', 'et_builder' ), '<span class="published">' . esc_html( get_the_date( $meta_date ) ) . '</span>' ) )
									: ''
							),
							(
								(( 'on' === $show_author || 'on' === $show_date ) && 'on' === $show_categories)
									? ' | '
									: ''
							),
							(
								'on' === $show_categories
									? get_the_category_list(', ')
									: ''
							),
							(
								(( 'on' === $show_author || 'on' === $show_date || 'on' === $show_categories ) && 'on' === $show_comments)
									? ' | '
									: ''
							),
							(
								'on' === $show_comments
									? sprintf( esc_html( _nx( '%s Comment', '%s Comments', get_comments_number(), 'number of comments', 'et_builder' ) ), number_format_i18n( get_comments_number() ) )
									: ''
							)
						);
					}

					echo '<div class="post-content">';
					global $et_pb_rendering_column_content;

					$post_content = et_strip_shortcodes( et_delete_post_first_video( get_the_content() ), true );

					$et_pb_rendering_column_content = true;

					if ( 'on' === $show_content ) {
						global $more;

						// page builder doesn't support more tag, so display the_content() in case of post made with page builder
						if ( et_pb_is_pagebuilder_used( get_the_ID() ) ) {
							$more = 1;
							echo apply_filters( 'the_content', $post_content );
						} else {
							$more = null;
							echo apply_filters( 'the_content', et_delete_post_first_video( get_the_content( esc_html__( 'read more...', 'et_builder' ) ) ) );
						}
					} else {
						if ( has_excerpt() ) {
							the_excerpt();
						} else {
							echo wpautop( et_delete_post_first_video( strip_shortcodes( truncate_post( 270, false, '', true ) ) ) );
						}
					}

					$et_pb_rendering_column_content = false;

					if ( 'on' !== $show_content ) {
						$more = 'on' == $show_more ? sprintf( ' <a href="%1$s" class="more-link" >%2$s</a>' , esc_url( get_permalink() ), esc_html__( 'read more', 'et_builder' ) )  : '';
						echo $more;
					}

					echo '</div>';
					?>
			<?php } // 'off' === $fullwidth || ! in_array( $post_format, array( 'link', 'audio', 'quote', 'gallery' ?>

			</article> <!-- .et_pb_post -->
	<?php
			} // endwhile

			if ( 'off' === $fullwidth ) {
 				echo '</div><!-- .et_pb_salvattore_content -->';
 			}

			if ( 'on' === $show_pagination && ! is_search() ) {
				if ( function_exists( 'wp_pagenavi' ) ) {
					wp_pagenavi();
				} else {
					if ( et_is_builder_plugin_active() ) {
						include( ET_BUILDER_PLUGIN_DIR . 'includes/navigation.php' );
					} else {
						get_template_part( 'includes/navigation', 'index' );
					}
				}

				echo '</div> <!-- .et_pb_posts -->';

				$container_is_closed = true;
			}
		} else {
			if ( et_is_builder_plugin_active() ) {
				include( ET_BUILDER_PLUGIN_DIR . 'includes/no-results.php' );
			} else {
				get_template_part( 'includes/no-results', 'index' );
			}
		}

		wp_reset_query();

		$posts = ob_get_contents();

		ob_end_clean();

		$class = " et_pb_bg_layout_{$background_layout}";

		if ( 'on' !== $fullwidth ) {
			$output = sprintf(
				'<div%5$s class="et_pb_module et_pb_blog_grid_wrapper%6$s">
					<div class="%1$s%3$s%7$s%9$s%11$s">
					%10$s
					%8$s
					<div class="et_pb_ajax_pagination_container">
						%2$s
					</div>
					%4$s %12$s
				</div>',
				( 'on' === $fullwidth ? 'et_pb_posts' : 'et_pb_blog_grid clearfix' ),
				$posts,
				esc_attr( $class ),
				( ! $container_is_closed ? '</div> <!-- .et_pb_posts -->' : '' ),
				( '' !== $module_id ? sprintf( ' id="%1$s"', esc_attr( $module_id ) ) : '' ),
				( '' !== $module_class ? sprintf( ' %1$s', esc_attr( $module_class ) ) : '' ),
				'' !== $video_background ? ' et_pb_section_video et_pb_preload' : '',
				$video_background,
				'' !== $parallax_image_background ? ' et_pb_section_parallax' : '',
				$parallax_image_background,
				$this->get_text_orientation_classname(),
				$this->drop_shadow_back_compatibility2( $function_name )
			);
		} else {
			$output = sprintf(
				'<div%5$s class="et_pb_module %1$s%3$s%6$s%7$s%9$s%11$s">
				%10$s
				%8$s
				<div class="et_pb_ajax_pagination_container">
					%2$s
				</div>
				%4$s %12$s',
				( 'on' === $fullwidth ? 'et_pb_posts' : 'et_pb_blog_grid clearfix' ),
				$posts,
				esc_attr( $class ),
				( ! $container_is_closed ? '</div> <!-- .et_pb_posts -->' : '' ),
				( '' !== $module_id ? sprintf( ' id="%1$s"', esc_attr( $module_id ) ) : '' ),
				( '' !== $module_class ? sprintf( ' %1$s', esc_attr( $module_class ) ) : '' ),
				'' !== $video_background ? ' et_pb_section_video et_pb_preload' : '',
				$video_background,
				'' !== $parallax_image_background ? ' et_pb_section_parallax' : '',
				$parallax_image_background,
				$this->get_text_orientation_classname(),
				$this->drop_shadow_back_compatibility2( $function_name )
			);
		}

		// Restore $wp_filter
		$wp_filter = $wp_filter_cache;
		unset($wp_filter_cache);

		// Restore global $post into its original state when et_pb_blog shortcode ends to avoid
		// the rest of the page uses incorrect global $post variable
		$post = $post_cache;

		return $output;
	}

	function get_post_types($type = false) {
		
		$options = get_post_types(array('public' => true, 'publicly_queryable' => true, '_builtin' => true));
		$options = array_merge(get_post_types(array('public' => true, 'publicly_queryable' => true, '_builtin' => false)), $options);
		if( $type !== false ) {
			return $options[$type];
		}
		return $options;
	}
	
	/**
	 * Since the styling file is not updated until the author updates the page/post,
	 * we should keep the drop shadow visible.
	 *
	 * @param string $functions_name
	 *
	 * @return string
	 */
	protected function drop_shadow_back_compatibility2( $functions_name ) {
		$utils = ET_Core_Data_Utils::instance();
		$atts  = $this->props;
		
		if (
				version_compare( $utils->array_get( $atts, '_builder_version', '3.0.93' ), '3.0.94', 'lt' )
				&&
				'on' !== $utils->array_get( $atts, 'fullwidth' )
				&&
				'on' === $utils->array_get( $atts, 'use_dropshadow' )
				) {
					$class = self::get_module_order_class( $functions_name );
					
					return sprintf(
							'<style>%1$s</style>',
							sprintf( '.%1$s  article.et_pb_post { box-shadow: 0 1px 5px rgba(0,0,0,.1) }', esc_html( $class ) )
							);
				}
				
				return '';
	}
	
}

new ET_Builder_Module_Post_Type_Blog;
