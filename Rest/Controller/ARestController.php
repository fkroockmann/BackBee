<?php

/*
 * Copyright (c) 2011-2013 Lp digital system
 * 
 * This file is part of BackBuilder5.
 *
 * BackBuilder5 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * BackBuilder5 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with BackBuilder5. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackBuilder\Rest\Controller;

use Symfony\Component\HttpFoundation\Response;

use BackBuilder\Controller\Controller,
    BackBuilder\Rest\Formatter\IFormatter;

use JMS\Serializer\SerializerBuilder;

/**
 * Abstract class for an api controller
 *
 * @category    BackBuilder
 * @package     BackBuilder\Rest
 * @copyright   Lp digital system
 * @author      k.golovin
 */
abstract class ARestController extends Controller implements IRestController, IFormatter
{
    /**
     *
     * @var \JMS\Serializer\Serializer
     */
    protected $serializer;
    
    /**
     * 
     *
     * @access public
     */
    public function optionsAction($endpoint) 
    {
        // TODO
        
        return array();
    }
    
    
    /*
     * Default formatter for a collection of objects
     * 
     * Implements BackBuilder\Rest\Formatter\IFormatter::formatCollection($collection)
     */
    public function formatCollection($collection) 
    {
        $items = array();
        
        foreach($collection as $item) {
            $items[] = $item;
        }
        
        return $this->getSerializer()->serialize($items, 'json');
    }
    
    /**
     * Serializes an object
     * 
     * Implements BackBuilder\Rest\Formatter\IFormatter::formatItem($item)
     * @param mixed $item
     * @return array
     */
    public function formatItem($item)
    {
        return $this->getSerializer()->serialize($item, 'json');
    }
    
    
    /**
     * Create a RESTful response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function createResponse($content = '', $statusCode = 200, $contentType = 'application/json')
    {
        $response = new Response($content, $statusCode);
        $response->headers->set('Content-Type', $contentType);
        
        return $response;
    }

    /**
     * 
     * @param type $message
     * @return type
     */
    protected function create404Response($message = null)
    {
        $response = $this->createResponse();
        $response->setStatusCode(404, $message);
        
        return $response;
    }
    
    /**
     * @return \JMS\Serializer\Serializer
     */
    protected function getSerializer()
    {
        if(null === $this->serializer) {
            $builder = SerializerBuilder::create();
            $builder->setAnnotationReader($this->getContainer()->get('annotation_reader'));
            $this->serializer = $builder->build();
        }
        
        return $this->serializer;
    }
}