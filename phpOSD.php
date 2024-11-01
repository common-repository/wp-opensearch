<?php

/**
 * @author 		Fabio Savina <fabio.savina@gmail.com>
 * @version		1.1b
 * @license		GPLv2
 */
class phpOSD {
	
	/**
	 * Brief title that identifies this search engine
	 * @var string
	 */
	protected $shortName;
	
	/**
	 * Text description of the search engine
	 * @var string
	 */
	protected $description;
	
	/**
	 * Interface by which a search client can make search requests of the search engine
	 * @var array
	 */
	protected $urls;
	
	/**
	 * Example search query
	 * @var string
	 */
	protected $exampleQuery;
	
	/**
	 * Email address of the maintainer of the description document
	 * @var string
	 */
	protected $contact;
	
	/**
	 * Set of keywords to identify and categorize this search content
	 * @var string
	 */
	protected $tags;
	
	/**
	 * Extended title that identifies this search engine
	 * @var string
	 */
	protected $longName;
	
	/**
	 * Name or identifier of the creator or the maintainer of the description document
	 * @var string
	 */
	protected $developer;
	
	/**
	 * List of all sources or entities that should be credited for the content contained in the search feed
	 * @var string
	 */
	protected $attribution;
	
	/**
	 * Degree to which the search results can be queried, displayed, and redistributed
	 * @var string
	 */
	protected $syndicationRight;
	
	/**
	 * Indicates if the search results may contain material intended only for adults
	 * @var bool
	 */
	protected $adultContent;
	
	/**
	 * Indicates that the search engine supports requests encoded with the specified character encoding
	 * @var string
	 */
	protected $inputEncoding;
	
	/**
	 * Indicates that the search engine supports responses encoded with the specified character encoding
	 * @var string
	 */
	protected $outputEncoding;
	
	/**
	 * URL of an 16x16 pixels image that can be used in association with this search content
	 * @var string
	 */
	protected $smallIcon;
	
	/**
	 * URL of an 64x64 pixels image that can be used in association with this search content
	 * @var string
	 */
	protected $largeIcon;
	
	/**
	 * Indicates that the search engine supports search results in the specified language
	 * @var string
	 */
	protected $language;
	
	
	/**
	 * Create a new phpOSD object
	 * @param string $shortName
	 * @param string $description
	 */
	public function __construct($shortName, $description) {
		$this->shortName = $shortName;
		$this->description = $description;
		
		$this->urls = array();
		$this->syndicationRight = 'open';
		$this->adultContent = false;
		$this->inputEncoding = 'UTF-8';
		$this->outputEncoding = 'UTF-8';
	}
	
	
	/**
	 * Set the property value
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value) {
		if (array_key_exists($name, get_class_vars(get_class($this)))) {
			$this -> $name = strip_tags($value);
		}
	}
	
	
	/**
	 * Get the property value
	 * @param string $name
	 * @return mixed $value
	 */
	public function __get($name) {
		if (array_key_exists($name, get_class_vars(get_class($this)))) {
			return $this -> $name;
		}
		return null;
	}
	
	
	/**
	 * Set the json autocomplete source URL
	 * @param string $source
	 */
	public function autocomplete($source) {
		$this->addUrl($source, 'application/x-suggestions+json');
	}
	
	
	/**
	 * Add a new url to the description
	 * @param string $template
	 * @return string[optional] $type
	 */
	public function addUrl($template, $type = 'text/html') {
		$this->urls[] = array(
			'type' => $type,
			'template' => $template
		);
	}
	
	
	/**
	 * Check the file type
	 * @param string $fileName
	 * @param string $extension
	 * @return bool
	 */
	protected static function fileIs($fileName, $extension) {
		return (end(split('\.', $fileName)) == $extension);
	}
	
	
	/**
	 * Serve the description on the standard output
	 */
	public function serve() {
		$xml = $this -> out();
		//header("Content-type: application/opensearchdescription+xml");
		echo $xml;
	}
	
	
	/**
	 * Return the description output
	 * @return string
	 */
	public function out() {
		if ( !count( $this -> urls ) ) {
			throw new Exception('No urls defined');
		}
		
		$osd = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/"></OpenSearchDescription>');
		
		$osd -> addChild( 'ShortName', htmlspecialchars ( $this -> shortName ) );
		$osd -> addChild( 'Description', htmlspecialchars ( $this -> description ) );
		$osd -> addChild( 'LongName', htmlspecialchars ( $this -> longName ) );
		$osd -> addChild( 'Contact', htmlspecialchars ( $this -> contact ) );
		$osd -> addChild( 'Developer', htmlspecialchars ( $this -> developer ) );
		$osd -> addChild( 'Attribution', htmlspecialchars ( $this -> attribution ) );
		$osd -> addChild( 'Tags', htmlspecialchars ( $this -> tags ) );
		$osd -> addChild( 'InputEncoding', $this -> inputEncoding );
		$osd -> addChild( 'OutputEncoding', $this -> outputEncoding );
		$osd -> addChild( 'Language', $this -> language );
		$osd -> addChild( 'AdultContent', ($this->adultContent ? 'true' : 'false') );
		
		$selfUrl = $osd -> addChild( 'Url' );
		$selfUrl -> addAttribute ( 'rel', 'self' );
		$selfUrl -> addAttribute ( 'type', 'application/opensearchdescription+xml' );
		$selfUrl -> addAttribute ( 'template', htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) );
		
		foreach ( $this -> urls as $url ) {
			$link = $osd -> addChild( 'Url' );
			$link -> addAttribute( 'type', $url['type'] );
			$link -> addAttribute( 'template', htmlspecialchars($url['template']) . '{searchTerms}' );
		}
		
		if ( $this -> smallIcon ) {
			if ( self :: fileIs ( $this -> smallIcon, 'ico' ) ) {
				$image = $osd -> addChild( 'Image', htmlspecialchars ( $this -> smallIcon ) );
				$image -> addAttribute( 'height', '16' );
				$image -> addAttribute( 'width', '16' );
				$image -> addAttribute( 'type', 'image/vnd.microsoft.icon' );
			}
		}
		
		if ( $this -> largeIcon ) {
			if ( self :: fileIs ( $this -> largeIcon, 'jpg' ) || self :: fileIs ( $this -> largeIcon, 'png' ) ) {
				$image = $osd -> addChild( 'Image', htmlspecialchars ( $this -> largeIcon ) );
				$image -> addAttribute( 'height', '64' );
				$image -> addAttribute( 'width', '64' );
				$image -> addAttribute( 'type', ( self :: fileIs($this->largeIcon, 'jpg') ? 'image/jpeg' : 'image/png') );
			}
		}
		
		$query = $osd -> addChild( 'Query' );
		$query -> addAttribute( 'role', 'example' );
		$query -> addAttribute( 'searchTerms', htmlspecialchars ( $this -> exampleQuery ) );
		
		if ( @in_array ( $this -> syndicationRight, array ( 'open', 'limited', 'private', 'closed' ) ) ) {
			$osd -> addChild( 'SyndicationRight', $this -> syndicationRight );
		}
		
		return $osd -> asXML();
	}
}






