<?php

class Dotdigitalgroup_Email_Model_Automation extends Mage_Core_Model_Abstract
{

    const AUTOMATION_TYPE_NEW_CUSTOMER = 'customer_automation';
    const AUTOMATION_TYPE_NEW_SUBSCRIBER = 'subscriber_automation';
    const AUTOMATION_TYPE_NEW_ORDER = 'order_automation';
    const AUTOMATION_TYPE_NEW_GUEST_ORDER = 'guest_order_automation';
    const AUTOMATION_TYPE_NEW_REVIEW = 'review_automation';
    const AUTOMATION_TYPE_NEW_WISHLIST = 'wishlist_automation';
    const AUTOMATION_STATUS_PENDING = 'pending';
    //automation enrolment limit
    public $limit = 100;
    public $email;
    public $typeId;
    public $websiteId;
    public $storeName;
    public $programId;
    public $programStatus = 'Active';
    public $programMessage;
    public $automationType;

    /**
     * constructor
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('ddg_automation/automation');
    }

    /**
     * @return $this|Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $now = Mage::getSingleton('core/date')->gmtDate();
        if ($this->isObjectNew()) {
            $this->setCreatedAt($now);
        } else {
            $this->setUpdatedAt($now);
        }

        return $this;
    }

    public function enrollment()
    {
        //automation statuses to filter
        $automationCollection = $this->getCollection()
            ->addFieldToSelect('automation_type')
            ->addFieldToFilter(
                'enrolment_status', self::AUTOMATION_STATUS_PENDING
            );
        $automationCollection->getSelect()->group('automation_type');
        //active types
        $automationTypes = $automationCollection->getColumnValues(
            'automation_type'
        );
        //send the campaign by each types
        foreach ($automationTypes as $type) {
            $contacts = array();
            //reset the collection
            $automationCollection->clear();
            $automationCollection = $this->getCollection()
                ->addFieldToFilter(
                    'enrolment_status', self::AUTOMATION_STATUS_PENDING
                )
                ->addFieldToFilter('automation_type', $type);
            //limit because of the each contact request to get the id
            $automationCollection->getSelect()->limit($this->limit);
            foreach ($automationCollection as $automation) {
                $type = $automation->getAutomationType();
                //customerid, subscriberid, wishlistid..
                $email           = $automation->getEmail();
                $this->typeId    = $automation->getTypeId();
                $this->websiteId = $automation->getWebsiteId();
                $this->programId = $automation->getProgramId();
                $this->storeName = $automation->getStoreName();
                $contactId       = Mage::helper('ddg')->getContactId(
                    $email, $this->websiteId
                );
                //contact id is valid, can update datafields
                if ($contactId) {
                    //need to update datafields
                    $this->updateDatafieldsByType(
                        $this->automationType, $email
                    );
                    $contacts[$automation->getId()] = $contactId;
                } else {
                    // the contact is suppressed or the request failed
                    $automation->setEnrolmentStatus('Suppressed')->save();
                }
            }
            //only for subscribed contacts
            if ( ! empty($contacts) && $type != ''
                && $this->_checkCampignEnrolmentActive($this->programId)
            ) {
                $result = $this->sendContactsToAutomation(
                    array_values($contacts)
                );
                //check for error message
                if (isset($result->message)) {
                    $this->programStatus  = 'Failed';
                    $this->programMessage = $result->message;
                }
                //program is not active
            } elseif ($this->programMessage
                == 'Error: ERROR_PROGRAM_NOT_ACTIVE '
            ) {
                $this->programStatus = 'Deactivated';
            }
            //update contacts with the new status, and log the error message if failes
            $num = $this->getResource()->updateContacts(
                $contacts, $this->programStatus, $this->programMessage
            );
            if ($num) {
                Mage::helper('ddg')->log(
                    'Automation type : ' . $type . ', updated no : ' . $num
                );
            }
        }

    }

    /**
     * update single contact datafields for this automation type.
     *
     * @param $type
     */
    public function updateDatafieldsByType($type, $email)
    {
        switch ($type) {
            case self::AUTOMATION_TYPE_NEW_CUSTOMER :
                $this->_updateDefaultDatafields($email);
                break;
            case self::AUTOMATION_TYPE_NEW_SUBSCRIBER :
                $this->_updateDefaultDatafields($email);
                break;
            case self::AUTOMATION_TYPE_NEW_ORDER :
                $this->_updateNewOrderDatafields();
                break;
            case self::AUTOMATION_TYPE_NEW_GUEST_ORDER:
                $this->_updateNewOrderDatafields();
                break;
            case self::AUTOMATION_TYPE_NEW_REVIEW :
                $this->_updateNewOrderDatafields();
                break;
            case self::AUTOMATION_TYPE_NEW_WISHLIST:
                $this->_updateDefaultDatafields($email);
                break;
            default:
                $this->_updateDefaultDatafields($email);
                break;
        }
    }

    protected function _updateDefaultDatafields($email)
    {
        $website = Mage::app()->getWebsite($this->websiteId);
        Mage::helper('ddg')->updateDataFields(
            $email, $website, $this->storeName
        );
    }

    protected function _updateNewOrderDatafields()
    {
        $website = Mage::app()->getWebsite($this->websiteId);
        $order   = Mage::getModel('sales/order')->load($this->typeId);
        //data fields
        if ($lastOrderId = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMER_LAST_ORDER_ID
        )
        ) {
            $data[] = array(
                'Key'   => $lastOrderId,
                'Value' => $order->getId()
            );
        }
        if ($orderIncrementId = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMER_LAST_ORDER_INCREMENT_ID
        )
        ) {
            $data[] = array(
                'Key'   => $orderIncrementId,
                'Value' => $order->getIncrementId()
            );
        }
        if ($storeName = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMER_STORE_NAME
        )
        ) {
            $data[] = array(
                'Key'   => $storeName,
                'Value' => $this->storeName
            );
        }
        if ($websiteName = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMER_WEBSITE_NAME
        )
        ) {
            $data[] = array(
                'Key'   => $websiteName,
                'Value' => $website->getName()
            );
        }
        if ($lastOrderDate = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMER_LAST_ORDER_DATE
        )
        ) {
            $data[] = array(
                'Key'   => $lastOrderDate,
                'Value' => $order->getCreatedAt()
            );
        }
        if (($customerId = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMER_ID
        ))
            && $order->getCustomerId()
        ) {
            $data[] = array(
                'Key'   => $customerId,
                'Value' => $order->getCustomerId()
            );
        }
        if ( ! empty($data)) {
            //update data fields
            $client = Mage::helper('ddg')->getWebsiteApiClient($website);
            $client->updateContactDatafieldsByEmail(
                $order->getCustomerEmail(), $data
            );
        }
    }

    /**
     * Program check if is valid and active.
     *
     * @param $programId
     *
     * @return bool
     */
    protected function _checkCampignEnrolmentActive($programId)
    {
        //program is not set
        if ( ! $programId) {
            return false;
        }
        $client  = Mage::helper('ddg')->getWebsiteApiClient($this->websiteId);
        $program = $client->getProgramById($programId);
        //program status
        if (isset($program->status)) {
            $this->programStatus = $program->status;
        }
        if (isset($program->status) && $program->status == 'Active') {
            return true;
        }

        return false;
    }

    /**
     * Enrol contacts for a program.
     *
     * @param $contacts
     *
     * @return null
     */
    public function sendContactsToAutomation($contacts)
    {
        $client = Mage::helper('ddg')->getWebsiteApiClient($this->websiteId);
        $data   = array(
            'Contacts'     => $contacts,
            'ProgramId'    => $this->programId,
            'AddressBooks' => array()
        );
        //api add contact to automation enrolment
        $result = $client->postProgramsEnrolments($data);

        return $result;
    }
}