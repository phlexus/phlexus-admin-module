<?php declare(strict_types=1);

namespace Phlexus\Modules\BaseAdmin\Events\Listeners;

use Exception;
use Phalcon\Di\Exception as DiException;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Dispatcher\Exception as MvcDispatcherException;
use Phalcon\Plugin;
use Phlexus\Libraries\Auth\AuthException;
use Phlexus\Module\ModuleException;
use Phlexus\Module\ModuleInterface;
use Phlexus\Modules\BaseAdmin\Module as AdminModule;
use Phalcon\Application\Exception as ApplicationException;

final class DispatcherListener extends Plugin
{
    /**
     * Before forwarding is happening.
     *
     * @param Event $event Event object.
     * @param Dispatcher $dispatcher Dispatcher object.
     * @param array $forward The forward data.
     *
     * @return bool
     * @throws DiException
     * @throws Exception
     */
    public function beforeForward(Event $event, Dispatcher $dispatcher, array $forward = []): bool
    {
        if (!empty($forward['module'])) {
            $moduleName = $forward['module'];
            if (!phlexus_container('modules')->offsetExists($moduleName)) {
                throw new ModuleException("Module {$moduleName} does not exist.");
            }

            $moduleDefinition = phlexus_container('modules')->offsetGet($moduleName);
            // TODO: think about that validation, as there are atm only vendor DI declaration
            /*if (!phlexus_container()->has($moduleDefinition->className)) {
                throw new DiException(
                    "Service '{$moduleDefinition->className}' wasn't found in the dependency injection container"
                );
            }*/

            /** @var ModuleInterface $module */
            $moduleClass = phlexus_container($moduleDefinition->className);
            $dispatcher->setModuleName($moduleName);
            $dispatcher->setNamespaceName($moduleClass->getHandlersNamespace());
        }

        return $event->isStopped();
    }

    /**
     * Before exception is happening.
     *
     * @param Event $event Event object.
     * @param Dispatcher $dispatcher Dispatcher object.
     * @param Exception $exception Exception object.
     *
     * @throws Exception
     * @return bool
     */
    public function beforeException(Event $event, Dispatcher $dispatcher, Exception $exception): bool
    {
        $moduleName = AdminModule::getModuleName();
        $namespace = AdminModule::getHandlersNamespace() . '\\Controllers';

        if ($exception instanceof MvcDispatcherException) {
            $this->response->setStatusCode(404);
            $dispatcher->forward([
                'module' => $moduleName,
                'namespace' => $namespace,
                'controller' => 'errors',
                'action' => 'show404',
            ]);

            return false;
        }

        if ($exception instanceof AuthException) {
            $this->response->setStatusCode(402);
            $dispatcher->forward([
                'module' => $moduleName,
                'namespace' => $namespace,
                'controller' => 'auth',
                'action' => 'login',
            ]);

            $event->stop();
        }

        if ($exception instanceof ApplicationException) {
            $this->response->setStatusCode(500);

            $dispatcher->forward([
                'module' => $moduleName,
                'namespace' => $namespace,
                'controller' => 'errors',
                'action' => 'show500',
            ]);

            $event->stop();
        }

        return $event->isStopped();
    }
}
