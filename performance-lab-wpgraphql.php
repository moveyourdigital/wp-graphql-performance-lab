<?php
/**
 * Plugin Name: WPGraphQL Performance Lab
 * Plugin URI: https://github.com/moveyourdigital/wp-graphql-performance-lab
 * Description: WPGraphQL plugin to support Performance Lab plugin features
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Version: 1.2.0
 * Author: Move Your Digital
 * Author URI: https://moveyourdigital.com
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-graphql-performance-lab
 *
 * @package wp-graphql-performance-lab
 */

namespace WPGraphQL\Performance_Lab;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * This version must be tied together with
 * https://github.com/WordPress/performance
 *
 * If they implement stuff, this should support
 * that implementation.
 */
define('WPGRAPHQL_PERFLAB_VERSION', '1.2.0');

/**
 * Fire an action as the type registry is initialized. This executes
 * before the `graphql_register_types` action to allow for earlier hooking
 *
 * @param TypeRegistry $registry Instance of the TypeRegistry
 */
add_action('graphql_register_types', function () {

  /**
   * Register the values for variant argument
   *
   * @type MediaItemPerformanceLabVariant
   */
  register_graphql_enum_type('MediaItemPerformanceLabVariant', [
    'values' => [
      'ORIGINAL' => [
        'value' => 'original',
      ],
      'WEBP' => [
        'value' => 'webp',
      ],
    ]
  ]);

  /**
   * Register the values for format argument
   *
   * @type MediaItemPerformanceLabDominantColorFormatArg
   */
  register_graphql_enum_type('MediaItemPerformanceLabDominantColorFormat', [
    'values' => [
      'HEX' => [
        'value' => 'hex',
      ],
    ]
  ]);

  /**
   * Implements an interface MediaItemPerformanceLab
   * that is going to be registered on both mediaItem and MediaItem
   *
   */
  register_graphql_interface_type('MediaItemPerformanceLab', [
    'fields' => [
      'dominantColor' => [
        'type' => 'string',
        'args' => [
          'format' => [
            'type' => 'MediaItemPerformanceLabDominantColorFormat',
            'description' => __('The format to return color', 'wp-graphql-performance-lab'),
          ],
        ],
        'description' => __(
          'The dominant color calculated for the image to use as placeholder background with that color.',
          'wp-graphql-performance-lab'
        ),
        'resolve' => function (\WPGraphQL\Model\Post $source, array $args) {
          $meta = wp_get_attachment_metadata($source->databaseId);
          $color = $meta['dominant_color'];

          switch ($args['format']) {
            case 'hex':
            default:
              return "#" . $color;
          }
        },
      ],

      'hasTransparency' => [
        'type' => 'boolean',
        'description' => __('Whether the image has transparent pixels.', 'wp-graphql-performance-lab'),
        'resolve'     => function (\WPGraphQL\Model\Post $source) {
          $meta = wp_get_attachment_metadata($source->databaseId);
          return !!$meta['has_transparency'];
        },
      ],

      'srcSet' => [
        'type' => 'string',
        'args' => [
          'size' => [
            'type' => 'MediaItemSizeEnum',
            'description' => __('Size of the MediaItem to calculate srcSet with', 'wp-graphql'),
          ],
          'variant' => [
            'type' => 'MediaItemPerformanceLabVariant',
            'description' => __(
              'Variant of the MediaItem to calculate srcSet with.',
              'wp-graphql-performance-lab'
            ),
          ]
        ],
        'description' => __(
          'The srcset attribute specifies the URL of the image to use in different situations. It is a comma separated string of urls and their widths.',
          'wp-graphql'
        ),
        'resolve'     => function (\WPGraphQL\Model\Post $source, array $args) {
          $size = 'medium';
          if (!empty($args['size'])) {
            $size = $args['size'];
          }

          $variant = 'original';
          if (!empty($args['variant'])) {
            $variant = $args['variant'];
          }

          switch ($variant) {
            case 'webp':
              /**
               * Filters the attachment meta data.
               *
               * @since 2.1.0
               *
               * @param array $data          Array of meta data for the given attachment.
               * @param int   $attachment_id Attachment post ID.
               */
              add_filter(
                'wp_get_attachment_metadata',
                '\\WPGraphQL\\Performance_Lab\\_wpgraphql_filter_webp_wp_calculate_image_srcset_meta',
                10, 2
              );

              // now, the srcset returns webp images only
              $src_set = wp_get_attachment_image_srcset($source->ID, $size);

              /** remove the previous filter immediatly */
              remove_filter(
                'wp_get_attachment_metadata',
                '\\WPGraphQL\\Performance_Lab\\_wpgraphql_filter_webp_wp_calculate_image_srcset_meta',
                10
              );
              break;

            default:
              $src_set = wp_get_attachment_image_srcset($source->ID, $size);
          }

          return !empty($src_set) ? $src_set : null;
        },
      ],
    ],
  ]);

  /**
   * Register MediaItemPerformanceLab to MediaItem
   */
  register_graphql_interfaces_to_types(
    ['MediaItemPerformanceLab'],
    ['mediaItem', 'MediaItem']
  );

}, 10, 1);

/**
 * When an image has webp versions available, this
 * function will hack the image metadata structure
 * to replace every jpeg file for the webp counterpart.
 *
 * If not available in some size, this simply unsets that
 * size, so no mixing formats are returned, safely returning
 * srcsets with webp images only.
 *
 * @since 1.2.0
 *
 * @param array $data          Array of meta data for the given attachment.
 * @param int   $attachment_id Attachment post ID.
 */
function _wpgraphql_filter_webp_wp_calculate_image_srcset_meta(
  $image_meta,
  $attachment_id
) {
  $mimetype = get_post_mime_type($attachment_id);

  // This image is already an webp, no further work is needed
  if ($mimetype == 'image/webp') {
    return $image_meta;
  }

  // If the webp source is available, replace the current
  // full/original version of a jpeg with the webp.
  if (isset($image_meta['sources']['image/webp'])) {
    $image_meta['file'] = dirname($image_meta['file'])
      . '/' . $image_meta['sources']['image/webp']['file'];
  }

  // For each registered size...
  foreach ($image_meta['sizes'] as $size => $args) {
    // unsets the size if no webp version is available
    if (empty($args['sources']['image/webp'])) {
      unset($image_meta['sizes'][$size]);
      continue;
    }

    $meta = $image_meta['sizes'][$size];

    // replaces file with webp version
    $meta['file'] = $args['sources']['image/webp']['file'];
    $meta['mime-type'] = 'image/webp';

    $image_meta['sizes'][$size] = $meta;
  }

  return $image_meta;
}
