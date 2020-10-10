<?php
	require("config.php");

	require('vendor/autoload.php');
	use Nesk\Puphpeteer\Puppeteer;
	use Nesk\Rialto\Data\JsFunction;

	include("anticaptcha-php/anticaptcha.php");
	include("anticaptcha-php/nocaptchaproxyless.php");
	/*$logo=array(
		base64_decode("X19fX19fICAgICAgICAgICAgICAgICAgICAgIF8gICAgICAgICAgICAgICAgX19fX18gICAgICAgICAgICAgIF8gICAgICAgICAgICAKfCBfX18gXCAgICAgICAgICAgICAgICAgICAgKF8pICAgICAgICAgICAgICAvICBfX198ICAgICAgICAgICAgKF8pICAgICAgICAgICAKfCB8Xy8gLyBfICAgXyAgXyBfXyAgXyBfXyAgIF8gIF8gX18gICAgX18gXyBcIGAtLS4gICBfX18gIF8gX18gIF8gICBfX18gIF9fXyAKfCBfX18gXHwgfCB8IHx8ICdfX3x8ICdfIFwgfCB8fCAnXyBcICAvIF9gIHwgYC0tLiBcIC8gXyBcfCAnX198fCB8IC8gXyBcLyBfX3wKfCB8Xy8gL3wgfF98IHx8IHwgICB8IHwgfCB8fCB8fCB8IHwgfHwgKF98IHwvXF9fLyAvfCAgX18vfCB8ICAgfCB8fCAgX18vXF9fIFwKXF9fX18vICBcX18sX3x8X3wgICB8X3wgfF98fF98fF98IHxffCBcX18sIHxcX19fXy8gIFxfX198fF98ICAgfF98IFxfX198fF9fXy8KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgX18vIHwgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB8X19fLyAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA="),
		'LinkFetcher - https://github.com/EvilCorpTM/BurningSeriesLinkFetcher'
	);
	echo implode("\n",$logo);*/

	if (sizeof($argv) != 2) die("\n\nPlease Use Following Syntax:\nphp -f main.php \"https://bs.to/serie/Drake-and-Josh/de\"\n\n\n");
	$url = $argv[1];
	@mkdir("dl");
	function cache_get($pageLink) {
	        $pageCache = "cache/".urlencode(base64_encode($pageLink));
        	if (!file_exists($pageCache)) { file_put_contents($pageCache, file_get_contents($pageLink)); }
        	return file_get_contents($pageCache);
	}
	$api = new NoCaptchaProxyless();
	$api->setVerboseMode(false);
	$api->setKey($ANTI_CAPTCHA_KEY);
	$api->setWebsiteKey($BS_CAPTCHA_KEY);
	$api->setWebsiteURL("https://serienstream.sx/");
	echo "Anti-Captcha Balance: USD ".($api->getBalance())."\n\n";

	function parseHeaders( $headers ) {
	    $head = array();
	    foreach( $headers as $k=>$v ) {
	        $t = explode( ':', $v, 2 );
	        if( isset( $t[1] ) )
	            $head[ trim($t[0]) ] = trim( $t[1] );
	        else {
	            $head[] = $v;
	            if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
	                $head['reponse_code'] = intval($out[1]);
	        }
	    }
	    return $head;
	}

	$re1 = '/<a\s*.*href="(\/serie\/stream\/.*)"\s+title="(.*)"\s*>.*<\/a>/m';
	$data = cache_get($url);

	$seasonIndex = substr($data, strpos($data, '<span><strong>Staffeln:</strong></span>'));
	//$seasonIndex = substr($data, strpos($data, '<ul class="clearfix">'));
	$seasonIndex = substr($seasonIndex, 0, strpos($seasonIndex, '</ul>'));
	preg_match_all($re1, $seasonIndex, $matches, PREG_SET_ORDER, 0);

	$title=explode('/',$matches[0][1])[3];
	echo "$title\n";
	@mkdir("dl/$title");

	$indexFile = "dl/$title/index.json";
	$index = array();
	if (!file_exists($indexFile)) file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT)); else $index=json_decode(file_get_contents($indexFile), true);
	//print_r($index);

	foreach ($matches as $season) {
		//print_r($season);
		$seasonURL="https://serienstream.sx".$season[1];
		echo $seasonURL."\t".$season[2]."\n";

		$episodeData = cache_get($seasonURL);
		$episodeIndex = substr($episodeData, strpos($episodeData, '<strong>Episoden:</strong>'));
		$episodeIndex = substr($episodeIndex, 0, strpos($episodeIndex, '</ul>'));

		preg_match_all('/<a\s*href="(\/serie\/stream\/.*\/.*\/.*)"\s+data-episode-id="(\d+)"\s+title="(.*)"\s*data-season-id="(\d+)"\s*>.*<\/a>/m', $episodeIndex, $matches2, PREG_SET_ORDER, 0);

		@mkdir("dl/$title/".$season[2]);
		$linkListPath = "dl/$title/".$season[2]."/links.txt";

		$lastEpisodeIdentifier="";
		foreach ($matches2 as $episode) {
			$episodeURL="https://serienstream.sx".$episode[1];
			//if (strpos($episodeURL, $seasonURL) === false) continue;
			//get serie/<SeriesName>/<Season>/<EpisodeName>/<Country>/<StreamSite> into parts
			//if (array_pop($b) == $country) continue;
			$episodeIdentifier=$episode[3];
			$episodeSUrl="https://serienstream.sx".$episode[1];
			$episodeHTML=cache_get("https://serienstream.sx".$episode[1]);
			preg_match_all('/<li\s+class=".*"\s+data-lang-key="1"\s+data-link-id="(\d+)"\s+data-link-target="\/redirect\/(\d+)"\s+data-external-embed="(true|false)">/m', $episodeHTML, $matches3, PREG_SET_ORDER, 0);
			foreach($matches3 as $downloadInfo) {
				echo "$episodeIdentifier\t$episodeURL\t\t";
				//print_r($downloadInfo);
				if ($FETCH_ALL_LINKS == false && $episodeIdentifier == $lastEpisodeIdentifier) { echo "skipping[p]\n"; continue; }
	                        if (array_key_exists($episodeIdentifier, $index)) { echo "skipping[i]\n"; continue; }
				$link="https://serienstream.sx/redirect/".$downloadInfo[1];
				$api->setWebsiteURL($link);
				if (!$api->createTask()) {
				    $api->debout("API v2 send failed - ".$api->getErrorMessage(), "red");
				    return false;
				}
				echo "solving captcha...";
				$taskId = $api->getTaskId();
				if (!$api->waitForResult()) {
				        $api->debout("could not solve captcha", "red");
				        $api->debout($api->getErrorMessage());
				        die($api->getErrorMessage());
				} else {
				        $recaptchaToken = $api->getTaskSolution();
				}
				echo "fetching link...";
				$dataLink = $link."?token=".$api->getTaskSolution()."&original=1";
				file_get_contents($dataLink);
				//var_dump($http_response_header);
				$headers=parseHeaders($http_response_header);
				if (isset($headers["Location"])) {
					echo "successfull ".$headers["Location"];
					file_put_contents($linkListPath, $headers["Location"]." ".$episode[3]." ".$episodeIdentifier."\n", FILE_APPEND);
					$index[$episodeIdentifier] = array(
						'link' => $headers["Location"],
						'title' => $episode[3],
						'season' => $episode[4],
						'url' => $episodeURL,
						'time' => time()
					);
					file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
					echo "\n";
					$lastEpisodeIdentifier=$episodeIdentifier;
				} else {
					echo "unsuccessfull\n";
				}
				//$embed = bsRequest($episodeSUrl, $api->getTaskSolution());
				//print_r($embed);
				/*if ($embed->success) {*/

			}
			continue;
		}
/*
*/
	}
?>
