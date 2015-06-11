<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of BackBee.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * BackBee is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee. If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Charles Rouillon <charles.rouillon@lp-digital.fr>
 */

namespace BackBee\Rest\EventListener;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

use BackBee\Event\Event;
use BackBee\Rest\Controller\AbstractRestController;

/**
 * EventDispatcherListener
 *
 * Dispatch a rest event like:
 *
 * Request: 'rest.controllername.actionname.request'
 * Response: 'rest.controllername.actionname.response'
 *
 * @category    BackBee
 *
 * @author      f.kroockmann <florian.kroockmann@lp-digital.fr>
 */
class EventDispatcherListener
{

    private $eventDispatcher = null;

    public function setEventDispatcher(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * onKernelController
     *
     * Dispatch event like 'rest.controllername.actionname.request'
     * for the rest request only
     *
     * @param  FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if ($this->eventDispatcher === null) {
            throw new \InvalidArgumentException(
                'Event dispatcher not found, you must add it in "calls" argument in service file'
            );
        }

        $request = $event->getRequest();
        $controller = $event->getController();

        if (!is_array($controller)) {
            return;
        }

        if ($controller[0] instanceof AbstractRestController && isset($controller[1])) {

            $eventName = $this->buildEventName(get_class($controller[0]), $controller[1], 'request');

            if (null !== $eventName) {
                $this->eventDispatcher->dispatch($eventName, new Event($event));
            }
        }
    }

    /**
     * onKernelResponse
     *
     * Dispatch event like 'rest.controllername.actionname.response'
     * for the rest request only
     *
     * @param  FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if ($this->eventDispatcher === null) {
            throw new \InvalidArgumentException(
                'Event dispatcher not found, you must add it in "calls" argument in service file'
            );
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $controller = $request->attributes->get('_controller');
        $action = $request->attributes->get('_action');

        if (null !== $controller && null !== $action && class_exists($controller)) {
            if (new $controller() instanceof AbstractRestController) {
                $eventName = $this->buildEventName($controller, $action, 'response');

                if (null !== $eventName) {
                    $this->eventDispatcher->dispatch($eventName, new Event($event));
                }
            }
        }
    }

    /**
     * buildEventName
     *
     * Search the controller name in path like
     * 'Backbee\Rest\Controller\FooController' => 'foo'
     *
     * And search the action name like
     * 'barAction' => bar
     *
     * @param  [String] $controller
     * @param  [String] $action
     * @param  [String] $type
     * @return [String|null]
     */
    private function buildEventName($controller, $action, $type)
    {
        $eventName = null;

        preg_match("/(\w+)action$/i", $action, $actionMatches);
        preg_match("/\\\\(\w+)controller$/i", $controller, $controllerMatches);

        if (isset($actionMatches[1]) && isset($controllerMatches[1])) {
            $eventName = strtolower('rest.' . $controllerMatches[1] . '.' . $actionMatches[1] . '.' . $type);
        }

        return $eventName;
    }
}
