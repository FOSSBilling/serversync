<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serversync;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;
    const KEYS = ['username'];
    const CLIENT_KEYS = ['email'];
    const ORDER_KEYS = ['status'];

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getHostingServers(): array
    {
        $service = $this->di['mod_service']('servicehosting');
        $serverPairs = $service->getServerPairs();

        $servers = [];
        foreach ($serverPairs as $id => $name) {
            $manager = $this->getHostingServerManager($this->getHostingServerById($id));
            $servers[] = [
                'id' => $id,
                'name' => $name,
                'manager' => [
                    'label' => $manager->getForm()['label'],
                    'supports_sync' => $this->hostingServerManagerSupportsSync($manager),
                ],
            ];
        }

        return $servers;
    }

    public function getHostingServerAccounts($id): array
    {
        $serverManager = $this->getHostingServerManager($this->getHostingServerById($id));
        
        if (!$this->hostingServerManagerSupportsSync($serverManager)) {
            throw new \Box_Exception('This server manager does not support synchronizing accounts');
        }

        $serverAccounts = $serverManager->listAccounts();

        return $this->getMatchingHostingAccounts($id, $serverAccounts);
    }

    private function getMatchingHostingAccounts($serverId, $serverAccounts): array
    {
        $hostingAccounts = [];

        foreach ($serverAccounts as $serverAccount) {
            $fossbillingHostingAccountModel = $this->di['db']->findOne('ServiceHosting', 'service_hosting_server_id = ? AND username = ?', [$serverId, $serverAccount['username']]);
            $hostingService = $this->di['mod_service']('servicehosting');

            // Kind of messy, but it seems like the only way for now
            $fossbillingHostingAccount = $fossbillingHostingAccountModel ? $hostingService->toApiArray($fossbillingHostingAccountModel) : null;
            $fossbillingOrderModel = $this->di['db']->findOne('ClientOrder', 'service_type = :service_type AND service_id = :service_id', ['service_type' => 'hosting', 'service_id' => $fossbillingHostingAccountModel->id]);
            if ($fossbillingHostingAccount) {
                $fossbillingHostingAccount['order'] = $fossbillingOrderModel ? $this->di['mod_service']('order')->toApiArray($fossbillingOrderModel) : null;
                $fossbillingHostingAccount['client'] = $fossbillingHostingAccountModel->client;    
            }
            
            $hostingAccounts[] = [
                'server' => $serverAccount,
                'fossbilling' => $fossbillingHostingAccount,
                'suggested_actions' => $this->suggestActions([
                    'server' => $serverAccount,
                    'fossbilling' => $fossbillingHostingAccount,
                ]),
            ];
        }

        // Order the accounts by username on the hosting server
        usort($hostingAccounts, function ($a, $b) {
            return strcmp($a['server']['username'], $b['server']['username']);
        });

        return $hostingAccounts;
    }

    public function suggestActions($hostingAccount): array
    {
        $fossbillingHostingAccount = $hostingAccount['fossbilling'];
        $serverAccount = $hostingAccount['server'];

        $suggested = [];

        if ($fossbillingHostingAccount === null) {
            $client = $this->di['db']->findOne('Client', 'email = ?', [$serverAccount['email']]);
            if ($client) {
                $suggested[] = [
                    'id' => 'create',
                    'label' => 'Link to: ' . $serverAccount['email'],
                ];
            } else {
                $suggested[] = [
                    'id' => 'create',
                    'label' => 'Create a client and link to it',
                ];
            }
            return $suggested; // No need to check for anything else
        }

        if ($fossbillingHostingAccount['order']['status'] === \Model_ClientOrder::STATUS_SUSPENDED && $serverAccount['status'] === \Model_ClientOrder::STATUS_ACTIVE) {
            $suggested[] = [
                'id' => 'unsuspend',
                'label' => 'Unsuspend the account',
            ];
        }

        if ($fossbillingHostingAccount['order']['status'] === \Model_ClientOrder::STATUS_ACTIVE && $serverAccount['status'] === \Model_ClientOrder::STATUS_SUSPENDED) {
            $suggested[] = [
                'id' => 'suspend',
                'label' => 'Suspend the account',
            ];
        }

        return $suggested;
    }

    private function getHostingServerById($id): object
    {
        $serverModel = $this->di['db']->getExistingModelById('ServiceHostingServer', $id, 'Server not found');

        return $serverModel;
    }

    private function getHostingServerManager($serverModel): object
    {
        $service = $this->di['mod_service']('servicehosting');
        $serverManager = $service->getServerManager($serverModel);

        return $serverManager;
    }

    private function hostingServerManagerSupportsSync($serverManager): bool
    {
        return method_exists($serverManager, 'listAccounts');
    }
}
