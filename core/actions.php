<?php

function getContactCustomViews($pearDB, $contactId, $adminId) {
    $query = "SELECT cv.custom_view_id, cv.name, cvur.is_owner, cvur.locked, cvur.is_consumed, cvur.is_share, " .
            "c.contact_name, c.contact_id FROM custom_view_user_relation cvur, custom_views cv, contact c " .
            "WHERE c.contact_id = cvur.user_id AND cvur.custom_view_id = cv.custom_view_id AND c.contact_id = :user";
    
    $res = $pearDB->prepare($query);
    $res->bindParam(':user', $contactId, \PDO::PARAM_INT);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Couldn't get custom views for user: " . $contactId . ". Error is: " . $e->getMessage());
    }

    $result = [];
    while ($row = $res->fetch()) {
        if (checkAdminOwnership($pearDB, $row['custom_view_id'], $adminId)) {
            $row['already_owned'] = true;
        } else {
            $row['already_owned'] = false;
        }
        $row['name'] = htmlentities($row['name'], ENT_QUOTES, 'UTF-8');
        $result[] = $row;
    }

    return $result;
}

function checkAdminOwnership($pearDB, $customViewId, $adminId) {
    $query = "SELECT SQL_CALC_FOUND_ROWS custom_view_id FROM custom_view_user_relation " . 
        "WHERE user_id=:user_id AND custom_view_id=:cv_id AND is_owner=1";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':user_id', $adminId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not check admin ownership for custom view: " . $customViewId . 
            " and admin user: " . $adminId . ". Error message is: " . $e->getMessage());
    }

    if ($res->rowCount() > 0) {
        return true;
    }

    return false;
}

function becomeOwner($pearDB, $customViewId, $userId) {
    try {
        // we must be sure that there is a single owner
        $ownerInfo = getOwnerInfo($pearDB, $customViewId);

        if ($ownerInfo['id'] === $userId) {
            throw new Exception("You already are the owner of custom view: " . $customViewId);
        }
        $file = fopen('/var/opt/rh/rh-php72/log/php-fpm/ccvm', 'a') or die ('Unable to open file!');
        fwrite($file, print_r("\nownership\n", true));
        fwrite($file, print_r($ownerInfo, true));
        fclose($file);
        removeOwnership($pearDB, $customViewId, $ownerInfo['id']);
        addOwnership($pearDB, $customViewId, $userId);
        // update widget_preferences using widget_views id table
        duplicateWidgetParameters($pearDB, $customViewId, $userId, $ownerInfo['id']);
        // module table with info : custom_view id, old owner, new owner
        saveModifications($pearDB, $customViewId, $userId, $ownerInfo['id']);
    } catch (\Exception $e) {
        throw new Exception($e->getMessage());
    }
}

function getOwnerInfo($pearDB, $customViewId) {
    $query = "SELECT SQL_CALC_FOUND_ROWS user_id AS id FROM custom_view_user_relation WHERE custom_view_id=:id AND is_owner=1 AND locked=0";
    
    $res = $pearDB->prepare($query);
    $res->bindParam(':id', $customViewId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Cannot get owner of custom view: " . $customViewId . ". Error is: " . $e->getMessage());
    }

    // we can't handle weird custom view configuration
    if ($res->rowCount() > 1) {
        throw new Exception("There are more than one owner for custom view: " . $customViewId);
    }

    return $res->fetch();
}

function removeOwnership($pearDB, $customViewId, $userId) {
    $query = "UPDATE custom_view_user_relation SET is_owner=0, locked=1, is_share=1 " . 
        "WHERE custom_view_id=:cv_id AND user_id=$userId";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Cannot remove ownership for custom view: " . $customViewId . " with owner: " . $userId .
            ". Error message is: " . $e->getMessage());
    }
}

function addOwnership($pearDB, $customViewId, $userId) {
    $query = "INSERT INTO custom_view_user_relation VALUES (:cv_id, :user_id, NULL, 0, 1, 0, 1)";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("User: " . $userId . " cannot become owner of the custom view: " . $customViewId .
            ". Error message is: " . $e->getMessage());
    }
}

function updateOwnership($pearDB, $customViewId, $userId) {
    $query = "UPDATE custom_view_user_relation SET is_owner=1, locked=0, is_share=0 ". 
        "WHERE custom_view_id=:cv_id AND user_id=:user_id";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("User: " . $userId . " cannot become owner of the custom view: " . $customViewId .
            ". Error message is: " . $e->getMessage());
    }
}

function duplicateWidgetParameters($pearDB, $customViewId, $userId, $ownerId) {
    try {
        $widgetViews = getWidgetViews($pearDB, $customViewId);
        foreach ($widgetViews as $widgetView) {
            copyPreferences($pearDB, $widgetView['widget_view_id'], $userId, $ownerId);
        }
    } catch (\Exception $e) {
        throw new Exception($e->getMessage());
    }
}

function getWidgetViews($pearDB, $customViewId) {
    $query = "SELECT widget_view_id FROM widget_views WHERE custom_view_id = :id";

    $res = $pearDB->prepare($query);
    $res->bindParam(':id', $customViewId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not get widget_view_id for custom view: " . $customViewId .
            ". Error message is: " . $e->getMessage());
    }

    $widgetViews = [];
    $count = 0;
    while ($row = $res->fetch()) {
        $widgetViews[$count] = $row;
        $count++;
    }

    return $widgetViews;
}

function copyPreferences($pearDB, $widgetViewId, $userId, $ownerId) {
    $query = "INSERT INTO widget_preferences (widget_view_id, parameter_id, preference_value, user_id) " .
        "SELECT $widgetViewId, parameter_id, preference_value, :user_id FROM widget_preferences " . 
        "WHERE widget_view_id = $widgetViewId AND user_id = $ownerId";

    $res = $pearDB->prepare($query);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not copy widget parameters for widget view: " . $widgetViewId . 
            ". Copy from user: " . $ownerId . " to user: " . $userId . ". Error message is: " . $e->getMessage());
    }
}

function saveModifications($pearDB, $customViewId, $newOwnerId, $oldOwnerId) {
    $query = "INSERT INTO mod_ccvm_custom_view_ownership (custom_view_id, new_owner, old_owner) " .
        "VALUES (:cv_id, :new_owner, :old_owner)";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':new_owner', $newOwnerId, \PDO::PARAM_INT);
    $res->bindParam(':old_owner', $oldOwnerId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not save ownership change for custom view: " . $customViewId . 
            ".From user: " . $oldOwnerId . " to user: " . $newOwnerId . ". Error message is: " . $e->getMessage());
    }
}

function giveBackOwnership($pearDB, $customViewId, $userId) {
    $oldOwnerInfo = getOldOwner($pearDB, $customViewId, $userId);
    removeDuplicatedView($pearDB, $customViewId, $userId);
    updateOwnership($pearDB, $customViewId, $oldOwnerInfo['id']);
    removeModification($pearDB, $customViewId, $userId, $oldOwnerInfo['id']);
}

function getOldOwner($pearDB, $customViewId, $userId) {
    $query = "SELECT SQL_CALC_FOUND_ROWS old_owner as id FROM mod_ccvm_custom_view_ownership WHERE new_owner=:user_id AND custom_view_id=:cv_id";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not get old owner for custom view: " . $customViewId . 
            " with current owner: " . $userId . ". Error message is: " . $e->getMessage());
    }

    if ($res->rowCount() > 1) {
        throw new Exception("There are more than one entry for custom view: " . $customViewId . 
            " and current owner: " . $userId);
    }

    return $res->fetch(); 
}

function removeDuplicatedView($pearDB, $customViewId, $userId) {
    removeViewFromUser($pearDB, $customViewId, $userId);
    removeWidgetPreferences($pearDB, $customViewId, $userId);
}

function removeViewFromUser($pearDB, $customViewId, $userId) {
    $query = "DELETE FROM custom_view_user_relation WHERE custom_view_id=:cv_id AND user_id=:user_id";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not remove custom view: " . $customViewId . 
            " for user: " . $userId . ". Error message is: " . $e->getMessage());
    }
}

function removeWidgetPreferences($pearDB, $customViewId, $userId) {
    try {
        $widgetViews = getWidgetViews($pearDB, $customViewId);
        foreach ($widgetViews as $widgetView) {
            deletePreferences($pearDB, $widgetView['widget_view_id'], $userId);
        }
    } catch (\Exception $e) {
        throw new Exception($e->getMessage());
    }
}

function deletePreferences($pearDB, $widgetViewId, $userId) {
    $query = "DELETE FROM widget_preferences WHERE widget_view_id=$widgetViewId AND user_id=:user_id";

    $res = $pearDB->prepare($query);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not remove widget preferences for widget view: " . $widgetViewId . 
            " for user: " . $userId . ". Error message is: " . $e->getMessage());
    }
}

function removeModification($pearDB, $customViewId, $newOwnerId, $oldOwnerId) {
    $query = "DELETE FROM mod_ccvm_custom_view_ownership WHERE " .
        "custom_view_id=:cv_id AND old_owner=:old_owner AND new_owner=:new_owner";

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':old_owner', $oldOwnerId, \PDO::PARAM_INT);
    $res->bindParam(':new_owner', $newOwnerId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not remove ownership change for custom view: " . $customViewId . 
            ". Old owner: " . $oldOwnerId . ", new owner: " . $newOwnerId . ". Error message is: " . $e->getMessage());
    }
}

function getSeizedViews($pearDB, $userId) {
    $query = "SELECT cv.name, cv.custom_view_id FROM custom_views cv, mod_ccvm_custom_view_ownership ccvm " . 
        "WHERE ccvm.custom_view_id=cv.custom_view_id AND ccvm.new_owner=:user_id";
    
    $res = $pearDB->prepare($query);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not list seized custom views for user: " . $userId . 
            ". Error message is: " . $e->getMessage());
    }

    $result = [];
    while ($row = $res->fetch()) {
        $row['name'] = htmlentities($row['name'], ENT_QUOTES, 'UTF-8');
        $result[] = $row;
    }

    return $result;
}

function getSharableViews($pearDB, $userId, $targetUser) {
    $query = "SELECT ccvm.custom_view_id, :target_user AS user_id, locked, is_consumed " .
    "FROM mod_ccvm_custom_view_ownership ccvm LEFT JOIN custom_view_user_relation cvur " .
    "ON ccvm.custom_view_id = cvur.custom_view_id AND ccvm.new_owner=:user_id AND cvur.user_id=:target_user";

    $res = $pearDB->prepare($query);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);
    $res->bindParam(':target_user', $targetUser, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not list sharable custom views for owner: " . $userId . 
            " and target user: " . $targetUser . ". Error message is: " . $e->getMessage());
    }

    $result = [];
    while ($row = $res->fetch()) {
        $row['name'] = htmlentities($row['name'], ENT_QUOTES, 'UTF-8');
        $result[] = $row;
    }

    return $result;
}
