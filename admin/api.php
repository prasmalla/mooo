<?php
include "./functions.php";
if (!isset($_SESSION['hash'])) { exit; }

if ($rAdminSettings["local_api"]) {
    $rAPI = "http://127.0.0.1:".$rServers[$_INFO["server_id"]]["http_broadcast_port"]."/api.php";
} else {
    $rAPI = "http://".$rServers[$_INFO["server_id"]]["server_ip"].":".$rServers[$_INFO["server_id"]]["http_broadcast_port"]."/api.php";
}

if (isset($_GET["action"])) {
    if ($_GET["action"] == "stream") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rStreamID = intval($_GET["stream_id"]);
        $rServerID = intval($_GET["server_id"]);
        $rSub = $_GET["sub"];
        if (in_array($rSub, Array("start", "stop"))) {
            $rURL = $rAPI."?action=stream&sub=".$rSub."&stream_ids[]=".$rStreamID."&servers[]=".$rServerID;
            echo file_get_contents($rURL);exit;
        } else if ($rSub == "restart") {
            if (json_decode(file_get_contents($rAPI."?action=stream&sub=start&stream_ids[]=".$rStreamID."&servers[]=".$rServerID), True)["result"]) {
                echo json_encode(Array("result" => True));exit;
            }
            echo json_encode(Array("result" => False));exit;
        } else if ($rSub == "delete") {
            $db->query("DELETE FROM `streams_sys` WHERE `stream_id` = ".$db->real_escape_string($rStreamID)." AND `server_id` = ".$db->real_escape_string($rServerID).";");
            $result = $db->query("SELECT COUNT(`server_stream_id`) AS `count` FROM `streams_sys` WHERE `stream_id` = ".$db->real_escape_string($rStreamID).";");
            if ($result->fetch_assoc()["count"] == 0) {
                $db->query("DELETE FROM `streams` WHERE `id` = ".$db->real_escape_string($rStreamID).";");
            }
            scanBouquets();
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "movie") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rStreamID = intval($_GET["stream_id"]);
        $rServerID = intval($_GET["server_id"]);
        $rSub = $_GET["sub"];
        if (in_array($rSub, Array("start", "stop"))) {
            $rURL = $rAPI."?action=vod&sub=".$rSub."&stream_ids[]=".$rStreamID."&servers[]=".$rServerID;
            echo file_get_contents($rURL);exit;
        } else if ($rSub == "delete") {
            $db->query("DELETE FROM `streams_sys` WHERE `stream_id` = ".$db->real_escape_string($rStreamID)." AND `server_id` = ".$db->real_escape_string($rServerID).";");
            $result = $db->query("SELECT COUNT(`server_stream_id`) AS `count` FROM `streams_sys` WHERE `stream_id` = ".$db->real_escape_string($rStreamID).";");
            if ($result->fetch_assoc()["count"] == 0) {
                $db->query("DELETE FROM `streams` WHERE `id` = ".$db->real_escape_string($rStreamID).";");
                deleteMovieFile($rServerID, $rStreamID);
                scanBouquets();
            }
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "user") {
        $rUserID = intval($_GET["user_id"]);
        // Check if this user falls under the reseller or subresellers.
        if (($rPermissions["is_reseller"]) && (!hasPermissions("user", $rUserID))) {
            echo json_encode(Array("result" => False));exit;
        }
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            if ((($rPermissions["is_reseller"]) && ($rPermissions["delete_users"])) OR ($rPermissions["is_admin"])) {
                if ($rPermissions["is_reseller"]) {
                    $rUserDetails = getUser($rUserID);
                    if ($rUserDetails) {
                        if ($rUserDetails["is_mag"]) {
                            $db->query("INSERT INTO `reg_userlog`(`owner`, `username`, `password`, `date`, `type`) VALUES(".intval($rUserInfo["id"]).", '".$db->real_escape_string($rUserDetails["username"])."', '".$db->real_escape_string($rUserDetails["password"])."', ".intval(time()).", '[<b>UserPanel</b> -> <u>Delete MAG</u>]');");
                        } else if ($rUserDetails["is_e2"]) {
                            $db->query("INSERT INTO `reg_userlog`(`owner`, `username`, `password`, `date`, `type`) VALUES(".intval($rUserInfo["id"]).", '".$db->real_escape_string($rUserDetails["username"])."', '".$db->real_escape_string($rUserDetails["password"])."', ".intval(time()).", '[<b>UserPanel</b> -> <u>Delete Enigma</u>]');");
                        } else {
                            $db->query("INSERT INTO `reg_userlog`(`owner`, `username`, `password`, `date`, `type`) VALUES(".intval($rUserInfo["id"]).", '".$db->real_escape_string($rUserDetails["username"])."', '".$db->real_escape_string($rUserDetails["password"])."', ".intval(time()).", '[<b>UserPanel</b> -> <u>Delete Line</u>]');");
                        }
                    }
                }
                $db->query("DELETE FROM `users` WHERE `id` = ".$db->real_escape_string($rUserID).";");
                $db->query("DELETE FROM `user_output` WHERE `user_id` = ".$db->real_escape_string($rUserID).";");
                $db->query("DELETE FROM `enigma2_devices` WHERE `user_id` = ".$db->real_escape_string($rUserID).";");
                $db->query("DELETE FROM `mag_devices` WHERE `user_id` = ".$db->real_escape_string($rUserID).";");
                echo json_encode(Array("result" => True));exit;
            } else {
                echo json_encode(Array("result" => False));exit;
            }
        } else if ($rSub == "enable") {
            $db->query("UPDATE `users` SET `enabled` = 1 WHERE `id` = ".$db->real_escape_string($rUserID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "disable") {
            $db->query("UPDATE `users` SET `enabled` = 0 WHERE `id` = ".$db->real_escape_string($rUserID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "ban") {
            if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
            $db->query("UPDATE `users` SET `admin_enabled` = 0 WHERE `id` = ".$db->real_escape_string($rUserID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "unban") {
            if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
            $db->query("UPDATE `users` SET `admin_enabled` = 1 WHERE `id` = ".$db->real_escape_string($rUserID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "kill") {
            $rResult = $db->query("SELECT `pid`, `server_id` FROM `user_activity_now` WHERE `user_id` = ".$db->real_escape_string($rUserID).";");
            if (($rResult) && ($rResult->num_rows > 0)) {
                while ($rRow = $rResult->fetch_assoc()) {
                    sexec($rRow["server_id"], "kill -9 ".$rRow["pid"]);
                }
            }
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "user_activity") {
        $rPID = intval($_GET["pid"]);
        // Check if the user running this PID falls under the reseller or subresellers.
        if (($rPermissions["is_reseller"]) && (!hasPermissions("pid", $rPID))) {
            echo json_encode(Array("result" => False));exit;
        }
        $rSub = $_GET["sub"];
        if ($rSub == "kill") {
            $rResult = $db->query("SELECT `server_id` FROM `user_activity_now` WHERE `pid` = ".intval($rPID)." LIMIT 1;");
            if (($rResult) && ($rResult->num_rows == 1)) {
                sexec($rResult->fetch_assoc()["server_id"], "kill -9 ".$rPID);
                echo json_encode(Array("result" => True));exit;
            }
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "reg_user") {
        $rUserID = intval($_GET["user_id"]);
        // Check if this registered user falls under the reseller or subresellers.
        if (($rPermissions["is_reseller"]) && (!hasPermissions("reg_user", $rUserID))) {
            echo json_encode(Array("result" => False));exit;
        }
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            if ((($rPermissions["is_reseller"]) && ($rPermissions["delete_users"])) OR ($rPermissions["is_admin"])) {
                if ($rPermissions["is_reseller"]) {
                    $rUserDetails = getRegisteredUser($rUserID);
                    if ($rUserDetails) {
                        $db->query("INSERT INTO `reg_userlog`(`owner`, `username`, `password`, `date`, `type`) VALUES(".intval($rUserInfo["id"]).", '".$db->real_escape_string($rUserDetails["username"])."', '', ".intval(time()).", '[<b>UserPanel</b> -> <u>Delete Subreseller</u>]');");
                    }
                    $rPrevOwner = getRegisteredUser($rUserDetails["owner_id"]);
                    $rCredits = $rUserDetails["credits"];
                    $rNewCredits = $rPrevOwner["credits"] + $rCredits;
                    $db->query("UPDATE `reg_users` SET `credits` = ".$rNewCredits." WHERE `id` = ".intval($rPrevOwner["id"]).";");
                }
                $db->query("DELETE FROM `reg_users` WHERE `id` = ".$db->real_escape_string($rUserID).";");
                echo json_encode(Array("result" => True));exit;
            } else {
                echo json_encode(Array("result" => False));exit;
            }
        } else if ($rSub == "reset") {
            $db->query("UPDATE `reg_users` SET `google_2fa_sec` = '' WHERE `id` = ".$db->real_escape_string($rUserID).";");
            echo json_encode(Array("result" => True));exit;
		} else if ($rSub == "enable") {
            $db->query("UPDATE `reg_users` SET `status` = 1 WHERE `id` = ".$db->real_escape_string($rUserID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "disable") {
            $db->query("UPDATE `reg_users` SET `status` = 0 WHERE `id` = ".$db->real_escape_string($rUserID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "ticket") {
        $rTicketID = intval($_GET["ticket_id"]);
        // Check if this ticket falls under the reseller or subresellers.
        if (($rPermissions["is_reseller"]) && (!hasPermissions("ticket", $rTicketID))) {
            echo json_encode(Array("result" => False));exit;
        }
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `tickets` WHERE `id` = ".$db->real_escape_string($rTicketID).";");
            $db->query("DELETE FROM `tickets_replies` WHERE `ticket_id` = ".$db->real_escape_string($rTicketID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "close") {
            $db->query("UPDATE `tickets` SET `status` = 0 WHERE `id` = ".$db->real_escape_string($rTicketID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "reopen") {
            $db->query("UPDATE `tickets` SET `status` = 1 WHERE `id` = ".$db->real_escape_string($rTicketID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "unread") {
            $db->query("UPDATE `tickets` SET `admin_read` = 0 WHERE `id` = ".$db->real_escape_string($rTicketID).";");
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "read") {
            $db->query("UPDATE `tickets` SET `admin_read` = 1 WHERE `id` = ".$db->real_escape_string($rTicketID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "mag") {
        $rMagID = intval($_GET["mag_id"]);
        // Check if this device falls under the reseller or subresellers.
        if (($rPermissions["is_reseller"]) && (!hasPermissions("mag", $rMagID))) {
            echo json_encode(Array("result" => False));exit;
        }
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $rMagDetails = getMag($rMagID);
            if (isset($rMagDetails["user_id"])) {
                $db->query("DELETE FROM `users` WHERE `id` = ".$db->real_escape_string($rMagDetails["user_id"]).";");
                $db->query("DELETE FROM `user_output` WHERE `user_id` = ".$db->real_escape_string($rMagDetails["user_id"]).";");
            }
            $db->query("DELETE FROM `mag_devices` WHERE `mag_id` = ".$db->real_escape_string($rMagID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "mag_event") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rMagID = intval($_GET["mag_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `mag_events` WHERE `id` = ".$db->real_escape_string($rMagID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "epg") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rEPGID = intval($_GET["epg_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `epg` WHERE `id` = ".$db->real_escape_string($rEPGID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "profile") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rProfileID = intval($_GET["profile_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `transcoding_profiles` WHERE `profile_id` = ".$db->real_escape_string($rProfileID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "useragent") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rUAID = intval($_GET["ua_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `blocked_user_agents` WHERE `id` = ".$db->real_escape_string($rUAID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "ip") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rIPID = intval($_GET["ip"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $rResult = $db->query("SELECT `ip` FROM `blocked_ips` WHERE `id` = ".$db->real_escape_string($rIPID).";");
            if (($rResult) && ($rResult->num_rows > 0)) {
                foreach ($rServers as $rServer) {
                    sexec($rServer["id"], "sudo /sbin/iptables -D INPUT -s ".$rResult->fetch_assoc()["ip"]." -j DROP");
                }
            }
            $db->query("DELETE FROM `blocked_ips` WHERE `id` = ".$db->real_escape_string($rIPID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "rtmp_ip") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rIPID = intval($_GET["ip"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `rtmp_ips` WHERE `id` = ".$db->real_escape_string($rIPID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "subreseller_setup") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rID = intval($_GET["id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `subreseller_setup` WHERE `id` = ".$db->real_escape_string($rID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "enigma") {
        $rEnigmaID = intval($_GET["enigma_id"]);
        // Check if this device falls under the reseller or subresellers.
        if (($rPermissions["is_reseller"]) && (!hasPermissions("e2", $rEnigmaID))) {
            echo json_encode(Array("result" => False));exit;
        }
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $rEnigmaDetails = getEnigma($rEnigmaID);
            if (isset($rEnigmaDetails["user_id"])) {
                $db->query("DELETE FROM `users` WHERE `id` = ".$db->real_escape_string($rEnigmaDetails["user_id"]).";");
                $db->query("DELETE FROM `user_output` WHERE `user_id` = ".$db->real_escape_string($rEnigmaDetails["user_id"]).";");
            }
            $db->query("DELETE FROM `enigma2_devices` WHERE `device_id` = ".$db->real_escape_string($rEnigmaID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "server") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rServerID = intval($_GET["server_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            if ($rServers[$_GET["server_id"]]["can_delete"] == 1) {
                $db->query("DELETE FROM `streaming_servers` WHERE `id` = ".$db->real_escape_string($rServerID).";");
                $db->query("DELETE FROM `streams_sys` WHERE `server_id` = ".$db->real_escape_string($rServerID).";");
                echo json_encode(Array("result" => True));exit;
            } else {
                echo json_encode(Array("result" => False));exit;
            }
        } else if ($rSub == "kill") {
            $rResult = $db->query("SELECT `pid`, `server_id` FROM `user_activity_now` WHERE `server_id` = ".$db->real_escape_string($rServerID).";");
            if (($rResult) && ($rResult->num_rows > 0)) {
                while ($rRow = $rResult->fetch_assoc()) {
                    sexec($rRow["server_id"], "kill -9 ".$rRow["pid"]);
                }
            }
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "start") {
            $rStreamIDs = Array();
            $rResult = $db->query("SELECT `stream_id` FROM `streams_sys` WHERE `server_id` = ".intval($rServerID)." AND `on_demand` = 0;");
            if (($rResult) && ($rResult->num_rows > 0)) {
                while ($rRow = $rResult->fetch_assoc()) {
                    $rStreamIDs[] = intval($rRow["stream_id"]);
                }
            }
            if (count($rStreamIDs) > 0) {
                $rPost = Array("action" => "stream", "sub" => "start", "stream_ids" => array_values($rStreamIDs), "servers" => Array(intval($rServerID)));
                $rContext = stream_context_create(array(
                    'http' => array(
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method'  => 'POST',
                        'content' => http_build_query($rPost)
                    )
                ));
                $rResult = json_decode(file_get_contents($rAPI, false, $rContext), True);
            }
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "stop") {
            $rStreamIDs = Array();
            $rResult = $db->query("SELECT `stream_id` FROM `streams_sys` WHERE `server_id` = ".intval($rServerID)." AND `on_demand` = 0;");
            if (($rResult) && ($rResult->num_rows > 0)) {
                while ($rRow = $rResult->fetch_assoc()) {
                    $rStreamIDs[] = intval($rRow["stream_id"]);
                }
            }
            if (count($rStreamIDs) > 0) {
                $rPost = Array("action" => "stream", "sub" => "stop", "stream_ids" => array_values($rStreamIDs), "servers" => Array(intval($rServerID)));
                $rContext = stream_context_create(array(
                    'http' => array(
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method'  => 'POST',
                        'content' => http_build_query($rPost)
                    )
                ));
                $rResult = json_decode(file_get_contents($rAPI, false, $rContext), True);
            }
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "package") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rPackageID = intval($_GET["package_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `packages` WHERE `id` = ".$db->real_escape_string($rPackageID).";");
            echo json_encode(Array("result" => True));exit;
        } else if (in_array($rSub, Array("is_trial", "is_official", "can_gen_mag", "can_gen_e2", "only_mag", "only_e2"))) {
            $db->query("UPDATE `packages` SET `".$db->real_escape_string($rSub)."` = ".intval($_GET["value"])." WHERE `id` = ".$db->real_escape_string($rPackageID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "group") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rGroupID = intval($_GET["group_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `member_groups` WHERE `group_id` = ".$db->real_escape_string($rGroupID)." AND `can_delete` = 1;");
            echo json_encode(Array("result" => True));exit;
        } else if (in_array($rSub, Array("is_banned", "is_admin", "is_reseller"))) {
            $db->query("UPDATE `member_groups` SET `".$db->real_escape_string($rSub)."` = ".intval($_GET["value"])." WHERE `group_id` = ".$db->real_escape_string($rGroupID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "bouquet") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rBouquetID = intval($_GET["bouquet_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `bouquets` WHERE `id` = ".$db->real_escape_string($rBouquetID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "category") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rCategoryID = intval($_GET["category_id"]);
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $db->query("DELETE FROM `stream_categories` WHERE `id` = ".$db->real_escape_string($rCategoryID).";");
            echo json_encode(Array("result" => True));exit;
        } else {
            echo json_encode(Array("result" => False));exit;
        }
    } else if ($_GET["action"] == "get_package") {
        $rReturn = Array();
        $rOverride = json_decode($rUserInfo["override_packages"], True);
        $rResult = $db->query("SELECT `id`, `bouquets`, `official_credits` AS `cost_credits`, `official_duration`, `official_duration_in`, `max_connections`, `can_gen_mag`, `can_gen_e2`, `only_mag`, `only_e2` FROM `packages` WHERE `id` = ".intval($_GET["package_id"]).";");
        if (($rResult) && ($rResult->num_rows == 1)) {
            $rData = $rResult->fetch_assoc();
            if (isset($rOverride[$rData["id"]]["official_credits"])) {
                $rData["cost_credits"] = $rOverride[$rData["id"]]["official_credits"];
            }
            $rData["exp_date"] = date('Y-m-d', strtotime('+'.intval($rData["official_duration"]).' '.$rData["official_duration_in"]));
            if (isset($_GET["user_id"])) {
                if ($rUser = getUser($_GET["user_id"])) {
                    if(time() < $rUser["exp_date"]) {
                        $rData["exp_date"] = date('Y-m-d', strtotime('+'.intval($rData["official_duration"]).' '.$rData["official_duration_in"], $rUser["exp_date"]));
                    } else {
                        $rData["exp_date"] = date('Y-m-d', strtotime('+'.intval($rData["official_duration"]).' '.$rData["official_duration_in"]));
                    }
                }
            }
            foreach (json_decode($rData["bouquets"], True) as $rBouquet) {
                $rResult = $db->query("SELECT * FROM `bouquets` WHERE `id` = ".intval($rBouquet).";");
                if (($rResult) && ($rResult->num_rows == 1)) {
                    $rRow = $rResult->fetch_assoc();
                    $rReturn[] = Array("id" => $rRow["id"], "bouquet_name" => $rRow["bouquet_name"], "bouquet_channels" => json_decode($rRow["bouquet_channels"], True), "bouquet_series" => json_decode($rRow["bouquet_series"], True));
                }
            }
            echo json_encode(Array("result" => True, "bouquets" => $rReturn, "data" => $rData));
        } else {
            echo json_encode(Array("result" => False));
        }
        exit;
    } else if ($_GET["action"] == "get_package_trial") {
        $rReturn = Array();
        $rResult = $db->query("SELECT `bouquets`, `trial_credits` AS `cost_credits`, `trial_duration`, `trial_duration_in`, `max_connections`, `can_gen_mag`, `can_gen_e2`, `only_mag`, `only_e2` FROM `packages` WHERE `id` = ".intval($_GET["package_id"]).";");
        if (($rResult) && ($rResult->num_rows == 1)) {
            $rData = $rResult->fetch_assoc();
            $rData["exp_date"] = date('Y-m-d', strtotime('+'.intval($rData["trial_duration"]).' '.$rData["trial_duration_in"]));
            foreach (json_decode($rData["bouquets"], True) as $rBouquet) {
                $rResult = $db->query("SELECT * FROM `bouquets` WHERE `id` = ".intval($rBouquet).";");
                if (($rResult) && ($rResult->num_rows == 1)) {
                    $rRow = $rResult->fetch_assoc();
                    $rReturn[] = Array("id" => $rRow["id"], "bouquet_name" => $rRow["bouquet_name"], "bouquet_channels" => json_decode($rRow["bouquet_channels"], True), "bouquet_series" => json_decode($rRow["bouquet_series"], True));
                }
            }
            echo json_encode(Array("result" => True, "bouquets" => $rReturn, "data" => $rData));
        } else {
            echo json_encode(Array("result" => False));
        }
        exit;
    } else if ($_GET["action"] == "streams") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rData = Array();
        $rStreamIDs = json_decode($_GET["stream_ids"], True);
        $rStreams = getStreams(null, false, $rStreamIDs);
        echo json_encode(Array("result" => True, "data" => $rStreams));
        exit;
    } else if ($_GET["action"] == "stats") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $return = Array("cpu" => 0, "mem" => 0, "uptime" => "--", "total_running_streams" => 0, "bytes_sent" => 0, "bytes_received" => 0, "offline_streams" => 0, "servers" => Array());
        if (isset($_GET["server_id"])) {
            $rServerID = intval($_GET["server_id"]);
            $rWatchDog = json_decode($rServers[$rServerID]["watchdog_data"], True);
            if (is_array($rWatchDog)) {
                $return["uptime"] = $rWatchDog["uptime"];
                $return["mem"] = intval($rWatchDog["total_mem_used_percent"]);
                $return["cpu"] = intval($rWatchDog["cpu_avg"]);
                //$return["total_running_streams"] = intval(trim($rWatchDog["total_running_streams"]));
                $return["bytes_received"] = intval($rWatchDog["bytes_received"]);
                $return["bytes_sent"] = intval($rWatchDog["bytes_sent"]);
            }
            $result = $db->query("SELECT COUNT(*) AS `count` FROM `user_activity_now` WHERE `server_id` = ".$rServerID.";");
            $return["open_connections"] = $result->fetch_assoc()["count"];
            $result = $db->query("SELECT COUNT(*) AS `count` FROM `user_activity_now`;");
            $return["total_connections"] = $result->fetch_assoc()["count"];
            $result = $db->query("SELECT COUNT(`user_id`) AS `count` FROM `user_activity_now` WHERE `server_id` = ".$rServerID." GROUP BY `user_id`;");
            $return["online_users"] = $result->num_rows;
            $result = $db->query("SELECT COUNT(`user_id`) AS `count` FROM `user_activity_now` GROUP BY `user_id`;");
            $return["total_users"] = $result->num_rows;
            $result = $db->query("SELECT COUNT(*) AS `count` FROM `streams_sys` LEFT JOIN `streams` ON `streams`.`id` = `streams_sys`.`stream_id` WHERE `server_id` = ".$rServerID." AND `stream_status` <> 2 AND `type` IN (1,3);");
            $return["total_streams"] = $result->fetch_assoc()["count"];
            $result = $db->query("SELECT COUNT(*) AS `count` FROM `streams_sys` LEFT JOIN `streams` ON `streams`.`id` = `streams_sys`.`stream_id` WHERE `server_id` = ".$rServerID." AND `pid` > 0 AND `type` IN (1,3);");
            $return["total_running_streams"] = $result->fetch_assoc()["count"];
            $result = $db->query("SELECT COUNT(*) AS `count` FROM `streams_sys` LEFT JOIN `streams` ON `streams`.`id` = `streams_sys`.`stream_id` WHERE `server_id` = ".$rServerID." AND ((`streams_sys`.`monitor_pid` IS NOT NULL AND `streams_sys`.`monitor_pid` > 0) AND (`streams_sys`.`pid` IS NULL OR `streams_sys`.`pid` <= 0) AND `streams_sys`.`stream_status` <> 0);");
            $return["offline_streams"] = $result->fetch_assoc()["count"];
            $return["network_guaranteed_speed"] = $rServers[$rServerID]["network_guaranteed_speed"];
        } else {
            $rUptime = 0;
            foreach (array_keys($rServers) as $rServerID) {
                $rArray = Array();
                $result = $db->query("SELECT COUNT(*) AS `count` FROM `user_activity_now` WHERE `server_id` = ".$rServerID.";");
                $rArray["open_connections"] = $result->fetch_assoc()["count"];
                $result = $db->query("SELECT COUNT(*) AS `count` FROM `user_activity_now`;");
                $rArray["total_connections"] = $result->fetch_assoc()["count"];
                $result = $db->query("SELECT `user_id` FROM `user_activity_now` WHERE `server_id` = ".$rServerID." GROUP BY `user_id`;");
                $rArray["online_users"] = $result->num_rows;
                $result = $db->query("SELECT `user_id` FROM `user_activity_now` GROUP BY `user_id`;");
                $rArray["total_users"] = $result->num_rows;
                $result = $db->query("SELECT COUNT(*) AS `count` FROM `streams_sys` LEFT JOIN `streams` ON `streams`.`id` = `streams_sys`.`stream_id` WHERE `server_id` = ".$rServerID." AND `stream_status` <> 2 AND `type` IN (1,3);");
                $rArray["total_streams"] = $result->fetch_assoc()["count"];
                $result = $db->query("SELECT COUNT(*) AS `count` FROM `streams_sys` LEFT JOIN `streams` ON `streams`.`id` = `streams_sys`.`stream_id` WHERE `server_id` = ".$rServerID." AND `pid` > 0 AND `type` IN (1,3);");
                $rArray["total_running_streams"] = $result->fetch_assoc()["count"];
                $result = $db->query("SELECT COUNT(*) AS `count` FROM `streams_sys` LEFT JOIN `streams` ON `streams`.`id` = `streams_sys`.`stream_id` WHERE `server_id` = ".$rServerID." AND ((`streams_sys`.`monitor_pid` IS NOT NULL AND `streams_sys`.`monitor_pid` > 0) AND (`streams_sys`.`pid` IS NULL OR `streams_sys`.`pid` <= 0) AND `streams_sys`.`stream_status` <> 0);");
                $rArray["offline_streams"] = $result->fetch_assoc()["count"];
                $rArray["network_guaranteed_speed"] = $rServers[$rServerID]["network_guaranteed_speed"];
                $rWatchDog = json_decode($rServers[$rServerID]["watchdog_data"], True);
                if (is_array($rWatchDog)) {
                    $rArray["uptime"] = $rWatchDog["uptime"];
                    $rArray["mem"] = intval($rWatchDog["total_mem_used_percent"]);
                    $rArray["cpu"] = intval($rWatchDog["cpu_avg"]);
                    $rArray["bytes_received"] = intval($rWatchDog["bytes_received"]);
                    $rArray["bytes_sent"] = intval($rWatchDog["bytes_sent"]);
                }
                $rArray["server_id"] = $rServerID;
                $return["servers"][] = $rArray;
            }
            foreach ($return["servers"] as $rServerArray) {
                $return["open_connections"] += $rServerArray["open_connections"];
                $return["online_users"] += $rServerArray["online_users"];
                $return["total_streams"] += $rServerArray["total_streams"];
                $return["total_running_streams"] += $rServerArray["total_running_streams"];
                $return["offline_streams"] += $rServerArray["offline_streams"];
            }
        }
        echo json_encode($return);exit;
    } else if ($_GET["action"] == "reseller_dashboard") {
        if ($rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $return = Array("open_connections" => 0, "online_users" => 0, "active_accounts" => 0, "credits" => 0);
        $result = $db->query("SELECT `activity_id` FROM `user_activity_now` AS `a` LEFT JOIN `users` AS `u` ON `a`.`user_id` = `u`.`id` WHERE `u`.`member_id` IN (".join(",", array_keys(getRegisteredUsers($rUserInfo["id"]))).");");
        $return["open_connections"] = $result->num_rows;
        $result = $db->query("SELECT `activity_id` FROM `user_activity_now` AS `a` LEFT JOIN `users` AS `u` ON `a`.`user_id` = `u`.`id` WHERE `u`.`member_id` IN (".join(",", array_keys(getRegisteredUsers($rUserInfo["id"]))).") GROUP BY `a`.`user_id`;");
        $return["online_users"] = $result->num_rows;
        $result = $db->query("SELECT `id` FROM `users` WHERE `member_id` IN (".join(",", array_keys(getRegisteredUsers($rUserInfo["id"]))).");");
        $return["active_accounts"] = $result->num_rows;
        $return["credits"] = $rUserInfo["credits"];
        echo json_encode($return);exit;
    } else if ($_GET["action"] == "review_bouquet") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $return = Array("streams" => Array(), "vod" => Array(), "series" => Array(), "result" => true);
        if (isset($_POST["data"]["stream"])) {
            foreach ($_POST["data"]["stream"] as $rStreamID) {
                $rResult = $db->query("SELECT `id`, `stream_display_name`, `type` FROM `streams` WHERE `id` = ".intval($rStreamID).";");
                if (($rResult) && ($rResult->num_rows == 1)) {
                    $rData = $rResult->fetch_assoc();
                    if ($rData["type"] == 2) {
                        $return["vod"][] = $rData;
                    } else {
                        $return["streams"][] = $rData;
                    }
                }
            }
        }
        if (isset($_POST["data"]["series"])) {
            foreach ($_POST["data"]["series"] as $rSeriesID) {
                $rResult = $db->query("SELECT `id`, `title` FROM `series` WHERE `id` = ".intval($rSeriesID).";");
                if (($rResult) && ($rResult->num_rows == 1)) {
                    $rData = $rResult->fetch_assoc();
                    $return["series"][] = $rData;
                }
            }
        }
        echo json_encode($return);exit;
    } else if ($_GET["action"] == "userlist") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $return = Array("total_count" => 0, "items" => Array(), "result" => true);
        if (isset($_GET["search"])) {
            if (isset($_GET["page"])) {
                $rPage = intval($_GET["page"]);
            } else {
                $rPage = 1;
            }
            $rResult = $db->query("SELECT COUNT(`id`) AS `id` FROM `users` WHERE `username` LIKE '%".$db->real_escape_string($_GET["search"])."%' AND `is_e2` = 0 AND `is_mag` = 0;");
            $return["total_count"] = $rResult->fetch_assoc()["id"];
            $rResult = $db->query("SELECT `id`, `username` FROM `users` WHERE `username` LIKE '%".$db->real_escape_string($_GET["search"])."%' AND `is_e2` = 0 AND `is_mag` = 0 ORDER BY `username` ASC LIMIT ".(($rPage-1) * 100).", 100;");
            if (($rResult) && ($rResult->num_rows > 0)) {
                while ($rRow = $rResult->fetch_assoc()) {
                    $return["items"][] = Array("id" => $rRow["id"], "text" => $rRow["username"]);
                }
            }
        }
        echo json_encode($return);exit;
    } else if ($_GET["action"] == "streamlist") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $return = Array("total_count" => 0, "items" => Array(), "result" => true);
        if (isset($_GET["search"])) {
            if (isset($_GET["page"])) {
                $rPage = intval($_GET["page"]);
            } else {
                $rPage = 1;
            }
            $rResult = $db->query("SELECT COUNT(`id`) AS `id` FROM `streams` WHERE `stream_display_name` LIKE '%".$db->real_escape_string($_GET["search"])."%';");
            $return["total_count"] = $rResult->fetch_assoc()["id"];
            $rResult = $db->query("SELECT `id`, `stream_display_name` FROM `streams` WHERE `stream_display_name` LIKE '%".$db->real_escape_string($_GET["search"])."%' ORDER BY `stream_display_name` ASC LIMIT ".(($rPage-1) * 100).", 100;");
            if (($rResult) && ($rResult->num_rows > 0)) {
                while ($rRow = $rResult->fetch_assoc()) {
                    $return["items"][] = Array("id" => $rRow["id"], "text" => $rRow["stream_display_name"]);
                }
            }
        }
        echo json_encode($return);exit;
    } else if ($_GET["action"] == "force_epg") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        sexec($_INFO["server_id"], "/home/xtreamcodes/iptv_xtream_codes/php/bin/php /home/xtreamcodes/iptv_xtream_codes/crons/epg.php");
        echo json_encode(Array("result" => True));exit;
    } else if ($_GET["action"] == "sort_bouquet") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rBouquet = getBouquet($_GET["bouquet_id"]);
        $rOrdered = Array();
        if (($_GET["type"] == "stream") OR ($_GET["type"] == "movie")) {
            $rChannels = json_decode($rBouquet["bouquet_channels"], True);
            if (is_array($rChannels)) {
                if ($_GET["type"] == "stream") {
                    $result = $db->query("SELECT `streams`.`id`, `streams`.`type`, `streams`.`category_id`, `streams`.`stream_display_name`, `stream_categories`.`category_name` FROM `streams`, `stream_categories` WHERE `streams`.`type` IN (1,3) AND `streams`.`category_id` = `stream_categories`.`id` AND `streams`.`id` IN (".$db->real_escape_string(join(",", $rChannels)).") ORDER BY `streams`.`stream_display_name` ASC;");
                } else {
                    $result = $db->query("SELECT `streams`.`id`, `streams`.`type`, `streams`.`category_id`, `streams`.`stream_display_name`, `stream_categories`.`category_name` FROM `streams`, `stream_categories` WHERE `streams`.`type` = 2 AND `streams`.`category_id` = `stream_categories`.`id` AND `streams`.`id` IN (".$db->real_escape_string(join(",", $rChannels)).") ORDER BY `streams`.`stream_display_name` ASC;");
                }
                if (($result) && ($result->num_rows > 0)) {
                    while ($row = $result->fetch_assoc()) {
                        $rOrdered[] = intval($row["id"]);
                    }
                }
            }
            foreach ($rChannels as $rChannel) {
                if (!in_array(intval($rChannel), $rOrdered)) {
                    $rOrdered[] = intval($rChannel);
                }
            }
            if (count($rOrdered) > 0) {
                $db->query("UPDATE `bouquets` SET `bouquet_channels` = '".$db->real_escape_string(json_encode($rOrdered))."' WHERE `id` = ".intval($rBouquet["id"]).";");
            }
            echo json_encode(Array("result" => True));exit;
        } else {
            $rSeries = json_decode($rBouquet["bouquet_series"], True);
            if (is_array($rSeries)) {
                $result = $db->query("SELECT `series`.`id`, `series`.`category_id`, `series`.`title`, `stream_categories`.`category_name` FROM `series`, `stream_categories` WHERE `series`.`category_id` = `stream_categories`.`id` AND `series`.`id` IN (".$db->real_escape_string(join(",", $rSeries)).") ORDER BY `series`.`title` ASC;");
                if (($result) && ($result->num_rows > 0)) {
                    while ($row = $result->fetch_assoc()) {
                        $rOrdered[] = intval($row["id"]);
                    }
                }
            }
            if (count($rOrdered) > 0) {
                $db->query("UPDATE `bouquets` SET `bouquet_series` = '".$db->real_escape_string(join(",", $rOrdered))."' WHERE `id` = ".intval($rBouquet["id"]).";");
            }
            echo json_encode(Array("result" => True));exit;
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "tmdb_search") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        include "tmdb.php";
        $rTMDB = new TMDb($rSettings["tmdb_api_key"]);
        $rTerm = $_GET["term"];
        if ($_GET["type"] == "movie") {
            if (strlen($rAdminSettings["tmdb_language"]) > 0) {
                
                $rResults = $rTMDB->searchMovie($rTerm, 1, false, $rAdminSettings["tmdb_language"]);
            } else {
                $rResults = $rTMDB->searchMovie($rTerm);
            }
        } else {
        }
        if (count($rResults) > 0) {
            echo json_encode(Array("result" => True, "data" => $rResults)); exit;
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "tmdb") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        include "tmdb.php";
        $rTMDB = new TMDb($rSettings["tmdb_api_key"]);
        $rID = $_GET["id"];
        if ($_GET["type"] == "movie") {
            if (strlen($rAdminSettings["tmdb_language"]) > 0) {
                $rResult = $rTMDB->getMovie($rID, $rAdminSettings["tmdb_language"]);
                $rResult["videos"] = $rTMDB->getMovieTrailers($rID, $rAdminSettings["tmdb_language"]);
            } else {
                $rResult = $rTMDB->getMovie($rID);
                $rResult["videos"] = $rTMDB->getMovieTrailers($rID);
            }
            $rResult["cast"] = $rTMDB->getMovieCast($rID);
        }
        if ($rResult) {
            echo json_encode(Array("result" => True, "data" => $rResult)); exit;
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "listdir") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        if ($_GET["filter"] == "video") {
            $rFilter = Array("mp4", "mkv", "mov", "avi", "mpg", "mpeg", "flv", "wmv");
        } else if ($_GET["filter"] == "subs") {
            $rFilter = Array("srt", "sub", "sbv");
        } else {
            $rFilter = null;
        }
        if ((isset($_GET["server"])) && (isset($_GET["dir"]))) {
            echo json_encode(Array("result" => True, "data" => listDir(intval($_GET["server"]), $_GET["dir"], $rFilter))); exit;
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "fingerprint") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rData = json_decode($_GET["data"], true);
        $rActiveServers = Array();
        foreach ($rServers as $rServer) {
            if (((((time() - $rServer["last_check_ago"]) > 360)) OR ($rServer["status"] == 2)) AND ($rServer["can_delete"] == 1) AND ($rServer["status"] <> 3)) { $rServerError = True; } else { $rServerError = False; }
            if (($rServer["status"] == 1) && (!$rServerError)) {
                $rActiveServers[] = $rServer["id"];
            }
        }
        if (($rData["id"] > 0) && ($rData["font_size"] > 0) && (strlen($rData["font_color"]) > 0) && (strlen($rData["xy_offset"]) > 0) && ((strlen($rData["message"]) > 0) OR ($rData["type"] < 3))) {
            $result = $db->query("SELECT `user_activity_now`.`activity_id`, `user_activity_now`.`user_id`, `user_activity_now`.`server_id`, `users`.`username` FROM `user_activity_now` LEFT JOIN `users` ON `users`.`id` = `user_activity_now`.`user_id` WHERE `user_activity_now`.`container` = 'ts' AND `stream_id` = ".intval($rData["id"]).";");
            if (($result) && ($result->num_rows > 0)) {
                set_time_limit(360);
                ini_set('max_execution_time', 360);
                ini_set('default_socket_timeout', 15);
                while ($row = $result->fetch_assoc()) {
                    if (in_array($row["server_id"], $rActiveServers)) {
                        $rArray = Array("font_size" => $rData["font_size"], "font_color" => $rData["font_color"], "xy_offset" => $rData["xy_offset"], "message" => "", "activity_id" => $row["activity_id"]);
                        if ($rData["type"] == 1) {
                            $rArray["message"] = "#".$row["activity_id"];
                        } else if ($rData["type"] == 2) {
                            $rArray["message"] = $row["username"];
                        } else if ($rData["type"] == 3) {
                            $rArray["message"] = $rData["message"];
                        }
                        $rAPI = "http://".$rServers[intval($row["server_id"])]["server_ip"].":".$rServers[intval($row["server_id"])]["http_broadcast_port"]."/system_api.php?password=".urlencode($rSettings["live_streaming_pass"])."&action=signal_send&".http_build_query($rArray);
                        $rSuccess = file_get_contents($rAPI);
                    }
                }
                echo json_encode(Array("result" => True));exit;
            }
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "restart_services") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rServerID = intval($_GET["server_id"]);
        if (isset($rServers[$rServerID])) {
            $rJSON = Array("status" => 0, "port" => intval($_GET["ssh_port"]), "host" => $rServer["server_ip"], "password" => $_GET["password"], "time" => intval(time()), "id" => $rServerID, "type" => "reboot");
            file_put_contents("/home/xtreamcodes/iptv_xtream_codes/adtools/balancer/".$rServerID.".json", json_encode($rJSON));
            echo json_encode(Array("result" => True));exit;
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "map_stream") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        ini_set('default_socket_timeout', 300);
        echo shell_exec("/home/xtreamcodes/iptv_xtream_codes/bin/ffprobe -v quiet -probesize 2000000 -print_format json -show_format -show_streams \"".$_GET["stream"]."\"");exit;
    } else if ($_GET["action"] == "clear_logs") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        if (strlen($_GET["from"]) == 0) {
            $rStartTime = null;
        } else if (!$rStartTime = strtotime($_GET["from"]. " 00:00:00")) {
            echo json_encode(Array("result" => False));exit;
        }
        if (strlen($_GET["to"]) == 0) {
            $rEndTime = null;
        } else if (!$rEndTime = strtotime($_GET["to"]." 23:59:59")) {
            echo json_encode(Array("result" => False));exit;
        }
        if (in_array($_GET["type"], Array("client_logs", "stream_logs", "user_activity", "credits_log", "reg_userlog"))) {
            if ($_GET["type"] == "user_activity") {
                $rColumn = "date_start";
            } else {
                $rColumn = "date";
            }
            if (($rStartTime) && ($rEndTime)) {
                $db->query("DELETE FROM `".$db->real_escape_string($_GET["type"])."` WHERE `".$rColumn."` >= ".intval($rStartTime)." AND `".$rColumn."` <= ".intval($rEndTime).";");
            } else if ($rStartTime) {
                $db->query("DELETE FROM `".$db->real_escape_string($_GET["type"])."` WHERE `".$rColumn."` >= ".intval($rStartTime).";");
            } else if ($rEndTime) {
                $db->query("DELETE FROM `".$db->real_escape_string($_GET["type"])."` WHERE `".$rColumn."` <= ".intval($rEndTime).";");
            } else {
                $db->query("DELETE FROM `".$db->real_escape_string($_GET["type"])."`;");
            }
        }
        echo json_encode(Array("result" => True));exit;
    } else if ($_GET["action"] == "backup") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rSub = $_GET["sub"];
        if ($rSub == "delete") {
            $rBackup = pathinfo($_GET["filename"])["filename"];
            if (file_exists(MAIN_DIR."adtools/backups/".$rBackup.".sql")) {
                unlink(MAIN_DIR."adtools/backups/".$rBackup.".sql");
            }
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "restore") {
            $rBackup = pathinfo($_GET["filename"])["filename"];
            $rFilename = MAIN_DIR."adtools/backups/".$rBackup.".sql";
            $rCommand = "mysql -u ".$_INFO["db_user"]." -p".$_INFO["db_pass"]." -P ".$_INFO["db_port"]." ".$_INFO["db_name"]." < \"".$rFilename."\"";
            $rRet = shell_exec($rCommand);
            echo json_encode(Array("result" => True));exit;
        } else if ($rSub == "backup") {
            $rFilename = MAIN_DIR."adtools/backups/backup_".date("Y-m-d_H:i:s").".sql";
            $rCommand = "mysqldump -u ".$_INFO["db_user"]." -p".$_INFO["db_pass"]." -P ".$_INFO["db_port"]." ".$_INFO["db_name"]." > \"".$rFilename."\"";
            $rRet = shell_exec($rCommand);
            if (file_exists($rFilename)) {
                echo json_encode(Array("result" => True, "data" => Array("filename" => pathinfo($rFilename)["filename"].".sql", "timestamp" => filemtime($rFilename), "date" => date("Y-m-d H:i:s", filemtime($rFilename)))));exit;
            }
            echo json_encode(Array("result" => True));exit;
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "send_event") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rData = json_decode($_GET["data"], True);
        $rMag = getMag($rData["id"]);
        if ($rMag) {
            if ($rData["type"] == "send_msg") {
                $rData["need_confirm"] = 1;
            } else if ($rData["type"] == "play_channel") {
                $rData["need_confirm"] = 0;
                $rData["reboot_portal"] = 0;
                $rData["message"] = intval($rData["channel"]);
            } else {
                $rData["need_confirm"] = 0;
                $rData["reboot_portal"] = 0;
                $rData["message"] = "";
            }
            if ($db->query("INSERT INTO `mag_events`(`status`, `mag_device_id`, `event`, `need_confirm`, `msg`, `reboot_after_ok`, `send_time`) VALUES (0, ".intval($rData["id"]).", '".$db->real_escape_string($rData["type"])."', ".intval($rData["need_confirm"]).", '".$db->real_escape_string($rData["message"])."', ".intval($rData["reboot_portal"]).", ".intval(time()).");")) {
                echo json_encode(Array("result" => True));exit;
            }
        }
        echo json_encode(Array("result" => False));exit;
    } else if ($_GET["action"] == "download") {
        if (!$rPermissions["is_admin"]) { echo json_encode(Array("result" => False)); exit; }
        $rBackup = pathinfo($_GET["filename"])["filename"];
        $rFilename = MAIN_DIR."adtools/backups/".$rBackup.".sql";
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($rFilename).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($rFilename));
        readfile($rFilename);
        exit;
    }
}
echo json_encode(Array("result" => False));
?>