<?php

namespace BackBuilder\AutoLoader;

use BackBuilder\BBApplication,
    BackBuilder\Exception\BBException,
    BackBuilder\Event\Dispatcher,
    BackBuilder\Logging\Logger,
    BackBuilder\Stream\ClassWrapper\Exception\ClassWrapperException;

if (false === defined('NAMESPACE_SEPARATOR')) {
    define('NAMESPACE_SEPARATOR', '\\');
}

/**
 * AutoLoader implements an autoloader for BackBuilder5
 *
 * It allows to load classes that:
 *
 * * use the standards for namespaces and class names
 * * throw defined wrappers returning php code
 *
 * Classes from namespace part can be looked for in a list
 * of wrappers and/or a list of locations.
 *
 * Beware of the auloloader begins to look for class throw the
 * defined wrappers then in the provided locations
 *
 * Example usage:
 *
 *     $autoloader = new \BackBuilder\AutoLoader\AutoLoader();
 *
 *     // register classes throw wrappers
 *     $autoloader->registerStreamWrapper('BackBuilder\ClassContent',
 *                                        'bb.class',
 *                                        '\BackBuilder\Stream\ClassWrapper\YamlWrapper');
 *
 *     // register classes by namespaces
 *     $autoloader->registerNamespace('BackBuilder', __DIR__)
 *                ->registerNamespace('Symfony', __DIR__.DIRECTORY_SEPARATOR.'vendor');
 *
 *     // activate the auloloader
 *     $autoloader->register();
 *
 * @category    BackBuilder
 * @package     BackBuilder\AutoLoader
 * @copyright   Lp digital system
 * @author      c.rouillon
 */
class AutoLoader
{

    /**
     * Current BackBuilder application
     * @var \BackBuilder\BBApplication
     */
    private $_application;

    /**
     * Availables wrappers to resolve loading
     * @var array
     */
    private $_availableWrappers;

    /**
     * Extensions to include searching file
     * @var array
     */
    private $_includeExtensions = array('.php');

    /**
     * Namespaces locations
     * @var array
     */
    private $_namespaces;

    /**
     * Namespaces wrappers
     * @var array
     */
    private $_streamWrappers;

    /**
     * Is the namespace registered ?
     * @var Boolean
     */
    private $_registeredNamespace = false;

    /**
     *  Events disptacher
     * @var \BackBuilder\Event\Dispatcher 
     */
    private $_dispatcher;

    /**
     * Application logger
     * @var \BackBuilder\Logging\Logger 
     */
    private $_logger;

    /**
     * Class constructor
     * @param \BackBuilder\BBApplication $application Optionnal BackBuilder Application
     * @param \BackBuilder\Event\Dispatcher $dispatcher Optionnal events dispatcher
     * @param \BackBuilder\Logging\Logger $logger Optionnal logging engine
     */
    public function __construct(BBApplication $application = null, Dispatcher $dispatcher = null, Logger $logger = null)
    {
        $this->_availableWrappers = stream_get_wrappers();

        $this->setApplication($application)
                ->setEventDispatcher($dispatcher)
                ->setLogger($logger);
    }

    /**
     * Sets the Backbuilder Application
     * @codeCoverageIgnore
     * @param \BackBuilder\BBApplication $application
     * @return \BackBuilder\AutoLoader\AutoLoader The current autoloader
     */
    public function setApplication(BBApplication $application = null)
    {
        $this->_application = $application;
        return $this;
    }

    /**
     * Sets the events dispatcher
     * @codeCoverageIgnore
     * @param \BackBuilder\Event\Dispatcher $dispatcher
     * @return \BackBuilder\AutoLoader\AutoLoader The current autoloader
     */
    public function setEventDispatcher(Dispatcher $dispatcher = null)
    {
        $this->_dispatcher = $dispatcher;
        return $this;
    }

    /**
     * Sets the logger engine
     * @codeCoverageIgnore
     * @param \BackBuilder\Logging\Logger $logger
     * @return \BackBuilder\AutoLoader\AutoLoader The current autoloader
     */
    public function setLogger(Logger $logger = null)
    {
        $this->_logger = $logger;
        return $this;
    }

    /**
     * Looks for class definition throw declared wrappers according to the namespace
     * @param string $namespace the namespace's class
     * @param string $classname the class name looked for
     * @return Boolean TRUE if the class is found FALSE else
     * @throws \BackBuilder\Stream\ClassWrapper\Exception\ClassWrapperException Occurs when the wrapper can not build PHP code
     * @throws \BackBuilder\AutoLoader\Exception\SyntaxErrorException Occurs when the generated PHP code is not valid
     */
    private function _autoloadThrowWrappers($namespace, $classname)
    {
        if (false === is_array($this->_streamWrappers)) {
            return false;
        }

        foreach ($this->_streamWrappers as $n => $wrappers) {
            if (0 === strpos($namespace, $n)) {
                $classpath = str_replace(array($n, NAMESPACE_SEPARATOR), array('', DIRECTORY_SEPARATOR), $namespace);
                if (DIRECTORY_SEPARATOR == substr($classpath, 0, 1)) {
                    $classpath = substr($classpath, 1);
                }

                foreach ($wrappers as $wrapper) {
                    try {
                        @include(sprintf('%s://%s/%s', $wrapper['protocol'], $classpath, $classname));
                        return true;
                    } catch (ClassWrapperException $e) {
                        // The class wrapper cannot return a valid class
                        throw $e;
                    } catch (\RuntimeException $e) {
                        // The include php file is not valid
                        throw new Exception\SyntaxErrorException($e->getMessage(), null, $e->getPrevious());
                    } catch (BBException $e) {
                        // Nothing to do
                    }
                }

                $this->_registeredNamespace = true;
            }
        }

        return false;
    }

    /**
     * Looks for class definition using the PHP 5.3 standards to the namespace
     * @param string $namespace the namespace's class
     * @param string $classname the class name looked for
     * @return Boolean TRUE if the class is found FALSE else
     * @throws \BackBuilder\AutoLoader\Exception\SyntaxErrorException Occurs when the found PHP code is not valid
     */
    private function _autoloadThrowFilesystem($namespace, $classname)
    {
        if (false === is_array($this->_namespaces)) {
            return false;
        }

        $pathfiles = array();
        if (false === is_array($this->_includeExtensions)
                || 0 == count($this->_includeExtensions)) {
            $pathfiles[] = str_replace(NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR . $classname;
        } else {
            foreach ($this->_includeExtensions as $ext) {
                $pathfiles[] = str_replace(NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR . $classname . $ext;
            }
        }

        foreach ($this->_namespaces as $n => $paths) {
            if (strpos($namespace, $n) === 0) {
                foreach ($paths as $path) {
                    foreach ($pathfiles as $pathfile) {
                        $filename = $path . DIRECTORY_SEPARATOR . $pathfile;
                        if (false === file_exists($filename)) {
                            $filename = $path . DIRECTORY_SEPARATOR . str_replace(str_replace(NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $n), '', $pathfile);
                        }

                        if (true === file_exists($filename) && true === is_readable($filename)) {
                            try {
                                include($filename);
                                return true;
                            } catch (\RuntimeException $e) {
                                // The include php file is not valid
                                throw new Exception\SyntaxErrorException($e->getMessage(), null, $e->getPrevious());
                            }
                        }
                    }
                }

                $this->_registeredNamespace = true;
            }
        }
        return false;
    }

    /**
     * Returns the namespace and the class name to be found
     * @param string $classpath the absolute class name
     * @return array array($namespace, $classname)
     * @throws \BackBuilder\AutoLoader\Exception\InvalidNamespaceException Occurs when the namespace is not valid
     * @throws \BackBuilder\AutoLoader\Exception\InvalidClassnameException Occurs when the class name is not valid
     */
    private function _normalizeClassname($classpath)
    {
        if (NAMESPACE_SEPARATOR == substr($classpath, 0, 1)) {
            $classpath = substr($classpath, 1);
        }

        if (false !== ($pos = strrpos($classpath, NAMESPACE_SEPARATOR))) {
            $namespace = substr($classpath, 0, $pos);
            $classname = substr($classpath, $pos + 1);
        } else {
            $namespace = '';
            $classname = $classpath;
        }

        if (false === preg_match("/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/", $namespace)) {
            throw new Exception\InvalidNamespaceException(sprintf('Invalid namespace provided: %s.', $namespace));
        }

        if (false === preg_match("/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/", $classname)) {
            throw new Exception\InvalidClassnameException(sprintf('Invalid class name provided: %s.', $classname));
        }

        return array($namespace, $classname);
    }

    /**
     * Registers pre-defined stream wrappers
     */
    private function _registerStreams()
    {
        foreach ($this->_streamWrappers as $wrappers) {
            foreach ($wrappers as $wrapper) {
                if (false === in_array($wrapper['protocol'], $this->_availableWrappers)) {
                    stream_wrapper_register($wrapper['protocol'], $wrapper['classname']);
                    $this->_availableWrappers = stream_get_wrappers();
                }
            }
        }
    }

    /**
     * Looks for the class name, call back function for spl_autolad_register()
     * First using the defined wrappers then throw filesystem
     * @param string $classpath
     * @throws \BackBuilder\AutoLoader\Exception\ClassNotFoundException Occurs when the class can not be found
     */
    public function autoload($classpath)
    {
        $this->_registeredNamespace = false;

        list($namespace, $classname) = $this->_normalizeClassname($classpath);

        if ($this->_autoloadThrowWrappers($namespace, $classname) || $this->_autoloadThrowFilesystem($namespace, $classname)) {
            if (NAMESPACE_SEPARATOR == substr($classpath, 0, 1)) {
                $classpath = substr($classpath, 1);
            }

            if (null !== $this->getEventDispatcher() && is_subclass_of($classpath, 'BackBuilder\ClassContent\AClassContent')) {
                $this->getEventDispatcher()->triggerEvent('include', new $classpath());
            }

            return;
        }

        if (true === $this->_registeredNamespace) {
            throw new Exception\ClassNotFoundException(sprintf('Class %s%s%s not found.', $namespace, NAMESPACE_SEPARATOR, $classname));
        }
    }

    /**
     * Returns the current BackBuilder application if defined, NULL otherwise
     * @codeCoverageIgnore
     * @return \BackBuilder\BBApplication | NULL
     */
    public function getApplication()
    {
        return $this->_application;
    }

    /**
     * Returns the events dispatcher if defined, NULL otherwise
     * @codeCoverageIgnore
     * @return \BackBuilder\Event\Dispatcher | NULL
     */
    public function getEventDispatcher()
    {
        return $this->_dispatcher;
    }

    /**
     * Returns the logging engine if defined, NULL otherwise
     * @codeCoverageIgnore
     * @return \BackBuilder\Logging\Logger | NULL
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * Returns the wrappers registered for provided namespaces and protocols
     * @param string|array $namespace The namespaces to look for
     * @param string|array $protocol The protocols to use
     * @return array An array of wrappers registered for these namespaces and protocols
     */
    public function getStreamWrapperClassname($namespace, $protocol)
    {
        $namespace = (array) $namespace;
        $protocol = (array) $protocol;

        $result = array();
        foreach ($this->_streamWrappers as $ns => $wrappers) {
            if (true === in_array($ns, $namespace)) {
                foreach ($wrappers as $wrapper) {
                    if (true === in_array($wrapper['protocol'], $protocol)) {
                        $result[] = $wrapper['classname'];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Returns AClassContent whom classname matches the provided pattern
     * @param string $pattern The pattern to test
     * @return array|FALSE An array of classnames matching the pattern of FALSE if none found
     */
    public function glob($pattern)
    {
        // $pattern = 'Media'.DIRECTORY_SEPARATOR.'*'
        $wrappers = $this->getStreamWrapperClassname('BackBuilder\ClassContent', 'bb.class');
        if (0 == count($wrappers)) {
            return false;
        }

        $classnames = array();
        foreach ($wrappers as $classname) {
            $wrapper = new $classname();
            if (false !== $matchingclass = $wrapper->glob($pattern)) {
                $classnames = array_merge($classnames, $matchingclass);
            }
        }

        if (0 == count($classnames))
            return false;

        return array_unique($classnames);
    }

    /**
     * Registers this auloloader
     * @param Boolean $throw [optional] This parameter specifies whether 
     *                       spl_autoload_register should throw exceptions 
     *                       when the autoload_function cannot be registered.
     * @param Boolean $prepend [optional] If TRUE, spl_autoload_register will 
     *                         prepend the autoloader on the autoload stack 
     *                         instead of appending it.
     * @return \BackBuilder\AutoLoader\AutoLoader The current instance of the autoloader class
     */
    public function register($throw = true, $prepend = false)
    {
        spl_autoload_register(array($this, 'autoload'), $throw, $prepend);
        return $this;
    }

    /**
     * Registers namespace parts to look for class name
     * @param string $namespace The namespace
     * @param string|array $paths One or an array of associated locations
     * @return \BackBuilder\AutoLoader\AutoLoader The current instance of the autoloader class
     */
    public function registerNamespace($namespace, $paths)
    {
        if (false === isset($this->_namespaces[$namespace])) {
            $this->_namespaces[$namespace] = array();
        }

        $this->_namespaces[$namespace] = array_merge($this->_namespaces[$namespace], (array) $paths);

        return $this;
    }

    /**
     * Registers listeners namespace parts to look for class name
     * @param string $path
     * @return \BackBuilder\AutoLoader\AutoLoader The current instance of the autoloader class
     */
    public function registerListenerNamespace($path)
    {
        if (false === isset($this->_namespaces['BackBuilder\Event\Listener'])) {
            $this->_namespaces['BackBuilder\Event\Listener'] = array();
        }

        array_unshift($this->_namespaces['BackBuilder\Event\Listener'], $path);

        return $this;
    }

    /**
     * Registers stream wrappers
     * @param string $namespace The namespace
     * @param string $protocol The wrapper's protocol
     * @param string $classname The class name implementing the wrapper
     * @return \BackBuilder\AutoLoader\AutoLoader The current instance of the autoloader class
     */
    public function registerStreamWrapper($namespace, $protocol, $classname)
    {
        if (!isset($this->_namespaces[$namespace])) {
            $this->_namespaces[$namespace] = array();
        }

        $this->_streamWrappers[$namespace][] = array('protocol' => $protocol, 'classname' => $classname);
        ksort($this->_streamWrappers);

        $this->_registerStreams();

        return $this;
    }

}