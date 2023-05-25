<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Serversync\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function fetchNavigation(): array
    {
        return [
            'subpages' => [
                [
                    'location' => 'system',
                    'index' => 160,
                    'label' => __trans('Server sync'),
                    'uri' => $this->di['url']->adminLink('serversync'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app): void
    {
        $app->get('/serversync', 'get_wizard_index', [], static::class);
        $app->get('/serversync/review/:id', 'get_wizard_review', ['id' => '[0-9]+'], static::class);
    }

    public function get_wizard_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_serversync_index', [
            'progress' => '25',
            'display_disclaimers' => true,
            'container_size' => 'container-tight',
        ]);
    }

    public function get_wizard_review(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_serversync_review', [
            'progress' => '50',
            'server_id' => $id,
            'go_back_url' => $this->di['url']->adminLink('serversync'),
        ]);
    }
}
