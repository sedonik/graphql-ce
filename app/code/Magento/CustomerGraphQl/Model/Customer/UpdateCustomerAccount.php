<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CustomerGraphQl\Model\Customer;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlAlreadyExistsException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthenticationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Update customer account data
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) - https://jira.corp.magento.com/browse/MC-18152
 */
class UpdateCustomerAccount
{
    /**
     * @var SaveCustomer
     */
    private $saveCustomer;

    /**
     * @var CheckCustomerPassword
     */
    private $checkCustomerPassword;

    /**
     * @var DataObjectHelper
     */
    private $dataObjectHelper;

    /**
     * @var ChangeSubscriptionStatus
     */
    private $changeSubscriptionStatus;

    /**
     * @var ValidateCustomerData
     */
    private $validateCustomerData;

    /**
     * @var array
     */
    private $restrictedKeys;

    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @param SaveCustomer $saveCustomer
     * @param CheckCustomerPassword $checkCustomerPassword
     * @param DataObjectHelper $dataObjectHelper
     * @param ChangeSubscriptionStatus $changeSubscriptionStatus
     * @param ValidateCustomerData $validateCustomerData
     * @param array $restrictedKeys
     * @param AccountManagementInterface $accountManagement
     */
    public function __construct(
        SaveCustomer $saveCustomer,
        CheckCustomerPassword $checkCustomerPassword,
        DataObjectHelper $dataObjectHelper,
        ChangeSubscriptionStatus $changeSubscriptionStatus,
        ValidateCustomerData $validateCustomerData,
        array $restrictedKeys = [],
        AccountManagementInterface $accountManagement
    ) {
        $this->saveCustomer = $saveCustomer;
        $this->checkCustomerPassword = $checkCustomerPassword;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->restrictedKeys = $restrictedKeys;
        $this->changeSubscriptionStatus = $changeSubscriptionStatus;
        $this->validateCustomerData = $validateCustomerData;
        $this->accountManagement = $accountManagement;
    }

    /**
     * Update customer account
     *
     * @param CustomerInterface $customer
     * @param array $data
     * @param StoreInterface $store
     * @return void
     * @throws GraphQlAlreadyExistsException
     * @throws GraphQlAuthenticationException
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    public function execute(CustomerInterface $customer, array $data, StoreInterface $store): void
    {
        if (isset($data['email']) && $customer->getEmail() !== $data['email']) {
            if (!isset($data['currentPassword']) || empty($data['currentPassword'])) {
                throw new GraphQlInputException(__('Provide the current "password" to change "email".'));
            }

            $this->checkCustomerPassword->execute($data['currentPassword'], (int)$customer->getId());
        }

        if (isset($data['password'])) {
            if (!isset($data['currentPassword']) || empty($data['currentPassword'])) {
                throw new GraphQlInputException(__('Provide the current "password" to change "password".'));
            }

            $this->checkCustomerPassword->execute($data['currentPassword'], (int)$customer->getId());

            try {
                $this->accountManagement->changePasswordById((int)$customer->getId(), $data['currentPassword'], $data['password']);
            } catch (LocalizedException $e) {
                throw new GraphQlInputException(__($e->getMessage()), $e);
            }
        }

        $this->validateCustomerData->execute($data);
        $filteredData = array_diff_key($data, array_flip($this->restrictedKeys));
        $this->dataObjectHelper->populateWithArray($customer, $filteredData, CustomerInterface::class);

        try {
            $customer->setStoreId($store->getId());
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()), $exception);
        }

        $this->saveCustomer->execute($customer);

        if (isset($data['is_subscribed'])) {
            $this->changeSubscriptionStatus->execute((int)$customer->getId(), (bool)$data['is_subscribed']);
        }
    }
}
