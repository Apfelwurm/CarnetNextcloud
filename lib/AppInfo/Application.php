<?php

namespace OCA\Carnet\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\Carnet\Hooks\FSHooks;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\INavigationManager;
use OCP\IURLGenerator;

class Application extends App implements IBootstrap {

    public function __construct(array $urlParams = array()) {
        parent::__construct('carnet', $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Service registration is now handled by dependency injection
        // No explicit service registration needed for basic services
    }

    public function boot(IBootContext $context): void {
        $this->registerFileSystemHooks($context);
        $this->registerNavigation($context);
    }

    private function registerFileSystemHooks(IBootContext $context): void {
        $container = $context->getAppContainer();
        
        /** @var IRootFolder $root */
        $root = $container->get(IRootFolder::class);
        
        $root->listen('\OC\Files', 'postWrite', function (Node $node) use ($container) {
            $watcher = $this->createFSHooks($container);
            if ($watcher !== null) {
                $watcher->postWrite($node);
            }
        });
        
        $root->listen('\OC\Files', 'postDelete', function (Node $node) use ($container) {
            $watcher = $this->createFSHooks($container);
            if ($watcher !== null) {
                $watcher->postDelete($node);
            }
        });
    }

    private function createFSHooks($container): ?FSHooks {
        $serverContainer = $container->get('ServerContainer');
        $user = $serverContainer->getUserSession()->getUser();
        if ($user === null) {
            return null;
        }
        
        return new FSHooks(
            $serverContainer->getUserFolder(),
            $user->getUID(),
            $serverContainer->getConfig(),
            'carnet',
            $container->get(IDBConnection::class)
        );
    }

    private function registerNavigation(IBootContext $context): void {
        $container = $context->getAppContainer();
        $appName = $container->get('AppName');
        
        $container->get(INavigationManager::class)->add(
            function () use ($container, $appName) {
                $urlGenerator = $container->get(IURLGenerator::class);
                
                return [
                    'id' => $appName,
                    'order' => 2,
                    'href' => $urlGenerator->linkToRoute($appName . '.page.index'),
                    'icon' => $urlGenerator->imagePath($appName, 'app.svg'),
                    'name' => 'Carnet'
                ];
            }
        );
    }
}
