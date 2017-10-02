<?php
	/**
	 * @param $feed_url
	 * @return \SimpleXMLElement|false
	 */
	function load_xml($feed_url) {

		// Add http before loading the URL
		$feed_url = add_http($feed_url);

		// Checks if given URL is a valid RSS/Atom feed. If not, exit with error.
		$xml = @simplexml_load_file($feed_url);

		return $xml;
	}

	function add_http($feed_url) {

		// Check if http is present or not, if not add it
		if(!preg_match("~^(?:f|ht)tps?://~i", $feed_url)) {
			$feed_url = "http://" . $feed_url;
		}

		return $feed_url;
	}

	function load_atom_feed($xml) {

		$feed = array();


		$xml->registerXPathNamespace("atom", "http://www.w3.org/2005/Atom");
		foreach ($xml->xpath("//atom:entry") as $entry) {
			array_push($feed, $entry);
		}

		return $feed;

	}

	function load_rss_feed($xml) {

		$feed = array();
		foreach ($xml->xpath("//item") as $item) {
			array_push($feed, $item);
		}

		return $feed;

	}

	function format_date($date) {

		// Convert the date to a simpler format
		return date('d F, Y G:i e', strtotime($date));
	}

	function load_feed($xml) {

		// Check if the feed is RSS or Atom and create feed from XML object
		if($xml->channel) {
			$feed = load_rss_feed($xml);

			// Sort the posts with order latest post first
			usort($feed, function($post1, $post2) {
				return strtotime($post2->pubDate) - strtotime($post1->pubDate);
			});
		}
		else {
			$feed = load_atom_feed($xml);

			// Sort the posts with order latest post first
			usort($feed, function($post1, $post2) {
				return strtotime($post2->updated) - strtotime($post1->updated);
			});
		}

		return $feed;
	}

	function display_posts($feed, $is_rss) {

		if($is_rss) {
			foreach ($feed as $post) {
				echo "<h2><a href=" . $post->link . ">" . $post->title . " (" . $post->author . ")" ."</a></h2>";
				echo "<h4>" . format_date($post->pubDate) . "</h4>";
				echo "<p>" . $post->description . "</p>";
				echo "<br><br><br>";
			}
		}
		else {
			foreach ($feed as $post) {
				echo "<h2><a href=" . $post->link['href'] . ">" . $post->title . " (" . $post->author->name . ")" ."</a></h2>";
				echo "<h4>" . format_date($post->updated) . "</h4>";
				echo "<p>" . $post->content . "</p>";
				echo "<br><br><br>";
			}
		}
	}

	/**
	 * Returns an array of SimpleXMLElement items.
	 * @param SimpleXMLElement $xml
	 * @return SimpleXMLElement[]|bool
	 */
	function get_posts($xml) {
		if (!$xml || !$feed = load_feed($xml)) {
			return false;
		}

		return $feed;
	}

	/**
	 * Returns feed XML Wrapper.
	 * @return false|\SimpleXMLElement
	 */
	function get_feed_xml() {
		// First get submitted url
		if (!$url = isset($_GET['siteurl']) ?
		  filter_var($_GET['siteurl'], FILTER_VALIDATE_URL) : false
		) {
			return false;
		}

		/** @var string[] $feeds */
		$feeds = find_feed_url($url);

		$xml = false;
		foreach ($feeds as $feed) {
			if ($xml = load_xml($feed)) {
				break;
			}
		}

		return $xml;
	}

	/**
	 * Finds RSS and atomb feeds from document <link> items.
	 * @param $url
	 * @return array|bool
	 */
	function find_feed_url($url) {
		// Get site contents.
		if (!$content = get_site_content($url)) {
			return false;
		}

		// Find feed tags.
		$document = new DOMDocument();
		if (!@$document->loadHTML($content)) {
			return false;
		}

		$xpath = new DOMXPath($document);
		/** @var DOMNodeList $nodes */
		$nodes = $xpath->query(
		  'head/link[@rel="alternate"][@type="application/atom+xml" or @type="application/rss+xml"][@href]'
		);

		$feeds = array();

		/** @var DOMElement $node */
		foreach ($nodes as $node) {
			$feeds[] = $node->getAttribute('href');
		}

		return $feeds;
	}

	/**
	 * Returns document content for an URL.
	 * @param $url
	 * @return false|string
	 * @throws \Exception
	 */
	function get_site_content($url) {
		// If curl is available then use it. It's more consistent.
		if (function_exists('curl_version')) {
			// Initializes channel
			$channel = curl_init();

			$cookies = tempnam(sys_get_temp_dir(), 'feedriver');
			$options = array(
			  CURLOPT_URL => $url,
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_HEADER => false,
			  CURLOPT_FOLLOWLOCATION => true,
			  CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36",
			  CURLOPT_AUTOREFERER => true,
			  CURLOPT_CONNECTTIMEOUT => 120,
			  CURLOPT_TIMEOUT => 120,
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_SSL_VERIFYPEER => false,
			  CURLOPT_COOKIEJAR => $cookies,
			  CURLOPT_COOKIEFILE => $cookies,
			);
			curl_setopt_array($channel, $options);
			$data = curl_exec($channel);

			if ($error = curl_error($channel)) {
				throw new Exception($error);
			}

			// Closes channel
			curl_close($channel);

			return $data;
		}

		// Then use file_get_contents.
		return @file_get_contents($url);
	}
?>

<!DOCTYPE html>
<html>
<head>
	<title>Feed-River - A minimalistic RSS/Atom Feed Reader</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<!-- jQuery library -->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

	<link rel="stylesheet" type="text/css" href="css/style.css">
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lato">

	<!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</head>
<body>

	<header>
		<div class="navbar navbar-default">
			<div class="navbar-header"><a class="navbar-brand" href="index.php">Feed-River</a></div>	
		</div>
	</header>

	<div class="col-xs-12 col-md-12 col-xs-12"">
		<div class="container-fluid">
			<?php $xml = get_feed_xml(); ?>
			<?php if ($xml && $posts = get_posts($xml)): ?>
				<div class="post">
					<?php display_posts($posts, isset($xml->channel)); ?>
				</div>
			<?php else: ?>
				<div class="alert alert-danger">
					<span class="glyphicon glyphicon-exclamation-sign"
						  aria-hidden="true"></span>
					&nbsp;Invalid feed URL. Please enter a valid RSS/Atom URL.
				</div>
			<?php endif ?>
		</div>

		<footer class="text-primary footer-feed">
			<p>
				Feed-River&nbsp;©
				<a href="https://github.com/ashwani99">Ashwani Gupta</a>
				&nbsp;
				2017
			</p>
		</footer>

	</div>
</body>
</html>
