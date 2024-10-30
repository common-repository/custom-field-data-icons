<?php
/*
Plugin Name: Custom Field Data Icons
Plugin URI: http://www.easycpmods.com
Description: Custom Field Data Icons is a lightweight plugin that will display custom field data with icons on front page. It requires Classipress theme to be installed.
Author: EasyCPMods
Version: 1.8.0
Author URI: http://www.easycpmods.com
Text Domain: ecpm-cfd
*/

define('ECPM_CFD', 'ecpm-cfd');
define('ECPM_CFD_NAME', 'Custom Field Data Icons');
define('ECPM_CFD_VERSION', '1.8.0');

register_activation_hook( __FILE__, 'ecpm_cfd_activate');
//register_deactivation_hook( __FILE__, 'ecpm_cfd_deactivate');
register_uninstall_hook( __FILE__, 'ecpm_cfd_uninstall');

add_action('plugins_loaded', 'ecpm_cfd_plugins_loaded');
add_action('admin_init', 'ecpm_cfd_requires_version');
  
add_action('admin_menu', 'ecpm_cfd_create_menu_set', 11);
add_action('wp_enqueue_scripts', 'ecpm_cfd_enqueue_styles');
add_action('admin_enqueue_scripts', 'ecpm_cfd_admin_enqueue_styles');
add_action('admin_notices', 'ecpm_cfd_show_notice');

if (ecpm_cfd_is_cp4())
  add_action('cp_listing_item_content', 'ecpm_get_loop_ad_details');
else
  add_action('appthemes_after_post_content', 'ecpm_get_loop_ad_details' );  
 
function ecpm_cfd_is_cp4() {
  if ( defined("CP_VERSION") )
    $cp_version = CP_VERSION;
  else   
    $cp_version = get_option('cp_version');
    
  if (version_compare($cp_version, '4.0.0') >= 0) {
    return true;
  }
  
  return false;
}


function ecpm_cfd_requires_version() {
  $allowed_apps = array('classipress');
  
  if ( defined(APP_TD) && !in_array(APP_TD, $allowed_apps ) ) { 
	  $plugin = plugin_basename( __FILE__ );
    $plugin_data = get_plugin_data( __FILE__, false );
		
    if( is_plugin_active($plugin) ) {
			deactivate_plugins( $plugin );
			wp_die( "<strong>".$plugin_data['Name']."</strong> requires a AppThemes Classipress theme to be installed. Your Wordpress installation does not appear to have that installed. The plugin has been deactivated!<br />If this is a mistake, please contact plugin developer!<br /><br />Back to the WordPress <a href='".get_admin_url(null, 'plugins.php')."'>Plugins page</a>." );
		}
	}
}

function ecpm_cfd_activate() {
  $ecpm_cfd_settings = get_option('ecpm_cfd_settings');
  if ( empty($ecpm_cfd_settings) ) {
    $ecpm_cfd_settings = array(
      'installed_version' => ECPM_CFD_VERSION,
      'h_position' => 'left',
      'show_icons' => '5',
      'max_fields' => '10',
      'sort_fields' => 'nosort',
      'enable_flds' => array(),
      'sel_fields' => array(),
      'sel_images' => array(),
      'media_images' => array(),
      'image_prefix' => 'cfd',
      'opacity' => '100',
      'side_margin' => '0'
    );
    update_option( 'ecpm_cfd_settings', $ecpm_cfd_settings );
  }
}

function ecpm_cfd_uninstall() {                                   
  delete_option( 'ecpm_cfd_settings' );
}

function ecpm_cfd_plugins_loaded() {
  $dir = dirname(plugin_basename(__FILE__)).DIRECTORY_SEPARATOR.'languages'.DIRECTORY_SEPARATOR;
	load_plugin_textdomain(ECPM_CFD, false, $dir);
}


function ecpm_cfd_enqueue_styles() {
  if (is_single())
    return;
  
  if (ecpm_cfd_is_cp4())
    wp_enqueue_script('ecpm_cfd_js', plugins_url( 'js/ecpm-cfd.js', __FILE__ ), array( 'jquery' ), false, true );
    
  wp_enqueue_style('ecpm_cfd_icons', plugins_url('css/ecpm-cfd-icons-min.css', __FILE__), array(), null);
}

function ecpm_cfd_admin_enqueue_styles() {
  wp_enqueue_style('ecpm_cfd_icons', plugins_url('css/ecpm-cfd-icons-min.css', __FILE__), array(), null);
}

function ecpm_cfd_get_settings($ret_value) {
  $cfd_settings = get_option('ecpm_cfd_settings');
  return $cfd_settings[$ret_value];
}

function ecpm_cfd_show_notice() {
  $version = ecpm_cfd_get_settings('installed_version');
  if ($version != ECPM_CFD_VERSION ) {
    $cfd_notice =  '<div id="ecpm-cfd-new.version" class="error fade"><p>';
    $cfd_notice .=  __('You have installed a new version of Custom Field Data Icons. Please go to plugin settings page and click SAVE SETTINGS.', ECPM_CFD);
    $cfd_notice .= '</p></div>'; 
    echo $cfd_notice;
  }   
}

function ecpm_cfd_get_media_files() {
  global $wpdb;
  
  $image_prefix = ecpm_cfd_get_settings('image_prefix');  
  $sql = "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'attachment' AND post_title LIKE '".$image_prefix."%'";
  $results = $wpdb->get_results( $sql ); 
  
  return $results;
} 

function ecpm_cfd_getFieldNames(){
  global $wpdb;
  
  $sql = "SELECT field_name FROM $wpdb->cp_ad_fields WHERE field_type IN ('drop-down', 'text box', 'radio')";
  $results = $wpdb->get_results( $sql );

  return $results;
}

function ecpm_cfd_getFieldLabel($field_name){
  global $wpdb;
 
  $sql = "SELECT field_label FROM $wpdb->cp_ad_fields WHERE field_name = '".$field_name."'";
  $result = $wpdb->get_var( $sql );
  
  if (!isset($result))
    return $field_name;
  else  
    return $result;
}

function ecpm_cfd_getAllowedFields($cfd_fields){
  if (empty($cfd_fields))
    return false;
    
  $allowed_fields = array();
  $results = ecpm_cfd_getFieldNames();

  foreach ( $results as $field ) {
    if ( in_array( $field->field_name, $cfd_fields) ) {
      $allowed_fields[] = $field->field_name;
    }
  }
   
  return $allowed_fields;
}



// display some custom fields on the loop ad listing
function ecpm_get_loop_ad_details() {
  global $post, $wpdb;
  if ( is_single() )
    return;

  $ecpm_cfd_settings = get_option('ecpm_cfd_settings');
  
  $ecpm_cfd_sort_fields = $ecpm_cfd_settings['sort_fields'];
  $ecpm_cfd_show_icons  = $ecpm_cfd_settings['show_icons'];
  $ecpm_cfd_enable_flds = $ecpm_cfd_settings['enable_flds'];
  $ecpm_cfd_sel_fields  = $ecpm_cfd_settings['sel_fields'];
  $ecpm_cfd_sel_images  = $ecpm_cfd_settings['sel_images'];
  $ecpm_cfd_media_images = $ecpm_cfd_settings['media_images'];
  $ecpm_cfd_h_position  = $ecpm_cfd_settings['h_position'];  
  $ecpm_cfd_opacity     = $ecpm_cfd_settings['opacity'];
  $ecpm_cfd_side_margin = $ecpm_cfd_settings['side_margin'];
  
  if ($ecpm_cfd_opacity == '')
    $ecpm_cfd_opacity = '1';
  else
    $ecpm_cfd_opacity = $ecpm_cfd_opacity / 100;  
  
  $location = 'list';

  if ( ! $post )
    return;
  
  $cp_results = ecpm_cfd_getAllowedFields($ecpm_cfd_sel_fields);
  if (!$cp_results)
    return;

  $showing_icon = 1;
  $ecpm_cfd_out_arr = array();
  
  if (!ecpm_cfd_is_cp4()) {
  ?>
  <style media="screen" type="text/css">
  div#custom-stats-left {float:left; }
  div#custom-stats-right {float:right;}
  .custom-stats-span-left {clear:both; font-size:11px; padding-right:12px;}
  .custom-stats-span-right {clear:both; font-size:11px; padding-left:12px;}
  </style>
  <?php
  }

  $ecpm_cfd_side_margin_style = '';
  if (is_numeric (intval($ecpm_cfd_side_margin ) ) )
    $ecpm_cfd_side_margin_style = 'style="margin-'.$ecpm_cfd_h_position.':'.$ecpm_cfd_side_margin.'px;"';
    
  if (ecpm_cfd_is_cp4())
    echo '<div style="margin-bottom:8px; text-align:'.$ecpm_cfd_h_position.'">';
  else  
    echo '<div id="custom-stats-'.$ecpm_cfd_h_position.'" '.$ecpm_cfd_side_margin_style.'>';
              
  foreach ( $cp_results as $cp_result ) {
    $cfd_key = array_search($cp_result, $ecpm_cfd_sel_fields);
    if ($cfd_key === false )
      continue;
      
    if ( $ecpm_cfd_enable_flds[$cfd_key] == 'on' ) {
      
      $post_meta_val = get_post_meta( $post->ID, $cp_result, true );
      if ( empty( $post_meta_val ) )
        continue;
    
      $image_html = '';
      $image_url = '';
      $field_label = ecpm_cfd_getFieldLabel($cp_result);
      $cfd_image_filename = basename($ecpm_cfd_sel_images[$cfd_key], '.php');
      if (!$cfd_image_filename) {
        $cfd_image_filename = $ecpm_cfd_media_images[$cfd_key];
        $image_url = wp_get_attachment_url( $cfd_image_filename );
      }  

      $args = array( 'value' => $post_meta_val, 'label' => $field_label, 'id' => $cp_result, 'class' => '' );
      $args = apply_filters( 'cp_ad_details_' . $cp_result, $args, $cp_result, $post, $location );
      
      if ( $cfd_image_filename ) {
        if (empty($ecpm_cfd_out_arr) && $ecpm_cfd_h_position == 'left')
          $cfd_sprite_class = 'cfd-sprite-first';
        else
          $cfd_sprite_class = 'cfd-sprite';
          
        if ($image_url == '') { //sprite
          $image_html = '<span class="cfd-sprite-img '.$cfd_sprite_class.' cfd_'. $cfd_image_filename.'" title="'. esc_html( translate( $args['label'], APP_TD ) ).'"></span>';
        } else {
          $image_html = '<span style="background-image: url('.$image_url.');" class="'.$cfd_sprite_class.'" title="'. esc_html( translate( $args['label'], APP_TD ) ).'"></span>';
        }  
      }  
        
      if ( $args ) {
        $ecpm_cfd_out_arr[$cfd_key] = $image_html.  '<span style="opacity:'.$ecpm_cfd_opacity.'; font-size:11px;">'. $args['value'] . '</span>';

        if ( in_array($ecpm_cfd_sort_fields, array('nosort', '') ) )
          echo $ecpm_cfd_out_arr[$cfd_key]; 
      }
    }
  }
  
  if ( in_array($ecpm_cfd_sort_fields, array('random', 'number') ) ) {
    if ( $ecpm_cfd_sort_fields == 'random' ) 
      shuffle($ecpm_cfd_out_arr);
    else
      ksort($ecpm_cfd_out_arr);
        
    foreach ( $ecpm_cfd_out_arr as $arr_value ) {
      if ($showing_icon <= $ecpm_cfd_show_icons ) {
        if ( $arr_value ) {
          echo $arr_value;
          $showing_icon++;
        }
      }
    }   
  }
  echo '</div>';
}

function ecpm_cfd_create_menu_set() {
  if ( is_plugin_active('easycpmods-toolbox/ecpm-toolbox.php') ) {
    $ecpm_etb_settings = get_option('ecpm_etb_settings');
    if ($ecpm_etb_settings['group_settings'] == 'on') {
      add_submenu_page( 'ecpm-menu', ECPM_CFD_NAME, ECPM_CFD_NAME, 'manage_options', 'ecpm_cfd_settings_page', 'ecpm_cfd_settings_page_callback' );
      return;
    }
  }
  add_options_page('Custom Field Data Icons','Custom Field Data Icons','manage_options', 'ecpm_cfd_settings_page','ecpm_cfd_settings_page_callback');
}    
  
function ecpm_cfd_settings_page_callback() {
	
	$ecpm_cfd_settings = get_option('ecpm_cfd_settings');
  
  $avail_sort = array('nosort', 'number', 'random');
  $avail_icon_pos = array('left', 'right');
  
  if ( current_user_can( 'manage_options' ) ) {
    if( isset( $_POST['ecpm_cfd_submit'] ) )
  	{
      if ( in_array($_POST[ 'ecpm_cfd_h_pos' ], $avail_icon_pos) )
        $ecpm_cfd_settings['h_position'] = sanitize_text_field($_POST[ 'ecpm_cfd_h_pos' ]);
        
      if ( is_numeric (intval($_POST[ 'ecpm_cfd_max_fields' ])) )
        $ecpm_cfd_settings['max_fields'] = sanitize_text_field($_POST[ 'ecpm_cfd_max_fields' ]);
            
      if ( in_array($_POST[ 'ecpm_cfd_sort_fields' ], $avail_sort) )
        $ecpm_cfd_settings['sort_fields'] = sanitize_text_field($_POST[ 'ecpm_cfd_sort_fields' ]);
        
      if ( is_numeric (intval($_POST[ 'ecpm_cfd_side_margin' ])) )
        $ecpm_cfd_settings['side_margin'] = sanitize_text_field($_POST[ 'ecpm_cfd_side_margin' ]);
      else
        $ecpm_cfd_settings['side_margin'] = 0;  
        
      if ( isset($_POST[ 'ecpm_cfd_image_prefix' ]) && $_POST[ 'ecpm_cfd_image_prefix' ] != '' )
        $ecpm_cfd_settings['image_prefix'] = sanitize_text_field($_POST[ 'ecpm_cfd_image_prefix' ]);
      else
        $ecpm_cfd_settings['image_prefix'] = 'cfd';    
            
      if ( is_numeric (intval($_POST[ 'ecpm_cfd_show_icons' ])) )
        $ecpm_cfd_settings['show_icons'] = sanitize_text_field($_POST[ 'ecpm_cfd_show_icons' ]);    
  // loop
      for ($i = 0; $i < $ecpm_cfd_settings['max_fields']; $i++) {
        if ( isset($_POST[ 'ecpm_cfd_enable_fld_'.$i ]) && $_POST[ 'ecpm_cfd_enable_fld_'.$i ] == 'on' ) 
          $ecpm_cfd_settings['enable_flds'][$i] = sanitize_text_field( $_POST[ 'ecpm_cfd_enable_fld_'.$i ] );
        else
          $ecpm_cfd_settings['enable_flds'][$i] = '';
            
        if ( isset($_POST[ 'ecpm_cfd_field_'.$i ] ) )
          $ecpm_cfd_settings['sel_fields'][$i] = sanitize_text_field( $_POST[ 'ecpm_cfd_field_'.$i ] );
        else
          $ecpm_cfd_settings['sel_fields'][$i] = '';
            
        if ( isset($_POST[ 'ecpm_cfd_image_'.$i ] ) )
          $ecpm_cfd_settings['sel_images'][$i] = sanitize_text_field( $_POST[ 'ecpm_cfd_image_'.$i ] );
        else
          $ecpm_cfd_settings['sel_images'][$i] = '';
            
        if ( isset($_POST[ 'ecpm_cfd_media_image_'.$i ] ) )
          $ecpm_cfd_settings['media_images'][$i] = sanitize_text_field( $_POST[ 'ecpm_cfd_media_image_'.$i ] );
        else
          $ecpm_cfd_settings['media_images'][$i] = '';

      }
      
      if ( is_numeric (intval($_POST[ 'ecpm_cfd_opacity' ])) )
          $ecpm_cfd_settings['opacity'] = $_POST[ 'ecpm_cfd_opacity' ];
          if (intval($ecpm_cfd_settings['opacity']) > 100 || intval($ecpm_cfd_settings['opacity']) < 0 || $ecpm_cfd_settings['opacity'] == '' )
            $ecpm_cfd_settings['opacity'] = '100';
      
      $ecpm_cfd_settings['installed_version'] = ECPM_CFD_VERSION;
      update_option( 'ecpm_cfd_settings', $ecpm_cfd_settings );
      
      echo scb_admin_notice( __( 'Settings saved.', APP_TD ), 'updated' );
  	}
  }  
  
  $ecpm_cfd_field_results = ecpm_cfd_getFieldNames();
  $ecpm_cfd_image_results = array('area','backup','beer','bg_color','bottle','camera','car','cloud','cloud_2','colour','currency','distance','dnk','ethernet','ethernet_cable','ext_memory_card','folder','fuel_1','fuel_2','geolocation','id_card_1','id_card_2','juice','keyboard','length_1','length_2','material','memory_card','memory_icon','memory_module','mobile','monitor','mouse','pc','percent','phone_1','phone_2','screen_size_1','screen_size_2','sd-memory_card','size','tag','timer','trash','truck','user','volume','weight','year');
  $ecpm_cfd_media_results = ecpm_cfd_get_media_files();
  ?>
  
		<div id="cfdsetting">
      <div class="wrap">
			<h1><?php echo _e('Custom Field Data Icons', ECPM_CFD); ?></h1>
      <?php
      echo "<i>Plugin version: <u>".ECPM_CFD_VERSION."</u>";
      echo "<br>Plugin language file: <u>ecpm-cfd-".get_locale().".mo</u></i>";
      ?>
  			<hr>
        <div id='cfd-container-left' style='float: left; margin-right: 285px;'>
        <form id='cfdsettingform' method="post" action="">
          <table width="100%" cellspacing="0" cellpadding="10" border="0">
            <tr>
    					<th align="left">
    						<label for="ecpm_cfd_h_pos"><?php echo _e('Horizontal position', ECPM_CFD); ?></label>
    					</th>
    					<td>
			          <Input type="radio" Name="ecpm_cfd_h_pos" value="left" <?php echo ($ecpm_cfd_settings['h_position'] == 'left' ? 'checked':'') ;?>><?php _e('Left', ECPM_CFD);?>&nbsp;&nbsp;
                <Input type="radio" Name="ecpm_cfd_h_pos" value="right" <?php echo ($ecpm_cfd_settings['h_position'] == 'right' ? 'checked':'') ;?>><?php _e('Right', ECPM_CFD);?>
    				  </td>
              <td>		
                <span class="description"><?php _e( 'Select where to position data icons' , ECPM_CFD ); ?></span>
    					</td>
    				</tr>
    				
    				<tr>
    					<th align="left">
    						<label for="ecpm_cfd_show_icons"><?php echo _e('Max icons to show', ECPM_CFD); ?></label>
    					</th>
    					<td>
                <select name="ecpm_cfd_show_icons">
                  <?php
                  for ($i = 1; $i<=10; $i++)
                    echo '<option value="'.$i.'"'. ($ecpm_cfd_settings['show_icons'] == $i ? 'selected':'') .">".$i."</option>";
                  ?>
                </select>

    				  </td>
              <td>		
                <span class="description"><?php _e( 'Maximum number of icons to show' , ECPM_CFD ); ?></span>
    					</td>
    				</tr>

           	<tr>
    					<th align="left">
    						<label for="ecpm_cfd_sort_fields"><?php echo _e('Sorting', ECPM_CFD); ?></label>
    					</th>
    					<td>
                <select name="ecpm_cfd_sort_fields">
                   <option value="nosort" <?php echo ($ecpm_cfd_settings['sort_fields'] == 'nosort' ? 'selected':'') ;?>><?php echo _e('No sorting', ECPM_CFD); ?></option>
                   <option value="number" <?php echo ($ecpm_cfd_settings['sort_fields'] == 'number' ? 'selected':'') ;?>><?php echo _e('By numbers', ECPM_CFD); ?></option>
                   <option value="random" <?php echo ($ecpm_cfd_settings['sort_fields'] == 'random' ? 'selected':'') ;?>><?php echo _e('Random', ECPM_CFD); ?></option>
                </select>
    				  </td>
              <td>		
                <span class="description"><?php _e( 'Would you like the icons to be sorted?' , ECPM_CFD ); ?></span>
    					</td>
    				</tr>
    				
    				<tr>
    					<th align="left" valign="top">
    						<label for="ecpm_cfd_opacity;?>"><?php echo _e('Opacity', ECPM_CFD); ?></label>
    					</th>
    					<td>
			          <Input type='text' size='2' id='ecpm_cfd_opacity' Name='ecpm_cfd_opacity' value='<?php echo esc_html($ecpm_cfd_settings['opacity']);?>'>%
    				  </td>
              <td>		
                <span class="description"><?php _e( 'Transparency for the shown data and icons' , ECPM_CFD ); ?></span>
    					</td>
    				</tr>
            
            <tr>
    					<th align="left">
    						<label for="ecpm_cfd_side_margin"><?php echo _e('Line side margin', ECPM_CFD); ?></label>
    					</th>
    					<td>
                <Input type='text' size='2' id='ecpm_cfd_side_margin' Name='ecpm_cfd_side_margin' value='<?php echo $ecpm_cfd_settings['side_margin'];?>'>px
    				  </td>
              <td>		
                <span class="description"><?php _e( 'Specify left or right line margin' , ECPM_CFD ); ?></span>
    					</td>
    				</tr>
            
            <tr><td colspan="3"><hr></td></tr>
            <tr>
    					<th align="left">
    						<label for="ecpm_cfd_image_prefix"><?php echo _e('Media image prefix', ECPM_CFD); ?></label>
    					</th>
    					<td>
                <Input type='text' size='2' Name ='ecpm_cfd_image_prefix' value='<?php echo $ecpm_cfd_settings['image_prefix'];?>'>
    				  </td>
              <td>		
                <span class="description"><?php _e( 'Only images with this prefix will be shown in the list' , ECPM_CFD ); ?></span>
    					</td>
    				</tr>
    				
    				<tr>
    					<th align="left">
    						<label for="ecpm_cfd_max_fields"><?php echo _e('Fields to show:', ECPM_CFD); ?></label>
    					</th>
    					<td>
                <Input type='text' size='2' Name ='ecpm_cfd_max_fields' value='<?php echo $ecpm_cfd_settings['max_fields'];?>'>
    				  </td>
              <td>		
                <span class="description"><?php _e( 'Number of fields to show below' , ECPM_CFD ); ?></span>
    					</td>
    				</tr>
    				
    				<tr><td colspan="3"><hr></td></tr>
            <tr>
    					<td colspan="3">
                <table width="600px" cellspacing="0" cellpadding="5" border="0">
                  <tr>
                    <td width="50px" colspan="2" align="center"><?php echo _e('Enable', ECPM_CFD); ?></td>
                    <td align="center"><?php echo _e('Field', ECPM_CFD); ?></td>
                    <td width="250px" align="center" colspan="2"><?php echo _e('Plugin icon', ECPM_CFD); ?></td>
                    <td width="250px" align="center" colspan="2"><?php echo _e('Media icon', ECPM_CFD); ?></td>
                  </tr>
                  <tr><td colspan="7"><hr></td></tr>
                <?php 
                    
                  for ($i = 0; $i < $ecpm_cfd_settings['max_fields']; $i++) {
                    $item = 0;
                    ?>
                    <tr>
                      <td align="center"><?php echo $i+1 .". ";?></td>
                      <td align="center"><Input type='checkbox' Name='ecpm_cfd_enable_fld_<?php echo $i;?>' <?php echo ( $ecpm_cfd_settings['enable_flds'][$i] == 'on' ? 'checked':'') ;?> ></td>
                      <td align="center">
                        <select name="ecpm_cfd_field_<?php echo $i;?>">
                          <option value="" <?php echo (!$ecpm_cfd_settings['sel_fields'][$i] ? 'selected':'') ;?>><?php echo _e('-- No field --', ECPM_CFD); ?></option>
                        <?php
                      	  foreach ( $ecpm_cfd_field_results as $result ) {
                            $item++;
                            $field_label = ecpm_cfd_getFieldLabel($result->field_name);
          							  ?>
          									<option value="<?php echo $result->field_name; ?>" <?php echo ($ecpm_cfd_settings['sel_fields'][$i] == $result->field_name ? 'selected':'') ;?>><?php echo $field_label; ?></option>
          							  <?php
          							  } 
                        ?>
                        </select>
                      </td>
                      <td align="center">
                        <select name="ecpm_cfd_image_<?php echo $i;?>" >
                          <option value="" <?php echo (!$ecpm_cfd_settings['sel_images'][$i] ? 'selected':'') ;?>><?php echo _e('-- No image --', ECPM_CFD); ?></option>
                        <?php
                      	  foreach ( $ecpm_cfd_image_results as $result ) {
          							    $image_name = str_replace('cfd_', '', basename($ecpm_cfd_settings['sel_images'][$i], '.png') );
                          ?>
          									<option value="<?php echo $result;?>" <?php echo ($image_name == $result ? 'selected':'');?>><?php echo $result; ?></option>
          							  <?php
          							  } 
                        ?>
                        </select>
                      </td>
                      <td align="center">
                      <?php
                      if ( $ecpm_cfd_settings['sel_images'][$i] ) { 
                        $image_name = str_replace('cfd_', '', basename($ecpm_cfd_settings['sel_images'][$i], '.png') );
                      ?>
                        <span class="cfd-sprite-img cfd-sprite cfd_<?php echo $image_name;?>"></span>
                      <?php 
                      }
                      ?>  
                      </td>
                        <td align="center">
                          <select name="ecpm_cfd_media_image_<?php echo $i;?>" >
                            <option value="" <?php echo (!$ecpm_cfd_settings['media_images'][$i] ? 'selected':'') ;?>><?php echo _e('-- No image --', ECPM_CFD); ?></option>
                          <?php
                        	  foreach ( $ecpm_cfd_media_results as $result ) {
            							  ?>
            									<option value="<?php echo $result->ID;?>" <?php echo ($ecpm_cfd_settings['media_images'][$i] == $result->ID ? 'selected':'') ;?>><?php echo $result->post_title; ?></option>
            							  <?php
            							  } 
                          ?>
                          </select>
                        </td>
                        
                        <td align="center">
                        <?php
                        if ( $ecpm_cfd_settings['media_images'][$i] ) {
                          echo wp_get_attachment_image ( $ecpm_cfd_settings['media_images'][$i], '', true );
                        }
                        ?>  
                        </td>
                       
                    </tr>
                    <?php
      					  } 
                  ?>
                
                </table>
    				  </td>
              
    				</tr>
          </table>
          <hr> 
          
  				<p class="submit">
  				<input type="submit" id="ecpm_cfd_submit" name="ecpm_cfd_submit" class="button-primary" value="<?php _e('Save settings', ECPM_CFD); ?>" />
  				</p>
  			</form>
        </div>
        
        <div id='ddc-container-right' class='nocloud' style='border: 1px solid #e5e5e5; float: right; margin-left: -275px; padding: 0em 1.5em 1em; background-color: #fff; box-shadow:10px 10px 5px #888888; display: inline-block; width: 234px;'>
          <h3>Custom Field Data Icons PRO</h3>
          <p><a href="http://www.easycpmods.com/custom-field-data-icons-pro/" target="_blank"><img src="<?php echo esc_url(plugins_url('images/custom-field-data-icons-pro.png', __FILE__));?>" border="0" width="236px" title="Custom Field Data Icons PRO" alt="Custom Field Data Icons PRO"></a></p>
          <hr>
  			  <p>Would you like to have more lines of icons or even additional text from other fields to be shown on loop ad listing?</p>
          <p>Then you should consider buying a PRO version of this plugin.</p>
          <p><strong>Additional features include:</strong>
          <ul>
          <li>- Multiple lines</li>
          <li>- Icons above or below the ad</li>
          <li>- Additional Classipress meta fields</li>
          <li>- Additional font settings</li>
          <li>- Animated CSS popup</li>
          <li>- Option to display data with <strong>QR code</strong></li>
          <li>- Option to display <strong>prefix</strong> and <strong>suffix</strong> text around the value</li>
          </ul>
          </p>
          
  			  <p>You can purchase Custom Field Data Icons PRO plugin from <a href="http://easycpmods.com/custom-field-data-icons-pro" target="_blank">here</a>.</p>
          <hr>
          <p>
          Please visit <a href="http://easycpmods.com/" target="_blank">our page</a> where you will find other usefull plugins.
          </p>
          <a href="http://easycpmods.com/" target="_blank">Easy CP Mods</a>	        
	      </div>
        
        <div id='cfd-container-right' class='nocloud' style='border: 1px solid #e5e5e5; float: right; margin-top:720px; margin-left: -275px; padding: 0em 1.5em 1em; background-color: #fff; box-shadow:10px 10px 5px #888888; display: inline-block; width: 234px;'>
          <h3>Thank you for using</h3>
	        <h2><?php echo ECPM_CFD_NAME;?></h2>
	        <hr>
	        <?php include_once( plugin_dir_path(__FILE__)  ."image_sidebar.php" );?>
        </div>
		</div>
	</div>
<?php
}

?>