<?php
/**
 * A WebSub/PubSubHubbub Publisher
 */
class PubSubHubbub_Publisher {
	/**
	 * Function that is called whenever a new post is published
	 *
	 * @param int $post_id the post-id
	 * @return int the post-id
	 */
	public static function publish_post( $post_id ) {
		// we want to notify the hub for every feed
		$feed_urls   = array();
		$feed_urls[] = get_bloginfo( 'atom_url' );
		$feed_urls[] = get_bloginfo( 'rdf_url' );
		$feed_urls[] = get_bloginfo( 'rss2_url' );

		$feed_urls = apply_filters( 'pubsubhubbub_feed_urls', $feed_urls, $post_id );

		// publish them
		pubsubhubbub_publish_to_hub( $feed_urls );
	}

	/**
	 * Function that is called whenever a new comment is published
	 *
	 * @param int $comment_id the comment-id
	 * @return int the comment-id
	 */
	public static function publish_comment( $comment_id ) {
		// get default comment-feeds
		$feed_urls   = array();
		$feed_urls[] = get_bloginfo( 'comments_atom_url' );
		$feed_urls[] = get_bloginfo( 'comments_rss2_url' );

		$feed_urls = apply_filters( 'pubsubhubbub_comment_feed_urls', $feed_urls, $comment_id );

		// publish them
		pubsubhubbub_publish_to_hub( $feed_urls );
	}

	/**
	 * Accepts either a single url or an array of urls
	 *
	 * @param string|array $topic_urls a single topic url or an array of topic urls
	 */
	public static function publish_update( $topic_urls, $hub_url ) {
		if ( ! isset( $hub_url ) ) {
			return new WP_Error( 'missing_hub_url', __( 'Please specify a hub url', 'pubsubhubbub' ) );
		}

		if ( ! preg_match( '|^https?://|i', $hub_url ) ) {
			return new WP_Error( 'invalid_hub_url', __( 'The specified hub url does not appear to be valid: ' . $hub_url, 'pubsubhubbub' ) );
		}

		if ( ! isset( $topic_urls ) ) {
			return new WP_Error( 'missing_topic_url', __( 'Please specify a topic url', 'pubsubhubbub' ) );
		}

		// check that we're working with an array
		if ( ! is_array( $topic_urls ) ) {
			$topic_urls = array( $topic_urls );
		}

		// set the mode to publish
		$post_string = 'hub.mode=publish';
		// loop through each topic url
		foreach ( $topic_urls as $topic_url ) {
			// lightweight check that we're actually working w/ a valid url
			if ( preg_match( '|^https?://|i', $topic_url ) ) {
				// append the topic url parameters
				$post_string .= '&hub.url=' . esc_url( $topic_url );
			}
		}

		$wp_version = get_bloginfo( 'version' );
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 100,
			'limit_response_size' => 1048576,
			'redirection' => 20,
			'user-agent' => "$user_agent; PubSubHubbub/WebSub",
			'body' => $post_string,
		);

		// make the http post request
		return wp_remote_post( $this->hub_url, $args );
	}

	/**
	 * The ability for other plugins to hook into the PuSH code
	 *
	 * @param array $feed_urls a list of feed urls you want to publish
	 */
	public static function publish_to_hub( $feed_urls ) {
		// remove dups (ie. they all point to feedburner)
		$feed_urls = array_unique( $feed_urls );

		// get the list of hubs
		$hub_urls = self::get_hubs();

		// loop through each hub
		foreach ( $hub_urls as $hub_url ) {
			// publish the update to each hub
			$response = self::publish_update( $feed_urls, $hub_url );

			do_action( 'pubsubhubbub_publish_update_response', $response );
		}
	}

	/**
	 * Get the endpoints from the WordPress options table
	 * valid parameters are "publish" or "subscribe"
	 *
	 * @uses apply_filters() Calls 'pubsubhubbub_hub_urls' filter
	 */
	public static function get_hubs() {
		$endpoints = get_option( 'pubsubhubbub_endpoints' );
		$hub_urls  = explode( PHP_EOL, $endpoints );

		// if no values have been set, revert to the defaults (websub on app engine & superfeedr)
		if ( ! $endpoints || ! $hub_urls || ! is_array( $hub_urls ) ) {
			$hub_urls = array(
				'https://pubsubhubbub.appspot.com',
				'https://pubsubhubbub.superfeedr.com',
			);
		}

		// clean out any blank values
		foreach ( $hub_urls as $key => $value ) {
			if ( empty( $value ) ) {
				unset( $hub_urls[ $key ] );
			} else {
				$hub_urls[ $key ] = trim( $hub_urls[ $key ] );
			}
		}

		return apply_filters( 'pubsubhubbub_hub_urls', $hub_urls );
	}
}