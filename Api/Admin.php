<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serversync\Api;

class Admin extends \Api_Abstract
{
    public function get_hosting_servers(): array
    {
        return $this->getService()->getHostingServers();
    }

    public function get_hosting_server_accounts($data): array
    {
        $required = [
            'id' => 'The ID of the hosting server is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->getHostingServerAccounts($data['id']);
    }
}
