<?php
/*
Plugin Name: Simple Group Manager
Version: 1.0
Plugin URI: http://kir-dev.sch.bme.hu/wp-opensso
Description: Manage the user's groups and the group's roles.
Author: Pairg
*/


global $capabilities_wp;
$capabilities_wp = array(
					'switch_themes',
                    'edit_themes',
                    'activate_plugins',
                    'edit_plugins',
                    'edit_users',
                    'edit_files',
                    'manage_options',
                    'moderate_comments',
                    'manage_categories',
                    'manage_links',
                    'upload_files',
                    'import',
                    'unfiltered_html',
                    'edit_posts',
                    'edit_others_posts',
                    'edit_published_posts',
                    'publish_posts',
                    'edit_pages',
                    'read',
                    'level_1,0',
                    'level_9',
                    'level_8',
                    'level_7',
                    'level_6',
                    'level_5',
                    'level_4',
                    'level_3',
                    'level_2',
                    'level_1,',
                    'level_0',
                    'edit_others_pages',
                    'edit_published_pages',
                    'publish_pages',
                    'delete_pages',
                    'delete_others_pages',
                    'delete_published_pages',
                    'delete_posts',
                    'delete_others_posts',
                    'delete_published_posts',
                    'delete_private_posts',
                    'edit_private_posts',
                    'read_private_posts',
                    'delete_private_pages',
                    'edit_private_pages',
                    'read_private_pages',
                    'delete_users',
                    'create_users',
                    'unfiltered_upload',
                    'manage_roles',
                    'edit_dashboard',
                    'update_plugins',
                    'delete_plugins',
                    'install_plugins',
                    'update_themes'
					 	 			 );


// Register install/uninstall functions
register_activation_hook(__FILE__, '_install_sgm' );
register_deactivation_hook(__FILE__, '_uninstall_sgm');

// Add some admin page
add_action('admin_menu', 'sgm_menu');

function sgm_menu(){
 add_users_page('Csoportok', 'Csoportok', '8', __FILE__, 'sgm_admin_page');
}


function _install_sgm(){
 global $wp_roles;
 if(!isset($wp_roles)) die("Ismeretlen hiba!");
 $roles = $wp_roles->get_names();
 $archive = array();
 foreach($roles AS $role=>$display_name){
  $role_obj = _get_real_role($role);
  $archive[$role_obj->name]['capabilities'] = $role_obj->capabilities;
  $archive[$role_obj->name]['name'] = $display_name;
 }
 add_option('sgm_archive', $archive);
}


function _uninstall_sgm(){
 $archive = get_option('sgm_archive');
 global $wp_roles;
 if(!isset($wp_roles)) die("Ismeretlen hiba!");
 if($archive === FALSE) return 1;
 $names = $wp_roles->get_names();
 foreach($names AS $name => $display_name){
  $wp_roles->remove_role($name);
 }
 foreach($archive AS $role=>$arr){
  $wp_roles->add_role($role, $arr['name'], $arr['capabilities']);
 }
 delete_option('sgm_archive');
}


function _get($var){
 if(!isset($_GET[$var]) OR empty($_GET[$var])) return FALSE;
  else return sanitize_user($_GET[$var]);
}

function _post($var){
 if(!isset($_POST[$var]) OR empty($_POST[$var])){
  return FALSE;
 }else{
  if(is_array($_POST[$var])){
	 foreach($_POST[$var] AS $key=>$value) $_POST[$var][$key] = sanitize_user($value);
	 return $_POST[$var];
	}else{
	 return sanitize_user($_POST[$var]);
  }
 }
}


function _group_name($name, $clear = FALSE){
 if($clear){
  $name = strtolower($name);
  $name = str_replace(' ', '_', $name);
  $name = str_replace('ö', 'o', $name);
  $name = str_replace('ü', 'o', $name);
  $name = str_replace('ó', 'o', $name);
  $name = str_replace('ő', 'o', $name);
  $name = str_replace('ú', 'u', $name);
  $name = str_replace('é', 'e', $name);
  $name = str_replace('á', 'a', $name);
  $name = str_replace('ű', 'u', $name);
  $name = str_replace('í', 'i', $name);
  $name = str_replace('Ö', 'o', $name);
  $name = str_replace('Ü', 'o', $name);
  $name = str_replace('Ó', 'o', $name);
  $name = str_replace('Ő', 'o', $name);
  $name = str_replace('Ú', 'u', $name);
  $name = str_replace('É', 'e', $name);
  $name = str_replace('Á', 'a', $name);
  $name = str_replace('Ű', 'u', $name);
  $name = str_replace('Í', 'i', $name);
 }
 if(empty($name) OR !is_string($name)) return FALSE;
 if(!preg_match('/^[a-z0-9-_]{3,50}$/', $name)){
  return FALSE;
 }else{
  if($clear) return $name;
   else return TRUE;
 }
}


// WP Bugfix
function _get_real_role($role_name){
 global $wp_roles;
 $role = $wp_roles->get_role($role_name);
 if($role !== null AND !is_object($role)){
  $role = new WP_Role($role_name, $wp_roles->roles[$role_name]['capabilities'] );
 }
 return $role;
}


function _get_caps(){
 global $capabilities_wp;
 $caps = _post('caps');
 if($caps === FALSE OR !is_array($caps)) return array();
 $caps2 = array();
 foreach($caps AS $cap=>$value){
  if(isset($value) AND !empty($value) AND $value){
   if(in_array($cap, $capabilities_wp)) $caps2[$cap] = 1;
	}
 }
 return $caps2;
}


function _format_role($role){
 $role = str_replace('_', ' ', $role);
 $role = str_replace(',', '', $role);
 $role = ucfirst($role);
 return $role;
}

function sgm_admin_page(){
 global $capabilities_wp;
 global $wp_roles;
 if(!isset($wp_roles)) die("Ismeretlen hiba!");

 if(_get('action')){
  switch(_get('action')){
   case 'creat_role':
	  $name = _group_name(_post('name'), TRUE);
	  if($name === FALSE){
		 $message = "Hibás csoportnév!";
	  }else{
	   $roles = $wp_roles->get_names();
	   if(in_array(_post('name'), $roles)){
		  $message = "Ilyen nevű csoport már létezik!";
	   }else{
	    // If have a group with this name: generate a new name with a number of the end 
	    if(_get_real_role($name) !== null){
	     $i = 0;
	     while(_get_real_role($name.$i) !== null) ++$i;
	     $name = $name.$i;
	    }
		  $wp_roles->add_role($name, _post('name'), _get_caps());
	   }
		}
    break;
   case 'delete_role':
    if(_group_name(_get('name'))){
     $wp_roles->remove_role(_get('name'));
		}else{
		 $message = "Hibás csoportnév!";
	  }
    break;
   case 'edit_role':
    if(_group_name(_get('name'))){
     $role_obj = _get_real_role(_get('name'));
     if($role_obj === null){
		  $message = "Nem létező csoport!";
      unset($role_obj);
     }
		}else{
		 $message = "Hibás csoportnév!";
	  }
    break;
   case 'edit_role_end':
    if(_group_name(_get('name'))){
     $role = _get_real_role(_get('name'));
     $roles = $wp_roles->get_names();
     $wp_roles->remove_role(_get('name'));
     $wp_roles->add_role($role->name, $roles[$role->name], _get_caps());
		}else{
		 $message = "Hibás csoportnév!";
	  }
    break;
  }
 }
 
 $roles = $wp_roles->get_names();
 ?>
 
 <div class="wrap" style="padding-bottom: 40px;">
 
  <h2>Csoportok</h2>
 
  <?php if($message): ?>
  <blockquote style="border: 1px solid; margin: 15px; padding: 5px; display: block;" id="rules">
   <b><?php echo $message; ?></b>
  </blockquote>
  <?php endif; ?>
  
  <?php if(!isset($role_obj) OR $role_obj === null): ?>  
 
  <table cellspacing="0" cellpadding="5px" border="1" style="padding-bottom: 20px;">
   <thead>
    <tr>
     <td style="border: 1px solid #464646; padding: 5px; font-weight: bold;">
      Név
     </td>
     <td style="border-top: 1px solid #464646; border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px; font-weight: bold;">
      Szerkesztés
     </td>
     <td style="border-top: 1px solid #464646; border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px; font-weight: bold;">
      Törlés
     </td>
    </tr>
   </thead>
   <tbody>
   <?php foreach($roles AS $role=>$name): ?>
    <tr>
     <td style="border-bottom: 1px solid #464646; border-left: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><?php echo $name; ?></td>
     <td style="border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><a href="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=edit_role&name=<?php echo $role; ?>">Szerkesztés</a></td>
     <td style="border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><a href="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=delete_role&name=<?php echo $role; ?>">Törlés</a></td>
    </tr>
   <?php endforeach; ?>
   </tbody>
  </table>
  
	<h3>Új csoport létrehozása</h3>
	
  <form action="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=creat_role" method="post" style="padding-left: 20px;">
   <b>Név:</b> <input type="text" name="name" /><br />
	 <small>*Csak magyar betűk, és számok szerepelhetnek benne.</small><br /><br />
   
   <b>Tulajdonságok:</b>
	 <table>
   <?php foreach($capabilities_wp AS $role): ?>
    <tr>
     <td style="padding-right: 10px;"><?php echo _format_role($role); ?></td>
	  <td><input type="checkbox" value="1" name="caps[<?php echo $role; ?>]" /></td>                
    </tr>
	 <?php endforeach; ?>
   </table>
   
   <br />
   <input type="submit" value="Létrehoz" />
  </form>
  
  <?php else: ?>
  
   <h3>"<?php echo $roles[$role_obj->name]; ?>" csoport szerkesztése</h3>
  
   <form action="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=edit_role_end&name=<?php echo _get('name'); ?>" method="post" style="padding-left: 20px;">
    <table>
   <?php foreach($capabilities_wp AS $role): ?>
     <tr>
      <td style="padding-right: 10px;"><?php echo _format_role($role); ?></td>
		  <td><input type="checkbox" <?php if($role_obj->capabilities[$role]) echo 'checked=true'; ?> value="1" name="caps[<?php echo $role; ?>]" /></td>                
     </tr>
	 <?php endforeach; ?>
    </table>
    
    <br />
    <input type="reset" value="Visszaállít" />
    <input type="submit" value="Szerkeszt" />
   </form>
   
  <?php endif; ?>
 
 </div>

 <?php
 
}


?>