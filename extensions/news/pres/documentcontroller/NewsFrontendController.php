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
namespace APF\extensions\news\pres\documentcontroller;

use APF\core\registry\Registry;
use APF\tools\link\LinkGenerator;
use APF\tools\link\Url;

/**
 * Document controller for the frontend of the news.
 *
 * @author Ralf Schubert <ralf.schubert@the-screeze.de>
 * @version
 * Version 1.0, 18.06.2011<br />
 */
class NewsFrontendController extends NewsBaseController {

   public function transformContent() {
      $appKey = $this->getAppKey();

      $newsManager = $this->getNewsManager();
      $newsList = $newsManager->getNewsByPage(null, 'DESC', $appKey);

      if (count($newsList) === 0) {
         $this->getTemplate('noentry')->transformOnPlace();

         return;
      }

      $Cfg = $this->getConfiguration('APF\extensions\news', 'news.ini');
      $allowHtml = ($Cfg->getSection('General')->getValue('AllowHtml') == 'TRUE') ? true : false;

      $List = $this->getIterator('list');

      $Data = [];

      // retrieve the charset from the registry to guarantee interoperability!
      $charset = Registry::retrieve('APF\core', 'Charset');

      foreach ($newsList as &$news) {
         $Date = new \DateTime($news->getProperty('CreationTimestamp'));
         $Author = '';

         if ($news->getAuthor() !== '') {
            $authorTpl = $this->getTemplate('author');
            $authorTpl->setPlaceHolder('authorname', $news->getAuthor());
            $Author = $authorTpl->transformTemplate();
         }

         $Text = $allowHtml ? $news->getText() : htmlentities($news->getText(), ENT_QUOTES, $charset, false);
         $Data[] = [
               'title'  => htmlentities($news->getTitle(), ENT_QUOTES, $charset, false),
               'text'   => $Text,
               'date'   => $Date->format('d.m.Y H:i:s'),
               'author' => $Author
         ];
      }

      $List->fillDataContainer($Data);
      $List->setPlaceHolder('pager', $this->buildPager($appKey));

      $List->transformOnPlace();
   }

   /**
    * Builds the html of the pager.
    *
    * @param string $appKey
    *
    * @return string
    */
   protected function buildPager($appKey) {
      $newsManager = $this->getNewsManager();
      $PageCount = $newsManager->getPageCount($appKey);

      // we don't need a pager for 0 or 1 pages
      if ($PageCount <= 1) {
         return '';
      }

      $Cfg = $this->getConfiguration('APF\extensions\news', 'news.ini');
      $PageParameter = $Cfg->getSection('Paging')->getValue('PageParameter');

      $Page = $newsManager->getPageNumber($appKey);
      $Links = [];
      for ($x = 1; $x <= $PageCount; $x++) {
         $link = LinkGenerator::generateUrl(Url::fromCurrent()->mergeQuery([$PageParameter => $x]));
         if ($Page === $x) {
            $Links[] = '<a href="' . $link . '" class="active">' . $x . '</a>';
         } else {
            $Links[] = '<a href="' . $link . '">' . $x . '</a>';
         }
      }

      $tpl = $this->getTemplate('pager');
      $tpl->setPlaceHolder('pages', implode('&nbsp;&nbsp;|&nbsp;&nbsp;', $Links));

      return $tpl->transformTemplate();
   }

}
