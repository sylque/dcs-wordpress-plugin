<?php
/**
 * Plugin Name: Docuss
 */

//------------------------------------------------------------------------------
//	Docuss includes
//------------------------------------------------------------------------------

define('DOCUSS_CSS', 'https://sylque.github.io/dcs-client/css/dcs-decorator.css');
define('DOCUSS_JS', 'https://sylque.github.io/dcs-client/dist/dcs-decorator.js');
//define('DOCUSS_CSS', 'http://localhost:8080/css/dcs-decorator.css');
//define('DOCUSS_JS', 'http://localhost:8080/dist/dcs-decorator.js');

// Add Docuss markup to every page
add_action('wp_head', function () {
	?>
	<link rel="stylesheet" type="text/css" href="<?php echo DOCUSS_CSS?>"/>
	<script src="<?php echo DOCUSS_JS?>" async></script>
  <script>
		console.log('dcs-wordpress-plugin json file url: <?php 
			echo plugins_url('dcs-website.php', __FILE__) 
		?>')
  </script>
	<?php
});


//------------------------------------------------------------------------------
// Json file creation
//------------------------------------------------------------------------------

function srcPath() {
	return plugin_dir_path(__FILE__) . 'dcs-website.json';
}

function destPath() {
	return plugin_dir_path(__FILE__) . 'do-not-delete.json';
}

// Create the do-not-delete.json file
// https://stackoverflow.com/questions/6505700/writing-to-a-file-from-my-wordpress-plugin
// https://codex.wordpress.org/Determining_Plugin_and_Content_Directories
// https://developer.wordpress.org/reference/functions/get_posts/
// https://www.taniarascia.com/how-to-use-json-data-with-php-or-javascript/
function create_website_json_file($postPrefix, $pagePrefix) {
	// Default args values
	$postPrefix = $postPrefix ?: get_option('postPrefix');
	$pagePrefix = $pagePrefix ?: get_option('pagePrefix');

	// Load the template json file
	$srcPath = srcPath();
	$srcText = file_get_contents($srcPath);
	if ($srcText == FALSE) {
		quit('File ' . $srcPath . ' not found');
	}
	$data = json_decode($srcText);
	if ($data == NULL) {
		quit('Invalid json in file ' . $srcPath . ': error ' . json_last_error() . '.');
		return;
	}

	// Check prefixes
	if ($data->dcsTag->forceLowercase && isPartUppercase($postPrefix)) {
		quit('Prefix "' . $postPrefix. '" is incompatible with dcsTag->forceLowercase=true. ' . 
		'Either change the prefix in Docuss > Settings, or change dcsTag->forceLowercase in dcs-website.json');	
	}
	if ($data->dcsTag->forceLowercase && isPartUppercase($pagePrefix)) {
		quit('Prefix "' . $pagePrefix. '" is incompatible with dcsTag->forceLowercase=true. ' . 
		'Either change the prefix in Docuss > Settings, or change dcsTag->forceLowercase in dcs-website.json');	
	}

	// If the page list is empty, add the home page
	if (empty($data->pages)) {
		$name = substr('wp', 0, $data->dcsTag->maxPageNameLength);
		$data->pages = [(object)['name' => $name, 'url' => get_home_url()]];
	}	

	// Check page names and urls
	foreach($data->pages as $page) {
		if ($data->dcsTag->maxPageNameLength)
		if (startsWith($page->name, $postPrefix) || startsWith($page->name, $pagePrefix)) {
			quit('Invalid page name "' . $page->name. '" in file ' . $srcPath . 
				': a user-defined page name shall not start with a prefix defined in Settings > Docuss.');	
		}
		$l = strtolower($page->url);
		if (!startsWith($l, 'http://') && !startsWith($l, 'https://')) {
			quit('Invalid page url "' . $page->url. '" in file ' . $srcPath . 
				': only absolute urls are supported by the Docuss Wordpress plugin.');	
		}

		// Check for trailing / presence, except on the  pure hostname url
		// See https://websiteseochecker.com/blog/trailing-slash-seo-why-it-matters/
		// "Explaining that a slash after a hostname or domain is irrelevant using 
		// it or not is up to you when referring to any URL but when used other than 
		// as mentioned above it becomes a significant part of any URL and can 
		// change the URL if it's not present where it should be."
		$c = canonical_url($page->url);
		if ($page->url !== get_home_url() && !endsWith($c, '/') && !endsWith($c, '.php')) {
			quit('Invalid page url "' . $page->url. '" in file ' . $srcPath . 
				': a Wordpress url pathname always ends with "/" or ".php".');	
		}
	}		

	// Get the list of all posts (of any type: posts, pages, comments, etc.) and
	// build a list of posts and pages with Docuss names and urls
	$postsAndPages = get_posts(array(
		'post_type' => 'any', 
		'post_status' => 'publish', 
		'orderby' => 'ID', 
		'order' => 'ASC'
	));
	$dcsPagesToAdd = [];
	foreach($postsAndPages as $item) {
		$name = '';
		if ($item->post_type === 'post') {
			$name = $postPrefix . $item->ID;
		} else if ($item->post_type === 'page') {
			$name = $pagePrefix . $item->ID;
		} else {
			continue;
		}
		if (strlen($name) > $data->dcsTag->maxPageNameLength) {
			quit('Generated page name "' . $name.	'" is too long. Possible solutions:<br />'.
			'1. Set a shorter prefix in Settings > Docuss<br />' .
			'2. Increase dcsTag.maxPageNameLength in dcs-website.json');	
		}
		$url = canonical_url(get_permalink($item->ID));
		array_push($dcsPagesToAdd, (object)['name' => $name, 'url' => $url]);
	}

	// Add the pages to the json object if not already present
	$alreadyInJson = array_map(function ($e) { 
		return canonical_url($e->url); 
	}, $data->pages);
	foreach ($dcsPagesToAdd as $p) {
		if (!in_array($p->url, $alreadyInJson)) {
			array_push($data->pages, (object)['name' => $p->name, 'url' => $p->url]);
		}
	}

	// Write the json file
	$text = json_encode($data, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
	if (!file_put_contents(destPath(), $text)) {
		quit('Cannot create a file in folder ' . plugin_dir_path(__FILE__) . 
			'.<br />Possible reason: Wordpress doesn\'t have the "write" permission on this folder.' . 
			'<br>Hint: check that the folder has the same owner and group than other Wordpress files and folders.');
	}
} 


//------------------------------------------------------------------------------
// Plugin activation/deactivation hooks
//------------------------------------------------------------------------------

// When plugin is installed, create the do-not-delete.json file
register_activation_hook(__FILE__, 'create_website_json_file');

// When plugin is uninstalled, delete the do-not-delete.json file
register_deactivation_hook(__FILE__, function () {
	unlink(destPath());
});


//------------------------------------------------------------------------------
// Post publication hook
//------------------------------------------------------------------------------

// When a post is published or unpublished, create the do-not-delete.json file
// https://codex.wordpress.org/Post_Status_Transitions
add_action('transition_post_status', function ($new_status, $old_status, $post) {
	if (($old_status == 'publish' && $new_status != 'publish') 
			|| ($old_status != 'publish' && $new_status == 'publish'))
		create_website_json_file();
}, 10, 3);


//------------------------------------------------------------------------------
// Docuss settings admin page
//------------------------------------------------------------------------------

define('PAGE_SLUG', 'docuss-slug');
define('MENU_NAME', 'Docuss');
define('QUERY_PARAM', 'docuss');
define('SETTINGS_PAGE_TITLE', 'Docuss Settings');
define('SECTION_TITLE', 'Prefixes for generated page names');
define('SECTION_SUBTITLE', 'If the prefix is "o_", generated page names will be o_0, o_1, o_2, etc.</p>');
define('PREFIX_REGEX', '/^[A-Za-z0-9_]+$/');

// https://premium.wpmudev.org/blog/creating-wordpress-admin-pages/
// https://codex.wordpress.org/Creating_Options_Pages
add_action('admin_menu', function () {
	add_options_page(SETTINGS_PAGE_TITLE, MENU_NAME, 'manage_options', QUERY_PARAM, function () {
		?>
		<div class="wrap">
			<h1><?php echo SETTINGS_PAGE_TITLE ?></h1>
			<form method="post" action="options.php"> 
				<?php 
					settings_fields(PAGE_SLUG);
					do_settings_sections(PAGE_SLUG);
					submit_button(); 
				?>
			</form>
		</div>				
		<?php
	});
});

function checkPrefix($prefix) {
	if (!preg_match_all(PREFIX_REGEX, $prefix)) {
		quit('Invalid prefix "' . $prefix . 
			'". The prefix should have at least one character and be composed of ' .
			'a-z, 0-9, _ (plus A-Z if you set forceLowercase=false in dcs-website.json)');
	}
}

add_action('admin_init', function () {
	$sectionName = 'prefixes';
	add_settings_section($sectionName, SECTION_TITLE, function () { echo SECTION_SUBTITLE; }, PAGE_SLUG);
	addInputSettingField(PAGE_SLUG, $sectionName, 'postPrefix', 'Prefix for "Post" pages', 'o_');
	addInputSettingField(PAGE_SLUG, $sectionName, 'pagePrefix', 'Prefix for "Page" pages', 'a_');
	add_filter('pre_update_option_postPrefix', function ($new_value, $old_value) {
		if ($new_value != $old_value) {
			checkPrefix($new_value);
			create_website_json_file($new_value, get_option('pagePrefix'));
		}
		return $new_value;
	}, 10, 2);
	add_filter('pre_update_option_pagePrefix', function ($new_value, $old_value) {
		if ($new_value != $old_value) {
			checkPrefix($new_value);
			create_website_json_file(get_option('postPrefix'), $new_value);
		}
		return $new_value;
	}, 10, 2);
});

function addInputSettingField($pageSlug, $sectionName, $name, $title, $default = '') {
	register_setting($pageSlug, $name, ['type' => 'string', 'default' => $default]);
	add_settings_field($name, $title, function () use ($name) { 
		$setting = get_option($name);
		?>
		<input type="text" name="<?php echo $name ?>" value="<?php echo isset($setting) ? esc_attr($setting) : ''; ?>">
		<?php			
	}, $pageSlug, $sectionName);
}

//------------------------------------------------------------------------------
// Utilities
//------------------------------------------------------------------------------

function contains($needle, $haystack) {
    return strpos($haystack, $needle) !== false;
}

function startsWith($haystack, $needle) {
	$length = strlen($needle);
	return substr($haystack, 0, $length) === $needle;
}

function endsWith($haystack, $needle) {
	$length = strlen($needle);
	if ($length == 0) {
			return true;
	}
	return (substr($haystack, -$length) === $needle);
}

function l($var) {
	wp_die('<p><b>Log</b></p><p>' . var_export($var, true) . '</p>');
	return;
}

function canonical_url($url) {
	$p = parse_url($url);
	if (!$p) {
		quit('Ill-formed url "' . $url . '"');
	}
	return strtolower($p['scheme']) . '://' . strtolower($p['host']) . $p['path'];
}

function quit($msg) {
	error_log('Docuss Plugin Error - ' . $msg);
	wp_die('<p><b>Docuss Plugin Error</b></p><p>' . $msg . '</p>');
}

// https://beneverard.co.uk/blog/php-check-if-any-part-of-a-string-is-uppercase/
function isPartUppercase($string) {
	return (bool) preg_match('/[A-Z]/', $string);
}

//-------------------------------------------------------------------------
