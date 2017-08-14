<?php
/*
Plugin Name: SEO Booster Lite
Plugin Script: seo-booster.php
Plugin URI: http://www.myWordPress.com
Description: Dynamic SEO. Use Search Engine traffic to dynamically optimize your blog!
Version: 1.9.4.1
Author: myWordPress.com
Author URI: http://www.myWordPress.com
Min WP Version: 2.6
Max WP Version: 2.8.6
Update Server: http://www.myWordPress.com


== Changelog ==

= 1.9.4.1 =
* No longer loads plugin CSS on all admin pages. Woopsie! Thanks Milan! :-)
* Verified working with WordPress v. 2.8.6
* Updated Help and Credits tabs.

= 1.9.4 =
* New wicked interface!

= 1.9.3.3 =
* Minor bug and support for PluginSponsors.com

= 1.9.3.1 =
* Verified compatible with WordPress 2.8.5
* Changed the settings page a little bit visually.
* Better explanation in the widget regarding affiliate ID.

= 1.9.3 =
* Affiliate partner id added... You can now promote SEO Booster PRO and get a commission for every sale!

= 1.9.2 =
* Attribution footer data added

= 1.9.1b =
* Bug-fixing the PHP function

= 1.9.1 =
* Now automatically ignores "related:"-searches
* Database cleaning also removes "related:"-searches

= 1.9.0 =
* Proper deletion of redudant data in database. Thanks to Milan Petrovic
* Query ignore list. New for the Lite version, previously only available for PRO users!

= 1.8.9 =
* Added deletion of numeric-only searches to the optional database cleaning introduced in v. 1.8.8

= 1.8.8 =
* Optional database cleaning. Accessible via the plugin page -> "Database operations" -> "Clean Database"

= 1.8.7 =
* Previous release was badly published, now fixed.
* Memory decrease. 
* The plugin will now ignore SITE:, CACHE:, and number only searches always, hence the settings panel has been disabled for now.

= 1.8.3 =
* Verified working for WordPress 2.8.4

= 1.8.2 =
* Fix for deleted posts
* Fix for not boosting same post several times

= 1.8.1 =
* Minor visual bug fix, introduced in v. 1.8

= 1.8 =
* Memory reduction.
* Backlinking is no longer optional.
* Boosts incoming searches for SERP result pages 2 and lower.
* New boost algorithm


= 1.7 =
* Database table updates for future versions.
* Visual changes to layout.

= 1.6 =
* WordPress 2.8 support.


*/





/*  Copyright 2008-2009  myWordPress.com  (email : contact@mywordpress.com)

	If the plugin finds a post that is referred from page 2 in the search engines, it attempts to boost its placement by creating
	sidewide links through the widget.

	Bringing the posts from page 2 to page 1 can mean a tremendous boost in your search engine traffic.
	
	This plugin uses a code-snippet from TellinYa;
	
	How to get Query for Refering Search Engine from Referer in PHP : 5ubliminal's TellinYa
	http://www.tellinya.com/read/2007/07/11/34.html
	
	His new homepage is here:
	http://blog.5ubliminal.com/
	
	
	From version 1.4, a small javascript code was added from the 
	FeedStats plugin (by Andr?s Nieto), http://bueltge.de/wp-feedstats-de-plugin/171/
	

*/


// ### No direct access to the plugin outside Wordpress
if (preg_match('#'.basename(__FILE__) .'#', $_SERVER['PHP_SELF'])) { 
	die('Direct access to this file is not allowed!'); 
}

function seobooster_admin_footer() {
	$plugin_data = get_plugin_data( __FILE__ );
	printf('%1$s plugin | Version %2$s | by %3$s<br />', $plugin_data['Title'], $plugin_data['Version'], $plugin_data['Author']);
}

// ----------------------------------------------------------------------------
/* $ref is optional, if not set will use current! */
function seobooster_sereferer($ref = false){
    $SeReferer = (is_string($ref) ? $ref : $_SERVER['HTTP_REFERER']);
    if( //Check against Google, Yahoo, MSN, Ask and others
        preg_match(
        "/[&\?](q|p|w|searchfor|as_q|as_epq|s|query)=([^&]+)/i",
        $SeReferer,$pcs)
    ){
        if(preg_match("/https?:\/\/([^\/]+)\//i",$SeReferer,$SeDomain)){
            $SeDomain    = trim(strtolower($SeDomain[1]));
            $SeQuery    = $pcs[2];
            if(preg_match("/[&\?](start|b|first|stq)=([0-9]*)/i",$SeReferer,$pcs)){
                $SePos    = (int)trim($pcs[2]);
                $SePage   = ($SePos/ 10) +1 ;
            }
        }
    }
    if(!isset($SeQuery)){
        if( //Check against DogPile
            preg_match(
            "/\/search\/web\/([^\/]+)\//i",
            $SeReferer,$pcs)
        ){
            if(preg_match("/https?:\/\/([^\/]+)\//i",$SeReferer,$SeDomain)){
                $SeDomain    = trim(strtolower($SeDomain[1]));
                $SeQuery    = $pcs[1];
            }
        }
    }
    // We Do Not have a query
    if(!isset($SeQuery)){ return false; }
    $OldQ=$SeQuery;
    $SeQuery=urldecode($SeQuery);
    // The Multiple URLDecode Trick to fix DogPile %XXXX Encodes
    while($SeQuery != $OldQ){
        $OldQ=$SeQuery; $SeQuery=urldecode($SeQuery);
    }
    //-- We have a query
   
    return array(
        "Se"=>$SeDomain,
        "Query"=>$SeQuery,
        "Pos"=>(int)$SePos,
        "Page"=>(int)$SePage,
        "Referstring"=>$SeReferer
    );
}
// ----------------------------------------------------------------------------


// ### Base installation function for LinkLaunder
function seobooster_install () { // Database setup. Runs first time plugin is activated
	global $wpdb;
	$table_name = $wpdb->prefix . "seobooster";	
	if ( file_exists(ABSPATH . 'wp-admin/includes/upgrade.php') ) {
	    require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
	} else { // Wordpress <= 2.2
	    require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	}
		    
	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
	$results = $wpdb->query("CREATE TABLE IF NOT EXISTS `{$table_name}` (
		  `id` int(11) NOT NULL auto_increment,
		  `PostId` int(11) default '0',
		  `SeDate` datetime NOT NULL default '0000-00-00 00:00:00',
		  `SeQuery` varchar(250) collate utf8_danish_ci NOT NULL default '',
		  `SeDomain` varchar(50) collate utf8_danish_ci NOT NULL,
		  `SeLang` varchar(8) collate utf8_danish_ci NOT NULL,
		  `SePage` varchar(250) collate utf8_danish_ci NOT NULL default '',
		  `SePos` int(4) NOT NULL,
		  `SePosLast` int(4) NOT NULL,
		  `SePosCheck` datetime NOT NULL,
		  `SeHits` int(4) default '1',
		  `SeRef` varchar(250) collate utf8_danish_ci NOT NULL default '',
		  PRIMARY KEY  (`id`),
		  KEY `SeQuery` (`SeQuery`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ");	
	}
	else 
	{ //Table does indeed exist, here we can make updates automatically if needed.
		$results = $wpdb->query("ALTER TABLE `$table_name` add column `SeRef` varchar(250) NOT NULL default '';");
		$results = $wpdb->query("ALTER TABLE `$table_name` ADD `SeDomain` VARCHAR( 50 ) NOT NULL AFTER `SeQuery` ;");
		$results = $wpdb->query("ALTER TABLE `$table_name` ADD `SeLang` VARCHAR( 8 ) NOT NULL AFTER `SeDomain` ;");
		$results = $wpdb->query("ALTER TABLE `$table_name` ADD `SePos` INT( 4 ) NOT NULL DEFAULT '0';");
		$results = $wpdb->query("ALTER TABLE `$table_name` CHANGE `SePos` `SePos` INT( 4 ) NOT NULL DEFAULT '0';"); 
		$results = $wpdb->query("ALTER TABLE `$table_name` ADD `SePosLast` INT( 4 ) NOT NULL AFTER `SePos` ;");
		$results = $wpdb->query("ALTER TABLE `$table_name` ADD `SePosCheck` DATETIME NOT NULL AFTER `SePosLast` ;");
	}


}

// ### This function is called every time wp_head() is called
function seobooster_checkreferrer() {
	global $wpdb,$wp_query,$ids;
	$table_name = $wpdb->prefix . "seobooster";
	if (is_feed()) return ''; // We dont care if its an RSS hit
	if (is_category()) return ''; 
	if (is_front_page()) return ''; 	
//	if (is_home()) return '';
		
	$referer= ($_SERVER['HTTP_REFERER']); // Just to make sure
	$se=seobooster_sereferer($referer); //Checking the referrer and splitting the data out
	$PostId		= get_the_id(); //Get the id of the post we're viewing!
	if (!$PostId<>'') 	{$PostId		= $wp_query->post->ID; } //Double check, just in case...

	if (($se) && ($PostId<>'')) { // If the result is from a search engine
	
	
	// Start check if new field exists
	// This part automatically upgrades the database for people who installed the first versions...
		$fields = mysql_list_fields(DB_NAME, $table_name);
		$columns = mysql_num_fields($fields);
		for ($i = 0; $i < $columns; $i++) {$field_array[] = mysql_field_name($fields, $i);}
		if (!in_array('SeRef', $field_array))
		{
			$result = mysql_query("ALTER TABLE `$table_name`  add column `SeRef` varchar(250) NOT NULL default '';");
		}

		$SePage		= $se['Page'];
		$SeQuery	= strtolower($se['Query']);
//IGNORE NUMBER-ONLY SEARCHES?		
		if ((is_numeric($SeQuery))) {return; } // Check for if the query is numeric...
//IGNORE "SITE:"-SEARCHES?
		$sitepos = strpos($SeQuery, 'site:');
		if (($sitepos <> false)) {  return; } // Check for if the query contains "site:"
//IGNORE "RELATED:"-SEARCHES?
		$sitepos = strpos($SeQuery, 'related:');
		if (($sitepos <> false)) {  return; } // Check for if the query contains "related:"
//IGNORE "CACHE:"-SEARCHES?
		$sitepos = strpos($SeQuery, 'cache:');// Check for if the query contains "cache:"
		if (($sitepos <> false)) {  return; } 
		
		$SeRef		= $se['Referstring'];
		if ($SePage=='0'){ $SePage='1';}

		//check if we already have registered this search query...
		$query = "SELECT * FROM `$table_name` WHERE `PostId` = $PostId AND `SeQuery` = '$SeQuery'";
		$excisting = mysql_query($query);
		if ($excisting) {$row = mysql_fetch_array($excisting); }
		$SeHits=$row['SeHits'];
		
		
		if ($SeHits=='') { // NEW QUERY
		$query = "INSERT INTO `$table_name` (PostId, SeDate, SeQuery,  SePage,SeRef) 
					  VALUES (
					  '$PostId',
					  NOW(), 
					  '$SeQuery',
					  '$SePage',
					  '$SeRef'
					  )";
					  $success = mysql_query($query);
					  
		} else {
			$SeHits=$row['SeHits']+1;
			$query = "UPDATE `$table_name` SET `SeHits` = '$SeHits', `SePage` = '$SePage', `SeRef` = '$SeRef' WHERE `id` ='".$row['id']."' LIMIT 1 ;";
			$success = mysql_query($query);
		
		}			   
					
	}

	unset($se);
	unset($referer);
	unset($PostId);
	unset($field_array);
	unset($columns);
	unset($fields);
	unset($SeQuery);
	unset($SePage);
	unset($SeHits);
	unset($query);
	unset($success);
	unset($excisting);
	unset($SeRef);
	unset($ignorecache);
	unset($sitepos);
	unset($ignoresite);
	unset($ignorenumbers);
	unset($wpdb);
	unset($wp_query);
	unset($ids);
	unset($table_name);
	
}



// ### Use this function if you do not wish to use the widget...
// Sample use: if(function_exists('seobooster_show_referrers')){ seobooster_show_referrers('10',TRUE); }
function seobooster_show_referrers($limit,$linklove=true){
		$limit=intval($limit);
		if (!is_integer($limit)) {exit();} //If the value is not a number, we do not want to display anything, and we will exit.

		global $wpdb;
		$table_name = $wpdb->prefix . "seobooster";		

		$seoboosterignore=get_option('seoboosterpro_ignorelist');
		$ignorearray = explode("\n", $seoboosterignore);
		$ignorestring=implode("','",$ignorearray);

		$ignorestring = str_replace(" '", "'", $ignorestring);
		$ignorestring = str_replace("\n", '', $ignorestring);
		$ignorestring = str_replace("\r", '', $ignorestring);
		$table_name = $wpdb->prefix . "seobooster";		
		$query = "SELECT * FROM `$table_name` WHERE ((`SePage`>'1') && (`SeQuery` NOT IN ('$ignorestring'))) GROUP BY `PostId` order by `SeHits` DESC limit $limit";
		    

		$posthits = $wpdb->get_results($query, ARRAY_A);
		
		    if ($posthits){
		    	echo "<div id='seoboosterlite'><ul>";
		    	foreach ($posthits as $hits) {
		    			$permalink=get_permalink($hits['PostId']); //Gets the permalink, which is empty if the post is deleted.
		    			if ($permalink<>''){
		    				$count++;
		    				echo "<li><a href='".$permalink."'>".$hits['SeQuery']."</a></li>";
		    			}
		    		}
		    		$backanchor=get_option('seobooster_backlink',false);
		    		$backlink=get_option('seobooster_backurl',false);
		    		echo "<li><a href='".$backlink."'>".$backanchor."</a></li>";
		    	echo "</ul></div>";
		    	}
		   

		unset($wpdb);
		unset($posthits);
		unset($hits);
		unset($query);
		unset($limit);
}




function widget_init_seobooster() {
	// Check for required functions
	if (!function_exists('register_sidebar_widget'))
		return;

		// ### The Widget Function
		function seobooster_widget($args) {
	 		extract($args);
			$blogurl=get_bloginfo('url');
			$options = get_option("widget_seobooster_widget");
			if (!is_array( $options )) //Default settings
			{
			$options = array(
			'title' => 'SEO Booster Lite',
			'limit' => '10',
			'partnerid' => ''
			);
			}
			
			echo $before_widget;
			echo $before_title;
			echo $options['title'];
			echo $after_title;
			global $wpdb;
			
			$seoboosterignore=get_option('seoboosterpro_ignorelist');
			$ignorearray = explode("\n", $seoboosterignore);
			$ignorestring=implode("','",$ignorearray);

			$ignorestring = str_replace(" '", "'", $ignorestring);
			$ignorestring = str_replace("\n", '', $ignorestring);
			$ignorestring = str_replace("\r", '', $ignorestring);
			$partnerid=$options['partnerid'];
			
			$table_name = $wpdb->prefix . "seobooster";		
			$query = "SELECT * FROM `$table_name` WHERE ((`SePage`>'1') && (`SeQuery` NOT IN ('$ignorestring'))) GROUP BY `PostId` order by `SeHits` DESC limit ".$options['limit'];
				
			$posthits = $wpdb->get_results($query, ARRAY_A);
			
				if ($posthits){
					echo "<ul>";
					foreach ($posthits as $hits) {
							$permalink=get_permalink($hits['PostId']); //Gets the permalink, which is empty if the post is deleted.
							if ($permalink<>''){
								$count++;
								echo "<li><a href='".$permalink."'>".$hits['SeQuery']."</a></li>";
							}
						}
						$backanchor=get_option('seobooster_backlink',false);
						$backlink=get_option('seobooster_backurl',false);
						
						if ($partnerid<>'') {
							$backlink="http://cleverplugins.com/idevaffiliate/idevaffiliate.php?id=".$partnerid."_2"; 
							$backanchor="SEO Booster PRO";
						}
						echo "<li><a href='".$backlink."' target='_blank'>".$backanchor."</a></li>";
					echo "</ul>";
					}
					
			echo $after_widget;
				} 
			
			
	
			
			
			
				
			function seobooster_widget_control()
			{
		
			
		
			$options = get_option("widget_seobooster_widget");
			
			if (!is_array( $options ))
			{
			$options = array(
			'title' => 'SEO Booster Lite',
			'limit' => '10',

			'partnerid' => ''
			);
			update_option("widget_seobooster_widget", $options);
			}

			
			if ($_POST['seobooster_widget-Submit'])
			
			{
	
				$options['title'] = htmlspecialchars($_POST['seobooster_widget-WidgetTitle']);
				$options['limit'] = $_POST['seobooster_widget-WidgetResults'];
				$options['partnerid'] = $_POST['seobooster_widget-partnerid'];
				update_option("widget_seobooster_widget", $options);
			}

			
			?>
			<p>
			<label for="seobooster_widget-WidgetTitle">Headline: </label>
			<input type="text" id="seobooster_widget-WidgetTitle" name="seobooster_widget-WidgetTitle" value="<?php echo $options['title'];?>" />
			<br>
			<label for="seobooster_widget-WidgetResults">Max Links: </label>
			<input type="text" id="seobooster_widget-WidgetResults" name="seobooster_widget-WidgetResults" value="<?php echo $options['limit'];?>" /><br/>
			<input type="hidden" id="seobooster_widget-Submit" name="seobooster_widget-Submit" value="1" />
			
			<label for="seobooster_widget-partnerid"><a href="http://cleverplugins.com/idevaffiliate/" target="_blank">Affiliate Partner</a> id: </label>
			<input type="text" id="seobooster_widget-partnerid" name="seobooster_widget-partnerid" value="<?php echo $options['partnerid'];?>" />
			<br>
			<small><em>Just the partner ID, ie '164', nothing else, the correct link will be generated.</em></small>
			
			<?php
			 if ($options['linklove']) {  
     			$seoboosterlinklove = ' checked="checked"';  
			 } else {  
   			  $seoboosterlinklove = '';  
			 } 	
			?>
			
			
			</p>

			<p>Want more flexibility and no backlink? <strong>Buy</strong> <a href="http://cleverplugins.com/wordpress-plugins/seo-booster-pro" target="_blank">SEO Booster PRO</a> now!</p>
			<?php
			}		

		register_sidebar_widget('SEO Booster Lite','seobooster_widget');
		register_widget_control('SEO Booster Lite', 'seobooster_widget_control', '', '145px');
}




if (!function_exists('seobooster_dashboard_widget_function'))
{
function seobooster_dashboard_widget_function() {
	include_once(ABSPATH . WPINC . '/rss.php');
	$baseurl=WP_CONTENT_URL . '/plugins/' . plugin_basename(dirname(__FILE__)). '/mywordpresssm.jpg';
	//echo '<div class="rss-widget">';	
	echo "<p><a href='http://mywordpress.com' target='_blank'><img src='$baseurl' class='alignright' border=0 ></a>";	
	$rss = fetch_rss('http://feeds2.feedburner.com/mywordpresscom');
	
	if ($rss) {
	    $items = @array_slice($rss->items, 0, 3);

	    if (empty($items)) 
	    	echo '<li>No news right now.</li>';
	    else {
	    	foreach ( $items as $item ) { ?>
	    	<?php
	 //   	var_dump($item);
	    	?>
	    	<a href='<?php echo $item['link']; ?>' class="rsswidget" ><?php echo $item['title']; ?></a>

			<?php	
			$summary=$item['summary'];
			$place = strpos($summary,'Post from: <a href="http://mywordpress.com">WordPress Plugins - myWordPress</a>');
	$summary = str_replace('Post from: <a href="http://mywordpress.com">WordPress Plugins - myWordPress</a>', '',$summary);
	    		 
	    		 
	 ?><div class="rssSummary"> <?php 

	echo substr($summary,$place);	 

	 
	  ?></div>

	    	<?php }
	    }
	}

} 

} 

if (!function_exists('seobooster_add_dashboard_widgets'))
{
// Create the function use in the action hook
function seobooster_add_dashboard_widgets() {

	global $wp_version;

	if (version_compare($wp_version,"2.6","<")){
		exit ('');
	} 
	else
	{
		wp_add_dashboard_widget('seobooster_dashboard_widget', 'Clever WordPress Plugins', 'seobooster_dashboard_widget_function');	
		}
} 
} 



// ### The Admin options/settings screen
function seobooster_admin_options(){

 	global $wpdb, $wp_version;
 	$table_name = $wpdb->prefix . "seobooster";	
 	$blogurl=get_bloginfo('url');	

	$pluginfo=get_plugin_data(__FILE__);
	$version=$pluginfo['Version'];

	add_action( 'in_admin_footer', 'seobooster_admin_footer' );

//Clearing the database!!
	if ( ($_POST['action'] == 'clear') && $_POST['seobooster_clear'] ) {
		$nonce=$_POST['_seoboosternonce'];
	    if (! wp_verify_nonce($nonce, 'seobooster') ) die("Failed security check");
	    	
		if ( function_exists('current_user_can') && current_user_can('edit_plugins') ) {
			
			$wpdb->query ("TRUNCATE TABLE `$table_name`");

			echo '<div class="updated fade"><p>' . __('SEO Booster Lite Database has been cleaned!', 'seobooster') . '</p></div>';
		} else {
			wp_die('<p>'.__('You do not have sufficient permissions.').'</p>');
		}
	}
	

//----------------------------------------------
//CLEANING THE DATABASE FOR SITE: AND CACHE: QUERIES!
//----------------------------------------------

	if ( ($_POST['action'] == 'clean') && $_POST['seoboosterpro_clean'] ) {
		$nonce=$_POST['_seoboosterprononce'];
	    if (! wp_verify_nonce($nonce, 'seoboosterpro') ) die("Failed security check");
	    	
		if ( function_exists('current_user_can') && current_user_can('edit_plugins') ) {
			$before = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name;");
			
//			$after = (int )$wpdb->get_var("SELECT COUNT(*) FROM `$table_name` where (`SeQuery` like '%cache:%');");
			$wpdb->query("DELETE FROM `$table_name` where (`SeQuery` like '%cache:%');");
			$wpdb->query("DELETE FROM `$table_name` where (`SeQuery` like '%site:%');");
			$wpdb->query("DELETE FROM `$table_name` where (`SeQuery` like '%related:%');");
			
			$query = 'DELETE FROM `'.$table_name.'` where sequery REGEXP \'^(-|\\\+){0,1}([0-9]+\\\.[0-9]*|[0-9]*\\\.[0-9]+|[0-9]+)$\';';
			$wpdb->query($query);

			$after = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name;");

			$difference = $before-$after;
			
			if ($difference=='0') $difference = 'No';
			
			//$wpdb->query ("TRUNCATE TABLE `$table_name`");

			echo "<div class='updated fade'><p><p>The database has been cleaned.</p><p>Before the operation:$before, after the operation:$after</p><p><strong>$difference</strong> entries have been removed from the database.</p></div>";
		} else {
			wp_die('<p>'.__('You do not have sufficient permissions.').'</p>');
		}
	}
	
	


//Saving settings
	if ( ($_POST['action'] == 'insert') && $_POST['seobooster_save'] ) {
	
		$nonce=$_POST['_seoboosternonce'];
	    if (! wp_verify_nonce($nonce, 'seobooster') ) die("Failed security check");

		if ( function_exists('current_user_can') && current_user_can('edit_plugins') ) {
		
			update_option('seoboosterpro_ignorelist',$_POST['seoboosterpro_ignorelist']);	
// Always turned ON by default... As per version 1.8.4

/*
			update_option('seobooster_ignoreinteger',$_POST['seobooster_ignoreinteger']);
			update_option('seobooster_ignoresite',$_POST['seobooster_ignoresite']);
			update_option('seobooster_ignorecache',$_POST['seobooster_ignorecache']);
*/		
			echo '<div class="updated fade"><p>' . __('The options have been saved!', 'seobooster') . '</p></div>';
		} else {
			wp_die('<p>'.__('You do not have sufficient permissions.').'</p>');
		}
	}
	
	
			$siteurl = get_option('siteurl');
		$imgurl = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/cleverpluginslogo.jpg';
	  
    
    ?>
<div class="wrap">
	
		
		<div id="tabs">
		
		<div style="float:right;"><a href="http://cleverplugins.com/" target="_blank"><img src="<?php echo $imgurl; ?>"></a></div>
			<h2><?php _e("SEO Booster Lite", 'seobooster'); ?>  <span id="cpgreen"> v. <?php echo $version; ?></span></h2>
   <br class="clear" />
    <p><em>- A clever WordPress Plugin by <a href="http://cleverplugins.com">CleverPlugins.com</a></em></p>

<script type="text/javascript">
var psHost = (("https:" == document.location.protocol) ? "https://" : "http://");
document.write(unescape("%3Cscript src='" + psHost + "pluginsponsors.com/direct/spsn/display.php?client=seobooster&spot=' type='text/javascript'%3E%3C/script%3E"));
</script>
<p align="right"><em><small>The ads shown are from PluginSponsors.com. Read their <a href="http://pluginsponsors.com/privacy.html" target="_blank">FAQ</a>.</small></em></p> 
		
			<ul>
				<li><a href="#dashboard">Dashboard</a></li>
				<li><a href="#settings">Settings</a></li>
				<li><a href="#top-25">Top 25</a></li>
				<li><a href="#last-25">Last 25</a></li>
				<li><a href="#database">Database</a></li>
				<li><a href="#help">Help</a></li>
				<li><a href="#credits">Credits</a></li>
			</ul>
<div id="dashboard">
				<h3><?php _e('Dashboard', 'seobooster'); ?></h3>
				
				
				<h4>The PRO Version</h4>
				<p>SEO Booster PRO can do A LOT more:</p>
				<ul>
				<li>Detailed ranking checks for each keyword/phrase.</li>
<li>Export data to .csv</li>
<li>No forced backlinks!</li>
<li>Pinpoint exactly which SERP positions you want to improve rankings for!</li>
<li>(Optional) Auto tag posts with incoming search query.</li>
<li>(Optional) Auto tag all related posts with incoming search query.</li>
<li>(Optional/Experimental) Add most popular keywords to the title-tag.</li>
<li>See ranking changes for search queries</li>
<li>Lifetime updates/upgrades</li>
</ul>
				
				<a href="http://cleverplugins.com/wordpress-plugins/seo-booster-pro" target="_blank">see the cool features and <strong>buy</strong> SEO Booster PRO</a> now!</p>
				
			<h4>Affiliates!</h4>
									<p><?php _e('You can earn money referring your visitors to SEO Booster PRO, <a href="http://cleverplugins.com/idevaffiliate/" target="_blank">sign up as affiliate right now</a> and enter your partnerID in the Widget!', 'seobooster'); ?></p>
				
<p>Have you remembered to add the Widget to your sidebar?</p>
			
</div>			
			
			
<div id="settings">
				<h3><?php _e('Settings', 'seobooster'); ?></h3>

    					<form name="form1" method="post" action="<?php echo $location; ?>">
						<?php $nonce= wp_create_nonce  ('my-nonce');?>
						<?php
						
					    	$seoboosterignore=get_option('seoboosterpro_ignorelist');
						?>				
						<table summary="seobooster options" class="form-table">


							<tr valign="top">
								<th scope="row"><?php _e('Query ignore list', 'seobooster'); ?></th>
								<td><textarea name="seoboosterpro_ignorelist" class="large-text" rows="10"><?php echo $seoboosterignore; ?></textarea>
								<br /><?php _e('<em>SEO Booster Lite will not display the queries entered here.</em>', 'seoboosterpro'); ?>
								<br /><?php _e('<em>Seperate each keyword/phrase on new lines.</em>', 'seobooster'); ?></td>
							</tr>		

    					
						</table>
						<?php $nonce= wp_create_nonce('seobooster');?>
						<p class="submit">
						
							<input type="hidden" name="_seoboosternonce" value="<?php echo $nonce; ?>" />
							<input type="hidden" name="action" value="insert" />
							<input class="button-primary" type="submit" name="seobooster_save" value="<?php _e('Update Settings'); ?> &raquo;" />
						</p>
					</form>		
			
			
</div>
<div id="top-25">
			
					<h3><?php _e('Top 25 Incoming Search Queries', 'seobooster'); ?></h3>
				
				<?php
				$query = "SELECT * FROM `$table_name` order by `SeHits` DESC limit 10";
			
				$posthits = $wpdb->get_results($query, ARRAY_A);
			
				if ($posthits){
				?>
				<table class='widefat' cellspacing='0'><thead>
	<tr>
		<th>Search Query</th>
		<th>SERP Page</th>
		<th>Total visits</th>
		<th>Landingpage</th>
	</tr>
</thead>
				<?php
				//	echo "<table  class='widefat post' cellspacing='3' cellpadding='3'><tr><td><h5></h5></td><td><h5></h5></td><td><h5></h5></td><td><h5></h5></td></tr>";
					
					foreach ($posthits as $hits) {
						echo "<tr><td><em>".$hits['SeQuery']."</em></td><td><center>".$hits['SePage']."</center></td><td><center>".$hits['SeHits']."</center></td><td><small>".str_replace($blogurl,'',get_permalink($hits['PostId']))."</small></td></tr>";
						}
					
					echo "</table>";
					}				
				
				
				?>
			
</div>
			
			
			
			
			
			
			
<div id="last-25">
			<h3><?php _e('Last 25 SERP Visits', 'seobooster'); ?></h3>
				

					<?php
					
				$query = "SELECT * FROM `$table_name` order by `SeDate` DESC limit 35";
			
				$posthits = $wpdb->get_results($query, ARRAY_A);
			
				if ($posthits){
								?>
				<table class='widefat' cellspacing='0'><thead>
	<tr>
		<th>Date</th>
		<th>Search Query</th>
		<th>SERP Page</th>
		<th>Total visits</th>
		<th>Query Ref</th>
		<th>Landingpage</th>
	</tr>
</thead>
				<?php
					//echo "<table  class='widefat post' cellspacing='3' cellpadding='3'><tr><td><h4>Date</h4></td><td><h4>Search Query</h4></td><td><h4>SERP Page</h4></td><td><h4>Total visits</h4></td><td><h4>Query Ref</h4></td><td><h4>Landingpage</h4></td></tr>";
					
					foreach ($posthits as $hits) {
						$strippedurl= str_replace($blogurl,'',get_permalink($hits['PostId']));
						echo "<tr><td><small>".$hits['SeDate']."</small></td><td>".$hits['SeQuery']."</td><td><center>".$hits['SePage']."</center></td><td><center>".$hits['SeHits']."</center></td><td><small>".$hits['SeRef']."</small></td><td><small>".str_replace($blogurl,'',get_permalink($hits['PostId']))."</small></td></tr>";
						}
					
					echo "</table>";
					}
					?>

</div>
			
			
			
			
			
			
			
			
<div id="database">

				<h3><?php _e('Database Operations', 'seoboosterpro'); ?></h3>
			
				<h4>Cleaning Database</h4>
				<p><?php _e('This operation empties all older queries which have been stored, but which are irrelevant. Earlier versions of SEO Booster Lite/PRO stored among others "site:"-, "related:"- and "cache:"-queries, which are irrelevant for on-site SEO optimization.', 'seoboosterpro'); ?></p>
					
					<p><?php _e('<em>Click this button if you want to clear these queries.</em>'); ?></p>
					<form name="form4" method="post" action="<?php echo $location; ?>">
					
						<?php $nonce= wp_create_nonce('seoboosterpro');?>
						<p class="submit">
						
							<input type="hidden" name="_seoboosterprononce" value="<?php echo $nonce; ?>" />
							<input type="hidden" name="action" value="clean" />
							<input class="button-primary" type="submit" name="seoboosterpro_clean" value="<?php _e('Clean Database', 'seoboosterpro'); ?>" />
						</p>
					</form>
					
					<h4>EMPTY DATABASE</h4>
					<p><?php _e('Clicking on this button clears ALL logged Search Queries to this blog. <strong>Attention: </strong>You <strong>cannot</strong> undo clicking this button.', 'seoboosterpro'); ?></p>
					
					<p><?php _e('<em>Only click this if you are absolutely certain!</em>'); ?></p>
					<form name="form2" method="post" action="<?php echo $location; ?>">
					
						<?php $nonce= wp_create_nonce('seoboosterpro');?>
						<p class="submit">
						
							<input type="hidden" name="_seoboosterprononce" value="<?php echo $nonce; ?>" />
							<input type="hidden" name="action" value="clear" />
							<input class="button-primary" type="submit" name="seoboosterpro_clear" value="<?php _e('Empty Database', 'seoboosterpro'); ?>" />
						</p>
					</form>
				


</div>


<div id="help">
				<h3><?php _e('SEO Booster Lite HELP', 'seobooster') ?></h3>
				
				<h4>Introduction</h4>
				<p>SEO Booster Lite detects visitors coming from Search Engines, and creates links on your blog to the blogposts/pages using these keywords.</p>
				
				<h4>FAQ</h4>
				<h5>Nothing is happening?</h5>
				<p>The time it takes for SBP to "kick in" is different from blog to blog, but as soon as you have activated the plugin, it starts working in the background. Remember to use the custom PHP code in your WordPress Theme, or use the Widget supplied with the plugin.</p>
					
				<h5>How Does SEO Booster Work?</h5>
				<p>SEO Booster is in itself a simple plugin, but the SEO strategy behind it is very powerful and is used by top SEO around the Internet. If you wish to learn more you should read the following blogpost:</p>
				<p>Read the full article here: <a href="http://mywordpress.com/how-does-seo-booster-work/" target="_blank">How Does SEO Booster Work?</a></p>		
</div>


<div id="credits">
    

				<h3><?php _e('SEO Booster Lite Credits', 'seobooster') ?></h3>
				
				<p>Thank you to all testers and users and the feedback that I have received!</p>
				<p>A special thank you must go to Tony Lindskog and Milan Petrovic for their input and assistance.</p>
					<?php
					$baseurl=WP_CONTENT_URL . '/plugins/' . plugin_basename(dirname(__FILE__)). '/mywordpresssm.jpg';	
					?>


				
					<p><p><?php _e('If you like this plugin, please give it a <a href="http://wordpress.org/extend/plugins/seo-booster-lite/" target="_blank">good rating</a>.', 'seobooster'); ?></p>
					
								<p>Get <strong>full control</strong>, <a href="http://cleverplugins.com/wordpress-plugins/seo-booster-pro" target="_blank">see the cool features and <strong>buy</strong> SEO Booster PRO</a> now!</p>
					<p>Debug Info:</p>
<p>
					<?php echo get_num_queries(); ?> queries. <?php timer_stop(1); ?> seconds.
	</p>
		
</div>
   
    
    
    	<script type="text/javascript">
			jQuery(function(){
	
				// Tabs
				jQuery('#tabs').tabs();
				
				//hover states on the static widgets
				jQuery('#dialog_link, ul#icons li').hover(
					function() { $(this).addClass('ui-state-hover'); }, 
					function() { $(this).removeClass('ui-state-hover'); }
				);
				
			});
		</script>	
		
		

</div> 

<?php
 
}


// ### Adding the admin menu to the WP-Admin interface
function seobooster_admin_menu() {
  if (function_exists('add_submenu_page')) {
    add_options_page('SEO Booster Lite', 'SEO Booster Lite', 8, basename(__FILE__), 'seobooster_admin_options');


  }
}


$backlink=get_option('seobooster_backurl',false);
$backanchor=get_option('seobooster_backlink',false);




if (($backanchor<>'') && ($backlink=='')) { // Must be an old version...
	update_option('seobooster_backurl','http://mywordpress.com');	
	$backlink=get_option('seobooster_backurl',false);
}


if (($backanchor=='') && ($backlink=='')) { // Must be a new install
	$rand=rand(1,6);
	
	switch ($rand) {
    case 1:
        update_option('seobooster_backurl','http://mywordpress.com');	
        update_option('seobooster_backlink','SEO for WordPress');
        break;
    case 2:
        update_option('seobooster_backurl','http://mywordpress.com/category/interviews/');	
        update_option('seobooster_backlink','WordPress Interviews');
        break;
    case 3:
        update_option('seobooster_backurl','http://mywordpress.com/category/seo-tools/');
        update_option('seobooster_backlink','SEO Tools');
        break;
    case 4:
        update_option('seobooster_backurl','http://mywordpress.com/category/wordpress-development-tips/');
        update_option('seobooster_backlink','WordPress Authoring Tips');
        break;   
    case 5:
        update_option('seobooster_backurl','http://cleverplugins.com');	
        update_option('seobooster_backlink','Clever WordPress Plugins');
        break;          
    case 6:
        update_option('seobooster_backurl','http://cleverplugins.com/products');	
        update_option('seobooster_backlink','Plugins for WordPress');
        break;  
	}

	$backlink=get_option('seobooster_backurl',false);
	$backanchor=get_option('seobooster_backlink',false);

}

function init_function() {
    $plugpage = strtolower($_GET['page']);    
    if ($plugpage=='seo-booster.php') { 
		wp_enqueue_script( 'jquery' );
    	wp_enqueue_script( 'jquery-ui-core' );
    	wp_enqueue_script( 'jquery-ui-tabs' );
    }
}
      
        
function admin_register_head() {
    $plugpage = strtolower($_GET['page']);    
    if ($plugpage=='seo-booster.php') {
    	wp_admin_css();
    	$siteurl = get_option('siteurl');
    	$url = $siteurl . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/seo-booster.css';
    	echo "<link rel='stylesheet' type='text/css' href='$url' />\n";
    }
}

add_action('init', 'init_function');  
add_action('admin_head', 'admin_register_head');
add_action('admin_menu', 'seobooster_admin_menu');
add_action('wp_dashboard_setup', 'seobooster_add_dashboard_widgets' );
add_action('widgets_init', 'widget_init_seobooster');
register_activation_hook(__FILE__,'seobooster_install');
add_filter('wp_head', 'seobooster_checkreferrer');

?>
