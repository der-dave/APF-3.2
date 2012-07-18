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

/**
 * @package core::service
 * @interface APFService
 *
 * Defines the structure of an APF service that is initialized with the <em>ServiceManager</em>.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 27.08.2011<br />
 * Version 0.2, 08.07.2012 (Added constant for service type "CACHED")
 */
interface APFService {

   // these constants define the service type of the APF objects
   const SERVICE_TYPE_NORMAL = 'NORMAL';
   const SERVICE_TYPE_CACHED = 'CACHED';
   const SERVICE_TYPE_SINGLETON = 'SINGLETON';
   const SERVICE_TYPE_SESSION_SINGLETON = 'SESSIONSINGLETON';

   /**
    * @public
    *
    * Sets the context of the current APF object.
    *
    * @param string $context The context.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.02.2010<br />
    */
   public function setContext($context);

   /**
    * @public
    *
    * Returns the context of the current APF object.
    *
    * @return string The context.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.02.2010<br />
    */
   public function getContext();

   /**
    * @public
    *
    * Sets the language of the current APF object.
    *
    * @param string $lang The language.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.02.2010<br />
    */
   public function setLanguage($lang);

   /**
    * @public
    *
    * Returns the language of the current APF object.
    *
    * @return string The language.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.02.2010<br />
    */
   public function getLanguage();

   /**
    * @public
    *
    * Sets the service type of the current APF object.
    *
    * @param string $serviceType The service type.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.02.2010<br />
    */
   public function setServiceType($serviceType);

   /**
    * @public
    *
    * Returns the service type of the current APF object.
    *
    * @return string The service type.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 20.02.2010<br />
    */
   public function getServiceType();

   /**
    * Interface definition of the init() method. This function is used to initialize a service
    * object with the service manager. It must be implemented by derived classes.
    *
    * @param string $initParam The initializing value of the service object. Data type may also be array or object.
    *
    * @author Christian Schäfer
    * @version
    * Version 0.1, 30.03.2007<br />
    */
   public function init($initParam);

}

/**
 * @package core::service
 * @interface APFDIService
 *
 * Defines the structure of an APF service that is initialized with the <em>DIServiceManager</em>.
 *
 * @author Christian Achatz
 * @version
 * Version 0.1, 27.08.2011<br />
 */
interface APFDIService extends APFService {

   /**
    * @public
    *
    * Marks the service as initialized.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.08.2011<br />
    */
   public function markAsInitialized();

   /**
    * @public
    *
    * Marks the service as *not* initialized or *no more* initialized. This causes the DIServiceManager
    * to initialize or re-initialize the service on next access.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.08.2011<br />
    */
   public function markAsPending();

   /**
    * @public
    *
    * Returns the initialization of the present service. In case the service is initialized,
    * the DIServiceManager to omit the call of the setup method defined within the DI service
    * object definition section.
    *
    * @return bool The initialization status (true = initialized, false = not initialized).
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 27.08.2011<br />
    */
   public function isInitialized();

}

