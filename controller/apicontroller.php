<?php
/**
 * ownCloud - ocsms
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Loic Blot <loic.blot@unix-experience.fr>
 * @copyright Loic Blot 2014-2015
 */

namespace OCA\OcSms\Controller;


use \OCP\IRequest;
use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;

use \OCA\OcSms\AppInfo\OcSmsApp;

use \OCA\OcSms\Db\SmsMapper;

class ApiController extends Controller {

	private $app;
	private $userId;
	private $smsMapper;
	private $errorMsg;

	public function __construct ($appName, IRequest $request, $userId, SmsMapper $mapper, OcSmsApp $app) {
		parent::__construct($appName, $request);
		$this->app = $app;
		$this->userId = $userId;
		$this->smsMapper = $mapper;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function getApiVersion () {
		return new JSONResponse(array("version" => 1));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * 
	 * This function is used by API v1
	 * Phone will compare its own message list with this
	 * message list and send the missing messages
	 * This call will remain as secure slow sync mode (1 per hour)
	 */
	public function retrieveAllIds () {
		$smsList = $this->smsMapper->getAllIds($this->userId);
		return new JSONResponse(array("smslist" => $smsList));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * 
	 * This function is used by API v2
	 * Phone will get this ID to push recent messages
	 * This call will be used combined with retrieveAllIds
	 * but will be used more times
	 */
	public function retrieveLastTimestamp () {
		$ts = $this->smsMapper->getLastTimestamp($this->userId);
		return new JSONResponse(array("timestamp" => $ts));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function retrieveAllIdsWithStatus () {
		$smsList = $this->smsMapper->getAllIdsWithStatus($this->userId);
		return new JSONResponse(array("smslist" => $smsList));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * This function is used by API v2
	 * Phone will get this list to generate a ListView
	 */
	public function getAllStoredPhoneNumbers () {
		$phoneList = $this->smsMapper->getAllPhoneNumbers($this->userId);
		return new JSONResponse(array("phoneList" => $phoneList));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function push ($smsCount, $smsDatas) {
		if ($this->checkPushStructure($smsCount, $smsDatas, true) === false) {
			return new JSONResponse(array("status" => false, "msg" => $this->errorMsg));
		}

		$this->smsMapper->writeToDB($this->userId, $smsDatas);
		return new JSONResponse(array("status" => true, "msg" => "OK"));
	}

	/**
	 * @NoAdminRequired
	 */
	 public function replace($smsCount, $smsDatas) {
		 if ($this->checkPushStructure($smsCount, $smsDatas, true) === false) {
			return new JSONResponse(array("status" => false, "msg" => $this->errorMsg));
		}

		$this->smsMapper->writeToDB($this->userId, $smsDatas, true);
		return new JSONResponse(array("status" => true, "msg" => "OK"));
	 }

	private function checkPushStructure ($smsCount, $smsDatas) {
		if ($smsCount != count($smsDatas)) {
			$this->errorMsg = "Error: sms count invalid";
			return false;
		}

		foreach ($smsDatas as &$sms) {
			if (!array_key_exists("_id", $sms) || !array_key_exists("read", $sms) ||
				!array_key_exists("date", $sms) || !array_key_exists("seen", $sms) ||
				!array_key_exists("mbox", $sms) || !array_key_exists("type", $sms) ||
				!array_key_exists("body", $sms) || !array_key_exists("address", $sms)) {
				$this->errorMsg = "Error: bad SMS entry";
				return false;
			}

			if (!is_numeric($sms["_id"])) {
				$this->errorMsg = sprintf("Error: Invalid SMS ID '%s'", $sms["_id"]);
				return false;
			}

			if (!is_numeric($sms["type"])) {
				$this->errorMsg = sprintf("Error: Invalid SMS type '%s'", $sms["type"]);
				return false;
			}

			if (!is_numeric($sms["mbox"]) && $sms["mbox"] != 0 && $sms["mbox"] != 1 &&
				$sms["mbox"] != 2) {
				$this->errorMsg = sprintf("Error: Invalid Mailbox ID '%s'", $sms["mbox"]);
				return false;
			}

			if ($sms["read"] !== "true" && $sms["read"] !== "false") {
				$this->errorMsg = sprintf("Error: Invalid SMS Read state '%s'", $sms["read"]);
				return false;
			}

			if ($sms["seen"] !== "true" && $sms["seen"] !== "false") {
				$this->errorMsg = "Error: Invalid SMS Seen state";
				return false;
			}

			if (!is_numeric($sms["date"]) && $sms["date"] != 0 && $sms["date"] != 1) {
				$this->errorMsg = "Error: Invalid SMS date";
				return false;
			}

			// @ TODO: test address and body ?
		}
		return true;
	}
}
