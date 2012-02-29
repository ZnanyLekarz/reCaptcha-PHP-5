<?php
namespace Library;
use Library\Captcha\Response;
use Library\Captcha\Exception;

/**
 * Copyright (c) 2012, Aleksey Korzun <al.ko@webfoundation.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation are those
 * of the authors and should not be interpreted as representing official policies,
 * either expressed or implied, of the FreeBSD Project.
 *
 * Proper library for reCaptcha service
 *
 * @author Aleksey Korzun <al.ko@webfoundation.net>
 * @throws Exception
 * @package library
 */
class Captcha {
	/**
	 * reCaptcha's API server
	 * @const SERVER
	 */
	CONST SERVER = 'http://api.recaptcha.net';

	/**
	 * reCaptcha's secure API server
	 * @const SERVER_SECURE
	 */
	CONST SERVER_SECURE = 'https://api-secure.recaptcha.net';

	/**
	 * reCaptcha's verify  server
	 * @const VERIFY_SERVER
	 */
	CONST VERIFY_SERVER = 'api-verify.recaptcha.net';

	/**
	 * Private key
	 * @var string $_privateKey
	 */
	protected $_privateKey;

	/**
	 * Public key
	 * @var string $_publicKey
	 */
	protected $_publicKey;

	/**
	 * Custom error message to return
	 * @var string $_error
	 */
	protected $_error;

	/**
	 * Flag to use SSL for our request(s)
	 * @var bool $_isSSL
	 */
	protected $_isSSL = false;

	/**
	 * Set SSL flag
	 *
	 * @param bool $flag
	 * @return void
	 */
	public function setSSL($flag = true) {
		$this->_isSSL = (bool) $flag;
	}

	/**
	 * Check if SSL is currently enabled
	 *
	 * @param void
	 * @return bool
	 */
	public function isSSL() {
		return (bool) $this->_isSSL;
	}

	/**
	 * Set public key
	 *
	 * @param string $key
	 * @return reCaptcha
	 */
	public function setPublicKey($key) {
		$this->_publicKey = $key;
		return $this;
	}

	/**
	 * Retrieve currently set public key
	 *
	 * @param void
	 * @return string
	 */
	public function getPublicKey() {
		return $this->_publicKey;
	}

	/**
	 * Set private key
	 *
	 * @param string $key
	 * @return reCaptcha
	 */
	public function setPrivateKey($key) {
		$this->_privateKey = $key;
		return $this;
	}

	/**
	 * Retrieve currently set private key
	 *
	 * @param void
	 * @return string
	 */
	public function getPrivateKey() {
		return $this->_privateKey;
	}

	/**
	 * Set public key
	 *
	 * @param string $key
	 * @return reCaptcha
	 */
	public function setError($error) {
		$this->_error = (string) $error;
		return $this;
	}

	/**
	 * Retrieve currently set error
	 *
	 * @param void
	 * @return string
	 */
	public function getError() {
		return $this->_error;
	}

	/**
	 * Generates reCaptcha form to output to your end user
	 *
	 * @throws Exception
	 * @param void
	 * @return string
	 */
	public function html() {
		if (!$this->getPublicKey()) {
			throw new Exception('You must set public key provided by reCaptcha');
		}

		if ($this->isSSL()) {
			$server = self::SERVER_SECURE;
		} else {
			$server = self::SERVER;
		}

		$error = ($this->getError() ? '&amp;error=' . $this->getError() : null);

		return '<script type="text/javascript" src="' . $server . '/challenge?k=' . $this->getPublicKey() . $error . '"></script>

		<noscript>
			<iframe src="' . $server . '/noscript?k=' . $this->getPublicKey() . $error . '" height="300" width="500" frameborder="0"></iframe><br/>
			<textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
			<input type="hidden" name="recaptcha_response_field" value="manual_challenge"/>
		</noscript>';
	}

	/**
	 * Checks and validates user's response
	 *
	 * @throws Exception
	 * @param void
	 * @return Response
	 */
	public function check() {
		if (!$this->getPrivateKey()) {
			throw new Exception('You must set private key provided by reCaptcha');
		}

		$captcha_challenge = false;
		$captcha_response = false;

		// Skip processing of empty data
		if (isset($_POST['recaptcha_challenge_field']) && isset($_POST['recaptcha_response_field'])) {
			$captcha_challenge = $_POST['recaptcha_challenge_field'];
			$captcha_response = $_POST['recaptcha_response_field'];
		}

		// Instance of response object
		$response = new Response();

		// Discard SPAM submissions
		if (strlen($captcha_challenge) == 0 || strlen($captcha_response) == 0) {
			$response->setValid(false);
			$response->setError('Incorrect-captcha-sol');
			return $response;
		}

		$process = $this->_process(array(
									'privatekey' => $this->getPrivateKey(),
									'remoteip' => $_SERVER['REMOTE_ADDR'],
									'challenge' => $captcha_challenge,
									'response' => $captcha_response));

		$answers = explode("\n", $process [1]);

		if (trim($answers[0]) == 'true') {
			$response->setValid(true);
		} else {
			$response->setValid(false);
			$response->setError($answers[1]);
		}

		return $response;
	}

	/**
	 * Make a signed validation request to reCaptcha's servers
	 *
	 * @throws Exception
	 * @param array $parameters
	 * @return string
	 */
	protected function _process($parameters) {
		// Properly encode parameters
		$parameters = $this->_encode($parameters);

		$request  = "POST /verify HTTP/1.0\r\n";
		$request .= "Host: " . self::VERIFY_SERVER . "\r\n";
		$request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
		$request .= "Content-Length: " . strlen($parameters) . "\r\n";
		$request .= "User-Agent: reCAPTCHA/PHP5\r\n";
		$request .= "\r\n";
		$request .= $parameters;

		if (false == ($socket = @fsockopen(self::VERIFY_SERVER, 80))) {
			throw new Exception('Could not open socket to: ' . self::VERIFY_SERVER);
		}

		fwrite($socket, $request);

		$response = '';

		while (!feof($socket) ) {
			$response .= fgets($socket, 1160);
		}

		fclose($socket);

		return explode("\r\n\r\n", $response, 2);
	}

	/**
	 * Construct encoded URI string from an array
	 *
	 * @param array $parameters
	 * @return string
	 */
	protected function _encode(array $parameters) {
		$uri = '';

		if ($parameters) {
			foreach ($parameters as $parameter => $value) {
				$uri .= $parameter . '=' . urlencode(stripslashes($value)) . '&';
			}
		}

		$uri = substr($uri, 0, strlen($uri)-1);

		return $uri;
	}
}