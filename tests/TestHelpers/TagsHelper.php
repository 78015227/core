<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Copyright (c) 2017 Artur Neumann artur@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace TestHelpers;

use Psr\Http\Message\ResponseInterface;
use Exception;
use SimpleXMLElement;

/**
 * Helper to administer Tags
 *
 * @author Artur Neumann <artur@jankaritech.com>
 *
 */
class TagsHelper extends \PHPUnit\Framework\Assert {
	/**
	 * tags a file
	 *
	 * @param string $baseUrl
	 * @param string $taggingUser
	 * @param string $password
	 * @param string $tagName
	 * @param string $fileName
	 * @param string $xRequestId
	 * @param string|null $fileOwner
	 * @param string|null $fileOwnerPassword
	 * @param int $davPathVersionToUse (1|2)
	 * @param string $adminUsername
	 * @param string $adminPassword
	 *
	 * @return ResponseInterface
	 * @throws \Exception
	 */
	public static function tag(
		$baseUrl,
		$taggingUser,
		$password,
		$tagName,
		$fileName,
		$xRequestId = '',
		$fileOwner = null,
		$fileOwnerPassword = null,
		$davPathVersionToUse = 2,
		$adminUsername = null,
		$adminPassword = null
	) {
		if ($fileOwner === null) {
			$fileOwner = $taggingUser;
		}

		if ($fileOwnerPassword === null) {
			$fileOwnerPassword = $password;
		}

		$fileID = WebDavHelper::getFileIdForPath(
			$baseUrl,
			$fileOwner,
			$fileOwnerPassword,
			$fileName,
			$xRequestId
		);

		try {
			$tag = self::requestTagByDisplayName(
				$baseUrl,
				$taggingUser,
				$password,
				$tagName,
				$xRequestId
			);
		} catch (Exception $e) {
			//the tag might be not accessible by the user
			//if we still want to find it, we need to try as admin
			if ($adminUsername !== null && $adminPassword !== null) {
				$tag = self::requestTagByDisplayName(
					$baseUrl,
					$adminUsername,
					$adminPassword,
					$tagName,
					$xRequestId
				);
			} else {
				throw $e;
			}
		}
		$tagID = self::getTagIdFromTagData($tag);
		$path = '/systemtags-relations/files/' . $fileID . '/' . $tagID;
		$response = WebDavHelper::makeDavRequest(
			$baseUrl,
			$taggingUser,
			$password,
			"PUT",
			$path,
			null,
			$xRequestId,
			null,
			$davPathVersionToUse,
			"systemtags"
		);
		return $response;
	}

	/**
	 * @param \SimpleXMLElement $tagData
	 *
	 * @return int
	 */
	public static function getTagIdFromTagData($tagData) {
		$tagID = $tagData->xpath(".//oc:id");
		self::assertArrayHasKey(
			0,
			$tagID,
			"cannot find id of tag"
		);

		return (int) $tagID[0]->__toString();
	}

	/**
	 * get all tags of a user
	 *
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 * @param string $xRequestId
	 * @param bool $withGroups
	 *
	 * @return SimpleXMLElement
	 */
	public static function requestTagsForUser(
		$baseUrl,
		$user,
		$password,
		$xRequestId = '',
		$withGroups = false
	) {
		$properties = [
			'oc:id',
			'oc:display-name',
			'oc:user-visible',
			'oc:user-assignable',
			'oc:can-assign'
		];
		if ($withGroups) {
			\array_push($properties, 'oc:groups');
		}
		$response = WebDavHelper::propfind(
			$baseUrl,
			$user,
			$password,
			'/systemtags/',
			$properties,
			$xRequestId,
			1,
			"systemtags"
		);
		return HttpRequestHelper::getResponseXml($response, __METHOD__);
	}

	/**
	 * find a tag by its name
	 *
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 * @param string $tagDisplayName
	 * @param string $xRequestId
	 * @param bool $withGroups
	 *
	 * @return SimpleXMLElement
	 */
	public static function requestTagByDisplayName(
		$baseUrl,
		$user,
		$password,
		$tagDisplayName,
		$xRequestId = '',
		$withGroups = false
	) {
		$tagList = self::requestTagsForUser(
			$baseUrl,
			$user,
			$password,
			$xRequestId,
			$withGroups
		);
		$tagData = $tagList->xpath(
			"//d:prop//oc:display-name[text() ='$tagDisplayName']/.."
		);
		self::assertArrayHasKey(
			0,
			$tagData,
			"cannot find 'oc:display-name' property with text '$tagDisplayName'"
		);
		return $tagData[0];
	}

	/**
	 *
	 * @param string $baseUrl see: self::makeDavRequest()
	 * @param string $user
	 * @param string $password
	 * @param string $name
	 * @param string $xRequestId
	 * @param string $userVisible "true", "1" or "false", "0"
	 * @param string $userAssignable "true", "1" or "false", "0"
	 * @param string $userEditable "true", "1" or "false", "0"
	 * @param string $groups separated by "|"
	 * @param int $davPathVersionToUse (1|2)
	 *
	 * @return ResponseInterface
	 * @link self::makeDavRequest()
	 */
	public static function createTag(
		$baseUrl,
		$user,
		$password,
		$name,
		$xRequestId = '',
		$userVisible = "true",
		$userAssignable = "true",
		$userEditable = "false",
		$groups = null,
		$davPathVersionToUse = 2
	) {
		$tagsPath = '/systemtags/';
		$body = [
			'name' => $name,
			'userVisible' => $userVisible,
			'userAssignable' => $userAssignable,
			'userEditable' => $userEditable
		];

		if ($groups !== null) {
			$body['groups'] = $groups;
		}

		return WebDavHelper::makeDavRequest(
			$baseUrl,
			$user,
			$password,
			"POST",
			$tagsPath,
			['Content-Type' => 'application/json',],
			$xRequestId,
			\json_encode($body),
			$davPathVersionToUse,
			"systemtags"
		);
	}

	/**
	 *
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 * @param int $tagID
	 * @param string $xRequestId
	 * @param int $davPathVersionToUse (1|2)
	 *
	 * @return ResponseInterface
	 */
	public static function deleteTag(
		$baseUrl,
		$user,
		$password,
		$tagID,
		$xRequestId = '',
		$davPathVersionToUse = 1
	) {
		$tagsPath = '/systemtags/' . $tagID;
		$response = WebDavHelper::makeDavRequest(
			$baseUrl,
			$user,
			$password,
			"DELETE",
			$tagsPath,
			[],
			$xRequestId,
			null,
			$davPathVersionToUse,
			"systemtags"
		);
		return $response;
	}

	/**
	 * Validate the keyword(s) used for the type of tag
	 * Tags can be "normal", "not user-assignable", "not user-visible" or "static"
	 * That determines the tag attributes which are set when creating the tag.
	 *
	 * When creating the tag, the attributes can be enabled/disabled by specifying
	 * either "true"/"false" or "1"/"0" in the request. Choose this "request style"
	 * by passing the $useTrueFalseStrings parameter.
	 *
	 * @param string $type
	 * @param boolean $useTrueFalseStrings use the strings "true"/"false" else "1"/"0"
	 *
	 * @throws \Exception
	 * @return string[]
	 */
	public static function validateTypeOfTag($type, $useTrueFalseStrings = true) {
		if ($useTrueFalseStrings) {
			$trueValue = "true";
			$falseValue = "false";
		} else {
			$trueValue = "1";
			$falseValue = "0";
		}
		$userVisible = $trueValue;
		$userAssignable = $trueValue;
		$userEditable = $trueValue;
		switch ($type) {
			case 'normal':
				break;
			case 'not user-assignable':
				$userAssignable = $falseValue;
				break;
			case 'not user-visible':
				$userVisible = $falseValue;
				break;
			case 'static':
				$userEditable = $falseValue;
				break;
			default:
				throw new \Exception('Unsupported type');
		}

		return [$userVisible, $userAssignable, $userEditable];
	}
}
