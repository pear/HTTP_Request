<?php
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Richard Heyes <richard@phpguru.org>                          |
// +----------------------------------------------------------------------+
//
// $Id$
//
// HTTP_Request Class
//

require_once('Net/Socket.php');
require_once('Net/URL.php');

define('HTTP_REQUEST_METHOD_GET',     'GET',     true);
define('HTTP_REQUEST_METHOD_HEAD',    'HEAD',    true);
define('HTTP_REQUEST_METHOD_POST',    'POST',    true);
define('HTTP_REQUEST_METHOD_PUT',     'PUT',     true);
define('HTTP_REQUEST_METHOD_DELETE',  'DELETE',  true);
define('HTTP_REQUEST_METHOD_OPTIONS', 'OPTIONS', true);
define('HTTP_REQUEST_METHOD_TRACE',   'TRACE',   true);

define('HTTP_REQUEST_HTTP_VER_1_0', '1.0', true);
define('HTTP_REQUEST_HTTP_VER_1_1', '1.1', true);

class HTTP_Request {

    /**
    * Full url
    * @var string
    */
    var $_url;

    /**
    * Type of request
    * @var string
    */
    var $_method;

    /**
    * HTTP Version
    * @var string
    */
    var $_http;

    /**
    * Request headers
    * @var array
    */
    var $_requestHeaders;

    /**
    * Basic Auth Username
    * @var string
    */
    var $_user;
    
    /**
    * Basic Auth Password
    * @var string
    */
    var $_pass;

    /**
    * Socket object
    * @var object
    */
    var $_sock;
    
    /**
    * Proxy server
    * @var string
    */
    var $_proxy_host;
    
    /**
    * Proxy port
    * @var integer
    */
    var $_proxy_port;
    
    /**
    * Proxy username
    * @var string
    */
    var $_proxy_user;
    
    /**
    * Proxy password
    * @var string
    */
    var $_proxy_pass;

    /**
    * Post data
    * @var mixed
    */
    var $_postData;

    /**
    * Connection timeout.
    * @var integer
    */
    var $_timeout;

    /**
    * Constructor
    *
    * Sets up the object
    * @param $url The url to fetch/access
    * @param $params Associative array of parameters which can be:
    *                  method     - Method to use, GET, POST etc
    *                  http       - HTTP Version to use, 1.0 or 1.1
    *                  user       - Basic Auth username
    *                  pass       - Basic Auth password
    *                  proxy_host - Proxy server host
    *                  proxy_port - Proxy server port
    *                  proxy_user - Proxy auth username
    *                  proxy_pass - Proxy auth password
    *                  timeout    - Connection timeout in seconds.
    * @access public
    */
    function HTTP_Request($url, $params = array())
    {
        $this->_url    =& new Net_URL($url);
        $this->_sock   =& new Net_Socket();
        $this->_method =  HTTP_REQUEST_METHOD_GET;
        $this->_http   =  HTTP_REQUEST_HTTP_VER_1_1;

        $this->_user = null;
        $this->_pass = null;

        $this->_proxy_host = null;
        $this->_proxy_port = null;

        $this->_timeout = null;

        foreach ($params as $key => $value) {
            $this->{'_' . $key} = $value;
        }

        // Default useragent
        $this->addHeader('User-Agent', 'PEAR HTTP_Request class ( http://pear.php.net/ )');

        // Default Content-Type
        $this->addHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Make sure keepalives dont knobble us
        $this->addHeader('Connection', 'close');

        // Basic authentication
        if (!empty($this->_user)) {
            $this->_requestHeaders['Authorization'] = 'Basic ' . base64_encode($this->_user . ':' . $this->_pass);
        }

        // Host header
        if (HTTP_REQUEST_HTTP_VER_1_1 == $this->_http) {
            $this->addHeader('Host', $this->_url->host);
        }
    }
    
    /**
    * Sets a proxy to be used
    *
    * @param $host Proxy host
    * @param $port Proxy port
    * @param $user Proxy username
    * @param $pass Proxy password
    * @access public
    */
    function setProxy($host, $port = 8080, $user = null, $pass = null)
    {
        $this->_proxy_host = $host;
        $this->_proxy_port = $port;
        $this->_proxy_user = $user;
        $this->_proxy_port = $port;

        if (!empty($user)) {
            $this->addHeader('Proxy-Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        }
    }

    /**
    * Sets basic authentication parameters
    *
    * @param $user Username
    * @param $pass Password
    */
    function setBasicAuth($user, $pass)
    {
        $this->_user = $user;
        $this->_pass = $pass;

        $this->addHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
    }

    /**
    * Sets the method to be used, GET, POST etc.
    *
    * @param $method Method to use. Use the defined constants for this
    * @access public
    */
    function setMethod($method)
    {
        $this->_method = $method;
    }

    /**
    * Sets the HTTP version to use, 1.0 or 1.1
    *
    * @param $http Version to use. Use the defined constants for this
    * @access public
    */
    function setHttpVer($http)
    {
        $this->_http = $http;
    }

    /**
    * Adds a request header
    *
    * @param $name Header name
    * @param $value Header value
    * @access public
    */
    function addHeader($name, $value)
    {
        $this->_requestHeaders[$name] = $value;
    }

    /**
    * Removes a request header
    *
    * @param $name Header name to remove
    * @access public
    */
    function removeHeader($name)
    {
        if (isset($this->_requestHeaders[$name])) {
            unset($this->_requestHeaders[$name]);
        }
    }

    /**
    * Adds a querystring parameter
    *
    * @param $name Querystring parameter name
    * @param $value Querystring parameter value
    * @param $preencoded Whether the value is already urlencoded or not, default = not
    * @access public
    */
    function addQueryString($name, $value, $preencoded = false)
    {
        $this->_url->addQueryString($name, $value, $preencoded);
    }    
    
    /**
    * Sets the querystring to literally what you supply
    *
    * @param $querystring The querystring data. Should be of the format foo=bar&x=y etc
    * @param $preencoded Whether data is already urlencoded or not, default = already encoded
    * @access public
    */
    function addRawQueryString($querystring, $preencoded = true)
    {
        $this->_url->addRawQueryString($querystring, $preencoded);
    }

    /**
    * Adds postdata items
    *
    * @param $name Post data name
    * @param $value Post data value
    * @param $preencoded Whether data is already urlencoded or not, default = not
    * @access public
    */
    function addPostData($name, $value, $preencoded = false)
    {
        $this->_postData[$name] = $preencoded ? $value : urlencode($value);
    }

    /**
    * Adds raw postdata
    *
    * @param $postdata The data
    * @param $preencoded Whether data is preencoded or not, default = already encoded
    * @access public
    */
    function addRawPostData($postdata, $preencoded = true)
    {
        $this->_postData = $preencoded ? $postdata : urlencode($postdata);
    }

    /**
    * Sends the request
    *
    * @access public
    */
    function sendRequest()
    {
        $host = isset($this->_proxy_host) ? $this->_proxy_host : $this->_url->host;
        $port = isset($this->_proxy_port) ? $this->_proxy_port : $this->_url->port;

        $this->_sock->connect($host, $port, null, $this->_timeout);
        $this->_sock->write($this->_buildRequest());

        $this->readResponse();
    }

    /**
    * Returns the response code
    *
    * @access public
    */
    function getResponseCode()
    {
        return isset($this->_response->_code) ? $this->_response->_code : false;
    }

    /**
    * Returns either the named header or all if no name given
    *
    * @param $headername The header name to return
    * @access public
    */
    function getResponseHeader($headername = null)
    {
        if (!isset($headername)) {
            return $this->_response->_headers;
        } else {
            return isset($this->_response->_headers[$headername]) ? $this->_response->_headers[$headername] : false;
        }
    }

    /**
    * Returns the body of the response
    *
    * @access public
    */
    function getResponseBody()
    {
        return isset($this->_response->_body) ? $this->_response->_body : false;
    }

    /**
    * Builds the request string
    *
    * @access private
    * @return string The request string
    */
    function _buildRequest()
    {
        $querystring = ($querystring = $this->_url->getQueryString()) ? '?' . $querystring : '';

        $host = isset($this->_proxy_host) ? $this->_url->protocol . '://' . $this->_url->host : '';
        $port = (isset($this->_proxy_host) AND $this->_url->port != 80) ? ':' . $this->_url->port : '';
        $path = $this->_url->path . $querystring;
        $url  = $host . $port . $path;

        $request = $this->_method . ' ' . $url . ' HTTP/' . $this->_http . "\r\n";

        // Request Headers
        if (!empty($this->_requestHeaders)) {
            foreach ($this->_requestHeaders as $name => $value) {
                $request .= $name . ': ' . $value . "\r\n";
            }
        }

        // Post data if it's an array
        if (!empty($this->_postData) AND is_array($this->_postData)) {
            foreach($this->_postData as $name => $value) {
                $postdata[] = $name . '=' . $value;
            }
            $postdata = implode('&', $postdata);
            $request .= 'Content-Length: ' . strlen($postdata) . "\r\n\r\n";
            $request .= $postdata;

        // Post data if it's raw
        } elseif(!empty($this->_postData)) {
            $request .= 'Content-Length: ' . strlen($this->_postData) . "\r\n\r\n";
            $request .= $this->_postData;

        // No post data, so simply add a final CRLF
        } else {
            $request .= "\r\n";
        }
        
        return $request;
    }

    /**
    * Initiates reading of the response
    *
    * @access private
    */
    function readResponse()
    {
        $this->_response =& new HTTP_Response($this->_sock);
    }
}


/**
* Response class to complement the Request class
*/
class HTTP_Response {

    /**
    * Socket object
    * @var object
    */
    var $_sock;

    /**
    * Protocol
    * @var string
    */
    var $_protocol;
    
    /**
    * Return code
    * @var string
    */
    var $_code;
    
    /**
    * Response headers
    * @var array
    */
    var $_headers;
    
    /**
    * Response body
    * @var string
    */
    var $_body;

    /**
    * Constructor
    *
    * Reads the entire response, parse out the headers, and checks
    * for chunked encoding.
    */
    function HTTP_Response(&$sock)
    {
        // Fetch all
        $response = $sock->readAll();

        // Sort out headers
        $headers = substr($response, 0, strpos($response, "\r\n\r\n"));
        $headers = explode("\r\n", $headers);

        list($this->_protocol, $this->_code) = sscanf($headers[0], '%s %s');
        unset($headers[0]);
        foreach ($headers as $value) {
            $headername  = substr($value, 0, strpos($value, ':'));
            $headervalue = ltrim(substr($value, strpos($value, ':') + 1));

            $this->_headers[$headername] = $headervalue;
        }

        // Store body
        $this->_body = substr($response, strpos($response, "\r\n\r\n") + 4);

        // If response was chunked, parse it out
        if (@$this->_headers['Transfer-Encoding'] == 'chunked') {
            $body   = $this->_body;
            $chunks = array();
            while (true) {
                $chunksize = 0;
                $line = substr($body, 0, $pos = strpos($body, "\r\n"));
                $body = substr($body, $pos + 2);

                if (preg_match('/^([0-9a-f]+)/i', $line, $matches)) {
                    $chunksize = hexdec($matches[1]);
                    if ($chunksize > 0) {
                        $chunks[] = substr($body, 0, $chunksize);
                        $body = substr($body, $chunksize + 2); // Plus trailing CRLF
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
            
            // Save chunks to $this->_body
            $this->_body = implode('', $chunks);
        }
    }
}
?>
