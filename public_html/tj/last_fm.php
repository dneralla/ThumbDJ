<?php

/**
 * LastFM class
 *
 * This source file can be used to communicate with last.fm (http://last.fm)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-lastfm-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * Known issues:
 * - radioGetPlaylist returns an server error
 *
 * Changelog since 1.0.0
 * - added artistGetCorrectName
 *
 * License
 * Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including neglience or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author		Tijs Verkoyen <php-lastfm@verkoyen.eu>
 * @version		1.0.1
 *
 * @copyright	Copyright (c) 2010, Tijs Verkoyen. All rights reserved.
 * @license		BSD License
 */
class LastFm
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the twitter-api
	const API_URL = 'http://ws.audioscrobbler.com/2.0';

	// port for the twitter-api
	const API_PORT = 80;

	// current version
	const VERSION = '1.0.1';


	/**
	 * The API-key
	 *
	 * @var string
	 */
	private $apiKey;


	/**
	 * cURL instance
	 *
	 * @var	resource
	 */
	private $curl;


	/**
	 * The secret
	 *
	 * @var	string
	 */
	private $secret;


	/**
	 * The session key
	 *
	 * @var	string
	 */
	private $sessionKey;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 10;


	/**
	 * The user Agent
	 *
	 * @var	string
	 */
	private $useragent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string[optional] $apiKey
	 * @param	string[optional] $secret
	 */
	public function __construct($apiKey = null, $secret = null)
	{
		if($apiKey !== null) $this->setAPIKey($apiKey);
		if($secret !== null) $this->setSecret($secret);
	}


	/**
	 * Default destructor
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		// close the cURL instance if needed
		if($this->curl !== null) curl_close($this->curl);
	}


	/**
	 * Get a signature
	 *
	 * @return	string
	 * @param	array $parameters
	 */
	private function buildSignature(array $parameters)
	{
		// order
		ksort($parameters);

		// init var
		$signature = '';

		// build string
		foreach($parameters as $key => $value) $signature .= $key . $value;

		// append secret
		$signature .= $this->getSecret();

		// hash and return
		return md5($signature);
	}


	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $method
	 * @param	array[optiona] $parameters
	 * @param	bool[optional] $usePost
	 */
	private function doCall($method, array $parameters = null, $authenticate = false, $httpMethod = 'GET')
	{
		// allowed HTTP-methods
		$allowedHTTPMethods = array('GET', 'POST');

		// redefine
		$method = (string) $method;
		$parameters = (array) $parameters;
		$authenticate = (bool) $authenticate;
		$httpMethod = (string) $httpMethod;

		// validate
		if(!in_array($httpMethod, $allowedHTTPMethods)) throw new LastFmException('Invalid HTTP-method ('. $httpMethod .'), possible values are: '. implode(', ', $allowedHTTPMethods) .'.');

		// add default parameters
		$parameters['method'] = $method;
		$parameters['api_key'] = $this->getAPIKey();

		// should we authenticate
		if($authenticate)
		{
			$parameters['sk'] = $this->getSessionKey();
			$parameters['api_sig'] = $this->buildSignature($parameters);
		}

		// init var
		$url = '';

		// based on the method we should prepare the parameters
		if($httpMethod == 'GET')
		{
			// append format
			$parameters['format'] = 'json';

			// add the parameters into the querystring
			if(!empty($parameters)) $url .= '?'. http_build_query($parameters);
		}

		elseif($httpMethod == 'POST')
		{
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $parameters;
		}

		// set options
		$options[CURLOPT_URL] = self::API_URL .'/'. $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();

		// init
		if($this->curl == null) $this->curl = curl_init();

		// set options
		curl_setopt_array($this->curl, $options);

		// execute
		$response = curl_exec($this->curl);
		$headers = curl_getinfo($this->curl);

		// fetch errors
		$errorNumber = curl_errno($this->curl);
		$errorMessage = curl_error($this->curl);

		// some methods will reply nothing, so return true if this happens
		if($errorMessage == 'Empty reply from server') return true;

		// invalid headers
		if(!in_array($headers['http_code'], array(0, 100, 200)))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';

				// stop the script
				exit;
			}

			// throw error
			throw new LastFmException('Invalid headers ('. $headers['http_code'] .')', (int) $headers['http_code']);
		}

		// error?
		if($errorNumber != '') throw new LastFmException($errorMessage, $errorNumber);

		// we expect JSON, so decode it
		$json = @json_decode($response, true);

		// validate JSON
		if($json === null)
		{
			Spoon::dump('Que?');
		}

		// is this an error?
		if(isset($json['error']) && isset($json['message'])) throw new LastFmException($json['message'], (int) $json['error']);

		// return
		return $json;
	}


	/**
	 * Get the API key
	 *
	 * @return	string
	 */
	public function getAPIKey()
	{
		return $this->apiKey;
	}


	/**
	 * Get the secret
	 *
	 * @return	string
	 */
	public function getSecret()
	{
		return $this->secret;
	}


	/**
	 * Get the session key
	 *
	 * @return	string
	 */
	private function getSessionKey()
	{
		return (string) $this->sessionKey;
	}


	/**
	 * Get the timeout
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP LastFm/'. self::VERSION .' '. $this->useragent;
	}


	/**
	 * Set the API key
	 *
	 * @return	void
	 * @param	string $apiKey
	 */
	public function setAPIKey($apiKey)
	{
		$this->apiKey = (string) $apiKey;
	}


	/**
	 * Set the secret
	 *
	 * @return	void
	 * @param	string $secret
	 */
	public function setSecret($secret)
	{
		$this->secret = (string) $secret;
	}


	/**
	 * Set the session key
	 *
	 * @return	void
	 * @param	string $key
	 */
	public function setSessionKey($key)
	{
		$this->sessionKey = (string) $key;
	}


	/**
	 * Set the timeout
	 *
	 * @return	void
	 * @param	int $seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours
	 *
	 * @return	void
	 * @param	string $userAgent
	 */
	public function setUserAgent($useragent)
	{
		$this->useragent = (string) $useragent;
	}


// Album methods
	/**
	 * Tag an album using a list of user supplied tags.
	 *
	 * @return	bool
	 * @param	string $artist	The artist name in question
	 * @param	string $album	The album name in question
	 * @param	array $tags		An array of user supplied tags to apply to this album. Accepts a maximum of 10 tags.
	 */
	public function albumAddTags($artist, $album, array $tags)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['album'] = (string) $album;
		$parameters['tags'] = implode(',', $tags);

		// make the call
		return $this->doCall('album.addTags', $parameters, true, 'POST');
	}


	/**
	 * Get a list of Buy Links for a particular Album.
	 * It is required that you supply either the artist and album params or the mbid param.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question.
	 * @param	string[optional] $album		The album in question.
	 * @param	string[optional] $mbid		A MusicBrainz id for the album in question.
	 * @param	string[optional] $country	A country name, as defined by the ISO 3166-1 country names standard.
	 */
	public function albumGetBuyLinks($artist = null, $album = null, $mbid = null, $country = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($album !== null) $parameters['album'] = (string) $album;
		if($mbid !== null) $parameters['mbid'] = (string) $mbid;
		if($country !== null) $parameters['country'] = (string) $country;

		// make the call
		return $this->doCall('album.getBuyLinks', $parameters);
	}


	/**
	 * Get the metadata for an album on Last.fm using the album name or a musicbrainz id.
	 * See playlistFetch on how to get the album playlist.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question
	 * @param	string[optional] $album		The album name in question
	 * @param	string[optional] $mbid		The musicbrainz id for the album
	 * @param	string[optional] $username	The username for the context of the request. If supplied, the user's playcount for this album is included in the response.
	 * @param	string[optional] $lang		The language to return the biography in, expressed as an ISO 639 alpha-2 code.
	 */
	public function albumGetInfo($artist = null, $album = null, $mbid = null, $username = null, $lang = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($album !== null) $parameters['album'] = (string) $album;
		if($mbid !== null) $parameters['mbid'] = (string) $mbid;
		if($username !== null) $parameters['username'] = (string) $username;
		if($lang !== null) $parameters['lang'] = (string) $lang;

		// make the call
		return $this->doCall('album.getInfo', $parameters);
	}


	/**
	 * Get the tags applied by an individual user to an album on Last.fm.
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 * @param	string $album	The album name in question
	 */
	public function albumGetTags($artist, $album)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['album'] = (string) $album;

		// make the call
		return $this->doCall('album.getTags', $parameters, true);
	}


	/**
	 * Remove a user's tag from an album.
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 * @param	string $album	The album name in question
	 * @param	string $tag		A single user tag to remove from this album
	 */
	public function albumRemoveTag($artist, $album, $tag)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['album'] = (string) $album;
		$parameters['tag'] = (string) $tag;

		// make the call
		return $this->doCall('album.removeTag', $parameters, true, 'POST');
	}


	/**
	 * Search for an album by name. Returns album matches sorted by relevance.
	 *
	 * @return	array
	 * @param	string $album			The album name in question.
	 * @param	int[optional] $limit	Limit the number of albums returned at one time. Default (maximum) is 30.
	 * @param	int[optional] $page		Scan into the results by specifying a page number. Defaults to first page.
	 */
	public function albumSearch($album, $limit = null, $page = null)
	{
		// build parameters
		$parameters['album'] = (string) $album;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('album.search', $parameters);
	}


	/**
	 * Share an album with one or more Last.fm users or other friends.
	 *
	 * @return	array
	 * @param	string $artist				The artist name in question
	 * @param	string $album				The album name in question
	 * @param	array $recipients			An array of email addresses or Last.fm usernames. Maximum is 10.
	 * @param	bool[optional] $public		Optionally show in the sharing users activity feed.
	 * @param	string[optional] $message	An optional message to send with the recommendation. If not supplied a default message will be used.
	 */
	public function albumShare($artist, $album, array $recipients, $public = false, $message = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['album'] = (string) $album;
		$parameters['recipient'] = implode(',', $recipients);
		if($public) $parameters['public'] = 1;
		if($message !== null) $parameters['message'] = (string) $message;

		// make the call
		return $this->doCall('album.share', $parameters, true, 'POST');
	}


// Artist methods
	/**
	 * Tag an artist with one or more user supplied tags.
	 *
	 * @return	bool
	 * @param	string $artist	The artist name in question.
	 * @param	array $tags		An array of user supplied tags to apply to this artist. Accepts a maximum of 10 tags.
	 */
	public function artistAddTags($artist, array $tags)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['tags'] = implode(',', $tags);

		// make the call
		return $this->doCall('artist.addTags', $parameters, true, 'POST');
	}


	/**
	 * Get the correct name for an artist
	 *
	 * @return	string
	 * @param	string $artist	The artist name in question.
	 */
	public function artistGetCorrectName($artist)
	{
		// get info
		$response = $this->artistGetInfo($artist);

		// init var
		$name = $response['artist']['name'];

		// it seems the artist should redirect
		if(substr_count($response['artist']['url'], 'noredirect') > 0)
		{
			// get the first similar artist
			if(isset($response['artist']['similar']['artist'][0]['name'])) $name = $response['artist']['similar']['artist'][0]['name'];
		}

		// scan the summary
		if(isset($response['artist']['bio']['summary']))
		{
			// is this an incorrect tag?
			if(substr_count($response['artist']['bio']['summary'], 'incorrect tag'))
			{
				// init var
				$match = array();

				// match
				preg_match('|incorrect\stag\s.*<a.*>(.*)</a>|iU', $response['artist']['bio']['summary'], $match);

				// any matches?
				if(isset($match[1])) $name = $match[1];
			}
		}

		return $name;
	}


	/**
	 * Get a list of upcoming events for this artist.
	 * Easily integratable into calendars, using the ical standard.
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 */
	public function artistGetEvents($artist)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;

		// make the call
		return $this->doCall('artist.getEvents', $parameters);
	}


	/**
	 * Get Images for this artist in a variety of sizes.
	 *
	 * @return	array
	 * @param	string $artist				The artist name in question.
	 * @param	int[optional] $limit		How many to return. Defaults and maxes out at 50.
	 * @param	int[optional] $page			Which page of limit amount to display.
	 * @param	string[optional] $order		Sort ordering can be either 'popularity' (default) or 'dateadded'. While ordering by popularity officially selected images by labels and artists will be ordered first.
	 */
	public function artistGetImages($artist, $limit = null, $page = null, $order = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;
		if($order !== null) $parameters['order'] = (string) $order;

		// make the call
		return $this->doCall('artist.getImages', $parameters);
	}


	/**
	 * Get the metadata for an artist on Last.fm. Includes biography.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question
	 * @param	string[optional] $mbid		The musicbrainz id for the artist
	 * @param	string[optional] $username	The username for the context of the request. If supplied, the user's playcount for this artist is included in the response.
	 * @param	string[optional] $lang		The language to return the biography in, expressed as an ISO 639 alpha-2 code.
	 */
	public function artistGetInfo($artist = null, $mbid = null, $username = null, $lang = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($mbid !== null) $parameters['mbid'] = (string) $mbid;
		if($username !== null) $parameters['username'] = (string) $username;
		if($lang !== null) $parameters['lang'] = (string) $lang;

		// make the call
		return $this->doCall('artist.getInfo', $parameters);
	}


	/**
	 * Get a paginated list of all the events this artist has played at in the past.
	 *
	 * @return	array
	 * @param	string $artist			The name of the artist you would like to fetch event listings for.
	 * @param	int[optional] $limit	The maximum number of results to return per page
	 * @param	int[optional] $page		The page of results to return.
	 */
	public function artistGetPastEvents($artist, $limit = null, $page = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('artist.getPastEvents', $parameters);
	}


	/**
	 * Get a podcast of free mp3s based on an artist
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 */
	public function artistGetPodcast($artist)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;

		// make the call
		return $this->doCall('artist.getPodcast', $parameters);
	}


	/**
	 * Get shouts for this artist.
	 *
	 * @return	array
	 * @param	string $artist			The artist name in question.
	 * @param	int[optional] $limit	An integer used to limit the number of shouts returned per page. The default is 50.
	 * @param	int[optional] $page		The page number to fetch.
	 */
	public function artistGetShouts($artist, $limit = null, $page = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('artist.getShouts', $parameters);
	}


	/**
	 * Get all the artists similar to this artist
	 *
	 * @return	array
	 * @param	string $artist			The artist name in question
	 * @param	int[optional] $limit	Limit the number of similar artists returned
	 */
	public function artistGetSimilar($artist, $limit = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		if($limit) $parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('artist.getSimilar', $parameters);
	}


	/**
	 * Get the tags applied by an individual user to an artist on Last.fm.
	 *
	 * @return	array
	 * @param	string $artist
	 */
	public function artistGetTags($artist)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;

		// make the call
		return $this->doCall('artist.getTags', $parameters, true);
	}


	/**
	 * Get the top albums for an artist on Last.fm, ordered by popularity.
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 */
	public function artistGetTopAlbums($artist)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;

		// make the call
		return $this->doCall('artist.getTopAlbums', $parameters);
	}


	/**
	 * Get the top fans for an artist on Last.fm, based on listening data.
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 */
	public function artistGetTopFans($artist)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;

		// make the call
		return $this->doCall('artist.getTopFans', $parameters);
	}


	/**
	 * Get the top tags for an artist on Last.fm, ordered by popularity.
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 */
	public function artistGetTopTags($artist)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;

		// make the call
		return $this->doCall('artist.getTopTags', $parameters);
	}


	/**
	 * Get the top tracks by an artist on Last.fm, ordered by popularity
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 */
	public function artistGetTopTracks($artist)
	{
		// build parameters
		$parameters['artist'] = $artist;

		// make the call
		return $this->doCall('artist.getTopTracks', $parameters);
	}


	/**
	 * Remove a user's tag from an artist.
	 *
	 * @return	bool
	 * @param	string $artist	The artist name in question.
	 * @param	string $tag		A single user tag to remove from this artist.
	 */
	public function artistRemoveTag($artist, $tag)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['tag'] = (string) $tag;

		// make the call
		return $this->doCall('artist.removeTag', $parameters, true, 'POST');
	}


	/**
	 * Search for an artist by name. Returns artist matches sorted by relevance.
	 *
	 * @return	array
	 * @param	string $artist			The artist name in question.
	 * @param	int[optional] $limit	Limit the number of artists returned at one time. Default (maximum) is 30.
	 * @param	int[optional] $page		Scan into the results by specifying a page number. Defaults to first page.
	 */
	public function artistSearch($artist, $limit = null, $page = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('artist.search', $parameters);
	}


	/**
	 * Share an artist with Last.fm users or other friends.
	 *
	 * @return	bool
	 * @param	string $artist				The artist to share.
	 * @param	array $recipients			An array of email addresses or Last.fm usernames. Maximum is 10.
	 * @param	bool[optional] $public		Show in the sharing users activity feed. Defaults to false.
	 * @param	string[optional] $message	An optional message to send with the recommendation. If not supplied a default message will be used.
	 */
	public function artistShare($artist, array $recipients, $public = false, $message = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['recipients'] = implode(',', $recipients);
		if($public) $parameters['public'] = 1;
		if($message !== null) $parameters['message'] = (string) $message;

		// make the call
		return $this->doCall('artist.share', $parameters, true, 'POST');
	}


	/**
	 * Shout in this artist's shoutbox
	 *
	 * @return	bool
	 * @param	string $artist		The name of the artist to shout on.
	 * @param	string $message		The message to post to the shoutbox.
	 */
	public function artistShout($artist, $message)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['message'] = (string) $message;

		// make the call
		return $this->doCall('artist.shout', $parameters, true, 'POST');
	}


// Auth methods
	/**
	 * Create a web service session for a user. Used for authenticating a user when the password can be inputted by the user.
	 * Only suitable for standalone mobile devices. See the authentication how-to for more.
	 *
	 * @return	array
	 * @param	string $username	The last.fm username.
	 * @param	string $password	The last.fm password
	 */
	public function authGetMobileSession($username, $password)
	{
		// build parameters
		$parameters['method'] = 'auth.getMobileSession';
		$parameters['username'] = (string) $username;
		$parameters['authToken'] = md5($username . md5((string) $password));
		$parameters['api_key'] = $this->getAPIKey();
		$parameters['api_sig'] = $this->buildSignature($parameters);

		// make the call
		$response = $this->doCall('auth.getMobileSession', $parameters);

		// store session key
		$this->setSessionKey($response['session']['key']);

		// return
		return $response;
	}


	/**
	 * Fetch a session key for a user.
	 * The third step in the authentication process. See the authentication how-to for more information.
	 *
	 * @return	array
	 * @param	string $token	A 32-character ASCII hexadecimal MD5 hash returned by step 1 of the authentication process (following the granting of permissions to the application by the user).
	 */
	public function authGetSession($token)
	{
		// build parameters
		$parameters['method'] = 'auth.getSession';
		$parameters['token'] = (string) $token;
		$parameters['api_key'] = $this->getAPIKey();
		$parameters['api_sig'] = $this->buildSignature($parameters);

		// make the call
		$response = $this->doCall('auth.getSession', $parameters);

		// store session key
		$this->setSessionKey($response['session']['key']);

		// return
		return $response;
	}


	/**
	 * Fetch an unathorized request token for an API account.
	 * This is step 2 of the authentication process for desktop applications. Web applications do not need to use this service.
	 *
	 * @return	string
	 */
	public function authGetToken()
	{
		// make the call
		return $this->doCall('auth.getToken');
	}


// Event methods
	/**
	 * Set a user's attendance status for an event.
	 *
	 * @return	bool
	 * @param	string $event	The numeric last.fm event id
	 * @param	int $status		The attendance status, possible values are: 0 = Attending, 1 = Maybe attending, 2 = Not attending.
	 */
	public function eventAttend($event, $status)
	{
		// build parameters
		$parameters['event'] = (string) $event;
		$parameters['status'] = (int) $status;

		// make the call
		return $this->doCall('event.attend', $parameters, true, 'POST');
	}


	/**
	 * Get a list of attendees for an event.
	 *
	 * @return	array
	 * @param	string $event	The numeric last.fm event id
	 */
	public function eventGetAttendees($event)
	{
		// build parameters
		$parameters['event'] = (string) $event;

		// make the call
		return $this->doCall('event.getAttendees', $parameters);
	}


	/**
	 * Get the metadata for an event on Last.fm. Includes attendance and lineup information.
	 *
	 * @return	array
	 * @param	string $event	The numeric last.fm event id
	 */
	public function eventGetInfo($event)
	{
		// build parameters
		$parameters['event'] = (string) $event;

		// make the call
		return $this->doCall('event.getInfo', $parameters);
	}


	/**
	 * Get shouts for this event.
	 *
	 * @return	array
	 * @param	string $event	The numeric last.fm event id
	 */
	public function eventGetShouts($event)
	{
		// build parameters
		$parameters['event'] = (string) $event;

		// make the call
		return $this->doCall('event.getShouts', $parameters);
	}


	/**
	 * Share an event with one or more Last.fm users or other friends.
	 *
	 * @return	bool
	 * @param	string $event				An event ID
	 * @param	array $recipients			An array of email addresses or Last.fm usernames. Maximum is 10.
	 * @param	bool[optional] $public		Optionally show the share in the sharing users recent activity. Defaults to false.
	 * @param	string[optional] $message	An optional message to send with the recommendation. If not supplied a default message will be used.
	 */
	public function eventShare($event, array $recipients, $public = false, $message = null)
	{
		// build parameters
		$parameters['event'] = (string) $event;
		$parameters['recipient'] = implode(',', $recipients);
		if($public) $parameters['public'] = 1;
		if($message !== null) $parameters['message'] = (string) $message;

		// make the call
		return $this->doCall('event.share', $parameters, true, 'POST');
	}


	/**
	 * Shout in this event's shoutbox
	 *
	 * @return	bool
	 * @param	string $event		The id of the event to shout on
	 * @param	string $message		The message to post to the shoutbox
	 */
	public function eventShout($event, $message)
	{
		// build parameters
		$parameters['event'] = (string) $event;
		$parameters['message'] = (string) $message;

		// make the call
		return $this->doCall('event.shout', $parameters, true, 'POST');
	}


// Geo methods
	/**
	 * Get all events in a specific location by country or city name.
	 *
	 * @return	array
	 * @param	string[optional] $location	Specifies a location to retrieve events for (service returns nearby events by default)
	 * @param	string[optional] $lat		Specifies a latitude value to retrieve events for (service returns nearby events by default)
	 * @param	string[optional] $long		Specifies a longitude value to retrieve events for (service returns nearby events by default)
	 * @param	string[optional] $distance	Find events within a specified radius (in kilometres)
	 * @param	int[optional] $page			Display more results by pagination
	 */
	public function geoGetEvents($location = null, $lat = null, $long = null, $distance = null, $page = null)
	{
		// build parameters
		$parameters = array();
		if($location !== null) $parameters['location'] = (string) $location;
		if($lat !== null) $parameters['lat'] = (string) $lat;
		if($long !== null) $parameters['long'] = (string) $long;
		if($distance !== null) $parameters['distance'] = (string) $distance;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('geo.getEvents', $parameters);
	}


	/**
	 * Get a chart of artists for a metro
	 *
	 * @return	array
	 * @param	string $country				A country name, as defined by the ISO 3166-1 country names standard
	 * @param	string $metro				The metro's name
	 * @param	string[optional] $start		Beginning timestamp of the weekly range requested.
	 * @param	string[optional] $end		Ending timestamp of the weekly range requested.
	 */
	public function geoGetMetroArtistChart($country, $metro, $start = null, $end = null)
	{
		// build parameters
		$parameters['country'] = (string) $country;
		$parameters['metro'] = (string) $metro;
		if($start !== null) $parameters['start'] = (string) $start;
		if($end !== null) $parameters['end'] = (string) $end;

		// make the call
		return $this->doCall('geo.getMetroArtistChart', $parameters);
	}


	/**
	 * Get a chart of hyped (up and coming) artists for a metro
	 *
	 * @return	array
	 * @param	string $country				A country name, as defined by the ISO 3166-1 country names standard
	 * @param	string $metro				The metro's name
	 * @param	string[optional] $start		Beginning timestamp of the weekly range requested.
	 * @param	string[optional] $end		Ending timestamp of the weekly range requested.
	 */
	public function geoGetMetroHypeArtistChart($country, $metro, $start = null, $end = null)
	{
		// build parameters
		$parameters['country'] = (string) $country;
		$parameters['metro'] = (string) $metro;
		if($start !== null) $parameters['start'] = (string) $start;
		if($end !== null) $parameters['end'] = (string) $end;

		// make the call
		return $this->doCall('geo.getMetroHypeArtistChart', $parameters);
	}


	/**
	 * Get a chart of tracks for a metro
	 *
	 * @return	array
	 * @param	string $country				A country name, as defined by the ISO 3166-1 country names standard
	 * @param	string $metro				The metro's name
	 * @param	string[optional] $start		Beginning timestamp of the weekly range requested.
	 * @param	string[optional] $end		Ending timestamp of the weekly range requested.
	 */
	public function geoGetMetroHypeTrackChart($country, $metro, $start = null, $end = null)
	{
		// build parameters
		$parameters['country'] = (string) $country;
		$parameters['metro'] = (string) $metro;
		if($start !== null) $parameters['start'] = (string) $start;
		if($end !== null) $parameters['end'] = (string) $end;

		// make the call
		return $this->doCall('geo.getMetroHypeTrackChart', $parameters);
	}


	/**
	 * Get a chart of tracks for a metro
	 *
	 * @return	array
	 * @param	string $country				A country name, as defined by the ISO 3166-1 country names standard
	 * @param	string $metro				The metro's name
	 * @param	string[optional] $start		Beginning timestamp of the weekly range requested.
	 * @param	string[optional] $end		Ending timestamp of the weekly range requested.
	 */
	public function geoGetMetroTrackChart($country, $metro, $start = null, $end = null)
	{
		// build parameters
		$parameters['country'] = (string) $country;
		$parameters['metro'] = (string) $metro;
		if($start !== null) $parameters['start'] = (string) $start;
		if($end !== null) $parameters['end'] = (string) $end;

		// make the call
		return $this->doCall('geo.getMetroHypeTrackChart', $parameters);
	}


	/**
	 * Get a chart of the artists which make that metro unique
	 *
	 * @return	array
	 * @param	string $country				A country name, as defined by the ISO 3166-1 country names standard
	 * @param	string $metro				The metro's name
	 * @param	string[optional] $start		Beginning timestamp of the weekly range requested.
	 * @param	string[optional] $end		Ending timestamp of the weekly range requested.
	 */
	public function geoGetMetroUniqueArtistChart($country, $metro, $start = null, $end = null)
	{
		// build parameters
		$parameters['country'] = (string) $country;
		$parameters['metro'] = (string) $metro;
		if($start !== null) $parameters['start'] = (string) $start;
		if($end !== null) $parameters['end'] = (string) $end;

		// make the call
		return $this->doCall('geo.getMetroUniqueArtistChart', $parameters);
	}


	/**
	 * Get a chart of tracks for a metro
	 *
	 * @return	array
	 * @param	string $country				A country name, as defined by the ISO 3166-1 country names standard
	 * @param	string $metro				The metro's name
	 * @param	string[optional] $start		Beginning timestamp of the weekly range requested.
	 * @param	string[optional] $end		Ending timestamp of the weekly range requested.
	 */
	public function geoGetMetroUniqueTrackChart($country, $metro, $start = null, $end = null)
	{
		// build parameters
		$parameters['country'] = (string) $country;
		$parameters['metro'] = (string) $metro;
		if($start !== null) $parameters['start'] = (string) $start;
		if($end !== null) $parameters['end'] = (string) $end;

		// make the call
		return $this->doCall('geo.getMetroUniqueTrackChart', $parameters);
	}


	/**
	 * Get a list of available chart periods for this metro, expressed as date ranges which can be sent to the chart services.
	 *
	 * @return	array
	 */
	public function geoGetMetroWeeklyChartlist()
	{
		// make the call
		return $this->doCall('geo.metroWeeklyChartlist');
	}


	/**
	 * Get the most popular artists on Last.fm by country
	 *
	 * @return	array
	 * @param	string $country		A country name, as defined by the ISO 3166-1 country names standard
	 */
	public function geoGetTopArtists($country)
	{
		// build parameters
		$parameters['country'] = (string) $country;

		// make the call
		return $this->doCall('geo.getTopArtists', $parameters);
	}


	/**
	 *  Get the most popular tracks on Last.fm last week by country
	 *
	 * @return	array
	 * @param	string $country				A country name, as defined by the ISO 3166-1 country names standard
	 * @param	string[optional] $location	A metro name, to fetch the charts for (must be within the country specified)
	 */
	public function geoGetTopTracks($country, $location = null)
	{
		// build parameters
		$parameters['country'] = (string) $country;
		if($location !== null) $parameters['location'] = (string) $location;

		// make the call
		return $this->doCall('geo.getTopTracks', $parameters);
	}


// Group methods
	/**
	 * Get a list of members for this group.
	 *
	 * @return	array
	 * @param	string $group	The group name to fetch the members of.
	 */
	public function groupGetMembers($group)
	{
		// build parameters
		$parameters['group'] = (string) $group;

		// make the call
		return $this->doCall('group.getMembers', $parameters);
	}


	/**
	 * Get an artist chart for a group, for a given date range. If no date range is supplied, it will return the most recent album chart for this group.
	 *
	 * @return	array
	 * @param	string $group	The last.fm group name to fetch the charts of.
	 * @param	string $from	The date at which the chart should start from.
	 * @param	string $to		The date at which the chart should end on.
	 */
	public function groupGetWeeklyAlbumChart($group, $from = null, $to = null)
	{
		// build parameters
		$parameters['group'] = (string) $group;
		if($from !== null) $parameters['from'] = (string) $from;
		if($to !== null) $parameters['to'] = (string) $to;

		// make the call
		return $this->doCall('group.weeklyAlbumChart', $parameters);
	}


	/**
	 * Get an artist chart for a group, for a given date range. If no date range is supplied, it will return the most recent album chart for this group.
	 *
	 * @return	array
	 * @param	string $group	The last.fm group name to fetch the charts of.
	 * @param	string $from	The date at which the chart should start from.
	 * @param	string $to		The date at which the chart should end on.
	 */
	public function groupGetWeeklyArtistChart($group, $from = null, $to = null)
	{
		// build parameters
		$parameters['group'] = (string) $group;
		if($from !== null) $parameters['from'] = (string) $from;
		if($to !== null) $parameters['to'] = (string) $to;

		// make the call
		return $this->doCall('group.weeklyArtistChart', $parameters);
	}


	/**
	 * Get a list of available charts for this group, expressed as date ranges which can be sent to the chart services.
	 *
	 * @return	array
	 * @param	string $group	The last.fm group name to fetch the charts list for.
	 */
	public function groupGetWeeklyChartList($group)
	{
		// build parameters
		$parameters['group'] = (string) $group;

		// make the call
		return $this->doCall('group.weeklyChartList', $parameters);
	}


	/**
	 * Get a track chart for a group, for a given date range. If no date range is supplied, it will return the most recent album chart for this group.
	 *
	 * @return	array
	 * @param	string $group	The last.fm group name to fetch the charts of.
	 * @param	string $from	The date at which the chart should start from.
	 * @param	string $to		The date at which the chart should end on.
	 */
	public function groupGetWeeklyTrackChart($group, $from = null, $to = null)
	{
		// build parameters
		$parameters['group'] = (string) $group;
		if($from !== null) $parameters['from'] = (string) $from;
		if($to !== null) $parameters['to'] = (string) $to;

		// make the call
		return $this->doCall('group.weeklyTrackChart', $parameters);
	}


// Library methods
	/**
	 *  Add an album to a user's Last.fm library
	 *
	 * @return	bool
	 * @param	string $artist	The artist that composed the track.
	 * @param	string $album	The album name you wish to add.
	 */
	public function libraryAddAlbum($artist, $album)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['album'] = (string) $album;

		// make the call
		return $this->doCall('library.addAlbum', $parameters, true, 'POST');
	}


	/**
	 * Add an artist to a user's Last.fm library
	 *
	 * @return	bool
	 * @param	string $artist	The artist that composed the track
	 */
	public function libraryAddArtist($artist)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;

		// make the call
		return $this->doCall('library.addArtist', $parameters, true, 'POST');
	}


	/**
	 * Add a track to a user's Last.fm library
	 *
	 * @return	bool
	 * @param	string $artist	The artist that composed the track
	 * @param	string $track	The track name you wish to add
	 */
	public function libraryAddTrack($artist, $track)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;

		// make the call
		return $this->doCall('library.addTrack', $parameters, true, 'POST');
	}


	/**
	 *  A paginated list of all the albums in a user's library, with play counts and tag counts.
	 *
	 * @return	array
	 * @param	string $username			The user whose library you want to fetch.
	 * @param	string[optional] $artist	An artist by which to filter tracks.
	 * @param	int[optional] $limit		Limit the amount of tracks returned (maximum/default is 50).
	 * @param	int[optional] $page			The page number you wish to scan to.
	 */
	public function libraryGetAlbums($username, $artist = null, $limit = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('library.getAlbums', $parameters);
	}


	/**
	 * A paginated list of all the artists in a user's library, with play counts and tag counts.
	 *
	 * @return	array
	 * @param	string $username		The user whose library you want to fetch.
	 * @param	int[optional] $limit	Limit the amount of artists returned (maximum/default is 50).
	 * @param	int[optional] $page		The page number you wish to scan to.
	 */
	public function libraryGetArtists($username, $limit = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('library.getArtists', $parameters);
	}


	/**
	 * A paginated list of all the tracks in a user's library, with play counts and tag counts.
	 *
	 * @return	array
	 * @param	string $username			The user whose library you want to fetch.
	 * @param	string[optional] $artist	An artist by which to filter tracks.
	 * @param	string[optional] $album		An album by which to filter tracks (needs an artist).
	 * @param	int[optional] $limit		Limit the amount of tracks returned (maximum/default is 50).
	 * @param	int[optional] $page			The page number you wish to scan to.
	 */
	public function libraryGetTracks($username, $artist = null, $album = null, $limit = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($album !== null) $parameters['album'] = (string) $album;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('library.getTracks', $parameters);
	}


// Playlist methods
	/**
	 * Add a track to a Last.fm user's playlist
	 *
	 * @return	bool
	 * @param	string $playlistID	The ID of the playlist - this is available in user.getPlaylists.
	 * @param	string $track		The track name to add to the playlist.
	 * @param	string $artist		The artist name that corresponds to the track to be added.
	 */
	public function playlistAddTrack($playlistID, $artist, $track)
	{
		// build parameters
		$parameters['playlistID'] = (string) $playlistID;
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;

		// make the call
		return $this->doCall('playlist.addTrack', $parameters, true, 'POST');
	}


	/**
	 * Create a Last.fm playlist on behalf of a user
	 *
	 * @return	array
	 * @param	string[optional] $title			Title for the playlist
	 * @param	string[optional] $description	Description for the playlist
	 */
	public function playlistCreate($title = null, $description = null)
	{
		// build parameters
		$parameters = array();
		if($title !== null) $parameters['title'] = (string) $title;
		if($description !== null) $parameters['description'] = (string) $description;

		// make the call
		return $this->doCall('playlist.create', $parameters, true, 'POST');
	}


	/**
	 * Fetch XSPF playlists using a lastfm playlist url.
	 *
	 * @return	array
	 * @param	string $playlistURL		A lastfm protocol playlist url ('lastfm://playlist/...') . See 'playlists' section for more information.
	 */
	public function playlistFetch($playlistURL)
	{
		// build parameters
		$parameters['playlistURL'] = (string) $playlistURL;

		// make the call
		return $this->doCall('playlist.fetch', $parameters);
	}


// Radio methods
	/**
	 * Fetch new radio content periodically in an XSPF format.
	 *
	 * @return	array
	 * @param	bool[optional] $discovery			Whether to request last.fm content with discovery mode switched on.
	 * @param	bool[optional] $rtp					Whether the user is scrobbling or not during this radio session (helps content generation).
	 * @param	int[optional] $bitrate				What bitrate to stream content at, in kbps (supported bitrates are 64 and 128).
	 * @param	bool[optional] $buyLinks			Whether the response should contain links for purchase/download, if available (default false).
	 * @param	string[optional] $speedMultiplier	The rate at which to provide the stream (supported multipliers are 1.0 and 2.0).
	 */
	public function radioGetPlaylist($discovery = null, $rtp = null, $bitrate = null, $buyLinks = null, $speedMultiplier = null)
	{
		// build parameters
		$parameters = array();
		if($discovery !== null) $parameters['discovery'] = ((bool) $discovery) ? '1' : '0';
		if($rtp !== null) $parameters['rtp'] = ((bool) $rtp) ? '1' : '0';
		if($bitrate !== null) $parameters['bitrate'] = (int) $parameters;
		if($buyLinks !== null) $parameters['buylink'] = ((bool) $buyLinks) ? '1' : '0';
		if($speedMultiplier !== null) $parameters['speed_multiplier'] = (string) $speedMultiplier;

		// make the call
		return $this->doCall('radio.getPlaylist', $parameters, true);
	}


	/**
	 * Tune in to a Last.fm radio station.
	 *
	 * @return	array
	 * @param	string $station			A lastfm radio URL
	 * @param	string[optional] $lang	An ISO language code to determine the language to return the station name in, expressed as an ISO 639 alpha-2 code.
	 */
	public function radioTune($station, $lang = null)
	{
		// build parameters
		$parameters['station'] = (string) $station;
		if($lang !== null) $paramaters['lang'] = (string) $lang;

		// make the call
		return $this->doCall('radio.tune', $parameters, true, 'POST');
	}


// Tag methods
	/**
	 * Search for tags similar to this one. Returns tags ranked by similarity, based on listening data.
	 *
	 * @return	array
	 * @param	string $tag		The tag name in question.
	 */
	public function tagGetSimilar($tag)
	{
		// build parameters
		$parameters['tag'] = $tag;

		// make the call
		return $this->doCall('tag.getSimilar', $parameters);
	}


	/**
	 *  Get the top albums tagged by this tag, ordered by tag count.
	 *
	 * @return	array
	 * @param	string $tag		The tag name in question.
	 */
	public function tagGetTopAlbums($tag)
	{
		// build parameters
		$parameters['tag'] = (string) $tag;

		// make the call
		return $this->doCall('tag.getTopAlbums', $parameters);
	}


	/**
	 *  Get the top artists tagged by this tag, ordered by tag count.
	 *
	 * @return	array
	 * @param	string $tag		The tag name in question.
	 */
	public function tagGetTopArtists($tag)
	{
		// build parameters
		$parameters['tag'] = (string) $tag;

		// make the call
		return $this->doCall('tag.getTopArtists', $parameters);
	}


	/**
	 * Fetches the top global tags on Last.fm, sorted by popularity (number of times used)
	 *
	 * @return	array
	 */
	public function tagGetTopTags()
	{
		// make the call
		return $this->doCall('tag.getTopTags');
	}


	/**
	 * Get the top tracks tagged by this tag, ordered by tag count.
	 *
	 * @return	array
	 * @param	string $tag		The tag name in question.
	 */
	public function tagGetTopTracks($tag)
	{
		// build parameters
		$parameters['tag'] = (string) $tag;

		// make the call
		return $this->doCall('tag.getTopTracks', $parameters);
	}


	/**
	 * Get an artist chart for a tag, for a given date range. If no date range is supplied, it will return the most recent artist chart for this tag.
	 *
	 * @return	array
	 * @param	string $tag					The tag name in question.
	 * @param	string[optional] $from		The date at which the chart should start from.
	 * @param	string[optional] $to		The date at which the chart should end on.
	 * @param	int[optional] $limit		The number of chart items to return. Default is 50.
	 */
	public function tagGetWeeklyArtistChart($tag, $from = null, $to = null, $limit = null)
	{
		// build parameters
		$parameters['tag'] = (string) $tag;
		if($from !== null) $parameters['from'] = (string) $from;
		if($to !== null) $parameters['to'] = (string) $to;
		if($limit !== null) $parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('tag.getWeeklyArtistChart', $parameters);
	}


	/**
	 * Get a list of available charts for this tag, expressed as date ranges which can be sent to the chart services.
	 *
	 * @return	array
	 * @param	string $tag		The tag name in question.
	 */
	public function tagGetWeeklyChartList($tag)
	{
		// build parameters
		$parameters['tag'] = (string) $tag;

		// make the call
		return $this->doCall('tag.getWeeklyChartList', $parameters);
	}


	/**
	 * Search for a tag by name. Returns matches sorted by relevance.
	 *
	 * @return	array
	 * @param	string $tag				The tag name in question.
	 * @param	int[optional] $limit	Limit the number of tags returned at one time. Default (maximum) is 30.
	 * @param	int[optional] $page		Scan into the results by specifying a page number. Defaults to first page.
	 */
	public function tagSearch($tag, $limit = null, $page = null)
	{
		// build parameters
		$parameters['tag'] = (string) $tag;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('tag.search', $parameters);
	}


// Tasteometer
	/**
	 * Get a Tasteometer score from two inputs, along with a list of shared artists. If the input is a User or a Myspace URL, some additional information is returned.
	 *
	 * @return	array
	 * @param	string $type1			The type for the first user, possible values are: user, artists, myspace.
	 * @param	string $type2			The type for the second user, possible values are: user, artists, myspace.
	 * @param	mixed $value1			The value for the first user, possible values are: Last.fm-username, array of artist-names, myspace profile url.
	 * @param	mixed $value2			The value for the second user, possible values are: Last.fm-username, array of artist-names, myspace profile url.
	 * @param	int[optional] $limit	How many shared artists to display. Default is 5.
	 */
	public function tasteometerCompare($type1, $type2, $value1, $value2, $limit = null)
	{
		// build parameters
		$parameters['type1'] = (string) $type1;
		$parameters['type2'] = (string) $type2;
		$parameters['value1'] = (is_array($value1)) ? implode(',', $value1) : (string) $value1;
		$parameters['value2'] = (is_array($value2)) ? implode(',', $value2) : (string) $value2;
		if($limit !== null) $parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('tasteometer.compare', $parameters);
	}


// Track methods
	/**
	 * Tag a track using a list of user supplied tags.
	 *
	 * @return	bool
	 * @param	string $artist	The artist name in question
	 * @param	string $track	The track name in question
	 * @param	array $tags		An array of user supplied tags to apply to this track. Accepts a maximum of 10 tags.
	 */
	public function trackAddTags($artist, $track, array $tags)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;
		$parameters['tags'] = implode(',', $tags);

		// make the call
		return $this->doCall('track.addTags', $parameters, true, 'POST');
	}


	/**
	 * Ban a track for a given user profile.
	 * This needs to be supplemented with a scrobbling submission containing the 'ban' rating (see the audioscrobbler API).
	 *
	 * @return	bool
	 * @param	string $artist		An artist name
	 * @param	string $track		A track name
	 */
	public function trackBan($artist, $track)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;

		// make the call
		return $this->doCall('track.ban', $parameters, true, 'POST');
	}


	/**
	 * Get a list of Buy Links for a particular Track. It is required that you supply either the artist and track params or the mbid param.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question.
	 * @param	string[optional] $track		The track name in question.
	 * @param	string[optional] $mbid		A MusicBrainz id for the album in question.
	 * @param	string[optional] $country	A country name, as defined by the ISO 3166-1 country names standard.
	 */
	public function trackGetBuylinks($artist = null, $track = null, $mbid = null, $country = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($track !== null)$parameters['track'] = (string) $track;
		if($mbid !== null)$parameters['mbid'] = (string) $mbid;
		if($country !== null)$parameters['country'] = (string) $country;

		// make the call
		return $this->doCall('track.getBuyLinks', $parameters);
	}


	/**
	 * Get the metadata for a track on Last.fm using the artist/track name or a musicbrainz id.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question
	 * @param	string[optional] $track		The track name in question
	 * @param	string[optional] $mbid		The musicbrainz id for the track
	 * @param	string[optional] $username	The username for the context of the request. If supplied, the user's playcount for this track and whether they have loved the track is included in the response.
	 */
	public function trackGetInfo($artist = null, $track = null, $mbid = null, $username = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($track !== null) $parameters['track'] = (string) $track;
		if($mbid !== null) $parameters['mbid'] = (string) $mbid;
		if($username !== null) $parameters['username'] = (string) $username;

		// make the call
		return $this->doCall('track.getInfo', $parameters);
	}


	/**
	 * Get the similar tracks for this track on Last.fm, based on listening data.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question
	 * @param	string[optional] $track		The track name in question
	 * @param	string[optional] $mbid		The musicbrainz id for the track
	 */
	public function trackGetSimilar($artist = null, $track = null, $mbid = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($track !== null) $parameters['track'] = (string) $track;
		if($mbid !== null) $parameters['mbid'] = (string) $mbid;

		// make the call
		return $this->doCall('track.getSimilar', $parameters);
	}


	/**
	 * Get the tags applied by an individual user to a track on Last.fm.
	 *
	 * @return	array
	 * @param	string $artist	The artist name in question
	 * @param	string $track	The track name in question
	 */
	public function trackGetTags($artist = null, $track = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;

		// make the call
		return $this->doCall('track.getTags', $parameters, true);
	}


	/**
	 * Get the top fans for this track on Last.fm, based on listening data. Supply either track & artist name or musicbrainz id.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question
	 * @param	string[optional] $track		The track name in question
	 * @param	string[optional] $mbid		The musicbrainz id for the track
	 */
	public function trackGetTopFans($artist = null, $track = null, $mbid = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($track !== null) $parameters['track'] = (string) $track;
		if($mbid !== null) $parameters['mbid'] = (string) $mbid;

		// make the call
		return $this->doCall('track.getTopFans', $parameters);
	}


	/**
	 * Get the top tags for this track on Last.fm, ordered by tag count. Supply either track & artist name or mbid.
	 *
	 * @return	array
	 * @param	string[optional] $artist	The artist name in question
	 * @param	string[optional] $track		The track name in question
	 * @param	string[optional] $mbid		The musicbrainz id for the track
	 */
	public function trackGetTopTags($artist = null, $track = null, $mbid = null)
	{
		// build parameters
		$parameters = array();
		if($artist !== null) $parameters['artist'] = (string) $artist;
		if($track !== null) $parameters['track'] = (string) $track;
		if($mbid !== null) $parameters['mbid'] = (string) $mbid;

		// make the call
		return $this->doCall('track.getTopTags', $parameters);
	}


	/**
	 * Love a track for a user profile. This needs to be supplemented with a scrobbling submission containing the 'love' rating (see the audioscrobbler API).
	 *
	 * @return	bool
	 * @param	string $artist	A track name
	 * @param	string $track	An artist name
	 */
	public function trackLove($artist, $track)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;

		// make the call
		return $this->doCall('track.love', $parameters, true, 'POST');
	}


	/**
	 * Remove a user's tag from a track.
	 *
	 * @return	bool
	 * @param	string $artist	The artist name in question
	 * @param	string $track	The track name in question
	 * @param	string $tag		A single user tag to remove from this track.
	 */
	public function trackRemoveTag($artist, $track, $tag)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;
		$parameters['tag'] = (string) $tag;

		// make the call
		return $this->doCall('track.removeTag', $parameters, true, 'POST');
	}


	/**
	 * Search for a track by track name. Returns track matches sorted by relevance.
	 *
	 * @return	array
	 * @param	string $track				The track name in question.
	 * @param	string[optional] $artist	Narrow your search by specifying an artist.
	 * @param	int[optional] $limit		Limit the number of tracks returned at one time. Default (maximum) is 30.
	 * @param	int[optional] $page			Scan into the results by specifying a page number. Defaults to first page.
	 */
	public function trackSearch($track, $artist = null, $limit = null, $page = null)
	{
		// build parameters
 		$parameters['track'] = (string) $track;
		if($artist !== null) $parameters['artist'] = (string) $artist;
 		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('track.search', $parameters);
	}


	/**
	 * Share an artist with Last.fm users or other friends.
	 *
	 * @return	bool
	 * @param	string $artist				The artist to share.
	 * @param	string $track				A track name.
	 * @param	array $recipients			An array of email addresses or Last.fm usernames. Maximum is 10.
	 * @param	bool[optional] $public		Show in the sharing users activity feed. Defaults to false.
	 * @param	string[optional] $message	An optional message to send with the recommendation. If not supplied a default message will be used.
	 */
	public function trackShare($artist, $track, array $recipients, $public = false, $message = null)
	{
		// build parameters
		$parameters['artist'] = (string) $artist;
		$parameters['track'] = (string) $track;
		$parameters['recipients'] = implode(',', $recipients);
		if($public) $parameters['public'] = 1;
		if($message !== null) $parameters['message'] = (string) $message;

		// make the call
		return $this->doCall('track.share', $parameters, true, 'POST');
	}


// User methods
	/**
	 * Get a list of tracks by a given artist scrobbled by this user, including scrobble time. Can be limited to specific timeranges, defaults to all time.
	 *
	 * @return	array
	 * @param	string $username		The last.fm username to fetch the recent tracks of.
	 * @param	string $artist			The artist name you are interested in
	 * @param	int[optional] $start	An unix timestamp to start at.
	 * @param	int[optional] $end		An unix timestamp to end at.
	 * @param	int[optional] $page		An integer used to fetch a specific page of tracks.
	 */
	public function userGetArtistTracks($username, $artist, $start = null, $end = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		$parameters['artist'] = (string) $artist;
		if($start !== null) $parameters['startTimestamp'] = (int) $start;
		if($end !== null) $parameters['endTimestamp'] = (int) $end;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('user.getArtistTracks', $parameters);
	}


	/**
	 * Get a list of upcoming events that this user is attending.
	 *
	 * @return	array
	 * @param	string $username	The user to fetch the events for.
	 */
	public function userGetEvents($username)
	{
		// build parameters
		$parameters['user'] = (string) $username;

		// make the call
		return $this->doCall('user.getEvents', $parameters);
	}


	/**
	 * Get a list of the user's friends on Last.fm.
	 *
	 * @return	array
	 * @param	string $username				The last.fm username to fetch the friends of.
	 * @param	bool[optional] $recentTracks	Whether or not to include information about friends' recent listening in the response.
	 * @param	int[optional] $limit			An integer used to limit the number of friends returned per page. The default is 50.
	 * @param	int[optional] $page				The page number to fetch.
	 */
	public function userGetFriends($username, $recentTracks = null, $limit = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($recentTracks) $parameters['recenttracks'] = '1';
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('user.getFriends', $parameters);
	}


	/**
	 * Get information about a user profile.
	 * @todo	check http://www.last.fm/api/show?service=344
	 *
	 * @return	array
	 * @param	string $username	The last.fm username to fetch the friends of.
	 */
	public function userGetInfo($username)
	{
		// build parameters
		$parameters['user'] = (string) $username;

		// make the call
		return $this->doCall('user.getInfo', $parameters);
	}


	/**
	 * Get the last 50 tracks loved by a user.
	 *
	 * @return	array
	 * @param	string $username		The user name to fetch the loved tracks for.
	 * @param	int[optional] $limit	An integer used to limit the number of tracks returned per page. The default is 50.
	 * @param	int[optional] $page		The page number to fetch.
	 */
	public function userGetLovedTracks($username, $limit = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($limit != null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('user.getLovedTracks', $parameters);
	}


	/**
	 * Get a list of a user's neighbours on Last.fm.
	 *
	 * @return	array
	 * @param	string $username		The last.fm username to fetch the neighbours of.
	 * @param	int[optional] $limit	An integer used to limit the number of neighbours returned.
	 */
	public function userGetNeighbours($username, $limit = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($limit !== null) $parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('user.getNeighbours', $parameters);
	}


	/**
	 * Get a paginated list of all events a user has attended in the past.
	 *
	 * @return	string
	 * @param	string $username		The username to fetch the events for.
	 * @param	int[optional] $limit	The maximum number of events to return per page.
	 * @param	int[optional] $page		The page number to scan to.
	 */
	public function userGetPastEvents($username, $limit = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($limit != null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('user.getPastEvents', $parameters);
	}


	/**
	 * Get a list of a user's playlists on Last.fm.
	 *
	 * @return	array
	 * @param	string $username	The last.fm username to fetch the playlists of.
	 */
	public function userGetPlaylists($username)
	{
		// build parameters
		$parameters['user'] = (string) $username;

		// make the call
		return $this->doCall('user.getPlaylists', $parameters);
	}


	/**
	 * Get a list of the recent Stations listened to by this user.
	 *
	 * @return	array
	 * @param	string $username		The last.fm username to fetch the recent Stations of.
	 * @param	int[optional] $limit	An integer used to limit the number of stations returned per page. The default is 10, the maximum is 25
	 * @param	int[optional] $page		The page number to fetch.
	 */
	public function userGetRecentStations($username, $limit = null, $page = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($limit != null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('user.getRecentStations', $parameters, true);
	}


	/**
	 * Get a list of the recent tracks listened to by this user.
	 *
	 * @return	array
	 * @param	string $username			The last.fm username to fetch the recent tracks of.
	 * @param	int[optional] $limit		An integer used to limit the number of tracks returned.
	 * @param	int[optional] $page			An integer used to fetch a specific page of tracks
	 * @param	bool[optional] $nowPlaying	Includes the currently playing track with the nowplaying="true" attribute if the user is currently listening.
	 */
	public function userGetRecentTracks($username, $limit = null, $page = null, $nowPlaying = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($limit != null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;
		if($nowPlaying) $paramters['nowplaying'] = 'true';

		// make the call
		return $this->doCall('user.getRecentTracks', $parameters);
	}


	/**
	 * Get Last.fm artist recommendations for a user
	 *
	 * @return	array
	 */
	public function userGetRecommendedArtists()
	{
		// make the call
		return $this->doCall('user.getRecommendedArtists', null, true);
	}


	/**
	 * Get a paginated list of all events recommended to a user by Last.fm, based on their listening profile.
	 *
	 * @return	array
	 * @param	int[optional] $limit	The number of events to return per page.
	 * @param	int[optional] $page		The page number to scan to.
	 */
	public function userGetRecommendedEvents($limit = null, $page = null)
	{
		// build parameters
		$parameters = array();
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('user.getRecommendedEvents', $parameters, true);
	}


	/**
	 * Get shouts for this user.
	 *
	 * @return	array
	 * @param	string $username	The user name to fetch shouts for.
	 */
	public function userGetShouts($username)
	{
		// build parameters
		$parameters['user'] = $username;

		// make the call
		return $this->doCall('user.getShouts', $parameters);
	}


	/**
	 * Get the top albums listened to by a user. You can stipulate a time period. Sends the overall chart by default.
	 *
	 * @return	array
	 * @param	string $user				The user name to fetch top albums for.
	 * @param	string[optional] $period	The time period over which to retrieve top albums for, possible values are: overall, 7day, 3month, 6month, 12month.
	 */
	public function userGetTopAlbums($username, $period = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($period !== null) $parameters['period'] = (string) $period;

		// make the call
		return $this->doCall('user.getTopAlbums', $parameters);
	}


	/**
	 * Get the top artists listened to by a user. You can stipulate a time period. Sends the overall chart by default.
	 *
	 * @return	array
	 * @param	string $user				The user name to fetch top artists for.
	 * @param	string[optional] $period	The time period over which to retrieve top artists for: overall, 7day, 3month, 6month, 12month.
	 */
	public function userGetTopArtists($username, $period = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($period !== null) $parameters['period'] = (string) $period;

		// make the call
		return $this->doCall('user.getTopArtists', $parameters);
	}


	/**
	 *
	 * @return	array
	 * @param	string $username		The user name
	 * @param	int[optional] $limit	Limit the number of tags returned
	 */
	public function userGetTopTags($username, $limit = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($limit !== null) $parameters['limit'] = (int) $limit;

		// make the call
		return $this->doCall('user.getTopTags', $parameters);
	}


	/**
	 *  Get the top tracks listened to by a user. You can stipulate a time period. Sends the overall chart by default.
	 *
	 * @return	array
	 * @param	string $user				The user name to fetch top tracks for.
	 * @param	string[optional] $period	The time period over which to retrieve top tracks for: overall, 7day, 3month, 6month, 12month.
	 */
	public function userGetTopTracks($username, $period = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($period !== null) $parameters['period'] = (string) $period;

		// make the call
		return $this->doCall('user.getTopTracks', $parameters);
	}


	/**
	 * Get an album chart for a user profile, for a given date range. If no date range is supplied, it will return the most recent album chart for this user.
	 *
	 * @return	array
	 * @param	string $username		The last.fm username to fetch the charts of.
	 * @param	string[optional] $from	The date at which the chart should start from.
	 * @param	string[optional] $to	The date at which the chart should end on.
	 */
	public function userGetWeeklyAlbumChart($username, $from = null, $to = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($from !== null) $parameters['from'] = (string) $from;
		if($to !== null) $parameters['to'] = (string) $to;

		// make the call
		return $this->doCall('user.getWeeklyAlbumChart', $parameters);
	}


	/**
	 * Get an artist chart for a user profile, for a given date range. If no date range is supplied, it will return the most recent artist chart for this user.
	 *
	 * @return	array
	 * @param	string $username		The last.fm username to fetch the charts of.
	 * @param	string[optional] $from	The date at which the chart should start from.
	 * @param	string[optional] $to	The date at which the chart should end on.
	 */
	public function userGetWeeklyArtistChart($username, $from = null, $to = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($from !== null) $parameters['from'] = (string) $from;
		if($to !== null) $parameters['to'] = (string) $to;

		// make the call
		return $this->doCall('user.getWeeklyArtistChart', $parameters);
	}


	/**
	 * Get a list of available charts for this user, expressed as date ranges which can be sent to the chart services.
	 *
	 * @return	array
	 * @param	string $username	The last.fm username to fetch the charts list for.
	 */
	public function userGetWeeklyChartList($username)
	{
		// build parameters
		$parameters['user'] = (string) $username;

		// make the call
		return $this->doCall('user.getWeeklyChartList', $parameters);
	}


	/**
	 * Get a track chart for a user profile, for a given date range. If no date range is supplied, it will return the most recent track chart for this user.
	 *
	 * @return	array
	 * @param	string $username		The last.fm username to fetch the charts of.
	 * @param	string[optional] $from	The date at which the chart should start from.
	 * @param	string[optional] $to	The date at which the chart should end on.
	 */
	public function userGetWeeklyTrackChart($username, $from = null, $to = null)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		if($from !== null) $parameters['from'] = (string) $from;
		if($to !== null) $parameters['to'] = (string) $to;

		// make the call
		return $this->doCall('user.getWeeklyTrackChart', $parameters);
	}


	/**
	 * Shout on this user's shoutbox
	 *
	 * @return	bool
	 * @param	string $username	The name of the user to shout on.
	 * @param	string $message		The message to post to the shoutbox.
	 */
	public function userShout($username, $message)
	{
		// build parameters
		$parameters['user'] = (string) $username;
		$parameters['message'] = (string) $message;

		// make the call
		return $this->doCall('user.shout', $parameters, true, 'POST');
	}


// Venue methods
	/**
	 * Get a list of upcoming events at this venue.
	 *
	 * @return	array
	 * @param	string $venue	The id for the venue you would like to fetch event listings for.
	 */
	public function venueGetEvents($venue)
	{
		// build parameters
		$parameters['venue'] = (string) $venue;

		// make the call
		return $this->doCall('venue.getEvents', $parameters);
	}


	/**
	 * Get a paginated list of all the events held at this venue in the past.
	 *
	 * @return	array
	 * @param	string $venue			The id for the venue you would like to fetch event listings for.
	 * @param	int[optional] $limit	The maximum number of results to return per page.
	 * @param	int[optional] $page		The page of results to return.
	 */
	public function venueGetPastEvents($venue, $limit = null, $page = null)
	{
		// build parameters
		$parameters['venue'] = (string) $venue;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;

		// make the call
		return $this->doCall('venue.getPastEvents', $parameters);
	}


	/**
	 * Search for a venue by venue name
	 *
	 * @return	array
	 * @param	string $venue				The venue name you would like to search for.
	 * @param	int[optional] $limit		The number of results to fetch per page. Defaults to 50.
	 * @param	int[optional] $page			The results page you would like to fetch
	 * @param	string[optional] $country	Filter your results by country. Expressed as an ISO 3166-2 code.
	 */
	public function venueSearch($venue, $limit = null, $page = null, $country = null)
	{
		// build parameters
		$parameters['venue'] = (string) $venue;
		if($limit !== null) $parameters['limit'] = (int) $limit;
		if($page !== null) $parameters['page'] = (int) $page;
		if($country !== null) $parameters['country'] = (string) $country;

		// make the call
		return $this->doCall('venue.search', $parameters);
	}


// Own methods
	public function auth($callbackURL = null)
	{
		// build url
		$url = 'http://www.last.fm/api/auth/?api_key='. $this->getApiKey();

		// append callback url if needed
		if($callbackURL !== null) $url .='&cb='. (string) $callbackURL;

		// redirect
		header('Location: '. $url);

		// stop the script
		exit;
	}
}


/**
 * Last.fm Exception class
 *
 * @author	Tijs Verkoyen <php-lastfm@verkoyen.eu>
 */
class LastFmException extends Exception
{
}

?>
