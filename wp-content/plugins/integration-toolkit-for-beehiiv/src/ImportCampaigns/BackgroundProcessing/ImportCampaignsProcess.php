<?php
/**
 * Background process for importing fetched campaigns into WordPress.
 *
 * @package ITFB_Beehiiv
 * @subpackage Importcampaigns\BackgroundProcessing
 * @since 1.0.0
 */

namespace ITFB\ImportCampaigns\BackgroundProcessing;

use WP_Background_Process;
use ITFB\ImportCampaigns\Helper;
use ITFB\ImportCampaigns\ImportTable;

/**
 * Processes for importing fetched campaigns into WordPress.
 */
class ImportCampaignsProcess extends WP_Background_Process {

    /**
     * The prefix for the background process.
     *
     * @var string
     */
    protected $prefix = 'ITFB_Beehiiv';

    /**
     * The batch size for the background process.
     *
     * @var int Batch size
     */
    protected $batch_size = 5;

    /**
     * The action name for importing campaigns.
     *
     * @var string
     */
    protected $action = 'import_campaigns';

    /**
     * Perform task with queued item.
     *
     * @param mixed $item Queue item to iterate over.
     * @return mixed
     */
    protected function task( $item ) {
        $this->import_campaign( unserialize( $item ) );
        return false;
    }

    /**
     * Complete processing.
     */
    protected function complete() {
        parent::complete();
    }

    /**
     * Import the campaign.
     *
     * @param object $item The campaign object.
     */
    protected function import_campaign( $item ) {

        // --- Fetch campaign safely ---
        $campaign_data = ImportTable::get_and_decode_campaign_data( trim( $item['campaign_id'] ), trim( $item['group_name'] ) );

        // Validate campaign data before continuing
        if ( empty( $campaign_data ) || ! is_array( $campaign_data ) ) {
            error_log('[Beehiiv Import] Skipping invalid campaign payload for ID ' . ( $item['campaign_id'] ?? 'unknown' ));
            // Delete the invalid row so it doesn’t get retried endlessly
            ImportTable::delete_custom_table_row( trim( $item['campaign_id'] ), trim( $item['group_name'] ) );
            return false;
        }

        // Assign valid data back to item
        $item['campaign'] = $campaign_data;

        // Clean up table entry (no retries)
        ImportTable::delete_custom_table_row( trim( $item['campaign_id'] ), trim( $item['group_name'] ) );

        // Validate essential keys to prevent null access warnings
        if (
            empty( $item['campaign']['title'] ) ||
            empty( $item['campaign']['content'] ) ||
            ! isset( $item['campaign']['status'] )
        ) {
            error_log('[Beehiiv Import] Missing required fields for campaign ID ' . $item['campaign_id']);
            return false;
        }

        // Determine audience type
        $content_type = ( 'free' === ( $item['params']['audience'] ?? '' ) || 'all' === ( $item['params']['audience'] ?? '' ) )
            ? 'free' : 'premium';

        // Safely access nested content
        $post_content = '';
        if ( isset( $item['campaign']['content'][ $content_type ]['web'] ) ) {
            $post_content = Helper::filter_campaign_content( $item['campaign']['content'][ $content_type ]['web'] );
        } else {
            error_log('[Beehiiv Import] Missing expected HTML content for campaign ID ' . $item['campaign_id']);
        }

        // Build post args
        $wp_post_args = array(
            'post_title'   => sanitize_text_field( $item['campaign']['title'] ?? '(Untitled Campaign)' ),
            'post_slug'    => sanitize_title( $item['campaign']['slug'] ?? '' ),
            'post_content' => $post_content,
            'post_status'  => sanitize_text_field( $item['params']['post_status'][ $item['campaign']['status'] ] ?? 'draft' ),
            'post_type'    => sanitize_text_field( $item['params']['post_type'] ?? 'post' ),
        );

        // Set the post date
        if ( ! empty( $item['campaign']['publish_date'] ) ) {
            $post_date_gmt = gmdate( 'Y-m-d H:i:s', $item['campaign']['publish_date'] );
            $wp_post_args['post_date']     = get_date_from_gmt( $post_date_gmt );
            $wp_post_args['post_date_gmt'] = $post_date_gmt;
        }

        // Handle taxonomy/terms
        if ( ! empty( $item['params']['taxonomy'] ) && ! empty( $item['params']['taxonomy_term'] ) ) {
            $term = term_exists( intval( $item['params']['taxonomy_term'] ), $item['params']['taxonomy'] );
            if ( $term ) {
                $wp_post_args['tax_input'] = array(
                    $item['params']['taxonomy'] => array( $term['term_id'] ),
                );
            }
        }

        // Handle Beehiiv tags (categories or post tags)
        if ( ! empty( $item['campaign']['content_tags'] ) && is_array( $item['campaign']['content_tags'] ) ) {
            $tags = $item['campaign']['content_tags'];
            if ( 'category' === ( $item['params']['import_cm_tags_as'] ?? '' ) ) {
                $tag_ids = array();
                foreach ( $tags as $tag ) {
                    $term = term_exists( $tag, 'category' );
                    if ( ! $term ) {
                        $term = wp_insert_term( $tag, 'category' );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $tag_ids[] = $term['term_id'];
                    }
                }
                $wp_post_args['post_category'] = $tag_ids;
            } elseif ( 'post_tag' === ( $item['params']['import_cm_tags_as'] ?? '' ) ) {
                $wp_post_args['tags_input'] = $tags;
            }
        }

        // Assign author
        if ( ! empty( $item['params']['author'] ) ) {
            $wp_post_args['post_author'] = intval( $item['params']['author'] );
        }

        // Meta fields (safe defaults)
        $wp_post_args['meta_input'] = array(
            'beehiiv_campaign_id'     => $item['campaign']['id'] ?? '',
            'beehiiv_web_version_url' => $item['campaign']['web_url'] ?? '',
            'beehiiv_authors'         => isset( $item['campaign']['authors'] ) ? serialize( $item['campaign']['authors'] ) : '',
            'beehiiv_audience'        => isset( $item['campaign']['audience'] ) ? serialize( $item['campaign']['audience'] ) : '',
            'write_description'       => $item['campaign']['subtitle'] ?? '',
        );

        // Insert or update
        if ( isset( $item['campaign']['wp_status'] ) && 'existing' === $item['campaign']['wp_status'] ) {
            $wp_post_args['ID'] = $item['campaign']['wp_post_id'] ?? 0;
            $post_id = wp_update_post( $wp_post_args );
        } else {
            $post_id = wp_insert_post( $wp_post_args );
        }

        // Featured image
        if ( ! empty( $item['campaign']['thumbnail_url'] ) ) {
            $thumbnail_id = Helper::itfb_set_post_thumbnail( $post_id, $item['campaign']['thumbnail_url'] );
            if ( $thumbnail_id ) {
                set_post_thumbnail( $post_id, $thumbnail_id );
            }
        }

        return $post_id;
    }
}
