<?php
/**
* This example will fetch the index page of php.net
* and display it.
*/

	include('HTTP/Request.php');

	$req =& new HTTP_Request('http://www.php.net/');
	$req->sendRequest();

	echo $req->getResponseBody();
?>