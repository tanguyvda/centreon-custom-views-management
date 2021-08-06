<?php

require_once _CENTREON_PATH_ . '/www/modules/centreon-custom-views-management/core/actions.php';
require_once _CENTREON_PATH_ . '/www/api/class/webService.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonSession.class.php';


class CentreonCustomViewsManagement extends CentreonWebService
{
    protected $db;

    public function __construct($db = null, $centreonObj = null)
    {
        if (is_null($centreonObj)) {
            global $centreon;
            $centreonObj = $centreon;
        }

        if (is_null($db)) {
            $this->db = new CentreonDB();
        } else {
            $this->db = $db;
        }

        parent::__construct();
    }

    public function postListContactCustomViews() {
        global $centreon;
        $contactId = filter_var($this->arguments['contact_id'], FILTER_SANITIZE_NUMBER_INT);
        try {
            $result = getContactCustomViews($this->db, $contactId, $centreon->user->user_id);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }

        return $result;
    }

    public function postBecomeOwner() {

        global $centreon;
        $customViewId = filter_var($this->arguments['custom_view_id'], FILTER_SANITIZE_NUMBER_INT);
        $targetUser = filter_var($this->arguments['target_user'], FILTER_SANITIZE_NUMBER_INT);
        
        try {
            $result = becomeOwner($this->db, $customViewId, $centreon->user->user_id, $targetUser);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }

        return $result;
    }

    public function postGiveBackOwnership() {
        global $centreon;
        $customViewId = filter_var($this->arguments['custom_view_id'], FILTER_SANITIZE_NUMBER_INT);

        try {
            giveBackOwnership($this->db, $customViewId, $centreon->user->user_id);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }
    }

    public function getListSeizedViews() {
        global $centreon;
        
        try {
            $result = getSeizedViews($this->db, $centreon->user->user_id);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }

        return $result;
    }

    public function postListSharableViews() {
        global $centreon;
        $targetUser = filter_var($this->arguments['target_user'], FILTER_SANITIZE_NUMBER_INT);

        if ($targetUser === $centreon->user->user_id) {
            throw new RestBadRequestException("You cannot share your own custom views to yourself");
        }
        
        try {
            $result = getSharableViews($this->db, $centreon->user->user_id, $targetUser);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }

        return $result;
    }

    public function postAddView() {
        global $centreon;
        $targetUser = filter_var($this->arguments['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $customViewId = filter_var($this->arguments['custom_view_id'], FILTER_SANITIZE_NUMBER_INT);

        try {
            addView($this->db, $customViewId, $centreon->user->user_id, $targetUser);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }
    }

    public function postRemoveView() {
        $targetUser = filter_var($this->arguments['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $customViewId = filter_var($this->arguments['custom_view_id'], FILTER_SANITIZE_NUMBER_INT);

        try {
            removeDuplicatedView($this->db, $customViewId, $targetUser);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }
    }

    public function postLockView() {
        $targetUser = filter_var($this->arguments['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $customViewId = filter_var($this->arguments['custom_view_id'], FILTER_SANITIZE_NUMBER_INT);
        $toLock = filter_var($this->arguments['to_lock'], FILTER_SANITIZE_NUMBER_INT);

        try {
            lockView($this->db, $customViewId, $targetUser, $toLock);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }
    }

    public function postConsumeView() {
        $targetUser = filter_var($this->arguments['user_id'], FILTER_SANITIZE_NUMBER_INT);
        $customViewId = filter_var($this->arguments['custom_view_id'], FILTER_SANITIZE_NUMBER_INT);
        $toConsume = filter_var($this->arguments['to_consume'], FILTER_SANITIZE_NUMBER_INT);

        try {
            consumeView($this->db, $customViewId, $targetUser, $toConsume);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }
    }
}