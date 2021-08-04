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
        
        try {
            $result = becomeOwner($this->db, $customViewId, $centreon->user->user_id);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }

        return $result;
    }

    public function postGiveBackOwnership() {
        global $centreon;
        $customViewId = filter_var($this->arguments['custom_view_id'], FILTER_SANITIZE_NUMBER_INT);

        try {
            $result = giveBackOwnership($this->db, $customViewId, $centreon->user->user_id);
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
        
        try {
            $result = getSharableViews($this->db, $centreon->user->user_id, $targetUser);
        } catch (\Exception $e) {
            throw new RestBadRequestException($e->getMessage());
        }

        return $result;
    }
}