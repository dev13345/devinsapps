<?php

class Customer_Mobile_Account_RegisterController extends Application_Controller_Mobile_Default
{

    public function postAction()
    {

        $application = $this->getApplication();
        $request = $this->getRequest();

        \Siberian\Hook::trigger('mobile.register', [
            'appId' => $application->getId(),
            'request' => $request
        ]);

        if ($data = Zend_Json::decode($this->getRequest()->getRawBody())) {


            $customer = new Customer_Model_Customer();

            try {

                if (empty($data["firstname"]) OR empty($data["lastname"])) {
                    throw new Exception(__("You must fill firstname and lastname fields."));
                }

                if (empty($data["privacy_policy"])) {
                    throw new Exception(__("You must agree to our privacy policy to create an account."));
                }

                if (!Zend_Validate::is($data['email'], 'EmailAddress')) {
                    throw new Exception(__('Please enter a valid email address'));
                }

                $dummy = new Customer_Model_Customer();
                $dummy->find([
                    "email" => $data['email'],
                    "app_id" => $this->getApplication()->getId(),
                ]);

                if ($dummy->getId()) {
                    throw new Exception(__("We are sorry but this address is already used."));
                }

                if (empty($data['show_in_social_gaming'])) {
                    $data['show_in_social_gaming'] = 0;
                }

                if (empty($data['password'])) {
                    throw new Exception(__('Please enter a password'));
                }

                $customer->setData($data)
                    ->setAppId($this->getApplication()->getId())
                    ->setPassword($data['password'])
                    ->save();

                // PUSH INDIVIDUAL TO USER ONLY
                Customer_Model_Customer_Push::registerForIndividualPush(
                    $customer,
                    $this->getApplication(),
                    $data['device_uid']);

                $this->getSession()->setCustomer($customer);

                $this->_sendNewAccountEmail($customer, $data['password']);

                $currentCustomer = Customer_Model_Customer::getCurrent();

                $html = [
                    'success' => 1,
                    'customer_id' => $customer->getId(),
                    'can_access_locked_features' => $customer->canAccessLockedFeatures(),
                    'token' => Zend_Session::getId(),
                    'customer' => $currentCustomer
                ];

                \Siberian\Hook::trigger('mobile.register.success', [
                    'appId' => $application->getId(),
                    'customerId' => $customer->getId(),
                    'customer' => $currentCustomer,
                    'token' => Zend_Session::getId()
                ]);

            } catch (Exception $e) {
                $html = ['error' => 1, 'message' => $e->getMessage()];

                \Siberian\Hook::trigger('mobile.register.error', [
                    'appId' => $application->getId(),
                    'message' => $e->getMessage(),
                    'type' => 'account'
                ]);
            }

            $this->_sendJson($html);
        }
    }

    protected function _sendNewAccountEmail($customer, $password)
    {
        $admin_email = null;
        $contact = new Contact_Model_Contact();
        $contact_page = $this->getApplication()->getPage('contact');

        if ($contact_page->getId()) {
            $contact->find($contact_page->getId(), 'value_id');
            $admin_email = $contact->getEmail();
        }

        $layout = $this->getLayout()->loadEmail('customer', 'create_account');
        $layout->getPartial('content_email')->setCustomer($customer)->setPassword($password)->setAdminEmail($admin_email)->setApp($this->getApplication()->getName());
        $content = $layout->render();

        # version 4.8.7 - SMTP
        $mail = new Siberian_Mail();
        $mail->setBodyHtml($content);
        $mail->addTo($customer->getEmail(), $customer->getName());
        $mail->setSubject(__('%s - Account creation', $this->getApplication()->getName()));
        $mail->send();

        return $this;

    }

}
