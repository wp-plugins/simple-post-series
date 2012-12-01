<?php
/*
Plugin Name: Post Series
Plugin URI: http://www.chaozh.com/wordpress-plugin-post-series-publish/
Version: 1.0
Author: chaozh
Author URI: http://chaozh.com/
origin: http://wp.tutsplus.com/tutorials/plugins/adding-post-series-functionality-to-wordpress-with-taxonomies/
*/
define('SERIES','series');
define('SERIES_URL', plugins_url( '', __FILE__ )); 
define('SERIES_ROOT', dirname(__FILE__) );
define('SERIES_BASE', dirname( plugin_basename( __FILE__ ) )); 
// Adds translation support for language files
function series_localization() {
	load_plugin_textdomain( SERIES_BASE, false, SERIES_BASE. '/languages' );
}
add_action( 'plugins_loaded', 'series_localization' );

function series_posttype_support(){
    return apply_filters('series_posttype_support', array('post', 'page') );
}

function series_register_taxonomy() {
    
    $posttypes = series_posttype_support();
    
	$labels = array(
    
		'name' => _x('Series', 'taxonomy general name',SERIES_BASE),
		'singular_name' => _x('Series', 'taxonomy singular name',SERIES_BASE),
		'all_items' => __('All Series',SERIES_BASE),
		'edit_item' => __('Edit Series',SERIES_BASE), 
		'update_item' => __('Update Series',SERIES_BASE),
		'add_new_item' => __('Add New Series',SERIES_BASE),
		'new_item_name' => __('New Series Name',SERIES_BASE),
		'menu_name' => __('Series',SERIES_BASE)
        
	);
    
    $series_tax_args = array(
    
        'labels' => $labels,
		'hierarchical' => true,
        'show_ui' => true,
        'query_var' => true,
		'rewrite' => array('slug' => SERIES)
        
   );

	register_taxonomy( SERIES, $posttypes, $series_tax_args );
    
    $options = get_option( SERIES.'_options' );
    if( $options['auto_display'] ){
        add_filter('the_content', 'series_auto_content_display',0);
    }

    if( !$options['custom_styles'] ){
        add_action( 'wp_enqueue_scripts', 'series_css' );
    }
    /*
     * we will not include sereis archives template by default 
     * and this setting section is removed from plugin settings
    if( $options['custom_archives'] ){
        add_filter('template_include', 'series_set_template');
    }
    */
} 
add_action('init', 'series_register_taxonomy', 0);

// Adds CSS for the slideshow
function series_css() {
	if ( file_exists( get_stylesheet_directory()."/series.css" ) ) {				
		wp_enqueue_style( SERIES, get_stylesheet_directory_uri() . '/series.css', array(), '1.0' );			
	}
	elseif ( file_exists( get_template_directory()."/series.css" ) ) {							
		wp_enqueue_style( SERIES, get_template_directory_uri() . '/series.css', array(), '1.0' );
	}
	else {
		wp_enqueue_style( SERIES, SERIES_URL . '/series.css', array(), '1.0' );
	}
}

function series_is_template( $template_path ){

    //Get template name
    $template = basename($template_path);

    //Check if template is taxonomy-series.php
    //Check if template is taxonomy-series-{term-slug}.php
    if( 1 == preg_match('/^taxonomy-series((-(\S*))?).php/',$template) )
         return true;

    return false;
}

function series_set_template( $template )
{
	if( is_tax(SERIES) && !series_is_template($template) ){
        $template = SERIES_ROOT."/template/taxonomy-".SERIES.".php";
	}

    return $template;
}

// Load admin functions if in the backend
if ( is_admin() ){
    require_once(SERIES_ROOT. '/series-admin.php');
}

require_once(SERIES_ROOT. '/series-display.php');
// The shortcode function of Post Series
function series_sc($atts) {
    $options = get_option( SERIES.'_options' );
    //merge options
	$series_arg = shortcode_atts( array (
    
            "slug" => '',
            "id" => '',
            "title" => '', 
            "limit" => -1, 
            "show_future" => true,
            "class_prefix" => $options["class_prefix"]
            			
    ), $atts );
    
    if( isset($atts['show_future']) && $atts['show_future'] == 'off'){
        $series_arg['show_future'] = false;
    }else{
        $series_arg['show_future'] = true;
    }    
    $series_arg = $series_arg + $options;
    return series_display($series_arg);
} 
add_shortcode('series','series_sc');

function series_auto_content_display($content) {
    
    if(is_single() || is_page() || is_feed()){
        $options = get_option( SERIES.'_options' );
        $series_arg = array(
            "limit" => -1
        );
        $series_arg = $options + $series_arg;
        $series_display = series_display($series_arg);
        $content =  $content."\n".$series_display."\n"; 
    }
    return $content;
}

class Post_Series_Widget extends WP_Widget {
    /** constructor */
    function __construct() {
    	$widget_ops = array(
            'classname' => 'widget_' . SERIES,
            'description' => __( "A simple widget to display post series.", SERIES_BASE) 
        );
        $this->WP_Widget( 'widget_' . SERIES, $name = __('Post Series', SERIES_BASE), $widget_ops);	
        
        add_action( 'save_post', array(&$this, 'flush_widget_cache') );
		add_action( 'deleted_post', array(&$this, 'flush_widget_cache') );
		add_action( 'switch_theme', array(&$this, 'flush_widget_cache') );
    }

    /** @use WP_Widget::widget */
    function widget($args, $instance) {
        $cache = wp_cache_get('widget_'.SERIES, 'widget');
        
        if ( !is_array($cache) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = null;

		if ( isset($cache[$args['widget_id']]) ) {
			echo $cache[$args['widget_id']];
			return;
		}
        
        $options = get_option( SERIES.'_options' );
        $series_arg = array(
        
            "id" => $instance["id"],
            "limit" => $instance["limit"],
            "show_future" => $instance["show_future"],
            "class_prefix" => $instance["class_prefix"], 
            "title_format" => ''
            
        );
        $series_arg = $series_arg + $options;
        
		$widget_title = apply_filters( 'widget_title', $instance['widget_title'] );
        
        ob_start();	
        extract( $args, EXTR_SKIP );
        	
		echo $before_widget;
		echo $before_title . $widget_title . $after_title;
    	echo series_display($series_arg);
        echo $after_widget;
        
        $cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_'.SERIES, $cache, 'widget');
    }
    
    function flush_widget_cache() {
		wp_cache_delete( 'widget_'.SERIES, 'widget' );
	}

    /** @use WP_Widget::update */
    function update($new_instance, $old_instance) {				
        $instance = $old_instance;
        $instance['widget_title'] = strip_tags($new_instance['widget_title']);
        $instance['id'] = absint($new_instance['id']);
        if( empty($new_instance['limit']) )
            $instance['limit'] = -1;
		
        $instance['class_prefix'] = strip_tags($new_instance['class_prefix']);
        $instance['show_future'] = $new_instance['show_future'] == "on" ? true : false;
        
        $this->flush_widget_cache();
        
        $alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_'.SERIES]) )
			delete_option('widget_'.SERIES);
        
    	return $instance;
    }

    /** @use WP_Widget::form */
    function form($instance) {
        $options = get_option( SERIES.'_options' );
        if(isset($instance['limit']) || $instance['limit'] == -1)
            $limit = '';
        else
            $limit = $instance['limit'];
        
        $class_prefix = isset($instance['class_prefix']) ? esc_attr($instance['class_prefix']) : $options['class_prefix'];
        $show_future = isset($instance['show_future']) ? $instance['show_future'] : $options['show_future'];
        ?>
            <p>
            	<label for="<?php echo $this->get_field_id('widget_title'); ?>"><?php _e('Title:'); ?> </label>
                <input id="<?php echo $this->get_field_id('widget_title'); ?>" name="<?php echo $this->get_field_name('widget_title'); ?>" class="widefat" type="text" value="<?php echo esc_attr($instance['widget_title']); ?>" />
			</p>
            <?php 
            $series = get_terms( SERIES );
            
            if( count($series) > 0 ): ?>
			<p>
            	<label for="<?php echo $this->get_field_id('id'); ?>"><?php _e('Choose post series to display:', SERIES_BASE); ?> </label>
            	<select id="<?php echo $this->get_field_id('id'); ?>" name="<?php echo $this->get_field_name('id'); ?>" class="widefat">
            		<?php foreach( $series as $term ): ?>
            		<option value="<?php echo $term->term_id; ?>" <?php echo ($instance['id'] == $term->term_id) ? 'selected="selected"':""; ?>><?php echo $term->name . "(" . $term->count . ")"; ?></option>
            		<?php endforeach; ?>
            	</select>
			</p>
			<?php endif; ?>
            <p>
            	<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of posts to show:', SERIES_BASE); ?> </label>
                <input id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" class="widefat" type="text" value="<?php echo $limit; ?>" size="3" />
			</p>
            <p>
            	<label for="<?php echo $this->get_field_id('class_prefix'); ?>"><?php _e('Special class prefix:', SERIES_BASE); ?> </label>
                <input id="<?php echo $this->get_field_id('class_prefix'); ?>" name="<?php echo $this->get_field_name('class_prefix'); ?>" class="widefat" type="text" value="<?php echo $class_prefix; ?>" />
			</p>
            <p>
                <input id="show_future" type="checkbox" name="<?php echo $this->get_field_name('show_future'); ?>" value="on" <?php checked( $show_future ); ?> />
                <label for="show_future" class="future-on-label"><?php _e('Show future',SERIES_BASE);?></label>
			</p>
        <?php 
    }

}
add_action( 'widgets_init', create_function( '', 'register_widget( "Post_Series_Widget" );' ) );
?>