<?php
/***********************************************************
 * Copyright (C) 2019 Siemens AG
 * Author: Gaurav Mishra <mishra.gaurav@siemens.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 **********************************************************/
namespace Fossology\UI\Ajax;

use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Plugin\DefaultPlugin;
use Fossology\Lib\Auth\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Fossology\UI\Api\Helper\RestHelper;

/**
 * @class AjaxManageToken
 * @brief Class to handle ajax calls to revoke an API token
 */
class AjaxManageToken extends DefaultPlugin
{

  const NAME = "manage-token";

  /** @var DbManager $dbManager
   * DB manager to use */
  private $dbManager;

  function __construct()
  {
    parent::__construct(self::NAME,
      array(
        self::PERMISSION => Auth::PERM_WRITE
      ));
    $this->dbManager = $this->getObject('db.manager');
  }

  /**
   * @brief Revoke an active API token
   * @param Request $request
   * @return Response Status as true if token is revoked or false on failure.
   */
  protected function handle(Request $request)
  {
    $task = GetParm('task', PARM_STRING);
    $tokenId = GetParm('token-id', PARM_STRING);
    $response = null;

    list($tokenPk, $userId) = explode(".", $tokenId);
    if (Auth::getUserId() != $userId) {
      $task = "invalid";
    } else {
      $verifySql = "SELECT user_fk FROM personal_access_tokens " .
                   "WHERE pat_pk = $1 LIMIT 1;";

      $row = $this->dbManager->getSingleRow($verifySql, [$tokenPk],
        __METHOD__ . ".verifyToken");
      if (empty($row) || $row['user_fk'] != $userId) {
        $task = "invalid";
      }
    }
    switch ($task) {
      case "reveal":
        $response = new JsonResponse($this->revealToken($tokenPk,
          $request->getHost()));
        break;
      case "revoke":
        $response = new JsonResponse($this->invalidateToken($tokenPk));
        break;
      default:
        $response = new JsonResponse(["status" => false], 400);
    }
    return $response;
  }

  /**
   * Regenerate the JWT token from DB, or get the client ID.
   *
   * @param int    $tokenPk  The token id
   * @param string $hostname Host issuing the token
   * @returns array Array with success status and token.
   */
  private function revealToken($tokenPk, $hostname)
  {
    global $container;
    $restDbHelper = $container->get("helper.dbHelper");
    $authHelper = $container->get('helper.authHelper');
    $user_pk = Auth::getUserId();
    $jti = "$tokenPk.$user_pk";

    $tokenInfo = $restDbHelper->getTokenKey($tokenPk);
    if (!empty($tokenInfo['client_id'])) {
      return [
        "status" => true,
        "token" => $tokenInfo['client_id']
      ];
    }
    $tokenScope = array_search($tokenInfo['token_scope'], RestHelper::SCOPE_DB_MAP);

    $jwtToken = $authHelper->generateJwtToken($tokenInfo['expire_on'],
      $tokenInfo['created_on'], $jti, $tokenScope, $tokenInfo['token_key']);
    return array(
      "status" => true,
      "token" => $jwtToken
    );
  }

  /**
   * Mark a token as invalid/inactive.
   *
   * @param int $tokenPk  The token id to be revoked
   * @returns array Array with success status.
   */
  private function invalidateToken($tokenPk)
  {
    global $container;
    $restDbHelper = $container->get("helper.dbHelper");
    $restDbHelper->invalidateToken($tokenPk);
    return array(
      "status" => true
    );
  }
}

register_plugin(new AjaxManageToken());
