<?php
/**
 * PatreonClient.php
 *
 * Based on OAuth2Client, but using specific Patreon classes for better Patreon integration
 *
 * Installation Instructions:
 *
 *      Unpack the zip file into the MediaWiki extensions directory.  This will create a PatreonClient subdirectory.
 *
 *      Run the Media Wiki web updater.  If your wiki's main page is at http://example.com/wiki/index.php/Main_Page
 *      then the web updater is at http://example.com/wiki/mw-config
 *
 *      Add the following to LocalSettings.php:
 
wfLoadExtension( 'PatreonClient' );
 
$wgPatreonClient['client']['id']     = 'replace with your Patreon client id';
$wgPatreonClient['client']['secret'] = 'replace with your Patreon client secret';
$wgPatreonClient['configuration']['login_link_text'] = "Optional, if you want the link text to say something different from the default";

 
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}
class PatreonClientHooks {
	public static function onPersonalUrls( array &$personal_urls, Title $title ) {

		global $wgPatreonClient, $wgUser, $wgRequest;
		if( $wgUser->isLoggedIn() ) return true;


		# Due to bug 32276, if a user does not have read permissions,
		# $this->getTitle() will just give Special:Badtitle, which is
		# not especially useful as a returnto parameter. Use the title
		# from the request instead, if there was one.
		# see SkinTemplate->buildPersonalUrls()
		$page = Title::newFromURL( $wgRequest->getVal( 'title', '' ) );

		if( isset( $wgPatreonClient['configuration']['login_link_text'] ) && 0 < strlen( $wgPatreonClient['configuration']['login_link_text'] ) ) {
			$service_login_link_text = $wgPatreonClient['configuration']['login_link_text'];
		} else {
			$service_login_link_text = wfMessage('patreonclient-link-text')->text();
		}

		$inExt = ( null == $page || ('PatreonClient' == substr( $page->getText(), 0, 12) ) || strstr($page->getText(), 'Logout') );
		$personal_urls['anon_oauth_login'] = array(
			'text' => $service_login_link_text,
			//'class' => ,
			'active' => false,
		);
		if( $inExt ) {
			$personal_urls['anon_oauth_login']['href'] = Skin::makeSpecialUrlSubpage( 'PatreonClient', 'redirect' );
		} else {
			# Due to bug 32276, if a user does not have read permissions,
			# $this->getTitle() will just give Special:Badtitle, which is
			# not especially useful as a returnto parameter. Use the title
			# from the request instead, if there was one.
			# see SkinTemplate->buildPersonalUrls()
			$personal_urls['anon_oauth_login']['href'] = Skin::makeSpecialUrlSubpage(
				'PatreonClient',
				'redirect',
				wfArrayToCGI( array( 'returnto' => $page ) )
			);
		}

		if( isset( $personal_urls['anonlogin'] ) ) {
			if( $inExt ) {
				$personal_urls['anonlogin']['href'] = Skin::makeSpecialUrl( 'Userlogin' );
			}
		}
		return true;
	}
    
    public static function onLoadExtensionSchemaUpdates( $updater ) {
		$sql = __DIR__ . '/sql';
		$schema = "$sql/patreon_user.sql";
		$updater->addExtensionUpdate( [ 'addTable', 'patreon_user', $schema, true ] );
		return true;
	}    

}
