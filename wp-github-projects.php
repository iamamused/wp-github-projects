<?php
/*
Plugin Name: Github Projects
Plugin URI: http://github.com/iamamused/wp-github-projects
Description: Lists github projects.
Version: 1.0
Author: Jeffrey Sambells
Author URI: http://jeffreysambells.com
License: GPL2

	Copyright 2010  Jeffrey Sambells  (email : github@tropicalpixels.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Requires at least: 3.0
*/


function github_projects_init() {
	wp_enqueue_script('jquery');
}
add_action('init', 'github_projects_init');


function github_projects_the_content( $content ) {
	
	$content = str_replace( '[github-projects]', github_projects_list_projects_markup(), $content );
	return $content;
}
add_filter( 'the_content', 'github_projects_the_content' );



//////////////////////
// Admin settings
//////////////////////


// create custom plugin settings menu
add_action('admin_menu', 'github_projects_create_menu');

function github_projects_create_menu() {

	//create new top-level menu
	add_menu_page('Git Projects Settings', 'Git Projects Settings', 'administrator', __FILE__, 'github_projects_settings_page',plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action( 'admin_init', 'github_projects_register_mysettings' );
}


function github_projects_register_mysettings() {
	register_setting( 'github-projects-settings-group', 'USERNAME' );
	register_setting( 'github-projects-settings-group', 'CACHE_DURATION' );
}

function github_projects_settings_page() {
?>
<div class="wrap">
<h2>Github Projects</h2>

<form method="post" action="options.php">
    <?php settings_fields( 'github-projects-settings-group' ); ?>

    <table class="form-table">
        <tr valign="top">
        <th scope="row">Github Username</th>
        <td><input type="text" name="USERNAME" value="<?php echo get_option('USERNAME'); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Cache Duration</th>
        <td><input type="text" name="CACHE_DURATION" value="<?php echo get_option('CACHE_DURATION'); ?>" /></td>
        </tr>
        
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<?php 
} 

/**
 * Returns an array of repositories for the specified username.
 */
function github_projects_get_repo_xml()
{

	$duration = get_option('CACHE_DURATION');
	$username = get_option('USERNAME');
	
	$cache = dirname(__FILE__) . '/cache/projects-' . md5( $username ) . '.cache';

	if (file_exists($cache) && filemtime($cache) + $duration > time()) {
		return file_get_contents($cache);
	}


	$xmlString = @file_get_contents( 'http://github.com/api/v2/xml/repos/show/' . $username );
	
	/*
	<repositories type='array'>
	<repository>
	<has-issues type='boolean'>true</has-issues>
	<pushed-at type='datetime'>2010-07-13T10:58:51-07:00</pushed-at>
	<watchers type='integer'>5</watchers>
	<created-at type='datetime'>2010-04-15T20:02:39-07:00</created-at>
	<forks type='integer'>0</forks>
	<has-downloads type='boolean'>true</has-downloads>
	<description>XCode Template for iPhone OS Static Libraries</description>
	<fork type='boolean'>false</fork>
	<private type='boolean'>false</private>
	<name>iPhone-OS-Static-Library-Template</name>
	<url>http://github.com/iamamused/iPhone-OS-Static-Library-Template</url>
	<owner>iamamused</owner>
	<has-wiki type='boolean'>true</has-wiki>
	<homepage>http://jeffreysambells.com/iphone-os-static-library-template/</homepage>
	<open-issues type='integer'>1</open-issues>
	</repository>
	*/

	file_put_contents($cache, $xmlString);

	return $xmlString;
}

function github_projects_get_repo_readme_xml( $repo )
{

	$duration = get_option('CACHE_DURATION');
	$username = get_option('USERNAME');
	
	$cache = dirname(__FILE__) . '/cache/readme-' . md5( $username .  $repo ) . '.cache';

	if (file_exists($cache) && filemtime($cache) + $duration > time()) {
		return file_get_contents($cache);
	}

	// Get the SHA of the master
	$xmlString = @file_get_contents( 'http://github.com/api/v2/xml/repos/show/' . $username . '/' . $repo . '/branches' );

	$xml = new SimpleXMLElement( $xmlString );
	$xmlString = @file_get_contents( 'http://github.com/api/v2/xml/blob/show/' . $username . '/' . $repo . '/' . $xml->master . '/BRIEF.md' );
	
	// @TODO check for error->message

	/*
	<blob>
	<name>README</name>
	<data>Please see http://jeffreysambells.com/iphone-os-static-library-template/</data>
	<size type='integer'>72</size>
	<sha>616f6a6dfbc3eceed6c20a814a97f4871c492f03</sha>
	<mode>100644</mode>
	<mime-type>text/plain</mime-type>
	</blob>
	*/

	file_put_contents($cache, $xmlString);

	return $xmlString;
}

/**
 * Automatically creates a sidebar block of projects for the specified username.
 *
 * @param string  $username Username to retrieve repositories for.
 * @param integer $duration Duration to cache the result, in seconds. Defaults to one hour.
 * @see   github_repositories_for
 */
function github_projects_list_projects_markup()
{
	$xmlString = github_projects_get_repo_xml();
	$xml = new SimpleXMLElement( $xmlString );
	
	$markup = '';
	$markup .= '<secion class="github projects">';

	foreach ($xml->repository as $repository) {
		if ($repository->fork == 'true') continue;
		
		$markup .= '<h2><a href="' . $repository->url . '">'. str_replace('-',' ',$repository->name) . '</a></h2>';
		$markup .= '<p>Link: <a href="' . $repository->url . '">'. $repository->url . '</a></p>';
		$markup .= '<p>' . $repository->description . '</p>';
		$readme = github_projects_get_repo_readme_xml($repository->name);
		
		if (strlen($readme) > 0) {
			$xml = new SimpleXMLElement( $readme );
			$markup .= "\n" . $xml->data . "\n";
		}
	}

	$markup .= '</section>';
	
	return $markup;
}
