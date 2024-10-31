<?php
/*
Plugin Name: Open SSO plugin
Version: 1.0
Plugin URI: http://kir-dev.sch.bme.hu/wp-opensso
Description: Authenticate users using Open Single Sign On service.
Author: Pairg
*/

/*
* TODO Az SSO csoportagságait adatbázisba írja-e, vagy csak "virtuálisan" legyenek jelen? -> beállítás
* TODO A már beállított csoporttagságokat felülírja-e? -> beállítás
* TODO Automatikus bejelentkeztetés, ha IDPn be van lépve (van cookie de nincs változó =? _death_time).
* TODO Kötelező bejelentkezés.
* TODO Ha nem létezik egy szabályban a megadott csoport, akkor a szabályt kihagyja.
*/

// Register install/uninstall functions
register_activation_hook(__FILE__, '_install_sso' );
register_deactivation_hook(__FILE__, '_uninstall_sso');

// Register actions
add_action('init', 'initialize');
add_filter('init', 'authenticate');
add_action('wp_logout', 'logout');
add_action('wp_authenticate', 'login', 10, 2);

// Disable some function
add_action('lost_password', 'disable_function');
add_action('retrieve_password', 'disable_function');
add_action('password_reset', 'disable_function');
add_filter('check_password', 'disable_true');
add_filter('show_password_fields', 'disable_false');

// Disable overwrite user's data
add_action('profile_update', 'update_user', 10, 2);

// Add some admin page
add_action('admin_menu', 'sso_menu');


function sso_menu(){
 add_users_page('SSO Autentikáció', 'SSO Autentikáció', '8', __FILE__, 'sso_admin_page');
}


function _install_sso(){
 // Activate Simple Group Manager plugin
 $base = dirname(plugin_basename(__FILE__));
 $sgm = $base.'/simple_group_manager.php';
 if(!is_plugin_active($sgm)) activate_plugin($sgm);
 
 add_option('sso_rules_number', 0);
 add_option('sso_is_ssl', 1);
 add_option('sso_trigger', '/sso-login.php');
 add_option('sso_logout_url', 'https://idp.sch.bme.hu/opensso/UI/Logout');
 add_option('sso_login_url', 'https://idp.sch.bme.hu/opensso/UI/Login');
 add_option('sso_user_name', 'REMOTE_USER');
 add_option('sso_user_email', 'HTTP_EMAIL');
 add_option('sso_user_firstname', '');
 add_option('sso_user_lastname', '');
 add_option('sso_user_nickname', '');
}


function _uninstall_sso(){
 // Deactivate Simple Group Manager plugin
 $base = dirname(plugin_basename(__FILE__));
 $sgm = $base.'/simple_group_manager.php';
 //if(is_plugin_active($sgm)) deactivate_plugins($sgm);
 
 $roles_num = get_option('sso_rules_number');
 
 delete_option('sso_rules_number');
 delete_option('sso_is_ssl');
 delete_option('sso_trigger');
 delete_option('sso_logout_url');
 delete_option('sso_login_url');
 delete_option('sso_user_name');
 delete_option('sso_user_email');
 delete_option('sso_user_firstname');
 delete_option('sso_user_lastname');
 delete_option('sso_user_nickname');
 
 // Delete all rules
 for($i=0; $i<$roles_num; $i++){
  if(get_option('sso_rule_'.$i) != FALSE) delete_option('sso_rule_'.$i);
 }
}


// Check the settings, that every necessary paramterer was setted.
// If the SSL is setted, owerwrite the 'site url' and the 'home' option with 'https' verions.
function initialize(){
 get_sso_option('rules_number');
 get_sso_option('is_ssl');
 get_sso_option('trigger');
 get_sso_option('logout_url');
 get_sso_option('user_name');
 get_sso_option('user_email');

 if(get_sso_option('is_ssl') AND (!empty($_COOKIE['sunIdentityServerAuthNServer']) OR is_user_logged_in()) ){
  global $wp_object_cache;
  $options = wp_cache_get('alloptions', 'options');
  $options['siteurl'] = @preg_replace('|^http://|', 'https://', $options['siteurl']);
  $options['home'] = @preg_replace('|^http://|', 'https://', $options['home']);;
  wp_cache_set('alloptions', $options, 'options');
 }
}


// Disable function with die
function disable_function() {
 die('Disabled');
}


// Disable function: return TRUE
function disable_false() {
 return FALSE;
}


// Disable function: return FALSE
function disable_true(){
 return TRUE;
}


// Get the element of the _SERVER array
// Return FALSE if the element isn't setted.
function server($element){
 if(@isset($_SERVER[$element])) return $_SERVER[$element];
  else return FALSE;
}


// Get this plugin's option
// If the option isn't setted the program exit.
function get_sso_option($option_name){
 $value = get_option('sso_'.$option_name);
 if($value === FALSE) die("Nincs beállítva a ".$option_name." opció az SSO pluginban!");
  else return $value;
}


function _generate_password(){
 $pass = get_sso_option('user_name').uniqid(microtime(), TRUE);
 return sha1($pass);
}


// If the SSL is setted, return the 'https' version of the URL
function ssl($url = ''){
 if(empty($url)) return FALSE;
 if(get_sso_option('is_ssl')){
  $url = @preg_replace('|^http://|', 'https://', $url);
  if(empty($url)) return FALSE;
   else return $url;
 }else return $url;
}


// Get 'redirect_to' parameter
// @return (bool) FALSE - 'redirect_to' not setted OR invalid
//				 (string) URL - came from 'redirect_to' and use SSL (if it's necessary)
//				 (string) URL - came from the blog's settings and use SSL (if it's necessary) => if $always === TRUE
//				 "exit without error" - header redirect to the 'redirect_to's value => if $redirect === TRUE
function redirect_to($always = FALSE, $redirect = FALSE){
 if(@isset($_REQUEST['redirect_to']) AND @!empty($_REQUEST['redirect_to'])) $redirect_to = $_REQUEST['redirect_to'];
  else $redirect_to = FALSE;
 if($redirect_to) $is_valid = @preg_match('|^https?://'.server('HTTP_HOST').'*|', $redirect_to);
 if(!isset($is_valid) OR empty($is_valid)) $redirect_to = FALSE;
 if($redirect_to){
  $url = $redirect_to;
 }else{
  if(!$always) return FALSE;
  global $wp_object_cache;
  $options = wp_cache_get('alloptions', 'options');
  if(!empty($options['home'])) $url = $options['home'];
   else $url = $options['siteurl'];
 }
 if($redirect){
  header('Location: '.ssl($url));
  exit(0);
 }else return ssl($url);
}


// Logout
// Go to the idp's logout URL.
function logout(){
 $logout_url = ssl(get_sso_option('logout_url'));
 $goto = redirect_to(TRUE);
 header('Location: '.$logout_url.'?goto='.urlencode($goto));
 exit(0);
}


// Login
// Creat/login (if exitsts) the user or (else) go to the Identity Provider.
function login($user_name, $password){
 $user_name = server(get_sso_option('user_name'));
 if(empty($_COOKIE['sunIdentityServerAuthNServer'])){
  // Go to IDP!
  $now = 'http://'.server('HTTP_HOST').server('REQUEST_URI');
  $login_url = ssl(get_option('sso_login_url'));
  $trigger = 'http://'.server('HTTP_HOST').get_sso_option('trigger');
  $trigger = ssl($trigger);
  $goto = redirect_to(TRUE);
  $url = $login_url.'?goto='.urlencode($trigger.'?redirect_to='.$goto);
	header('Location: '.$url);
  exit(0);
 }elseif(!empty($_COOKIE['sunIdentityServerAuthNServer']) AND empty($user_name)){
  // Go to trigger!
  $now = 'http://'.server('HTTP_HOST').server('REQUEST_URI');
  $trigger = get_sso_option('trigger').'?redirect_to='.$now;
  $trigger = ssl($trigger);
	header('Location: '.$trigger);
  exit(0);
 }elseif(!empty($_COOKIE['sunIdentityServerAuthNServer']) AND !empty($user_name) AND !is_user_logged_in()){
  // Login!
	$user_email = server(get_sso_option('user_email'));
  $user = get_userdatabylogin($user_name);
  if($user === FALSE){
   // creat new user
   require_once(WPINC . DIRECTORY_SEPARATOR . 'registration.php');
   $id = wp_create_user($user_name, _generate_password(), $user_email);
   $user = get_userdata($id);
	}
	// login...
  _update_userdata($user);
  $password = _generate_password();
 }else{
  // Go to the blog!
  redirect_to(TRUE, TRUE);
 }
}


// Authenticate AND Trigger
function authenticate(){
 // If this site is a login site exit from this function.
 $now = server('HTTP_HOST').server('PHP_SELF');
 if(@preg_match('|^https?://'.$now.'*$|', wp_login_url())) return;

 // The Trigger
 if(@preg_match('|^'.get_sso_option('trigger').'*|', server('PHP_SELF'))){
  // If the user not logged in: login.
  $user_name = server(get_sso_option('user_name'));
	if(!is_user_logged_in()) wp_login($user_name, _generate_password());
	 else redirect_to(FALSE, TRUE);
 }
 
 // If user logged in... (general)
 if(is_user_logged_in()){
  // HTTPS
  if(get_sso_option('is_ssl')){
   if(!isset($_SERVER['HTTPS']) OR 'on' != strtolower($_SERVER['HTTPS'])){
    header('Location: https://'.server('HTTP_HOST').server('REQUEST_URI'), TRUE, 307);
    exit(0);
	 }
	}
  // Logout...
	if(empty($_COOKIE['sunIdentityServerAuthNServer'])) wp_logout();
  // Go to trigger!
	$user_name = server(get_sso_option('user_name'));
  if(empty($user_name)){
   $now = 'http://'.server('HTTP_HOST').server('REQUEST_URI');
   $trigger = get_sso_option('trigger').'?redirect_to='.$now;
   $trigger = ssl($trigger);
	 header('Location: '.$trigger);
   exit(0);
  }
  $user = get_userdatabylogin($user_name);
  set_user_groups();
 }
}


// If the user's data updated this function set up with the original (sso) data
function update_user($user_id, $old_userdata){
 $old_userdata = (array)$old_userdata;
 if($old_userdata['user_nicename']) $user_nicename = $old_userdata['user_nicename'];
 if($old_userdata['user_pass']) $user_pass = $old_userdata['user_pass'];
 if($old_userdata['user_email']) $user_email = $old_userdata['user_email'];
 if($old_userdata['nickname'] AND server(get_option('sso_user_nickname')) !== FALSE)
  update_usermeta($user_id, 'nickname', $old_userdata['nickname']);
 if($old_userdata['first_name'] AND server(get_option('sso_user_firstname')) !== FALSE)
  update_usermeta($user_id, 'first_name', $old_userdata['first_name']);
 if($old_userdata['last_name'] AND server(get_option('sso_user_lastname')) !== FALSE)
  update_usermeta($user_id, 'last_name', $old_userdata['last_name']);

 global $wpdb;
 $data = compact('user_pass', 'user_email', 'user_nicename');
 $data = stripslashes_deep($data);
 $e = $wpdb->update($wpdb->users, $data, array('ID' => $user_id));
}


// Update the user's data
function _update_userdata($userdata){
 $userdata = (array)$userdata;
 $userdata2['user_email'] = server(get_sso_option('user_email'));
 $nickname = server(get_option('sso_user_nickname'));
 if($nickname !== FALSE AND $nickname !== $userdata['nickname']) $userdata2['nickname'] = $nickname;
 $firstname = server(get_option('sso_user_firstname'));
 if($firstname !== FALSE AND $firstname !== $userdata['firstname']) $userdata2['first_name'] = $firstname;
 $lastname = server(get_option('sso_user_lastname'));
 if($lastname !== FALSE AND $lastname !== $userdata['lastname']) $userdata2['last_name'] = $lastname;

 update_user($userdata['ID'], (array)$userdata2);
 //require_once(WPINC . DIRECTORY_SEPARATOR . 'registration.php');
 //wp_update_user((array)$userdata);
 
 set_user_groups();
}


// Save rule
function _save_rule($var, $regexp, $group){
 $rule = array('var' => $var, 'regexp' => urlencode($regexp), 'group' => urlencode($group));
 $rule = serialize($rule);
 $num = get_option('sso_rules_number');
 if(get_option('sso_rule_'.$num) == FALSE){
  add_option('sso_rule_'.$num, $rule);
  update_option('sso_rules_number', ++$num);
 }else{
  return FALSE;
 }
 return TRUE;
}


// Update rule
function _update_rule($rule_num, $var, $regexp, $group){
 $rule = array('var' => $var, 'regexp' => urlencode($regexp), 'group' => urlencode($group));
 $rule = serialize($rule);
 $num = get_option('sso_rules_number');
 if($num <= $rule_num) return FALSE;
 if(get_option('sso_rule_'.$rule_num) == FALSE){
  return FALSE;
 }else{
  update_option('sso_rule_'.$rule_num, $rule);
 }
 return TRUE;
}


// Delete rule
function _delete_rule($rule_num){
 $num = get_option('sso_rules_number');
 if($num <= $rule_num) return FALSE;
 if(get_option('sso_rule_'.$rule_num) != FALSE){
  delete_option('sso_rule_'.$rule_num);
 }
}


// Get rule
// @return array Syntax: array('var' =>'', 'regexp'=>'', 'group'=>'');
function _get_rule($rule_num){
 if(get_option('sso_rule_'.$rule_num) != FALSE){
  $rule = get_option('sso_rule_'.$rule_num);
 }else{
  return FALSE;
 }
 $rule = unserialize($rule);
 $rule['regexp'] = urldecode($rule['regexp']);
 $rule['group'] = urldecode($rule['group']);
 return $rule;
}


// Run rule
// @return bool Return TRUE if the user is in the group (else return FALSE). 
function _run_rule($var, $regexp, $group){
 if(server($var)){
	$groups = server($var);
	foreach(explode('|', $groups) as $value){
   if(@preg_match($regexp, trim($value))) return TRUE;
  }
 }
 return FALSE;
}


// Get the user's groups
// @return array Return the array of the group names.
function _get_user_groups(){
 $groups = array();
 for($i=0; $i<get_sso_option('rules_number'); $i++){
  $rule = _get_rule($i);
  if($rule != FALSE){
	 $is_group = _run_rule($rule['var'], $rule['regexp'], $rule['group']);
	 if($is_group === TRUE) $groups[] = $rule['group'];
  }
 }
 return $groups;
}


// Set user to their groups
// Creat connection between goups and the user.
function set_user_groups(){
 $groups = _get_user_groups();
 $user_name = server(get_sso_option('user_name'));
 $user = get_userdatabylogin($user_name);
 $inherited_groups = get_usermeta($user->ID, 'wp_capabilities');
 $end_groups = $inherited_groups;
 $update = FALSE;
 for($i=0; $i<count($groups); $i++){
  if(!isset($inhereted_groups[$groups[$i]]) OR $inhereted_groups[$groups[$i]] != $end_groups[$groups[$i]]) $update = TRUE;
  $end_groups[$groups[$i]] = TRUE;
 }
 if($update) update_usermeta($user->ID, 'wp_capabilities', $end_groups);
}
 
 
// Admin page for this plugin
function sso_admin_page(){
 global $wp_roles;
 $groups = $wp_roles->get_names();

 // Edit datas
 $edit_rule = FALSE;
 switch($_GET['action']){
 
	case 'add_rule':
   if($_POST['var'] AND $_POST['regexp'] AND $_POST['group'] AND isset($groups[$_POST['group']])){
    $is_saved = _save_rule($_POST['var'], $_POST['regexp'], $_POST['group']);
    if($is_saved === FALSE) $message = "A szabályt nem sikerült elmenteni!";
   }else{
	  $message = "A szabályt nem sikerült elmenteni, mert hiányos!";
   }
   break;
  
	case 'delete_rule':
   $rule_id = $_GET['rule_id'];
   if(is_numeric($rule_id)) _delete_rule($rule_id);
   break;
  
	case 'edit_rule':
   $rule_id = $_GET['rule_id'];
   if(isset($rule_id) AND is_numeric($rule_id)){
    if(_get_rule($rule_id)){
     $edited_rule = _get_rule($rule_id);
     $edit_rule = TRUE;
    }else{
     $message = "A szabályt nem sikerült módosítani, mert ilyen szabály nem létezik!";
		}
   }else{
    $message = "A szabályt nem sikerült módosítani, mert hibás az azonosítója!";
	 }
   break;
   
   case 'edit_rule_end':
    $rule_id = $_GET['rule_id'];
    if(isset($rule_id) AND is_numeric($rule_id)){
     if(isset($_POST['var']) AND isset($_POST['regexp']) AND isset($_POST['group']) AND isset($groups[$_POST['group']])){
	    $is_updated = _update_rule($rule_id, $_POST['var'], $_POST['regexp'], $_POST['group']);
	    if($is_updated === FALSE) $message = "A szabályt nem sikerült módosítani!";
	   }else{
	    $message = "A szabályt nem sikerült módosítani, mert hiányos!";
		 }
    }else{
     $message = "A szabályt nem sikerült módosítani, mert hibás az azonosítója!";
	  }
    break;
   
   case 'edit_settings':
    if(isset($_POST['is_ssl'])){
     update_option('sso_is_ssl', (int)$_POST['is_ssl']);
		}
    if(isset($_POST['trigger']) AND is_string($_POST['trigger'])){
     update_option('sso_trigger', $_POST['trigger']);
		}
    if(isset($_POST['logout_url']) AND is_string($_POST['logout_url'])){
     update_option('sso_logout_url', $_POST['logout_url']);
		}
    if(isset($_POST['login_url']) AND is_string($_POST['login_url'])){
     update_option('sso_login_url', trim($_POST['login_url']));
		}
    if(isset($_POST['user_name']) AND is_string($_POST['user_name'])){
     update_option('sso_user_name', $_POST['user_name']);
		}
    if(isset($_POST['user_email']) AND is_string($_POST['user_email'])){
     update_option('sso_user_email', $_POST['user_email']);
		}
    if(isset($_POST['user_lastname']) AND is_string($_POST['user_lastname'])){
     update_option('sso_user_lastname', $_POST['user_lastname']);
		}
    if(isset($_POST['user_firstname']) AND is_string($_POST['user_firstname'])){
     update_option('sso_user_firstname', $_POST['user_firstname']);
		}
    if(isset($_POST['user_nickname']) AND is_string($_POST['user_nickname'])){
     update_option('sso_user_nickname', $_POST['user_nickname']);
		}
    break;

 }

 ?>
 
 <div class="wrap" style="padding-bottom: 40px;">
 
  <h2>SSO Autentikáció</h2>
  
  <?php if($message): ?>
  <blockquote style="border: 1px solid; margin: 15px; padding: 5px; display: block;" id="rules">
   <b><?php echo $message; ?></b>
  </blockquote>
  <?php endif; ?>
  
  <?php if(!$edit_rule): ?>
  
  <form action="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=edit_settings" method="post" style="padding-bottom: 20px;">
   <p>Az SSO által védett hely URL-je (trigger):<br />
    <input type="text" size="70" name="trigger" value="<?php echo get_option('sso_trigger'); ?>" />
	 </p>
   <p>Az SSO szolgáltatás kiléptető URL-je:<br />
    <input type="text" size="70" name="logout_url" value="<?php echo get_option('sso_logout_url'); ?>" />
	 </p>
   <p>Az SSO szolgáltatás beléptető URL-je:<br />
    <input type="text" size="70" name="login_url" value="<?php echo get_option('sso_login_url'); ?>" /><br />
    <small>
		*Ezt az URL-t akkor használjuk, ha az IDP-ről már kijelentkezett, de az ágens még tárolja<br />
		a felhasználó adatit.	Ebből adódóan a védett URL-re (trigger) ugráskor az úgy viselkedne,<br />
		mintha be lenne lépve. Ekkor nem a védett URL-re (trigger) ugrik, hanem ide. Ha ez nincs<br />
		beállítva, akkor engedi belépni a felhasználót!
		</small>
	 </p>
   <p>Használ-e SSL titkosítást:<br />
    <select name="is_ssl">
     <option value="1" <?php if(get_option('sso_is_ssl')) echo 'selected="selected"'; ?>>Igen</option>
     <option value="0" <?php if(!get_option('sso_is_ssl')) echo 'selected="selected"'; ?>>Nem</option>
    </select>
	 </p>
   <p>A felhasználói nevet tartalmazó _SERVER változó:<br />
    <input type="text" name="user_name" value="<?php echo get_option('sso_user_name'); ?>" />
	 </p>
   <p>A felhasználó e-mail címét tartalmazó _SERVER változó:<br />
    <input type="text" name="user_email" value="<?php echo get_option('sso_user_email'); ?>" />
	 </p>
   <p>A felhasználó becenevét tartalmazó _SERVER változó (opcionális):<br />
    <input type="text" name="user_nickname" value="<?php echo get_option('sso_user_nickname'); ?>" />
	 </p>
   <p>A felhasználó vezetéknevét tartalmazó _SERVER változó (opcionális):<br />
    <input type="text" name="user_lastname" value="<?php echo get_option('sso_user_lastname'); ?>" />
	 </p>
   <p>A felhasználó keresztnevét tartalmazó _SERVER változó (opcionális):<br />
    <input type="text" name="user_firstname" value="<?php echo get_option('sso_user_firstname'); ?>" />
	 </p>
	 <input type="submit" value="Beállít" />
  </form>
 
  <h3 id="rules">Szabályok</h3>
  <table cellspacing="0" cellpadding="5px" border="1" style="padding-bottom: 20px;">
   <thead>
    <tr>
     <td style="border: 1px solid #464646; padding: 5px; font-weight: bold;">
      Változó
     </td>
     <td style="border-top: 1px solid #464646; border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px; font-weight: bold;">
      Regexp szabály
     </td>
     <td style="border-top: 1px solid #464646; border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px; font-weight: bold;">
      Csoport
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
   <?php
	 for($i=0; $i<get_option('sso_rules_number'); $i++){
	  $rule = _get_rule($i);
	  if($rule !== FALSE){
	 ?>
    <tr>
     <td style="border-bottom: 1px solid #464646; border-left: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><?php echo $rule['var']; ?></td>
     <td style="border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><?php echo wordwrap($rule['regexp'], 45, "<br />\n", TRUE); ?></td>
     <td style="border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><?php echo $groups[$rule['group']]; ?></td>
     <td style="border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><a href="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=edit_rule&rule_id=<?php echo $i; ?>">Szerkesztés</a></td>
     <td style="border-bottom: 1px solid #464646; border-right: 1px solid #464646; padding: 5px;"><a href="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=delete_rule&rule_id=<?php echo $i; ?>#rules">Törlés</a></td>
    </tr>
   <?php
    }
	 } 
	 ?>
	 </tbody>
  </table>

  <h3>Új szabály hozzáadása</h3>
  <form method="post" action="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=add_rule#rules">
   Változó neve:<input type="text" name="var" /><br />
   Regexp:<input type="text" size="70" name="regexp" /><br />
   Csoport neve:<select name="group" title="--Csoport--">
			          <?php foreach($groups AS $group=>$name): ?>
                 <option value="<?php echo $group; ?>"><?php echo $name; ?></option>
								 <?php endforeach; ?>
                </select><br />
   <input type="submit" value="Hozzáad" />
  </form>
  
 <?php else: ?>
 
  <h3>Szabály szerkesztése</h3>
  <form method="post" action="/wp-admin/users.php?page=<?php echo plugin_basename(__FILE__); ?>&action=edit_rule_end&rule_id=<?php echo $_GET['rule_id']; ?>#rules">
   Változó neve:<input type="text" name="var" value="<?php echo $edited_rule['var']; ?>" /><br />
   Regexp:<input type="text" size="70" name="regexp" value="<?php echo $edited_rule['regexp']; ?>" /><br />
   Csoport neve:<select name="group" title="--Csoport--">
			          <?php foreach($groups AS $group=>$name): ?>
                 <option value="<?php echo $group; ?>" <?php if($group === $edited_rule['group']) echo 'selected="selected" '; ?>><?php echo $name; ?></option>
								 <?php endforeach; ?>
                </select><br />
   <input type="reset" value="Visszaállítás" />
   <input type="submit" value="Szerkeszt" />
  </form>
  
 <?php endif; ?>
 
 </div>
 
 
 <?php

}


?>