<?php
// 修复脚本:为 desktop 上下文创建默认 HomeTab(等价于 LoadDesktopHomeData fixture)
// 用法: php /tmp/fix_desktop_hometab.php

require __DIR__ . '/vendor/autoload.php';

use Claroline\KernelBundle\Kernel;
use Claroline\AppBundle\API\SerializerProvider;
use Claroline\HomeBundle\Entity\HomeTab;
use Claroline\HomeBundle\Entity\Type\WidgetsTab;
use Claroline\CoreBundle\Component\Context\DesktopContext;
use Claroline\InstallationBundle\Fixtures\FixtureLoader;
use Symfony\Component\HttpKernel\KernelInterface;

$env = getenv('APP_ENV') ?: 'prod';
$kernel = new Kernel($env, false);
$kernel->boot();

$container = $kernel->getContainer();

$om = $container->get('Claroline\AppBundle\Persistence\ObjectManager');
$repo = $om->getRepository(HomeTab::class);
$existing = $repo->findBy(['contextName' => DesktopContext::getName()]);

if (!empty($existing)) {
    echo "desktop home tab already exists, skip\n";
    $kernel->shutdown();
    exit(0);
}

$serializer = $container->get(SerializerProvider::class);
$translator = $container->get('translator');

$defaultTab = [
    'title' => $translator->trans('information', [], 'platform'),
    'longTitle' => $translator->trans('information', [], 'platform'),
    'slug' => 'information',
    'type' => WidgetsTab::getType(),
    'class' => WidgetsTab::class,
    'position' => 1,
    'restrictions' => [
        'hidden' => false,
    ],
    'parameters' => [
        'widgets' => [[
            'name' => $translator->trans('my_workspaces', [], 'workspace'),
            'visible' => true,
            'display' => [
                'layout' => [1],
                'color' => '#333333',
                'backgroundType' => 'color',
                'background' => '#ffffff',
            ],
            'parameters' => [],
            'contents' => [[
                'type' => 'list',
                'source' => 'my_workspaces',
                'parameters' => [
                    'display' => 'tiles',
                    'enableDisplays' => false,
                    'availableDisplays' => [],
                    'card' => [
                        'display' => [
                            'icon',
                            'flags',
                            'subtitle',
                            'description',
                            'footer',
                        ],
                    ],
                    'paginated' => true,
                    'count' => true,
                ],
            ]],
        ]],
    ],
];

$tab = new HomeTab();
$tab->setContextName(DesktopContext::getName());

$serializer->deserialize($defaultTab, $tab);

$om->persist($tab);
$om->flush();

echo "desktop home tab created: " . $tab->getUuid() . "\n";
$kernel->shutdown();
