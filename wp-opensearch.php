<?php
/*
 Plugin Name: WP OpenSearch
 Plugin URI: http://longjohndesign.blogspot.com/2011/01/wp-opensearch-wordpress-opensearch.html
 Description: Add OpenSearch standard support for search clients to your website implementing the <a href="http://www.opensearch.org/Specifications/OpenSearch/1.1">OpenSearch Specification 1.1</a>.
 Version: 1.0
 Author: Fabio Savina
 Author URI: http://longjohndesign.blogspot.com/
 */


if (!is_admin())
{
	add_action ( 'init', 'wp_opensearch_flush_rewrite_rules' );
	add_action ( 'query_vars', 'wp_opensearch_query_vars' );
	add_action ( 'generate_rewrite_rules','wp_opensearch_add_rewrite_rules' );
	
	add_filter ( 'redirect_canonical', 'wp_opensearch_canonical', 10, 2 );
	
	add_action ( 'wp_head', 'wp_opensearch_head' );
	add_action ( 'template_redirect', 'wp_opensearch_template_redirect' );
} else {
	add_action ('admin_menu', 'wp_opensearch_admin_menu' );
	
	if (isset($_POST['wp_opensearch_save']))
	{
		wp_opensearch_save_options();
	}
}

//add_action('widgets_init', 'wp_opensearch_widgets_init');


function wp_opensearch_flush_rewrite_rules()
{
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}


function wp_opensearch_query_vars($vars)
{
	$vars[] = 'wp_opensearch_description';
	if (wp_opensearch_get_option('suggestions'))
		$vars[] = 'wp_opensearch_suggest';
	return $vars;
}


function wp_opensearch_add_rewrite_rules($wp_rewrite)
{
	global $wp_rewrite;
	$rules = array(
		'osd.xml' => $wp_rewrite->index . '?wp_opensearch_description=1'
	);
	if (wp_opensearch_get_option('suggestions'))
	{
		$rules['suggest'] = $wp_rewrite->index . '?wp_opensearch_suggest=1';
	}
		
	$wp_rewrite->rules = $rules + $wp_rewrite->rules;
	return $wp_rewrite;
}


function wp_opensearch_canonical($redirect_url, $requested_url) {
	if ( substr($requested_url, -7) == 'osd.xml' )
		return FALSE;
	return $redirect_url;
}


function wp_opensearch_head($head)
{
	if (wp_opensearch_get_option('autodiscovery'))
	{
		echo '<link rel="search" href="' . get_bloginfo ( 'url' ) . '?wp_opensearch_description=1" type="application/opensearchdescription+xml" title="' . wp_opensearch_get_option('short_name') . '"/>';
	}
}


function wp_opensearch_template_redirect()
{
	global $wp_query;
	if (!empty($wp_query -> query_vars['wp_opensearch_description']))
	{
		wp_opensearch_serve_description();
	}
	if (!empty($wp_query -> query_vars['wp_opensearch_suggest']))
	{
		wp_opensearch_suggest();
	}
	return;
}


function wp_opensearch_serve_description()
{
	require_once ('phpOSD.php');
	$osd = new phpOSD ( wp_opensearch_get_option('short_name'), wp_opensearch_get_option('description', ''));
	
	$osd -> language = get_locale();
	$osd -> developer = 'WP OpenSearch plugin for Wordpress';
	
	$osd -> addUrl ( get_bloginfo ( 'url' ) . '?s=' );
	$osd -> addUrl ( get_bloginfo ( 'url' ) . '?feed=rss2&s=', 'application/rss+xml' );
	$osd -> addUrl ( get_bloginfo ( 'url' ) . '?feed=atom&s=', 'application/atom+xml' );
	
	if (wp_opensearch_get_option('suggestions'))
		$osd -> autocomplete ( get_bloginfo ( 'url' ) . '/suggest?s=' );	
	
	if ($contact = wp_opensearch_get_option('contact'))
		$osd -> contact = $contact;
	
	if ($tags = wp_opensearch_get_option('tags'))
		$osd -> tags = $tags;
	
	if ($example_query = wp_opensearch_get_option('example_query'))
		$osd -> exampleQuery = $example_query;
	
	if ($attribution = wp_opensearch_get_option('attribution'))
		$osd -> attribution = $attribution;
	
	if ($long_name = wp_opensearch_get_option('long_name'))
		$osd -> longName = $long_name;
	
	if ($syndication_right = wp_opensearch_get_option('syndication_right'))
		$osd -> syndicationRight = $syndication_right;
	
	if ($small_icon = wp_opensearch_get_option('small_icon'))
		$osd -> smallIcon = $small_icon;
	
	$osd -> adultContent = wp_opensearch_get_option('adult_content');
	
	$osd -> serve();
	exit();
}


function wp_opensearch_get_option($option, $default = FALSE)
{
	switch ($option)
	{
		case 'short_name':
		case 'description':
			$default = get_bloginfo('name');
			break;
		case 'attribution':
			$default = 'Copyright© ' . date('Y') . ' ' . get_bloginfo('name');
			break;
		case 'suggestions':
		case 'autodiscovery':
			$default = 1;
		case 'contact':
			$default = get_bloginfo('admin_email');
			break;
	}
	$value = get_option("wp_opensearch_{$option}");
	return strlen ( $value ) ? $value : $default;
}


function wp_opensearch_update_option($option, $value)
{
	update_option("wp_opensearch_{$option}", $value);
}


function wp_opensearch_suggest()
{
	if (isset($_GET['s']) and strlen($_GET['s']))
	{
		$result = array();
		$result[] = $_GET['s'];
		
		$suggestions = array();
		$terms = get_terms('post_tag', 'number=10&orderby=count&order=DESC&name__like=' . $_GET['s']);
		if (count($terms))
		{
			$counts = array();
			$urls = array();
			foreach($terms as $term)
			{
				$suggestions[] = $term -> name;
				$counts[] = $term -> count . ' ' . _(($term -> count == 1) ? 'result' : 'results');
				$urls[] = get_bloginfo('url').'?s=' . $term -> name;
			}
		}
		$result[] = $suggestions;
		$result[] = $counts;
		$result[] = $urls;
		
		header("Content-type: application/x-suggestions+json");
		echo json_encode($result);
		exit();		
	}
}



function wp_opensearch_admin_menu()
{
	add_options_page('WP OpenSearch', 'WP OpenSearch', 10, 'wp-opensearch', 'wp_opensearch_options_page');
}


function wp_opensearch_options_page()
{
	?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>WP OpenSearch</h2>
		<?php if (isset($_GET['wp_opensearch_updated']) and $_GET['wp_opensearch_updated']):?>
			<div class="updated">
				<p><strong><?php _e('Settings saved.');?></strong></p>
			</div>
		<?php endif; ?>
		<div class="metabox-holder has-right-sidebar">
			<div class="inner-sidebar">
				<div style="position:relative;" class="meta-box-sortabless ui-sortable" id="side-sortables">
					<div class="postbox" id="dm_donations">
						<h3 class="hndle"><span><?php _e('Make a donation'); ?></span></h3>
						<div class="inside">
							<p style="text-align:center;"><strong><?php _e('Thanks for your support!');?></strong></p>
							<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank" style="text-align:center;">
								<input type="text" name="amount" value="2.00" style="width:50px;text-align:right;"/>$
								<input type="hidden" name="cmd" value="_donations"/>
								<input type="hidden" name="business" value="5QUG426XZWQSJ"/>
								<input type="hidden" name="lc" value="US"/>
								<input type="hidden" name="item_name" value="WP OpenSearch Plugin"/>
								<input type="hidden" name="item_number" value="1.0"/>
								<input type="hidden" name="currency_code" value="USD"/>
								<input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHosted"/>
								<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!" style="vertical-align:middle;"/>
								<img alt="" border="0" src="https://www.paypal.com/it_IT/i/scr/pixel.gif" width="1" height="1"/>
							</form>
						</div>
					</div>
				</div>
			</div>
			
			<form method="post" action="options-general.php?page=wp-opensearch">
				<div class="has-sidebar sm-padded">
					<div class="has-sidebar-content" id="post-body-content">
						<div class="meta-box-sortabless">
							<div class="postbox">
								<h3 class="hndle"><?php _e('Search Engine Description'); ?></h3>
								<div class="inside">
									<table class="form-table" style="clear:none;">
										<tbody>
											<tr valign="top">
												<th scope="row"><label for="short_name"><?php _e('Short name'); ?>:</label></th>
												<td><input type="text" id="short_name" name="short_name" class="regular-text" value="<?php echo wp_opensearch_get_option('short_name'); ?>"/></td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="long_name"><?php _e('Long Name'); ?>:</label></th>
												<td>
													<input type="text" id="long_name" name="long_name" class="regular-text" value="<?php echo wp_opensearch_get_option('long_name'); ?>"/><br/>
													<span class="description"><?php _e('An extended human-readable title that identifies this search engine.');?></span>
												</td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="description"><?php _e('Description'); ?>:</label></th>
												<td><input type="text" id="description" name="description" class="regular-text" value="<?php echo wp_opensearch_get_option('description', ''); ?>"/></td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="tags"><?php _e('Tags'); ?>:</label></th>
												<td>
													<input type="text" id="tags" name="tags" class="regular-text" value="<?php echo wp_opensearch_get_option('tags', ''); ?>"/><br/>
													<span class="description"><?php _e('A set of single words separated by space character (" ") that are used to categorize the search content.');?></span>
												</td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="attribution"><?php _e('Attribution'); ?>:</label></th>
												<td><input type="text" id="attribution" name="attribution" class="regular-text" value="<?php echo wp_opensearch_get_option('attribution', ''); ?>"/></td>
											</tr>
											<tr valign="top">
												<?php $small_icon = wp_opensearch_get_option('small_icon', '') ;?>
												<th scope="row"><label for="small_icon"><?php _e('Small Icon'); ?> (16x16 - ico):</label></th>
												<td><input type="text" id="small_icon" name="small_icon" class="regular-text code" value="<?php echo $small_icon; ?>"/>
													<?php if ($small_icon ):?><img src="<?php echo $small_icon ; ?>" style="vertical-align:middle;"/><?php endif;?>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
							<div class="postbox">
								<h3 class="hndle"><?php _e('Other Informations'); ?></h3>
								<div class="inside">
									<table class="form-table" style="clear:none;">
										<tbody>
											<tr valign="top">
												<th scope="row"><label for="contact"><?php _e('Contact'); ?>:</label></th>
												<td>
													<input type="text" id="contact" name="contact" class="regular-text" value="<?php echo wp_opensearch_get_option('contact', ''); ?>"/><br/>
													<span class="description"><?php _e('A valid email address at which the maintainer of the search engine can be reached.'); ?></span>
												</td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="large_icon"><?php _e('Large Icon'); ?> (64x64 - jpg,png):</label></th>
												<td><input type="text" id="large_icon" name="large_icon" class="regular-text code" value="<?php echo wp_opensearch_get_option('large_icon', ''); ?>"/></td>
											</tr>
											<tr valign="top">
												<?php $syndication_right = wp_opensearch_get_option('syndication_right', '');?>
												<th scope="row"><label for="syndication_right"><?php _e('Syndication right'); ?>:</label></th>
												<td><select id="syndication_right" name="syndication_right">
													<option value="" <?php echo $syndication_right == '' ? 'selected="selected"' : ''?>>auto</option>
													<option value="open" <?php echo $syndication_right == 'open' ? 'selected="selected"' : ''?>>open</option>
													<option value="limited" <?php echo $syndication_right == 'limited' ? 'selected="selected"' : ''?>>limited</option>
													<option value="private" <?php echo $syndication_right == 'private' ? 'selected="selected"' : ''?>>private</option>
													<option value="closed" <?php echo $syndication_right == 'closed' ? 'selected="selected"' : ''?>>closed</option>
												</select><br/>
												<span class="description"><?php _e('Indicates the degree to which the search results provided by this search engine can be queried, displayed and redistributed'); ?></span>
												</td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="adult_content"><?php _e('Adult Content'); ?>:</label></th>
												<td>
													<input type="checkbox" id="adult_content" name="adult_content" class="regular-text code" value="1" <?php echo wp_opensearch_get_option('adult_content') ? 'checked="checked"' : ''; ?>/>
													<?php _e('Search results may contain material intended only for adults');?>
												</td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="example_query"><?php _e('Example Query'); ?>:</label></th>
												<td>
													<input type="text" id="example_query" name="example_query" class="regular-text" value="<?php echo wp_opensearch_get_option('example_query', ''); ?>"/><br/>
													<span class="description"><?php _e('An example search query that is expected to return search results.');?></span>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
							<div class="postbox">
								<h3 class="hndle"><?php _e('Search Engine Configuration'); ?></h3>
								<div class="inside">
									<table class="form-table" style="clear:none;">
										<tbody>
											<tr valign="top">
												<th scope="row"><label for="suggestions"><?php _e('Suggestions'); ?>:</label></th>
												<td>
													<input type="checkbox" id="suggestions" name="suggestions" class="regular-text code" value="1" <?php echo wp_opensearch_get_option('suggestions') ? 'checked="checked"' : ''; ?>/>
													<?php _e('Activate suggestions for this search engine');?>
												</td>
											</tr>
											<tr valign="top">
												<th scope="row"><label for="autodiscovery"><?php _e('Autodiscovery'); ?>:</label></th>
												<td>
													<input type="checkbox" id="autodiscovery" name="autodiscovery" class="regular-text code" value="1" <?php echo wp_opensearch_get_option('autodiscovery') ? 'checked="checked"' : ''; ?>/>
													<?php _e('Embed an autodiscovery link in HTML code');?>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
							<p><input type="submit" value="<?php esc_attr_e('Save Changes') ?>" class="button-primary" name="wp_opensearch_save"/></p>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
	<?php
}


function wp_opensearch_save_options()
{
	$options = array('short_name', 'long_name', 'description', 'small_icon', 'large_icon', 'syndication_right', 'tags', 'example_query', 'attribution', 'contact', 'adult_content', 'suggestions', 'autodiscovery');
	foreach($options as $option)
	{
		switch ($option)
		{
			case 'adult_content':
			case 'suggestions':
			case 'autodiscovery':
				if (isset($_POST[$option]))
				{
					wp_opensearch_update_option($option, $_POST[$option]);
				} else
				{
					wp_opensearch_update_option($option, 0);
				}
				break;
			default:
				if (isset($_POST[$option]))
				{
					wp_opensearch_update_option($option, $_POST[$option]);
				}
				break;
		}
	}
	header('Location: options-general.php?page=wp-opensearch&wp_opensearch_updated=1');
	exit();
}

/*
function wp_opensearch_widgets_init()
{
	register_widget('WP_OpenSearch_AddSearchProvider');
}


class WP_OpenSearch_AddSearchProvider extends WP_Widget
{
	
	function WP_OpenSearch_AddSearchProvider()
	{
		$widget_ops = array( 'description' => __( 'Create a link to add your search provider to the user browser.' ) );
		$this->WP_Widget ( 'opensearch_add_provider', __('Add Search Provider'), $widget_ops );
	}
	

	function widget($args, $instance)
	{
		extract($args);
		
		echo $before_widget;
		$title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
		if ( $title)
			echo $before_title . $title . $after_title;
		?>
		<p>
			<a href="#" onclick="window.external.AddSearchProvider('<?php echo get_bloginfo ( 'url' ); ?>/osd.xml');return false;"><?php echo $instance['anchor_text']; ?></a>
		</p>
		<?php
		echo $after_widget;
	}
	
	
	function form($instance)
	{
		$instance = wp_parse_args( (array) $instance, array('title'=>'', 'anchor_text'=>'') );
		?>
		<p><strong><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title'); ?>:</label></strong>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $instance['title']; ?>" /></p>
		
		<p><strong><label for="<?php echo $this->get_field_id('anchor_text'); ?>"><?php _e('Anchor Text'); ?>:</label></strong>
		<input class="widefat" id="<?php echo $this->get_field_id('anchor_text'); ?>" name="<?php echo $this->get_field_name('anchor_text'); ?>" type="text" value="<?php echo $instance['anchor_text']; ?>" /></p>
		<?php
	}
	
	
	function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['anchor_text'] = strip_tags($new_instance['anchor_text']);
		return $instance; 
	}
	
}*/





