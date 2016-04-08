<?php
/**
 * <!--
 * This file is part of the adventure php framework (APF) published under
 * http://adventure-php-framework.org.
 *
 * The APF is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The APF is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with the APF. If not, see http://www.gnu.org/licenses/lgpl-3.0.txt.
 * -->
 */
namespace APF\core\frontcontroller;

use APF\core\benchmark\BenchmarkTimer;
use APF\core\filter\InputFilterChain;
use APF\core\filter\OutputFilterChain;
use APF\core\http\mixins\GetRequestResponse;
use APF\core\http\RequestImpl;
use APF\core\http\Response;
use APF\core\http\ResponseImpl;
use APF\core\pagecontroller\APFObject;
use APF\core\registry\Registry;
use APF\core\singleton\Singleton;
use Exception;
use InvalidArgumentException;

/**
 * Implements the APF front controller. It enables the developer to execute actions
 * defined within the bootstrap file or the url to enrich a page controller application
 * with business logic.
 * <p/>
 * The controller has it's own timing model. Hence, he can be used for special jobs such
 * as image delivery or creation of the business layer components concerning the time
 * slots the actions are executed. Please refer to the documentation page for a
 * timing diagram.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 27.01.2007<br />
 * Version 0.2, 01.03.2007 (Input objects are now loaded by the front controller, too!)<br />
 * Version 0.3, 08.06.2007 (Now permanent actions defined within the bootstrap file are introduced.)<br />
 * Version 0.4, 01.07.2007 (Removed __createInputObject())<br />
 * Version 0.5. 20.08.2013 Jan Wiese (Added support for actions generated by thw DIServiceManager)<br />
 * Version 0.6, 22.01.2015 (ID#234: added support to change the request and response implementation)<br />
 */
class Frontcontroller extends APFObject {

   use GetRequestResponse;

   /**
    * Allows you to exchange the APF implementation of the Request interface with your custom one.
    * <p />
    * To do so, add something like
    * <code>
    * Frontcontroller::$requestImplClass = 'VENDOR\request\CustomRequestImpl';
    * </code>
    * to your bootstrap file. Be careful implementing all methods of APF's RequestImpl class.
    *
    * @var string The fully-qualified name of the request implementation.
    */
   public static $requestImplClass = RequestImpl::class;

   /**
    * Allows you to exchange the APF implementation of the Response interface with your custom one.
    * <p />
    * To do so, add something like
    * <code>
    * Frontcontroller::$responseImplClass = 'VENDOR\request\CustomResponseImpl';
    * </code>
    * to your bootstrap file. Be careful implementing all methods of APF's ResponseImpl class.
    *
    * @var string The fully-qualified name of the request implementation.
    */
   public static $responseImplClass = ResponseImpl::class;

   /**
    * Allows you to change the extension of action configuration the
    * Frontcontroller takes to load with the ConfigurationManager and
    * it's registered ConfigurationProvider.
    * <p/>
    * To change the extension, simple add the following lines into your
    * bootstrap file (e.g. index.php):
    * <code>
    * Frontcontroller::$configExtension = 'php';
    * </code>
    *
    * @var string Name of the config extension to use for action configurations.
    */
   public static $configExtension = 'ini';

   /**
    * The front controller's action stack.
    *
    * @var Action[] $actionStack
    */
   protected $actionStack = [];

   /**
    * @var string[][] $urlMappingsByToken The registered URL mappings for actions accessible via token.
    */
   private $urlMappingsByToken = [];

   /**
    * @var string[][] $urlMappingsByNamespaceAndName The registered URL mappings for actions accessible via namespace and name.
    */
   private $urlMappingsByNamespaceAndName = [];

   /**
    * Executes the desired actions and creates the page output.
    *
    * @return Response The content of the transformed page.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.01.2007<br />
    * Version 0.2, 27.01.2007<br />
    * Version 0.3, 31.01.2007<br />
    * Version 0.4, 03.02.2007 (Added permanent actions)<br />
    * Version 0.5, 08.06.2007 (Outsourced URL filtering to generic input filter)<br />
    * Version 0.6, 01.07.2007 (Removed permanentpre and permanentpost actions)<br />
    * Version 0.7, 29.09.2007 (Added further benchmark tags)<br />
    * Version 0.8, 21.06.2008 (Introduced Registry to retrieve URLRewrite configuration)<br />
    * Version 0.9, 13.10.2008 (Removed $URLRewriting parameter, because URL rewriting must be configured in the registry)<br />
    * Version 1.0, 11.12.2008 (Switched to the new input filter concept)<br />
    */
   public function start() {

      // Create request and response implementations for OO abstraction
      $request = $this->getRequest();
      $response = $this->getResponse();

      // apply input filter to process request
      InputFilterChain::getInstance()->filter($request);

      // execute pre page create actions (see timing model)
      $this->runActions(Action::TYPE_PRE_PAGE_CREATE);

      // execute content creating actions (see timing model)
      $this->runActions(Action::TYPE_CREATE_CONTENT);

      // apply output filter to response body
      $response->setBody(OutputFilterChain::getInstance()->filter($response->getBody()));

      // execute actions after page transformation (see timing model)
      $this->runActions(Action::TYPE_POST_TRANSFORM);

      return $response;
   }

   /**
    * Executes all actions with the given type. Possible types are
    * <ul>
    * <li>prepagecreate</li>
    * <li>create-content</li>
    * <li>posttransform</li>
    * </ul>
    *
    * @param string $type Type of the actions to execute.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.01.2007<br />
    * Version 0.2, 31.01.2007<br />
    * Version 0.3, 03.02.2007 (Added benchmarker)<br />
    * Version 0.4, 01.07.2007 (Removed debug output)<br />
    * Version 0.5, 08.11.2007<br />
    * Version 0.6, 28.03.2008 (Optimized benchmarker call)<br />
    * Version 0.7, 07.08.2010 (Added action activation indicator to disable actions on demand)<br />
    * Version 0.8, 24.03.2016 (Added allow execution indicator)<br />
    */
   protected function runActions($type = Action::TYPE_PRE_PAGE_CREATE) {

      /* @var $t BenchmarkTimer */
      $t = Singleton::getInstance(BenchmarkTimer::class);

      foreach ($this->actionStack as &$action) {

         // only execute, when the current action has a suitable type, is active, and is not "protected"
         if ($action->getType() == $type && $action->isActive() && $action->allowExecution()) {

            $id = get_class($action) . '::run()';
            $t->start($id);

            $action->run();

            $t->stop($id);
         }
      }
   }

   /**
    * Returns the action specified by the input param.
    *
    * @param string $actionName The name of the action to return.
    *
    * @return Action The desired action or null.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 05.02.2007<br />
    * Version 0.2, 08.02.2007<br />
    * Version 0.3, 11.02.2007<br />
    * Version 0.4, 01.03.2007<br />
    * Version 0.5, 01.03.2007<br />
    * Version 0.6, 08.03.2007 (Switched to new ConfigurationManager)<br />
    * Version 0.7, 08.06.2007<br />
    * Version 0.8, 08.11.2007 (Switched to new hash offsets)<br />
    */
   public function &getActionByName($actionName) {

      foreach ($this->actionStack as &$action) {
         if ($action->getActionName() == $actionName) {
            return $action;
         }
      }

      // return null, if action could not be found
      $null = null;

      return $null;
   }

   /**
    * Returns the action stack.
    *
    * @return Action[] The front controller action stack.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 05.02.2007<br />
    */
   public function &getActions() {
      return $this->actionStack;
   }

   /**
    * Registers an action with the front controller. This includes action configuration using
    * the action params defined within the action mapping. Each action definition is expected
    * to be stored in the <em>{ENVIRONMENT}_actionconfig.*</em> file under the namespace
    * <em>{$namespace}\{$this->context}.</em>
    * <p/>
    * Using the forth parameter, you can directly register an action URL mapping. URL mappings
    * allow you to shorten action URLs from e.g. <em>VENDOR_foo-action:bar=a:b|c:d</em> to
    * <em>bar=a:b|c:d</em>. For details, please refer to <em>Frontcontroller::registerActionUrlMapping()</em>.
    *
    * @param string $namespace Namespace of the action to register.
    * @param string $name Name of the action to register.
    * @param array $params (Input-) params of the action.
    * @param string $urlToken Name of the action URL mapping token to register along with the action.
    *
    * @deprecated Please use addAction() instead!
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 08.06.2007<br />
    * Version 0.2, 01.07.2007 (Action namespace is now translated at the addAction() method)<br />
    * Version 0.3, 01.07.2007 (Config params are now parsed correctly)<br />
    * Version 0.4, 27.09.2010 (Removed synthetic "actions" sub-namespace)<br />
    * Version 0.5, 19.03.2014 (Added implicit registration of action mapping)<br />
    * Version 0.6, 27.03.2016 (Removed erroneous parameter mapping and set method to deprecated)<br />
    */
   public function registerAction($namespace, $name, array $params = [], $urlToken = null) {

      $this->addAction($namespace, $name, $params);

      // register action URL mapping if desired
      if ($urlToken !== null) {
         $this->registerActionUrlMapping(new ActionUrlMapping($urlToken, $namespace, $name));
      }
   }

   /**
    * Adds an action to the front controller action stack. Please note, that the namespace of
    * the namespace of the action config is added the current context. The name of the
    * config file is concatenated by the current environment and the string
    * <em>*_actionconfig.*</em>.
    * <p/>
    * Using the forth parameter, you can directly register an action URL mapping. URL mappings
    * allow you to shorten action URLs from e.g. <em>VENDOR_foo-action:bar=a:b|c:d</em> to
    * <em>bar=a:b|c:d</em>. For details, please refer to <em>Frontcontroller::registerActionUrlMapping()</em>.
    *
    * @param string $namespace Namespace of the action.
    * @param string $name Name of the action (section key of the config file).
    * @param array $params (Input-)params of the action.
    * @param string $urlToken Name of the action URL mapping token to register along with the action.
    *
    * @throws InvalidArgumentException In case the action cannot be found within the appropriate
    * configuration or the action implementation classes are not available.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 05.06.2007<br />
    * Version 0.2, 01.07.2007<br />
    * Version 0.3, 02.09.2007<br />
    * Version 0.4, 08.09.2007 (Bug-fix: input params from config are now evaluated)<br />
    * Version 0.5, 08.11.2007 (Changed action stack construction to hash offsets)<br />
    * Version 0.6, 21.06.2008 (Replaced APPS__ENVIRONMENT constant with a value from the Registry)<br />
    * Version 0.7, 27.09.2010 (Removed synthetic "actions" sub-namespace)<br />
    * Version 0.8, 09.04.2011 (Made input implementation optional, removed separate action and input class file definition)<br />
    * Version 0.9. 20.08.2013 Jan Wiese (Added support for actions generated by thw DIServiceManager)<br />
    * Version 1.0, 19.03.2014 (Added implicit registration of action mapping)<br />
    */
   public function addAction($namespace, $name, array $params = [], $urlToken = null) {

      // re-map namespace
      $namespace = $this->getActionNamespaceByURLString($namespace);

      // load the action configuration
      $config = $this->getConfiguration($namespace, 'actionconfig.' . self::$configExtension);

      // throw exception, in case the action config is not present
      if (!$config->hasSection($name)) {
         $env = Registry::retrieve('APF\core', 'Environment');
         throw new InvalidArgumentException('[Frontcontroller::addAction()] No config '
               . 'section for action key "' . $name . '" available in configuration file "' . $env
               . '_actionconfig.' . self::$configExtension . '" in namespace "' . $namespace . '" and context "'
               . $this->getContext() . '"!', E_USER_ERROR);
      }

      $actionConfig = $config->getSection($name);

      // evaluate which method to use: simple object or di service
      $actionServiceName = $actionConfig->getValue('ActionServiceName');
      $actionServiceNamespace = $actionConfig->getValue('ActionServiceNamespace');

      if (!(empty($actionServiceName) || empty($actionServiceNamespace))) {
         // use di service
         try {
            $action = $this->getDIServiceObject($actionServiceNamespace, $actionServiceName);
         } catch (Exception $e) {
            throw new InvalidArgumentException('[Frontcontroller::addAction()] Action could not
            be created using DIServiceManager with service name "' . $actionServiceName . '" and service
            namespace "' . $actionServiceNamespace . '". Please check your action and service
            configuration files! Message from DIServiceManager was: ' . $e->getMessage(), $e->getCode());
         }

      } else {
         // use simple object

         // include action implementation
         $actionClass = $actionConfig->getValue('ActionClass');

         // check for class being present
         if (!class_exists($actionClass)) {
            throw new InvalidArgumentException('[Frontcontroller::addAction()] Action class with name "'
                  . $actionClass . '" could not be found. Please check your action configuration file!', E_USER_ERROR);
         }

         // init action
         $action = new $actionClass;
         /* @var $action Action */

         $action->setContext($this->getContext());
         $action->setLanguage($this->getLanguage());

      }

      // init action
      $action->setActionNamespace($namespace);
      $action->setActionName($name);

      // check for custom input implementation
      $inputClass = $actionConfig->getValue('InputClass');

      // include input implementation in case a custom implementation is desired
      if (empty($inputClass)) {
         $inputClass = FrontcontrollerInput::class;
      }

      // check for class being present
      if (!class_exists($inputClass)) {
         throw new InvalidArgumentException('[Frontcontroller::addAction()] Input class with name "' . $inputClass
               . '" could not be found. Please check your action configuration file!', E_USER_ERROR);
      }

      // init input
      $input = new $inputClass;
      /* @var $input FrontcontrollerInput */

      // merge input params with the configured params (params included in the URL are kept!)
      $input->setParameters(
            array_merge(
                  $this->generateParamsFromInputConfig($actionConfig->getValue('InputParams')),
                  $params
            )
      );

      $input->setAction($action);
      $action->setInput($input);

      // set the frontcontroller as a parent object to the action
      $action->setFrontController($this);

      // add the action as a child
      $this->actionStack[] = $action;

      // ID#83: Sort actions to allow prioritization of actions. This is done using
      // uksort() in order to both respect Action::getPriority()
      // and the order of registration for equivalence groups.
      uksort($this->actionStack, [$this, 'sortActions']);

      // register action URL mapping if desired
      if ($urlToken !== null) {
         $this->registerActionUrlMapping(new ActionUrlMapping($urlToken, $namespace, $name));
      }
   }

   /**
    * Creates the url representation of a given namespace.
    *
    * @param string $namespaceUrlRepresentation The url string.
    *
    * @return string The namespace of the action.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 03.06.2007<br />
    */
   protected function getActionNamespaceByURLString($namespaceUrlRepresentation) {
      return str_replace('_', '\\', $namespaceUrlRepresentation);
   }

   /**
    * Create an array from a input param string (scheme: <code>a:b|c:d</code>).
    *
    * @param string $config The config string contained in the action config.
    *
    * @return string[] The resulting param-value array.
    *
    * @author Christian W. Schäfer
    * @version
    * Version 0.1, 08.09.2007<br />
    */
   protected function generateParamsFromInputConfig($config) {

      $inputParams = [];

      $config = trim($config);
      if (strlen($config) > 0) {

         // first: explode couples by "|"
         $params = explode('|', $config);
         $count = count($params);

         for ($i = 0; $i < $count; $i++) {

            // second: explode key and value by ":"
            $pairs = explode(':', $params[$i]);

            if (isset($pairs[0]) && isset($pairs[1]) && !empty($pairs[0]) && !empty($pairs[1])) {
               $inputParams = array_merge($inputParams, [$pairs[0] => $pairs[1]]);
            }
         }
      }

      return $inputParams;
   }

   /**
    * Registers an action URL mapping with the front controller.
    * <p/>
    * Action mappings allow to shorten action instructions within the URL from e.g.
    * <em>VENDOR_foo_bar-action:doIt</em> to <em>doId</em>.
    *
    * @param ActionUrlMapping $mapping The URL mapping to add for actions.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 12.03.2014<br />
    */
   public function registerActionUrlMapping(ActionUrlMapping $mapping) {
      // maintain two indexes for performance reasons
      $this->urlMappingsByToken[$mapping->getUrlToken()] = $mapping;
      $this->urlMappingsByNamespaceAndName[$mapping->getNamespace() . $mapping->getName()] = $mapping;
   }

   /**
    * Registers multiple action URL mappings with the front controller defined within a
    * configuration file specified by it's namespace and name.
    * <p/>
    * For details please refer to Frontcontroller::registerActionUrlMapping().
    *
    * @param string $namespace The configuration file namespace.
    * @param string $name The configuration file name.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.03.2014<br />
    */
   public function registerActionUrlMappings($namespace, $name) {
      $config = $this->getConfiguration($namespace, $name);

      foreach ($config->getSectionNames() as $urlToken) {
         $section = $config->getSection($urlToken);
         $this->registerActionUrlMapping(
               new ActionUrlMapping($urlToken, $section->getValue('ActionNamespace'), $section->getValue('ActionName'))
         );
      }

   }

   /**
    * Returns the list of registered URL tokens that are registered with the front controller.
    *
    * @return string[] The list of registered URL tokens.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 12.03.2014<br />
    */
   public function getActionUrlMappingTokens() {
      return array_keys($this->urlMappingsByToken);
   }

   /**
    * Returns the action URL mapping either by URL token or action namespace and name:
    * <code>
    * $fC->getActionUrlMapping('url-token');
    * $fC->getActionUrlMapping('VENDOR\actions', 'do-something');
    * </code>
    *
    * @param string $tokenOrNamespace The URL token of the mapping or the action namespace.
    * @param string $name The action name.
    *
    * @return ActionUrlMapping|null The desired URL mapping or <em>null</em> in case no mapping is registered..
    */
   public function getActionUrlMapping($tokenOrNamespace, $name = null) {

      // retrieve mapping by token
      if ($name === null) {
         return isset($this->urlMappingsByToken[$tokenOrNamespace])
               ? $this->urlMappingsByToken[$tokenOrNamespace]
               : null;
      }

      // retrieve mapping by action namespace and name
      return isset($this->urlMappingsByNamespaceAndName[$tokenOrNamespace . $name])
            ? $this->urlMappingsByNamespaceAndName[$tokenOrNamespace . $name]
            : null;
   }

   /**
    * Compares two actions to allow sorting of actions.
    * <p/>
    * Actions with a lower priority returned by <em>Action::getPriority()</em>
    * are executed prior to others as described in CR ID#83.
    *
    * @param int $a Offset one for comparison.
    * @param int $b Offset two for comparison.
    *
    * @return int <em>-1</em> in case action <em>$one</em> has lower priority, <em>1</em> in case <em>$two</em> has higher priority. <em>0</em> in case actions are equal.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 11.03.2014<br />
    */
   private function sortActions($a, $b) {
      if ($this->actionStack[$a]->getPriority() == $this->actionStack[$b]->getPriority()) {
         if ($a == $b) {
            return 0;
         }

         return $a > $b ? 1 : -1; // sort equals again to preserve order!
      }

      return $this->actionStack[$a]->getPriority() > $this->actionStack[$b]->getPriority() ? -1 : 1;
   }

}
