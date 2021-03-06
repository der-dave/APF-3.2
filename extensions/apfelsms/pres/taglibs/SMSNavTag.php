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
namespace APF\extensions\apfelsms\pres\taglibs;

use APF\core\pagecontroller\Document;
use APF\core\pagecontroller\Page;

/**
 * @author Jan Wiese <jan.wiese@adventure-php-framework.org>
 * @version v0.1 (08.08.12)
 *
 */
class SMSNavTag extends Document {


   public function transform() {


      ////
      // fetch attributes from taglib tag

      // navigation level
      $level = $this->getAttribute('level', '0');

      // navigation is relative (in respect to level of current page)
      $rellevel = $this->getAttribute('rellevel', 'false');

      // depth of levels (how many levels are displayed (in submenus))
      $depth = $this->getAttribute('depth', '1');

      // base page id
      $basePageId = $this->getAttribute('basePageId');

      // keep request parameters
      $keepRequestParams = $this->getAttribute('keepRequestParams');


      ////
      // fetch template name and namespace

      $tmplName = $this->getAttribute('template', 'navTaglib');
      $tmplNamespace = $this->getAttribute('namespace', 'APF\extensions\apfelsms\pres\templates');


      $page = new Page();
      $page->setContext($this->getContext());
      $page->setLanguage($this->getLanguage());

      $page->loadDesign($tmplNamespace, $tmplName);

      $rootDoc = $page->getRootDocument();
      $rootDoc->setAttribute('SMSNavLevel', $level);
      $rootDoc->setAttribute('SMSNavRelLevel', $rellevel);
      $rootDoc->setAttribute('SMSNavDepth', $depth);
      $rootDoc->setAttribute('SMSNavBasePageId', $basePageId);
      $rootDoc->setAttribute('SMSBaseNavKeepRequestParams', $keepRequestParams);

      return $page->transform();

   }

}
