<?php
/*
Plugin Name: WP Flex Gallery
Plugin URI: 
Description: FlexSlider implementation for WP galleries
Version: 0.3
Author: hypedtext
Author URI: 
License: GPLv3 or later
*/ 

global $wpfg;
$wpfg = new WP_Flex_Gallery;
       
class WP_Flex_Gallery {
	
	var $dir;
	var $default_slider_options;
	var $slider_options;
		
	function __construct() {
		
		$this->dir = plugins_url('',__FILE__);
		
		$this->default_slider_options_js = array(
			'animation' => 'slide',
			'direction' => 'horizontal',
			'slideshowSpeed' => '7000',
			'animationSpeed' => '600',
			'smoothHeight' => '0',
			'slideshow' => '1',
			'randomize' => '0',
			'directionNav' => '1'
		);
		
		$this->default_slider_options_other = array(
			'slider' => 0,
			'link' => 'attachment',
			'carouselPosition' => 'top'
		);
		
		$options = get_option('wpfg_options');
		if(is_array($options['slider_js']))
			$this->slider_options['slider_js'] = array_merge($this->default_slider_options_js, $options['slider_js']);
		else
			$this->slider_options['slider_js'] = $this->default_slider_options_js;
			
		if(is_array($options['other']))
			$this->slider_options['other'] = array_merge($this->default_slider_options_other, $options['other']);
		else
			$this->slider_options['other'] = $this->default_slider_options_other;
		
		add_action('wp_enqueue_scripts', array($this,'register_scripts_styles'));
		
		remove_shortcode( 'gallery' );
		add_shortcode( 'gallery' , array($this,'flexslider_gallery_shortcode') );
		add_shortcode( 'galleryx' , array($this,'flexslider_galleryx_shortcode') );
		
		add_action('admin_init', array($this,'init') );
		add_action( 'admin_menu', array($this,'admin_page') );
	}
	
	function register_scripts_styles() {
		wp_enqueue_script( 'jquery' );
		
		wp_register_script('FlexSlider', $this->dir.'/FlexSlider/jquery.flexslider-min.js', array('jquery'));
		wp_enqueue_script('FlexSlider');
		
		wp_register_style('FlexSlider', $this->dir.'/FlexSlider/flexslider.css');
		wp_enqueue_style('FlexSlider');		
	}
	
	function flexslider_gallery_shortcode($attr) {
		$options = $this->get_value('slider_options');
		
		if(($attr['slider'] != 'on' && $options['other']['slider'] != 1) || $attr['slider'] == 'off')
			return gallery_shortcode($attr);
		else
			return $this->flexslider_gallery($attr, $options);
	}
	
	function flexslider_galleryx_shortcode($attr) {
		return $this->flexslider_gallery($attr, $this->get_value('slider_options'));
	}
	
	function flexslider_gallery($attr, $options) {
        $post = get_post();
	
		static $instance = 0;
		$instance++;
	
		if ( ! empty( $attr['ids'] ) ) {
			// 'ids' is explicitly ordered, unless you specify otherwise.
			if ( empty( $attr['orderby'] ) )
				$attr['orderby'] = 'post__in';
			$attr['include'] = $attr['ids'];
		}
	
		// We're trusting author input, so let's at least make sure it looks like a valid orderby statement
		if ( isset( $attr['orderby'] ) ) {
			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
			if ( !$attr['orderby'] )
				unset( $attr['orderby'] );
		}
		
		//turns every array name with prefix to deeper level array
		$result = array();
		foreach ($attr as $k => $v) {
			$name = explode('_', $k);
			$newkey = array_shift($name);
			$newname = implode('_', $name);
			if($newname != '')
				$attr_split[$newkey][$newname] = $v;
		}
		if(is_array($attr_split['slider'])) {
			$options['slider_js'] = array_merge($options['slider_js'], $attr_split['slider']);
			if(isset($options['slider_js']['carouselPosition'])) {
				unset($options['slider_js']['carouselPosition']);
				$options['other']['carouselPosition'] = $attr_split['slider']['carouselPosition'];
			}
		}	
	
		extract(shortcode_atts(array(
			'order'      => 'ASC',
			'orderby'    => 'menu_order ID',
			'id'         => $post->ID,
			'size'       => 'large',
			'include'    => '',
			'exclude'    => ''
		), $attr));
	
		$id = intval($id);
		if ( 'RAND' == $order )
			$orderby = 'none';
	
		if ( !empty($include) ) {
			$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
	
			$attachments = array();
			foreach ( $_attachments as $key => $val ) {
				$attachments[$val->ID] = $_attachments[$key];
			}
		} elseif ( !empty($exclude) ) {
			$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		} else {
			$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		}
	
		if ( empty($attachments) )
			return '';
	
		if ( is_feed() ) {
			$output = "\n";
			foreach ( $attachments as $att_id => $attachment )
				$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
			return $output;
		}
	
		$selector = "gallery-{$instance}";
		
		if($options['other']['carouselPosition'] != 'hide') {
			$carousel = "<div id='carousel-$selector' class='flexslider flexslider-carousel'><ul class='slides'>";
		
			foreach ( $attachments as $id => $attachment ) {
				$image = wp_get_attachment_image($id, 'thumbnail');
		
				$carousel .= "<li>$image</li>";
			}
			
			$carousel .= '</ul></div>';
		}
		
		$output = "";
		
		if($options['other']['carouselPosition'] == 'top')
			$output .= $carousel;
	
		$output .= "<div id='slider-$selector' class='flexslider'><ul class='slides'>";
	
		foreach ( $attachments as $id => $attachment ) {
            
            if($options['other']['link'] == 'file')
                $link = wp_get_attachment_link($id, $size, false, false);
            elseif($options['other']['link'] == 'none')
                $link = wp_get_attachment_image( $id, $size );
            else
                $link = wp_get_attachment_link($id, $size, true, false);
            
			/*if(isset($attr['link']))
				$link = 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);
			else { 
				if($options['other']['link'] == 'file')
					$link = wp_get_attachment_link($id, $size, false, false);
				elseif($options['other']['link'] == 'none')
					$link = wp_get_attachment_image( $id, $size );
				else
					$link = wp_get_attachment_link($id, $size, true, false);
			}*/
			
			$output .= "<li>$link</li>";
		}
		$output .= "</ul></div>";
		
		if($options['other']['carouselPosition'] == 'bottom' || empty($options['other']['carouselPosition']))
			$output .= $carousel;
				
		//prepers all the variables for js
		$options['slider_js']['sync'] = '#carousel-'.$selector;
		$options['slider_js']['additionalSettings'] = '{'.str_replace("'",'"',$options['slider_js']['additionalSettings']).'}';
		$additional_settings = json_decode($options['slider_js']['additionalSettings'], true);
		if(is_array($additional_settings))
			$options['slider_js'] = array_merge($options['slider_js'], $additional_settings);
		unset($options['slider_js']['additionalSettings']);
		$options['slider_js'] = json_encode($this->ensure_boolen($options['slider_js']));
		
		$output .= '	
			<script type="text/javascript">	
				
				jQuery("#carousel-'.$selector.'").flexslider({
					animation: "slide",
					controlNav: false,
					animationLoop: false,
					slideshow: false,
					itemWidth: 150,
					itemMargin: 5,
					asNavFor: "#slider-'.$selector.'"
				});
				
				jQuery("#slider-'.$selector.'").flexslider('.$options['slider_js'].');
			</script>
			';
	
		return $output;
	}
	
	// Init plugin options to white list our options
	function init(){
		register_setting( 'wpfg_plugin_options', 'wpfg_options', array($this,'validate_options') );
	}
	function validate_options($input) {
		return $input;
	}
	
	// Handles BPL Trunk8 option page
	function admin_page() {
		add_options_page('WP FLex Gallery', 'WP Flex Gallery', 9, basename(__FILE__), array($this,'manage'));
	}
	function manage() {
	?>
		<div class="wrap">
		
			<?php screen_icon(); ?>
			<h2>WP Flex Gallery</h2>
			<form action="options.php" method="post" >
			
				<?php 
				settings_fields('wpfg_plugin_options');			
				$options = $this->slider_options;
				?>
				
				<h3>Default slider settings for all galleries</h3>
				<p>Those settings will be used by default but they can also be overwritten with function or shortcode arguments.
			
				<table class="form-table">
				
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[other][slider]">Enable slider for all [gallery] shortcodes by default</label>
						</th>
						
						<td>
							<select name="wpfg_options[other][slider]">
								<?php $this->the_select_options('', $options['other']['slider']); ?>
							</select>
						</td>
					</tr>
				
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[other][link]">Image links to (link)</label>
						</th>
						
						<td>
							<?php
							$select = array( 'attachment' => 'Attachment page', 'file' => 'Image file', 'none' => 'None' );
							?>
							<select name="wpfg_options[other][link]">
								<?php $this->the_select_options($select, $options['other']['link']); ?>
							</select>
						</td>
					</tr>
				
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[other][carouselPosition]">Display slider thumbnails as carousel (slider_carouselPosition)</label>
						</th>
						
						<td>
							<?php
							$select = array( 'bottom' => 'Bottom', 'top' => 'Top', 'hide' => 'Hide' );
							?>
							<select name="wpfg_options[other][carouselPosition]">
								<?php $this->the_select_options($select, $options['other']['carouselPosition']); ?>
							</select>
						</td>
					</tr>
				
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][animation]">Animation (slider_animation)</label>
						</th>
						
						<td>
							<?php
							$animation = array( 'fade' => 'Fade', 'slide' => 'Slide' );
							?>
							<select name="wpfg_options[slider_js][animation]">
								<?php $this->the_select_options($animation, $options['slider_js']['animation']); ?>
							</select>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][direction]">Sliding direction (slider_direction)</label>
						</th>
						
						<td>
							<?php
							$animation = array( 'horizontal' => 'Horizontal', 'vertical' => 'Vertical' );
							?>
							<select name="wpfg_options[slider_js][direction]">
								<?php $this->the_select_options($animation, $options['slider_js']['direction']); ?>
							</select>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][slideshowSpeed]">Slideshow speed (slider_slideshowSpeed)</label>
						</th>
						
						<td>
							<input type="number" class="small-text" name="wpfg_options[slider_js][slideshowSpeed]" value="<?php echo $options['slider_js']['slideshowSpeed']; ?>" />
							<p class="description">Set the speed of the slideshow cycling, in milliseconds.</p>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][animationSpeed]">Animation speed (slider_animationSpeed)</label>
						</th>
						
						<td>
							<input type="number" class="small-text" name="wpfg_options[slider_js][animationSpeed]" value="<?php echo $options['slider_js']['animationSpeed']; ?>" />
							<p class="description">Set the speed of animations, in milliseconds.</p>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][smoothHeight]">Smooth height (slider_smoothHeight)</label>
						</th>
						
						<td>
							<select name="wpfg_options[slider_js][smoothHeight]">
								<?php $this->the_select_options('', $options['slider_js']['smoothHeight']); ?>
							</select>
							<p class="description">Allow height of the slider to animate smoothly in horizontal mode.</p>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][slideshow]">Enable slideshow (slider_slideshow)</label>
						</th>
						
						<td>
							<select name="wpfg_options[slider_js][slideshow]">
								<?php $this->the_select_options('', $options['slider_js']['slideshow']); ?>
							</select>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][randomize]">Random Images Order (slider_randomize)</label>
						</th>
						
						<td>
							<select name="wpfg_options[slider_js][randomize]">
								<?php $this->the_select_options('', $options['slider_js']['randomize']); ?>
							</select>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][directionNav]">Enable navigation menu (slider_directionNav)</label>
						</th>
						
						<td>
							<select name="wpfg_options[slider_js][directionNav]">
								<?php $this->the_select_options('', $options['slider_js']['directionNav']); ?>
							</select>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row">
							<label for="wpfg_options[slider_js][additionalSettings]">Input additional settings for FlexSlider2 as JS array (slider_additionalSettings)</label>
						</th>
						
						<td>
							<input type="number" class="regular-text ltr" name="wpfg_options[slider_js][additionalSettings]" value="<?php echo $options['slider_js']['additionalSettings']; ?>" />
							<p class="description">For example " 'pauseOnAction': true, 'startAt' : 0 "</p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				</p>
			</form>
	
		</div>
	<?	
	}
	
	function the_select_options($array, $current) {
		if(empty($array))
			$array = array( 1 => 'True', 0 => 'False' );
			
		foreach( $array as $name => $label ) {
			$selected = selected( $current, $name, false );
			echo '<option value="'.$name.'" '.$selected.'>'.$label.'</option>';
		}
	}
	
	function ensure_boolen($array) {
		$return = array();
		
		foreach ( $array as $name => $element ) {
			if( $element == '1' || $element == '0' )
				$return[$name] = (bool)$element;
			else
				$return[$name] = $element;
		}
		
		return $return;
	}
	
    function get_value($var) {
        return $this->$var;
    }

}

function wpfg_gallery($attr = array(), $slider_options = '') {
	if(empty($slider_options)) {
		global $wpfg;
		$slider_options = $wpfg->get_value('slider_options');
	}
	echo $wpfg->flexslider_gallery($attr = array(), $slider_options);
}
