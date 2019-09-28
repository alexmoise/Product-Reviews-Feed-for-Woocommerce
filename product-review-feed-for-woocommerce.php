<?php
/**
 * Plugin Name: Product Reviews Feed for Woocommerce
 * Plugin URI: https://github.com/alexmoise/Product-Reviews-Feed-for-Woocommerce
 * Description: A plugin that generates the Product Reviews Feed necessary as a first step for displaying product reviews in Google Shopping Ads.
 * Version: 1.0.6
 * Author: Alex Moise
 * Author URI: https://moise.pro
 */

if ( ! defined( 'ABSPATH' ) ) {	exit(0);}

// Try these only once in case feed doesn't show:
// global $wp_rewrite;
// $wp_rewrite->flush_rules();

// === Main function: add the feed in the first place:
add_action('init', 'mos_add_the_product_feed');
function mos_add_the_product_feed(){
        add_feed('product-reviews', 'mos_create_the_product_feed');
}
// Then create the feed:
function mos_create_the_product_feed(){
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
        <name>'.get_bloginfo("name").'</name>
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
	$product = wc_get_product( $prod_id );
	$prod_sku = $product->get_sku();
	$prod_gtin = $product->get_meta( '_gtin' );
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
			$comm_date_ISO = date("c", strtotime($comm_date));
			$comm_url = $prod_url.'#comment-'.$comm_id;
			// Finally call the helper functio to output one review at a time
			mos_output_feed_item ($prod_id, $prod_title, $prod_url, $prod_sku, $prod_gtin, $comm_rating, $comm_id, $comm_author, $comm_date_ISO, $comm_url, $comm_content);
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
function mos_output_feed_item ($prod_id, $prod_title, $prod_url, $prod_sku, $prod_gtin, $comm_rating, $comm_id, $comm_author, $comm_date_ISO, $comm_url, $comm_content) {
echo '
	<review>
		<review_id>'.$comm_id.'</review_id>
		<reviewer>
			<name>'.$comm_author.'</name>
		</reviewer>
		<review_timestamp>'.$comm_date_ISO.'</review_timestamp>
		<content>'.$comm_content.'</content>
		<review_url type="singleton">'.$comm_url.'</review_url>
		<ratings>
			<overall min="1" max="5">'.$comm_rating.'</overall>
		</ratings>
		<products>
			<product>
				<product_ids>
					<gtins>
						<gtin>'.$prod_gtin.'</gtin>
					</gtins>
					<skus>
						<sku>'.$prod_sku.'</sku>
					</skus>
				</product_ids>
				<product_name>'.$prod_title.'</product_name>
				<product_url>'.$prod_url.'</product_url>
			</product>
		</products>
	</review>';
}

// === GTIN management functions below:
// Add GTIN field in simple product inventory options
add_action('woocommerce_product_options_sku', 'mos_add_gtin' );
function mos_add_gtin(){
	echo '<div id="gtin_parent_attr" class="options_group" style="width: 100%;">';
	woocommerce_wp_text_input( array(
		'id'          => '_gtin',
		'label'       => __('GTIN', 'woocommerce' ),
		'placeholder' => __('Enter GTIN here.', 'woocommerce' ),
		'desc_tip'    => true,
		'description' => __('Used by Reviews Feed for Woocommerce plugin. Enter the EAN, UPC, ISBN that will appear in the feed.', 'woocommerce' )
	) );
	echo '</div>';
}
// Add GTIN field in product variations options pricing
add_action( 'woocommerce_variation_options_pricing', 'mos_add_variation_gtin', 10, 3 );
function mos_add_variation_gtin( $loop, $variation_data, $variation ){
	echo '<div id="gtin_attr" class="options_group" style="display:inline-block; width:100%;">';
	woocommerce_wp_text_input( array(
		'id'          => '_gtin['.$loop.']',
		'label'       => __('GTIN', 'woocommerce' ),
		'placeholder' => __('Enter GTIN here', 'woocommerce' ),
		'description' => __('Used by Reviews Feed for Woocommerce plugin. Enter the EAN, UPC, ISBN that will appear in the feed.', 'woocommerce' ),
		'value'       => get_post_meta( $variation->ID, '_gtin', true )
	) );
	echo '</div><style>div#gtin_attr input { width: 100%; }</style>';
}

// Save GTIN field value for simple product inventory options
add_action('woocommerce_admin_process_product_object', 'mos_product_save_gtin', 10, 1 );
function mos_product_save_gtin( $product ){
    if( isset($_POST['_gtin']) )
        $product->update_meta_data( '_gtin', sanitize_text_field($_POST['_gtin']) );
}
// Save GTIN field value from product variations options pricing
add_action( 'woocommerce_save_product_variation', 'mos_variation_item_save_gtin', 10, 2 );
function mos_variation_item_save_gtin( $variation_id, $i ){
    if( isset($_POST['_gtin'][$i]) ){
        update_post_meta( $variation_id, '_gtin', sanitize_text_field($_POST['_gtin'][$i]) );
    }
}
// Save GTIN to order items (and display it on admin orders)
add_filter( 'woocommerce_checkout_create_order_line_item', 'mos_order_item_save_gtin', 10, 4 );
function mos_order_item_save_gtin( $item, $cart_item_key, $cart_item, $order ) {
    if( $value = $cart_item['data']->get_meta('_gtin') ) {
        $item->update_meta_data( '_gtin', esc_attr( $value ) );
    }
    return $item_qty;
}
/* 
// Woocommerce does NOT store reviews per variation - but GTINs MUST be assigned to variations - so we have a situation here.
// For the moment we'll fall back doing the feed only for non-variable products, ehh
// ... BUT we'll need these later when we'll use reviews per variation
// *****
// Hide GTIN field in parent product if product is variable - AND display a hint about this!
add_action ('woocommerce_product_options_sku', 'mos_hide_gtin_field');
function mos_hide_gtin_field() {
	global $post;
	$product = wc_get_product( $post->ID );
	$prod_type = $product->get_type();
	if( $prod_type == 'variable' ) {
		echo '<style>div#gtin_parent_attr { display: none; }</style>
		<p class="form-field"><label>GTIN</label>This is a variable product, please enter GTIN in each variation!</p>';
	} else {
		echo '<style>div#gtin_parent_attr { display: inline-block; }</style>';
	}
}
// The quick way to get all variation IDs:
if( $product->get_type() == 'variable' ) { 
	$children_ids = $product->get_children(); 
	echo 'Children:<pre>'; print_r($children_ids); echo '</pre>';
}
*/

// === Add the feed link to plugin action links, for convenience
add_action( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mos_plugin_action_links' );
function mos_plugin_action_links( $moslinks ) {
	$moslinks = array_merge( $moslinks, array(
		'<a target="_blank" href="'.get_home_url().'/feed/product-reviews/">' . __( 'Your reviews feed' ) . '</a>'
	) );
	return $moslinks;
}
?>
