<?php

class Dotdigitalgroup_Email_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function isEnabled($website = 0)
    {
        $website = Mage::app()->getWebsite($website);

        return (bool)$website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_API_ENABLED
        );
    }

    /**
     * @param int /object $website
     *
     * @return mixed
     */
    public function getApiUsername($website = 0)
    {
        $website = Mage::app()->getWebsite($website);

        return $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_API_USERNAME
        );
    }

    public function getApiPassword($website = 0)
    {
        $website = Mage::app()->getWebsite($website);

        return Mage::helper('core')->decrypt(
            $website->getConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_API_PASSWORD
            )
        );

    }

    public function auth($authRequest)
    {
        if ($authRequest != Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_CONTENT_PASSCODE
        )
        ) {
            $this->getRaygunClient()->Send(
                'Authentication failed with code :' . $authRequest
            );

            return false;
        }

        return true;
    }

    public function getMappedCustomerId()
    {
        return Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_MAPPING_CUSTOMER_ID
        );
    }

    public function getMappedOrderId()
    {
        return Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_MAPPING_LAST_ORDER_ID
        );
    }

    public function getPasscode()
    {
        return Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_CONTENT_PASSCODE
        );
    }

    public function getLastOrderId()
    {
        return Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_MAPPING_LAST_ORDER_ID
        );

    }

    public function getLastQuoteId()
    {
        return Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_MAPPING_LAST_QUOTE_ID
        );

    }

    public function log($data, $level = Zend_Log::DEBUG, $filename = 'api.log')
    {
        if ($this->getDebugEnabled()) {
            $filename = 'connector_' . $filename;

            Mage::log($data, $level, $filename, $force = true);
        }

        return $this;
    }

    public function getDebugEnabled()
    {
        return (bool)Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_ADVANCED_DEBUG_ENABLED
        );
    }

    /**
     * Extension version number.
     *
     * @return string
     */
    public function getConnectorVersion()
    {
        $modules = (array)Mage::getConfig()->getNode('modules')->children();
        if (isset($modules['Dotdigitalgroup_Email'])) {
            $moduleName = $modules['Dotdigitalgroup_Email'];

            return (string)$moduleName->version;
        }

        return '';
    }


    public function getPageTrackingEnabled()
    {
        return (bool)Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_PAGE_TRACKING_ENABLED
        );
    }

    public function getRoiTrackingEnabled()
    {
        return (bool)Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_ROI_TRACKING_ENABLED
        );
    }

    public function getResourceAllocationEnabled()
    {
        return (bool)Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_RESOURCE_ALLOCATION
        );
    }

    public function getMappedStoreName($website)
    {
        $mapped    = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_MAPPING_CUSTOMER_STORENAME
        );
        $storeName = ($mapped) ? $mapped : '';

        return $storeName;
    }

    /**
     * Get the contact id for the custoemer based on website id.
     *
     * @param $email
     * @param $websiteId
     *
     * @return bool
     */
    public function getContactId($email, $websiteId)
    {
        $contact = Mage::getModel('ddg_automation/contact')
            ->loadByCustomerEmail($email, $websiteId);
        if ($contactId = $contact->getContactId()) {
            return $contactId;
        }

        $client   = $this->getWebsiteApiClient($websiteId);
        $response = $client->postContacts($email);

        if (isset($response->message)) {
            return false;
        }
        //save contact id
        if (isset($response->id)) {
            $contact->setContactId($response->id)
                ->save();
        }

        return $response->id;
    }

    public function getCustomerAddressBook($website)
    {
        $website = Mage::app()->getWebsite($website);

        return $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMERS_ADDRESS_BOOK_ID
        );
    }

    public function getSubscriberAddressBook($website)
    {
        $website = Mage::app()->getWebsite($website);

        return $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_SUBSCRIBERS_ADDRESS_BOOK_ID
        );
    }

    public function getGuestAddressBook($website)
    {
        $website = Mage::app()->getWebsite($website);

        return $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_GUEST_ADDRESS_BOOK_ID
        );
    }

    /**
     * @return $this
     */
    public function allowResourceFullExecution()
    {
        if ($this->getResourceAllocationEnabled()) {

            /* it may be needed to set maximum execution time of the script to longer,
             * like 60 minutes than usual */
            set_time_limit(7200);

            /* and memory to 512 megabytes */
            ini_set('memory_limit', '512M');
        }

        return $this;
    }

    public function convert($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');

        return round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' '
        . $unit[$i];
    }

    /**
     * @return string
     */
    public function getStringWebsiteApiAccounts()
    {
        $accounts = array();
        foreach (Mage::app()->getWebsites() as $website) {
            $websiteId              = $website->getId();
            $apiUsername            = $this->getApiUsername($website);
            $accounts[$apiUsername] = $apiUsername . ', websiteId: '
                . $websiteId . ' name ' . $website->getName();
        }

        return implode('</br>', $accounts);
    }

    /**
     * @param int $website
     *
     * @return array|mixed
     * @throws Mage_Core_Exception
     */
    public function getCustomAttributes($website = 0)
    {
        $website = Mage::app()->getWebsite($website);
        $attr    = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_MAPPING_CUSTOM_DATAFIELDS
        );

        if ( ! $attr) {
            return array();
        }

        return unserialize($attr);
    }


    /**
     * Enterprise custom datafields attributes.
     *
     * @param int $website
     *
     * @return array
     * @throws Mage_Core_Exception
     */
    public function getEnterpriseAttributes($website = 0)
    {
        $website = Mage::app()->getWebsite($website);
        $result  = array();
        $attrs   = $website->getConfig(
            'connector_data_mapping/enterprise_data'
        );

        if (is_array($attrs)) {
            //get individual mapped keys
            foreach ($attrs as $key => $one) {
                $config = $website->getConfig(
                    'connector_data_mapping/enterprise_data/' . $key
                );
                //check for the mapped field
                if ($config) {
                    $result[$key] = $config;
                }
            }
        }

        if (empty($result)) {
            return false;
        }

        return $result;
    }

    /**
     * @param                                              $path
     * @param null|string|bool|int|Mage_Core_Model_Website $websiteId
     *
     * @return mixed
     */
    public function getWebsiteConfig($path, $websiteId = 0)
    {
        $website = Mage::app()->getWebsite($websiteId);

        return $website->getConfig($path);
    }

    /**
     * Api client by website.
     *
     * @param mixed $website
     *
     * @return bool|Dotdigitalgroup_Email_Model_Apiconnector_Client
     */
    public function getWebsiteApiClient($website = 0)
    {
        if ( ! $this->isEnabled($website)) {
            return false;
        }

        if ( ! $apiUsername = $this->getApiUsername($website)
            || ! $apiPassword
                = $this->getApiPassword($website)
        ) {
            return false;
        }

        $client = Mage::getModel('ddg_automation/apiconnector_client');
        $client->setApiUsername($this->getApiUsername($website))
            ->setApiPassword($this->getApiPassword($website));

        return $client;
    }

    /**
     * Retrieve authorisation code.
     */
    public function getCode()
    {
        $adminUser = Mage::getSingleton('admin/session')->getUser();
        $code      = $adminUser->getEmailCode();

        return $code;
    }

    /**
     * Autorisation url for OAUTH.
     *
     * @return string
     */
    public function getAuthoriseUrl()
    {
        $clientId = Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CLIENT_ID
        );

        //callback uri if not set custom
        $redirectUri = $this->getRedirectUri();
        $redirectUri .= 'connector/email/callback';
        $adminUser = Mage::getSingleton('admin/session')->getUser();
        //query params
        $params = array(
            'redirect_uri'  => $redirectUri,
            'scope'         => 'Account',
            'state'         => $adminUser->getId(),
            'response_type' => 'code'
        );

        $authorizeBaseUrl = Mage::helper('ddg/config')->getAuthorizeLink();
        $url              = $authorizeBaseUrl . http_build_query($params)
            . '&client_id=' . $clientId;

        return $url;
    }

    public function getRedirectUri()
    {
        $callback = Mage::helper('ddg/config')->getCallbackUrl();

        return $callback;
    }

    /**
     * order status config value
     *
     * @param int $website
     *
     * @return mixed order status
     */
    public function getConfigSelectedStatus($website = 0)
    {
        $status = $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_SYNC_ORDER_STATUS,
            $website
        );
        if ($status) {
            return explode(',', $status);
        } else {
            return false;
        }
    }

    public function getConfigSelectedCustomOrderAttributes($website = 0)
    {
        $customAttributes = $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOM_ORDER_ATTRIBUTES,
            $website
        );
        if ($customAttributes) {
            return explode(',', $customAttributes);
        } else {
            return false;
        }
    }

    public function getConfigSelectedCustomQuoteAttributes($website = 0)
    {
        $customAttributes = $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOM_QUOTE_ATTRIBUTES,
            $website
        );
        if ($customAttributes) {
            return explode(',', $customAttributes);
        } else {
            return false;
        }
    }

    /**
     * check sweet tooth installed/active status
     *
     * @return boolean
     */
    public function isSweetToothEnabled()
    {
        return (bool)Mage::getConfig()->getModuleConfig('TBT_Rewards')->is(
            'active', 'true'
        );
    }

    /**
     * check sweet tooth installed/active status and active status
     *
     * @param Mage_Core_Model_Website $website
     *
     * @return boolean
     */
    public function isSweetToothToGo($website)
    {
        $stMappingStatus = $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_MAPPING_SWEETTOOTH_ACTIVE,
            $website
        );
        if ($stMappingStatus && $this->isSweetToothEnabled()) {
            return true;
        }

        return false;
    }

    public function setConnectorContactToReImport($customerId)
    {
        try {
            $coreResource = Mage::getSingleton('core/resource');
            $con          = $coreResource->getConnection('core_write');
            $con->update(
                $coreResource->getTableName('ddg_automation/contact'),
                array('email_imported' => new Zend_Db_Expr('null')),
                array("customer_id = ?" => $customerId)
            );
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Diff between to times.
     *
     * @param      $timeOne
     * @param null $timeTwo
     *
     * @return int
     */
    public function dateDiff($timeOne, $timeTwo = null)
    {
        if (is_null($timeTwo)) {
            $timeTwo = Mage::getModel('core/date')->date();
        }
        $timeOne = strtotime($timeOne);
        $timeTwo = strtotime($timeTwo);

        return $timeTwo - $timeOne;
    }


    /**
     * Disable website config when the request is made admin area only!
     *
     * @param $path
     *
     * @throws Mage_Core_Exception
     */
    public function disableConfigForWebsite($path)
    {
        $scopeId = 0;
        if ($website = Mage::app()->getRequest()->getParam('website')) {
            $scope   = 'websites';
            $scopeId = Mage::app()->getWebsite($website)->getId();
        } else {
            $scope = "default";
        }
        $config = Mage::getConfig();
        $config->saveConfig($path, 0, $scope, $scopeId);
        $config->cleanCache();
    }

    /**
     * number of customers with duplicate emails, emails as total number
     *
     * @return Mage_Customer_Model_Resource_Customer_Collection
     */
    public function getCustomersWithDuplicateEmails()
    {
        $customers = Mage::getModel('customer/customer')->getCollection();

        //duplicate emails
        $customers->getSelect()
            ->columns(array('emails' => 'COUNT(e.entity_id)'))
            ->group('email')
            ->having('emails > ?', 1);

        return $customers;
    }

    /**
     * Create new raygun client.
     *
     * @return bool|\Raygun4php\RaygunClient
     */
    public function getRaygunClient()
    {
        $code = Mage::getstoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_RAYGUN_APPLICATION_CODE
        );

        if ($this->raygunEnabled()) {
            //use async mode for sending.
            $async = Mage::getStoreConfigFlag(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_RAYGUN_APPLICATION_ASYNC
            );
            require_once Mage::getBaseDir('lib') . DS . 'Raygun4php' . DS
                . 'RaygunClient.php';

            return new Raygun4php\RaygunClient($code, $async);
        }

        return false;
    }

    /**
     * Raygun logs.
     *
     * @param        $message
     * @param string $filename
     * @param int    $line
     * @param array  $tags
     *
     * @return int|null
     */
    public function rayLog($message, $filename = 'apiconnector/client.php',
        $line = 1, $tags = array()
    )
    {
        //check if raygun has code enabled
        if ( ! $this->raygunEnabled()) {
            return;
        }

        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

        if (empty($tags)) {
            $tags = array(
                $baseUrl,
                Mage::getVersion()
            );
        }

        $client = $this->getRaygunClient();
        //user, firstname, lastname, email, annonim, uuid
        $client->SetUser($baseUrl, null, null, $this->getApiUsername());
        $client->SetVersion($this->getConnectorVersion());
        $client->SendError(100, $message, $filename, $line, $tags);
    }


    /**
     * check for raygun application and if enabled.
     *
     * @param int $websiteId
     *
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function raygunEnabled($websiteId = 0)
    {
        $website = Mage::app()->getWebsite($websiteId);

        return (bool)$website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_RAYGUN_APPLICATION_CODE
        );

    }

    /**
     * Generate the baseurl for the default store
     * dynamic content will be displayed
     *
     * @return string
     * @throws Mage_Core_Exception
     */
    public function generateDynamicUrl()
    {
        $website = Mage::app()->getRequest()->getParam('website', false);

        //set website url for the default store id
        $website = ($website) ? Mage::app()->getWebsite($website) : 0;

        $defaultGroup = Mage::app()->getWebsite($website)
            ->getDefaultGroup();

        if ( ! $defaultGroup) {
            return $mage = Mage::app()->getStore()->getBaseUrl(
                Mage_Core_Model_Store::URL_TYPE_WEB
            );
        }

        //base url
        $baseUrl = Mage::app()->getStore($defaultGroup->getDefaultStore())
            ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);

        return $baseUrl;

    }

    /**
     *
     *
     * @param int $store
     *
     * @return mixed
     */
    public function isNewsletterSuccessDisabled($store = 0)
    {
        return Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DISABLE_NEWSLETTER_SUCCESS,
            $store
        );
    }

    /**
     * @return bool
     */
    public function getEasyEmailCapture()
    {
        return Mage::getStoreConfigFlag(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_EMAIL_CAPTURE
        );
    }

    /**
     * @return bool
     */
    public function getEasyEmailCaptureForNewsletter()
    {
        return Mage::getStoreConfigFlag(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_EMAIL_CAPTURE_NEWSLETTER
        );
    }

    /**
     * get feefo logon config value
     *
     * @return mixed
     */
    public function getFeefoLogon()
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_REVIEWS_FEEFO_LOGON
        );
    }

    /**
     * get feefo reviews limit config value
     *
     * @return mixed
     */
    public function getFeefoReviewsPerProduct()
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_REVIEWS_FEEFO_REVIEWS
        );
    }

    /**
     * get feefo logo template config value
     *
     * @return mixed
     */
    public function getFeefoLogoTemplate()
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_REVIEWS_FEEFO_TEMPLATE
        );
    }

    /**
     * @param $website
     *
     * @return string
     */
    public function getReviewDisplayType($website)
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_CONTENT_REVIEW_DISPLAY_TYPE,
            $website
        );
    }

    /**
     * update data fields
     *
     * @param                         $email
     * @param Mage_Core_Model_Website $website
     * @param                         $storeName
     */
    public function updateDataFields($email, Mage_Core_Model_Website $website,
        $storeName
    )
    {
        $data = array();
        if ($storeName = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_CUSTOMER_STORE_NAME
        )
        ) {
            $data[] = array(
                'Key'   => $storeName,
                'Value' => $storeName
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
        if ( ! empty($data)) {
            //update data fields
            $client = $this->getWebsiteApiClient($website);
            $client->updateContactDatafieldsByEmail($email, $data);
        }
    }

    /**
     * check connector SMTP installed/active status
     *
     * @return boolean
     */
    public function isSmtpEnabled()
    {
        return (bool)Mage::getConfig()->getModuleConfig('Ddg_Transactional')
            ->is('active', 'true');
    }

    /**
     * Is magento enterprise.
     *
     * @return bool
     */
    public function isEnterprise()
    {
        return Mage::getConfig()->getModuleConfig('Enterprise_Enterprise')
        && Mage::getConfig()->getModuleConfig('Enterprise_AdminGws')
        && Mage::getConfig()->getModuleConfig('Enterprise_Checkout')
        && Mage::getConfig()->getModuleConfig('Enterprise_Customer');

    }

    /**
     * Update last quote id datafield.
     *
     * @param $quoteId
     * @param $email
     * @param $websiteId
     */
    public function updateLastQuoteId($quoteId, $email, $websiteId)
    {
        $client = $this->getWebsiteApiClient($websiteId);
        //last quote id config data mapped
        $quoteIdField = $this->getLastQuoteId();

        $data[] = array(
            'Key'   => $quoteIdField,
            'Value' => $quoteId
        );
        //update datafields for conctact
        $client->updateContactDatafieldsByEmail($email, $data);
    }

    /**
     * Remove code and disable Raygun.
     */
    public function disableRaygun()
    {
        $config = Mage::getModel('core/config');
        $config->saveConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_RAYGUN_APPLICATION_CODE,
            ''
        );
        Mage::getConfig()->cleanCache();
    }

    public function enableRaygunCode()
    {
        $curl = new Varien_Http_Adapter_Curl();
        $curl->setConfig(
            array(
                'timeout' => 2
            )
        );
        $curl->write(
            Zend_Http_Client::GET,
            Dotdigitalgroup_Email_Helper_Config::RAYGUN_API_CODE_URL, '1.0'
        );
        $data = $curl->read();

        if ($data === false) {
            return false;
        }
        $data = preg_split('/^\r?$/m', $data, 2);
        $data = trim($data[1]);
        $curl->close();

        $xml        = new SimpleXMLElement($data);
        $raygunCode = $xml->code;

        //not found
        if ( ! $raygunCode) {
            return;
        }

        $config = Mage::getModel('core/config');
        $config->saveConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_RAYGUN_APPLICATION_CODE,
            $raygunCode
        );
    }

    /**
     * Send the exception to raygun.
     *
     * @param $e Exception
     */
    public function sendRaygunException($e)
    {
        if ( ! $this->raygunEnabled()) {
            return;
        }
        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $tags    = array(
            $baseUrl,
            Mage::getVersion()
        );

        $client = $this->getRaygunClient();
        //user, firstname, lastname, email, annonim, uuid
        $client->SetUser($baseUrl, null, null, $this->getApiUsername());
        $client->SetVersion($this->getConnectorVersion());
        $client->SendException($e, $tags);
    }

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    public function getOrderSyncEnabled($websiteId = 0)
    {
        return Mage::getStoreConfigFlag(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_SYNC_ORDER_ENABLED,
            $websiteId
        );
    }

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    public function getCatalogSyncEnabled($websiteId = 0)
    {
        return Mage::getStoreConfigFlag(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_SYNC_CATALOG_ENABLED,
            $websiteId
        );
    }

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    public function getContactSyncEnabled($websiteId = 0)
    {
        return Mage::getStoreConfigFlag(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_SYNC_CONTACT_ENABLED,
            $websiteId
        );
    }

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    public function getGuestSyncEnabled($websiteId = 0)
    {
        return Mage::getStoreConfigFlag(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_SYNC_GUEST_ENABLED,
            $websiteId
        );
    }

    /**
     * @param int $websiteId
     *
     * @return bool
     */
    public function getSubscriberSyncEnabled($websiteId = 0)
    {
        return Mage::getStoreConfigFlag(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_SYNC_SUBSCRIBER_ENABLED,
            $websiteId
        );
    }

    /**
     * @return bool
     */
    public function getCronInstalled()
    {
        $lastCustomerSync = Mage::getModel('ddg_automation/cron')
            ->getLastCustomerSync();
        $timespan         = Mage::helper('ddg')->dateDiff($lastCustomerSync);

        //last customer cron was less then 15 min
        if ($timespan <= 15 * 60) {
            return true;
        }

        return false;
    }

    /**
     * Get the config id by the automation type.
     *
     * @param     $automationType
     * @param int $websiteId
     *
     * @return mixed
     */
    public function getAutomationIdByType($automationType, $websiteId = 0)
    {
        $path                 = constant(
            'Dotdigitalgroup_Email_Helper_Config::' . $automationType
        );
        $automationCampaignId = $this->getWebsiteConfig($path, $websiteId);

        return $automationCampaignId;
    }

    public function getAbandonedProductName()
    {
        return Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_ABANDONED_PRODUCT_NAME
        );

    }

    /**
     * Update last quote id datafield.
     *
     * @param $name
     * @param $email
     * @param $websiteId
     */
    public function updateAbandonedProductName($name, $email, $websiteId)
    {
        $client = $this->getWebsiteApiClient($websiteId);
        // id config data mapped
        $field = $this->getAbandonedProductName();

        if ($field) {
            $data[] = array(
                'Key'   => $field,
                'Value' => $name
            );
            //update data field for contact
            $client->updateContactDatafieldsByEmail($email, $data);
        }
    }


    /**
     * Api request response time limit that should be logged.
     *
     * @param int $websiteId
     *
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function getApiResponseTimeLimit($websiteId = 0)
    {
        $website = Mage::app()->getWebsite($websiteId);
        $limit   = $website->getConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DEBUG_API_REQUEST_LIMIT
        );

        return $limit;
    }

    /**
     * Main email for an account.
     *
     * @param int $website
     *
     * @return string
     */
    public function getAccountEmail($website = 0)
    {
        $client = $this->getWebsiteApiClient($website);
        $info   = $client->getAccountInfo();
        $email  = '';

        if ($info && isset($info->properties)) {
            $properties = $info->properties;

            foreach ($properties as $property) {

                if ($property->name == 'MainEmail') {
                    $email = $property->value;
                }
            }
        }

        return $email;
    }

    public function authIpAddress()
    {
        if ($ipString = Mage::getStoreConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_IP_RESTRICTION_ADDRESSES
        )
        ) {
            //string to array
            $ipArray = explode(',', $ipString);
            //remove white spaces
            foreach ($ipArray as $key => $ip) {
                $ipArray[$key] = preg_replace('/\s+/', '', $ip);
            }
            //ip address
            $ipAddress = Mage::helper('core/http')->getRemoteAddr();

            if (in_array($ipAddress, $ipArray)) {
                return true;
            }

            return false;
        } else {
            //empty ip list will ignore the validation
            return true;
        }
    }

    /**
     * get log file content.
     *
     * @param string $filename
     *
     * @return string
     */
    public function getLogFileContent($filename = 'connector_api.log')
    {
        $pathLogfile = Mage::getBaseDir('log') . DS . $filename;
        //tail the length file content
        $lengthBefore = 500000;

        $handle = fopen($pathLogfile, 'r');
        fseek($handle, -$lengthBefore, SEEK_END);

        if ( ! $handle) {
            return "Log file is not readable or does not exist at this moment. File path is "
            . $pathLogfile;
        }

        $contents = fread($handle, filesize($pathLogfile));

        if ( ! $contents) {
            return "Log file is not readable or does not exist at this moment. File path is "
            . $pathLogfile;
        }
        fclose($handle);

        return $contents;
    }


    /**
     * PRODUCT REVIEW REMINDER.
     */
    public function isReviewReminderEnabled($website)
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_REVIEWS_ENABLED,
            $website
        );
    }

    /**
     * @param $website
     *
     * @return string
     */
    public function getReviewReminderOrderStatus($website)
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_AUTOMATION_REVIEW_STATUS,
            $website
        );
    }

    /**
     * @param $website
     *
     * @return int
     */
    public function getReviewReminderDelay($website)
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_AUTOMATION_REVIEW_DELAY,
            $website
        );
    }

    /**
     * @param $website
     *
     * @return int
     */
    public function getReviewReminderCampaign($website)
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_AUTOMATION_REVIEW_CAMPAIGN,
            $website
        );
    }

    /**
     * @param $website
     *
     * @return string
     */
    public function getReviewReminderAnchor($website)
    {
        return $this->getWebsiteConfig(
            Dotdigitalgroup_Email_Helper_Config::XML_PATH_AUTOMATION_REVIEW_ANCHOR,
            $website
        );
    }


    public function getDynamicStyles()
    {
        return $dynamicStyle = array(
            'nameStyle'          => explode(
                ',', Mage::getStoreConfig(
                    Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_NAME_STYLE
                )
            ),
            'priceStyle'         => explode(
                ',', Mage::getStoreConfig(
                    Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_PRICE_STYLE
                )
            ),
            'linkStyle'          => explode(
                ',', Mage::getStoreConfig(
                    Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_LINK_STYLE
                )
            ),
            'otherStyle'         => explode(
                ',', Mage::getStoreConfig(
                    Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_OTHER_STYLE
                )
            ),
            'nameColor'          => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_NAME_COLOR
            ),
            'fontSize'           => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_NAME_FONT_SIZE
            ),
            'priceColor'         => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_PRICE_COLOR
            ),
            'priceFontSize'      => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_PRICE_FONT_SIZE
            ),
            'urlColor'           => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_LINK_COLOR
            ),
            'urlFontSize'        => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_LINK_FONT_SIZE
            ),
            'otherColor'         => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_OTHER_COLOR
            ),
            'otherFontSize'      => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_OTHER_FONT_SIZE
            ),
            'docFont'            => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_DOC_FONT
            ),
            'docBackgroundColor' => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_DOC_BG_COLOR
            ),
            'dynamicStyling'     => Mage::getStoreConfig(
                Dotdigitalgroup_Email_Helper_Config::XML_PATH_CONNECTOR_DYNAMIC_STYLING
            )
        );
    }
}