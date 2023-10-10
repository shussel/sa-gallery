<?php

/*
 
Plugin Name: Shane's Adventure Gallery
 
Plugin URI: https://shanesadventure.com
 
Description: custom galleries. Size vertical and horizontal images proportionally with equal area sizing.
 
Version: 1.0
 
Author: Shane Hussel
 
Author URI: https://shanesadventure.com
 
License: GPLv2 or later
 
Text Domain: shanes-adventure
 
*/

// permananent redirects for non-standard structure
$moved = [
	'/photography/gallery/featured/' => '/photography/'
];
if ( $_SERVER['REQUEST_URI'] && isset( $moved[ $_SERVER['REQUEST_URI'] ] ) ) {
	wp_redirect( home_url( $moved[ $_SERVER['REQUEST_URI'] ] ), 301 );
	exit();
}

/* TAXONOMY */

// Register custom taxonomy gallery to attachments
add_action( 'init', function () {
	$labels = array(
		'name'          => _x( 'Galleries', 'taxonomy general name' ),
		'singular_name' => _x( 'Gallery', 'taxonomy singular name' ),
		'search_items'  => __( 'Search Galleries' ),
		'all_items'     => __( 'All Galleries' ),
		'edit_item'     => __( 'Edit Gallery' ),
		'update_item'   => __( 'Update Gallery' ),
		'add_new_item'  => __( 'Add New Gallery' ),
		'new_item_name' => __( 'New Gallery' ),
		'menu_name'     => __( 'Galleries' ),
	);
	$args   = array(
		'labels'            => $labels,
		'hierarchical'      => false,
		'public'            => true,
		'show_in_rest'      => true,
		"show_in_menu"      => true,
		'show_admin_column' => true,
		'query_var'         => 'gallery',
		'rewrite'           => array( 'slug' => 'photography/gallery' )
	);
	// register galleries for attachments
	register_taxonomy( 'sa_gallery', 'attachment', $args );
}, 0 );

/* SITEMAP */

// remove these strings from the end of tax sitemap urls
add_filter( 'wp_sitemaps_taxonomies_entry',
	function ( $entry ) {
		$trim_urls = [
			'gallery/featured/'
		];
		foreach ( $trim_urls as $trim_url ) {
			$trim_length = strlen( $trim_url );
			if ( substr_compare( $entry['loc'], $trim_url, - $trim_length ) === 0 ) {
				$entry['loc'] = substr( $entry['loc'], 0, - $trim_length );
			}
		}

		return $entry;
	}
);

// remove post format gallery index site map
add_filter( 'wp_sitemaps_taxonomies_query_args',
	function ( $args ) {
		$args['exclude']   = isset( $args['exclude'] ) ? $args['exclude'] : array();
		$args['exclude'][] = 28;
		$args['exclude'][] = 36;
		$args['exclude'][] = 37;
		$args['exclude'][] = 38;

		return $args;
	}
);

/* URLS */

// set gallery root
add_filter( 'rewrite_rules_array', function ( $rules ) {
	$custom_rules['photography/?$'] = 'index.php?gallery=featured';

	return array_merge( $custom_rules, $rules );
}, 10, 1 );

function sag_get_gallery_link( $gallery_name, $title = '' ) {
	$title = $title ?: $gallery_name;

	return '<a href="' . get_term_link( $gallery_name, 'sa_gallery' ) . '">' . $title . '</a>';
}

function sag_adjacent_links( $post = null ) {

	if ( ! ( $post = get_post( $post ) ) ) {
		return false;
	}
	$args   = [
		'numberposts' => - 1,
		'post_status' => 'publish',
		'post_type'   => 'attachment',
		'order'       => 'asc',
		'post_parent' => 0
	];
	$result = [ 'next' => false, 'prev' => false ];

	if ( $post->post_parent ) {
		$args['post_parent'] = $post->post_parent;
	}
	if ( ( $posts = get_posts( $args ) ) && ( $post_count = count( $posts ) ) ) {

		foreach ( $posts as $postkey => $postdata ) {

			if ( $postdata->ID == $post->ID ) {
				$post_index = $postkey;
				break;
			}
		}
		$prev_post = $post_index > 0 ? $posts[ $post_index - 1 ]->ID : ( $post->post_parent ?: 0 );
		$next_post = $post_index < ( $post_count - 1 ) ? $posts[ $post_index + 1 ]->ID : ( $post->post_parent ?: 0 );
	}
	if ( $prev_post ) {
		$result['prev'] = '<a id="previous-image" rel="prev" href="' . get_permalink( $prev_post ) . '">&#9664;</a>';
	}
	if ( $next_post ) {
		$result['next'] = '<a id="next-image" rel="next" href="' . get_permalink( $next_post ) . '">&#9654;</a>';
	}

	return $result;
}

function sag_parent_link( $post = null ) {
	if ( ( $post = get_post( $post ) ) && $post->post_parent && (get_post_status($post->post_parent) == 'publish' )) { ?>
        <label>Full Story</label><a href="<?= get_permalink( $post->post_parent ) ?>"
                                    class="parent_story"><?= get_the_title( $post->post_parent ) ?></a>
	<?php }
}

// sort galleries asc
add_action( 'pre_get_posts', function ( $query ) {

	// exclude admin or not main query from changes
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	// sort galleries asc
	if ( is_tax( 'sa_gallery' ) ) {
		$query->set( 'order', 'ASC' );
	}
} );

/* IMAGES */

// link gallery post image to gallery
add_filter( 'post_thumbnail_html', function ( $html, $post_id, $post_image_id ) {
	if ( get_post_format( $post_id ) === 'gallery' ) {
		return sag_get_gallery_link( get_the_title( $post_id ), $html );
	} else {
		return $html;
	}
}, 99, 3 );

// image attachments are their own thumbnail
add_filter( 'post_thumbnail_id', function ( $thumbnail_id, $post ) {
	if ( 'attachment' === $post->post_type ) {
		$thumbnail_id = $post->ID;
	}

	return $thumbnail_id;
}, 10, 2 );

// increase thumbnail size for attachment and gallery post wide images
add_filter( 'post_thumbnail_size', function ( $size, $post_id ) {

	global $sidebar_loaded;
	// only applies to thumbnail size attachment post type
	if ( $size !== 'thumbnail' || ! ( ( get_post_type( $post_id ) === 'attachment' ) || ( 'gallery' === get_post_format( $post_id ) ) ) ) {
		return $size;
	}
    // use thumbanil of post if its a regular post
	if ( get_post_type( $post_id ) !== 'attachment' ) {
		$post_id = get_post_thumbnail_id( $post_id );
	}
	// increase image size for wide images
	if (!$sidebar_loaded && ( $photo_info = samp_get_photo_info( $post_id ) ) && $photo_info['aspect_ratio'] ) {
		if ( $photo_info['aspect_ratio'] < .66 ) {
			$size = 'medium';
		}
		if ( $photo_info['aspect_ratio'] <= .3 ) {
			$size = 'medium_large';
		}
	}
	return $size;
}, 10, 2 );

// do not include sources more than exactly twice the requested size
add_filter( 'wp_calculate_image_srcset', function ( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
	foreach ( $sources as $width => $src ) {
		if ( ( $width != $size_array[0] ) && ( $width > 2 * $size_array[0] ) ) {
			unset( $sources[ $width ] );
		}
	}

	return $sources;
}, 10, 5 );

add_filter( 'post_thumbnail_html', 'remove_thumbnail_width_height', 10, 5 );

function remove_thumbnail_width_height( $html, $post_id, $post_thumbnail_id, $size, $attr ) {

	global $sidebar_loaded;

    $width=0;
    if ($image_info = wp_get_attachment_image_src($post_thumbnail_id, $size)) {
		$width = $image_info[1] ?: 400;
    }

    switch ($size) {
        case 'thumbnail':
	        $width = $width ?: 400;
            $sizes = 'sizes="(max-width: ' . $width + 60 . 'px) calc(100vw - 60px), '.$width.'px"';
            if ($sidebar_loaded) {
	            $sizes = 'sizes="(max-width: ' . $width + 60 . 'px) calc(100vw - 60px),(max-width: 999px) '.$width.'px, 200px"';
            }
            break;
	    case 'medium':
		    $width = $width ?: 600;
		    $sizes = 'sizes="(max-width: ' . $width + 60 . 'px) calc(100vw - 60px), '.$width.'px"';
		    break;
	    case 'large':
		    $sizes = 'sizes="(max-width: 679px) calc(100vw - 48px),
		                     (max-width: 999px) calc(100vw - 283px),
		                     (max-width: 1327px) calc(100vw - 528px),
		                     1200px"';
		    break;
        default:
            $sizes=null;
    }
    if ($sizes) {
	    $html = preg_replace( '/sizes="[^"]*"/', $sizes, $html );
    }
	return $html;
}

// add article classes based on thumbnail parameters
add_filter( 'post_class', function ( $classes, $class, $post_id ) {

	// if post has a thumbnail
	if ( ( $thumbnail_id = get_post_thumbnail_id( $post_id ) ) &&
	     ( $photo_info = samp_get_photo_info( $thumbnail_id ) ) ) {

		if ( $photo_info['width'] < 201 ) {
			$classes[] = 'small-image';
		} elseif ( $photo_info['width'] < 601 ) {
			$classes[] = 'medium-image';
		}
		if ( $photo_info['aspect_ratio'] ) {
            if ($photo_info['aspect_ratio'] < .3) {
                $ar_digits = round( $photo_info['aspect_ratio'] * 100 / 5 ) * 5;
            } else {
	            $ar_digits = round($photo_info['aspect_ratio']*10);
            }
			$classes[] = str_replace( '.', '-', 'image-ar-0-' . $ar_digits );
		}
	}

	return $classes;

}, 10, 3 );

/* Image sizing by area
	 *
	 * When we fit images to a fixed size, there is a noticeable discrepancy in relative
	 * visual size among images with different aspect ratios. This is because the area in
	 * pixels displayed for each image is different. Wide images fit to a square will be
	 * much smaller than square images fit to the same square. A mixed set of vertical and
	 * horizontal images sized to the same box may leave one or the other looking too big.
	 *
	 * We want to make the total pixel area of each image closer to the same size so
	 * visually they will be more cohesive. We can do this by sizing each image so it
	 * occupies a certain area, which is not the same as fitting it to fixed dimensions.
	 *
	 * If you take the total pixel area of the sizing box as the area to fit and the
	 * aspect ratio of the image you are sizing, you can use a formula to get height and
	 * width of the image to equal that total area. This means the image may exceed the
	 * fit dimensions on one axis, for example fitting a rectangle into a square of the
	 * same area, the rectangle will be wider, but shorter. In fact for a very long,
	 * skinny image, it could have infinite height or width by this method.
	 *
	 * This does accomplish what we set out to do, every image will have the same area,
	 * but there is another problem. WP uses the image size box as an absolute limit.
	 * Even if you create and size your image with this method, later in WP when the image
	 * is displayed, it may be downsized again to fit within the defined bounding box.
	 * The result is image html that doesn't match actual image dimensions and other
	 * issues. So we can't let our image resizing exceed the size of the bounding box.
	 * What is the point of resizing images with no maximum dimension anyway?
	 * Are our plans spoiled?
	 *
	 * No, given a couple more considerations. You probably don't care about extremely
	 * wide or tall images, they are a fringe case. Most of your images probably conform
	 * to a certain aspect ratio, or a small range of them. Take the widest aspect ratio
	 * you normally use as your "best ratio". Now given our original boundary box, fit
	 * an image of your best ratio into that box. The area of that resized image is now
	 * your "best area". Example, fitting a 2:3 photo into a 1:1 box, the "best area"
	 * is .6667x the area of the box. (If your box and best fit are both squares, this
	 * is not going to be any different than regular WP resizing.)
	 *
	 * Now you can resize images to fit within this smaller best fit area and also keep
	 * them within the boundary box. Every image narrower than your best ratio will be
	 * resized to fit within the box, and its dimensions will be reduced within the box.
	 * Most such images will have the same pixel area as the 2:3 image. The exception is
	 * the case of images with wider aspect ratios than your target. Those would still
	 * have to be sized wider than the box to have the same area, but we can't do that.
	 * So we constrain those to the box, and as a result they will fill slightly less
	 * area than the other images. Everything is still constrained within our original
	 * boundary box, which is important for templates, etc. but most images of the same
	 * size will occupy the same area no matter their aspect ratio, and the result viewing
	 * sets of these images will become more balanced and visually appealing.
	 *
	 * The final step is dealing with these images in your theme. You can no longer take
	 * the box dimensions of an image as the exact size, it is now just the maximum. You
	 * may have to make some changes to prevent the reduced images from being stretched,
	 * which is often just changing width and height to max-width and max-height.
	 *
*/
add_filter( 'image_resize_dimensions', function ( $payload, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {

	// do not change sizing for cropped images, free dimension
	if ( $crop || $dest_h == 0 || $dest_w == 0 ) {
		return $payload;
	}

	/* most common minimum aspect ratios of your images (short/long)
	 * images wider than this will have reduced total area */
	$best_ratio = .6667;
	if ( $best_ratio >= 1 ) {
		$best_ratio = 1 / $best_ratio;
	}

	// reduce area to best fit area
	if ( $best_ratio ) {
		$dest_area = $dest_w * $dest_h * $best_ratio;
		// if you aren't using a reduced area, this method is no different than the default
	} else {
		return $payload;
	}

	// get image aspect ratio
	$aspect_ratio = round( $orig_h / $orig_w, 3 );

	// get fit height no more than max height
	$new_dest_h = min( [ round( sqrt( $dest_area * $aspect_ratio ) ), $dest_h ] );
	// get fit width no more than max width
	$new_dest_w = min( [ round( $dest_area / $new_dest_h ), $dest_w ] );
	// don't allow it to be off by 2
	if ( ($width_diff = abs( $dest_w - $new_dest_w )) && $width_diff < 3 ) {
		$width_diff = $dest_w/$new_dest_w;
		$new_dest_w = $dest_w;
		$new_dest_h = round($new_dest_h*$width_diff);
	}

	// calculate new image dimensions within constraint
	list( $new_w, $new_h ) = wp_constrain_dimensions( $orig_w, $orig_h, $new_dest_w, $new_dest_h );

	// if the resulting image would be the same size or larger we don't want to resize it
	if ( $new_w >= $orig_w || $new_h >= $orig_h ) {
		return false;
	}

	// the return array matches the parameters to imagecopyresampled()
	// int dst_x, int dst_y, int src_x, int src_y, int dst_w, int dst_h, int src_w, int src_h
	return array( 0, 0, 0, 0, (int) $new_w, (int) $new_h, (int) $orig_w, (int) $orig_h );
}, 10, 6 );

/* LIGHTBOX */

function sag_lightbox( $image_id ) { ?>
    <div id="sag-lightbox"><?= wp_get_attachment_image( $image_id, 'full', false ) ?></div>
<?php }

