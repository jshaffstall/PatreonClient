<?php
/**
 * SpecialPatreonClient.php
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}

class SpecialPatreonClient extends SpecialPage {

	private $_provider;

	/**
	 * Required settings in global $wgPatreonClient
	 *
	 * $wgPatreonClient['client']['id']
	 * $wgPatreonClient['client']['secret']
     *
	 * $wgPatreonClient['configuration']['redirect_uri']
	 */
	public function __construct() {

		parent::__construct('PatreonClient');
	}

	// default method being called by a specialpage
	public function execute( $parameter ){
		$this->setHeaders();
		switch($parameter){
			case 'redirect':
				$this->_redirect();
			break;
			case 'callback':
				$this->_handleCallback();
			break;
			default:
				$this->_default();
			break;
		}

	}

	private function _redirect() {

		global $wgRequest, $wgOut;
        global $wgPatreonClient;
        
		$wgRequest->getSession()->persist();
		$wgRequest->getSession()->set('returnto', $wgRequest->getVal( 'returnto' ));

        // Build the authorization URL for Patreon
        $authorizationUrl = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id='.$wgPatreonClient['client']['id'];
        //$authorizationUrl .= '&redirect_uri='.$wgPatreonClient['configuration']['redirect_uri'];
        $authorizationUrl .= '&redirect_uri='.SpecialPage::getTitleFor('PatreonClient')->getCanonicalURL().'/callback';
        
		// Redirect the user to the authorization URL.
		$wgOut->redirect( $authorizationUrl );
	}

	private function _handleCallback(){
        global $wgPatreonClient;
        
        require_once('Patreon/API.php');
        require_once('Patreon/OAuth.php');
        
        $oauth_client = new Patreon\OAuth($wgPatreonClient['client']['id'], $wgPatreonClient['client']['secret']);
        $tokens = $oauth_client->get_tokens($_GET['code'], SpecialPage::getTitleFor('PatreonClient')->getCanonicalURL().'/callback');
        $access_token = $tokens['access_token'];
        $refresh_token = $tokens['refresh_token'];
        
        $api_client = new Patreon\API($access_token);
        $patron_response = $api_client->fetch_user();
        $patron = $patron_response['data'];
        
		$user = $this->_userHandling( $patron );

		global $wgOut, $wgRequest;
		$title = null;
		if( $wgRequest->getSession()->exists('returnto') ) {
			$title = Title::newFromText( $wgRequest->getSession()->get('returnto') );
			$wgRequest->getSession()->remove('returnto');
			$wgRequest->getSession()->save();
		}

		if( !$title instanceof Title || 0 > $title->mArticleID ) {
			$title = Title::newMainPage();
		}
		$wgOut->redirect( $title->getFullURL() );
		return true;
	}
    
	protected function _userHandling( $patron ) {
		global $wgAuth, $wgRequest;
        
        $parts = explode("@", $patron['attributes']['email']);
        $real_name = $parts[0];
        
        $email = $patron['attributes']['email'];
        
        $db = wfGetDB (DB_MASTER);
        
        $puser = $db->select('patreon_user', ['user_id'], [ 'user_patreonid' => $patron['id']], __METHOD__);
        
        if ($puser === FALSE)
        {
            throw new MWException('Error retrieving Wiki user id via Patreon id');
			die();
        }
        
        if ($puser->numRows () == 0)
        {
            // This is the first time this Patreon user has logged onto the wiki
            // Create user by email prefix
            
            // If that user already exists in the wiki, that means we can't use that
            // email prefix for another.  Go into a while loop to generate sequential
            // numbered versions of the email prefix until we find one that doesn't 
            // already exist.
            
            // Then save the user to the database and create the patreon_user record
            // mapping their wiki user id to their patreon id
            $username = $real_name;
            $sequence = 0;
            
            while (TRUE)
            {
                $user = User::newFromName($username, 'creatable');
                if (!$user) {
                    throw new MWException('Could not create user with name:' . $username);
                    die();
                }

                if ($user->getId() == 0)
                {
                    $user->setRealName($username);
                    $user->setEmail($email);
                    $user->addToDatabase();
                    $user->confirmEmail();
                    $user->setToken();
                    
                    // Create patreon_user record
                    $db->insert('patreon_user', ['user_patreonid'=>$patron['id'], 'user_id'=>$user->getId()]);
                    break;
                }
                
                $sequence += 1;
                $username = "$real_name$sequence";
            }
        }
        else
        {
            // We found a match for the Patreon user on the wikie
            // create the user by wiki user id
            $puser = $puser->next ();
            $user = User::newFromId($puser->user_id);
            
            if (!$user) {
                throw new MWException('Could not load user with wiki id:' . $puser->user_id);
                die();
            }
            
            $user->setRealName($real_name);
            $user->setEmail($email);
            $user->load();
            $user->setToken();
        }
        
		// Setup the session
		$wgRequest->getSession()->persist();
		$user->setCookies();
		$this->getContext()->setUser( $user );
		$user->saveSettings();
		global $wgUser;
		$wgUser = $user;
		$sessionUser = User::newFromSession($this->getRequest());
		$sessionUser->load();
		return $user;
	}

	private function _default(){
		global $wgPatreonClient, $wgOut, $wgUser, $wgScriptPath, $wgExtensionAssetsPath;

   		if( isset( $wgPatreonClient['configuration']['login_link_text'] ) && 0 < strlen( $wgPatreonClient['configuration']['login_link_text'] ) ) {
			$service_login_link_text = $wgPatreonClient['configuration']['login_link_text'];
		} else {
			$service_login_link_text = wfMessage('patreonclient-link-text')->text();
        }
        
        $url = $this->getTitle( 'redirect' )->getCanonicalURL();
        
		$wgOut->setPagetitle( wfMessage( 'patreonclient-login-header')->text() );
		if ( !$wgUser->isLoggedIn() ) {
			$wgOut->addWikiMsg( 'patreonclient-you-can-login-to-this-wiki-using-patreon');
			$wgOut->addHTML( '<a href="'.$url.'">'.$service_login_link_text.'</a>');

		} else {
			$wgOut->addWikiMsg( 'patreonclient-youre-already-loggedin' );
		}
		return true;
	}
}
