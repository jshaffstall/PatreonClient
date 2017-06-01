## Synopsis

A MediaWiki extension that allows users to log in via their Patreon credentials.  Useful for wiki sites run by Patreon creators.

Requires MediaWiki 1.25+.

## Acknowledgements

This extension is based on the OAuth2 extension at https://github.com/Schine/MW-OAuth2Client, but focused on Patreon.  It also uses the Patreon PHP classes.

This extension was originally created for [Zombie Orpheus Entertainment](http://zombieorpheus.com/) who has generously allowed it to be placed on github for others to use.  

## Limitations

This extension is not yet designed to function as a general MediaWiki extension in any environment.  It works specifically on non-WMF sites using MySQL.  I welcome pull requests from others more knowledgeable than me to improve its use in different environments.

Use with caution!  Try it on a test site that uses the same environment as your live site before going live with it.

## Installation

Clone this repo into the extension directory. 

## Configuration

*Patreon Client*

Create a Patreon client at https://www.patreon.com/platform/documentation/clients

For the Redirect URI, use the Special:PatreonClient/callback page of your wiki.  So if your wiki main page is at  http://example.com/wiki/index.php/Main_Page then your redirect URI would be http://example.com/wiki/index.php/Special:PatreonClient/callback

*LocalSettings.php*

Add to the bottom of your LocalSettings.php file:

```PHP
wfLoadExtension( 'PatreonClient' );
 
$wgPatreonClient['client']['id']     = 'replace with your Patreon client id';
$wgPatreonClient['client']['secret'] = 'replace with your Patreon client secret';
```

By default this will add a Login with Patreon link to the wiki.  If you would like to change the text used in that link, you may add the following to LocalSettings.php:

```PHP
$wgPatreonClient['configuration']['login_link_text'] = "Your desired link text";
```

*MediaWiki web updater*

Run the Media Wiki web updater.  If your wiki's main page is at http://example.com/wiki/index.php/Main_Page then the web updater is at http://example.com/wiki/mw-config

The web updater will ask you for an upgrade key from your LocalSettings.php file.  Open that file and find the variable named $wgUpgradeKey.  Copy the contents of that string (not including the quotes) into the web updater's field that asks for the upgrade key.

At some point during the upgrade process it should give you a list of tasks it has done.  One of those should be the create of the patreon_user table.  

*Login with Patreon*

You should now see a Login with Patreon link along the top right of your wiki.  Logging in will create a MediaWiki user tied to that Patreon account.

## License

LGPL (GNU Lesser General Public License) http://www.gnu.org/licenses/lgpl.html