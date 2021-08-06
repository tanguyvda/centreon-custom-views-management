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

function becomeOwner($pearDB, $customViewId, $userId, $targetUser) {
    try {
        // we must be sure that there is a single owner
        $ownerInfo = getOwnerInfo($pearDB, $customViewId);

        if ($ownerInfo['id'] === $userId) {
            throw new Exception("You already are the owner of custom view: " . $customViewId);
        }

        $ownerExist = 1;
        if (! isset($ownerInfo[0]['id']) || $ownerInfo[0]['id'] === '') {
            $ownerExist = 0;
            $ownerInfo[0]['id'] = null;
        }

        // make sure the owner exists before removing its owner rights
        if ($ownerExist !== 0) {
            removeOwnership($pearDB, $customViewId, $ownerInfo[0]['id']);
        }

        addOwnership($pearDB, $customViewId, $userId);
        // update widget_preferences using widget_views id table
        if ($ownerExist !== 0) {
            duplicateWidgetParameters($pearDB, $customViewId, $userId, $ownerInfo[0]['id']);
        } else {
            duplicateWidgetParameters($pearDB, $customViewId, $userId, $targetUser);
        }

        // module table with info : custom_view id, old owner, new owner
        saveModifications($pearDB, $customViewId, $userId, $ownerInfo[0]['id']);
    } catch (\Exception $e) {
        throw new Exception($e->getMessage());
    }

    return $ownerInfo[0];
}

function getOwnerInfo($pearDB, $customViewId) {
    $query = "SELECT SQL_CALC_FOUND_ROWS user_id AS id, contact_name FROM custom_view_user_relation cvur " .
        "LEFT JOIN contact c ON cvur.user_id = c.contact_id WHERE custom_view_id=:id AND is_owner=1 AND locked=0";
    
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

    $result = [];
    while ($row = $res->fetch()) {
        $result[] = $row;
    }

    return $result;
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
    try {
        $oldOwnerInfo = getOldOwner($pearDB, $customViewId, $userId);

        if (! isset($oldOwnerInfo[0]['id']) || $oldOwnerInfo[0]['id'] === '') {
            removeModification($pearDB, $customViewId, $userId);
        } else {
            removeDuplicatedView($pearDB, $customViewId, $userId);
            updateOwnership($pearDB, $customViewId, $oldOwnerInfo[0]['id']);
            removeModification($pearDB, $customViewId, $userId, $oldOwnerInfo[0]['id']);
        }
    } catch (\Exception $e) {
        throw new Exception($e->getMessage());
    }
}

function getOldOwner($pearDB, $customViewId, $userId) {
    $query = "SELECT SQL_CALC_FOUND_ROWS old_owner AS id FROM mod_ccvm_custom_view_ownership WHERE new_owner=:user_id AND custom_view_id=:cv_id";

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

    $result = [];
    while ($row = $res->fetch()) {
        $result[] = $row;
    }

    return $result; 
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

function removeModification($pearDB, $customViewId, $newOwnerId, $oldOwnerId = null) {
    $query = "DELETE FROM mod_ccvm_custom_view_ownership WHERE custom_view_id=:cv_id AND new_owner=:new_owner";
        
    if ($oldOwnerId !== null) {
        $query .= " AND old_owner=:old_owner";
    }

    $res = $pearDB->prepare($query);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':new_owner', $newOwnerId, \PDO::PARAM_INT);

    if ($oldOwnerId !== null) {
        $res->bindParam(':old_owner', $oldOwnerId, \PDO::PARAM_INT);
    }

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not remove ownership change for custom view: " . $customViewId . 
            ". Old owner: " . $oldOwnerId . ", new owner: " . $newOwnerId . ". Error message is: " . $e->getMessage());
    }
}

function getSeizedViews($pearDB, $userId) {
    $query = "SELECT cv.name, cv.custom_view_id, c.contact_name FROM custom_views cv, mod_ccvm_custom_view_ownership ccvm " .
        "LEFT JOIN contact c ON c.contact_id = ccvm.old_owner " .
        "WHERE ccvm.custom_view_id=cv.custom_view_id AND ccvm.new_owner=:user_id ORDER BY cv.name DESC";
    
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
    $query = "SELECT cv.name,cvur.custom_view_id, cvur.user_id, ccvm.new_owner, :target_user AS target_user, " . 
        "cvur2.locked, cvur2.is_consumed, cvur2.is_share FROM custom_view_user_relation cvur " .
        "LEFT JOIN custom_view_user_relation cvur2 ON cvur.custom_view_id = cvur2.custom_view_id  AND cvur2.user_id=:target_user " .
        "LEFT JOIN custom_views cv ON cv.custom_view_id = cvur.custom_view_id " .
        "LEFT JOIN mod_ccvm_custom_view_ownership ccvm ON cvur.custom_view_id=ccvm.custom_view_id " .
        "WHERE cvur.user_id=:user_id AND cvur.is_owner=1 ORDER BY cv.name ASC";

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

function addView($pearDB, $customViewId, $userId, $targetUser) {
    try {
        insertCustomView($pearDB, $customViewId, $targetUser);
        duplicateWidgetParameters($pearDB, $customViewId, $targetUser, $userId);
    } catch (\Exception $e) {
        throw new Exception($e->getMessage());
    }
}

function insertCustomView($pearDB, $customViewId, $userId) {
    $query = "INSERT INTO custom_view_user_relation VALUES (:cv_id, :user_id, NULL, 1, 0, 1, 0)";

    $res = $pearDB->prepare($query);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not add custom view: " . $customViewId . 
            " to user: " . $userId . ". Error message is: " . $e->getMessage());
    }
}

function lockView($pearDB, $customViewId, $userId, $toLock) {
    $query = "UPDATE custom_view_user_relation SET locked=:to_lock WHERE user_id=:user_id AND custom_view_id=:cv_id";

    $res = $pearDB->prepare($query);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':to_lock', $toLock, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not set lock value to: " . $toLock . ". for user: " . $userId . 
            "on custom view: " . $customViewId . ". Error message is: " . $e->getMessage());
    }
}

function consumeView($pearDB, $customViewId, $userId, $toConsume) {
    $query = "UPDATE custom_view_user_relation SET is_consumed=:to_consume WHERE user_id=:user_id AND custom_view_id=:cv_id";

    $res = $pearDB->prepare($query);
    $res->bindParam(':user_id', $userId, \PDO::PARAM_INT);
    $res->bindParam(':cv_id', $customViewId, \PDO::PARAM_INT);
    $res->bindParam(':to_consume', $toConsume, \PDO::PARAM_INT);

    try {
        $res->execute();
    } catch (\PDOException $e) {
        throw new Exception("Could not set consume value to: " . $toConsume . ". for user: " . $userId . 
            "on custom view: " . $customViewId . ". Error message is: " . $e->getMessage());
    }
}