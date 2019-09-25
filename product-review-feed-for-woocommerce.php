<?php
/**
 * Plugin Name: Product Reviews Feed for Woocommerce
 * Plugin URI: 
 * Description: A plugin that generates the Product Reviews Feed necessary as a first step for displaying product reviews in Google Shopping Ads.
 * Version: 1.0.0
 * Author: Alex Moise
 * Author URI: https://moise.pro
 */

if ( ! defined( 'ABSPATH' ) ) {	exit(0);}

// Try these only once in case feed doesn't show:
// global $wp_rewrite;
// $wp_rewrite->flush_rules();

// Add the feed in the first place:
add_action('init', 'mos_add_the_PRfeed');
function mos_add_the_PRfeed(){
        add_feed('product-reviews', 'mos_create_the_PRfeed');
}

// Then create the feed:
function mos_create_the_PRfeed(){
// Set the header, so the feed doesn't get downloaded
header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
// Then echo the feed head
echo '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns:vc="http://www.w3.org/2007/XMLSchema-versioning"
 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
 xsi:noNamespaceSchemaLocation=
 "http://www.google.com/shopping/reviews/schema/product/2.2/product_reviews.xsd">
    <version>2.2</version>
    <publisher>
        <name>Trainsane GmbH</name>
    </publisher>
    <reviews>';
// Get the products IDs
$product_ids = get_posts( array(
		'post_type' => 'product',
		'numberposts' => -1,
		'post_status' => 'publish',
		'fields' => 'ids',
) );
// Then for each product ID get some info and the comments
foreach ( $product_ids as $prod_id ) {
	$prod_title = get_the_title( $prod_id );
	$prod_url = get_permalink( $prod_id );
	$comments = get_comments('post_id='.$prod_id);
	// Then only if there are comments proceed to get them all
	if ( $comments ) {
		foreach($comments as $comment) {
			// At each comment check if there's a rating, else break out of it
			$comm_rating = get_comment_meta( $comment->comment_ID, 'rating', true );
			if ( !$comm_rating ) {break;}
			$comm_id = ($comment->comment_ID);
			$comm_author = ($comment->comment_author);
			$comm_content = ($comment->comment_content);
			$comm_date = ($comment->comment_date );
			$comm_url = $prod_url.'#comment-'.$comm_id;
			// Finally call the helper functio to output one review at a time
			mos_output_PRfeed ($prod_id, $prod_title, $prod_url, $comm_rating, $comm_id, $comm_author, $comm_date, $comm_url, $comm_content);
		}
	}
	// Unset the comments, otherwise it will be picked up at next iteration
	unset ($comments);
}
// Finally close the feed markup
echo '
    </reviews>
</feed>
';
}
// Helper function to output each review
function mos_output_PRfeed ($prod_id, $prod_title, $prod_url, $comm_rating, $comm_id, $comm_author, $comm_date, $comm_url, $comm_content) {
echo '
	<review>
		<review_id>'.$comm_id.'</review_id>
		<reviewer>
			<name>'.$comm_author.'</name>
		</reviewer>
		<review_timestamp>'.$comm_date.'</review_timestamp>
		<content>'.$comm_content.'</content>
		<review_url type="singleton">'.$comm_url.'</review_url>
		<ratings>
			<overall min="1" max="5">'.$comm_rating.'</overall>
		</ratings>
		<products>
			<product>
				<product_name>'.$prod_title.'</product_name>
				<product_url>'.$prod_url.'</product_url>
			</product>
		</products>
	</review>';
}
// 
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mos_plugin_action_links' );
function mos_plugin_action_links( $moslinks ) {
	$moslinks = array_merge( $moslinks, array(
		'<a target="_blank" href="'.get_home_url().'/feed/product-reviews/">' . __( 'Your reviews feed' ) . '</a>'
	) );
	return $moslinks;
}
?>
